<?php
/**
 * inc/security.php
 *
 * Endurecimento geral do site (complementa inc/login-security.php).
 *
 * Notas de arquitetura (porque isto é WordPress, não PHP "puro"):
 *   • SQL Injection  -> resolvido com $wpdb->prepare() no repositório.
 *                       (NÃO usamos PDO/MySQLi à parte: seria contornar a
 *                        camada de BD do WP e perder o prepare() nativo.)
 *   • XSS            -> resolvido com esc_html()/esc_attr()/esc_url() nos templates.
 *   • Hashing        -> o WP já usa hashing moderno (bcrypt/phpass) via
 *                       wp_hash_password()/wp_check_password(). Não reinventamos.
 *   • Sessões        -> o WP autentica por COOKIES (não por $_SESSION); por isso
 *                       endurecemos os cookies (SameSite/HttpOnly/Secure).
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Security {

	public static function init(): void {
		// Cookies restritos (SameSite/HttpOnly/Secure).
		self::endurecer_cookies();

		// Menor superfície de ataque / menos fuga de informação.
		add_filter( 'xmlrpc_enabled', '__return_false' );        // desliga XML-RPC (brute force/pingback)
		add_filter( 'the_generator', '__return_empty_string' );  // esconde a versão do WP
		remove_action( 'wp_head', 'wp_generator' );

		// Anti-enumeração de utilizadores (prioridade 0 -> antes do redirect
		// canónico do WP, que senão exporia o login em /author/{login}/).
		add_action( 'template_redirect', array( self::class, 'bloquear_enumeracao_autor' ), 0 );
		add_filter( 'rest_endpoints', array( self::class, 'restringir_rest_users' ) );

		// Bloqueia o editor de ficheiros do admin (se não estiver no wp-config).
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}
	}

	
	//  SameSite=Strict + HttpOnly + Secure(SSL) para o cookie de sessão do PHP.
	// 	Os cookies de autenticação do WP já são HttpOnly e Secure (em HTTPS);
	//  os browsers modernos aplicam SameSite=Lax por omissão a cookies sem o atributo.
	 
	private static function endurecer_cookies(): void {
		if ( headers_sent() || PHP_SESSION_ACTIVE === session_status() ) {
			return;
		}
		session_set_cookie_params( array(
			'lifetime' => 0,
			'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict',
		) );
	}

	// ?author=N ou /author/... -> não expõe o login; redireciona para a home. */
	public static function bloquear_enumeracao_autor(): void {
		if ( is_admin() ) {
			return;
		}
		if ( isset( $_GET['author'] ) || is_author() ) {
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	// Esconde /wp-json/wp/v2/users a quem não tem sessão (anti-enumeração). */
	public static function restringir_rest_users( array $endpoints ): array {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'] );
			unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}
}

Clinica_Security::init();
