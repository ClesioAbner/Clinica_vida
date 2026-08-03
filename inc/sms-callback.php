<?php
/**
 * Endpoint de entrega: o fornecedor chama-nos quando o SMS chega (ou falha).
 *
 *   POST /wp-json/clinica/v1/sms/callback
 *
 * É público (o fornecedor não tem sessão), por isso a autenticidade tem de ser
 * provada: HMAC-SHA256 do corpo cru com CLINICA_SMS_SEGREDO. Sem segredo
 * definido fica fechado — melhor não existir do que aceitar qualquer POST
 * vindo da internet.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', static function (): void {
	register_rest_route(
		'clinica/v1',
		'/sms/callback',
		array(
			'methods'             => 'POST',
			'callback'            => 'clinica_sms_callback',
			'permission_callback' => 'clinica_sms_callback_autorizado',
		)
	);
} );

// ADAPTAR AO FORNECEDOR — o nome do cabeçalho e o esquema da assinatura. */
function clinica_sms_callback_autorizado( WP_REST_Request $pedido ): bool {
	$segredo = Clinica_Credenciais::get( 'sms_segredo' );
	if ( '' === $segredo ) {
		return false;
	}

	$assinatura = (string) $pedido->get_header( 'x_clinica_assinatura' );
	if ( '' === $assinatura ) {
		return false;
	}

	// hash_equals: comparação em tempo constante, contra ataques de temporização.
	return hash_equals( hash_hmac( 'sha256', $pedido->get_body(), $segredo ), $assinatura );
}

// ADAPTAR AO FORNECEDOR — os nomes dos campos do relatório de entrega.
function clinica_sms_callback( WP_REST_Request $pedido ): WP_REST_Response {
	$corpo = (array) $pedido->get_json_params();

	$id     = (string) ( $corpo['id'] ?? $corpo['message_id'] ?? '' );
	$estado = (string) ( $corpo['status'] ?? '' );
	$erro   = (string) ( $corpo['error'] ?? '' );

	if ( '' === $id ) {
		return new WP_REST_Response( array( 'ok' => false, 'erro' => 'Falta o id da mensagem.' ), 400 );
	}

	Clinica_Sms_Log::definir_estado( $id, Clinica_Sms_Log::traduzir( $estado ), $erro );

	// Devolver 200 depressa: se demorarmos, o fornecedor repete o callback.
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}
