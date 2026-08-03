<?php
/**
 * inc/mpesa-gateway.php
 *
 * Gateway M-Pesa que o SITE usa (contrato Clinica_Gateway_Pagamento):
 *   Clinica_Pagamentos::gateway() -> Clinica_Mpesa -> iniciar() cobra a consulta.
 *
 * Fluxo v2 OpenAPI (o único que autentica — o v1x direto devolve 401):
 *   1) getSession: cifra-se a api_key (RSA+base64) -> GET getSession/ -> SessionID.
 *   2) c2bPayment: cifra-se o SessionID -> é ESSE o Bearer do POST da cobrança.
 *   3) O cliente recebe o pedido de PIN (USSD Push); o M-Pesa responde com INS-*.
 *
 * NOTA: o ficheiro inc/mpesa.php é do Clesio (Clinica_Mpesa_Gateway, experiência
 * v1x) e não é usado pela receção — a receção usa esta classe (Clinica_Mpesa).
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Na máquina de dev o Avast interceta o HTTPS; aponta-se o pedido para um bundle
 * que inclui a raiz do Avast (cacert-dev.pem), mantendo a verificação SSL LIGADA.
 * Em produção o ficheiro não existe e usa-se o CA normal do WordPress.
 */
add_filter( 'http_request_args', static function ( array $args, string $url ): array {
	if ( str_contains( $url, 'openapi.m-pesa.com' ) || str_contains( $url, 'vm.co.mz' ) ) {
		$dev = __DIR__ . '/certs/cacert-dev.pem';
		$moz = __DIR__ . '/certs/cacert.pem';
		if ( is_readable( $dev ) ) {
			$args['sslcertificates'] = $dev;
		} elseif ( is_readable( $moz ) ) {
			$args['sslcertificates'] = $moz;
		}
	}
	return $args;
}, 10, 2 );

final class Clinica_Mpesa implements Clinica_Gateway_Pagamento {

	private const HOST    = 'https://openapi.m-pesa.com';
	private const MERCADO = 'vodacomMOZ';
	private const PAIS    = 'MOZ';
	private const MOEDA   = 'MZN';
	private const SUCESSO = 'INS-0';

	private const CODIGOS = array(
		'INS-0'    => 'Pagamento efetuado com sucesso.',
		'INS-1'    => 'Erro interno do M-Pesa. Tente novamente.',
		'INS-4'    => 'O cliente não tem a conta M-Pesa ativa.',
		'INS-5'    => 'O cliente cancelou a operação no telemóvel.',
		'INS-6'    => 'A transação falhou (PIN incorreto ou recusa).',
		'INS-9'    => 'O pedido expirou (o cliente não introduziu o PIN a tempo).',
		'INS-10'   => 'Transação duplicada — esta referência já foi usada.',
		'INS-13'   => 'Código de comerciante inválido.',
		'INS-15'   => 'Valor inválido.',
		'INS-17'   => 'Referência da transação inválida (use entre 1 e 20 caracteres).',
		'INS-20'   => 'Faltam dados no pedido. Tente novamente.',
		'INS-21'   => 'Validação dos dados falhou. Reveja os campos.',
		'INS-26'   => 'Moeda inválida.',
		'INS-2006' => 'Saldo insuficiente na conta do cliente.',
		'INS-2051' => 'Número de telemóvel inválido.',
	);

	public function __construct(
		private readonly string $api_key,
		private readonly string $public_key,
		private readonly string $service_code,
		private readonly string $ambiente = 'sandbox'
	) {}

	public static function a_partir_da_config(): self {
		return new self(
			Clinica_Credenciais::get( 'mpesa_api_key' ),
			Clinica_Credenciais::get( 'mpesa_public_key' ),
			Clinica_Credenciais::get( 'mpesa_service_code' ),
			Clinica_Credenciais::get( 'mpesa_ambiente', 'sandbox' )
		);
	}

	public function configurado(): bool {
		return '' !== $this->api_key && '' !== $this->public_key && '' !== $this->service_code;
	}

	public function nome(): string {
		return 'mpesa';
	}

	public function iniciar( array $pagamento ): Clinica_Resposta {
		if ( ! $this->configurado() ) {
			return Clinica_Resposta::falha( 'M-Pesa não configurado (faltam as credenciais).' );
		}

		$msisdn = Clinica_Telefone::normalizar( (string) ( $pagamento['telefone'] ?? '' ) );
		if ( '' === $msisdn ) {
			return Clinica_Resposta::falha( 'Número de telemóvel inválido para M-Pesa.' );
		}

		$valor = round( (float) ( $pagamento['valor'] ?? 0 ), 2 );
		if ( $valor <= 0 ) {
			return Clinica_Resposta::falha( 'Valor a cobrar tem de ser maior que zero.' );
		}

		$sessao = $this->sessao();
		if ( '' === $sessao ) {
			return Clinica_Resposta::falha( 'Não foi possível autenticar no M-Pesa. Verifique as credenciais e tente novamente.' );
		}
		$bearer = $this->cifrar( $sessao );
		if ( '' === $bearer ) {
			return Clinica_Resposta::falha( 'Não foi possível cifrar a sessão. Verifique a chave pública.' );
		}

		$r = Clinica_Http::post_json(
			$this->base() . 'c2bPayment/singleStage/',
			array(
				'input_Amount'                   => number_format( $valor, 2, '.', '' ),
				'input_Country'                  => self::PAIS,
				'input_Currency'                 => self::MOEDA,
				'input_CustomerMSISDN'           => $msisdn,
				'input_ServiceProviderCode'      => $this->service_code,
				'input_ThirdPartyConversationID' => $this->conversa_id( (string) ( $pagamento['id'] ?? '' ) ),
				'input_TransactionReference'     => $this->referencia( (string) ( $pagamento['referencia'] ?? '' ) ),
				'input_PurchasedItemsDesc'       => 'Consulta medica',
			),
			array(
				'Authorization' => 'Bearer ' . $bearer,
				'Origin'        => $this->origin(),
			),
			90
		);

		if ( ! $r->ok() && ! isset( $r->dados()['output_ResponseCode'] ) ) {
			$this->esquecer_sessao();
		}

		return $this->ler( $r );
	}

