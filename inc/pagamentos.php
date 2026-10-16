<?php
/**
 * inc/pagamentos.php
 *
 * Módulo Financeiro (Fase 4). Pagamentos das consultas via M-Pesa, com estados
 * e leitura para a Administração (quem pagou / pendente). Arquitetura extensível:
 * a lógica do meio de pagamento vive atrás de uma interface (Gateway) — hoje
 * M-Pesa, amanhã outro, sem mexer no resto.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;


//Contrato de um meio de pagamento.
 
 
//O módulo financeiro depende deste contrato e não de nenhum operador. Trocar
// M-Pesa por outro (e-Mola, cartão) é escrever uma classe nova — sem tocar aqui.
 
// A implementação real vive em inc/mpesa.php (Clinica_Mpesa).
 


interface Clinica_Gateway_Pagamento {

	
	 // Inicia a cobrança.
	 
	 // @param array $pagamento valor, telefone, referencia, id.
	
	public function iniciar( array $pagamento ): Clinica_Resposta;

	// Está configurado e pronto a cobrar? */
	public function configurado(): bool;

	//Nome do operador, para auditoria e recibos. */
	public function nome(): string;
}

/**
 * Gateway inativo (null-object). Usado quando não há uma implementação de
 * M-Pesa carregada — a receção continua a funcionar (recebe em dinheiro), só
 * não mostra o botão de cobrança M-Pesa, em vez de rebentar com erro fatal.
 */
final class Clinica_Gateway_Inativo implements Clinica_Gateway_Pagamento {
	public function iniciar( array $pagamento ): Clinica_Resposta {
		return Clinica_Resposta::falha( 'Pagamento por M-Pesa indisponível de momento.' );
	}
	public function configurado(): bool {
		return false;
	}
	public function nome(): string {
		return 'mpesa';
	}
}

final class Clinica_Pagamentos {

	const TABELA     = 'clinica_pagamentos';
	const DB_VERSION = '1';
	const ESTADOS    = array( 'pendente', 'pago', 'cancelado', 'falhou', 'reembolsado' );

	// Métodos de pagamento (chave => rótulo). Guardados na coluna `metodo`.
	const METODOS = array(
		'mpesa'     => 'M-Pesa',
		'emola'     => 'e-Mola',
		'numerario' => 'Numerário',
		'cartao'    => 'Cartão',
	);

