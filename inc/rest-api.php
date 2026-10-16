<?php
/**
 * inc/rest-api.php
 *
 * API REST da Clínica (namespace clinica/v1) para integrações externas — n8n,
 * Thunder Client, etc. Embrulha a lógica que já vive em Clinica_Repo e
 * Clinica_Pagamentos; não duplica regras de negócio.
 *
 * Autenticação: cabeçalho  X-API-Key: <chave>
 *   A chave vive em Clinica_Config('api_key') ou na constante CLINICA_API_KEY
 *   (wp-config.php). É uma chave de servidor — dá acesso total; manter secreta.
 *
 * Base: /wp-json/clinica/v1/
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Autentica um pedido. Aceita DOIS modos:
 *   1) Authorization: Bearer <token>  — token obtido em POST /auth/login (por utilizador).
 *   2) X-API-Key: <chave>             — chave de servidor (Clinica_Config api_key), acesso total.
 */
function clinica_api_auth( WP_REST_Request $req ) {
	// Modo 1 — Bearer token de login.
	$auth = (string) $req->get_header( 'Authorization' );
	if ( 0 === stripos( $auth, 'Bearer ' ) ) {
		$token = trim( substr( $auth, 7 ) );
		$uid   = (int) get_transient( 'clinica_api_tok_' . hash( 'sha256', $token ) );
		if ( $uid > 0 ) {
			wp_set_current_user( $uid );
			return true;
		}
		return clinica_api_erro( 'token_invalido', 'Token inválido ou expirado. Faça login de novo em /auth/login.', 401 );
	}

	// Modo 2 — X-API-Key (header ou, para testar no browser, ?api_key= no URL).
	$esperada = Clinica_Credenciais::get( 'api_key' );
	$recebida = (string) $req->get_header( 'X-API-Key' );
	if ( '' === $recebida ) {
		$recebida = (string) $req->get_param( 'api_key' );
	}
	if ( '' !== $esperada && '' !== $recebida && hash_equals( $esperada, $recebida ) ) {
		return true;
	}

	return clinica_api_erro( 'nao_autorizado', 'Autenticação em falta: use "Authorization: Bearer <token>" (após /auth/login) ou "X-API-Key: <chave>".', 401 );
}

/** POST /auth/login — valida utilizador/senha e devolve um token Bearer. */
function clinica_api_login( WP_REST_Request $req ) {
	$in   = clinica_api_body( $req );
	$user = wp_authenticate( (string) ( $in['username'] ?? $in['email'] ?? '' ), (string) ( $in['password'] ?? '' ) );
	if ( is_wp_error( $user ) ) {
		return clinica_api_erro( 'credenciais', 'Utilizador ou palavra-passe inválidos.', 401 );
	}

	$dias  = 7;
	$token = bin2hex( random_bytes( 24 ) );
	set_transient( 'clinica_api_tok_' . hash( 'sha256', $token ), $user->ID, $dias * DAY_IN_SECONDS );

	return rest_ensure_response( array(
		'token'      => $token,
		'token_type' => 'Bearer',
		'expires_in' => $dias * DAY_IN_SECONDS,
		'user'       => array(
			'id'     => $user->ID,
			'nome'   => $user->display_name,
			'email'  => $user->user_email,
			'papeis' => array_values( $user->roles ),
		),
	) );
}

/** GET /auth/me — devolve o utilizador do token atual. */
function clinica_api_me( WP_REST_Request $req ) {
	$u = wp_get_current_user();
	if ( ! $u || ! $u->ID ) {
		return clinica_api_erro( 'sem_sessao', 'Sem sessão (use um token de /auth/login).', 401 );
	}
	return rest_ensure_response( array(
		'id'     => $u->ID,
		'nome'   => $u->display_name,
		'email'  => $u->user_email,
		'papeis' => array_values( $u->roles ),
	) );
}

