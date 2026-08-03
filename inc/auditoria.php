<?php
/**
 * inc/auditoria.php
 *
 * Registo de auditoria da clínica (tabela clinica_auditoria). Guarda quem fez
 * o quê e quando — sessões, gestão de equipa/especialidades e alterações às
 * regras. Consultado no painel de Administração.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Auditoria {

	const TABELA     = 'clinica_auditoria';
	const DB_VERSION = '1';

	/** Cria a tabela (uma vez). */
	public static function instalar(): void {
		if ( get_option( 'clinica_auditoria_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$t       = self::TABELA;
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			user_nome VARCHAR(190) NOT NULL DEFAULT '',
			acao VARCHAR(120) NOT NULL DEFAULT '',
			detalhe VARCHAR(255) NOT NULL DEFAULT '',
			criado_em DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY criado_em (criado_em)
		) {$charset};" );

		update_option( 'clinica_auditoria_db', self::DB_VERSION );
	}

	/** Nome da tabela. */
	private static function t(): string {
		return self::TABELA;
	}

	/** Regista uma ação. Se $user_id for null, usa o utilizador atual. */
	public static function registar( string $acao, string $detalhe = '', ?int $user_id = null ): void {
		global $wpdb;
		$uid  = $user_id ?? get_current_user_id();
		$nome = '';
		if ( $uid ) {
			$u    = get_userdata( $uid );
			$nome = $u ? $u->display_name : '';
		}
		$wpdb->insert(
			self::t(),
			array(
				'user_id'   => (int) $uid,
				'user_nome' => mb_substr( $nome, 0, 190 ),
				'acao'      => mb_substr( $acao, 0, 120 ),
				'detalhe'   => mb_substr( $detalhe, 0, 255 ),
				'criado_em' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/** Últimos registos (mais recentes primeiro). */
	public static function listar( int $limit = 50 ): array {
		global $wpdb;
		$limit = max( 1, min( 500, $limit ) );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT id, user_id, user_nome, acao, detalhe, criado_em FROM " . self::t() . " ORDER BY id DESC LIMIT %d", $limit ),
			ARRAY_A
		) ?: array();
	}

	/** Total de registos. */
	public static function total(): int {
		global $wpdb;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::t() );
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Auditoria', 'instalar' ) );

// Regista automaticamente o início de sessão.
add_action( 'wp_login', static function ( $login, $user ): void {
	if ( $user instanceof WP_User ) {
		Clinica_Auditoria::registar( 'Sessão iniciada', '', $user->ID );
	}
}, 10, 2 );
