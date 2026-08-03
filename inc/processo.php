<?php
/**
 * inc/processo.php
 *
 * Processo Clínico: a ficha permanente do paciente. UM por paciente, com número
 * único (PROC-000001). Criado uma vez; todas as consultas ligam-se a ele.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Processo {

	const TABELA     = 'clinica_processos';
	const DB_VERSION = '1';

	/** Cria a tabela do processo (uma vez). */
	public static function instalar(): void {
		if ( get_option( 'clinica_processos_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$t       = self::TABELA;
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			numero VARCHAR(20) NULL,
			paciente_id BIGINT UNSIGNED NOT NULL,
			aberto_em DATETIME NOT NULL,
			estado VARCHAR(16) NOT NULL DEFAULT 'ativo',
			grupo_sanguineo VARCHAR(4) NULL,
			alergias TEXT NULL,
			antecedentes TEXT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY paciente_id (paciente_id),
			UNIQUE KEY numero (numero)
		) {$charset};" );

		update_option( 'clinica_processos_db', self::DB_VERSION );
	}

	/**
	 * Garante que o paciente tem processo; devolve o id do processo.
	 * Nunca cria um segundo processo para o mesmo paciente.
	 */
	public static function ensure( int $paciente_id ): int {
		global $wpdb;
		self::instalar();
		$t = self::TABELA;

		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE paciente_id = %d", $paciente_id ) );
		if ( $id ) {
			return $id;
		}

		$wpdb->insert( $t, array(
			'paciente_id' => $paciente_id,
			'aberto_em'   => current_time( 'mysql' ),
			'estado'      => 'ativo',
		), array( '%d', '%s', '%s' ) );

		$id = (int) $wpdb->insert_id;
		if ( $id ) {
			// Número derivado do id: PROC-000001.
			$wpdb->update( $t, array( 'numero' => self::formatar_numero( $id ) ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
		}
		return $id;
	}

	/** Formata o número do processo. */
	public static function formatar_numero( int $id ): string {
		return 'PROC-' . str_pad( (string) $id, 6, '0', STR_PAD_LEFT );
	}

	/** Dados do processo de um paciente (ou null). */
	public static function do_paciente( int $paciente_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . self::TABELA . " WHERE paciente_id = %d",
			$paciente_id
		), ARRAY_A );
		return $row ?: null;
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Processo', 'instalar' ) );