/**
 * Alias de URL: além de /wp-json/clinica/v1/… , as rotas ficam também em
 * /api/v1/…  (mais limpo para testar). Ex.: /clinica/api/v1/auth/login.
 */
add_action( 'init', function () {
	add_rewrite_rule( '^api/v1/(.+?)/?$', 'index.php?rest_route=/clinica/v1/$matches[1]', 'top' );
	if ( '2' !== get_option( 'clinica_api_rewrite' ) ) {
		flush_rewrite_rules( false );
		update_option( 'clinica_api_rewrite', '2' );
	}
} );

/** Resposta de erro uniforme. */
function clinica_api_erro( string $codigo, string $msg, int $status ): WP_Error {
	return new WP_Error( $codigo, $msg, array( 'status' => $status ) );
}

/**
 * Lê o corpo do pedido como array, tolerante ao Content-Type: tenta JSON, depois
 * form, e por fim descodifica o corpo cru como JSON (para quando o cliente envia
 * JSON mas esquece o cabeçalho application/json).
 */
function clinica_api_body( WP_REST_Request $req ): array {
	$in = $req->get_json_params();
	if ( is_array( $in ) && $in ) {
		return $in;
	}
	// Corpo JSON mesmo sem o cabeçalho application/json — descodifica-se antes do
	// form, senão o JSON seria lido como uma chave de formulário sem sentido.
	$raw = trim( (string) $req->get_body() );
	if ( '' !== $raw && ( '{' === $raw[0] || '[' === $raw[0] ) ) {
		$dec = json_decode( $raw, true );
		if ( is_array( $dec ) ) {
			return $dec;
		}
	}
	$in = $req->get_body_params();
	return is_array( $in ) ? $in : array();
}

add_action( 'rest_api_init', function () {
	$ns   = 'clinica/v1';
	$auth = 'clinica_api_auth';

	// Auth
	register_rest_route( $ns, '/auth/login', array(
		array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'clinica_api_login' ),
	) );
	register_rest_route( $ns, '/auth/me', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_me' ),
	) );

	// Consultas
	register_rest_route( $ns, '/consultas', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_consultas_listar' ),
		array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => 'clinica_api_consultas_criar' ),
	) );
	register_rest_route( $ns, '/consultas/(?P<id>\d+)', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_consulta_obter' ),
		array( 'methods' => 'PUT', 'permission_callback' => $auth, 'callback' => 'clinica_api_consulta_atualizar' ),
		array( 'methods' => 'DELETE', 'permission_callback' => $auth, 'callback' => 'clinica_api_consulta_cancelar' ),
	) );

	// Pacientes
	register_rest_route( $ns, '/pacientes', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_pacientes_listar' ),
		array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => 'clinica_api_pacientes_criar' ),
	) );
	register_rest_route( $ns, '/pacientes/por-telefone', array(
		array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => 'clinica_api_paciente_por_telefone' ),
	) );
	register_rest_route( $ns, '/pacientes/(?P<id>\d+)', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_paciente_obter' ),
	) );

	// Médicos
	register_rest_route( $ns, '/medicos', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_medicos_listar' ),
	) );
	register_rest_route( $ns, '/medicos/(?P<id>\d+)', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_medico_obter' ),
	) );
	register_rest_route( $ns, '/medicos/(?P<id>\d+)/horarios', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_medico_horarios' ),
	) );

	// Especialidades
	register_rest_route( $ns, '/especialidades', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_especialidades_listar' ),
	) );

	// Pagamentos
	register_rest_route( $ns, '/pagamentos', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_pagamentos_listar' ),
	) );
	register_rest_route( $ns, '/pagamentos/(?P<id>\d+)', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_pagamento_obter' ),
		array( 'methods' => 'PUT', 'permission_callback' => $auth, 'callback' => 'clinica_api_pagamento_atualizar' ),
	) );
	register_rest_route( $ns, '/pagamentos/(?P<id>\d+)/cobrar', array(
		array( 'methods' => 'POST', 'permission_callback' => $auth, 'callback' => 'clinica_api_pagamento_cobrar' ),
	) );
	register_rest_route( $ns, '/pagamentos/(?P<id>\d+)/recibo', array(
		array( 'methods' => 'GET', 'permission_callback' => $auth, 'callback' => 'clinica_api_pagamento_recibo' ),
	) );
} );

