<?php
/**
 * inc/agenda.php
 *
 * Motor de agenda (Fase 2). Calcula os horários DISPONÍVEIS de um médico numa
 * data, aplicando as regras de negócio (config): duração, intervalo de almoço,
 * feriados, bloqueios/folgas, antecedência mínima e limites por dia/hora.
 *
 * Slots livres = horário do médico − ocupados − intervalo − feriado/bloqueio
 *                − passado/antecedência − limites.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Agenda {

	const T_HORARIOS  = 'clinica_horarios';
	const T_FERIADOS  = 'clinica_feriados';
	const T_BLOQUEIOS = 'clinica_bloqueios';
	const DB_VERSION  = '1';

	/** dia_semana segue o ISO: 1=Segunda … 7=Domingo (= date('N')). */

	/** Cria as tabelas de feriados/bloqueios e semeia horários por omissão. */
	public static function instalar(): void {
		if ( get_option( 'clinica_agenda_db' ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE " . self::T_FERIADOS . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			data DATE NOT NULL,
			descricao VARCHAR(120) NULL,
			PRIMARY KEY (id),
			UNIQUE KEY data (data)
		) {$charset};" );

		dbDelta( "CREATE TABLE " . self::T_BLOQUEIOS . " (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			medico_id BIGINT UNSIGNED NOT NULL,
			data_inicio DATE NOT NULL,
			data_fim DATE NOT NULL,
			motivo VARCHAR(120) NULL,
			PRIMARY KEY (id),
			KEY medico_id (medico_id)
		) {$charset};" );

		// Semear horários por omissão para os médicos que ainda não têm (Seg–Sáb, 08–17).
		$medicos = $wpdb->get_col( "SELECT id FROM " . Clinica_Repo::T_MEDICOS );
		foreach ( $medicos as $mid ) {
			$tem = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . self::T_HORARIOS . " WHERE medico_id = %d", $mid ) );
			if ( $tem ) {
				continue;
			}
			for ( $dow = 1; $dow <= 6; $dow++ ) {
				$wpdb->insert( self::T_HORARIOS, array(
					'medico_id'   => (int) $mid,
					'dia_semana'  => $dow,
					'hora_inicio' => '08:00:00',
					'hora_fim'    => '17:00:00',
					'ativo'       => 1,
				), array( '%d', '%d', '%s', '%s', '%d' ) );
			}
		}

		update_option( 'clinica_agenda_db', self::DB_VERSION );
	}

	private static function min( string $hora ): int {
		$p = explode( ':', $hora );
		return ( (int) $p[0] ) * 60 + ( (int) ( $p[1] ?? 0 ) );
	}

	public static function e_feriado( string $data ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM " . self::T_FERIADOS . " WHERE data = %s", $data ) );
	}

	public static function esta_bloqueado( int $medico_id, string $data ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM " . self::T_BLOQUEIOS . " WHERE medico_id = %d AND %s BETWEEN data_inicio AND data_fim",
			$medico_id, $data
		) );
	}

	/**
	 * Horas disponíveis ('HH:MM') de um médico numa data (Y-m-d).
	 * Devolve [] se o dia não tiver disponibilidade.
	 */
	public static function slots_livres( int $medico_id, string $data ): array {
		global $wpdb;

		$ts_dia = strtotime( $data . ' 00:00:00' );
		if ( ! $ts_dia ) {
			return array();
		}
		if ( self::e_feriado( $data ) || self::esta_bloqueado( $medico_id, $data ) ) {
			return array();
		}

		$dow      = (int) date( 'N', $ts_dia );
		$horarios = $wpdb->get_results( $wpdb->prepare(
			"SELECT hora_inicio, hora_fim FROM " . self::T_HORARIOS . " WHERE medico_id = %d AND dia_semana = %d AND ativo = 1 ORDER BY hora_inicio",
			$medico_id, $dow
		), ARRAY_A );
		if ( ! $horarios ) {
			return array();
		}

		$dur      = max( 5, Clinica_Config::int( 'duracao_consulta_min', 30 ) );
		$int_i    = self::min( (string) Clinica_Config::get( 'intervalo_inicio', '13:00' ) );
		$int_f    = self::min( (string) Clinica_Config::get( 'intervalo_fim', '14:00' ) );
		$max_dia  = Clinica_Config::int( 'max_consultas_dia', 40 );
		$max_hora = Clinica_Config::int( 'max_consultas_hora', 5 );
		$antec    = Clinica_Config::int( 'antecedencia_min_horas', 2 );

		// Ocupados (slot_ativo=1) nesse dia.
		$ocup_raw = $wpdb->get_col( $wpdb->prepare(
			"SELECT TIME_FORMAT(inicio, '%%H:%%i') FROM " . Clinica_Repo::T_CONSULTAS . " WHERE medico_id = %d AND DATE(inicio) = %s AND slot_ativo = 1",
			$medico_id, $data
		) );
		if ( count( $ocup_raw ) >= $max_dia ) {
			return array();
		}
		$ocupados = array_flip( $ocup_raw );
		$por_hora = array();
		foreach ( $ocup_raw as $o ) {
			$h              = substr( $o, 0, 2 );
			$por_hora[ $h ] = ( $por_hora[ $h ] ?? 0 ) + 1;
		}

		$limite_ts = time() + $antec * HOUR_IN_SECONDS;
		$slots     = array();

		foreach ( $horarios as $h ) {
			$ini = self::min( $h['hora_inicio'] );
			$fim = self::min( $h['hora_fim'] );
			for ( $m = $ini; $m + $dur <= $fim; $m += $dur ) {
				if ( $m >= $int_i && $m < $int_f ) {
					continue; // intervalo de almoço
				}
				$hhmm = sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 );
				if ( isset( $ocupados[ $hhmm ] ) ) {
					continue; // já ocupado
				}
				if ( strtotime( $data . ' ' . $hhmm ) < $limite_ts ) {
					continue; // passado / antecedência mínima
				}
				$hh = substr( $hhmm, 0, 2 );
				if ( ( $por_hora[ $hh ] ?? 0 ) >= $max_hora ) {
					continue; // limite por hora
				}
				$slots[] = $hhmm;
			}
		}

		return $slots;
	}

	// Leituras (gestão)

	/** Horário semanal de um médico. */
	public static function horarios_medico( int $medico_id ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT id, dia_semana, hora_inicio, hora_fim, ativo FROM " . self::T_HORARIOS . " WHERE medico_id = %d ORDER BY dia_semana, hora_inicio",
			$medico_id
		), ARRAY_A ) ?: array();
	}

	/** Todos os feriados. */
	public static function feriados(): array {
		global $wpdb;
		return $wpdb->get_results( "SELECT id, data, descricao FROM " . self::T_FERIADOS . " ORDER BY data", ARRAY_A ) ?: array();
	}

	/** Todos os bloqueios/folgas, com o nome do médico. */
	public static function bloqueios_todos(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT b.id, b.medico_id, b.data_inicio, b.data_fim, b.motivo, u.display_name AS medico
			 FROM " . self::T_BLOQUEIOS . " b
			 JOIN " . Clinica_Repo::T_MEDICOS . " md ON b.medico_id = md.id
			 JOIN {$wpdb->users} u ON md.user_id = u.ID
			 ORDER BY b.data_inicio DESC",
			ARRAY_A
		) ?: array();
	}

	public static function hora_valida( string $h ): bool {
		return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $h );
	}

	public static function data_valida( string $d ): bool {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d );
	}
}