	public static function instalar(): void {
		if ( get_option( 'clinica_pagamentos_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t  = self::TABELA;
		$cs = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consulta_id BIGINT UNSIGNED NULL,
			paciente_id BIGINT UNSIGNED NOT NULL,
			valor DECIMAL(10,2) NOT NULL DEFAULT 0,
			metodo VARCHAR(20) NOT NULL DEFAULT 'mpesa',
			estado VARCHAR(16) NOT NULL DEFAULT 'pendente',
			referencia VARCHAR(64) NULL,
			telefone VARCHAR(20) NULL,
			criado_em DATETIME NOT NULL,
			atualizado_em DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY consulta_id (consulta_id),
			KEY estado (estado)
		) {$cs};" );
		update_option( 'clinica_pagamentos_db', self::DB_VERSION );
	}

	// Cria (uma vez) o pagamento pendente de uma consulta. */
	public static function criar_para_consulta( int $consulta_id, int $paciente_id, float $valor ): int {
		global $wpdb;
		self::instalar();
		$existe = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM " . self::TABELA . " WHERE consulta_id = %d", $consulta_id ) );
		if ( $existe ) {
			return $existe;
		}
		$wpdb->insert( self::TABELA, array(
			'consulta_id' => $consulta_id,
			'paciente_id' => $paciente_id,
			'valor'       => $valor,
			'metodo'      => 'mpesa',
			'estado'      => 'pendente',
			'criado_em'   => current_time( 'mysql' ),
		), array( '%d', '%d', '%f', '%s', '%s', '%s' ) );
		return (int) $wpdb->insert_id;
	}

	
	 // Gateway em uso. Filtrável para trocar de operador sem editar este ficheiro.
	
	public static function gateway(): Clinica_Gateway_Pagamento {
		// Robusto: se não houver uma implementação Clinica_Mpesa carregada (ex.:
		// inc/mpesa.php a definir outra coisa), usa um gateway inativo em vez de
		// rebentar com erro fatal na receção.
		$gw = class_exists( 'Clinica_Mpesa' )
			? Clinica_Mpesa::a_partir_da_config()
			: new Clinica_Gateway_Inativo();
		return apply_filters( 'clinica_gateway_pagamento', $gw );
	}

	/**
	 * Cobra um pagamento pendente pelo operador (o cliente recebe o pedido de
	 * PIN no telemóvel).
	 *
	 * Só grava 'pago' se o operador confirmar. Uma falha deixa o pagamento
	 * intacto em 'pendente' — a receção pode sempre voltar a tentar ou receber
	 * em dinheiro. Nunca marcar como pago aquilo que não se confirmou.
	 */

	public static function cobrar( int $id, string $telefone = '' ): Clinica_Resposta {
		$pg = self::obter( $id );
		if ( ! $pg ) {
			return Clinica_Resposta::falha( 'Pagamento não encontrado.' );
		}
		if ( 'pago' === $pg['estado'] ) {
			return Clinica_Resposta::falha( 'Este pagamento já está pago.' );
		}

		$gateway = self::gateway();
		if ( ! $gateway->configurado() ) {
			return Clinica_Resposta::falha( 'Operador de pagamento não configurado.' );
		}

		$r = $gateway->iniciar(
			array(
				'valor'      => (float) $pg['valor'],
				'telefone'   => $telefone ?: (string) ( $pg['paciente_tel'] ?? '' ),
				'referencia' => self::numero_recibo( $id ),
				'id'         => $id,
			)
		);

		if ( class_exists( 'Clinica_Auditoria' ) ) {
			// No log fica o motivo detalhado (ex.: erro de SSL/rede); ao operador
			// mostra-se só a mensagem amigável de $r->erro().
			$motivo = (string) $r->campo( 'detalhe', $r->erro() );
			Clinica_Auditoria::registar(
				$r->ok() ? 'Pagamento cobrado (' . $gateway->nome() . ')' : 'Cobrança falhou (' . $gateway->nome() . ')',
				self::numero_recibo( $id ) . ( $r->ok() ? '' : ': ' . $motivo )
			);
		}

		if ( ! $r->ok() ) {
			return $r;
		}

		self::definir_estado( $id, 'pago', (string) $r->campo( 'referencia', '' ), 'mpesa' );
		self::guardar_telefone( $id, $telefone );

		return $r;
	}

	// Garda o número usado na cobrança (fica no histórico do pagamento). 
	private static function guardar_telefone( int $id, string $telefone ): void {
		if ( '' === $telefone ) {
			return;
		}
		global $wpdb;
		$wpdb->update( self::TABELA, array( 'telefone' => substr( $telefone, 0, 20 ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
	}

	// Muda o estado de um pagamento (confirmar, estornar, falhar…). */
	public static function definir_estado( int $id, string $estado, string $referencia = '', string $metodo = '' ): bool {
		if ( ! in_array( $estado, self::ESTADOS, true ) ) {
			return false;
		}
		global $wpdb;
		$dados = array( 'estado' => $estado, 'atualizado_em' => current_time( 'mysql' ) );
		$fmt   = array( '%s', '%s' );
		if ( '' !== $referencia ) {
			$dados['referencia'] = $referencia;
			$fmt[]               = '%s';
		}
		if ( '' !== $metodo && isset( self::METODOS[ $metodo ] ) ) {
			$dados['metodo'] = $metodo;
			$fmt[]           = '%s';
		}
		$ok = false !== $wpdb->update( self::TABELA, $dados, array( 'id' => $id ), $fmt, array( '%d' ) );
		if ( $ok ) {
			do_action( 'clinica_pagamento_estado', $id, $estado );
		}
		return $ok;
	}

	// Lista pagamentos (com nomes) — filtro opcional por estado. */
	public static function listar( string $estado = '' ): array {
		global $wpdb;
		$sql  = "SELECT p.*, pu.display_name AS paciente, c.inicio, e.nome AS esp, mu.display_name AS medico
				 FROM " . self::TABELA . " p
				 LEFT JOIN " . Clinica_Repo::T_PACIENTES . " pa ON p.paciente_id = pa.id
				 LEFT JOIN {$wpdb->users} pu ON pa.user_id = pu.ID
				 LEFT JOIN " . Clinica_Repo::T_CONSULTAS . " c ON p.consulta_id = c.id
				 LEFT JOIN " . Clinica_Repo::T_MEDICOS . " md ON c.medico_id = md.id
				 LEFT JOIN {$wpdb->users} mu ON md.user_id = mu.ID
				 LEFT JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id";
		$args = array();
		if ( in_array( $estado, self::ESTADOS, true ) ) {
			$sql   .= " WHERE p.estado = %s";
			$args[] = $estado;
		}
		$sql .= " ORDER BY p.criado_em DESC";
		return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A ) ?: array();
	}

	// Um pagamento com paciente, consulta, médico e especialidade (para o recibo). */
	public static function obter( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT p.*, pu.display_name AS paciente, pu.user_email AS paciente_email, pa.telefone AS paciente_tel,
					c.inicio, c.duracao_min, e.nome AS esp, mu.display_name AS medico
			 FROM " . self::TABELA . " p
			 LEFT JOIN " . Clinica_Repo::T_PACIENTES . " pa ON p.paciente_id = pa.id
			 LEFT JOIN {$wpdb->users} pu ON pa.user_id = pu.ID
			 LEFT JOIN " . Clinica_Repo::T_CONSULTAS . " c ON p.consulta_id = c.id
			 LEFT JOIN " . Clinica_Repo::T_MEDICOS . " md ON c.medico_id = md.id
			 LEFT JOIN {$wpdb->users} mu ON md.user_id = mu.ID
			 LEFT JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
			 WHERE p.id = %d",
			$id
		), ARRAY_A );
		return $row ?: null;
	}

	// Pagamentos de um paciente (histórico administrativo da receção). */
	public static function do_paciente( int $paciente_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.id, p.valor, p.metodo, p.estado, p.referencia, p.criado_em, p.atualizado_em,
					c.inicio, e.nome AS esp
			 FROM " . self::TABELA . " p
			 LEFT JOIN " . Clinica_Repo::T_CONSULTAS . " c ON p.consulta_id = c.id
			 LEFT JOIN " . Clinica_Repo::T_MEDICOS . " md ON c.medico_id = md.id
			 LEFT JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
			 WHERE p.paciente_id = %d
			 ORDER BY p.criado_em DESC",
			$paciente_id
		), ARRAY_A ) ?: array();
	}

	// Número do recibo a partir do id do pagamento. */
	public static function numero_recibo( int $id ): string {
		return 'RC-' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Recebido agrupado por método de pagamento (só pagamentos 'pago').
	 * Devolve sempre todos os métodos conhecidos (mesmo a zero), para o gráfico
	 * ser estável. @return array<int,array{metodo,label,n,valor}>
	 */
	public static function por_metodo(): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT metodo, COUNT(*) n, SUM(valor) v FROM " . self::TABELA . " WHERE estado = 'pago' GROUP BY metodo", ARRAY_A ) ?: array();
		$out  = array();
		foreach ( self::METODOS as $k => $label ) {
			$out[ $k ] = array( 'metodo' => $k, 'label' => $label, 'n' => 0, 'valor' => 0.0 );
		}
		foreach ( $rows as $r ) {
			$k = (string) $r['metodo'];
			if ( ! isset( $out[ $k ] ) ) {
				$out[ $k ] = array( 'metodo' => $k, 'label' => ucfirst( $k ), 'n' => 0, 'valor' => 0.0 );
			}
			$out[ $k ]['n']     = (int) $r['n'];
			$out[ $k ]['valor'] = (float) $r['v'];
		}
		return array_values( $out );
	}

	// Totais para os KPIs do Financeiro. */
	public static function totais(): array {
		global $wpdb;
		$rows = $wpdb->get_results( "SELECT estado, COUNT(*) n, SUM(valor) v FROM " . self::TABELA . " GROUP BY estado", ARRAY_A ) ?: array();
		$out  = array( 'pago_valor' => 0.0, 'pendente_valor' => 0.0, 'pago_n' => 0, 'pendente_n' => 0 );
		foreach ( $rows as $r ) {
			if ( 'pago' === $r['estado'] ) {
				$out['pago_valor'] = (float) $r['v'];
				$out['pago_n']     = (int) $r['n'];
			}
			if ( 'pendente' === $r['estado'] ) {
				$out['pendente_valor'] = (float) $r['v'];
				$out['pendente_n']     = (int) $r['n'];
			}
		}
		return $out;
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Pagamentos', 'instalar' ) );

// Controlador PRG: a Administração processa/confirma pagamentos. */
function clinica_controller_pagamentos(): void {
	if ( empty( $_POST['pg_action'] ) ) {
		return;
	}
	if ( ! current_user_can( 'clinica_gerir_pagamentos' ) || ! Clinica_Input::nonce_ok( $_POST, 'pg_nonce', 'pg_manage' ) ) {
		clinica_redirect( 'negado' );
	}
	if ( 'pg_estado' === sanitize_key( $_POST['pg_action'] ) ) {
		Clinica_Pagamentos::definir_estado(
			Clinica_Input::inteiro( $_POST, 'id' ),
			Clinica_Input::chave( $_POST, 'estado' ),
			Clinica_Input::texto( $_POST, 'referencia' )
		);
		clinica_redirect( 'pg_ok' );
	}
}
