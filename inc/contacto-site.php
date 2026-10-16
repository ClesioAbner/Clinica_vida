<?php
/**
 * Backend do formulário de contacto público.
 *
 * Este módulo fica separado de contacto.php porque esse ficheiro contém uma
 * experiência standalone e não pode ser executado durante o bootstrap do tema.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Contacto {

	const TABELA     = 'clinica_mensagens';
	const DB_VERSION = '1';

	/** Cria a tabela de mensagens uma vez por versão. */
	public static function instalar(): void {
		if ( get_option( 'clinica_mensagens_db' ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tabela  = self::TABELA;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$tabela} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			nome VARCHAR(120) NOT NULL,
			email VARCHAR(160) NOT NULL,
			telefone VARCHAR(40) NULL,
			assunto VARCHAR(160) NULL,
			mensagem TEXT NOT NULL,
			criado_em DATETIME NOT NULL,
			lido TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY criado_em (criado_em)
		) {$charset};";

		dbDelta( $sql );
		update_option( 'clinica_mensagens_db', self::DB_VERSION );
	}

	/** Grava uma mensagem. */
	public static function guardar( array $in ): bool {
		global $wpdb;
		self::instalar();

		$ok = $wpdb->insert(
			self::TABELA,
			array(
				'nome'      => $in['nome'],
				'email'     => $in['email'],
				'telefone'  => $in['telefone'],
				'assunto'   => $in['assunto'],
				'mensagem'  => $in['mensagem'],
				'criado_em' => current_time( 'mysql' ),
				'lido'      => 0,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return (bool) $ok;
	}

	/** Lista mensagens, das mais recentes para as mais antigas. */
	public static function listar( int $limit = 50 ): array {
		global $wpdb;
		self::instalar();
		$limit = max( 1, min( 200, $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, nome, email, telefone, assunto, mensagem, criado_em, lido FROM ' . self::TABELA . ' ORDER BY id DESC LIMIT %d',
				$limit
			),
			ARRAY_A
		) ?: array();
	}

	/** Número de mensagens por ler. */
	public static function nao_lidas(): int {
		global $wpdb;
		self::instalar();

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::TABELA . ' WHERE lido = 0' );
	}

	/** Marca uma mensagem como lida. */
	public static function marcar_lida( int $id ): bool {
		global $wpdb;

		return false !== $wpdb->update(
			self::TABELA,
			array( 'lido' => 1 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Contacto', 'instalar' ) );

/** Controlador PRG do formulário de contacto público. */
function clinica_controller_contacto(): void {
	if ( ! isset( $_POST['con_action'] ) ) {
		return;
	}

	if ( ! Clinica_Input::nonce_ok( $_POST, 'con_nonce', 'con_send' ) ) {
		clinica_redirect( 'msg_invalida', 'contacto' );
	}

	$nome     = Clinica_Input::texto( $_POST, 'nome' );
	$email    = Clinica_Input::email( $_POST, 'email' );
	$telefone = Clinica_Input::texto( $_POST, 'telefone' );
	$assunto  = Clinica_Input::texto( $_POST, 'assunto' );
	$mensagem = Clinica_Input::textarea( $_POST, 'mensagem' );

	if ( '' === $nome || '' === $email || '' === $mensagem ) {
		clinica_redirect( 'msg_invalida', 'contacto' );
	}

	Clinica_Contacto::guardar(
		array(
			'nome'     => mb_substr( $nome, 0, 120 ),
			'email'    => mb_substr( $email, 0, 160 ),
			'telefone' => mb_substr( $telefone, 0, 40 ),
			'assunto'  => mb_substr( $assunto, 0, 160 ),
			'mensagem' => $mensagem,
		)
	);

	clinica_redirect( 'msg_enviada', 'contacto' );
}
