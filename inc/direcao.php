<?php
/**
 * inc/direcao.php
 *
 * Nível EXECUTIVO (Direção). Duas coisas que o executivo faz e que não são
 * leitura: definir METAS (o objetivo contra o qual se lê o desempenho) e
 * decidir APROVAÇÕES estratégicas (abrir especialidade, orçamentos…).
 *
 * A leitura consolidada vive em inc/analitica.php (Clinica_Analitica::direcao).
 * Aqui fica o que muda estado, sempre atrás de capability + nonce.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Direcao {

	const TABELA      = 'clinica_aprovacoes';
	const DB_VERSION  = '1';
	const OPT_META_RECEITA   = 'clinica_meta_receita_mensal';
	const OPT_META_PACIENTES = 'clinica_meta_pacientes_mes';

	/** Tabela das aprovações estratégicas (uma vez). */
	public static function instalar(): void {
		if ( get_option( 'clinica_direcao_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$t       = self::TABELA;

		dbDelta( "CREATE TABLE {$t} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			tipo VARCHAR(40) NOT NULL DEFAULT 'geral',
			titulo VARCHAR(180) NOT NULL,
			detalhe TEXT NULL,
			valor DECIMAL(12,2) NULL,
			estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
			criado_por BIGINT UNSIGNED NOT NULL,
			criado_em DATETIME NOT NULL,
			decidido_por BIGINT UNSIGNED NULL,
			decidido_em DATETIME NULL,
			nota VARCHAR(255) NULL,
			PRIMARY KEY (id),
			KEY estado (estado)
		) {$charset};" );

		update_option( 'clinica_direcao_db', self::DB_VERSION );
	}

	/** Metas atuais (com valores por omissão a zero = sem meta definida). */
	public static function metas(): array {
		return array(
			'receita'   => (float) get_option( self::OPT_META_RECEITA, 0 ),
			'pacientes' => (int) get_option( self::OPT_META_PACIENTES, 0 ),
		);
	}

	/** Aprovações, mais recentes primeiro. Filtra por estado se for pedido. */
	public static function aprovacoes( string $estado = '' ): array {
		global $wpdb;
		$t = self::TABELA;
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$t}'" ) ) {
			return array();
		}
		$sql = "SELECT a.*, uc.display_name AS criador, ud.display_name AS decisor
				FROM {$t} a
				LEFT JOIN {$wpdb->users} uc ON a.criado_por = uc.ID
				LEFT JOIN {$wpdb->users} ud ON a.decidido_por = ud.ID";
		if ( $estado ) {
			$sql .= $wpdb->prepare( ' WHERE a.estado = %s', $estado );
		}
		$sql .= ' ORDER BY a.id DESC';
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	/** Contagem por estado, para os cartões. */
	public static function contagem(): array {
		global $wpdb;
		$t = self::TABELA;
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$t}'" ) ) {
			return array( 'pendente' => 0, 'aprovado' => 0, 'rejeitado' => 0 );
		}
		$rows = $wpdb->get_results( "SELECT estado, COUNT(*) n FROM {$t} GROUP BY estado", ARRAY_A ) ?: array();
		$out  = array( 'pendente' => 0, 'aprovado' => 0, 'rejeitado' => 0 );
		foreach ( $rows as $r ) {
			$out[ $r['estado'] ] = (int) $r['n'];
		}
		return $out;
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Direcao', 'instalar' ) );

/**
 * Garante que a página /direcao/ existe com o template atribuído. Os outros
 * portais foram criados à mão no admin; este cria-se sozinho para não dar
 * "nada encontrado". Idempotente (option-gated).
 */
