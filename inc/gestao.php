<?php
/**
 * inc/gestao.php
 *
 * Painel de gestão (Administração/Admin): CRUD de especialidades e médicos,
 * ligado à BD real. Controlador PRG com nonce e verificação de capabilities.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/** Todas as especialidades (inclui inativas) para o painel. */
function clinica_gestao_especialidades(): array {
	global $wpdb;
	return $wpdb->get_results( "SELECT id, nome, slug, descricao, ativo FROM " . Clinica_Repo::T_ESPECIALIDADES . " ORDER BY nome ASC", ARRAY_A ) ?: array();
}

/** Todos os médicos com nome e especialidade. */
function clinica_gestao_medicos(): array {
	global $wpdb;
	return $wpdb->get_results(
		"SELECT md.id, md.ativo, md.num_ordem, md.duracao_padrao_min, u.ID AS user_id, u.display_name AS nome, u.user_email AS email, e.nome AS esp_nome
		 FROM " . Clinica_Repo::T_MEDICOS . " md
		 JOIN {$wpdb->users} u ON md.user_id = u.ID
		 JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
		 ORDER BY u.display_name ASC",
		ARRAY_A
	) ?: array();
}

/** Slug único a partir do nome da especialidade. */
function clinica_slug_unico( string $nome ): string {
	global $wpdb;
	$base = sanitize_title( $nome ) ?: 'especialidade';
	$slug = $base;
	$i    = 2;
	while ( $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM " . Clinica_Repo::T_ESPECIALIDADES . " WHERE slug = %s", $slug ) ) ) {
		$slug = $base . '-' . $i;
		$i++;
	}
	return $slug;
}

/**
 * Migração idempotente: garante a coluna `sala` (consultório) em clinica_medicos.
 * A tabela não tem instalador no tema, por isso a coluna é adicionada por ALTER
 * uma única vez (option-gated + verificação no information_schema).
 */
function clinica_medicos_migrar_sala(): void {
	if ( get_option( 'clinica_medicos_sala_db' ) === '1' ) {
		return;
	}
	global $wpdb;
	$t = Clinica_Repo::T_MEDICOS;
	$existe = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM information_schema.COLUMNS
		 WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'sala'",
		DB_NAME, $t
	) );
	if ( ! $existe ) {
		$wpdb->query( "ALTER TABLE {$t} ADD COLUMN sala VARCHAR(40) NULL AFTER num_ordem" );
	}
	update_option( 'clinica_medicos_sala_db', '1' );
}
add_action( 'after_setup_theme', 'clinica_medicos_migrar_sala' );

