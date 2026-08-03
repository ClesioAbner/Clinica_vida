<?php
/**
 * inc/integracoes.php
 *
 * Infraestrutura partilhada por todas as integrações externas (M-Pesa, SMS…).
 *
 * Três peças, cada uma com uma responsabilidade:
 *   • Clinica_Credenciais — de onde vêm os segredos (e onde NUNCA devem estar).
 *   • Clinica_Resposta    — o resultado de uma chamada, como objeto e não array.
 *   • Clinica_Http        — o único sítio que fala com a rede.
 *
 * Porquê objetos e não arrays soltos: uma integração falha de muitas maneiras
 * (rede em baixo, credencial errada, saldo insuficiente). Um array `['ok'=>…]`
 * obriga quem chama a adivinhar as chaves; um objeto obriga o compilador a
 * ajudar e mantém o tratamento de erro honesto em todo o lado.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Fonte única de credenciais.
 *
 * Ordem de procura, do mais seguro para o mais cómodo:
 *   1) Constante no wp-config.php  — ex.: CLINICA_MPESA_API_KEY
 *   2) Tabela clinica_config       — para o administrador poder mudar sem deploy
 *   3) Valor por omissão
 *
 * REGRA: segredos (chaves de API, chaves públicas) pertencem ao wp-config.php,
 * que está fora do controlo de versões. A tabela serve para o que NÃO é segredo
 * (ambiente, código do comerciante, remetente do SMS). Nunca comitar chaves.
 */
final class Clinica_Credenciais {

	/** Prefixo das constantes em wp-config.php. */
	private const PREFIXO = 'CLINICA_';

	/**
	 * Lê uma credencial.
	 *
	 * @param string $chave   Nome em snake_case (ex.: 'mpesa_api_key').
	 * @param string $default Valor se não estiver definida em lado nenhum.
	 */
	public static function get( string $chave, string $default = '' ): string {
		$constante = self::PREFIXO . strtoupper( $chave );
		if ( defined( $constante ) ) {
			$v = (string) constant( $constante );
			if ( '' !== $v ) {
				return $v;
			}
		}
		if ( class_exists( 'Clinica_Config' ) ) {
			$v = (string) Clinica_Config::get( $chave, '' );
			if ( '' !== $v ) {
				return $v;
			}
		}
		return $default;
	}

	/** Está configurada? Usar antes de tentar a chamada, para falhar cedo e claro. */
	public static function definida( string $chave ): bool {
		return '' !== self::get( $chave );
	}

	/**
	 * Versão segura para registos/auditoria: mostra só o fim.
	 * Nunca escrever uma credencial inteira num log — logs são copiados,
	 * enviados por email e acabam onde não devem.
	 */
	public static function mascarar( string $valor ): string {
		$n = strlen( $valor );
		if ( 0 === $n ) {
			return '(vazio)';
		}
		return $n <= 4 ? str_repeat( '•', $n ) : str_repeat( '•', 6 ) . substr( $valor, -4 );
	}
}

/**
 * Números de telemóvel moçambicanos.
 *
 * Vive aqui e não no módulo de SMS porque o M-Pesa precisa exatamente da mesma
 * regra — e pagamentos não devem depender de SMS para tratar um número.
 */
final class Clinica_Telefone {

	/**
	 * Aceita "+258 84 210 3344", "842103344" ou "0842103344".
	 *
	 * Móveis em Moçambique: 9 dígitos começados em 8, com o segundo entre 2 e 7
	 * (82/83 Tmcel, 84/85 Vodacom, 86/87 Movitel). Fora disto devolve ''.
	 */
	public static function normalizar( string $telefone ): string {
		$d = preg_replace( '/\D+/', '', $telefone ) ?? '';

		if ( str_starts_with( $d, '258' ) ) {
			$d = substr( $d, 3 );
		} elseif ( str_starts_with( $d, '0' ) ) {
			$d = ltrim( $d, '0' );
		}

		return preg_match( '/^8[2-7]\d{7}$/', $d ) ? '258' . $d : '';
	}

