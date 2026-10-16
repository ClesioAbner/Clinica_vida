<?php
/**
 * inc/clinic-repo.php
 *
 * Acesso a dados das tabelas clinica_* (sem prefixo wp_).
 * Regra: nenhum template escreve SQL. Os templates chamam só estes métodos.
 * Tudo o que recebe input passa por $wpdb->prepare() ou por insert/update/delete.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

final class Clinica_Repo {

	const T_CONSULTAS      = 'clinica_consultas';
	const T_ESPECIALIDADES = 'clinica_especialidades';
	const T_MEDICOS        = 'clinica_medicos';
	const T_PACIENTES      = 'clinica_pacientes';

	// Limites do slot (o schema pede validação no PHP).
	const DURACAO_MIN = 5;
	const DURACAO_MAX = 480;

	// Espelha o ENUM da coluna status.
	const STATUS = array( 'pendente', 'confirmada', 'cancelada', 'realizada', 'faltou' );


	// Paciente

	
	// Devolve o id de paciente do utilizador WP, criando a ficha se não existir.
	//Sem isto, o INSERT em consultas falha: paciente_id é NOT NULL + FK.
	 
	public static function ensure_paciente( int $user_id ): int {
		global $wpdb;

		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . self::T_PACIENTES . " WHERE user_id = %d",
			$user_id
		) );
		if ( $id ) {
			return (int) $id;
		}

		$wpdb->insert( self::T_PACIENTES, array( 'user_id' => $user_id ) );
		$paciente_id = (int) $wpdb->insert_id;

		// Abre automaticamente o processo clínico (um por paciente).
		if ( $paciente_id && class_exists( 'Clinica_Processo' ) ) {
			Clinica_Processo::ensure( $paciente_id );
		}
		return $paciente_id;
	}

	// Dados do perfil do paciente (para pré-preencher a marcação).
	public static function paciente_dados( int $paciente_id ): array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT telefone, data_nascimento, endereco
			 FROM " . self::T_PACIENTES . " WHERE id = %d",
			$paciente_id
		), ARRAY_A );
		return $row ?: array( 'telefone' => '', 'data_nascimento' => null, 'endereco' => '' );
	}

	// Guarda a data de nascimento do paciente. Aceita 'Y-m-d'; ignora inválidas.
	public static function guardar_data_nascimento( int $paciente_id, string $data ): void {
		$d = DateTime::createFromFormat( 'Y-m-d', $data );
		if ( ! $d ) {
			return;
		}
		global $wpdb;
		$wpdb->update(
			self::T_PACIENTES,
			array( 'data_nascimento' => $d->format( 'Y-m-d' ) ),
			array( 'id' => $paciente_id )
		);
	}

	// Idade (anos) a partir da data de nascimento. null se vazia/invalida.
	public static function idade( ?string $data_nascimento ): ?int {
		if ( empty( $data_nascimento ) ) {
			return null;
		}
		$d = DateTime::createFromFormat( 'Y-m-d', substr( $data_nascimento, 0, 10 ) );
		return $d ? (int) $d->diff( new DateTime( 'now' ) )->y : null;
	}

	// Atualiza a ficha administrativa do paciente (BI, género, contacto, morada).
	public static function guardar_perfil_paciente( int $paciente_id, array $in ): void {
		$campos = array();
		if ( isset( $in['telefone'] ) && '' !== $in['telefone'] ) {
			$campos['telefone'] = substr( $in['telefone'], 0, 20 );
		}
		if ( isset( $in['bi'] ) && '' !== $in['bi'] ) {
			$campos['bi'] = substr( $in['bi'], 0, 30 );
		}
		if ( isset( $in['genero'] ) && in_array( $in['genero'], array( 'M', 'F' ), true ) ) {
			$campos['genero'] = $in['genero'];
		}
		if ( isset( $in['endereco'] ) && '' !== $in['endereco'] ) {
			$campos['endereco'] = substr( $in['endereco'], 0, 255 );
		}
		if ( ! empty( $in['data_nascimento'] ) ) {
			$d = DateTime::createFromFormat( 'Y-m-d', $in['data_nascimento'] );
			if ( $d ) {
				$campos['data_nascimento'] = $d->format( 'Y-m-d' );
			}
		}
		if ( $campos ) {
			global $wpdb;
			$wpdb->update( self::T_PACIENTES, $campos, array( 'id' => $paciente_id ) );
		}
	}


	// Catálogos (especialidades / médicos)
	
	public static function especialidades(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT id, nome, slug, descricao
			 FROM " . self::T_ESPECIALIDADES . "
			 WHERE ativo = 1
			 ORDER BY nome ASC",
			ARRAY_A
		) ?: array();
	}

	// Uma especialidade pelo slug (para a página de detalhe /especialidades/{slug}).
	public static function especialidade_por_slug( string $slug ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, nome, slug, descricao
			 FROM " . self::T_ESPECIALIDADES . "
			 WHERE slug = %s AND ativo = 1",
			$slug
		), ARRAY_A );
		return $row ?: null;
	}

	// Médicos de uma especialidade (nome WP + foto + bio) — para os cartões.
	public static function medicos_por_especialidade( int $especialidade_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT md.id, md.num_ordem, md.foto_id, md.descricao AS bio, md.duracao_padrao_min,
					u.display_name AS nome
			 FROM " . self::T_MEDICOS . " md
			 JOIN {$wpdb->users} u ON md.user_id = u.ID
			 WHERE md.especialidade_id = %d AND md.ativo = 1
			 ORDER BY u.display_name ASC",
			$especialidade_id
		), ARRAY_A ) ?: array();
	}

	// Nº de médicos ativos por especialidade -> [especialidade_id => total].
	public static function contar_medicos_por_especialidade(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT especialidade_id, COUNT(*) AS total
			 FROM " . self::T_MEDICOS . "
			 WHERE ativo = 1
			 GROUP BY especialidade_id",
			ARRAY_A
		) ?: array();

		$out = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r['especialidade_id'] ] = (int) $r['total'];
		}
		return $out;
	}

	// O nome do médico vem do utilizador WP associado.
	public static function medicos(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT md.id, md.especialidade_id, md.duracao_padrao_min, u.display_name AS nome
			 FROM " . self::T_MEDICOS . " md
			 JOIN {$wpdb->users} u ON md.user_id = u.ID
			 WHERE md.ativo = 1
			 ORDER BY u.display_name ASC",
			ARRAY_A
		) ?: array();
	}

	public static function medico( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, especialidade_id, duracao_padrao_min
			 FROM " . self::T_MEDICOS . "
			 WHERE id = %d AND ativo = 1",
			$id
		), ARRAY_A );
		return $row ?: null;
	}

	// Leitura de consultas

	// Consultas de um paciente (portal do cliente).
	public static function consultas_do_paciente( int $paciente_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*, e.nome AS esp_nome, e.id AS esp_id, u.display_name AS med_nome
			 FROM " . self::T_CONSULTAS . " c
			 JOIN " . self::T_MEDICOS . " md ON c.medico_id = md.id
			 JOIN {$wpdb->users} u ON md.user_id = u.ID
			 JOIN " . self::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
			 WHERE c.paciente_id = %d
			 ORDER BY c.inicio DESC",
			$paciente_id
		), ARRAY_A ) ?: array();
	}

	// Uma consulta do próprio paciente. Usar antes de editar/cancelar (dono + estado).
	public static function consulta_do_paciente( int $id, int $paciente_id ): ?array {
		global $wpdb;
		// JOIN ao medico para trazer a especialidade (esp_id), usada ao pre-preencher o form de edicao.
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT c.*, md.especialidade_id AS esp_id
			 FROM " . self::T_CONSULTAS . " c
			 JOIN " . self::T_MEDICOS . " md ON c.medico_id = md.id
			 WHERE c.id = %d AND c.paciente_id = %d",
			$id, $paciente_id
		), ARRAY_A );
		return $row ?: null;
	}

	// Todas as consultas (painel do médico). Filtro opcional por estado.
	public static function consultas_todas( string $status = '' ): array {
		global $wpdb;

		$sql = "SELECT c.*, e.nome AS esp_nome, u.display_name AS med_nome, pu.display_name AS pac_nome
				FROM " . self::T_CONSULTAS . " c
				JOIN " . self::T_MEDICOS . " md ON c.medico_id = md.id
				JOIN {$wpdb->users} u  ON md.user_id = u.ID
				JOIN " . self::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
				JOIN " . self::T_PACIENTES . " p ON c.paciente_id = p.id
				JOIN {$wpdb->users} pu ON p.user_id = pu.ID";

		if ( in_array( $status, self::STATUS, true ) ) {
			$sql .= $wpdb->prepare( " WHERE c.status = %s", $status );
		}
		$sql .= " ORDER BY c.inicio DESC";

		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	// Totais por estado, para os KPIs. Devolve todos os estados (0 incluído).
	public static function contagem_por_estado(): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS total
			 FROM " . self::T_CONSULTAS . "
			 GROUP BY status",
			ARRAY_A
		) ?: array();

		$out = array_fill_keys( self::STATUS, 0 );
		foreach ( $rows as $r ) {
			$out[ $r['status'] ] = (int) $r['total'];
		}
		return $out;
	}

	// Médico -> id em clinica_medicos (define o âmbito das suas consultas).
	public static function medico_id_do_user( int $user_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM " . self::T_MEDICOS . " WHERE user_id = %d",
			$user_id
		) );
	}

	// Consultas SÓ de um médico (menor privilégio: vê apenas os seus pacientes).
	public static function consultas_do_medico( int $medico_id, string $status = '' ): array {
		global $wpdb;
		$sql = "SELECT c.*, e.nome AS esp_nome, u.display_name AS med_nome, pu.display_name AS pac_nome
				FROM " . self::T_CONSULTAS . " c
				JOIN " . self::T_MEDICOS . " md ON c.medico_id = md.id
				JOIN {$wpdb->users} u  ON md.user_id = u.ID
				JOIN " . self::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
				JOIN " . self::T_PACIENTES . " p ON c.paciente_id = p.id
				JOIN {$wpdb->users} pu ON p.user_id = pu.ID
				WHERE c.medico_id = %d";
		$args = array( $medico_id );
		if ( in_array( $status, self::STATUS, true ) ) {
			$sql   .= " AND c.status = %s";
			$args[] = $status;
		}
		$sql .= " ORDER BY c.inicio DESC";
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) ?: array();
	}

	// Totais por estado de UM médico (KPIs do painel do próprio).
	public static function contagem_por_estado_medico( int $medico_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT status, COUNT(*) AS total FROM " . self::T_CONSULTAS . " WHERE medico_id = %d GROUP BY status",
			$medico_id
		), ARRAY_A ) ?: array();
		$out = array_fill_keys( self::STATUS, 0 );
		foreach ( $rows as $r ) {
			$out[ $r['status'] ] = (int) $r['total'];
		}
		return $out;
	}


	// Pacientes de um médico (0 = todos) com a ficha preenchida pelo próprio.
	public static function pacientes_do_medico( int $medico_id ): array {
		global $wpdb;
		$where = $medico_id > 0 ? $wpdb->prepare( "WHERE c.medico_id = %d", $medico_id ) : '';
		// Inclui o que interessa clinicamente ao médico: nº de processo e alergias.
		$tp    = class_exists( 'Clinica_Processo' ) ? Clinica_Processo::TABELA : 'clinica_processos';
		$sql   = "SELECT pa.id, u.display_name AS nome, u.user_email AS email, pa.telefone,
					pa.data_nascimento, pa.genero, pr.numero AS processo, pr.alergias,
					COUNT(c.id) AS n_consultas, MAX(c.inicio) AS ultima
				 FROM " . self::T_CONSULTAS . " c
				 JOIN " . self::T_PACIENTES . " pa ON c.paciente_id = pa.id
				 JOIN {$wpdb->users} u ON pa.user_id = u.ID
				 LEFT JOIN {$tp} pr ON pr.paciente_id = pa.id
				 {$where}
				 GROUP BY pa.id, u.display_name, u.user_email, pa.telefone, pa.data_nascimento,
					pa.genero, pr.numero, pr.alergias
				 ORDER BY u.display_name ASC";
		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}


	// Ficha do paciente (dados pessoais + utilizador). Null se não existir.
	public static function paciente_ficha( int $paciente_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT pa.id, pa.bi, pa.genero, pa.telefone, pa.data_nascimento, pa.endereco,
					u.ID AS user_id, u.display_name AS nome, u.user_email AS email
			 FROM " . self::T_PACIENTES . " pa
			 JOIN {$wpdb->users} u ON pa.user_id = u.ID
			 WHERE pa.id = %d",
			$paciente_id
		), ARRAY_A );
		return $row ?: null;
	}

	// Histórico clínico COMPLETO do paciente: todas as consultas, de TODOS os
	// médicos (continuidade de cuidados — o médico vê por onde o paciente passou).
	public static function historico_do_paciente( int $paciente_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT c.id, c.medico_id, c.inicio, c.duracao_min, c.status, c.observacoes,
					c.motivo_cancelamento, c.checkin_em, c.retorno_em, c.retorno_nota,
					e.nome AS esp_nome, u.display_name AS med_nome
			 FROM " . self::T_CONSULTAS . " c
			 JOIN " . self::T_MEDICOS . " md ON c.medico_id = md.id
			 JOIN {$wpdb->users} u ON md.user_id = u.ID
			 JOIN " . self::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
			 WHERE c.paciente_id = %d
			 ORDER BY c.inicio DESC",
			$paciente_id
		), ARRAY_A ) ?: array();
	}

	// Âmbito: este paciente alguma vez teve consulta com este médico?
	// Usar antes de abrir a ficha (menor privilégio: só os seus pacientes).
	public static function medico_tem_paciente( int $medico_id, int $paciente_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM " . self::T_CONSULTAS . " WHERE medico_id = %d AND paciente_id = %d LIMIT 1",
			$medico_id, $paciente_id
		) );
	}


	// Escrita (CRUD) — todos devolvem [bool $ok, string $codigo]

	public static function criar( array $in ): array {
		global $wpdb;

		$medico = self::medico( (int) $in['medico_id'] );
		if ( ! $medico ) {
			return array( false, 'invalido' );
		}

		$duracao = (int) $medico['duracao_padrao_min'] ?: 30;
		$duracao = max( self::DURACAO_MIN, min( self::DURACAO_MAX, $duracao ) );

		// Validação de negócio: a hora tem de ser um slot livre (horário, intervalo,
		// feriado, bloqueio, antecedência e limites por dia/hora).
		if ( class_exists( 'Clinica_Agenda' ) ) {
			$data_slot = date( 'Y-m-d', strtotime( $in['inicio'] ) );
			$hora_slot = date( 'H:i', strtotime( $in['inicio'] ) );
			if ( ! in_array( $hora_slot, Clinica_Agenda::slots_livres( (int) $in['medico_id'], $data_slot ), true ) ) {
				return array( false, 'indisponivel' );
			}
		}

		$ok = $wpdb->insert( self::T_CONSULTAS, array(
			'nome_cliente' => (string) ( $in['nome_cliente'] ?? '' ),  // a consulta guarda sempre o nome
			'paciente_id'  => (int) $in['paciente_id'],
			'medico_id'    => (int) $in['medico_id'],
			'inicio'       => $in['inicio'],
			'duracao_min'  => $duracao,
			'status'       => 'pendente',
			'slot_ativo'   => 1,  // ocupa o horário (uq_slot impede duplicados)
			'observacoes'  => $in['observacoes'],
		) + ( ! empty( $in['origem'] ) ? array( 'origem' => substr( (string) $in['origem'], 0, 16 ) ) : array() ) );

		// Guardar já: criar_para_consulta() faz outro INSERT e $wpdb->insert_id
		// passaria a ser o do pagamento, não o da consulta.
		$consulta_id = false !== $ok ? (int) $wpdb->insert_id : 0;

		// Cria o pagamento pendente da consulta (Financeiro / M-Pesa).
		if ( $consulta_id && class_exists( 'Clinica_Pagamentos' ) ) {
			$valor = class_exists( 'Clinica_Config' ) ? (float) Clinica_Config::int( 'valor_consulta_padrao', 1000 ) : 0.0;
			Clinica_Pagamentos::criar_para_consulta( $consulta_id, (int) $in['paciente_id'], $valor );
		}

		if ( $consulta_id ) {
			do_action( 'clinica_consulta_criada', $consulta_id );
		}

		// false = colisão com a UNIQUE uq_slot (mesmo médico, mesma hora).
		return $ok === false ? array( false, 'conflito' ) : array( true, 'criada' );
	}

	// Só edita enquanto a consulta está pendente.
	public static function atualizar( int $id, int $paciente_id, array $in ): array {
		$atual = self::consulta_do_paciente( $id, $paciente_id );
		if ( ! $atual || $atual['status'] !== 'pendente' ) {
			return array( false, 'negado' );
		}

		global $wpdb;
		$ok = $wpdb->update(
			self::T_CONSULTAS,
			array(
				'medico_id'   => (int) $in['medico_id'],
				'inicio'      => $in['inicio'],
				'observacoes' => $in['observacoes'],
			),
			array( 'id' => $id, 'paciente_id' => $paciente_id )
		);

		return $ok === false ? array( false, 'conflito' ) : array( true, 'atualizada' );
	}

	// Cancela mantendo histórico. slot_ativo passa a NULL e liberta o horário.
	public static function cancelar( int $id, int $paciente_id, string $motivo ): array {
		global $wpdb;
		$ok = $wpdb->update(
			self::T_CONSULTAS,
			array(
				'status'              => 'cancelada',
				'motivo_cancelamento' => $motivo,
				'cancelada_em'        => current_time( 'mysql' ),
			),
			array( 'id' => $id, 'paciente_id' => $paciente_id )
		);
		return $ok === false ? array( false, 'invalido' ) : array( true, 'cancelada' );
	}

	public static function apagar( int $id, int $paciente_id ): array {
		global $wpdb;
		$ok = $wpdb->delete(
			self::T_CONSULTAS,
			array( 'id' => $id, 'paciente_id' => $paciente_id )
		);
		return $ok ? array( true, 'removida' ) : array( false, 'invalido' );
	}

	// Check-in (receção): regista a CHEGADA do paciente. Não toca no estado
	// clínico — quem marca "realizada" é o médico.
	public static function registar_checkin( int $id ): array {
		global $wpdb;
		$ok = $wpdb->update(
			self::T_CONSULTAS,
			array( 'checkin_em' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		return $ok === false ? array( false, 'invalido' ) : array( true, 'rc_checkin_ok' );
	}

	// Reagendar (receção): novo médico/hora, validado contra os slots livres.
	// Volta a "pendente" — o médico tem de reconfirmar.
	public static function reagendar( int $id, int $medico_id, string $inicio ): array {
		global $wpdb;
		if ( ! self::medico( $medico_id ) ) {
			return array( false, 'invalido' );
		}
		if ( class_exists( 'Clinica_Agenda' ) ) {
			$d = date( 'Y-m-d', strtotime( $inicio ) );
			$h = date( 'H:i', strtotime( $inicio ) );
			if ( ! in_array( $h, Clinica_Agenda::slots_livres( $medico_id, $d ), true ) ) {
				return array( false, 'indisponivel' );
			}
		}
		// A hora mudou: o lembrete antigo já não serve.
		$ok = $wpdb->update(
			self::T_CONSULTAS,
			array( 'medico_id' => $medico_id, 'inicio' => $inicio, 'status' => 'pendente', 'slot_ativo' => 1, 'lembrete_em' => null ),
			array( 'id' => $id )
		);
		if ( false !== $ok ) {
			do_action( 'clinica_consulta_reagendada', $id );
		}
		return $ok === false ? array( false, 'conflito' ) : array( true, 'rc_reagendada' );
	}

	// Cancelar por staff (receção/gestão) — sem exigir o dono. Liberta o slot.
	public static function cancelar_staff( int $id, string $motivo ): array {
		global $wpdb;
		$ok = $wpdb->update(
			self::T_CONSULTAS,
			array(
				'status'              => 'cancelada',
				'motivo_cancelamento' => $motivo,
				'cancelada_em'        => current_time( 'mysql' ),
				'slot_ativo'          => null, // liberta o horário (uq_slot ignora NULLs)
			),
			array( 'id' => $id )
		);
		return $ok === false ? array( false, 'invalido' ) : array( true, 'cancelada' );
	}

	// Transição de estado feita pelo médico/gestão.
	public static function mudar_estado( int $id, string $novo ): array {
		if ( ! in_array( $novo, self::STATUS, true ) ) {
			return array( false, 'invalido' );
		}

		global $wpdb;
		$dados = array( 'status' => $novo );
		if ( $novo === 'cancelada' ) {
			$dados['cancelada_em'] = current_time( 'mysql' );
		}

		$ok = $wpdb->update( self::T_CONSULTAS, $dados, array( 'id' => $id ) );
		if ( false !== $ok ) {
			// Ponto de extensão: quem reage a mudanças de estado (lembretes,
			// notificações) liga-se aqui, sem que o repositório os conheça.
			do_action( 'clinica_consulta_estado', $id, $novo );
		}
		return $ok === false ? array( false, 'invalido' ) : array( true, 'estado' );
	}


	// Datas

	// 'Y-m-dTH:i' (input datetime-local) -> DATETIME MySQL. null se inválida.
	public static function normalizar_inicio( string $raw ): ?string {
		$raw = trim( $raw );
		$dt  = DateTime::createFromFormat( 'Y-m-d\TH:i', $raw )
			?: DateTime::createFromFormat( 'Y-m-d H:i:s', $raw )
			?: DateTime::createFromFormat( 'Y-m-d H:i', $raw );

		return $dt ? $dt->format( 'Y-m-d H:i:s' ) : null;
	}

	public static function e_futuro( string $mysql_dt ): bool {
		return strtotime( $mysql_dt ) > current_time( 'timestamp' );
	}

	// DATETIME MySQL -> valor aceite por <input type="datetime-local">.
	public static function para_input( string $mysql_dt ): string {
		return str_replace( ' ', 'T', substr( $mysql_dt, 0, 16 ) );
	}
}