// Consultas

function clinica_api_consultas_listar( WP_REST_Request $req ) {
	$status = sanitize_key( (string) $req->get_param( 'status' ) );
	return rest_ensure_response( Clinica_Repo::consultas_todas( $status ) );
}

function clinica_api_consulta_obter( WP_REST_Request $req ) {
	$c = clinica_api_consulta_row( (int) $req['id'] );
	if ( ! $c ) {
		return clinica_api_erro( 'nao_encontrada', 'Consulta não encontrada.', 404 );
	}
	return rest_ensure_response( $c );
}

function clinica_api_consultas_criar( WP_REST_Request $req ) {
	$in = clinica_api_body( $req );

	$paciente_id = (int) ( $in['paciente_id'] ?? 0 );
	$medico_id   = (int) ( $in['medico_id'] ?? 0 );
	$inicio      = Clinica_Repo::normalizar_inicio( (string) ( $in['inicio'] ?? '' ) );

	if ( ! $paciente_id || ! $medico_id || ! $inicio ) {
		return clinica_api_erro( 'dados', 'Obrigatórios: paciente_id, medico_id, inicio (YYYY-MM-DD HH:MM).', 422 );
	}

	// Captura o id da nova consulta pelo hook de criação.
	$novo_id = 0;
	$cap = function ( $id ) use ( &$novo_id ) { $novo_id = (int) $id; };
	add_action( 'clinica_consulta_criada', $cap );

	list( $ok, $code ) = Clinica_Repo::criar( array(
		'nome_cliente' => (string) ( $in['nome_cliente'] ?? '' ),
		'paciente_id'  => $paciente_id,
		'medico_id'    => $medico_id,
		'inicio'       => $inicio,
		'observacoes'  => (string) ( $in['observacoes'] ?? '' ),
	) );

	remove_action( 'clinica_consulta_criada', $cap );

	if ( ! $ok ) {
		$mapa = array(
			'invalido'     => array( 'Médico inválido.', 422 ),
			'indisponivel' => array( 'Horário indisponível para esse médico.', 409 ),
			'conflito'     => array( 'Já existe uma consulta nesse horário.', 409 ),
		);
		$e = $mapa[ $code ] ?? array( 'Não foi possível criar a consulta.', 400 );
		return clinica_api_erro( $code, $e[0], $e[1] );
	}

	// A criação da consulta gera logo o pagamento pendente — devolve-se também,
	// para o fluxo "cliente cria consulta -> paga" encadear sem outro pedido.
	global $wpdb;
	$pg_id     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Clinica_Pagamentos::TABELA . ' WHERE consulta_id = %d', $novo_id ) );
	$pagamento = $pg_id ? Clinica_Pagamentos::obter( $pg_id ) : null;

	$resp = rest_ensure_response( array(
		'ok'        => true,
		'id'        => $novo_id,
		'consulta'  => clinica_api_consulta_row( $novo_id ),
		'pagamento' => $pagamento,
	) );
	$resp->set_status( 201 );
	return $resp;
}