	public static function valido( string $telefone ): bool {
		return '' !== self::normalizar( $telefone );
	}
}

/**
 * Resultado de uma chamada externa (imutável).
 *
 * Nunca é lançada exceção por uma falha *esperada* (recusa do operador, saldo
 * insuficiente): isso é um resultado de negócio, não um bug. Exceções ficam
 * para erros de programação.
 */
final class Clinica_Resposta {

	private function __construct(
		private readonly bool $ok,
		private readonly int $codigo,
		private readonly array $dados,
		private readonly string $erro
	) {}

	/** Chamada bem-sucedida. */
	public static function sucesso( array $dados = array(), int $codigo = 200 ): self {
		return new self( true, $codigo, $dados, '' );
	}

	/** Chamada falhada. $erro é para humanos (já em português). */
	public static function falha( string $erro, int $codigo = 0, array $dados = array() ): self {
		return new self( false, $codigo, $dados, $erro );
	}

	public function ok(): bool { return $this->ok; }
	public function codigo(): int { return $this->codigo; }
	public function dados(): array { return $this->dados; }
	public function erro(): string { return $this->erro; }

	/** Lê um campo do corpo devolvido, sem partir se não existir. */
	public function campo( string $chave, mixed $default = null ): mixed {
		return $this->dados[ $chave ] ?? $default;
	}
}

/**
 * Cliente HTTP.
 *
 * Assenta na API do WordPress (wp_remote_*) e não em cURL directo: assim
 * respeita proxies, filtros e o ambiente do site — e é testável.
 */
final class Clinica_Http {

	/** Segundos. Uma integração de pagamento que pendura bloqueia a receção. */
	private const TIMEOUT = 25;

	/**
	 * POST com corpo JSON.
	 *
	 * @param array<string,string> $cabecalhos Cabeçalhos extra (auth, origin…).
	 */
	public static function post_json( string $url, array $corpo, array $cabecalhos = array(), int $timeout = self::TIMEOUT ): Clinica_Resposta {
		return self::pedido( 'POST', $url, wp_json_encode( $corpo ), $cabecalhos, $timeout );
	}

	/** GET com parâmetros na query string. */
	public static function get( string $url, array $params = array(), array $cabecalhos = array(), int $timeout = self::TIMEOUT ): Clinica_Resposta {
		if ( $params ) {
			$url = add_query_arg( array_map( 'rawurlencode', $params ), $url );
		}
		return self::pedido( 'GET', $url, null, $cabecalhos, $timeout );
	}

	/** Executa e normaliza tudo para Clinica_Resposta. */
	private static function pedido( string $metodo, string $url, ?string $corpo, array $cabecalhos, int $timeout ): Clinica_Resposta {
		$args = array(
			'method'  => $metodo,
			'timeout' => $timeout,
			'headers' => array_merge(
				array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				$cabecalhos
			),
		);
		if ( null !== $corpo ) {
			$args['body'] = $corpo;
		}

		$r = wp_remote_request( $url, $args );

		// Falha de transporte (DNS, TLS, timeout) — nunca chegou ao servidor.
		if ( is_wp_error( $r ) ) {
			return Clinica_Resposta::falha( 'Não foi possível contactar o serviço: ' . $r->get_error_message() );
		}

		$estado = (int) wp_remote_retrieve_response_code( $r );
		$texto  = (string) wp_remote_retrieve_body( $r );
		$dados  = json_decode( $texto, true );
		if ( ! is_array( $dados ) ) {
			$dados = array( 'corpo' => $texto ); // A API respondeu algo que não é JSON.
		}

		if ( $estado < 200 || $estado >= 300 ) {
			return Clinica_Resposta::falha( 'O serviço respondeu com erro (HTTP ' . $estado . ').', $estado, $dados );
		}

		return Clinica_Resposta::sucesso( $dados, $estado );
	}
}
