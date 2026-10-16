<?php
/**
 * inc/marcacao-rapida.php
 *
 * Marcação rápida sem conta: numa aflição, a pessoa marca só com nome e
 * telefone, sem criar conta nem palavra-passe. A receção liga para confirmar.
 *
 *  - A ficha do paciente continua ligada a um utilizador WordPress (o modelo
 *    exige-o: clinica_pacientes.user_id), mas é uma conta interna, sem email
 *    nem acesso; a pessoa nunca a vê.
 *  - Se o telefone e o nome já existem numa ficha, reaproveita-a (família que
 *    partilha telefone com nomes diferentes fica em fichas separadas).
 *  - Proteções: nonce, campo-armadilha, limite por IP (3/hora) e por telefone
 *    (2 marcações por confirmar). As horas livres são públicas (só horas).
 *  - Cada consulta guarda a origem (site_rapida, balcao, site) para a receção
 *    saber quem tem de contactar.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

final class Clinica_Marcacao_Rapida {

	/** Pedidos por IP numa hora antes de pedir que liguem. */
	private const LIMITE_IP = 3;

	/** Marcações por confirmar por telefone. */
	private const LIMITE_TELEFONE = 2;

	/** Origem gravada na consulta. */
	public const ORIGEM = 'site_rapida';

	public static function init(): void {
		add_action( 'after_setup_theme', array( self::class, 'migrar' ) );
		// Horas livres também para quem não tem sessão (só horas, nada pessoal).
		add_action( 'wp_ajax_nopriv_clinica_slots', 'clinica_ajax_slots' );
	}

	/** Coluna "origem" nas consultas (idempotente). */
	public static function migrar(): void {
		if ( '1' === get_option( 'clinica_consultas_origem_db' ) ) {
			return;
		}
		global $wpdb;
		$t = Clinica_Repo::T_CONSULTAS;
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'origem'" ) ) {
			$wpdb->query( "ALTER TABLE {$t} ADD COLUMN origem VARCHAR(16) NULL DEFAULT NULL AFTER observacoes" );
		}
		update_option( 'clinica_consultas_origem_db', '1' );
	}

	// Telefone

	/**
	 * Telefone de Moçambique em formato único "+258 8X XXX XXXX".
	 * Aceita 84 123 4567, 841234567, +258841234567, 00258 84… Devolve null se
	 * não for um móvel moçambicano válido (82–87).
	 */
	public static function telefone( string $bruto ): ?string {
		$d = preg_replace( '/\D+/', '', $bruto ) ?? '';
		if ( str_starts_with( $d, '00258' ) ) {
			$d = substr( $d, 5 );
		} elseif ( str_starts_with( $d, '258' ) && strlen( $d ) === 12 ) {
			$d = substr( $d, 3 );
		}
		if ( ! preg_match( '/^8[2-7]\d{7}$/', $d ) ) {
			return null;
		}
		return '+258 ' . substr( $d, 0, 2 ) . ' ' . substr( $d, 2, 3 ) . ' ' . substr( $d, 5 );
	}

	/** Só os 9 dígitos (para comparar números escritos de formas diferentes). */
	private static function digitos( string $tel ): string {
		$d = preg_replace( '/\D+/', '', $tel ) ?? '';
		return strlen( $d ) > 9 ? substr( $d, -9 ) : $d;
	}

	/** Nome comparável: minúsculas, sem acentos, espaços simples. */
	private static function nome_chave( string $nome ): string {
		$n = remove_accents( mb_strtolower( trim( $nome ) ) );
		return (string) preg_replace( '/\s+/', ' ', $n );
	}

	// Ficha

	/**
	 * Ficha para nome + telefone: reaproveita a que tiver o mesmo telefone e o
	 * mesmo nome; senão cria uma conta interna sem email e a ficha.
	 */
	public static function paciente_para( string $nome, string $telefone, string $origem ): int {
		global $wpdb;
		$dig   = self::digitos( $telefone );
		$chave = self::nome_chave( $nome );

		$cands = $wpdb->get_results(
			"SELECT pa.id, pa.telefone, u.display_name
			 FROM " . Clinica_Repo::T_PACIENTES . " pa
			 JOIN {$wpdb->users} u ON u.ID = pa.user_id
			 WHERE pa.telefone IS NOT NULL AND pa.telefone <> ''",
			ARRAY_A
		) ?: array();
		foreach ( $cands as $c ) {
			if ( self::digitos( (string) $c['telefone'] ) === $dig && self::nome_chave( (string) $c['display_name'] ) === $chave ) {
				return (int) $c['id'];
			}
		}

		$login = 'p' . $dig . '-' . strtolower( wp_generate_password( 5, false ) );
		$uid   = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => '',
				'user_pass'    => wp_generate_password( 24, true, true ),
				'display_name' => $nome,
				'first_name'   => $nome,
				'role'         => 'clinica_paciente',
			)
		);
		if ( is_wp_error( $uid ) ) {
			return 0;
		}
		update_user_meta( (int) $uid, 'clinica_conta_interna', $origem );
		$pid = Clinica_Repo::ensure_paciente( (int) $uid );
		Clinica_Repo::guardar_perfil_paciente( $pid, array( 'telefone' => $telefone ) );
		return $pid;
	}

	// Limites

	private static function chave_ip(): string {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		return 'clinica_mr_' . substr( hash( 'sha256', $ip . wp_salt( 'nonce' ) ), 0, 32 );
	}

	/** Marcações do site ainda por confirmar para este telefone. */
	private static function pendentes_do_telefone( string $telefone ): int {
		global $wpdb;
		$dig  = self::digitos( $telefone );
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT pa.telefone FROM " . Clinica_Repo::T_CONSULTAS . " c
			 JOIN " . Clinica_Repo::T_PACIENTES . " pa ON pa.id = c.paciente_id
			 WHERE c.status = 'pendente' AND c.inicio >= %s AND c.origem = %s",
			current_time( 'mysql' ),
			self::ORIGEM
		) ) ?: array();
		return count( array_filter( $rows, static fn( $t ) => self::digitos( (string) $t ) === $dig ) );
	}

	// Pedido

	/**
	 * Trata o POST do formulário público (/marcar/ sem sessão). Termina sempre
	 * num redirecionamento (PRG): sucesso com um código de leitura única, erro
	 * com ?mr=codigo e os campos guardados 10 minutos para não se perderem.
	 */
	public static function controlador(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['mr_action'] ) || is_user_logged_in() ) {
			return;
		}
		$volta = remove_query_arg( array( 'mr', 'rapida', 'f' ) );

		$falhar = static function ( string $code, array $campos = array() ) use ( $volta ): void {
			$args = array( 'mr' => $code );
			if ( $campos ) {
				$f = strtolower( wp_generate_password( 16, false ) );
				set_transient( 'clinica_mr_form_' . $f, $campos, 10 * MINUTE_IN_SECONDS );
				$args['f'] = $f;
			}
			wp_safe_redirect( add_query_arg( $args, $volta ) . '#marcar' );
			exit;
		};

		if ( ! Clinica_Input::nonce_ok( $_POST, 'mr_nonce', 'clinica_marcacao_rapida' ) ) {
			$falhar( 'expirou' );
		}
		// Armadilha: invisível para pessoas, os robôs preenchem.
		if ( '' !== trim( (string) ( $_POST['site_web'] ?? '' ) ) ) {
			wp_safe_redirect( $volta );
			exit;
		}

		$nome   = mb_substr( Clinica_Input::texto( $_POST, 'nome' ), 0, 120 );
		$tel    = self::telefone( Clinica_Input::texto( $_POST, 'telefone' ) );
		$medico = Clinica_Input::inteiro( $_POST, 'medico_id' );
		$inicio = Clinica_Repo::normalizar_inicio( (string) ( $_POST['inicio'] ?? '' ) );
		$motivo = mb_substr( Clinica_Input::texto( $_POST, 'motivo' ), 0, 500 );
		$campos = array( 'nome' => $nome, 'telefone' => Clinica_Input::texto( $_POST, 'telefone' ), 'motivo' => $motivo, 'medico' => $medico );

		if ( mb_strlen( $nome ) < 3 || ! $tel || empty( $_POST['aceito'] ) ) {
			$falhar( 'dados', $campos );
		}
		if ( ! $medico || ! $inicio ) {
			$falhar( 'hora', $campos );
		}
		if ( ! Clinica_Repo::e_futuro( $inicio ) ) {
			$falhar( 'passado', $campos );
		}

		$chave = self::chave_ip();
		$n_ip  = (int) get_transient( $chave );
		if ( $n_ip >= self::LIMITE_IP ) {
			$falhar( 'limite' );
		}
		if ( self::pendentes_do_telefone( $tel ) >= self::LIMITE_TELEFONE ) {
			$falhar( 'telefone', $campos );
		}

		$pid = self::paciente_para( $nome, $tel, self::ORIGEM );
		if ( ! $pid ) {
			$falhar( 'erro', $campos );
		}
		list( $ok, $code ) = Clinica_Repo::criar(
			array(
				'nome_cliente' => $nome,
				'paciente_id'  => $pid,
				'medico_id'    => $medico,
				'inicio'       => $inicio,
				'observacoes'  => $motivo,
				'origem'       => self::ORIGEM,
			)
		);
		if ( ! $ok ) {
			$falhar( 'indisponivel' === $code || 'conflito' === $code ? 'ocupada' : 'erro', $campos );
		}
		set_transient( $chave, $n_ip + 1, HOUR_IN_SECONDS );

		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Site: marcação rápida (sem conta)', $nome );
		}

		$med   = self::medico_info( $medico );
		$token = strtolower( wp_generate_password( 20, false ) );
		set_transient(
			'clinica_mr_ok_' . $token,
			array(
				'nome'     => $nome,
				'telefone' => $tel,
				'inicio'   => $inicio,
				'medico'   => $med ? clinica_nome_medico( (string) $med['nome'] ) : '',
				'esp'      => $med['esp_nome'] ?? '',
			),
			30 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'rapida', $token, $volta ) . '#marcar' );
		exit;
	}

	/** Nome e especialidade de um médico (para o resumo). */
	private static function medico_info( int $id ): ?array {
		global $wpdb;
		$r = $wpdb->get_row( $wpdb->prepare(
			"SELECT u.display_name AS nome, e.nome AS esp_nome
			 FROM " . Clinica_Repo::T_MEDICOS . " md
			 JOIN {$wpdb->users} u ON u.ID = md.user_id
			 LEFT JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON e.id = md.especialidade_id
			 WHERE md.id = %d",
			$id
		), ARRAY_A );
		return $r ?: null;
	}

	/** Resumo da marcação acabada de fazer (só com o código do redirecionamento). */
	public static function confirmacao(): ?array {
		$t = isset( $_GET['rapida'] ) ? sanitize_key( wp_unslash( $_GET['rapida'] ) ) : '';
		if ( '' === $t ) {
			return null;
		}
		$d = get_transient( 'clinica_mr_ok_' . $t );
		return is_array( $d ) ? $d : null;
	}

	/** Campos guardados após um erro (para voltar a preencher o formulário). */
	public static function campos(): array {
		$f = isset( $_GET['f'] ) ? sanitize_key( wp_unslash( $_GET['f'] ) ) : '';
		$d = '' !== $f ? get_transient( 'clinica_mr_form_' . $f ) : false;
		return is_array( $d ) ? $d : array();
	}

	/** Mensagem de erro do formulário público. */
	public static function erro(): string {
		$m = array(
			'dados'    => 'Indique o seu nome, um telefone moçambicano válido (ex.: 84 123 4567) e aceite o contacto.',
			'hora'     => 'Escolha o médico, o dia e uma hora livre.',
			'passado'  => 'Essa hora já passou. Escolha outra.',
			'ocupada'  => 'Essa hora acabou de ser ocupada. Escolha outra hora livre.',
			'limite'   => 'Recebemos vários pedidos deste dispositivo. Ligue-nos e marcamos consigo.',
			'telefone' => 'Este número já tem marcações por confirmar. Aguarde a nossa chamada ou ligue-nos.',
			'expirou'  => 'O formulário expirou. Tente de novo.',
			'erro'     => 'Não foi possível marcar agora. Tente de novo ou ligue-nos.',
		);
		$c = isset( $_GET['mr'] ) ? sanitize_key( wp_unslash( $_GET['mr'] ) ) : '';
		return $m[ $c ] ?? '';
	}

	// Receção

	/** Marcações do site sem conta, por confirmar (de hoje em diante). */
	public static function por_confirmar(): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.id, c.nome_cliente, c.inicio, c.observacoes, c.created_at, pa.telefone,
					mu.display_name AS med_nome, e.nome AS esp_nome
			 FROM " . Clinica_Repo::T_CONSULTAS . " c
			 LEFT JOIN " . Clinica_Repo::T_PACIENTES . " pa ON pa.id = c.paciente_id
			 LEFT JOIN " . Clinica_Repo::T_MEDICOS . " md ON md.id = c.medico_id
			 LEFT JOIN {$wpdb->users} mu ON mu.ID = md.user_id
			 LEFT JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON e.id = md.especialidade_id
			 WHERE c.origem = %s AND c.status = 'pendente' AND c.inicio >= %s
			 ORDER BY c.inicio ASC",
			self::ORIGEM,
			current_time( 'Y-m-d 00:00:00' )
		), ARRAY_A ) ?: array();
	}
}

Clinica_Marcacao_Rapida::init();