function clinica_api_consulta_atualizar( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( ! clinica_api_consulta_row( $id ) ) {
		return clinica_api_erro( 'nao_encontrada', 'Consulta não encontrada.', 404 );
	}
	$in = clinica_api_body( $req );

	// Reagendar (inicio [+ medico_id]) OU mudar de estado (status).
	if ( ! empty( $in['inicio'] ) ) {
		$inicio    = Clinica_Repo::normalizar_inicio( (string) $in['inicio'] );
		$medico_id = (int) ( $in['medico_id'] ?? 0 );
		if ( ! $inicio ) {
			return clinica_api_erro( 'dados', 'inicio inválido (YYYY-MM-DD HH:MM).', 422 );
		}
		if ( ! $medico_id ) {
			$row       = clinica_api_consulta_row( $id );
			$medico_id = (int) ( $row['medico_id'] ?? 0 );
		}
		list( $ok, $code ) = Clinica_Repo::reagendar( $id, $medico_id, $inicio );
	} elseif ( ! empty( $in['status'] ) ) {
		list( $ok, $code ) = Clinica_Repo::mudar_estado( $id, sanitize_key( (string) $in['status'] ) );
	} else {
		return clinica_api_erro( 'dados', 'Envie "status" (mudar estado) ou "inicio" (reagendar).', 422 );
	}

	if ( ! $ok ) {
		return clinica_api_erro( $code ?: 'erro', 'Não foi possível atualizar: ' . ( $code ?: 'erro' ), 409 );
	}
	return rest_ensure_response( array( 'ok' => true, 'consulta' => clinica_api_consulta_row( $id ) ) );
}

function clinica_api_consulta_cancelar( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( ! clinica_api_consulta_row( $id ) ) {
		return clinica_api_erro( 'nao_encontrada', 'Consulta não encontrada.', 404 );
	}
	$in     = clinica_api_body( $req );
	$motivo = (string) ( $in['motivo'] ?? 'Cancelada via API' );
	list( $ok, $code ) = Clinica_Repo::cancelar_staff( $id, $motivo );
	if ( ! $ok ) {
		return clinica_api_erro( $code ?: 'erro', 'Não foi possível cancelar: ' . ( $code ?: 'erro' ), 409 );
	}
	return rest_ensure_response( array( 'ok' => true, 'estado' => 'cancelada' ) );
}

function clinica_api_consulta_row( int $id ): ?array {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT c.id, c.paciente_id, c.medico_id, c.inicio, c.duracao_min, c.status, c.observacoes,
				c.nome_cliente, pu.display_name AS paciente, mu.display_name AS medico, e.nome AS especialidade
		 FROM " . Clinica_Repo::T_CONSULTAS . " c
		 LEFT JOIN " . Clinica_Repo::T_PACIENTES . " pa ON c.paciente_id = pa.id
		 LEFT JOIN {$wpdb->users} pu ON pa.user_id = pu.ID
		 LEFT JOIN " . Clinica_Repo::T_MEDICOS . " md ON c.medico_id = md.id
		 LEFT JOIN {$wpdb->users} mu ON md.user_id = mu.ID
		 LEFT JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
		 WHERE c.id = %d",
		$id
	), ARRAY_A );
	return $row ?: null;
}

// Pacientes

function clinica_api_pacientes_listar( WP_REST_Request $req ) {
	global $wpdb;
	$sql   = "SELECT pa.id, pu.display_name AS nome, pu.user_email AS email, pa.telefone, pa.genero, pa.data_nascimento
			  FROM " . Clinica_Repo::T_PACIENTES . " pa
			  JOIN {$wpdb->users} pu ON pa.user_id = pu.ID";
	$where = array();
	$args  = array();

	$busca = trim( (string) $req->get_param( 'q' ) );
	if ( '' !== $busca ) {
		$where[] = '( pu.display_name LIKE %s OR pu.user_email LIKE %s )';
		$like    = '%' . $wpdb->esc_like( $busca ) . '%';
		$args[]  = $like;
		$args[]  = $like;
	}

	// Procura por telefone (WhatsApp): compara pelos últimos 9 dígitos (nº MZ).
	$tel = preg_replace( '/\D+/', '', (string) $req->get_param( 'telefone' ) ) ?? '';
	if ( '' !== $tel ) {
		$where[] = "REPLACE(REPLACE(REPLACE(pa.telefone,' ',''),'+',''),'-','') LIKE %s";
		$args[]  = '%' . substr( $tel, -9 );
	}

	if ( $where ) {
		$sql .= ' WHERE ' . implode( ' AND ', $where );
	}
	$sql .= ' ORDER BY pu.display_name LIMIT 500';
	$rows = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql, ARRAY_A );
	return rest_ensure_response( $rows ?: array() );
}

