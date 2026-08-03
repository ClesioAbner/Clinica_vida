<?php

 // inc/clinic-ui.php
 
  //Duas responsabilidades, separadas em blocos:
   // A) Apresentação — mensagens flash, badge de estado.
   // B) Controladores — tratam POST/GET e redirecionam (PRG).
 
  //PRG (Post-Redirect-Get): cada gravação processa, redireciona com um
  //código curto (?cl=...) e sai. Dar refresh não reenvia o formulário.
  //Por isso os controladores correm ANTES de get_header(): depois do
  //primeiro output, o redirect já não é possível.


if ( ! defined( 'ABSPATH' ) ) exit;

// A) APRESENTAÇÃO


// Código curto -> [mensagem, tipo]. Evita passar texto livre no URL.
function clinica_flash( string $code ): ?array {
	$map = array(
		'criada'     => array( 'Consulta enviada com sucesso! Aguarda confirmação do médico.', 'success' ),
		'atualizada' => array( 'Consulta atualizada.',                                    'success' ),
		'cancelada'  => array( 'Consulta cancelada.',                                     'success' ),
		'removida'   => array( 'Consulta removida.',                                      'success' ),
		'estado'     => array( 'Estado da consulta atualizado.',                          'success' ),
		'conflito'   => array( 'O médico já tem uma marcação nesse horário.',             'error' ),
		'indisponivel' => array( 'Esse horário já não está disponível. Escolha outra hora.', 'error' ),
		'passado'    => array( 'A data e hora têm de ser no futuro.',                     'error' ),
		'invalido'   => array( 'Dados inválidos. Reveja os campos e tente de novo.',      'error' ),
		'negado'     => array( 'Ação não permitida para o estado atual da consulta.',     'error' ),
		'doc_ok'            => array( 'Documento anexado ao processo do paciente.',                      'success' ),
		'doc_dados'         => array( 'Indique um título para o documento.',                            'error' ),
		'doc_ficheiro'      => array( 'Escolha um ficheiro para anexar.',                               'error' ),
		'doc_tipo'          => array( 'Formato não aceite. Use PDF ou imagem (JPG, PNG, WEBP).',        'error' ),
		'doc_erro'          => array( 'Não foi possível guardar o ficheiro. Tente novamente.',          'error' ),
		'presc_ok'          => array( 'Prescrição registada no processo do paciente.',                   'success' ),
		'presc_dados'       => array( 'Indique o medicamento para prescrever.',                         'error' ),
		'exame_ok'          => array( 'Pedido de exame registado no processo do paciente.',             'success' ),
		'exame_dados'       => array( 'Indique o exame a pedir.',                                       'error' ),
		'fluxo_chamar'      => array( 'Paciente chamado. Pode iniciar a consulta quando entrar.',        'success' ),
		'fluxo_iniciar'     => array( 'Consulta iniciada.',                                             'success' ),
		'fluxo_pausar'      => array( 'Consulta em pausa.',                                             'info' ),
		'fluxo_retomar'     => array( 'Consulta retomada.',                                            'success' ),
		'fluxo_invalido'    => array( 'Essa ação não é possível no estado atual da consulta.',          'error' ),
		'fluxo_sem_checkin' => array( 'A receção ainda não deu entrada a este paciente.',               'error' ),
		'atendido'        => array( 'Atendimento registado. Consulta concluída.',                        'success' ),
		'sem_diagnostico' => array( 'Indique o diagnóstico (CID-10) para poder concluir o atendimento.', 'error' ),
		'erro'            => array( 'Não foi possível concluir a operação. Tente novamente.',           'error' ),
		'msg_enviada'  => array( 'Mensagem enviada com sucesso! Entraremos em contacto em breve.', 'success' ),
		'msg_invalida' => array( 'Reveja os campos: nome, email válido e mensagem são obrigatórios.', 'error' ),
		'login_falhou'   => array( 'Conta ou palavra-passe incorretas. Verifique os dados e tente de novo.', 'error' ),
		'registo_dados'  => array( 'Reveja os dados: nome, email válido e palavra-passe de 6+ caracteres.',   'error' ),
		'registo_existe' => array( 'Já existe uma conta com esse email. Inicie sessão.',                       'error' ),
		'registo_erro'   => array( 'Não foi possível criar a conta. Tente novamente.',                         'error' ),
		'perfil_ok'      => array( 'Perfil atualizado com sucesso.',                                            'success' ),
		'foto_erro'      => array( 'Não foi possível carregar a imagem. Use JPG ou PNG.',                       'error' ),
		'meta_ok'    => array( 'Metas atualizadas.',                                 'success' ),
		'prop_ok'    => array( 'Proposta registada. Aguarda decisão.',               'success' ),
		'prop_dados' => array( 'Indique um título para a proposta.',                  'error' ),
		'dec_ok'     => array( 'Decisão registada.',                                 'success' ),
		'dec_erro'   => array( 'Essa proposta já tinha sido decidida.',              'error' ),
		'esp_criada'     => array( 'Especialidade criada.',                          'success' ),
		'esp_atualizada' => array( 'Especialidade atualizada.',                      'success' ),
		'esp_apagada'    => array( 'Especialidade removida.',                        'success' ),
		'esp_em_uso'     => array( 'Não é possível remover: tem médicos associados.', 'error' ),
		'med_criado'     => array( 'Médico criado e conta ativada.',                 'success' ),
		'med_estado'     => array( 'Estado do médico atualizado.',                   'success' ),
		'med_sala_ok'    => array( 'Consultório do médico atualizado.',              'success' ),
		'med_dados'      => array( 'Reveja os dados do médico (nome, email, palavra-passe 6+, especialidade).', 'error' ),
		'med_existe'     => array( 'Já existe um utilizador com esse email.',        'error' ),
		'med_erro'       => array( 'Não foi possível criar o médico.',              'error' ),
		'ag_hor_ok'      => array( 'Horário adicionado.',           'success' ),
		'ag_hor_del'     => array( 'Horário removido.',             'success' ),
		'ag_fer_ok'      => array( 'Feriado adicionado.',           'success' ),
		'ag_fer_del'     => array( 'Feriado removido.',             'success' ),
		'ag_blq_ok'      => array( 'Folga/bloqueio adicionado.',    'success' ),
		'ag_blq_del'     => array( 'Folga/bloqueio removido.',      'success' ),
		'ag_invalido'    => array( 'Dados de agenda inválidos. Reveja as datas/horas.', 'error' ),
		'cfg_ok'         => array( 'Definições da clínica guardadas.', 'success' ),
		'pg_ok'          => array( 'Pagamento atualizado.', 'success' ),
		'rc_pac_ok'      => array( 'Paciente registado com sucesso.', 'success' ),
		'rc_pac_dados'   => array( 'Reveja os dados do paciente (nome e email válido são obrigatórios).', 'error' ),
		'rc_pac_existe'  => array( 'Já existe uma conta com esse email.', 'error' ),
		'nc_ok'          => array( 'Consulta marcada. A pessoa foi registada e a consulta agendada (fica pendente até o médico confirmar).', 'success' ),
		'nc_dados'       => array( 'Reveja os dados: nome completo e email válido são obrigatórios.', 'error' ),
		'nc_existe'      => array( 'Já existe uma pessoa com esse email. Use “Usar esta ficha” para reaproveitar em vez de duplicar.', 'error' ),
		'nc_invalido'    => array( 'Escolha o sector, o médico e a hora da consulta.', 'error' ),
		'pg_mp_ok'       => array( 'Pagamento M-Pesa confirmado. Já pode emitir a fatura.', 'success' ),
		'pg_mp_tel'      => array( 'Indique o número M-Pesa do cliente para cobrar.', 'error' ),
		'pg_mp_falhou'   => array( 'O pagamento M-Pesa não foi concluído. Tente de novo ou receba por outro meio.', 'error' ),
		'rc_msg_lida'    => array( 'Mensagem marcada como lida.', 'success' ),
		'rc_checkin_ok'  => array( 'Presença confirmada. O paciente entrou na fila de atendimento.', 'success' ),
		'rc_reagendada'  => array( 'Consulta reagendada. Aguarda nova confirmação do médico.', 'success' ),
		'rc_pac_ed_ok'   => array( 'Dados do paciente atualizados.', 'success' ),
	);
	return $map[ $code ] ?? null;
}

