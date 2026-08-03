<?php
/**
 * inc/clinico.php
 *
 * Núcleo do ATO CLÍNICO (Bloco 1 da reestruturação). É o nível operacional que
 * protege a integridade de tudo o que os níveis tático/executivo consomem.
 *
 * Regras estruturais aqui garantidas:
 *   • Registo clínico APPEND-ONLY — só INSERT. Correções fazem-se por aditamento
 *     (nova entrada que referencia a original), nunca por UPDATE/DELETE destrutivo.
 *   • Encerramento de consulta BLOQUEADO sem diagnóstico (CID-10).
 *   • Só o médico ATRIBUÍDO à consulta pode registar o atendimento (âmbito).
 *   • Tudo ligado ao Processo Clínico do paciente (um por paciente).
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Clinico {

	const T_NOTAS        = 'clinica_notas';         // nota SOAP por consulta
	const T_DIAGNOSTICOS = 'clinica_diagnosticos';  // diagnóstico codificado (CID-10)
	const T_RECEITAS     = 'clinica_receitas';      // prescrição de medicamentos
	const T_EXAMES       = 'clinica_exames';        // pedidos de exame
	const T_DOCUMENTOS   = 'clinica_documentos';    // anexos ao processo
	const DB_VERSION     = '1';
	const FLUXO_VERSION  = '1';
	const MOD_VERSION    = '1';
	const RETORNO_VERSION = '1';

	/** Cria as tabelas clínicas (uma vez). Append-only por convenção. */
	public static function instalar(): void {
		if ( get_option( 'clinica_clinico_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$tn = self::T_NOTAS;
		dbDelta( "CREATE TABLE {$tn} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consulta_id BIGINT UNSIGNED NOT NULL,
			processo_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			paciente_id BIGINT UNSIGNED NOT NULL,
			medico_id BIGINT UNSIGNED NOT NULL,
			subjetivo TEXT NULL,
			objetivo TEXT NULL,
			avaliacao TEXT NULL,
			plano TEXT NULL,
			adenda_de BIGINT UNSIGNED NULL,
			criado_em DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY paciente_id (paciente_id),
			KEY consulta_id (consulta_id)
		) {$charset};" );

		$td = self::T_DIAGNOSTICOS;
		dbDelta( "CREATE TABLE {$td} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consulta_id BIGINT UNSIGNED NOT NULL,
			processo_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			paciente_id BIGINT UNSIGNED NOT NULL,
			medico_id BIGINT UNSIGNED NOT NULL,
			cid10 VARCHAR(10) NOT NULL,
			descricao VARCHAR(255) NULL,
			criado_em DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY paciente_id (paciente_id),
			KEY consulta_id (consulta_id)
		) {$charset};" );

		update_option( 'clinica_clinico_db', self::DB_VERSION );
	}

	/**
	 * Módulos clínicos: prescrições, pedidos de exame e documentos do processo.
	 * Append-only por convenção — o que se prescreve/pede fica registado.
	 */
	public static function instalar_modulos(): void {
		if ( get_option( 'clinica_modclin_db' ) === self::MOD_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		$tr = self::T_RECEITAS;
		dbDelta( "CREATE TABLE {$tr} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consulta_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			paciente_id BIGINT UNSIGNED NOT NULL,
			medico_id BIGINT UNSIGNED NOT NULL,
			medicamento VARCHAR(160) NOT NULL,
			dosagem VARCHAR(80) NULL,
			posologia VARCHAR(160) NULL,
			duracao VARCHAR(80) NULL,
			notas TEXT NULL,
			criado_em DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY paciente_id (paciente_id),
			KEY consulta_id (consulta_id)
		) {$charset};" );

		$te = self::T_EXAMES;
		dbDelta( "CREATE TABLE {$te} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			consulta_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			paciente_id BIGINT UNSIGNED NOT NULL,
			medico_id BIGINT UNSIGNED NOT NULL,
			exame VARCHAR(160) NOT NULL,
			tipo VARCHAR(60) NULL,
			urgencia VARCHAR(20) NOT NULL DEFAULT 'normal',
			notas TEXT NULL,
			estado VARCHAR(20) NOT NULL DEFAULT 'pedido',
			criado_em DATETIME NOT NULL,
			resultado_em DATETIME NULL,
			PRIMARY KEY (id),
			KEY paciente_id (paciente_id),
			KEY consulta_id (consulta_id)
		) {$charset};" );

		$td = self::T_DOCUMENTOS;
		dbDelta( "CREATE TABLE {$td} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			paciente_id BIGINT UNSIGNED NOT NULL,
			processo_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			consulta_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			titulo VARCHAR(160) NOT NULL,
			tipo VARCHAR(60) NULL,
			anexo_id BIGINT UNSIGNED NOT NULL,
			enviado_por BIGINT UNSIGNED NOT NULL,
			criado_em DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY paciente_id (paciente_id)
		) {$charset};" );

		update_option( 'clinica_modclin_db', self::MOD_VERSION );
	}

	/** Prescrições de um paciente (mais recentes primeiro). */
	public static function receitas_do_paciente( int $paciente_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT r.*, u.display_name AS med_nome
			 FROM " . self::T_RECEITAS . " r
			 LEFT JOIN " . Clinica_Repo::T_MEDICOS . " m ON r.medico_id = m.id
			 LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
			 WHERE r.paciente_id = %d ORDER BY r.criado_em DESC, r.id DESC",
			$paciente_id
		), ARRAY_A ) ?: array();
	}

	/** Pedidos de exame de um paciente (mais recentes primeiro). */
	public static function exames_do_paciente( int $paciente_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT e.*, u.display_name AS med_nome
			 FROM " . self::T_EXAMES . " e
			 LEFT JOIN " . Clinica_Repo::T_MEDICOS . " m ON e.medico_id = m.id
			 LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID
			 WHERE e.paciente_id = %d ORDER BY e.criado_em DESC, e.id DESC",
			$paciente_id
		), ARRAY_A ) ?: array();
	}

	/** Documentos anexados ao processo do paciente. */
	public static function documentos_do_paciente( int $paciente_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . self::T_DOCUMENTOS . " WHERE paciente_id = %d ORDER BY criado_em DESC, id DESC",
			$paciente_id
		), ARRAY_A ) ?: array();
	}

	/** Regista uma prescrição. Devolve [ok, código]. */
	public static function prescrever( int $paciente_id, int $medico_id, int $consulta_id, array $in ): array {
		global $wpdb;
		$med = trim( (string) ( $in['medicamento'] ?? '' ) );
		if ( '' === $med || ! $paciente_id ) {
			return array( false, 'presc_dados' );
		}
		$ok = $wpdb->insert( self::T_RECEITAS, array(
			'consulta_id' => $consulta_id,
			'paciente_id' => $paciente_id,
			'medico_id'   => $medico_id,
			'medicamento' => $med,
			'dosagem'     => (string) ( $in['dosagem'] ?? '' ),
			'posologia'   => (string) ( $in['posologia'] ?? '' ),
			'duracao'     => (string) ( $in['duracao'] ?? '' ),
			'notas'       => (string) ( $in['notas'] ?? '' ),
			'criado_em'   => current_time( 'mysql' ),
		), array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		if ( ! $ok ) {
			return array( false, 'erro' );
		}
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Médico: prescrição registada', $med );
		}
		return array( true, 'presc_ok' );
	}

	/**
	 * Pasta protegida dos documentos clínicos. Aplicada por filtro só durante o
	 * upload, para não afetar o resto da biblioteca de media.
	 */
	public static function dir_protegida( array $dirs ): array {
		$sub            = '/clinica-processos';
		$dirs['path']   = $dirs['basedir'] . $sub;
		$dirs['url']    = $dirs['baseurl'] . $sub;
		$dirs['subdir'] = $sub;
		return $dirs;
	}

	/**
	 * Garante que a pasta existe e que o servidor recusa acesso direto.
	 * Sem isto, qualquer pessoa com o link veria um documento clínico.
	 */
	private static function garantir_protecao(): void {
		$up  = wp_upload_dir();
		$dir = $up['basedir'] . '/clinica-processos';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$ht = $dir . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			file_put_contents(
				$ht,
				"# Documentos clínicos: sem acesso direto pelo browser.\n"
				. "# A entrega é feita por endpoint que verifica sessão e permissão.\n"
				. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n"
			);
		}
		if ( ! file_exists( $dir . '/index.html' ) ) {
			file_put_contents( $dir . '/index.html', '' );
		}
	}

	/**
	 * Entrega um documento clínico só a quem tem direito a vê-lo.
	 * Substitui o acesso direto ao ficheiro: verifica sessão, permissão e audita.
	 */
	public static function servir_documento(): void {
		if ( ! isset( $_GET['clinica_doc'] ) ) {
			return;
		}
		$id = (int) $_GET['clinica_doc'];
		if ( ! is_user_logged_in() || ! $id ) {
			wp_die( esc_html__( 'Sem permissão para ver este documento.', 'clinica' ), '', array( 'response' => 403 ) );
		}

		global $wpdb;
		$doc = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, paciente_id, anexo_id, titulo FROM " . self::T_DOCUMENTOS . " WHERE id = %d",
			$id
		), ARRAY_A );
		if ( ! $doc ) {
			wp_die( esc_html__( 'Documento não encontrado.', 'clinica' ), '', array( 'response' => 404 ) );
		}

		// Clínico, receção, ou o próprio paciente.
		$pode = current_user_can( 'clinica_ver_processo' ) || current_user_can( 'clinica_cadastrar_paciente' );
		if ( ! $pode ) {
			$pac  = Clinica_Repo::paciente_ficha( (int) $doc['paciente_id'] );
			$pode = $pac && (int) $pac['user_id'] === get_current_user_id();
		}
		if ( ! $pode ) {
			wp_die( esc_html__( 'Sem permissão para ver este documento.', 'clinica' ), '', array( 'response' => 403 ) );
		}

		$path = get_attached_file( (int) $doc['anexo_id'] );
		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'Ficheiro indisponível.', 'clinica' ), '', array( 'response' => 404 ) );
		}

		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Documento clínico consultado', $doc['titulo'] );
		}

		$mime = get_post_mime_type( (int) $doc['anexo_id'] ) ?: 'application/octet-stream';
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: inline; filename="' . basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: default-src \'none\'' );
		readfile( $path );
		exit;
	}

	/** URL de entrega protegida de um documento. */
	public static function url_documento( int $doc_id ): string {
		return add_query_arg( 'clinica_doc', $doc_id, home_url( '/' ) );
	}

	/**
	 * Anexa um documento ao processo do paciente (exame digitalizado, relatório…).
	 * Só aceita PDF e imagens, e passa pelo tratamento de ficheiros do WordPress
	 * (que valida o conteúdo real, não só a extensão).
	 *
	 * @return array{0:bool,1:string}
	 */
	public static function anexar_documento( int $paciente_id, int $consulta_id, array $in, array $file ): array {
		$titulo = trim( (string) ( $in['titulo'] ?? '' ) );
		if ( ! $paciente_id || '' === $titulo ) {
			return array( false, 'doc_dados' );
		}
		if ( empty( $file['name'] ) || ! empty( $file['error'] ) ) {
			return array( false, 'doc_ficheiro' );
		}

		// Lista branca de tipos — nada de executáveis no processo clínico.
		$permitidos = array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
		);
		$check = wp_check_filetype( $file['name'], $permitidos );
		if ( empty( $check['ext'] ) ) {
			return array( false, 'doc_tipo' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Documento clínico vai para pasta fechada, nunca para a media pública.
		self::garantir_protecao();
		add_filter( 'upload_dir', array( __CLASS__, 'dir_protegida' ) );
		$_FILES['clinica_doc'] = $file;
		$anexo_id = media_handle_upload( 'clinica_doc', 0, array(), array( 'test_form' => false, 'mimes' => $permitidos ) );
		remove_filter( 'upload_dir', array( __CLASS__, 'dir_protegida' ) );
		if ( is_wp_error( $anexo_id ) ) {
			return array( false, 'doc_erro' );
		}

		global $wpdb;
		$processo_id = class_exists( 'Clinica_Processo' ) ? (int) Clinica_Processo::ensure( $paciente_id ) : 0;
		$ok = $wpdb->insert( self::T_DOCUMENTOS, array(
			'paciente_id' => $paciente_id,
			'processo_id' => $processo_id,
			'consulta_id' => $consulta_id,
			'titulo'      => $titulo,
			'tipo'        => (string) ( $in['tipo'] ?? '' ),
			'anexo_id'    => (int) $anexo_id,
			'enviado_por' => get_current_user_id(),
			'criado_em'   => current_time( 'mysql' ),
		), array( '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%s' ) );

		if ( ! $ok ) {
			wp_delete_attachment( (int) $anexo_id, true ); // não deixar ficheiro órfão
			return array( false, 'erro' );
		}
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Médico: documento anexado ao processo', $titulo );
		}
		return array( true, 'doc_ok' );
	}

	/** Regista um pedido de exame. Devolve [ok, código]. */
	public static function pedir_exame( int $paciente_id, int $medico_id, int $consulta_id, array $in ): array {
		global $wpdb;
		$ex = trim( (string) ( $in['exame'] ?? '' ) );
		if ( '' === $ex || ! $paciente_id ) {
			return array( false, 'exame_dados' );
		}
		$ok = $wpdb->insert( self::T_EXAMES, array(
			'consulta_id' => $consulta_id,
			'paciente_id' => $paciente_id,
			'medico_id'   => $medico_id,
			'exame'       => $ex,
			'tipo'        => (string) ( $in['tipo'] ?? '' ),
			'urgencia'    => in_array( ( $in['urgencia'] ?? '' ), array( 'normal', 'urgente' ), true ) ? $in['urgencia'] : 'normal',
			'notas'       => (string) ( $in['notas'] ?? '' ),
			'estado'      => 'pedido',
			'criado_em'   => current_time( 'mysql' ),
		), array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		if ( ! $ok ) {
			return array( false, 'erro' );
		}
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Médico: exame pedido', $ex );
		}
		return array( true, 'exame_ok' );
	}

	/**
	 * Migração do fluxo de atendimento: alarga o enum de estados da consulta e
	 * acrescenta os tempos do ato (chamada, início, motivo de pausa).
	 * Idempotente: só corre uma vez e verifica cada coluna antes de a criar.
	 */
	public static function instalar_fluxo(): void {
		if ( get_option( 'clinica_fluxo_db' ) === self::FLUXO_VERSION ) {
			return;
		}
		global $wpdb;
		$t = Clinica_Repo::T_CONSULTAS;

		$wpdb->query(
			"ALTER TABLE {$t} MODIFY status
			 ENUM('pendente','confirmada','chamada','em_atendimento','pausada','cancelada','realizada','faltou')
			 NOT NULL DEFAULT 'pendente'"
		);

		$colunas = array(
			'chamado_em'   => "ALTER TABLE {$t} ADD COLUMN chamado_em DATETIME NULL DEFAULT NULL AFTER checkin_em",
			'iniciado_em'  => "ALTER TABLE {$t} ADD COLUMN iniciado_em DATETIME NULL DEFAULT NULL AFTER chamado_em",
			'pausa_motivo' => "ALTER TABLE {$t} ADD COLUMN pausa_motivo VARCHAR(160) NULL DEFAULT NULL AFTER iniciado_em",
		);
		foreach ( $colunas as $col => $sql ) {
			if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE '{$col}'" ) ) {
				$wpdb->query( $sql );
			}
		}

		update_option( 'clinica_fluxo_db', self::FLUXO_VERSION );
	}

	/**
	 * Necessidade de retorno. O médico assinala que o paciente deve voltar e
	 * em que prazo; quem marca a data efetiva continua a ser a receção.
	 */
	public static function instalar_retorno(): void {
		if ( get_option( 'clinica_retorno_db' ) === self::RETORNO_VERSION ) {
			return;
		}
		global $wpdb;
		$t = Clinica_Repo::T_CONSULTAS;

		$colunas = array(
			'retorno_em'   => "ALTER TABLE {$t} ADD COLUMN retorno_em DATE NULL DEFAULT NULL",
			'retorno_nota' => "ALTER TABLE {$t} ADD COLUMN retorno_nota VARCHAR(200) NULL DEFAULT NULL",
		);
		foreach ( $colunas as $col => $sql ) {
			if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE '{$col}'" ) ) {
				$wpdb->query( $sql );
			}
		}

		update_option( 'clinica_retorno_db', self::RETORNO_VERSION );
	}

	/**
	 * Fluxo de atendimento do médico: chamar -> iniciar -> (pausar/retomar) -> concluir.
	 * A conclusão faz-se em registar_atendimento (exige diagnóstico), não aqui.
	 * O médico NÃO confirma, cancela nem remarca — isso é da receção.
	 *
	 * @return array{0:bool,1:string} [ok, código de mensagem]
	 */
	public static function fluxo( int $consulta_id, int $medico_id, string $acao, string $motivo = '' ): array {
		global $wpdb;
		$t = Clinica_Repo::T_CONSULTAS;

		$c = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, medico_id, status, checkin_em FROM {$t} WHERE id = %d",
			$consulta_id
		), ARRAY_A );

		if ( ! $c ) {
			return array( false, 'invalido' );
		}
		// Âmbito: só o médico atribuído mexe no fluxo da sua consulta.
		if ( (int) $c['medico_id'] !== $medico_id ) {
			return array( false, 'negado' );
		}

		// Transições permitidas (tudo o resto é recusado).
		$mapa = array(
			'chamar'  => array( 'de' => array( 'pendente', 'confirmada' ), 'para' => 'chamada' ),
			'iniciar' => array( 'de' => array( 'chamada' ),                'para' => 'em_atendimento' ),
			'pausar'  => array( 'de' => array( 'em_atendimento' ),         'para' => 'pausada' ),
			'retomar' => array( 'de' => array( 'pausada' ),                'para' => 'em_atendimento' ),
		);
		if ( ! isset( $mapa[ $acao ] ) || ! in_array( $c['status'], $mapa[ $acao ]['de'], true ) ) {
			return array( false, 'fluxo_invalido' );
		}
		// Só se chama quem a receção já deu entrada.
		if ( 'chamar' === $acao && empty( $c['checkin_em'] ) ) {
			return array( false, 'fluxo_sem_checkin' );
		}

		$agora = current_time( 'mysql' );
		$dados = array( 'status' => $mapa[ $acao ]['para'] );
		$fmt   = array( '%s' );

		if ( 'chamar' === $acao )  { $dados['chamado_em']   = $agora;  $fmt[] = '%s'; }
		if ( 'iniciar' === $acao ) { $dados['iniciado_em']  = $agora;  $fmt[] = '%s'; }
		if ( 'pausar' === $acao )  { $dados['pausa_motivo'] = $motivo; $fmt[] = '%s'; }
		if ( 'retomar' === $acao ) { $dados['pausa_motivo'] = '';      $fmt[] = '%s'; }

		$ok = $wpdb->update( $t, $dados, array( 'id' => $consulta_id ), $fmt, array( '%d' ) );
		if ( false === $ok ) {
			return array( false, 'erro' );
		}
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Médico: ' . $acao . ' consulta', '#' . $consulta_id );
		}
		do_action( 'clinica_consulta_estado', $consulta_id, $mapa[ $acao ]['para'] );

		return array( true, 'fluxo_' . $acao );
	}

	/**
	 * Regista o atendimento: nota SOAP + diagnóstico, e conclui a consulta.
	 * Devolve [bool $ok, string $codigo]. Transacional (tudo ou nada).
	 *
	 * @param array $in subjetivo, objetivo, avaliacao, plano, cid10, cid_descricao
	 */
	public static function registar_atendimento( int $consulta_id, int $medico_id, array $in ): array {
		global $wpdb;

		$c = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, paciente_id, medico_id, status FROM " . Clinica_Repo::T_CONSULTAS . " WHERE id = %d",
			$consulta_id
		), ARRAY_A );

		if ( ! $c ) {
			return array( false, 'invalido' );
		}
		// Âmbito: só o médico atribuído à consulta a pode atender.
		if ( (int) $c['medico_id'] !== $medico_id ) {
			return array( false, 'negado' );
		}
		// Não reabrir consultas já encerradas.
		if ( in_array( $c['status'], array( 'realizada', 'cancelada', 'faltou' ), true ) ) {
			return array( false, 'estado' );
		}
		// GUARDA: não se conclui sem diagnóstico (CID-10).
		$cid = strtoupper( trim( (string) ( $in['cid10'] ?? '' ) ) );
		if ( '' === $cid ) {
			return array( false, 'sem_diagnostico' );
		}

		$paciente_id = (int) $c['paciente_id'];
		$processo_id = class_exists( 'Clinica_Processo' ) ? Clinica_Processo::ensure( $paciente_id ) : 0;
		$agora       = current_time( 'mysql' );

		$wpdb->query( 'START TRANSACTION' );

		$ok_nota = $wpdb->insert( self::T_NOTAS, array(
			'consulta_id' => $consulta_id,
			'processo_id' => $processo_id,
			'paciente_id' => $paciente_id,
			'medico_id'   => $medico_id,
			'subjetivo'   => self::limpar( $in['subjetivo'] ?? '' ),
			'objetivo'    => self::limpar( $in['objetivo'] ?? '' ),
			'avaliacao'   => self::limpar( $in['avaliacao'] ?? '' ),
			'plano'       => self::limpar( $in['plano'] ?? '' ),
			'criado_em'   => $agora,
		) );

		$ok_diag = $wpdb->insert( self::T_DIAGNOSTICOS, array(
			'consulta_id' => $consulta_id,
			'processo_id' => $processo_id,
			'paciente_id' => $paciente_id,
			'medico_id'   => $medico_id,
			'cid10'       => mb_substr( $cid, 0, 10 ),
			'descricao'   => mb_substr( self::limpar( $in['cid_descricao'] ?? '' ), 0, 255 ),
			'criado_em'   => $agora,
		) );

		// Necessidade de retorno: o médico indica o prazo, a receção marca a data.
		$campos_consulta = array( 'status' => 'realizada' );
		$prazo           = (int) ( $in['retorno_prazo'] ?? 0 );
		if ( $prazo > 0 ) {
			$campos_consulta['retorno_em']   = date( 'Y-m-d', strtotime( '+' . $prazo . ' days', strtotime( $agora ) ) );
			$campos_consulta['retorno_nota'] = mb_substr( self::limpar( $in['retorno_nota'] ?? '' ), 0, 200 );
		}

		$ok_estado = $wpdb->update(
			Clinica_Repo::T_CONSULTAS,
			$campos_consulta,
			array( 'id' => $consulta_id )
		);

		if ( false === $ok_nota || false === $ok_diag || false === $ok_estado ) {
			$wpdb->query( 'ROLLBACK' );
			return array( false, 'erro' );
		}
		$wpdb->query( 'COMMIT' );

		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Médico: atendimento registado', 'Consulta #' . $consulta_id . ' · CID ' . $cid );
		}
		do_action( 'clinica_consulta_atendida', $consulta_id );
		do_action( 'clinica_consulta_estado', $consulta_id, 'realizada' );

		return array( true, 'atendido' );
	}

	/** Diagnósticos de um paciente (todas as consultas), mais recentes primeiro. */
	public static function diagnosticos_do_paciente( int $paciente_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT consulta_id, cid10, descricao, criado_em FROM " . self::T_DIAGNOSTICOS . "
			 WHERE paciente_id = %d ORDER BY id DESC",
			$paciente_id
		), ARRAY_A ) ?: array();
	}

	/** IDs de consulta do paciente que já têm nota clínica registada. */
	public static function consultas_com_nota( int $paciente_id ): array {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT consulta_id FROM " . self::T_NOTAS . " WHERE paciente_id = %d",
			$paciente_id
		) );
		return array_map( 'intval', $ids ?: array() );
	}

	/** A nota SOAP mais recente de uma consulta (ou null). */
	public static function nota_da_consulta( int $consulta_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT subjetivo, objetivo, avaliacao, plano, criado_em FROM " . self::T_NOTAS . "
			 WHERE consulta_id = %d ORDER BY id DESC LIMIT 1",
			$consulta_id
		), ARRAY_A );
		return $row ?: null;
	}

	/** Normaliza texto livre: corta espaços; guarda em bruto (escapado só na saída). */
	private static function limpar( $v ): string {
		return trim( (string) $v );
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Clinico', 'instalar' ) );
add_action( 'after_setup_theme', array( 'Clinica_Clinico', 'instalar_fluxo' ) );
add_action( 'after_setup_theme', array( 'Clinica_Clinico', 'instalar_modulos' ) );
add_action( 'after_setup_theme', array( 'Clinica_Clinico', 'instalar_retorno' ) );
add_action( 'init', array( 'Clinica_Clinico', 'servir_documento' ) );

/**
 * Processo clínico do paciente em PDF — para o médico analisar.
 * Só conteúdo clínico: identificação, segurança, história, diagnósticos,
 * prescrições e exames. NÃO inclui pagamentos nem recibos (isso é da receção).
 */
/**
 * Processo clínico em PDF: história, diagnósticos, prescrições e exames.
 * Documento do médico, sem pagamentos nem recibos, que são da receção.
 */
function clinica_medico_render_processo( int $paciente_id ): void {
	if ( ! current_user_can( 'clinica_ver_processo' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	$p = Clinica_Repo::paciente_ficha( $paciente_id );
	if ( ! $p ) {
		wp_die( esc_html__( 'Processo não encontrado.', 'clinica' ) );
	}

	$proc   = class_exists( 'Clinica_Processo' ) ? Clinica_Processo::do_paciente( $paciente_id ) : null;
	$hist   = Clinica_Repo::historico_do_paciente( $paciente_id );
	$recs   = Clinica_Clinico::receitas_do_paciente( $paciente_id );
	$exs    = Clinica_Clinico::exames_do_paciente( $paciente_id );
	$idade  = Clinica_Repo::idade( $p['data_nascimento'] ?? null );
	$nasc   = ( ! empty( $p['data_nascimento'] ) && '0000-00-00' !== $p['data_nascimento'] ) ? date_i18n( 'd/m/Y', strtotime( $p['data_nascimento'] ) ) : 'Não registada';
	$g      = $p['genero'] ?? '';
	$gen    = 'F' === $g ? 'Feminino' : ( 'M' === $g ? 'Masculino' : 'Não indicado' );
	$numero = $proc['numero'] ?? 'Sem processo';
	$labels = array( 'pendente' => 'Pendente', 'confirmada' => 'Confirmada', 'chamada' => 'Chamada', 'em_atendimento' => 'Em atendimento', 'pausada' => 'Em pausa', 'realizada' => 'Realizada', 'cancelada' => 'Cancelada', 'faltou' => 'Faltou' );

	$diag_map = array();
	foreach ( Clinica_Clinico::diagnosticos_do_paciente( $paciente_id ) as $d ) {
		$diag_map[ (int) $d['consulta_id'] ][] = $d;
	}
	$n_diag = 0;
	foreach ( $diag_map as $ds ) {
		$n_diag += count( $ds );
	}
	$ultima = '';
	foreach ( $hist as $h ) {
		if ( 'realizada' === $h['status'] ) {
			$ultima = $h['inicio'];
			break;
		}
	}
	$agora_doc = date_i18n( 'd/m/Y \à\s H\hi' );

	if ( class_exists( 'Clinica_Auditoria' ) ) {
		Clinica_Auditoria::registar( 'Médico: processo clínico exportado', $p['nome'] );
	}

	ob_start();
	echo clinica_pdf_topo( 'Processo Clínico', $numero );
	?>
	<p class="doc-crumb">Documento clínico confidencial · <b><?php echo esc_html( $p['nome'] ); ?></b></p>

	<?php
	echo clinica_pdf_meta( array(
		array( 'Nome', $p['nome'] ),
		array( 'Processo', $numero ),
		array( 'Nascimento', $nasc ),
		array( 'Idade', null !== $idade ? $idade . ' anos' : 'Não registada' ),
		array( 'Género', $gen ),
		array( 'Telefone', $p['telefone'] ?: 'Não registado' ),
		array( 'Consultas', (string) count( $hist ) ),
		array( 'Última consulta', $ultima ? date_i18n( 'd/m/Y', strtotime( $ultima ) ) : 'Nenhuma' ),
		array( 'Emitido', $agora_doc ),
	) );
	?>

	<?php if ( $proc && ! empty( $proc['alergias'] ) ) : ?>
		<div class="doc-alerta"><b>Alergias:</b> <?php echo esc_html( $proc['alergias'] ); ?></div>
	<?php endif; ?>

	<div class="fx-sec"><h3>Segurança clínica</h3></div>
	<table class="fx-tbl">
		<tbody>
			<tr><td style="width:26%"><b>Grupo sanguíneo</b></td><td><?php echo ! empty( $proc['grupo_sanguineo'] ) ? esc_html( $proc['grupo_sanguineo'] ) : 'Não registado'; ?></td></tr>
			<tr><td><b>Alergias</b></td><td><?php echo ! empty( $proc['alergias'] ) ? esc_html( $proc['alergias'] ) : 'Nenhuma registada'; ?></td></tr>
			<tr><td><b>Antecedentes</b></td><td><?php echo ! empty( $proc['antecedentes'] ) ? esc_html( $proc['antecedentes'] ) : 'Nenhum registado'; ?></td></tr>
		</tbody>
	</table>

	<div class="fx-sec"><h3>História clínica</h3><span><?php echo count( $hist ); ?> consultas · <?php echo (int) $n_diag; ?> diagnósticos</span></div>
	<?php if ( $hist ) : ?>
		<table class="fx-tbl">
			<thead><tr><th style="width:16%">Data</th><th style="width:28%">Sector e médico</th><th>Diagnóstico (CID-10)</th><th style="width:16%">Estado</th></tr></thead>
			<tbody>
				<?php foreach ( $hist as $h ) : ?>
					<tr>
						<td><b><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $h['inicio'] ) ) ); ?></b><span class="fx-sub"><?php echo esc_html( date_i18n( 'H:i', strtotime( $h['inicio'] ) ) ); ?></span></td>
						<td><b><?php echo esc_html( $h['esp_nome'] ); ?></b><span class="fx-sub"><?php echo esc_html( $h['med_nome'] ); ?></span></td>
						<td>
							<?php if ( ! empty( $diag_map[ (int) $h['id'] ] ) ) : ?>
								<?php foreach ( $diag_map[ (int) $h['id'] ] as $d ) : ?>
									<b><?php echo esc_html( $d['cid10'] ); ?></b><?php echo $d['descricao'] ? ' ' . esc_html( $d['descricao'] ) : ''; ?><br>
								<?php endforeach; ?>
							<?php elseif ( ! empty( $h['observacoes'] ) ) : ?>
								<span class="fx-sub"><?php echo esc_html( $h['observacoes'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $h['retorno_em'] ) ) : ?>
								<span class="fx-sub">Retorno indicado para <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $h['retorno_em'] ) ) ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $labels[ $h['status'] ] ?? $h['status'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="rb-note-p">Sem consultas registadas</p>
	<?php endif; ?>

	<div class="fx-sec"><h3>Prescrições</h3><span><?php echo count( $recs ); ?> registos</span></div>
	<?php if ( $recs ) : ?>
		<table class="fx-tbl">
			<thead><tr><th style="width:16%">Data</th><th>Medicamento</th><th style="width:16%">Dosagem</th><th style="width:26%">Posologia</th><th style="width:14%">Duração</th></tr></thead>
			<tbody>
				<?php foreach ( $recs as $r ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $r['criado_em'] ) ) ); ?></td>
						<td><b><?php echo esc_html( $r['medicamento'] ); ?></b></td>
						<td><?php echo esc_html( $r['dosagem'] ?: '' ); ?></td>
						<td><?php echo esc_html( $r['posologia'] ?: '' ); ?></td>
						<td><?php echo esc_html( $r['duracao'] ?: '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="rb-note-p">Sem prescrições registadas</p>
	<?php endif; ?>

	<div class="fx-sec"><h3>Exames pedidos</h3><span><?php echo count( $exs ); ?> registos</span></div>
	<?php if ( $exs ) : ?>
		<table class="fx-tbl">
			<thead><tr><th style="width:16%">Data</th><th>Exame</th><th style="width:20%">Tipo</th><th style="width:14%">Urgência</th><th style="width:16%">Estado</th></tr></thead>
			<tbody>
				<?php foreach ( $exs as $x ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $x['criado_em'] ) ) ); ?></td>
						<td><b><?php echo esc_html( $x['exame'] ); ?></b></td>
						<td><?php echo esc_html( $x['tipo'] ?: '' ); ?></td>
						<td><?php echo 'urgente' === $x['urgencia'] ? '<span class="fx-st--bad">Urgente</span>' : 'Normal'; ?></td>
						<td><?php echo esc_html( ucfirst( $x['estado'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="rb-note-p">Sem exames pedidos</p>
	<?php endif; ?>

	<div class="fx-footer">
		<span>Documento gerado em <?php echo esc_html( $agora_doc ); ?></span>
		<span>Clínica Vida · Processo clínico · Confidencial</span>
	</div>
	<?php
	$corpo = ob_get_clean();

	clinica_pdf_saida(
		'Processo clínico ' . $p['nome'],
		'processo-' . $numero . '-' . $p['nome'],
		$corpo,
		add_query_arg( 'pac', $paciente_id, home_url( '/portal-medico/' ) )
	);
}

/**
 * Receita médica e requisição de exames para o paciente levar consigo.
 * Documento próprio: sem histórico nem notas clínicas, só o que a farmácia
 * ou o laboratório precisam de ler.
 */
function clinica_medico_render_receita( int $paciente_id ): void {
	if ( ! current_user_can( 'clinica_prescrever' ) && ! current_user_can( 'clinica_pedir_exames' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	$p = Clinica_Repo::paciente_ficha( $paciente_id );
	if ( ! $p ) {
		wp_die( esc_html__( 'Paciente não encontrado.', 'clinica' ) );
	}

	$proc   = class_exists( 'Clinica_Processo' ) ? Clinica_Processo::do_paciente( $paciente_id ) : null;
	$numero = $proc['numero'] ?? 'Sem processo';
	$idade  = Clinica_Repo::idade( $p['data_nascimento'] ?? null );

	// Só o que foi prescrito ou pedido nos últimos 90 dias: uma receita antiga
	// não deve sair outra vez num documento novo.
	$limite = strtotime( '-90 days' );
	$recs   = array_filter( Clinica_Clinico::receitas_do_paciente( $paciente_id ), static function ( $r ) use ( $limite ) {
		return strtotime( $r['criado_em'] ) >= $limite;
	} );
	$exs = array_filter( Clinica_Clinico::exames_do_paciente( $paciente_id ), static function ( $e ) use ( $limite ) {
		return strtotime( $e['criado_em'] ) >= $limite;
	} );

	$medico    = wp_get_current_user();
	$agora_doc = date_i18n( 'd/m/Y \à\s H\hi' );

	if ( class_exists( 'Clinica_Auditoria' ) ) {
		Clinica_Auditoria::registar( 'Médico: receita emitida', $p['nome'] );
	}

	ob_start();
	echo clinica_pdf_topo( 'Receita Médica', $numero );
	?>
	<p class="doc-crumb">Documento para o paciente · <b><?php echo esc_html( $p['nome'] ); ?></b></p>

	<?php
	echo clinica_pdf_meta( array(
		array( 'Nome', $p['nome'] ),
		array( 'Processo', $numero ),
		array( 'Idade', null !== $idade ? $idade . ' anos' : 'Não registada' ),
		array( 'Médico', $medico->display_name ),
		array( 'Emitido', $agora_doc ),
	) );
	?>

	<?php if ( $proc && ! empty( $proc['alergias'] ) ) : ?>
		<div class="doc-alerta"><b>Alergias:</b> <?php echo esc_html( $proc['alergias'] ); ?></div>
	<?php endif; ?>

	<div class="fx-sec"><h3>Medicamentos prescritos</h3></div>
	<?php if ( $recs ) : ?>
		<table class="fx-tbl">
			<thead><tr><th style="width:14%">Data</th><th>Medicamento</th><th style="width:14%">Dosagem</th><th style="width:28%">Posologia</th><th style="width:14%">Duração</th></tr></thead>
			<tbody>
				<?php foreach ( $recs as $r ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $r['criado_em'] ) ) ); ?></td>
						<td><b><?php echo esc_html( $r['medicamento'] ); ?></b></td>
						<td><?php echo esc_html( $r['dosagem'] ?: '' ); ?></td>
						<td><?php echo esc_html( $r['posologia'] ?: '' ); ?></td>
						<td><?php echo esc_html( $r['duracao'] ?: '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="rb-note-p">Sem medicamentos prescritos nos últimos 90 dias</p>
	<?php endif; ?>

	<div class="fx-sec"><h3>Exames a realizar</h3></div>
	<?php if ( $exs ) : ?>
		<table class="fx-tbl">
			<thead><tr><th style="width:14%">Data</th><th>Exame</th><th style="width:20%">Tipo</th><th style="width:14%">Urgência</th><th style="width:26%">Indicação</th></tr></thead>
			<tbody>
				<?php foreach ( $exs as $x ) : ?>
					<tr>
						<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $x['criado_em'] ) ) ); ?></td>
						<td><b><?php echo esc_html( $x['exame'] ); ?></b></td>
						<td><?php echo esc_html( $x['tipo'] ?: '' ); ?></td>
						<td><?php echo 'urgente' === $x['urgencia'] ? '<span class="fx-st--bad">Urgente</span>' : 'Normal'; ?></td>
						<td><?php echo esc_html( $x['notas'] ?: '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p class="rb-note-p">Sem exames pedidos nos últimos 90 dias</p>
	<?php endif; ?>

	<div class="fx-assina">
		<span class="fx-assina__l"></span>
		<span class="fx-assina__n"><?php echo esc_html( $medico->display_name ); ?></span>
	</div>

	<div class="fx-footer">
		<span>Documento gerado em <?php echo esc_html( $agora_doc ); ?></span>
		<span>Clínica Vida · Receita médica</span>
	</div>
	<?php
	$corpo = ob_get_clean();

	clinica_pdf_saida(
		'Receita médica ' . $p['nome'],
		'receita-' . $p['nome'] . '-' . date_i18n( 'Y-m-d' ),
		$corpo,
		add_query_arg( 'pac', $paciente_id, home_url( '/portal-medico/' ) )
	);
}