/** Procura um paciente pelo telefone (últimos 9 dígitos). */
function clinica_api_paciente_por_tel( string $tel ): ?array {
	$norm = Clinica_Telefone::normalizar( $tel );
	if ( '' === $norm ) {
		return null;
	}
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT pa.id FROM " . Clinica_Repo::T_PACIENTES . " pa
		 WHERE REPLACE(REPLACE(REPLACE(pa.telefone,' ',''),'+',''),'-','') LIKE %s LIMIT 1",
		'%' . substr( $norm, -9 )
	), ARRAY_A );
	return $row ?: null;
}

/**
 * POST /pacientes/por-telefone — procura o paciente pelo número (identidade do
 * WhatsApp) e cria-o se não existir. Devolve { criado, paciente }.
 */
function clinica_api_paciente_por_telefone( WP_REST_Request $req ) {
	$in   = clinica_api_body( $req );
	$norm = Clinica_Telefone::normalizar( (string) ( $in['telefone'] ?? '' ) );
	if ( '' === $norm ) {
		return clinica_api_erro( 'telefone', 'Telefone inválido (formato Moçambique).', 422 );
	}

	$existe = clinica_api_paciente_por_tel( $norm );
	if ( $existe ) {
		return rest_ensure_response( array( 'criado' => false, 'paciente' => Clinica_Repo::paciente_ficha( (int) $existe['id'] ) ) );
	}

	// Criar registo mínimo (WhatsApp dá só o número; email/login gerados).
	$nome  = sanitize_text_field( (string) ( $in['nome'] ?? ( 'Cliente ' . substr( $norm, -4 ) ) ) );
	$email = sanitize_email( (string) ( $in['email'] ?? '' ) );
	if ( ! is_email( $email ) || email_exists( $email ) ) {
		$email = $norm . '@wa.clinica.local';
		if ( email_exists( $email ) ) {
			$email = $norm . '.' . wp_generate_password( 4, false, false ) . '@wa.clinica.local';
		}
	}
	$login = 'wa' . $norm;
	$i     = 1;
	while ( username_exists( $login ) ) { $login = 'wa' . $norm . $i; $i++; }

	$uid = wp_insert_user( array(
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 12, false ),
		'display_name' => $nome,
		'first_name'   => $nome,
		'role'         => 'clinica_paciente',
	) );
	if ( is_wp_error( $uid ) ) {
		return clinica_api_erro( 'falha', 'Não foi possível criar o paciente.', 400 );
	}

	$pid = Clinica_Repo::ensure_paciente( (int) $uid );
	Clinica_Repo::guardar_perfil_paciente( $pid, array( 'telefone' => $norm ) );

	$resp = rest_ensure_response( array( 'criado' => true, 'paciente' => Clinica_Repo::paciente_ficha( $pid ) ) );
	$resp->set_status( 201 );
	return $resp;
}

function clinica_api_paciente_obter( WP_REST_Request $req ) {
	$f = Clinica_Repo::paciente_ficha( (int) $req['id'] );
	if ( ! $f ) {
		return clinica_api_erro( 'nao_encontrado', 'Paciente não encontrado.', 404 );
	}
	return rest_ensure_response( $f );
}