/**
 * Guarda uma mensagem exata para o próximo ecrã (padrão PRG).
 *
 * Alguns avisos não cabem num código fixo — o M-Pesa devolve o motivo real
 * ("saldo insuficiente", "cliente cancelou"). Guarda-se por 30s, ligado ao
 * utilizador, e é lido uma única vez no redirecionamento seguinte.
 */
function clinica_flash_dinamico( string $mensagem ): void {
	if ( '' !== $mensagem ) {
		set_transient( 'clinica_flash_dyn_' . get_current_user_id(), $mensagem, 30 );
	}
}

// Lê ?cl=... e imprime o alerta. Chamar no topo da vista.
function clinica_render_flash(): void {
	$code  = isset( $_GET['cl'] ) ? sanitize_key( $_GET['cl'] ) : '';
	$flash = clinica_flash( $code );
	if ( ! $flash ) {
		return;
	}

	$mensagem = $flash[0];
	// Avisos com motivo exato (ex.: M-Pesa) trazem o texto num transient.
	if ( 'pg_mp_falhou' === $code ) {
		$chave = 'clinica_flash_dyn_' . get_current_user_id();
		$dyn   = get_transient( $chave );
		if ( $dyn ) {
			delete_transient( $chave );
			$mensagem = (string) $dyn;
		}
	}

	$tipo = in_array( $flash[1], array( 'success', 'error', 'info' ), true ) ? $flash[1] : 'info';
	$role = 'error' === $tipo ? 'alert' : 'status';

	$icones = array(
		'success' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
		'error'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>',
		'info'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>',
	);

	clinica_flash_estilos();

	printf(
		'<div class="cl-toastwrap"><div class="cl-toast cl-toast--%1$s" role="%2$s" aria-live="%3$s"><span class="cl-toast__i">%4$s</span><span class="cl-toast__m">%5$s</span><button type="button" class="cl-toast__x" aria-label="Fechar mensagem">&times;</button></div></div>',
		esc_attr( $tipo ),
		esc_attr( $role ),
		'alert' === $role ? 'assertive' : 'polite',
		$icones[ $tipo ],
		esc_html( $mensagem )
	);
}

