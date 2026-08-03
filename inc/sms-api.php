<?php
/**
 * Canais de SMS: a API privada e o canal de desenvolvimento.
 *
 * Tudo o que é específico do fornecedor está confinado aos métodos marcados
 * ADAPTAR AO FORNECEDOR. Nada fora deste ficheiro sabe como ele fala.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Sms_Api implements Clinica_Sms_Fornecedor {

	//
	// Caminhos relativos à base (CLINICA_SMS_URL).
	// ADAPTAR AO FORNECEDOR — ou sobrepor via filtro clinica_sms_rotas.
	
	// A rota de estado precisa de um identificador do fornecedor, que e devolvido no envio.
	private const ROTAS = array(
		'enviar' => '/messages',
		'lote'   => '/messages/bulk',
		'estado' => '/messages/%s',   // %s = id do fornecedor
		'saldo'  => '/account/balance',
	);

	public function __construct(
		private readonly string $base,
		private readonly string $chave,
		private readonly string $remetente
	) {}

	public function nome(): string {
		return 'sms_api';
	}

	public function configurado(): bool {
		return '' !== $this->base && '' !== $this->chave;
	}

	public function enviar( string $destino, string $mensagem ): Clinica_Resposta {
		$erro = $this->validar( $destino, $mensagem );
		if ( '' !== $erro ) {
			return Clinica_Resposta::falha( $erro );
		}

		$r = Clinica_Http::post_json( $this->url( 'enviar' ), $this->corpo( $destino, $mensagem ), $this->cabecalhos() );
		return $r->ok() ? $this->ler_envio( $r ) : $r;
	}

	public function enviar_lote( array $mensagens ): Clinica_Resposta {
		if ( ! $this->configurado() ) {
			return Clinica_Resposta::falha( 'API de SMS não configurada.' );
		}

		$itens = array();
		foreach ( $mensagens as $m ) {
			$destino  = (string) ( $m['destino'] ?? '' );
			$mensagem = (string) ( $m['mensagem'] ?? '' );
			if ( '' === $this->validar( $destino, $mensagem ) ) {
				$itens[] = $this->corpo( $destino, $mensagem );
			}
		}
		if ( ! $itens ) {
			return Clinica_Resposta::falha( 'Nenhuma mensagem válida no lote.' );
		}

		$r = Clinica_Http::post_json( $this->url( 'lote' ), $this->corpo_lote( $itens ), $this->cabecalhos(), 60 );
		return $r->ok() ? $this->ler_envio( $r ) : $r;
	}

	public function estado( string $id_fornecedor ): Clinica_Resposta {
		if ( '' === $id_fornecedor ) {
			return Clinica_Resposta::falha( 'Falta o identificador da mensagem.' );
		}
		if ( ! $this->configurado() ) {
			return Clinica_Resposta::falha( 'API de SMS não configurada.' );
		}

		$r = Clinica_Http::get( $this->url( 'estado', $id_fornecedor ), array(), $this->cabecalhos() );
		return $r->ok() ? $this->ler_estado( $r ) : $r;
	}

	public function saldo(): Clinica_Resposta {
		if ( ! $this->configurado() ) {
			return Clinica_Resposta::falha( 'API de SMS não configurada.' );
		}

		$r = Clinica_Http::get( $this->url( 'saldo' ), array(), $this->cabecalhos() );
		return $r->ok() ? $this->ler_saldo( $r ) : $r;
	}

	private function validar( string $destino, string $mensagem ): string {
		if ( ! $this->configurado() ) {
			return 'API de SMS não configurada (falta CLINICA_SMS_URL ou CLINICA_SMS_CHAVE).';
		}
		if ( '' === $destino ) {
			return 'Número de telefone inválido.';
		}
		if ( '' === trim( $mensagem ) ) {
			return 'Mensagem vazia.';
		}
		return '';
	}

	// As rotas são filtráveis para adaptar ao fornecedor sem editar código. */
	private function url( string $rota, string ...$args ): string {
		$rotas   = apply_filters( 'clinica_sms_rotas', self::ROTAS );
		$caminho = (string) ( $rotas[ $rota ] ?? self::ROTAS[ $rota ] );
		if ( $args ) {
			$caminho = vsprintf( $caminho, array_map( 'rawurlencode', $args ) );
		}
		return rtrim( $this->base, '/' ) . $caminho;
	}

	// ADAPTAR AO FORNECEDOR — uns usam Bearer, outros X-API-Key ou Basic. */
	private function cabecalhos(): array {
		return array( 'Authorization' => 'Bearer ' . $this->chave );
	}

	// ADAPTAR AO FORNECEDOR — uns usam to/from/message, outros msisdn/sender/text. */
	private function corpo( string $destino, string $mensagem ): array {
		return array(
			'to'      => $destino,
			'from'    => $this->remetente,
			'message' => $mensagem,
		);
	}

	// ADAPTAR AO FORNECEDOR — alguns esperam {"messages":[…]}, outros o array cru. */
	private function corpo_lote( array $itens ): array {
		return array( 'messages' => $itens );
	}

	// ADAPTAR AO FORNECEDOR
	//
	// Muitas APIs de SMS devolvem HTTP 200 com o erro no corpo. Um 200 não
	// significa "enviado" — é preciso ler o estado que vem lá dentro.
	//
	private function ler_envio( Clinica_Resposta $r ): Clinica_Resposta {
		$estado = strtolower( (string) $r->campo( 'status', 'ok' ) );

		if ( in_array( $estado, array( 'error', 'failed', 'rejected' ), true ) ) {
			return Clinica_Resposta::falha(
				(string) $r->campo( 'message', 'O fornecedor recusou a mensagem.' ),
				$r->codigo(),
				$r->dados()
			);
		}

		return Clinica_Resposta::sucesso(
			array(
				'id'     => (string) $r->campo( 'id', '' ),
				'estado' => $estado,
			),
			$r->codigo()
		);
	}

	// ADAPTAR AO FORNECEDOR — normalizar o estado dele para o nosso vocabulário.
	private function ler_estado( Clinica_Resposta $r ): Clinica_Resposta {
		return Clinica_Resposta::sucesso(
			array(
				'id'     => (string) $r->campo( 'id', '' ),
				'estado' => Clinica_Sms_Log::traduzir( (string) $r->campo( 'status', '' ) ),
				'bruto'  => $r->dados(),
			),
			$r->codigo()
		);
	}

	// ADAPTAR AO FORNECEDOR — uns devolvem 'balance', outros 'credits'. */
	private function ler_saldo( Clinica_Resposta $r ): Clinica_Resposta {
		return Clinica_Resposta::sucesso(
			array(
				'saldo' => (float) $r->campo( 'balance', $r->campo( 'credits', 0 ) ),
				'moeda' => (string) $r->campo( 'currency', 'MZN' ),
			),
			$r->codigo()
		);
	}
}

// Canal usado quando não há credenciais: não gasta SMS nem depende da rede. */
final class Clinica_Sms_Registo implements Clinica_Sms_Fornecedor {

	public function nome(): string {
		return 'sms_registo';
	}

	public function configurado(): bool {
		return true;
	}

	public function enviar( string $destino, string $mensagem ): Clinica_Resposta {
		return Clinica_Resposta::sucesso( array( 'id' => '', 'estado' => 'registado' ) );
	}

	public function enviar_lote( array $mensagens ): Clinica_Resposta {
		return Clinica_Resposta::sucesso( array( 'id' => '', 'estado' => 'registado', 'total' => count( $mensagens ) ) );
	}

	public function estado( string $id_fornecedor ): Clinica_Resposta {
		return Clinica_Resposta::falha( 'Sem fornecedor configurado não há estado de entrega.' );
	}

	public function saldo(): Clinica_Resposta {
		return Clinica_Resposta::falha( 'Sem fornecedor configurado  não há saldo.' );
	}
}