function clinica_api_pacientes_criar( WP_REST_Request $req ) {
	$in    = clinica_api_body( $req );
	$nome  = sanitize_text_field( (string) ( $in['nome'] ?? '' ) );
	$email = sanitize_email( (string) ( $in['email'] ?? '' ) );

	if ( '' === $nome || ! is_email( $email ) ) {
		return clinica_api_erro( 'dados', 'Obrigatórios: nome e email válido.', 422 );
	}
	if ( email_exists( $email ) ) {
		return clinica_api_erro( 'existe', 'Já existe uma conta com esse email.', 409 );
	}

	$pass  = wp_generate_password( 12, false );
	$base  = sanitize_user( current( explode( '@', $email ) ), true ) ?: 'paciente';
	$login = $base;
	$i     = 1;
	while ( username_exists( $login ) ) { $login = $base . $i; $i++; }

	$uid = wp_insert_user( array(
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => $pass,
		'display_name' => $nome,
		'first_name'   => $nome,
		'role'         => 'clinica_paciente',
	) );
	if ( is_wp_error( $uid ) ) {
		return clinica_api_erro( 'falha', 'Não foi possível criar o utilizador.', 400 );
	}

	$pid = Clinica_Repo::ensure_paciente( (int) $uid );
	Clinica_Repo::guardar_perfil_paciente( $pid, array(
		'bi'              => sanitize_text_field( (string) ( $in['bi'] ?? '' ) ),
		'genero'          => sanitize_text_field( (string) ( $in['genero'] ?? '' ) ),
		'telefone'        => sanitize_text_field( (string) ( $in['telefone'] ?? '' ) ),
		'endereco'        => sanitize_text_field( (string) ( $in['endereco'] ?? '' ) ),
		'data_nascimento' => sanitize_text_field( (string) ( $in['data_nascimento'] ?? '' ) ),
	) );

	$resp = rest_ensure_response( array( 'ok' => true, 'id' => $pid, 'paciente' => Clinica_Repo::paciente_ficha( $pid ) ) );
	$resp->set_status( 201 );
	return $resp;
}

// Médicos

function clinica_api_medicos_listar( WP_REST_Request $req ) {
	// Filtro opcional por especialidade (o bot usa isto depois de sugerir a área).
	$esp = (int) $req->get_param( 'especialidade' );
	if ( $esp > 0 ) {
		return rest_ensure_response( Clinica_Repo::medicos_por_especialidade( $esp ) );
	}
	return rest_ensure_response( Clinica_Repo::medicos() );
}

/** GET /medicos/{id}/horarios?data=YYYY-MM-DD — horários livres do médico nesse dia. */
function clinica_api_medico_horarios( WP_REST_Request $req ) {
	$medico_id = (int) $req['id'];
	if ( ! Clinica_Repo::medico( $medico_id ) ) {
		return clinica_api_erro( 'nao_encontrado', 'Médico não encontrado.', 404 );
	}
	$data = (string) $req->get_param( 'data' );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data ) ) {
		$data = date( 'Y-m-d', strtotime( '+1 day' ) );
	}
	$slots = class_exists( 'Clinica_Agenda' ) ? Clinica_Agenda::slots_livres( $medico_id, $data ) : array();
	return rest_ensure_response( array(
		'medico_id' => $medico_id,
		'data'      => $data,
		'horarios'  => array_values( $slots ),
	) );
}

function clinica_api_medico_obter( WP_REST_Request $req ) {
	$m = Clinica_Repo::medico( (int) $req['id'] );
	if ( ! $m ) {
		return clinica_api_erro( 'nao_encontrado', 'Médico não encontrado.', 404 );
	}
	return rest_ensure_response( $m );
}

// Especialidades

function clinica_api_especialidades_listar( WP_REST_Request $req ) {
	return rest_ensure_response( Clinica_Repo::especialidades() );
}

// Pagamentos

function clinica_api_pagamentos_listar( WP_REST_Request $req ) {
	$estado = sanitize_key( (string) $req->get_param( 'estado' ) );
	return rest_ensure_response( Clinica_Pagamentos::listar( $estado ) );
}

