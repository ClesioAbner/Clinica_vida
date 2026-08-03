<?php
/**
 * SMS: contratos e serviço.
 *
 * O módulo está dividido por responsabilidade:
 *   sms.php           contratos + Clinica_Sms (a porta de entrada)
 *   sms-api.php       os canais (API privada e modo de registo)
 *   sms-log.php       histórico e estados de entrega
 *   sms-mensagens.php os textos da clínica
 *   sms-callback.php  endpoint que o fornecedor chama
 *
 * Segredos vão no wp-config.php, nunca aqui nem na BD:
 *   define( 'CLINICA_SMS_URL',       'https://api.privada.mz/v1' );  // base, sem barra final
 *   define( 'CLINICA_SMS_CHAVE',     '...' );
 *   define( 'CLINICA_SMS_REMETENTE', 'ClinicaVida' );
 *   define( 'CLINICA_SMS_SEGREDO',   '...' );  // assina o callback de entrega
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

interface Clinica_Notificador {

	/** @param string $destino Já normalizado (258XXXXXXXXX). */
	public function enviar( string $destino, string $mensagem ): Clinica_Resposta;

	public function nome(): string;

	public function configurado(): bool;
}

/**
 * Um fornecedor de SMS faz mais do que enviar: consulta entregas e saldo.
 * Fica num contrato à parte para não obrigar canais futuros (email, WhatsApp)
 * a implementar coisas que não têm.
 */
interface Clinica_Sms_Fornecedor extends Clinica_Notificador {

	/** @param array<int,array{destino:string,mensagem:string}> $mensagens */
	public function enviar_lote( array $mensagens ): Clinica_Resposta;

	/** Estado de entrega de uma mensagem já enviada (DLR). */
	public function estado( string $id_fornecedor ): Clinica_Resposta;

	public function saldo(): Clinica_Resposta;
}

/**
 * A porta de entrada. O resto do sistema fala só com esta classe: ela escolhe
 * o canal, valida o número, corta o texto e grava no histórico.
 */
final class Clinica_Sms {

	/** Acima disto o fornecedor divide a mensagem em duas e cobra a dobrar. */
	private const LIMITE = 160;

	private static ?Clinica_Sms_Fornecedor $canal = null;

	public static function canal(): Clinica_Sms_Fornecedor {
		if ( null !== self::$canal ) {
			return self::$canal;
		}

		$url   = Clinica_Credenciais::get( 'sms_url' );
		$chave = Clinica_Credenciais::get( 'sms_chave' );

		$canal = ( '' !== $url && '' !== $chave )
			? new Clinica_Sms_Api( $url, $chave, Clinica_Credenciais::get( 'sms_remetente', 'ClinicaVida' ) )
			: new Clinica_Sms_Registo();

		self::$canal = apply_filters( 'clinica_sms_canal', $canal );
		return self::$canal;
	}

	/** Nunca lança exceção: uma recusa do fornecedor é resultado, não erro. */
	public static function enviar( string $telefone, string $mensagem ): Clinica_Resposta {
		if ( ! self::ativo() ) {
			return Clinica_Resposta::falha( 'Envio de SMS desativado nas definições.' );
		}

		$numero = Clinica_Telefone::normalizar( $telefone );
		if ( '' === $numero ) {
			return Clinica_Resposta::falha( 'Número de telemóvel inválido: ' . $telefone );
		}

		$texto = self::encurtar( $mensagem );
		$canal = self::canal();
		$r     = $canal->enviar( $numero, $texto );

		Clinica_Sms_Log::registar( $numero, $texto, $r, $canal->nome() );
		return $r;
	}

	/** @param array<int,array{telefone:string,mensagem:string}> $mensagens */
	public static function enviar_lote( array $mensagens ): Clinica_Resposta {
		if ( ! self::ativo() ) {
			return Clinica_Resposta::falha( 'Envio de SMS desativado nas definições.' );
		}

		$itens = array();
		foreach ( $mensagens as $m ) {
			$numero = Clinica_Telefone::normalizar( (string) ( $m['telefone'] ?? '' ) );
			if ( '' === $numero ) {
				continue; // Um número mau não pode deitar o lote todo abaixo.
			}
			$itens[] = array( 'destino' => $numero, 'mensagem' => self::encurtar( (string) ( $m['mensagem'] ?? '' ) ) );
		}
		if ( ! $itens ) {
			return Clinica_Resposta::falha( 'Nenhum número válido no lote.' );
		}

		$canal = self::canal();
		$r     = $canal->enviar_lote( $itens );

		foreach ( $itens as $i ) {
			Clinica_Sms_Log::registar( $i['destino'], $i['mensagem'], $r, $canal->nome() );
		}
		return $r;
	}

	/** Pergunta ao fornecedor o estado de entrega e atualiza o histórico. */
	public static function sincronizar_estado( int $id ): Clinica_Resposta {
		$fid = Clinica_Sms_Log::fornecedor_id( $id );
		if ( '' === $fid ) {
			return Clinica_Resposta::falha( 'Mensagem sem identificador do fornecedor.' );
		}

		$r = self::canal()->estado( $fid );
		if ( $r->ok() ) {
			Clinica_Sms_Log::definir_estado( $fid, (string) $r->campo( 'estado', 'enviado' ) );
		}
		return $r;
	}

	public static function saldo(): Clinica_Resposta {
		return self::canal()->saldo();
	}

	public static function historico( int $limite = 50 ): array {
		return Clinica_Sms_Log::listar( $limite );
	}

	public static function ativo(): bool {
		return class_exists( 'Clinica_Config' ) ? Clinica_Config::bool( 'sms_ativo', false ) : false;
	}

	private static function encurtar( string $m ): string {
		$m = trim( preg_replace( '/\s+/', ' ', $m ) ?? '' );
		return mb_strlen( $m ) <= self::LIMITE ? $m : mb_substr( $m, 0, self::LIMITE - 1 ) . '…';
	}
}