/**
 * Controlador PRG da gestão de agenda (horários, feriados, folgas).
 * Requer capability clinica_gerir_agenda (secretária/admin).
 */
function clinica_controller_agenda(): void {
	if ( empty( $_POST['ag_action'] ) ) {
		return;
	}
	if ( ! current_user_can( 'clinica_gerir_agenda' ) || ! Clinica_Input::nonce_ok( $_POST, 'ag_nonce', 'ag_manage' ) ) {
		clinica_redirect( 'negado' );
	}
	global $wpdb;
	$acao = sanitize_key( $_POST['ag_action'] );

	// Horário semanal 
	if ( 'hor_add' === $acao ) {
		$mid = Clinica_Input::inteiro( $_POST, 'medico_id' );
		$dow = Clinica_Input::inteiro( $_POST, 'dia_semana' );
		$hi  = Clinica_Input::texto( $_POST, 'hora_inicio' );
		$hf  = Clinica_Input::texto( $_POST, 'hora_fim' );
		if ( ! $mid || $dow < 1 || $dow > 7 || ! Clinica_Agenda::hora_valida( $hi ) || ! Clinica_Agenda::hora_valida( $hf ) || $hi >= $hf ) {
			clinica_redirect( 'ag_invalido' );
		}
		$wpdb->insert( Clinica_Agenda::T_HORARIOS, array(
			'medico_id'   => $mid,
			'dia_semana'  => $dow,
			'hora_inicio' => $hi . ':00',
			'hora_fim'    => $hf . ':00',
			'ativo'       => 1,
		), array( '%d', '%d', '%s', '%s', '%d' ) );
		clinica_redirect( 'ag_hor_ok' );
	}
	if ( 'hor_del' === $acao ) {
		$wpdb->delete( Clinica_Agenda::T_HORARIOS, array( 'id' => Clinica_Input::inteiro( $_POST, 'id' ) ), array( '%d' ) );
		clinica_redirect( 'ag_hor_del' );
	}

	//  Feriados 
	if ( 'fer_add' === $acao ) {
		$data = Clinica_Input::texto( $_POST, 'data' );
		if ( ! Clinica_Agenda::data_valida( $data ) ) {
			clinica_redirect( 'ag_invalido' );
		}
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO " . Clinica_Agenda::T_FERIADOS . " (data, descricao) VALUES (%s, %s)",
			$data, mb_substr( Clinica_Input::texto( $_POST, 'descricao' ), 0, 120 )
		) );
		clinica_redirect( 'ag_fer_ok' );
	}
	if ( 'fer_del' === $acao ) {
		$wpdb->delete( Clinica_Agenda::T_FERIADOS, array( 'id' => Clinica_Input::inteiro( $_POST, 'id' ) ), array( '%d' ) );
		clinica_redirect( 'ag_fer_del' );
	}

	//  Folgas / bloqueios 
	if ( 'blq_add' === $acao ) {
		$mid = Clinica_Input::inteiro( $_POST, 'medico_id' );
		$di  = Clinica_Input::texto( $_POST, 'data_inicio' );
		$df  = Clinica_Input::texto( $_POST, 'data_fim' );
		if ( ! $mid || ! Clinica_Agenda::data_valida( $di ) || ! Clinica_Agenda::data_valida( $df ) || $di > $df ) {
			clinica_redirect( 'ag_invalido' );
		}
		$wpdb->insert( Clinica_Agenda::T_BLOQUEIOS, array(
			'medico_id'   => $mid,
			'data_inicio' => $di,
			'data_fim'    => $df,
			'motivo'      => mb_substr( Clinica_Input::texto( $_POST, 'motivo' ), 0, 120 ),
		), array( '%d', '%s', '%s', '%s' ) );
		clinica_redirect( 'ag_blq_ok' );
	}
	if ( 'blq_del' === $acao ) {
		$wpdb->delete( Clinica_Agenda::T_BLOQUEIOS, array( 'id' => Clinica_Input::inteiro( $_POST, 'id' ) ), array( '%d' ) );
		clinica_redirect( 'ag_blq_del' );
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Agenda', 'instalar' ) );

/** AJAX: devolve as horas livres de um médico numa data (para o /marcar/). */
add_action( 'wp_ajax_clinica_slots', 'clinica_ajax_slots' );
function clinica_ajax_slots(): void {
	$medico = isset( $_GET['medico'] ) ? absint( $_GET['medico'] ) : 0;
	$data   = isset( $_GET['data'] ) ? sanitize_text_field( wp_unslash( $_GET['data'] ) ) : '';
	if ( ! $medico || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $data ) ) {
		wp_send_json( array() );
	}
	wp_send_json( Clinica_Agenda::slots_livres( $medico, $data ) );
}