add_action( 'after_setup_theme', static function (): void {
	if ( get_option( 'clinica_pagina_direcao' ) === '1' ) {
		return;
	}
	$existe = get_page_by_path( 'direcao' );
	if ( ! $existe ) {
		$id = wp_insert_post( array(
			'post_title'   => 'Direção',
			'post_name'    => 'direcao',
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => '',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_wp_page_template', 'page-executivo.php' );
		}
	} else {
		update_post_meta( $existe->ID, '_wp_page_template', 'page-executivo.php' );
	}
	update_option( 'clinica_pagina_direcao', '1' );
} );

/** Controlador PRG da Direção. Corre antes de get_header(). */
function clinica_controller_direcao(): void {
	if ( empty( $_POST['dir_action'] ) ) {
		return;
	}
	$acao = sanitize_key( $_POST['dir_action'] );

	// Definir metas
	if ( 'metas' === $acao ) {
		if ( ! current_user_can( 'clinica_definir_metas' ) || ! wp_verify_nonce( $_POST['dir_nonce'] ?? '', 'dir_metas' ) ) {
			clinica_redirect( 'negado' );
		}
		update_option( Clinica_Direcao::OPT_META_RECEITA, max( 0, (float) ( $_POST['meta_receita'] ?? 0 ) ) );
		update_option( Clinica_Direcao::OPT_META_PACIENTES, max( 0, (int) ( $_POST['meta_pacientes'] ?? 0 ) ) );
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Direção: metas atualizadas', '' );
		}
		clinica_redirect( 'meta_ok', 'metas' );
	}

	// Propor uma decisão estratégica
	if ( 'propor' === $acao ) {
		// Propor é da GESTÃO (sobe à Direção). A Direção não propõe, decide.
		if ( ! current_user_can( 'clinica_propor_estrategico' ) || ! wp_verify_nonce( $_POST['dir_nonce'] ?? '', 'dir_propor' ) ) {
			clinica_redirect( 'negado' );
		}
		$titulo = sanitize_text_field( wp_unslash( $_POST['titulo'] ?? '' ) );
		if ( '' === $titulo ) {
			clinica_redirect( 'prop_dados', 'propostas' );
		}
		global $wpdb;
		$wpdb->insert( Clinica_Direcao::TABELA, array(
			'tipo'       => sanitize_key( $_POST['tipo'] ?? 'geral' ),
			'titulo'     => mb_substr( $titulo, 0, 180 ),
			'detalhe'    => sanitize_textarea_field( wp_unslash( $_POST['detalhe'] ?? '' ) ),
			'valor'      => '' === ( $_POST['valor'] ?? '' ) ? null : (float) $_POST['valor'],
			'estado'     => 'pendente',
			'criado_por' => get_current_user_id(),
			'criado_em'  => current_time( 'mysql' ),
		) );
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Gestão: proposta à Direção', mb_substr( $titulo, 0, 120 ) );
		}
		clinica_redirect( 'prop_ok', 'propostas' );
	}

	// Decidir (aprovar/rejeitar)
	if ( 'decidir' === $acao ) {
		if ( ! current_user_can( 'clinica_aprovar_estrategico' ) || ! wp_verify_nonce( $_POST['dir_nonce'] ?? '', 'dir_decidir' ) ) {
			clinica_redirect( 'negado' );
		}
		$id    = (int) ( $_POST['id'] ?? 0 );
		$dec   = ( 'aprovar' === ( $_POST['decisao'] ?? '' ) ) ? 'aprovado' : 'rejeitado';
		global $wpdb;
		$ok = $wpdb->update(
			Clinica_Direcao::TABELA,
			array(
				'estado'       => $dec,
				'decidido_por' => get_current_user_id(),
				'decidido_em'  => current_time( 'mysql' ),
				'nota'         => mb_substr( sanitize_text_field( wp_unslash( $_POST['nota'] ?? '' ) ), 0, 255 ),
			),
			array( 'id' => $id, 'estado' => 'pendente' )
		);
		if ( $ok && class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Direção: decisão estratégica', ucfirst( $dec ) . ' #' . $id );
		}
		clinica_redirect( $ok ? 'dec_ok' : 'dec_erro', 'aprovacoes' );
	}
}
