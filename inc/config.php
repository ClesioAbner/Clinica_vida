<?php
/**
 * inc/config.php
 *
 * Configurações da clínica em BD (tabela clinica_config). NENHUMA regra de
 * negócio fica fixa no código — tudo é lido daqui (com cache por pedido).
 * O administrador altera estes valores sem tocar em código.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Config {

	const TABELA     = 'clinica_config';
	/** v2: chaves das integrações (SMS + M-Pesa). Só insere o que falta. */
	const DB_VERSION = '2';

	/** Cache em memória para o pedido atual. */
	private static ?array $cache = null;

	/** Valores por omissão (semente inicial). chave => [valor, tipo]. */
	private static function defaults(): array {
		return array(
			'hora_abertura'          => array( '08:00', 'hora' ),
			'hora_fecho'             => array( '20:00', 'hora' ),
			'intervalo_inicio'       => array( '13:00', 'hora' ),
			'intervalo_fim'          => array( '14:00', 'hora' ),
			'dias_uteis'             => array( '1,2,3,4,5,6', 'lista' ), // 1=Seg … 7=Dom
			'duracao_consulta_min'   => array( '30', 'int' ),
			'max_consultas_dia'      => array( '40', 'int' ),
			'max_consultas_hora'     => array( '5', 'int' ),
			'antecedencia_min_horas' => array( '2', 'int' ),
			'cancelamento_horas'     => array( '24', 'int' ),
			'pagamento_obrigatorio'  => array( '0', 'bool' ),
			'valor_consulta_padrao'  => array( '1000', 'int' ), // MZN
			'moeda'                  => array( 'MZN', 'texto' ),
			// Integrações. Os SEGREDOS (chaves de API) vivem no wp-config.php,
			// nunca aqui — esta tabela é lida por quem tem acesso ao painel.
			'sms_ativo'              => array( '0', 'bool' ),
			'sms_remetente'          => array( 'ClinicaVida', 'texto' ),
			'mpesa_ambiente'         => array( 'sandbox', 'texto' ), // sandbox | producao
			'mpesa_service_code'     => array( '', 'texto' ),
		);
	}

	/** Cria a tabela e semeia os valores por omissão (uma vez). */
	public static function instalar(): void {
		if ( get_option( 'clinica_config_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$t       = self::TABELA;
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
			chave VARCHAR(64) NOT NULL,
			valor TEXT NULL,
			tipo VARCHAR(16) NOT NULL DEFAULT 'texto',
			PRIMARY KEY (chave)
		) {$charset};" );

		foreach ( self::defaults() as $chave => $def ) {
			// Só insere se ainda não existir (não sobrepõe alterações do admin).
			$existe = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$t} WHERE chave = %s", $chave ) );
			if ( ! $existe ) {
				$wpdb->insert( $t, array( 'chave' => $chave, 'valor' => $def[0], 'tipo' => $def[1] ), array( '%s', '%s', '%s' ) );
			}
		}
		update_option( 'clinica_config_db', self::DB_VERSION );
	}

	/** Carrega tudo para cache. */
	private static function carregar(): void {
		if ( null !== self::$cache ) {
			return;
		}
		global $wpdb;
		self::$cache = array();
		$rows = $wpdb->get_results( "SELECT chave, valor FROM " . self::TABELA, ARRAY_A ) ?: array();
		foreach ( $rows as $r ) {
			self::$cache[ $r['chave'] ] = $r['valor'];
		}
	}

	/** Lê uma configuração (string). */
	public static function get( string $chave, $default = null ) {
		self::carregar();
		return array_key_exists( $chave, self::$cache ) ? self::$cache[ $chave ] : $default;
	}

	/** Lê como inteiro. */
	public static function int( string $chave, int $default = 0 ): int {
		$v = self::get( $chave, null );
		return null === $v ? $default : (int) $v;
	}

	/** Lê como booleano. */
	public static function bool( string $chave, bool $default = false ): bool {
		$v = self::get( $chave, null );
		return null === $v ? $default : ( '1' === (string) $v );
	}

	/** Grava/atualiza uma configuração. */
	public static function set( string $chave, string $valor ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO " . self::TABELA . " (chave, valor) VALUES (%s, %s)
			 ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
			$chave, $valor
		) );
		self::$cache = null; // invalida cache
	}

	/** Todas as configurações [chave => valor]. */
	public static function all(): array {
		self::carregar();
		return self::$cache;
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Config', 'instalar' ) );
