<?php
/**
 * inc/webhooks.php
 *
 * Webhooks de saída: a clínica avisa um serviço externo (n8n) quando acontecem
 * ações do utilizador — nova consulta, mudança de estado, pagamento. O n8n
 * reage (ex.: manda WhatsApp ao paciente).
 *
 * Configuração (Clinica_Config ou constantes CLINICA_*):
 *   n8n_webhook_url   → URL do Webhook do n8n (vazio = webhooks desligados).
 *   webhook_secret    → segredo enviado no header X-Clinica-Secret (default: api_key).
 *
 * Envio "fire-and-forget" (blocking=false): nunca atrasa nem quebra a ação do
 * utilizador se o n8n estiver em baixo.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Webhooks {

	public static function url(): string {
		return Clinica_Credenciais::get( 'n8n_webhook_url' );
	}

	public static function ativo(): bool {
		return '' !== self::url();
	}

	/** Envia um evento ao n8n. Silencioso e não bloqueante. */
	public static function enviar( string $evento, array $dados ): void {
		if ( ! self::ativo() ) {
			return;
		}
		$segredo = Clinica_Credenciais::get( 'webhook_secret', Clinica_Credenciais::get( 'api_key' ) );

		wp_remote_post( self::url(), array(
			'timeout'  => 8,
			'blocking' => false, // não espera resposta — não atrasa a receção/receita.
			'headers'  => array(
				'Content-Type'     => 'application/json',
				'X-Clinica-Event'  => $evento,
				'X-Clinica-Secret' => $segredo,
			),
			'body'     => wp_json_encode( array(
				'event'     => $evento,
				'timestamp' => current_time( 'c' ),
				'data'      => $dados,
			) ),
		) );
	}

	// Dados de uma consulta para o payload (inclui telefone, para o WhatsApp). */
	public static function consulta_payload( int $id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT c.id, c.paciente_id, c.medico_id, c.inicio, c.duracao_min, c.status, c.observacoes,
					pu.display_name AS paciente, pu.user_email AS email, pa.telefone,
					mu.display_name AS medico, e.nome AS especialidade
			 FROM " . Clinica_Repo::T_CONSULTAS . " c
			 LEFT JOIN " . Clinica_Repo::T_PACIENTES . " pa ON c.paciente_id = pa.id
			 LEFT JOIN {$wpdb->users} pu ON pa.user_id = pu.ID
			 LEFT JOIN " . Clinica_Repo::T_MEDICOS . " md ON c.medico_id = md.id
			 LEFT JOIN {$wpdb->users} mu ON md.user_id = mu.ID
			 LEFT JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
			 WHERE c.id = %d",
			$id
		), ARRAY_A );
		return $row ?: array( 'id' => $id );
	}

	// Dados de um pagamento para o payload. */
	public static function pagamento_payload( int $id ): array {
		return class_exists( 'Clinica_Pagamentos' ) ? ( Clinica_Pagamentos::obter( $id ) ?: array( 'id' => $id ) ) : array( 'id' => $id );
	}
}

//  Eventos 

// Nova consulta marcada (site, receção ou API).
add_action( 'clinica_consulta_criada', static function ( int $id ): void {
	Clinica_Webhooks::enviar( 'consulta.criada', Clinica_Webhooks::consulta_payload( $id ) );
} );

// Mudança de estado da consulta (confirmada, cancelada, realizada…).
add_action( 'clinica_consulta_estado', static function ( int $id, string $novo ): void {
	Clinica_Webhooks::enviar( 'consulta.' . $novo, Clinica_Webhooks::consulta_payload( $id ) );
}, 10, 2 );

// Reagendamento.
add_action( 'clinica_consulta_reagendada', static function ( int $id ): void {
	Clinica_Webhooks::enviar( 'consulta.reagendada', Clinica_Webhooks::consulta_payload( $id ) );
} );

// Mudança de estado do pagamento (pago, falhou, reembolsado…).
add_action( 'clinica_pagamento_estado', static function ( int $id, string $estado ): void {
	Clinica_Webhooks::enviar( 'pagamento.' . $estado, Clinica_Webhooks::pagamento_payload( $id ) );
}, 10, 2 );
