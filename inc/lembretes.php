<?php
/**
 * Lembretes por SMS antes da consulta: UM DIA antes e UMA HORA antes.
 *
 * Não há polling. Cada consulta agenda os SEUS eventos (inicio-24h e inicio-1h),
 * porque um cron horário nunca acertaria numa consulta das 9:30. O varrimento
 * horário é só rede de segurança para eventos perdidos.
 *
 * Os dois avisos são rastreados em colunas separadas (lembrete_dia_em e
 * lembrete_em): enviar o de um dia NÃO pode bloquear o de uma hora.
 *
 * Em produção o WP-Cron tem de ser substituído pelo cron do sistema, senão os
 * avisos só saem quando alguém visita o site:
 *   wp-config.php:  define( 'DISABLE_WP_CRON', true );
 *   crontab:        *\/5 * * * * curl -s https://dominio/wp-cron.php?doing_wp_cron
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Lembretes {

	// Evento próprio de cada consulta — dá a hora exata de cada aviso.
	const HOOK_HORA = 'clinica_lembrete_consulta'; // 1 hora antes (nome mantido)
	const HOOK_DIA  = 'clinica_lembrete_dia';      // 1 dia antes

	// Rede de segurança horária.
	const HOOK_VARRER = 'clinica_lembretes_varrer';

	const ANTEC_DIA  = DAY_IN_SECONDS;
	const ANTEC_HORA = HOUR_IN_SECONDS;

	/** Abaixo disto o paciente já vai a caminho — o aviso só irrita. */
	const MARGEM_MINIMA = 5 * MINUTE_IN_SECONDS;

	const DB_VERSION = '2';

	/** Os dois avisos: [ hook, antecedência, coluna de registo ]. */
	private static function avisos(): array {
		return array(
			'dia'  => array( self::HOOK_DIA, self::ANTEC_DIA, 'lembrete_dia_em' ),
			'hora' => array( self::HOOK_HORA, self::ANTEC_HORA, 'lembrete_em' ),
		);
	}

	public static function instalar(): void {
		if ( get_option( 'clinica_lembretes_db' ) !== self::DB_VERSION ) {
			global $wpdb;
			$t = Clinica_Repo::T_CONSULTAS;
			if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'lembrete_em'" ) ) {
				$wpdb->query( "ALTER TABLE {$t} ADD COLUMN lembrete_em DATETIME NULL DEFAULT NULL AFTER checkin_em" );
			}
			if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'lembrete_dia_em'" ) ) {
				$wpdb->query( "ALTER TABLE {$t} ADD COLUMN lembrete_dia_em DATETIME NULL DEFAULT NULL AFTER lembrete_em" );
			}
			update_option( 'clinica_lembretes_db', self::DB_VERSION );
		}

		if ( ! wp_next_scheduled( self::HOOK_VARRER ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK_VARRER );
		}
	}

	/** Agenda os dois avisos de uma consulta (o que ainda faz sentido). */
	public static function agendar( int $consulta_id ): void {
		self::cancelar( $consulta_id );

		$c = self::consulta( $consulta_id );
		if ( ! $c || ! in_array( $c['status'], array( 'pendente', 'confirmada' ), true ) ) {
			return;
		}

		$inicio = strtotime( $c['inicio'] );
		foreach ( self::avisos() as $tipo => $def ) {
			list( $hook, $antec, $coluna ) = $def;
			// Já enviado, ou o momento do aviso já passou → deixa ao varrimento.
			if ( ! empty( $c[ $coluna ] ) ) {
				continue;
			}
			$quando = $inicio - $antec;
			if ( $quando <= time() ) {
				continue;
			}
			wp_schedule_single_event( $quando, $hook, array( $consulta_id ) );
		}
	}

	/** Cancela os dois avisos (reagendamento, cancelamento, etc.). */
	public static function cancelar( int $consulta_id ): void {
		foreach ( self::avisos() as $def ) {
			$hook = $def[0];
			$ts   = wp_next_scheduled( $hook, array( $consulta_id ) );
			if ( $ts ) {
				wp_unschedule_event( $ts, $hook, array( $consulta_id ) );
			}
		}
	}

	/** Callback do evento de 1 dia. */
	public static function enviar_dia( int $consulta_id ): void {
		self::disparar( $consulta_id, 'dia' );
	}

	/** Callback do evento de 1 hora. */
	public static function enviar_hora( int $consulta_id ): void {
		self::disparar( $consulta_id, 'hora' );
	}

	/** Envia um aviso, reservando-o de forma atómica para não duplicar. */
	private static function disparar( int $consulta_id, string $tipo ): void {
		$avisos = self::avisos();
		if ( ! isset( $avisos[ $tipo ] ) ) {
			return;
		}
		$coluna = $avisos[ $tipo ][2];

		$c = self::consulta( $consulta_id );
		if ( ! $c ) {
			return;
		}
		// O estado pode ter mudado entre o agendamento e agora.
		if ( ! in_array( $c['status'], array( 'pendente', 'confirmada' ), true ) ) {
			return;
		}
		// Consulta já passou (ou está mesmo em cima) → não avisa.
		if ( strtotime( $c['inicio'] ) - time() < self::MARGEM_MINIMA ) {
			return;
		}
		if ( ! self::marcar( $consulta_id, $coluna ) ) {
			return;
		}

		if ( 'dia' === $tipo ) {
			Clinica_Sms_Mensagens::lembrete_um_dia(
				(string) $c['telefone'],
				(string) $c['paciente'],
				(string) $c['medico'],
				(string) $c['inicio']
			);
		} else {
			Clinica_Sms_Mensagens::lembrete_uma_hora(
				(string) $c['telefone'],
				(string) $c['paciente'],
				(string) $c['medico'],
				(string) $c['inicio']
			);
		}
	}

	/**
	 * Rede de segurança: apanha os avisos que o evento próprio perdeu.
	 * O de 1 hora cobre [agora, agora+1h]; o de 1 dia cobre (agora+1h, agora+24h]
	 * — assim não se sobrepõem.
	 */
	public static function varrer(): void {
		if ( ! Clinica_Sms::ativo() ) {
			return;
		}

		global $wpdb;
		$t       = Clinica_Repo::T_CONSULTAS;
		$agora_t = current_time( 'timestamp' );
		$agora   = current_time( 'mysql' );
		$mais1h  = date( 'Y-m-d H:i:s', $agora_t + self::ANTEC_HORA );
		$mais24h = date( 'Y-m-d H:i:s', $agora_t + self::ANTEC_DIA );

		// 1 hora — início entre agora e agora+1h, aviso de hora por enviar.
		$hora = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$t}
			 WHERE lembrete_em IS NULL AND status IN ('pendente','confirmada')
			   AND inicio > %s AND inicio <= %s LIMIT 200",
			$agora,
			$mais1h
		) );
		foreach ( $hora as $id ) {
			self::disparar( (int) $id, 'hora' );
		}

		// 1 dia — início entre agora+1h e agora+24h, aviso de dia por enviar.
		$dia = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$t}
			 WHERE lembrete_dia_em IS NULL AND status IN ('pendente','confirmada')
			   AND inicio > %s AND inicio <= %s LIMIT 200",
			$mais1h,
			$mais24h
		) );
		foreach ( $dia as $id ) {
			self::disparar( (int) $id, 'dia' );
		}
	}

	/**
	 * Reserva a consulta para este processo; false = outro já a levou.
	 * O UPDATE condicional é atómico e só um ganha (o WP-Cron pode disparar o
	 * mesmo evento em duas visitas simultâneas).
	 */
	private static function marcar( int $consulta_id, string $coluna ): bool {
		// Whitelist: a coluna nunca vem de input; ainda assim, garante-se.
		if ( ! in_array( $coluna, array( 'lembrete_em', 'lembrete_dia_em' ), true ) ) {
			return false;
		}
		global $wpdb;
		$t = Clinica_Repo::T_CONSULTAS;
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$t} SET {$coluna} = %s WHERE id = %d AND {$coluna} IS NULL",
			current_time( 'mysql' ),
			$consulta_id
		) );
		return $wpdb->rows_affected > 0;
	}

	private static function consulta( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT c.id, c.inicio, c.status, c.lembrete_em, c.lembrete_dia_em,
					pa.telefone, pu.display_name AS paciente, mu.display_name AS medico
			 FROM " . Clinica_Repo::T_CONSULTAS . " c
			 JOIN " . Clinica_Repo::T_PACIENTES . " pa ON c.paciente_id = pa.id
			 JOIN {$wpdb->users} pu ON pa.user_id = pu.ID
			 JOIN " . Clinica_Repo::T_MEDICOS . " md ON c.medico_id = md.id
			 JOIN {$wpdb->users} mu ON md.user_id = mu.ID
			 WHERE c.id = %d",
			$id
		), ARRAY_A );
		return $row ?: null;
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Lembretes', 'instalar' ) );

add_action( Clinica_Lembretes::HOOK_HORA, array( 'Clinica_Lembretes', 'enviar_hora' ) );
add_action( Clinica_Lembretes::HOOK_DIA, array( 'Clinica_Lembretes', 'enviar_dia' ) );
add_action( Clinica_Lembretes::HOOK_VARRER, array( 'Clinica_Lembretes', 'varrer' ) );

add_action( 'clinica_consulta_estado', static function ( int $id, string $novo ): void {
	if ( in_array( $novo, array( 'pendente', 'confirmada' ), true ) ) {
		Clinica_Lembretes::agendar( $id );
		return;
	}
	Clinica_Lembretes::cancelar( $id );
}, 10, 2 );

// Reagendar muda a hora: os avisos antigos deixam de servir.
add_action( 'clinica_consulta_reagendada', array( 'Clinica_Lembretes', 'agendar' ) );
add_action( 'clinica_consulta_criada', array( 'Clinica_Lembretes', 'agendar' ) );
