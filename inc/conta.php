<?php
/**
 * inc/conta.php
 *
 * Backend da área "Minha Conta": foto de perfil (upload -> biblioteca de média),
 * atualização de dados do utente e leituras (mensagens enviadas). Tudo em BD.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/** URL da foto de perfil: a carregada pelo utente, senão o avatar padrão. */
function clinica_avatar_url( int $user_id, int $size = 200 ): string {
	$att = (int) get_user_meta( $user_id, 'clinica_avatar', true );
	if ( $att ) {
		$url = wp_get_attachment_image_url( $att, 'medium' );
		if ( $url ) {
			return $url;
		}
	}
	return get_avatar_url( $user_id, array( 'size' => $size ) );
}

/** Mensagens de contacto enviadas por este email (fonte: clinica_mensagens). */
function clinica_mensagens_do_email( string $email ): array {
	global $wpdb;
	$t = Clinica_Contacto::TABELA;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT id, assunto, mensagem, criado_em, lido FROM `{$t}` WHERE email = %s ORDER BY criado_em DESC",
		$email
	), ARRAY_A ) ?: array();
}

/** Controlador PRG do perfil: grava nome, telefone, nascimento e foto. */
function clinica_controller_perfil( int $paciente_id, int $user_id ): void {
	if ( ! isset( $_POST['perfil_action'] ) ) {
		return;
	}
	if ( ! Clinica_Input::nonce_ok( $_POST, 'perfil_nonce', 'perfil_save' ) ) {
		clinica_redirect( 'invalido' );
	}

	$nome = Clinica_Input::texto( $_POST, 'nome' );
	if ( '' !== $nome ) {
		wp_update_user( array( 'ID' => $user_id, 'display_name' => $nome, 'first_name' => $nome ) );
	}

	Clinica_Repo::guardar_perfil_paciente( $paciente_id, array(
		'telefone'        => Clinica_Input::texto( $_POST, 'telefone' ),
		'data_nascimento' => Clinica_Input::texto( $_POST, 'data_nascimento' ),
	) );

	// Foto de perfil (opcional) -> biblioteca de média + meta do utilizador.
	if ( ! empty( $_FILES['foto']['name'] ) ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$att = media_handle_upload( 'foto', 0 );
		if ( is_wp_error( $att ) ) {
			clinica_redirect( 'foto_erro' );
		}
		update_user_meta( $user_id, 'clinica_avatar', (int) $att );
	}

	clinica_redirect( 'perfil_ok' );
}