function clinica_api_pagamento_obter( WP_REST_Request $req ) {
	$p = Clinica_Pagamentos::obter( (int) $req['id'] );
	if ( ! $p ) {
		return clinica_api_erro( 'nao_encontrado', 'Pagamento não encontrado.', 404 );
	}
	return rest_ensure_response( $p );
}

function clinica_api_pagamento_atualizar( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( ! Clinica_Pagamentos::obter( $id ) ) {
		return clinica_api_erro( 'nao_encontrado', 'Pagamento não encontrado.', 404 );
	}
	$in     = clinica_api_body( $req );
	$estado = sanitize_key( (string) ( $in['estado'] ?? '' ) );
	if ( '' === $estado ) {
		return clinica_api_erro( 'dados', 'Envie "estado" (pendente|pago|cancelado|falhou|reembolsado).', 422 );
	}
	$ok = Clinica_Pagamentos::definir_estado( $id, $estado, (string) ( $in['referencia'] ?? '' ) );
	if ( ! $ok ) {
		return clinica_api_erro( 'estado', 'Estado inválido.', 422 );
	}
	return rest_ensure_response( array( 'ok' => true, 'pagamento' => Clinica_Pagamentos::obter( $id ) ) );
}

/** GET /pagamentos/{id}/recibo — dados do recibo (só para pagamentos pagos). */
function clinica_api_pagamento_recibo( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	$p  = Clinica_Pagamentos::obter( $id );
	if ( ! $p ) {
		return clinica_api_erro( 'nao_encontrado', 'Pagamento não encontrado.', 404 );
	}
	if ( 'pago' !== $p['estado'] ) {
		return clinica_api_erro( 'nao_pago', 'O recibo só existe para pagamentos pagos.', 409 );
	}
	$moeda    = class_exists( 'Clinica_Config' ) ? (string) Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';
	$consulta = trim( (string) ( $p['esp'] ?? 'Consulta' )
		. ( ! empty( $p['medico'] ) ? ' · ' . $p['medico'] : '' )
		. ( ! empty( $p['inicio'] ) ? ' · ' . date_i18n( 'd/m H:i', strtotime( (string) $p['inicio'] ) ) : '' ) );
	$metodo   = ( class_exists( 'Clinica_Pagamentos' ) && isset( Clinica_Pagamentos::METODOS[ $p['metodo'] ] ) ) ? Clinica_Pagamentos::METODOS[ $p['metodo'] ] : (string) $p['metodo'];

	return rest_ensure_response( array(
		'numero'     => Clinica_Pagamentos::numero_recibo( $id ),
		'estado'     => (string) $p['estado'],
		'paciente'   => (string) ( $p['paciente'] ?? '' ),
		'consulta'   => $consulta,
		'valor'      => (float) $p['valor'],
		'moeda'      => $moeda,
		'metodo'     => $metodo,
		'referencia' => (string) ( $p['referencia'] ?? '' ),
		'data'       => (string) ( $p['atualizado_em'] ?? $p['criado_em'] ?? '' ),
		'clinica'    => 'Clínica Vida',
	) );
}

function clinica_api_pagamento_cobrar( WP_REST_Request $req ) {
	$id = (int) $req['id'];
	if ( ! Clinica_Pagamentos::obter( $id ) ) {
		return clinica_api_erro( 'nao_encontrado', 'Pagamento não encontrado.', 404 );
	}
	$in  = clinica_api_body( $req );
	$tel = (string) ( $in['telefone'] ?? '' );

	$r = Clinica_Pagamentos::cobrar( $id, $tel );
	if ( ! $r->ok() ) {
		return new WP_Error( 'cobranca_falhou', $r->erro(), array( 'status' => 402, 'detalhe' => $r->campo( 'detalhe', '' ) ) );
	}
	return rest_ensure_response( array(
		'ok'           => true,
		'codigo_mpesa' => $r->campo( 'codigo_mpesa', '' ),
		'referencia'   => $r->campo( 'referencia', '' ),
		'pagamento'    => Clinica_Pagamentos::obter( $id ),
	) );
}