/**
 * CSS + JS do toast (pop-up) das mensagens do sistema. Impresso uma única vez.
 *
 * Autocontido de propósito: as mensagens do PRG aparecem em todos os portais e
 * páginas públicas, cada um com o seu CSS. Um toast fixo com estilos próprios
 * evita depender de cada folha de estilo e garante o mesmo aspeto em todo o lado.
 */
function clinica_flash_estilos(): void {
	static $feito = false;
	if ( $feito ) {
		return;
	}
	$feito = true;
	?>
	<style>
	.cl-toastwrap{position:fixed;top:18px;right:18px;z-index:99999;display:flex;flex-direction:column;gap:10px;width:360px;max-width:calc(100vw - 32px);pointer-events:none}
	.cl-toast{pointer-events:auto;display:flex;align-items:flex-start;gap:11px;padding:14px 14px 14px 16px;background:#fff;border:1px solid #E6EAF0;border-left:4px solid #4E7E9B;border-radius:13px;box-shadow:0 14px 36px rgba(15,23,42,.18);font-size:14px;font-weight:600;line-height:1.45;color:#1f2933;opacity:0;transform:translateY(-12px);transition:opacity .28s ease,transform .28s ease}
	.cl-toast.is-on{opacity:1;transform:translateY(0)}
	.cl-toast__i{flex:none;margin-top:1px}
	.cl-toast__i svg{display:block;width:19px;height:19px}
	.cl-toast__m{flex:1;min-width:0}
	.cl-toast__x{flex:none;margin:-3px -2px 0 0;padding:0 4px;background:none;border:none;font-size:21px;line-height:1;color:#9aa5b1;cursor:pointer;font-family:inherit}
	.cl-toast__x:hover{color:#1f2933}
	.cl-toast--success{border-left-color:#1E9E6A}.cl-toast--success .cl-toast__i{color:#1E9E6A}
	.cl-toast--error{border-left-color:#D64541}.cl-toast--error .cl-toast__i{color:#D64541}
	.cl-toast--info{border-left-color:#4E7E9B}.cl-toast--info .cl-toast__i{color:#4E7E9B}
	@media(max-width:520px){.cl-toastwrap{top:12px;right:12px;left:12px;width:auto}}
	</style>
	<script>
	(function(){
		function init(){
			document.querySelectorAll('.cl-toast:not([data-init])').forEach(function(t){
				t.setAttribute('data-init','1');
				requestAnimationFrame(function(){t.classList.add('is-on');});
				var fechar=function(){t.classList.remove('is-on');setTimeout(function(){var w=t.closest('.cl-toastwrap');(w||t).remove();},320);};
				var x=t.querySelector('.cl-toast__x');if(x){x.addEventListener('click',fechar);}
				setTimeout(fechar,5500);
			});
		}
		if(document.readyState!=='loading'){init();}else{document.addEventListener('DOMContentLoaded',init);}
	})();
	</script>
	<?php
}

/**
 * Nome de um médico com título, sem duplicar o que já lá está.
 * O display_name tanto vem "Dra. Ana Come" (dava "Dr(a). Dra. Ana Come")
 * como "Clesio" (sem título nenhum). Um sítio só decide isto.
 */
function clinica_nome_medico( string $nome ): string {
	$nome = trim( $nome );
	if ( '' === $nome ) {
		return '';
	}
	if ( preg_match( '/^(Dr|Dra|Prof)\.?\s/iu', $nome ) ) {
		return $nome;
	}
	return 'Dr(a). ' . $nome;
}

// Badge de estado, com o mesmo rótulo em todo o lado.
function clinica_status_pill( string $status ): string {
	$labels = array(
		'pendente'   => 'Pendente',
		'confirmada' => 'Confirmada',
		'cancelada'  => 'Cancelada',
		'realizada'  => 'Realizada',
		'faltou'     => 'Faltou',
	);
	$label = $labels[ $status ] ?? ucfirst( $status );
	return sprintf(
		'<span class="cl-pill cl-pill--%s">%s</span>',
		esc_attr( $status ),
		esc_html( $label )
	);
}

// B) CONTROLADORES (PRG)


// Redireciona para a mesma página com o código de resultado e limpa a ação.
function clinica_redirect( string $code, string $fragmento = '' ): void {
	$url = add_query_arg(
		'cl',
		rawurlencode( $code ),
		remove_query_arg( array( 'acao', 'id', 'nova', 'editar', '_wpnonce', 'cl' ) )
	);
	// Âncora opcional: devolve o utente ao formulário que submeteu, não ao topo.
	if ( '' !== $fragmento ) {
		$url .= '#' . rawurlencode( $fragmento );
	}
	if ( ! headers_sent() ) {
		wp_safe_redirect( $url );
		exit;
	}
}

// Portal do cliente: criar, atualizar, cancelar, apagar (só do próprio paciente).
function clinica_controller_cliente( int $paciente_id ): void {

	// Criar / atualizar
	if ( isset( $_POST['cl_action'] ) ) {
		if ( ! wp_verify_nonce( $_POST['cl_nonce'] ?? '', 'cl_save' ) ) {
			clinica_redirect( 'invalido' );
		}

		Clinica_Repo::guardar_perfil_paciente( $paciente_id, array(
			'telefone'        => Clinica_Input::texto( $_POST, 'telefone' ),
			'data_nascimento' => Clinica_Input::texto( $_POST, 'data_nascimento' ),
		) );

		$inicio    = Clinica_Repo::normalizar_inicio( $_POST['inicio'] ?? '' );
		$medico_id = intval( $_POST['medico_id'] ?? 0 );
		if ( ! $inicio || ! $medico_id ) {
			clinica_redirect( 'invalido' );
		}

		$dados = array(
			'nome_cliente' => Clinica_Input::texto( $_POST, 'nome_cliente' ) ?: wp_get_current_user()->display_name,
			'paciente_id'  => $paciente_id,
			'medico_id'    => $medico_id,
			'inicio'       => $inicio,
			'observacoes'  => sanitize_textarea_field( $_POST['observacoes'] ?? '' ),
		);

		if ( $_POST['cl_action'] === 'atualizar' ) {
			list( , $code ) = Clinica_Repo::atualizar( intval( $_POST['consulta_id'] ?? 0 ), $paciente_id, $dados );
			clinica_redirect( $code );
		}

		if ( ! Clinica_Repo::e_futuro( $inicio ) ) {
			clinica_redirect( 'passado' );
		}
		list( , $code ) = Clinica_Repo::criar( $dados );
		clinica_redirect( $code );
	}

	// Cancelar
	if ( isset( $_POST['cl_cancelar'] ) ) {
		if ( ! wp_verify_nonce( $_POST['cl_cancel_nonce'] ?? '', 'cl_cancel' ) ) {
			clinica_redirect( 'invalido' );
		}
		list( , $code ) = Clinica_Repo::cancelar(
			intval( $_POST['consulta_id'] ?? 0 ),
			$paciente_id,
			sanitize_text_field( $_POST['motivo_cancelamento'] ?? '' )
		);
		clinica_redirect( $code );
	}

	// Apagar (link GET com nonce por id)
	if ( ( $_GET['acao'] ?? '' ) === 'apagar' && isset( $_GET['id'] ) ) {
		$id = intval( $_GET['id'] );
		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'cl_delete_' . $id ) ) {
			clinica_redirect( 'invalido' );
		}
		list( , $code ) = Clinica_Repo::apagar( $id, $paciente_id );
		clinica_redirect( $code );
	}
}

// Painel do médico: registar atendimento (clínico) ou mudar o estado de uma consulta.
function clinica_controller_medico(): void {
	// Registar atendimento: nota SOAP + diagnóstico (CID-10) e concluir a consulta.
	if ( isset( $_POST['cl_atender'] ) ) {
		if ( ! current_user_can( 'clinica_atender' ) || ! wp_verify_nonce( $_POST['cl_atender_nonce'] ?? '', 'cl_atender' ) ) {
			clinica_redirect( 'negado' );
		}
		$medico_id = Clinica_Repo::medico_id_do_user( get_current_user_id() );
		list( , $code ) = Clinica_Clinico::registar_atendimento(
			intval( $_POST['consulta_id'] ?? 0 ),
			$medico_id,
			array(
				'subjetivo'     => wp_unslash( $_POST['subjetivo'] ?? '' ),
				'objetivo'      => wp_unslash( $_POST['objetivo'] ?? '' ),
				'avaliacao'     => wp_unslash( $_POST['avaliacao'] ?? '' ),
				'plano'         => wp_unslash( $_POST['plano'] ?? '' ),
				'cid10'         => sanitize_text_field( wp_unslash( $_POST['cid10'] ?? '' ) ),
				'cid_descricao' => wp_unslash( $_POST['cid_descricao'] ?? '' ),
				'retorno_prazo' => intval( $_POST['retorno_prazo'] ?? 0 ),
				'retorno_nota'  => wp_unslash( $_POST['retorno_nota'] ?? '' ),
			)
		);
		clinica_redirect( $code );
	}

	// ---- Prescrever medicamento ---------------------------------------------
	if ( isset( $_POST['cl_prescrever'] ) ) {
		if ( ! current_user_can( 'clinica_prescrever' ) || ! wp_verify_nonce( $_POST['cl_presc_nonce'] ?? '', 'cl_presc' ) ) {
			clinica_redirect( 'negado' );
		}
		$medico_id = (int) Clinica_Repo::medico_id_do_user( get_current_user_id() );
		list( , $code ) = Clinica_Clinico::prescrever(
			intval( $_POST['paciente_id'] ?? 0 ),
			$medico_id,
			intval( $_POST['consulta_id'] ?? 0 ),
			array(
				'medicamento' => sanitize_text_field( wp_unslash( $_POST['medicamento'] ?? '' ) ),
				'dosagem'     => sanitize_text_field( wp_unslash( $_POST['dosagem'] ?? '' ) ),
				'posologia'   => sanitize_text_field( wp_unslash( $_POST['posologia'] ?? '' ) ),
				'duracao'     => sanitize_text_field( wp_unslash( $_POST['duracao'] ?? '' ) ),
				'notas'       => sanitize_textarea_field( wp_unslash( $_POST['notas'] ?? '' ) ),
			)
		);
		clinica_redirect( $code );
	}

	// ---- Anexar documento ao processo ---------------------------------------
	if ( isset( $_POST['cl_documento'] ) ) {
		if ( ! current_user_can( 'clinica_ver_processo' ) || ! wp_verify_nonce( $_POST['cl_doc_nonce'] ?? '', 'cl_doc' ) ) {
			clinica_redirect( 'negado' );
		}
		list( , $code ) = Clinica_Clinico::anexar_documento(
			intval( $_POST['paciente_id'] ?? 0 ),
			intval( $_POST['consulta_id'] ?? 0 ),
			array(
				'titulo' => sanitize_text_field( wp_unslash( $_POST['titulo'] ?? '' ) ),
				'tipo'   => sanitize_text_field( wp_unslash( $_POST['tipo'] ?? '' ) ),
			),
			isset( $_FILES['documento'] ) ? $_FILES['documento'] : array()
		);
		clinica_redirect( $code );
	}

	// ---- Pedir exame ---------------------------------------------------------
	if ( isset( $_POST['cl_exame'] ) ) {
		if ( ! current_user_can( 'clinica_pedir_exames' ) || ! wp_verify_nonce( $_POST['cl_exame_nonce'] ?? '', 'cl_exame' ) ) {
			clinica_redirect( 'negado' );
		}
		$medico_id = (int) Clinica_Repo::medico_id_do_user( get_current_user_id() );
		list( , $code ) = Clinica_Clinico::pedir_exame(
			intval( $_POST['paciente_id'] ?? 0 ),
			$medico_id,
			intval( $_POST['consulta_id'] ?? 0 ),
			array(
				'exame'    => sanitize_text_field( wp_unslash( $_POST['exame'] ?? '' ) ),
				'tipo'     => sanitize_text_field( wp_unslash( $_POST['tipo'] ?? '' ) ),
				'urgencia' => sanitize_key( $_POST['urgencia'] ?? 'normal' ),
				'notas'    => sanitize_textarea_field( wp_unslash( $_POST['notas'] ?? '' ) ),
			)
		);
		clinica_redirect( $code );
	}

	// ---- Fluxo de atendimento: chamar / iniciar / pausar / retomar ----------
	if ( isset( $_POST['cl_fluxo'] ) ) {
		if ( ! current_user_can( 'clinica_atender' ) || ! wp_verify_nonce( $_POST['cl_fluxo_nonce'] ?? '', 'cl_fluxo' ) ) {
			clinica_redirect( 'negado' );
		}
		$medico_id = Clinica_Repo::medico_id_do_user( get_current_user_id() );
		list( , $code ) = Clinica_Clinico::fluxo(
			intval( $_POST['consulta_id'] ?? 0 ),
			(int) $medico_id,
			sanitize_key( $_POST['cl_fluxo'] ?? '' ),
			sanitize_text_field( wp_unslash( $_POST['motivo'] ?? '' ) )
		);
		clinica_redirect( $code );
	}

	if ( ! isset( $_POST['cl_estado'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_POST['cl_estado_nonce'] ?? '', 'cl_estado' ) ) {
		clinica_redirect( 'invalido' );
	}
	// Separação de deveres: o médico NÃO confirma, aceita, recusa, cancela,
	// remarca nem altera horários ou o médico responsável — isso é da receção.
	// O ato clínico (concluir) é registado em Atender, com diagnóstico obrigatório.
	clinica_redirect( 'negado' );
}