/** Controlador PRG do painel de gestão. */
function clinica_controller_gestao(): void {
	if ( empty( $_POST['ge_action'] ) ) {
		return;
	}
	$acao = sanitize_key( $_POST['ge_action'] );
	global $wpdb;

	// ---- Especialidades -----------------------------------------------------
	if ( in_array( $acao, array( 'esp_criar', 'esp_editar', 'esp_apagar' ), true ) ) {
		if ( ! current_user_can( 'clinica_gerir_especialidades' ) || ! Clinica_Input::nonce_ok( $_POST, 'ge_nonce', 'ge_esp' ) ) {
			clinica_redirect( 'negado' );
		}

		if ( 'esp_criar' === $acao ) {
			$nome = Clinica_Input::texto( $_POST, 'nome' );
			if ( '' === $nome ) {
				clinica_redirect( 'invalido' );
			}
			$wpdb->insert( Clinica_Repo::T_ESPECIALIDADES, array(
				'nome'      => mb_substr( $nome, 0, 80 ),
				'slug'      => clinica_slug_unico( $nome ),
				'descricao' => mb_substr( Clinica_Input::texto( $_POST, 'descricao' ), 0, 255 ),
				'ativo'     => 1,
			), array( '%s', '%s', '%s', '%d' ) );
			Clinica_Auditoria::registar( 'Especialidade criada', $nome );
			clinica_redirect( 'esp_criada' );
		}

		if ( 'esp_editar' === $acao ) {
			$id = Clinica_Input::inteiro( $_POST, 'id' );
			$wpdb->update( Clinica_Repo::T_ESPECIALIDADES, array(
				'nome'      => mb_substr( Clinica_Input::texto( $_POST, 'nome' ), 0, 80 ),
				'descricao' => mb_substr( Clinica_Input::texto( $_POST, 'descricao' ), 0, 255 ),
				'ativo'     => empty( $_POST['ativo'] ) ? 0 : 1,
			), array( 'id' => $id ), array( '%s', '%s', '%d' ), array( '%d' ) );
			Clinica_Auditoria::registar( 'Especialidade atualizada', Clinica_Input::texto( $_POST, 'nome' ) );
			clinica_redirect( 'esp_atualizada' );
		}

		if ( 'esp_apagar' === $acao ) {
			$id  = Clinica_Input::inteiro( $_POST, 'id' );
			$uso = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . Clinica_Repo::T_MEDICOS . " WHERE especialidade_id = %d", $id ) );
			if ( $uso > 0 ) {
				clinica_redirect( 'esp_em_uso' );
			}
			$wpdb->delete( Clinica_Repo::T_ESPECIALIDADES, array( 'id' => $id ), array( '%d' ) );
			Clinica_Auditoria::registar( 'Especialidade removida', '#' . $id );
			clinica_redirect( 'esp_apagada' );
		}
	}

	// Médicos 
	if ( in_array( $acao, array( 'med_criar', 'med_estado', 'med_sala' ), true ) ) {
		if ( ! current_user_can( 'clinica_gerir_medicos' ) || ! Clinica_Input::nonce_ok( $_POST, 'ge_nonce', 'ge_med' ) ) {
			clinica_redirect( 'negado' );
		}

		if ( 'med_criar' === $acao ) {
			$nome  = Clinica_Input::texto( $_POST, 'nome' );
			$email = Clinica_Input::email( $_POST, 'email' );
			$pass  = (string) ( $_POST['pwd'] ?? '' );
			$esp   = Clinica_Input::inteiro( $_POST, 'especialidade_id' );
			$ordem = Clinica_Input::texto( $_POST, 'num_ordem' );
			if ( '' === $nome || '' === $email || strlen( $pass ) < 6 || ! $esp ) {
				clinica_redirect( 'med_dados' );
			}
			if ( email_exists( $email ) ) {
				clinica_redirect( 'med_existe' );
			}
			$base = sanitize_user( current( explode( '@', $email ) ), true ) ?: 'medico';
			$login = $base; $i = 1;
			while ( username_exists( $login ) ) { $login = $base . $i; $i++; }

			$uid = wp_insert_user( array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $nome,
				'first_name'   => $nome,
				'role'         => 'clinica_medico',
			) );
			if ( is_wp_error( $uid ) ) {
				clinica_redirect( 'med_erro' );
			}
			$wpdb->insert( Clinica_Repo::T_MEDICOS, array(
				'user_id'            => (int) $uid,
				'especialidade_id'   => $esp,
				'num_ordem'          => mb_substr( $ordem ?: ( 'OM-' . $uid ), 0, 20 ),
				'sala'               => mb_substr( Clinica_Input::texto( $_POST, 'sala' ), 0, 40 ),
				'telefone'           => mb_substr( Clinica_Input::texto( $_POST, 'telefone' ), 0, 20 ),
				'duracao_padrao_min' => Clinica_Config::int( 'duracao_consulta_min', 30 ),
				'ativo'              => 1,
			), array( '%d', '%d', '%s', '%s', '%s', '%d', '%d' ) );

			// Foto do médico (opcional) -> biblioteca de média + meta do utilizador
			// (reutiliza o mesmo mecanismo de avatar do perfil do paciente).
			if ( ! empty( $_FILES['foto']['name'] ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				$att = media_handle_upload( 'foto', 0 );
				if ( ! is_wp_error( $att ) ) {
					update_user_meta( (int) $uid, 'clinica_avatar', (int) $att );
				}
			}

			Clinica_Auditoria::registar( 'Médico criado', $nome );
			clinica_redirect( 'med_criado' );
		}

		if ( 'med_estado' === $acao ) {
			$id    = Clinica_Input::inteiro( $_POST, 'id' );
			$ativo = empty( $_POST['ativo'] ) ? 0 : 1;
			$wpdb->update( Clinica_Repo::T_MEDICOS, array( 'ativo' => $ativo ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
			Clinica_Auditoria::registar( $ativo ? 'Médico ativado' : 'Médico desativado', '#' . $id );
			clinica_redirect( 'med_estado' );
		}

		if ( 'med_sala' === $acao ) {
			$id   = Clinica_Input::inteiro( $_POST, 'id' );
			$sala = mb_substr( Clinica_Input::texto( $_POST, 'sala' ), 0, 40 );
			$wpdb->update( Clinica_Repo::T_MEDICOS, array( 'sala' => $sala ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
			Clinica_Auditoria::registar( 'Consultório do médico atualizado', '#' . $id . ' -> ' . $sala );
			clinica_redirect( 'med_sala_ok' );
		}
	}

	// ---- Definições da clínica (regras de negócio na config) ----
	if ( 'cfg_save' === $acao ) {
		if ( ! current_user_can( 'clinica_configurar' ) || ! Clinica_Input::nonce_ok( $_POST, 'ge_nonce', 'ge_cfg' ) ) {
			clinica_redirect( 'negado' );
		}
		$chaves = array( 'hora_abertura', 'hora_fecho', 'intervalo_inicio', 'intervalo_fim', 'duracao_consulta_min', 'max_consultas_dia', 'max_consultas_hora', 'antecedencia_min_horas', 'cancelamento_horas', 'valor_consulta_padrao', 'moeda' );
		foreach ( $chaves as $k ) {
			if ( isset( $_POST[ $k ] ) ) {
				Clinica_Config::set( $k, Clinica_Input::texto( $_POST, $k ) );
			}
		}
		Clinica_Config::set( 'pagamento_obrigatorio', empty( $_POST['pagamento_obrigatorio'] ) ? '0' : '1' );
		Clinica_Auditoria::registar( 'Definições da clínica atualizadas' );
		clinica_redirect( 'cfg_ok' );
	}
}
