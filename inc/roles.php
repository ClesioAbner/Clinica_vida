<?php
/**
 * inc/roles.php
 *
 * Papéis e permissões (RBAC) da clínica, segundo boas práticas:
 *   • Menor privilégio — cada papel só tem o mínimo necessário.
 *   • Separação de deveres — quem gere o sistema (TI/Admin) NÃO tem acesso
 *     clínico por omissão; só o Médico lê/escreve dados clínicos.
 *   • Papéis legíveis, capabilities agrupadas por domínio.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Roles {

	const VERSION = '4';

	/* Capabilities agrupadas por domínio (todas prefixadas clinica_). */

	/** Paciente — só o que é seu. */
	const CAPS_PACIENTE = array( 'clinica_marcar', 'clinica_pagar', 'clinica_ver_proprio' );

	/** Clínico — EXCLUSIVO do médico (dados clínicos). */
	const CAPS_CLINICO = array( 'clinica_ver_agenda_propria', 'clinica_atender', 'clinica_diagnosticar', 'clinica_prescrever', 'clinica_pedir_exames', 'clinica_ver_processo' );

	/** Receção/Secretária — administrativo (sem clínico). */
	const CAPS_RECECAO = array( 'clinica_cadastrar_paciente', 'clinica_marcar', 'clinica_gerir_agenda', 'clinica_confirmar_presenca', 'clinica_confirmar_pagamento', 'clinica_emitir_recibo', 'clinica_ver_mensagens' );

	/** Administração/Gestão — nível TÁTICO: catálogos, agenda, pessoas (sem clínico).
	 * Pode PROPOR decisões estratégicas à Direção, mas não as decide. */
	const CAPS_GESTAO = array( 'clinica_gerir_medicos', 'clinica_gerir_funcionarios', 'clinica_gerir_especialidades', 'clinica_gerir_pagamentos', 'clinica_ver_relatorios', 'clinica_configurar', 'clinica_ver_mensagens', 'clinica_propor_estrategico' );

	/**
	 * Direção — nível EXECUTIVO: só estratégia. Lê indicadores consolidados,
	 * define metas e aprova o que é estratégico. NÃO gere a operação (não mexe
	 * em agendas, médicos, pagamentos individuais) nem toca em dados clínicos.
	 */
	const CAPS_DIRECAO = array( 'clinica_ver_direcao', 'clinica_ver_relatorios', 'clinica_definir_metas', 'clinica_aprovar_estrategico' );

	/** Sistema — exclusivo do Administrador (TI). */
	const CAPS_SISTEMA = array( 'clinica_gerir_utilizadores', 'clinica_gerir_roles', 'clinica_ver_auditoria', 'clinica_backup' );

	/** Papéis próprios da clínica -> [nome legível, capabilities]. */
	private static function papeis(): array {
		return array(
			'clinica_paciente'   => array( 'Paciente',      self::CAPS_PACIENTE ),
			'clinica_medico'     => array( 'Médico',        self::CAPS_CLINICO ),
			'clinica_secretaria' => array( 'Secretária',    self::CAPS_RECECAO ),
			'clinica_gestao'     => array( 'Administração', self::CAPS_GESTAO ),
			'clinica_direcao'    => array( 'Direção',       self::CAPS_DIRECAO ),
		);
	}

	/**
	 * Capabilities do Administrador: sistema + gestão + receção (operacional).
	 * NÃO inclui as clínicas — separação de deveres (TI ≠ dados clínicos).
	 */
	private static function caps_admin(): array {
		return array_values( array_unique( array_merge(
			self::CAPS_SISTEMA, self::CAPS_GESTAO, self::CAPS_RECECAO, self::CAPS_DIRECAO
		) ) );
	}

	/** Regista/atualiza os papéis (idempotente, por versão). */
	public static function instalar(): void {
		if ( get_option( 'clinica_roles_v' ) === self::VERSION ) {
			return;
		}

		foreach ( self::papeis() as $slug => $papel ) {
			$caps = array( 'read' => true );
			foreach ( $papel[1] as $c ) {
				$caps[ $c ] = true;
			}
			remove_role( $slug );          // idempotente: recria com as caps atuais
			add_role( $slug, $papel[0], $caps );
		}

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			// Retira o acesso clínico ao admin (pode ter sido dado na v1).
			foreach ( self::CAPS_CLINICO as $c ) {
				$admin->remove_cap( $c );
			}
			// Garante as capabilities operacionais e de sistema.
			foreach ( self::caps_admin() as $c ) {
				$admin->add_cap( $c );
			}
		}

		update_option( 'clinica_roles_v', self::VERSION );
	}
}

add_action( 'after_setup_theme', array( 'Clinica_Roles', 'instalar' ) );
