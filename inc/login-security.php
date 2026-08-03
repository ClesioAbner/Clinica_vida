<?php
/**
 * inc/login-security.php
 *
 * Endurecimento do login do WordPress:
 *   • Limite de tentativas por IP com bloqueio temporário (rate limit).
 *   • Cabeçalhos de segurança (equivalente ao "helmet" do Node).
 *   • Mensagens de erro genéricas (evita enumeração de utilizadores).
 *
 * Notas de arquitetura:
 *   - CORS não se aplica: a comunicação front↔back é server-side (PHP), mesma
 *     origem. A proteção contra CSRF é feita por nonces nos formulários.
 *   - O IP é sempre guardado em HASH (privacidade dos dados).
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Login_Guard {

	/** Tentativas falhadas permitidas antes de bloquear. */
	private const MAX_TENTATIVAS = 5;

	/** Janela para acumular tentativas falhadas (1 hora). */
	private const JANELA_SEGUNDOS = HOUR_IN_SECONDS;

	/** Duração do bloqueio depois de exceder as tentativas (4 horas). */
	private const BLOQUEIO_SEGUNDOS = 4 * HOUR_IN_SECONDS;

	/** Marca que o pedido atual foi bloqueado (para não genericizar a mensagem). */
	private static bool $bloqueado = false;

	/**
	 * Bloqueio por IP (rate limit). Desativado durante o desenvolvimento —
	 * ainda não há front de login próprio. Mudar para true para reativar.
	 */
	private const RATE_LIMIT_ATIVO = false;

	/** Regista todos os hooks. Chamado uma vez no fim do ficheiro. */
	public static function init(): void {
		if ( self::RATE_LIMIT_ATIVO ) {
			add_filter( 'authenticate', array( self::class, 'recusar_se_bloqueado' ), 30 );
			add_action( 'wp_login_failed', array( self::class, 'registar_falha' ) );
			add_action( 'wp_login', array( self::class, 'limpar_contador' ) );
		}
		add_filter( 'login_errors', array( self::class, 'mensagem_generica' ) );
		add_action( 'send_headers', array( self::class, 'cabecalhos_seguranca' ) );
	}

	// ---------------------------------------------------------------- IP / chave

	/**
	 * IP do cliente. Usa REMOTE_ADDR (fonte fiável); cabeçalhos de proxy como
	 * X-Forwarded-For são falsificáveis, por isso não os usamos por omissão.
	 */
	private static function ip(): string {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/** Chave do transient: IP em hash + salt -> nunca guardamos o IP em claro. */
	private static function chave(): string {
		return 'clg_' . hash( 'sha256', self::ip() . wp_salt( 'auth' ) );
	}

	// ---------------------------------------------------------------- Rate limit

	/** Antes de autenticar: se o IP está bloqueado, recusa de imediato. */
	public static function recusar_se_bloqueado( $user ) {
		$dados = get_transient( self::chave() );

		if ( is_array( $dados ) && ! empty( $dados['ate'] ) && $dados['ate'] > time() ) {
			self::$bloqueado = true;
			return new WP_Error(
				'clinica_bloqueado',
				sprintf(
					/* translators: %s = tempo restante legível (ex.: "3 horas"). */
					esc_html__( 'Demasiadas tentativas falhadas. Tente novamente dentro de %s.', 'clinica' ),
					human_time_diff( time(), $dados['ate'] )
				)
			);
		}

		return $user;
	}

	/** A cada falha, incrementa o contador do IP e ativa o bloqueio ao exceder. */
	public static function registar_falha(): void {
		$chave = self::chave();
		$dados = get_transient( $chave );

		if ( ! is_array( $dados ) ) {
			$dados = array( 'tentativas' => 0, 'ate' => 0 );
		}

		// Já bloqueado -> mantém o prazo, não conta mais.
		if ( ! empty( $dados['ate'] ) && $dados['ate'] > time() ) {
			return;
		}

		$dados['tentativas']++;

		if ( $dados['tentativas'] >= self::MAX_TENTATIVAS ) {
			$dados['ate'] = time() + self::BLOQUEIO_SEGUNDOS;
			set_transient( $chave, $dados, self::BLOQUEIO_SEGUNDOS );
		} else {
			set_transient( $chave, $dados, self::JANELA_SEGUNDOS );
		}
	}

	/** Login com sucesso -> limpa o contador desse IP. */
	public static function limpar_contador(): void {
		delete_transient( self::chave() );
	}

	// ---------------------------------------------------------------- Mensagens

	/**
	 * Mensagem de erro única para credenciais inválidas -> não revela se o
	 * utilizador existe. Mantém intacta a mensagem de bloqueio.
	 */
	public static function mensagem_generica( $erro ): string {
		if ( self::$bloqueado ) {
			return (string) $erro;
		}
		return esc_html__( 'Credenciais inválidas. Verifique o utilizador e a palavra-passe.', 'clinica' );
	}

	// ---------------------------------------------------------------- Cabeçalhos

	/** Cabeçalhos de segurança no frontend (equivalente ao "helmet"). */
	public static function cabecalhos_seguranca(): void {
		if ( headers_sent() ) {
			return;
		}
		header( 'X-Frame-Options: SAMEORIGIN' );                       // anti-clickjacking
		header( 'X-Content-Type-Options: nosniff' );                   // não adivinhar MIME
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );  // menos fuga de referrer
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
		}
	}
}

Clinica_Login_Guard::init();