	public function estado( string $conversa_id ): Clinica_Resposta {
		if ( ! $this->configurado() ) {
			return Clinica_Resposta::falha( 'M-Pesa não configurado.' );
		}
		$sessao = $this->sessao();
		if ( '' === $sessao ) {
			return Clinica_Resposta::falha( 'Não foi possível autenticar no M-Pesa.' );
		}
		$bearer = $this->cifrar( $sessao );
		if ( '' === $bearer ) {
			return Clinica_Resposta::falha( 'Não foi possível cifrar a sessão.' );
		}

		$r = Clinica_Http::get(
			$this->base() . 'queryTransactionStatus/',
			array(
				'input_QueryReference'           => $conversa_id,
				'input_ServiceProviderCode'      => $this->service_code,
				'input_ThirdPartyConversationID' => $conversa_id,
				'input_Country'                  => self::PAIS,
			),
			array(
				'Authorization' => 'Bearer ' . $bearer,
				'Origin'        => $this->origin(),
			)
		);

		return $this->ler( $r );
	}

	// ------------------------------------------------------------------ interno

	private function sessao( bool $forcar = false ): string {
		$chave = $this->chave_cache();
		if ( ! $forcar ) {
			$cache = get_transient( $chave );
			if ( is_string( $cache ) && '' !== $cache ) {
				return $cache;
			}
		}

		$bearer = $this->cifrar( $this->api_key );
		if ( '' === $bearer ) {
			return '';
		}

		$r = Clinica_Http::get(
			$this->base() . 'getSession/',
			array(),
			array(
				'Authorization' => 'Bearer ' . $bearer,
				'Origin'        => $this->origin(),
			),
			30
		);

		$sid = (string) $r->campo( 'output_SessionID', '' );
		if ( '' === $sid || self::SUCESSO !== (string) $r->campo( 'output_ResponseCode', '' ) ) {
			return '';
		}

		set_transient( $chave, $sid, 45 * MINUTE_IN_SECONDS );
		return $sid;
	}

	private function esquecer_sessao(): void {
		delete_transient( $this->chave_cache() );
	}

	private function chave_cache(): string {
		return 'clinica_mpesa_sessao_' . substr( md5( $this->api_key . '|' . $this->ambiente ), 0, 16 );
	}

	private function ler( Clinica_Resposta $r ): Clinica_Resposta {
		$dados = $r->dados();

		if ( ! $r->ok() && ! isset( $dados['output_ResponseCode'] ) ) {
			$detalhe = (string) ( $dados['output_error'] ?? $r->erro() );
			return Clinica_Resposta::falha(
				'Não foi possível concluir a cobrança no M-Pesa. Tente novamente ou receba por outro meio.',
				$r->codigo(),
				array( 'detalhe' => $detalhe )
			);
		}

		$codigo = (string) ( $dados['output_ResponseCode'] ?? '' );
		$desc   = self::CODIGOS[ $codigo ] ?? ( (string) ( $dados['output_ResponseDesc'] ?? 'Resposta desconhecida do M-Pesa.' ) );

		if ( self::SUCESSO !== $codigo ) {
			return Clinica_Resposta::falha( $desc, $r->codigo(), array( 'detalhe' => $codigo . ' — ' . $desc ) );
		}

		return Clinica_Resposta::sucesso(
			array(
				'estado'       => 'pago',
				'referencia'   => (string) ( $dados['output_TransactionID'] ?? '' ),
				'conversa'     => (string) ( $dados['output_ConversationID'] ?? '' ),
				'codigo_mpesa' => $codigo,
				'descricao'    => $desc,
			),
			$r->codigo()
		);
	}

	private function cifrar( string $valor ): string {
		$rsa = openssl_pkey_get_public( $this->pem( $this->public_key ) );
		if ( false === $rsa ) {
			return '';
		}
		$cifrado = '';
		if ( ! openssl_public_encrypt( $valor, $cifrado, $rsa, OPENSSL_PKCS1_PADDING ) ) {
			return '';
		}
		return base64_encode( $cifrado );
	}

	private function pem( string $chave ): string {
		if ( str_contains( $chave, 'BEGIN PUBLIC KEY' ) ) {
			return $chave;
		}
		$limpa = preg_replace( '/\s+/', '', $chave ) ?? '';
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( $limpa, 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	private function base(): string {
		$seg = 'producao' === $this->ambiente ? 'openapi' : 'sandbox';
		return self::HOST . '/' . $seg . '/ipg/v2/' . self::MERCADO . '/';
	}

	private function origin(): string {
		return Clinica_Credenciais::get( 'mpesa_origin', '*' );
	}

	private function referencia( string $v ): string {
		$limpa = preg_replace( '/[^0-9A-Za-z]/', '', $v ) ?? '';
		return mb_substr( '' !== $limpa ? $limpa : 'Consulta', 0, 20 );
	}

	private function conversa_id( string $id ): string {
		$sufixo = strtoupper( wp_generate_password( 24, false, false ) );
		return mb_substr( 'CLV' . preg_replace( '/\D/', '', $id ) . $sufixo, 0, 40 );
	}
}
