<?php
/**
 * Histórico de SMS (clinica_sms_log).
 *
 * Existe porque o callback de entrega precisa de um sítio onde aterrar —
 * aceitar um webhook para deitar os dados fora não faz sentido.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Sms_Log {

	const TABELA     = 'clinica_sms_log';
	const DB_VERSION = '1';

	// O nosso vocabulário. O do fornecedor é traduzido para aqui. */
	const ESTADOS = array( 'registado', 'enviado', 'entregue', 'falhou' );

	public static function instalar(): void {
		if ( get_option( 'clinica_sms_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$t  = self::TABELA;
		$cs = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			destino VARCHAR(20) NOT NULL,
			mensagem VARCHAR(255) NOT NULL,
			estado VARCHAR(16) NOT NULL DEFAULT 'enviado',
			fornecedor_id VARCHAR(64) NULL,
			erro VARCHAR(255) NULL,
			criado_em DATETIME NOT NULL,
			atualizado_em DATETIME NULL,
			PRIMARY KEY (id),
			KEY fornecedor_id (fornecedor_id),
			KEY criado_em (criado_em)
		) {$cs};" );

		update_option( 'clinica_sms_db', self::DB_VERSION );
	}

	// ADAPTAR AO FORNECEDOR — cada um inventa os seus estados. */
	public static function traduzir( string $bruto ): string {
		return match ( strtolower( trim( $bruto ) ) ) {
			'delivered', 'delivrd', 'success', 'entregue' => 'entregue',
			'failed', 'undeliverable', 'rejected', 'expired', 'error' => 'falhou',
			default => 'enviado',
		};
	}

	public static function registar( string $destino, string $texto, Clinica_Resposta $r, string $canal ): int {
		global $wpdb;
		self::instalar();
		$wpdb->insert(
			self::TABELA,
			array(
				'destino'       => $destino,
				'mensagem'      => mb_substr( $texto, 0, 255 ),
				'estado'        => $r->ok() ? ( 'sms_registo' === $canal ? 'registado' : 'enviado' ) : 'falhou',
				'fornecedor_id' => (string) $r->campo( 'id', '' ),
				'erro'          => mb_substr( $r->erro(), 0, 255 ),
				'criado_em'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	// Atualiza pelo id do fornecedor — é o que o callback traz. */
	public static function definir_estado( string $fornecedor_id, string $estado, string $erro = '' ): bool {
		if ( '' === $fornecedor_id || ! in_array( $estado, self::ESTADOS, true ) ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->update(
			self::TABELA,
			array( 'estado' => $estado, 'erro' => mb_substr( $erro, 0, 255 ), 'atualizado_em' => current_time( 'mysql' ) ),
			array( 'fornecedor_id' => $fornecedor_id ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);
	}

	public static function fornecedor_id( int $id ): string {
		global $wpdb;
		self::instalar();
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT fornecedor_id FROM " . self::TABELA . " WHERE id = %d", $id ) );
	}

	public static function listar( int $limite = 50 ): array {
		global $wpdb;
		self::instalar();
		$limite = max( 1, min( 200, $limite ) );
		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM " . self::TABELA . " ORDER BY id DESC LIMIT %d", $limite ),
			ARRAY_A
		) ?: array();
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Sms_Log', 'instalar' ) );
