<?php
/**
 * inc/auth.php
 *
 * Autenticação da clínica (login + criação de conta), ligada ao backend real
 * (tabela de utilizadores do WordPress). Controlador PRG, com nonce, validação,
 * mensagens de erro claras e auto-login após registo.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/** Painel de destino conforme o papel do utilizador. */
function clinica_painel_do_utilizador(): string {
	// A Direção (executivo) é mais específica que a Gestão (tático): decide primeiro.
	// O administrador tem ambas as capacidades, por isso mantém o destino de gestão.
	if ( current_user_can( 'clinica_ver_direcao' ) && ! current_user_can( 'clinica_gerir_especialidades' ) ) {
		return home_url( '/direcao/' );
	}
	if ( current_user_can( 'clinica_configurar' ) || current_user_can( 'clinica_gerir_especialidades' ) ) {
		return home_url( '/gestao/' );
	}
	if ( current_user_can( 'clinica_atender' ) ) {
		return home_url( '/portal-medico/' );
	}
	if ( current_user_can( 'clinica_cadastrar_paciente' ) || current_user_can( 'clinica_confirmar_presenca' ) ) {
		return home_url( '/recepcao/' );
	}
	return home_url( '/conta/' );
}

/** Destino após entrar/criar conta (respeita redirect_to, senão painel do papel). */
function clinica_auth_destino(): string {
	$r = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
	return $r ?: clinica_painel_do_utilizador();
}

/** Redireciona para a página de entrada com um código de resultado (e aba). */
function clinica_auth_redirect( string $code, string $form = 'login' ): void {
	$url = add_query_arg(
		array( 'cl' => rawurlencode( $code ), 'form' => $form ),
		remove_query_arg( array( '_wpnonce', 'cl', 'form' ) )
	);
	if ( ! headers_sent() ) {
		wp_safe_redirect( $url );
		exit;
	}
}

/** Controlador PRG: trata login e registo. Corre antes de get_header(). */
function clinica_controller_auth(): void {
	if ( ! isset( $_POST['auth_action'] ) || is_user_logged_in() ) {
		return;
	}
	$acao = sanitize_key( $_POST['auth_action'] );

	// Iniciar sessão
	if ( 'login' === $acao ) {
		if ( ! Clinica_Input::nonce_ok( $_POST, 'auth_nonce', 'auth_login' ) ) {
			clinica_auth_redirect( 'invalido', 'login' );
		}
		$user = wp_signon( array(
			'user_login'    => Clinica_Input::texto( $_POST, 'log' ),
			'user_password' => (string) ( $_POST['pwd'] ?? '' ),
			'remember'      => ! empty( $_POST['rememberme'] ),
		), is_ssl() );

		if ( is_wp_error( $user ) ) {
			clinica_auth_redirect( 'login_falhou', 'login' );
		}
		// wp_signon não define o utilizador atual neste pedido: definimos nós,
		// para o encaminhamento por papel ver as capabilities corretas.
		wp_set_current_user( $user->ID );
		wp_safe_redirect( clinica_auth_destino() );
		exit;
	}

	// Criar conta
	if ( 'register' === $acao ) {
		if ( ! Clinica_Input::nonce_ok( $_POST, 'auth_nonce', 'auth_register' ) ) {
			clinica_auth_redirect( 'invalido', 'reg' );
		}
		$nome  = Clinica_Input::texto( $_POST, 'nome' );
		$email = Clinica_Input::email( $_POST, 'email' );
		$pass  = (string) ( $_POST['pwd'] ?? '' );

		if ( '' === $nome || '' === $email || strlen( $pass ) < 6 ) {
			clinica_auth_redirect( 'registo_dados', 'reg' );
		}
		if ( email_exists( $email ) ) {
			clinica_auth_redirect( 'registo_existe', 'reg' );
		}

		// Nome de utilizador único a partir do email.
		$base = sanitize_user( current( explode( '@', $email ) ), true ) ?: 'utente';
		$user = $base;
		$i    = 1;
		while ( username_exists( $user ) ) {
			$user = $base . $i;
			$i++;
		}

		$uid = wp_insert_user( array(
			'user_login'   => $user,
			'user_email'   => $email,
			'user_pass'    => $pass,
			'display_name' => $nome,
			'first_name'   => $nome,
			'role'         => 'subscriber',
		) );

		if ( is_wp_error( $uid ) ) {
			clinica_auth_redirect( 'registo_erro', 'reg' );
		}

		// Auto-login e ficha de paciente criada à primeira marcação.
		wp_set_current_user( (int) $uid );
		wp_set_auth_cookie( (int) $uid, true );
		wp_safe_redirect( clinica_auth_destino() );
		exit;
	}
}
