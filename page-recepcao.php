<?php
/**
 * Template Name: Clínica — Receção
 *
 * Portal da Secretária / Receção (app com sidebar): painel, agenda do dia com
 * check-in, marcação por paciente, gestão de pacientes, pagamentos na receção e
 * caixa de mensagens. Backend: inc/recepcao.php. Âmbito: toda a clínica.
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_stylesheet_directory() . '/inc/clinic-repo.php';
require_once get_stylesheet_directory() . '/inc/clinic-ui.php';

if ( ! is_user_logged_in() || ( ! current_user_can( 'clinica_confirmar_presenca' ) && ! current_user_can( 'clinica_cadastrar_paciente' ) ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

clinica_controller_recepcao();

// Recibo imprimível: documento autónomo (termina o pedido).
if ( isset( $_GET['recibo'] ) ) {
	clinica_recepcao_render_recibo( (int) $_GET['recibo'] );
}

// Ficha do paciente em PDF (documento autónomo, termina o pedido).
if ( isset( $_GET['ficha'] ) ) {
	clinica_recepcao_render_ficha( (int) $_GET['ficha'] );
}

get_header();

$utilizador = wp_get_current_user();
$avatar     = function_exists( 'clinica_avatar_url' ) ? clinica_avatar_url( $utilizador->ID, 96 ) : get_avatar_url( $utilizador->ID );
$primeiro   = explode( ' ', trim( $utilizador->display_name ) )[0];



$agora     = current_time( 'timestamp' );
$hora_dia  = (int) date_i18n( 'G', $agora );
$saudacao  = $hora_dia < 12 ? 'Bom dia' : ( $hora_dia < 19 ? 'Boa tarde' : 'Boa noite' );
$data_hoje = date_i18n( 'l, j \d\e F', $agora );
$hoje_ymd  = date_i18n( 'Y-m-d', $agora );


// Dados (reaproveita os motores existentes).
$consultas  = Clinica_Repo::consultas_todas();
$kpis       = Clinica_Repo::contagem_por_estado();
$pacientes  = clinica_recepcao_pacientes();

// Dados para o autocomplete de paciente (pesquisa com sugestões).
$pac_ac = array();
foreach ( $pacientes as $p ) {
	$nm    = trim( (string) $p['nome'] );
	$parts = preg_split( '/\s+/', $nm ) ?: array( $nm );
	$ini   = mb_strtoupper( mb_substr( $parts[0], 0, 1 ) . ( count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '' ) );
	$pac_ac[] = array(
		'id' => (int) $p['id'],
		'n'  => $nm,
		'm'  => (string) ( $p['telefone'] ?: ( $p['email'] ?? '' ) ),
		'b'  => mb_strtolower( $nm . ' ' . ( $p['telefone'] ?? '' ) . ' ' . ( $p['bi'] ?? '' ) ),
		'i'  => $ini,
	);
}
$medicos    = array_filter( clinica_gestao_medicos(), static function ( $m ) { return (int) $m['ativo'] === 1; } );
$esp_list   = Clinica_Repo::especialidades();          // para o seletor de marcação
$med_list   = Clinica_Repo::medicos();                 // traz especialidade_id (ativos)
$dur_padrao = class_exists( 'Clinica_Config' ) ? Clinica_Config::int( 'duracao_consulta_min', 30 ) : 30;
$antec_min  = class_exists( 'Clinica_Config' ) ? Clinica_Config::int( 'antecedencia_min_horas', 2 ) : 2;
$valor_cons = class_exists( 'Clinica_Config' ) ? Clinica_Config::int( 'valor_consulta_padrao', 1000 ) : 1000;

// Atalhos de data para a marcação (hoje + 6 dias).
$dias_rapidos = array();
for ( $i = 0; $i < 7; $i++ ) {
	$ts = strtotime( "+$i day", $agora );
	$dias_rapidos[] = array(
		'v' => date( 'Y-m-d', $ts ),
		'l' => 0 === $i ? 'Hoje' : ( 1 === $i ? 'Amanhã' : ucfirst( date_i18n( 'D', $ts ) ) ),
		'd' => date_i18n( 'j/n', $ts ),
	);
}
$pagamentos = Clinica_Pagamentos::listar( 'pendente' );
$pg_pagos   = Clinica_Pagamentos::listar( 'pago' );
$pg_tot     = Clinica_Pagamentos::totais();
$mensagens  = Clinica_Contacto::listar( 50 );
$msg_novas  = Clinica_Contacto::nao_lidas();


// Apenas mensagens reais do formulário do site (sem exemplos).
$moeda      = class_exists( 'Clinica_Config' ) ? Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';

$labels   = array(
	'pendente'       => 'Pendente',
	'confirmada'     => 'Confirmada',
	'chamada'        => 'Chamada',
	'em_atendimento' => 'Em atendimento',
	'pausada'        => 'Em pausa',
	'realizada'      => 'Realizada',
	'cancelada'      => 'Cancelada',
	'faltou'         => 'Faltou',
);
$fmt      = static function ( $iso ) { return date_i18n( 'd/m · H:i', strtotime( $iso ) ); };
$fmt_hora = static function ( $iso ) { return date_i18n( 'H:i', strtotime( $iso ) ); };
$fmt_dt   = static function ( $iso ) { return date_i18n( 'd/m/Y · H:i', strtotime( $iso ) ); };

// Agenda de hoje (não canceladas) e próximas (pendente/confirmada no futuro).
$hoje_lista = array_values( array_filter( $consultas, static function ( $c ) use ( $hoje_ymd ) {
	return substr( $c['inicio'], 0, 10 ) === $hoje_ymd && 'cancelada' !== $c['status'];
} ) );
usort( $hoje_lista, static function ( $a, $b ) { return strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] ); } );
$futuras = array_values( array_filter( $consultas, static function ( $c ) {
	return strtotime( $c['inicio'] ) >= time() && in_array( $c['status'], array( 'pendente', 'confirmada' ), true );
} ) );
usort( $futuras, static function ( $a, $b ) { return strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] ); } );

$por_confirmar = (int) $kpis['pendente'];

$fila = clinica_recepcao_fila();
$site_pend = class_exists( 'Clinica_Marcacao_Rapida' ) ? Clinica_Marcacao_Rapida::por_confirmar() : array();

// Agenda do dia: dia selecionado (?dia=Y-m-d), navegação e resumo
$dia_sel = isset( $_GET['dia'] ) ? sanitize_text_field( wp_unslash( $_GET['dia'] ) ) : $hoje_ymd;
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $dia_sel ) || ! strtotime( $dia_sel ) ) {
	$dia_sel = $hoje_ymd;
}
$dia_ts   = strtotime( $dia_sel );
$dia_prev = date( 'Y-m-d', strtotime( '-1 day', $dia_ts ) );
$dia_next = date( 'Y-m-d', strtotime( '+1 day', $dia_ts ) );
$dia_hoje = ( $dia_sel === $hoje_ymd );

$dia_lista = array_values( array_filter( $consultas, static function ( $c ) use ( $dia_sel ) {
	return substr( $c['inicio'], 0, 10 ) === $dia_sel;
} ) );
usort( $dia_lista, static function ( $a, $b ) { return strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] ); } );

$d_conf = 0; $d_pend = 0; $d_fila = 0; $d_feito = 0;
foreach ( $dia_lista as $c ) {
	$terminada = in_array( $c['status'], array( 'realizada', 'cancelada', 'faltou' ), true );
	if ( 'pendente' === $c['status'] ) { $d_pend++; }
	if ( 'confirmada' === $c['status'] ) { $d_conf++; }
	if ( $terminada ) { $d_feito++; }
	if ( ! empty( $c['checkin_em'] ) && ! $terminada ) { $d_fila++; }
}

// Receção = nível OPERACIONAL: sem gráficos de tendência (isso é tático/executivo,
// vive na Gestão). O painel foca no que é acionável agora.

// Próximo paciente (o primeiro da fila; senão a próxima consulta).
$prox_c   = $fila[0] ?? ( $futuras[0] ?? null );
$prox_fic = null; $prox_idade = null; $prox_ult = ''; $prox_n = 0;
if ( $prox_c && ! empty( $prox_c['paciente_id'] ) ) {
	$prox_fic = Clinica_Repo::paciente_ficha( (int) $prox_c['paciente_id'] );
	if ( $prox_fic ) {
		$prox_idade = Clinica_Repo::idade( $prox_fic['data_nascimento'] ?? null );
		$hs         = Clinica_Repo::historico_do_paciente( (int) $prox_c['paciente_id'] );
		$prox_n     = count( $hs );
		foreach ( $hs as $h ) {
			if ( 'realizada' === $h['status'] ) { $prox_ult = $h['inicio']; break; }
		}
	}
}

// Médicos em atendimento (consultas de hoje por médico).
$med_hoje = array();
foreach ( $hoje_lista as $h ) {
	$med_hoje[ $h['med_nome'] ] = ( $med_hoje[ $h['med_nome'] ] ?? 0 ) + 1;
}
$med_estado = array();
foreach ( $hoje_lista as $h ) {
	$ini = strtotime( $h['inicio'] );
	$fim = $ini + ( (int) $h['duracao_min'] * 60 );
	if ( $agora >= $ini && $agora <= $fim ) { $med_estado[ $h['med_nome'] ] = 'Em atendimento'; }
}

// Pedidos por confirmar (mapeia os "approval requests" da referência).
$pedidos = array_values( array_filter( $consultas, static function ( $c ) { return 'pendente' === $c['status']; } ) );
usort( $pedidos, static function ( $a, $b ) { return strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] ); } );

// Gráficos do dashboard (só dados de HOJE, já em memória — sem query pesada).
// A tendência histórica continua a ser tático/executivo (vive na Gestão);
// aqui os gráficos respondem "como está a correr o dia AGORA".
$hora_ini = 8;
$hora_fim = 19;
$por_hora = array();
for ( $h = $hora_ini; $h <= $hora_fim; $h++ ) { $por_hora[ $h ] = 0; }
foreach ( $hoje_lista as $c ) {
	$hh = (int) date_i18n( 'G', strtotime( $c['inicio'] ) );
	$hh = max( $hora_ini, min( $hora_fim, $hh ) );
	$por_hora[ $hh ]++;
}

// Estado de hoje (barras do painel).
$h_pend = 0; $h_conf = 0; $h_feito = 0;
foreach ( $hoje_lista as $c ) {
	if ( 'pendente' === $c['status'] ) { $h_pend++; }
	elseif ( 'confirmada' === $c['status'] ) { $h_conf++; }
	elseif ( in_array( $c['status'], array( 'realizada', 'faltou' ), true ) ) { $h_feito++; }
}

// Histórico ADMINISTRATIVO de um paciente (?hist=ID)
// Só factos administrativos: consultas (datas/médico/estado/chegada) e
// pagamentos/recibos. Nunca diagnósticos nem conteúdo do processo clínico.
$hist_pac = null; $hist_cons = array(); $hist_pgs = array(); $hist_proc = null;
$hist_id  = isset( $_GET['hist'] ) ? (int) $_GET['hist'] : 0;
if ( $hist_id ) {
	$hist_pac = Clinica_Repo::paciente_ficha( $hist_id );
	if ( $hist_pac ) {
		$hist_cons = Clinica_Repo::historico_do_paciente( $hist_id );
		$hist_pgs  = Clinica_Pagamentos::do_paciente( $hist_id );
		$hist_proc = class_exists( 'Clinica_Processo' ) ? Clinica_Processo::do_paciente( $hist_id ) : null;
	}
}

// Transições permitidas à receção. "realizada" NÃO consta: o ato clínico só é
// confirmado pelo médico. A receção confirma a marcação, a presença e a falta.
$rc_acoes = array(
	'pendente'   => array( 'confirmada' => array( 'Confirmar', 'ok' ) ),
	'confirmada' => array( 'faltou' => array( 'Faltou', 'danger' ) ),
	'realizada'  => array(),
	'faltou'     => array(),
	'cancelada'  => array(),
);
$rc_editavel = array( 'pendente', 'confirmada' );
?>
<div class="hp">
<div class="pm-shell pm-shell--rec dz gt">

	<!-- Topo (forma "Dizinv"): marca, pesquisa de pacientes, avisos e perfil -->
	<header class="dz-topo">
		<a class="dz-marca" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="28" height="28" alt="">
			<span>Clínica Vida</span>
		</a>
		<form class="dz-procura" role="search" data-dz-procura>
			<?php echo Clinica_Painel::icone( 'procurar' ); ?>
			<label class="db-sr" for="dz-procura">Procurar paciente</label>
			<input type="search" id="dz-procura" placeholder="Procurar paciente por nome, telefone ou BI" autocomplete="off">
		</form>
		<div class="dz-topo__r">
			<a class="dz-ic" href="#fila" data-go="fila" aria-label="<?php echo esc_attr( sprintf( _n( '%d paciente na fila', '%d pacientes na fila', count( $fila ), 'clinica' ), count( $fila ) ) ); ?>"><?php echo Clinica_Painel::icone( 'sino' ); ?><?php if ( $fila ) : ?><b class="dz-ic__n"><?php echo count( $fila ); ?></b><?php endif; ?></a>
			<a class="dz-ic" href="#mensagens" data-go="mensagens" aria-label="<?php echo esc_attr( sprintf( _n( '%d mensagem por ler', '%d mensagens por ler', (int) $msg_novas, 'clinica' ), (int) $msg_novas ) ); ?>"><?php echo Clinica_Painel::icone( 'mensagem' ); ?><?php if ( $msg_novas ) : ?><b class="dz-ic__n"><?php echo (int) $msg_novas; ?></b><?php endif; ?></a>
			<details class="dz-perfil">
				<summary><img src="<?php echo esc_url( $avatar ); ?>" width="32" height="32" alt=""><span><?php echo esc_html( $primeiro ); ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
				<div class="dz-perfil__menu">
					<a href="#marcar" data-go="marcar">Nova consulta</a>
					<a href="#novo-paciente" data-go="novo-paciente">Novo paciente</a>
					<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Sair</a>
				</div>
			</details>
		</div>
	</header>

	<aside class="pm-side gt-side">
		<a class="gt-marca" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span class="gt-marca__ic"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="24" height="24" alt=""></span>
			<span class="gt-marca__t">Clínica Vida</span>
		</a>
		<div class="gt-perfil">
			<img src="<?php echo esc_url( $avatar ); ?>" width="54" height="54" alt="">
			<span><small>Olá,</small><b><?php echo esc_html( $utilizador->display_name ); ?></b></span>
		</div>
		<nav class="pm-nav">
			<span class="pm-nav__cap">Receção</span>
			<a class="pm-nav__i is-active" href="#" data-go="painel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Painel</span></a>
			<div class="pm-nav__group">
				<a class="pm-nav__i" href="#" data-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Agenda</span><i class="pm-nav__chev" aria-hidden="true"></i></a>
				<div class="pm-nav__sub">
					<a class="pm-nav__subi" href="#" data-go="consultas">Agenda do dia</a>
					<a class="pm-nav__subi" href="#" data-go="fila">Fila de atendimento<?php if ( $fila ) : ?> <b class="rc-badge"><?php echo count( $fila ); ?></b><?php endif; ?></a>
					<a class="pm-nav__subi" href="#" data-go="marcar">Nova consulta</a>
				</div>
			</div>
			<div class="pm-nav__group">
				<a class="pm-nav__i" href="#" data-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Pacientes</span><i class="pm-nav__chev" aria-hidden="true"></i></a>
				<div class="pm-nav__sub">
					<a class="pm-nav__subi" href="#" data-go="pacientes">Todos os pacientes</a>
					<a class="pm-nav__subi" href="#" data-go="novo-paciente">Novo paciente</a>
				</div>
			</div>
			<a class="pm-nav__i" href="#" data-go="historico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg><span>Históricos</span></a>
				<a class="pm-nav__i" href="#" data-go="pagamentos"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg><span>Pagamentos</span></a>
			<a class="pm-nav__i" href="#" data-go="mensagens"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4h16v12H5.17L4 17.17V4z"/></svg><span>Mensagens<?php if ( $msg_novas ) : ?> <b class="rc-badge"><?php echo (int) $msg_novas; ?></b><?php endif; ?></span></a>
		</nav>

		<div class="gt-side__barra">
			<a href="#marcar" data-go="marcar" aria-label="Nova consulta" title="Nova consulta"><?php echo Clinica_Painel::icone( 'agenda' ); ?></a>
			<a href="#pagamentos" data-go="pagamentos" aria-label="Caixa" title="Caixa"><?php echo Clinica_Painel::icone( 'dinheiro' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/contacto/' ) ); ?>" aria-label="Precisa de ajuda?" title="Precisa de ajuda?"><?php echo Clinica_Painel::icone( 'mensagem' ); ?></a>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" aria-label="Sair" title="Sair"><?php echo Clinica_Painel::icone( 'sair' ); ?></a>
		</div>
	</aside>

	<main class="pm-main gt-main">

		<nav class="dz-migalhas" aria-label="Onde estou">
			<span class="dz-migalhas__c"><?php echo Clinica_Painel::icone( 'painel' ); ?><span data-dz-titulo>Painel</span></span>
			<a class="dz-mais" href="#marcar" data-go="marcar" aria-label="Nova consulta"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></a>
		</nav>

		<?php
		// Depois de um pagamento registado, o ecrã de sucesso da Caixa substitui o aviso.
		if ( isset( $_GET['pago'] ) && 'pg_recebido' === ( $_GET['cl'] ?? '' ) ) {
			ob_start();
			clinica_render_flash();
			ob_end_clean();
		} else {
			clinica_render_flash();
		}
		?>

		<!-- Painel
		     Forma "Gentelella" (a mesma da Direção) organizada para o balcão:
		     boas-vindas com a fotografia da receção, faixa de seis números do
		     dia, pedidos do site para ligar, consultas marcadas com os médicos
		     de hoje ao lado, próximos pacientes em tabela e, em baixo, o que
		     falta cobrar, a cobrança e as mensagens do site. -->
		<?php
		$hoje_n    = count( $hoje_lista );
		$pend_val  = (float) $pg_tot['pendente_valor'];
		$pend_n    = (int) $pg_tot['pendente_n'];
		$pago_val  = (float) ( $pg_tot['pago_valor'] ?? 0 );
		$cobrado   = ( $pago_val + $pend_val ) > 0 ? $pago_val / ( $pago_val + $pend_val ) * 100 : null;
		$amanha    = date( 'Y-m-d', strtotime( '+1 day', strtotime( $hoje_ymd ) ) );
		$amanha_n  = count( array_filter( $consultas, static fn( $c ) => substr( $c['inicio'], 0, 10 ) === $amanha && 'cancelada' !== $c['status'] ) );
		$horizonte = isset( $_GET['horizonte'] ) ? (int) $_GET['horizonte'] : 14;
		$horizonte = in_array( $horizonte, array( 7, 14, 30 ), true ) ? $horizonte : 14;
		$hz_rot    = array();
		$hz_longo  = array();
		$hz_val    = array();
		$hz_idx    = array();
		for ( $d = 0; $d < $horizonte; $d++ ) {
			$ts = strtotime( "+{$d} day", strtotime( $hoje_ymd ) );
			$hz_idx[ date( 'Y-m-d', $ts ) ] = $d;
			$hz_rot[]   = 7 === $horizonte ? date_i18n( 'D j', $ts ) : date_i18n( 'j/n', $ts );
			$hz_longo[] = ucfirst( date_i18n( 'l, j \d\e F', $ts ) );
			$hz_val[]   = 0;
		}
		foreach ( $consultas as $c ) {
			$k = substr( $c['inicio'], 0, 10 );
			if ( isset( $hz_idx[ $k ] ) && 'cancelada' !== $c['status'] ) {
				$hz_val[ $hz_idx[ $k ] ]++;
			}
		}
		$hz_url = static fn( int $n ): string => add_query_arg( 'horizonte', $n, remove_query_arg( array( 'horizonte', 'cl' ) ) ) . '#painel';
		$dz_faixa = array(
			array( 'pendente', 'Por confirmar', $por_confirmar, 'consultas', 'rosa' ),
			array( 'fila', 'Na fila', count( $fila ), 'fila', 'areia' ),
			array( 'agenda', 'Consultas hoje', $hoje_n, 'consultas', 'menta' ),
			array( 'financeiro', 'Pagamentos pendentes', $pend_n, 'pagamentos', 'agua' ),
		);
		?>
		<?php
		$hoje_rec = 0.0;
		foreach ( $pg_pagos as $pg ) {
			if ( substr( (string) ( $pg['atualizado_em'] ?? '' ), 0, 10 ) === $hoje_ymd ) {
				$hoje_rec += (float) $pg['valor'];
			}
		}
		$rc_resumo = array();
		if ( $hoje_n ) {
			$rc_resumo[] = sprintf( _n( '%d consulta hoje', '%d consultas hoje', $hoje_n, 'clinica' ), $hoje_n );
		}
		if ( $por_confirmar ) {
			$rc_resumo[] = sprintf( _n( '%d por confirmar', '%d por confirmar', $por_confirmar, 'clinica' ), $por_confirmar );
		}
		if ( $pend_n ) {
			$rc_resumo[] = sprintf( _n( '%d pagamento por cobrar', '%d pagamentos por cobrar', $pend_n, 'clinica' ), $pend_n );
		}
		// [ícone, rótulo, valor, formato, nota, destaque, vista]
		$rc_tiles = array(
			array( 'agenda', 'Consultas hoje', $hoje_n, 'int', 'Na agenda do dia', true, 'consultas' ),
			array( 'pendente', 'Por confirmar', $por_confirmar, 'int', 'Ligar ou confirmar', false, 'consultas' ),
			array( 'fila', 'Na fila', count( $fila ), 'int', 'Já chegaram', false, 'fila' ),
			array( 'consultas', 'Amanhã', $amanha_n, 'int', 'Marcadas para amanhã', false, 'consultas' ),
			array( 'financeiro', 'Por cobrar', $pend_val, 'mil', sprintf( _n( '%d pagamento', '%d pagamentos', $pend_n, 'clinica' ), $pend_n ), false, 'pagamentos' ),
			array( 'dinheiro', 'Recebido hoje', $hoje_rec, 'mil', 'Na caixa hoje', false, 'pagamentos' ),
		);
		$gt_ferr = static function (): string {
			return '<div class="gt-ferr">'
				. '<button type="button" data-gt-recolher aria-expanded="true" aria-label="Recolher painel"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg></button>'
				. '<button type="button" data-gt-fechar aria-label="Fechar painel"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>'
				. '</div>';
		};
		?>
		<section class="gt-painel rc-painel pm-view" data-view="painel">

			<section class="rc-boas" aria-labelledby="rc-boas-t" data-gt-entrada>
				<div class="rc-boas__txt">
					<p class="rc-boas__data"><?php echo esc_html( ucfirst( $data_hoje ) ); ?></p>
					<h1 class="rc-boas__t" id="rc-boas-t"><?php echo esc_html( $saudacao . ', ' . $primeiro ); ?></h1>
					<p class="rc-boas__resumo"><?php echo esc_html( $rc_resumo ? implode( ' · ', $rc_resumo ) : 'Tudo em dia por agora.' ); ?></p>
					<div class="rc-boas__acoes">
						<a class="rc-boas__btn rc-boas__btn--forte" href="#marcar" data-go="marcar"><?php echo Clinica_Painel::icone( 'agenda' ); ?>Marcar consulta</a>
						<a class="rc-boas__btn" href="#pagamentos" data-go="pagamentos"><?php echo Clinica_Painel::icone( 'dinheiro' ); ?>Cobrar pagamento</a>
						<a class="rc-boas__btn" href="#novo-paciente" data-go="novo-paciente"><?php echo Clinica_Painel::icone( 'paciente' ); ?>Novo paciente</a>
					</div>
				</div>
				<img class="rc-boas__img" src="<?php echo esc_url( CLINICA_URI . '/assets/img/inicio/porque.webp' ); ?>" width="1493" height="871" alt="" decoding="async">
			</section>

			<div class="gt-tiles" data-gt-entrada>
				<?php foreach ( $rc_tiles as $t ) :
					$moe = 'mil' === $t[3];
					?>
					<a class="gt-tile rc-tile" href="#<?php echo esc_attr( $t[6] ); ?>" data-go="<?php echo esc_attr( $t[6] ); ?>" data-gt-3d>
						<span class="gt-tile__l"><?php echo Clinica_Painel::icone( $t[0] ); ?><?php echo esc_html( $t[1] ); ?></span>
						<b class="gt-tile__n<?php echo $t[5] ? ' is-destaque' : ''; ?>"<?php echo $moe ? ' title="' . esc_attr( number_format( (float) $t[2], 0, ',', ' ' ) . ' ' . $moeda ) . '"' : ''; ?>><span data-gt-n="<?php echo esc_attr( (string) round( (float) $t[2] ) ); ?>" data-gt-fmt="<?php echo esc_attr( $t[3] ); ?>"><?php echo esc_html( $moe ? Clinica_Grafico::compacto( (float) $t[2] ) : number_format_i18n( (int) $t[2] ) ); ?></span><?php echo $moe ? ' <small>' . esc_html( $moeda ) . '</small>' : ''; ?></b>
						<span class="gt-tile__d"><?php echo esc_html( $t[4] ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( $site_pend ) : ?>
				<section class="gt-x rc-site" aria-labelledby="rc-site-t" data-gt-entrada>
					<div class="gt-x__cab"><h2 class="gt-x__t" id="rc-site-t">Pedidos do site <small>marcados sem conta · ligar a confirmar</small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<ul class="rc-site__lista">
							<?php foreach ( $site_pend as $sp ) : ?>
								<li class="rc-site__i">
									<span class="dz-av" aria-hidden="true"><?php echo esc_html( Clinica_Painel::iniciais( (string) $sp['nome_cliente'] ) ); ?></span>
									<span class="rc-site__quem">
										<b><?php echo esc_html( $sp['nome_cliente'] ); ?></b>
										<small><?php echo esc_html( ucfirst( date_i18n( 'D, j M · H:i', strtotime( $sp['inicio'] ) ) ) . ' · ' . $sp['med_nome'] . ( $sp['esp_nome'] ? ' · ' . $sp['esp_nome'] : '' ) ); ?></small>
										<?php if ( ! empty( $sp['observacoes'] ) ) : ?><em><?php echo esc_html( wp_trim_words( (string) $sp['observacoes'], 14, '…' ) ); ?></em><?php endif; ?>
									</span>
									<span class="rc-site__acoes">
										<?php if ( ! empty( $sp['telefone'] ) ) : ?>
											<a class="rc-site__ligar" href="<?php echo esc_attr( 'tel:' . preg_replace( '/[^\d+]/', '', (string) $sp['telefone'] ) ); ?>"><?php echo Clinica_Painel::icone( 'telemovel' ); ?><?php echo esc_html( $sp['telefone'] ); ?></a>
										<?php endif; ?>
										<form method="post" onsubmit="return confirm('Confirmar a consulta de <?php echo esc_js( $sp['nome_cliente'] ); ?>?');">
											<?php wp_nonce_field( 'rc_checkin', 'rc_nonce' ); ?>
											<input type="hidden" name="rc_action" value="checkin">
											<input type="hidden" name="id" value="<?php echo (int) $sp['id']; ?>">
											<input type="hidden" name="novo_estado" value="confirmada">
											<button type="submit" class="rc-site__ok">Confirmar</button>
										</form>
										<form method="post" onsubmit="return confirm('Cancelar o pedido de <?php echo esc_js( $sp['nome_cliente'] ); ?>? A hora fica livre.');">
											<?php wp_nonce_field( 'rc_canc', 'rc_nonce' ); ?>
											<input type="hidden" name="rc_action" value="cancelar">
											<input type="hidden" name="id" value="<?php echo (int) $sp['id']; ?>">
											<input type="hidden" name="motivo" value="Pedido do site não confirmado">
											<button type="submit" class="rc-site__nao">Cancelar</button>
										</form>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</section>
			<?php endif; ?>

			<section class="gt-x gt-atividade" aria-labelledby="rc-g-curva" data-gt-entrada>
				<div class="gt-x__cab">
					<h2 class="gt-x__t" id="rc-g-curva">Consultas marcadas <small>próximos <?php echo (int) $horizonte; ?> dias</small></h2>
					<label class="dz-select">
						<span class="db-sr">Horizonte do gráfico</span>
						<select data-gf-ir>
							<?php foreach ( array( 7, 14, 30 ) as $n ) : ?>
								<option value="<?php echo esc_url( $hz_url( $n ) ); ?>"<?php selected( $n, $horizonte ); ?>><?php echo esc_html( 'Próximos ' . $n . ' dias' ); ?></option>
							<?php endforeach; ?>
						</select>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
					</label>
				</div>
				<div class="gt-x__corpo gt-atividade__corpo">
					<?php
					echo Clinica_Grafico::linhas(
						array(
							'titulo'  => 'Consultas marcadas por dia',
							'rotulos' => $hz_rot,
							'longos'  => $hz_longo,
							'saltar'  => 30 === $horizonte ? 5 : ( 14 === $horizonte ? 2 : 1 ),
							'suave'   => true,
							'pontos'  => 'valores',
							'final'   => false,
							'series'  => array( array( 'nome' => 'Consultas', 'valores' => $hz_val, 'cor' => 'c1' ) ),
							'formato' => static fn( $v ) => sprintf( _n( '%d consulta', '%d consultas', (int) $v, 'clinica' ), (int) $v ),
							'eixo'    => static fn( $v ) => number_format_i18n( (int) $v ),
							'vazio'   => 'Sem consultas marcadas nos próximos ' . $horizonte . ' dias.',
						)
					);
					?>
					<div class="gt-campanha">
						<p class="gt-campanha__t">Médicos hoje</p>
						<?php if ( $medicos ) : ?>
							<ul class="rc-meds-hoje">
								<?php foreach ( $medicos as $m ) :
									$n  = (int) ( $med_hoje[ $m['nome'] ] ?? 0 );
									$em = ( ( $med_estado[ $m['nome'] ] ?? '' ) === 'Em atendimento' );
									$st = $em ? 'Em consulta' : ( $n > 0 ? 'Com agenda' : 'Livre' );
									?>
									<li>
										<span class="dz-av" aria-hidden="true"><?php echo esc_html( Clinica_Painel::iniciais( $m['nome'] ) ); ?></span>
										<span class="rc-meds-hoje__t"><b><?php echo esc_html( $m['nome'] ); ?></b><small><?php echo esc_html( $m['esp_nome'] . ' · ' . sprintf( _n( '%d consulta', '%d consultas', $n, 'clinica' ), $n ) ); ?></small></span>
										<span class="rc-estado rc-estado--<?php echo $em ? 'on' : ( $n > 0 ? 'agenda' : 'livre' ); ?>"><?php echo esc_html( $st ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="gt-nota">Sem médicos ativos.</p>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<section class="gt-x" aria-labelledby="rc-g-prox" data-gt-entrada>
				<div class="gt-x__cab"><h2 class="gt-x__t" id="rc-g-prox">Próximos pacientes <small>pendentes e confirmadas</small></h2><a class="dz-pilula" href="#consultas" data-go="consultas">Agenda do dia</a><?php echo $gt_ferr(); ?></div>
				<div class="gt-x__corpo">
					<?php if ( $futuras ) : ?>
						<div class="bi-rtbl-wrap">
							<table class="bi-rtbl gt-tabela">
								<thead><tr><th scope="col">Quando</th><th scope="col">Paciente</th><th scope="col">Médico</th><th scope="col">Especialidade</th><th scope="col">Origem</th><th scope="col">Estado</th></tr></thead>
								<tbody>
									<?php foreach ( array_slice( $futuras, 0, 6 ) as $c ) :
										$orig = 'site_rapida' === ( $c['origem'] ?? '' ) ? 'Site, sem conta' : ( 'balcao' === ( $c['origem'] ?? '' ) ? 'Balcão' : 'Portal' );
										?>
										<tr>
											<td class="bi-rtbl__k"><?php echo esc_html( $fmt( $c['inicio'] ) ); ?></td>
											<td><span class="dz-pessoa"><span class="dz-av" aria-hidden="true"><?php echo esc_html( Clinica_Painel::iniciais( $c['pac_nome'] ) ); ?></span><?php echo esc_html( $c['pac_nome'] ); ?></span></td>
											<td><?php echo esc_html( $c['med_nome'] ); ?></td>
											<td><?php echo esc_html( $c['esp_nome'] ); ?></td>
											<td class="rc-orig"><?php echo esc_html( $orig ); ?></td>
											<td><span class="rc-estado rc-estado--<?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $labels[ $c['status'] ] ?? $c['status'] ); ?></span></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php else : ?>
						<p class="gt-nota gt-nota--meio">Sem consultas marcadas. Use "Marcar consulta".</p>
					<?php endif; ?>
				</div>
			</section>

			<div class="gt-linha3" data-gt-entrada>
				<section class="gt-x" aria-labelledby="rc-g-cob">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="rc-g-cob">Por cobrar <small><?php echo esc_html( Clinica_Grafico::moeda( $pend_val ) ); ?></small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<?php if ( $pagamentos ) : ?>
							<ul class="rc-cobrar">
								<?php foreach ( array_slice( $pagamentos, 0, 5 ) as $pg ) :
									$nm = (string) ( $pg['paciente'] ?: 'Paciente' );
									?>
									<li>
										<span class="dz-av" aria-hidden="true"><?php echo esc_html( Clinica_Painel::iniciais( $nm ) ); ?></span>
										<span class="rc-cobrar__t"><b><?php echo esc_html( $nm ); ?></b><small><?php echo esc_html( number_format( (float) $pg['valor'], 0, ',', ' ' ) . ' ' . $moeda ); ?></small></span>
										<a class="rc-cobrar__a" href="<?php echo esc_url( add_query_arg( 'pg', (int) $pg['id'], remove_query_arg( array( 'cl', 'pg' ) ) ) . '#pagamentos' ); ?>">Cobrar</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="gt-nota gt-nota--meio">Nada por cobrar.</p>
						<?php endif; ?>
					</div>
				</section>

				<section class="gt-x" aria-labelledby="rc-g-anel">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="rc-g-anel">Cobrança</h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<div class="gt-metodos gt-metodos--valor">
							<p class="gt-metodos__cab"><span>Total</span><span>Estado</span><span>Quota</span></p>
							<?php
							echo Clinica_Grafico::anel(
								array(
									'titulo'  => 'Cobrança de todos os registos',
									'centro'  => Clinica_Grafico::pct( $cobrado ),
									'sub'     => 'cobrado',
									'legenda' => false,
									'itens'   => array(
										array( 'rotulo' => 'Cobrado', 'valor' => $pago_val, 'cor' => 'c1', 'texto' => Clinica_Grafico::moeda( $pago_val ) ),
										array( 'rotulo' => 'Por cobrar', 'valor' => $pend_val, 'cor' => 'c2', 'texto' => Clinica_Grafico::moeda( $pend_val ) ),
									),
									'vazio'   => 'Sem pagamentos registados.',
								)
							);
							$tot_cob = $pago_val + $pend_val;
							?>
							<ul class="gt-metodos__lista">
								<li><i class="gf-c--c1"></i><span>Cobrado</span><em><?php echo esc_html( Clinica_Grafico::compacto( $pago_val ) ); ?></em><b><?php echo esc_html( Clinica_Grafico::pct( $tot_cob > 0 ? $pago_val / $tot_cob * 100 : null ) ); ?></b></li>
								<li><i class="gf-c--c2"></i><span>Por cobrar</span><em><?php echo esc_html( Clinica_Grafico::compacto( $pend_val ) ); ?></em><b><?php echo esc_html( Clinica_Grafico::pct( $tot_cob > 0 ? $pend_val / $tot_cob * 100 : null ) ); ?></b></li>
							</ul>
						</div>
					</div>
				</section>

				<section class="gt-x" aria-labelledby="rc-g-msg">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="rc-g-msg">Mensagens <small><?php echo esc_html( sprintf( _n( '%d por ler', '%d por ler', (int) $msg_novas, 'clinica' ), (int) $msg_novas ) ); ?></small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<?php if ( $mensagens ) : ?>
							<ul class="gt-linhatempo">
								<?php foreach ( array_slice( $mensagens, 0, 3 ) as $mg ) : ?>
									<li<?php echo empty( $mg['lido'] ) ? ' class="is-nova"' : ''; ?>>
										<b><?php echo esc_html( $mg['assunto'] ?: 'Mensagem do site' ); ?></b>
										<small><?php echo esc_html( $mg['nome'] . ' · há ' . human_time_diff( strtotime( $mg['criado_em'] ), current_time( 'timestamp' ) ) ); ?></small>
									</li>
								<?php endforeach; ?>
							</ul>
							<a class="rc-link rc-msg-ver" href="#mensagens" data-go="mensagens">Abrir a caixa de entrada</a>
						<?php else : ?>
							<p class="gt-nota gt-nota--meio">Sem mensagens do site.</p>
						<?php endif; ?>
					</div>
				</section>
			</div>
		</section>

		<!-- Agenda do dia -->
		<section class="pm-view" data-view="consultas" hidden>
			<!-- Barra de dia compacta (‹ data › + Nova consulta), estilo Smile360 -->
			<div class="rc-agbar">
				<div class="rc-daynav">
					<a class="rc-daynav__b" href="<?php echo esc_url( add_query_arg( 'dia', $dia_prev, remove_query_arg( array( 'cl', 'dia', 'hist' ) ) ) ); ?>" title="Dia anterior" aria-label="Dia anterior">‹</a>
					<div class="rc-daynav__d">
						<b><?php echo esc_html( ucfirst( date_i18n( 'l, j \d\e F', $dia_ts ) ) ); ?></b>
						<span><?php echo $dia_hoje ? 'Hoje' : esc_html( date_i18n( 'Y', $dia_ts ) ); ?> · <?php echo count( $dia_lista ); ?> consulta<?php echo 1 === count( $dia_lista ) ? '' : 's'; ?></span>
					</div>
					<a class="rc-daynav__b" href="<?php echo esc_url( add_query_arg( 'dia', $dia_next, remove_query_arg( array( 'cl', 'dia', 'hist' ) ) ) ); ?>" title="Dia seguinte" aria-label="Dia seguinte">›</a>
					<?php if ( ! $dia_hoje ) : ?>
						<a class="rc-daynav__t" href="<?php echo esc_url( remove_query_arg( array( 'cl', 'dia', 'hist' ) ) ); ?>">Hoje</a>
					<?php endif; ?>
				</div>
				<a class="ge-btn ge-btn--primary rc-agbar__new" href="#" data-go="marcar">+ Nova consulta</a>
			</div>

			<!-- Filtros (fonte única de contagens — sem duplicar o resumo) -->
			<div class="rc-filters" id="rc-filters">
				<button type="button" class="rc-f is-active" data-f="todas">Todas <b><?php echo count( $dia_lista ); ?></b></button>
				<button type="button" class="rc-f" data-f="pendente">Por confirmar <b><?php echo (int) $d_pend; ?></b></button>
				<button type="button" class="rc-f" data-f="confirmada">Confirmadas <b><?php echo (int) $d_conf; ?></b></button>
				<button type="button" class="rc-f" data-f="fila">Na fila <b><?php echo (int) $d_fila; ?></b></button>
				<button type="button" class="rc-f" data-f="feito">Concluídas <b><?php echo (int) $d_feito; ?></b></button>
			</div>

			<div class="pm-panel">
				<?php $lista = $dia_lista; ?>
				<?php if ( $lista ) : ?>
					<div class="rc-agenda__head" id="rc-aghead">
						<button type="button" class="rc-sort" data-sort="hora">Hora<i></i></button>
						<button type="button" class="rc-sort" data-sort="nome">Paciente<i></i></button>
						<button type="button" class="rc-sort" data-sort="esp">Especialidade<i></i></button>
						<button type="button" class="rc-sort" data-sort="med">Médico<i></i></button>
						<button type="button" class="rc-sort" data-sort="che">Chegada<i></i></button>
						<button type="button" class="rc-sort" data-sort="estado">Estado<i></i></button>
						<span class="r">Ações</span>
					</div>
					<div class="rc-agenda" id="rc-list">
						<?php foreach ( $lista as $c ) :
							$acoes     = $rc_acoes[ $c['status'] ] ?? array();
							$terminada = in_array( $c['status'], array( 'realizada', 'cancelada', 'faltou' ), true );
							$na_fila   = ! empty( $c['checkin_em'] ) && ! $terminada;
							$passou    = strtotime( $c['inicio'] ) < $agora && ! $terminada;
						?>
							<article class="rc-ag<?php echo $terminada ? ' is-done' : ''; ?><?php echo $passou ? ' is-late' : ''; ?>"
								data-nome="<?php echo esc_attr( mb_strtolower( $c['pac_nome'] ) ); ?>"
								data-status="<?php echo esc_attr( $c['status'] ); ?>"
								data-fila="<?php echo $na_fila ? '1' : '0'; ?>"
								data-feito="<?php echo $terminada ? '1' : '0'; ?>"
								data-hora="<?php echo esc_attr( $fmt_hora( $c['inicio'] ) ); ?>"
								data-esp="<?php echo esc_attr( mb_strtolower( $c['esp_nome'] ) ); ?>"
								data-med="<?php echo esc_attr( mb_strtolower( $c['med_nome'] ) ); ?>"
								data-che="<?php echo esc_attr( ! empty( $c['checkin_em'] ) ? $fmt_hora( $c['checkin_em'] ) : '~' ); ?>"
								data-estado="<?php echo esc_attr( $c['status'] ); ?>">
								<div class="rc-ag__hora"><b><?php echo esc_html( $fmt_hora( $c['inicio'] ) ); ?></b><span><?php echo (int) $c['duracao_min']; ?> min</span></div>
								<div class="rc-ag__pac"<?php echo ! empty( $c['observacoes'] ) ? ' title="Motivo: ' . esc_attr( $c['observacoes'] ) . '"' : ''; ?>><?php echo esc_html( $c['pac_nome'] ); ?></div>
								<div class="rc-ag__cell rc-ag__esp"><?php echo esc_html( $c['esp_nome'] ); ?></div>
								<div class="rc-ag__cell rc-ag__med"><?php echo esc_html( $c['med_nome'] ); ?></div>
								<div class="rc-ag__cell rc-ag__che">
									<?php if ( ! empty( $c['checkin_em'] ) ) : ?>
										<span class="rc-che--in"><?php echo esc_html( $fmt_hora( $c['checkin_em'] ) ); ?></span>
									<?php elseif ( $passou ) : ?>
										<span class="rc-che--late">Em atraso</span>
									<?php else : ?>
										<span class="rc-che--none">—</span>
									<?php endif; ?>
								</div>
								<div class="rc-ag__cell"><span class="pm-st pm-st--<?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $labels[ $c['status'] ] ?? $c['status'] ); ?></span></div>
								<div class="rc-ag__act">
									<?php if ( empty( $c['checkin_em'] ) && in_array( $c['status'], $rc_editavel, true ) ) : ?>
										<form method="post" class="pm-af">
											<?php wp_nonce_field( 'rc_checkin', 'rc_nonce' ); ?>
											<input type="hidden" name="rc_action" value="presenca">
											<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
											<button type="submit" class="pm-act<?php echo 'confirmada' === $c['status'] ? ' pm-act--ok' : ''; ?>" title="Registar chegada do paciente">Chegou</button>
										</form>
									<?php endif; ?>
									<?php foreach ( $acoes as $novo => $info ) : ?>
										<form method="post" class="pm-af">
											<?php wp_nonce_field( 'rc_checkin', 'rc_nonce' ); ?>
											<input type="hidden" name="rc_action" value="checkin">
											<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
											<input type="hidden" name="novo_estado" value="<?php echo esc_attr( $novo ); ?>">
											<button type="submit" class="pm-act pm-act--<?php echo esc_attr( $info[1] ); ?>"><?php echo esc_html( $info[0] ); ?></button>
										</form>
									<?php endforeach; ?>
									<?php if ( in_array( $c['status'], $rc_editavel, true ) ) : ?>
										<form method="post" class="pm-af" onsubmit="return confirm('Cancelar esta consulta?');">
											<?php wp_nonce_field( 'rc_canc', 'rc_nonce' ); ?>
											<input type="hidden" name="rc_action" value="cancelar">
											<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
											<button type="submit" class="pm-act pm-act--ico pm-act--ico-danger" title="Cancelar consulta" aria-label="Cancelar consulta"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
										</form>
										<?php if ( $medicos ) : ?>
											<details class="rc-reag">
												<summary class="rc-reag__s rc-reag__ico" title="Reagendar" aria-label="Reagendar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v5h-5"/></svg></summary>
												<form method="post" class="rc-reag__f">
													<?php wp_nonce_field( 'rc_reag', 'rc_nonce' ); ?>
													<input type="hidden" name="rc_action" value="reagendar">
													<input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
													<select name="medico_id" required>
														<?php foreach ( $medicos as $m ) : ?><option value="<?php echo (int) $m['id']; ?>" <?php selected( (int) $m['id'], (int) $c['medico_id'] ); ?>><?php echo esc_html( $m['nome'] ); ?></option><?php endforeach; ?>
													</select>
													<input type="datetime-local" name="inicio" required>
													<button type="submit" class="pm-act pm-act--ok">Guardar</button>
												</form>
											</details>
										<?php endif; ?>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
					<p class="pm-empty" id="rc-noresult" hidden>Nenhum paciente encontrado.</p>
				<?php else : ?>
					<p class="pm-empty">Sem consultas em <?php echo esc_html( date_i18n( 'j \d\e F', $dia_ts ) ); ?>.<?php echo $dia_hoje ? ' Vá a <b>Marcar consulta</b> para agendar.' : ' Use as setas para ver outro dia.'; ?></p>
				<?php endif; ?>
			</div>
		</section>

		<!-- Fila de atendimento -->
		<section class="pm-view" data-view="fila" hidden>
			<?php if ( $fila ) :
				$esperas = array();
				foreach ( $fila as $f ) { $esperas[] = max( 0, (int) round( ( $agora - strtotime( $f['checkin_em'] ) ) / 60 ) ); }
				$media = $esperas ? (int) round( array_sum( $esperas ) / count( $esperas ) ) : 0;
			?>
				<!-- Resumo compacto (contagem única) -->
				<div class="rc-flbar">
					<div class="rc-flstat"><b><?php echo count( $fila ); ?></b><span>à espera</span></div>
					<div class="rc-flstat"><b><?php echo $media; ?> min</b><span>espera média</span></div>
					<div class="rc-flstat"><b><?php echo max( $esperas ); ?> min</b><span>há mais tempo</span></div>
				</div>

				<div class="pm-panel">
					<div class="rc-fltable">
						<div class="rc-fltable__head">
							<span>#</span>
							<span>Paciente</span>
							<span>Especialidade</span>
							<span>Médico</span>
							<span>Sala</span>
							<span>Chegada</span>
							<span>Espera</span>
							<span>Alertas</span>
						</div>
						<?php foreach ( $fila as $i => $f ) :
							$espera = $esperas[ $i ];
							$atraso = max( 0, (int) round( ( strtotime( $f['checkin_em'] ) - strtotime( $f['inicio'] ) ) / 60 ) );
						?>
							<div class="rc-flrow<?php echo $espera >= 30 ? ' is-long' : ''; ?>">
								<span class="rc-fl__num"><?php echo $i + 1; ?></span>
								<span class="rc-fl__pac"><?php echo esc_html( $f['pac_nome'] ); ?></span>
								<span class="rc-fl__cell"><?php echo esc_html( $f['esp_nome'] ); ?></span>
								<span class="rc-fl__cell"><?php echo esc_html( $f['med_nome'] ); ?></span>
								<span class="rc-fl__cell rc-fl__sala"><?php echo $f['sala'] ? esc_html( $f['sala'] ) : '—'; ?></span>
								<span class="rc-fl__cell rc-fl__che" title="Marcada para <?php echo esc_attr( $fmt_hora( $f['inicio'] ) ); ?>"><?php echo esc_html( $fmt_hora( $f['checkin_em'] ) ); ?><?php if ( $atraso > 5 ) : ?><small>+<?php echo $atraso; ?> min</small><?php endif; ?></span>
								<span class="rc-fl__wait"><b><?php echo $espera; ?></b> min</span>
								<span class="rc-fl__alerts">
									<?php if ( in_array( $f['status'], array( 'chamada', 'em_atendimento', 'pausada' ), true ) ) : ?>
										<span class="rc-chip rc-chip--estado rc-chip--<?php echo esc_attr( $f['status'] ); ?>"><?php echo esc_html( $labels[ $f['status'] ] ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $f['alergias'] ) ) : ?><span class="rc-chip rc-chip--danger" title="<?php echo esc_attr( $f['alergias'] ); ?>">Alergias</span><?php endif; ?>
									<?php if ( ! empty( $f['pg_pendente'] ) ) : ?><span class="rc-chip rc-chip--warn" title="<?php echo esc_attr( number_format( (float) $f['pg_pendente'], 0, ',', ' ' ) . ' ' . $moeda ); ?>">Pagamento</span><?php endif; ?>
									<?php if ( empty( $f['alergias'] ) && empty( $f['pg_pendente'] ) ) : ?><span class="rc-fl__ok">—</span><?php endif; ?>
								</span>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="rc-flnote">O médico chama o paciente e marca a consulta como <b>realizada</b> no portal dele. Nessa altura sai da fila.</p>
				</div>
			<?php else : ?>
				<div class="ad-head"><div class="ad-head__t"><h2>Fila de atendimento</h2></div></div>
				<div class="pm-panel">
					<p class="pm-empty">Ninguém na fila. Confirme a presença dos pacientes em <b>Agenda do dia</b> à medida que chegam.</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- Marcar consulta -->
		<section class="pm-view rc-nc" data-view="marcar" hidden>
			<?php
			// Imagem de cada especialidade (quando existe no tema) e médicos por especialidade.
			$esp_img = static function ( array $e ): string {
				$f = 'assets/img/inicio/esp-' . sanitize_title( (string) ( $e['slug'] ?: $e['nome'] ) ) . '.webp';
				return file_exists( CLINICA_DIR . '/' . $f ) ? CLINICA_URI . '/' . $f : '';
			};
			$med_por_esp = array();
			foreach ( $med_list as $m ) {
				$med_por_esp[ (int) $m['especialidade_id'] ] = ( $med_por_esp[ (int) $m['especialidade_id'] ] ?? 0 ) + 1;
			}
			?>
			<div class="rc-nc__cab">
				<div>
					<h2 class="rc-nc__t">Nova consulta</h2>
					<p class="rc-nc__s">Paciente, médico e hora. Fica pendente e o pagamento por cobrar.</p>
				</div>
				<ol class="rc-passos" aria-label="Passos da marcação">
					<li data-passo="1"><b>1</b>Paciente</li>
					<li data-passo="2"><b>2</b>Médico</li>
					<li data-passo="3"><b>3</b>Data e hora</li>
				</ol>
			</div>

			<?php if ( ! $medicos ) : ?>
				<div class="dz-cartao"><p class="pm-empty">Ainda não há médicos ativos. Peça à Administração para adicionar médicos.</p></div>
			<?php else : ?>
			<form method="post" class="rc-nc__grelha" id="rc-form" novalidate>
				<?php wp_nonce_field( 'rc_nc', 'rc_nonce' ); ?>
				<input type="hidden" name="rc_action" value="nova_consulta">
				<input type="hidden" name="inicio" id="rc-inicio">
				<input type="hidden" name="reuse_paciente_id" id="rc-reuse">
				<input type="hidden" name="medico_id" id="rc-med">

				<div class="rc-nc__passos">
					<!-- 1 · Paciente -->
					<section class="rc-nc__bloco" data-bloco="1" aria-labelledby="rc-nc-b1">
						<header class="rc-nc__bcab">
							<span class="rc-nc__n" aria-hidden="true">1</span>
							<h3 id="rc-nc-b1">Paciente</h3>
							<div class="rc-seg" role="radiogroup" aria-label="Paciente">
								<button type="button" role="radio" aria-checked="true" data-modo="existente">Já registado</button>
								<button type="button" role="radio" aria-checked="false" data-modo="novo">Pessoa nova</button>
							</div>
						</header>

						<div class="rc-nc__modo" data-modo="existente">
							<div class="rc-busca">
								<?php echo Clinica_Painel::icone( 'procurar' ); ?>
								<label class="db-sr" for="rc-pac-q">Procurar paciente</label>
								<input type="search" id="rc-pac-q" placeholder="Nome, telefone ou BI" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="rc-pac-res" aria-autocomplete="list">
							</div>
							<ul class="rc-pac-res" id="rc-pac-res" role="listbox" aria-label="Pacientes encontrados" hidden></ul>
							<div class="rc-pac-sel" id="rc-pac-sel" hidden>
								<span class="dz-av" data-sel-ini aria-hidden="true"></span>
								<span class="rc-pac-sel__t"><b data-sel-nome></b><small data-sel-tel></small></span>
								<button type="button" class="rc-link" data-sel-trocar>Trocar</button>
							</div>
						</div>

						<div class="rc-nc__modo" data-modo="novo" hidden>
							<div class="rc-campos">
								<label class="rc-c rc-c--largo"><span>Nome completo</span><input type="text" name="nome" id="rc-nome" placeholder="Como está no documento" autocomplete="off"></label>
								<label class="rc-c"><span>Telefone</span><input type="tel" name="telefone" id="rc-tel" placeholder="84 123 4567" inputmode="tel" autocomplete="off"></label>
								<label class="rc-c"><span>Email <small>opcional</small></span><input type="email" name="email" id="rc-email" placeholder="Para ter acesso ao portal" autocomplete="off"></label>
							</div>
							<details class="rc-mais">
								<summary>Mais dados (BI, nascimento, género, morada)</summary>
								<div class="rc-campos">
									<label class="rc-c"><span>Nº do BI</span><input type="text" name="bi" placeholder="110100123456A" autocomplete="off"></label>
									<label class="rc-c"><span>Data de nascimento</span><input type="date" name="data_nascimento" id="rc-nasc" max="<?php echo esc_attr( $hoje_ymd ); ?>"></label>
									<label class="rc-c"><span>Idade</span><input type="text" id="rc-idade" placeholder="Automático" readonly tabindex="-1"></label>
									<label class="rc-c"><span>Género</span><select name="genero"><option value="">Selecione</option><option value="F">Feminino</option><option value="M">Masculino</option></select></label>
									<label class="rc-c rc-c--largo"><span>Morada</span><input type="text" name="endereco" placeholder="Bairro, avenida, número, cidade"></label>
								</div>
							</details>
						</div>

						<label class="rc-c rc-c--largo rc-nc__motivo"><span>Motivo / sintomas <small>opcional</small></span><textarea name="sintomas" rows="2" placeholder="Ex.: febre há 3 dias, dores no peito, seguimento…"></textarea></label>
					</section>

					<!-- 2 · Especialidade e médico -->
					<section class="rc-nc__bloco" data-bloco="2" aria-labelledby="rc-nc-b2">
						<header class="rc-nc__bcab">
							<span class="rc-nc__n" aria-hidden="true">2</span>
							<h3 id="rc-nc-b2">Especialidade e médico</h3>
						</header>
						<div class="rc-esps" role="radiogroup" aria-label="Especialidade">
							<?php foreach ( $esp_list as $e ) :
								$img = $esp_img( $e );
								$n   = (int) ( $med_por_esp[ (int) $e['id'] ] ?? 0 );
								if ( ! $n ) {
									continue;
								}
								?>
								<button type="button" role="radio" aria-checked="false" class="rc-esp" data-esp="<?php echo (int) $e['id']; ?>" data-nome="<?php echo esc_attr( $e['nome'] ); ?>" data-img="<?php echo esc_url( $img ); ?>">
									<?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="" width="400" height="420" loading="lazy" decoding="async"><?php else : ?><span class="rc-esp__ic" aria-hidden="true"><?php echo Clinica_Painel::icone( 'especialidades' ); ?></span><?php endif; ?>
									<span class="rc-esp__t"><b><?php echo esc_html( $e['nome'] ); ?></b><small><?php echo esc_html( sprintf( _n( '%d médico', '%d médicos', $n, 'clinica' ), $n ) ); ?></small></span>
								</button>
							<?php endforeach; ?>
						</div>
						<div class="rc-meds" role="radiogroup" aria-label="Médico" id="rc-meds">
							<p class="rc-nc__dica" data-meds-dica>Escolha a especialidade para ver os médicos.</p>
							<?php foreach ( $med_list as $m ) : ?>
								<button type="button" role="radio" aria-checked="false" class="rc-medc" data-med="<?php echo (int) $m['id']; ?>" data-esp="<?php echo (int) $m['especialidade_id']; ?>" data-nome="<?php echo esc_attr( $m['nome'] ); ?>" hidden>
									<span class="rc-medc__av" aria-hidden="true"><?php echo esc_html( Clinica_Painel::iniciais( (string) $m['nome'] ) ); ?></span>
									<span class="rc-medc__t"><b><?php echo esc_html( $m['nome'] ); ?></b><small><?php echo esc_html( (int) $m['duracao_padrao_min'] . ' min por consulta' ); ?></small></span>
									<span class="rc-medc__ok" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>
								</button>
							<?php endforeach; ?>
						</div>
					</section>

					<!-- 3 · Data e hora -->
					<section class="rc-nc__bloco" data-bloco="3" aria-labelledby="rc-nc-b3">
						<header class="rc-nc__bcab">
							<span class="rc-nc__n" aria-hidden="true">3</span>
							<h3 id="rc-nc-b3">Data e hora <em id="rc-nslots"></em></h3>
							<button type="button" class="rc-link" id="rc-primeira" disabled>Primeira vaga</button>
						</header>
						<div class="rc-days" id="rc-days">
							<?php foreach ( $dias_rapidos as $i => $d ) : ?>
								<button type="button" class="rc-dchip" data-v="<?php echo esc_attr( $d['v'] ); ?>" aria-pressed="false"><b><?php echo esc_html( $d['l'] ); ?></b><span><?php echo esc_html( $d['d'] ); ?></span></button>
							<?php endforeach; ?>
							<label class="rc-dother"><span>Outra data</span><input type="date" id="rc-data" min="<?php echo esc_attr( $hoje_ymd ); ?>" value=""></label>
						</div>
						<div class="rc-slots" id="rc-slots" aria-live="polite"><p class="rc-slots__msg">Escolha o médico para ver as horas livres.</p></div>
					</section>
				</div>

				<aside class="rc-resumo" aria-labelledby="rc-res-t">
					<div class="rc-resumo__foto" id="rc-res-foto" style="background-image:url(<?php echo esc_url( CLINICA_URI . '/assets/img/marcar/hero-1600.webp' ); ?>)"></div>
					<div class="rc-resumo__corpo">
						<h3 class="rc-resumo__t" id="rc-res-t">Resumo</h3>
						<dl class="rc-resumo__dl">
							<div><dt>Paciente</dt><dd id="s-pac">—</dd></div>
							<div><dt>Especialidade</dt><dd id="s-esp">—</dd></div>
							<div><dt>Médico</dt><dd id="s-med">—</dd></div>
							<div><dt>Data</dt><dd id="s-data">—</dd></div>
							<div><dt>Hora</dt><dd id="s-hora">—</dd></div>
						</dl>
						<p class="rc-resumo__valor"><span>Valor da consulta</span><b><?php echo esc_html( number_format( (float) $valor_cons, 0, ',', ' ' ) . ' ' . $moeda ); ?></b></p>
						<label class="rc-resumo__op"><input type="checkbox" name="cobrar_ja" value="1" checked><span>Cobrar logo a seguir</span></label>
						<button class="rc-resumo__go" type="submit" id="rc-go" disabled>Marcar consulta</button>
						<p class="rc-resumo__falta" id="rc-falta">Falta: paciente, médico e hora.</p>
					</div>
				</aside>
			</form>
			<?php endif; ?>
		</section>

		<!-- Pacientes -->
		<!-- Novo paciente (página dedicada) -->
		<section class="pm-view" data-view="novo-paciente" hidden>
			<div class="ad-head">
				<div class="ad-head__t"><h2>Novo paciente</h2><p>Fica com conta no portal e processo clínico aberto automaticamente</p></div>
				<a class="ge-btn ge-btn--ghost" href="#" data-go="pacientes">← Voltar aos pacientes</a>
			</div>
			<form method="post" class="ge-nform">
				<?php wp_nonce_field( 'rc_pac', 'rc_nonce' ); ?>
				<input type="hidden" name="rc_action" value="paciente_criar">
				<div class="ge-nform__body">
					<div class="ge-nform__main">
						<div class="ge-nform__grp">
							<h3 class="ge-nform__gt">Identificação</h3>
							<div class="ge-grid">
								<label class="ge-field ge-field--wide"><span>Nome completo *</span><input type="text" name="nome" required placeholder="Como está no BI"></label>
								<label class="ge-field"><span>Nº do BI</span><input type="text" name="bi" placeholder="110100123456A"></label>
								<label class="ge-field"><span>Género</span>
									<select name="genero"><option value="">—</option><option value="F">Feminino</option><option value="M">Masculino</option></select>
								</label>
								<label class="ge-field"><span>Data de nascimento</span><input type="date" name="data_nascimento" max="<?php echo esc_attr( $hoje_ymd ); ?>"></label>
								<label class="ge-field"><span>Telefone</span><input type="tel" name="telefone" placeholder="+258 84 000 0000"></label>
							</div>
						</div>
						<div class="ge-nform__grp">
							<h3 class="ge-nform__gt">Conta &amp; contacto</h3>
							<div class="ge-grid">
								<label class="ge-field ge-field--wide"><span>Email *</span><input type="email" name="email" required placeholder="Usado para entrar no portal"></label>
								<label class="ge-field ge-field--wide"><span>Morada</span><input type="text" name="endereco" placeholder="Bairro, avenida, nº, cidade"></label>
								<label class="ge-field"><span>Palavra-passe</span><input type="password" name="pwd" minlength="6" placeholder="Gerada se vazia"></label>
							</div>
						</div>
					</div>
					<aside class="ge-nform__side">
						<h3 class="ge-nform__gt">Nota</h3>
						<p class="ge-sidenote">Ao registar, cria-se <b>conta no portal</b> e <b>processo clínico</b> automaticamente. Sem palavra-passe, é gerada uma. Só dados administrativos: o prontuário é do médico.</p>
					</aside>
				</div>
				<div class="ge-nform__foot">
					<a class="ge-btn ge-btn--ghost" href="#" data-go="pacientes">Cancelar</a>
					<button type="submit" class="ge-btn ge-btn--primary">Registar paciente</button>
				</div>
			</form>
		</section>

		<section class="pm-view" data-view="pacientes" hidden>
			<div class="ad-head">
				<div class="ad-head__t"><h2>Pacientes</h2><p>Diretório de pacientes da clínica</p></div>
				<div class="rc-agenda-actions">
					<input type="search" class="pm-search" id="rc-psearch" placeholder="Procurar por nome, telefone ou BI…" aria-label="Procurar paciente">
					<a class="ge-btn ge-btn--primary" href="#" data-go="novo-paciente">+ Novo paciente</a>
				</div>
			</div>
			<?php if ( $pacientes ) : ?>
				<div class="pm-panel">
					<div class="rc-ptbl">
						<div class="rc-ptbl__head">
							<span>Paciente</span><span>Nº do BI</span><span>Contacto</span><span>Idade / género</span><span class="r">Consultas</span><span></span>
						</div>
						<?php foreach ( $pacientes as $p ) :
							$idade  = Clinica_Repo::idade( $p['data_nascimento'] ?? null );
							$gen    = $p['genero'] ?? '';
							$gen_t  = 'F' === $gen ? 'Feminino' : ( 'M' === $gen ? 'Masculino' : '' );
							$pnome  = trim( (string) $p['nome'] );
							$pparts = preg_split( '/\s+/', $pnome ) ?: array( $pnome );
							$pini   = mb_strtoupper( mb_substr( $pparts[0], 0, 1 ) . ( count( $pparts ) > 1 ? mb_substr( end( $pparts ), 0, 1 ) : '' ) );
						?>
							<a class="rc-ptr" data-name="<?php echo esc_attr( mb_strtolower( $p['nome'] . ' ' . ( $p['telefone'] ?? '' ) . ' ' . ( $p['bi'] ?? '' ) ) ); ?>" href="<?php echo esc_url( add_query_arg( 'hist', (int) $p['id'], remove_query_arg( array( 'cl', 'hist' ) ) ) ); ?>">
								<span class="rc-ptr__pac"><span class="rc-pav"><?php echo esc_html( $pini ); ?></span><span class="rc-ptr__n"><?php echo esc_html( $p['nome'] ); ?></span></span>
								<span class="rc-ptr__c rc-ptr__bi"><?php echo $p['bi'] ? esc_html( $p['bi'] ) : ''; ?></span>
								<span class="rc-ptr__c"><?php echo esc_html( $p['telefone'] ?: $p['email'] ); ?></span>
								<span class="rc-ptr__c"><?php echo null !== $idade ? (int) $idade . ' anos' : ''; ?><?php echo ( null !== $idade && $gen_t ) ? ' · ' : ''; ?><?php echo esc_html( $gen_t ); ?></span>
								<span class="rc-ptr__c r rc-ptr__num"><?php echo (int) $p['n_consultas']; ?></span>
								<span class="rc-ptr__go" aria-hidden="true">›</span>
							</a>
						<?php endforeach; ?>
					</div>
					<p class="pm-empty" id="rc-pac-noresult" hidden>Nenhum paciente corresponde à pesquisa.</p>
				</div>
			<?php else : ?>
				<p class="pm-empty">Ainda não há pacientes registados. Use <b>+ Novo paciente</b> para registar o primeiro.</p>
			<?php endif; ?>
		</section>
		<!-- Histórico administrativo -->
		<section class="pm-view" data-view="histadmin" hidden>
			<?php if ( $hist_pac ) :
				$h_idade  = Clinica_Repo::idade( $hist_pac['data_nascimento'] ?? null );
				$h_gen    = 'F' === ( $hist_pac['genero'] ?? '' ) ? 'Feminino' : ( 'M' === ( $hist_pac['genero'] ?? '' ) ? 'Masculino' : '' );
				$h_nasc   = ( ! empty( $hist_pac['data_nascimento'] ) && '0000-00-00' !== $hist_pac['data_nascimento'] ) ? substr( $hist_pac['data_nascimento'], 0, 10 ) : '';
				$n_cons   = count( $hist_cons );
				$ultima   = '';
				foreach ( $hist_cons as $h ) { if ( 'realizada' === $h['status'] ) { $ultima = $h['inicio']; break; } }
				$tot_pago = 0; $tem_pend = false;
				foreach ( $hist_pgs as $pg ) { if ( 'pago' === $pg['estado'] ) { $tot_pago += (float) $pg['valor']; } else { $tem_pend = true; } }
			?>
				<a class="rc-fback" href="<?php echo esc_url( remove_query_arg( array( 'hist', 'cl' ) ) ); ?>">← Voltar aos pacientes</a>

				<div class="rc-fbanner">
					<div class="rc-fbanner__id">
						<h2 class="rc-fbanner__n"><?php echo esc_html( $hist_pac['nome'] ); ?></h2>
						<p class="rc-fbanner__m"><?php echo esc_html( $hist_proc['numero'] ?? 'Sem processo' ); ?><?php if ( null !== $h_idade ) : ?> · <?php echo (int) $h_idade; ?> anos<?php endif; ?><?php if ( $h_gen ) : ?> · <?php echo esc_html( $h_gen ); ?><?php endif; ?><?php if ( $hist_pac['telefone'] ) : ?> · <?php echo esc_html( $hist_pac['telefone'] ); ?><?php endif; ?></p>
					</div>
					<a class="ge-btn ge-btn--primary" href="<?php echo esc_url( add_query_arg( 'ficha', (int) $hist_id, remove_query_arg( array( 'hist', 'cl' ) ) ) ); ?>" target="_blank" rel="noopener">Exportar em PDF</a>
				</div>

				<?php if ( $hist_proc && ! empty( $hist_proc['alergias'] ) ) : ?>
					<div class="rc-alerta"><b>Alergias:</b> <?php echo esc_html( $hist_proc['alergias'] ); ?></div>
				<?php endif; ?>

				<div class="rc-fmetrics">
					<div class="rc-fmetric"><span>Consultas</span><b><?php echo (int) $n_cons; ?></b></div>
					<div class="rc-fmetric"><span>Última visita</span><b><?php echo $ultima ? esc_html( date_i18n( 'd/m/Y', strtotime( $ultima ) ) ) : 'Nenhuma'; ?></b></div>
					<div class="rc-fmetric"><span>Total pago</span><b><?php echo number_format( $tot_pago, 0, ',', ' ' ); ?> <?php echo esc_html( $moeda ); ?></b></div>
					<div class="rc-fmetric"><span>Conta</span><b class="<?php echo $tem_pend ? 'rc-pend' : ''; ?>"><?php echo $tem_pend ? 'Pendente' : 'Em dia'; ?></b></div>
				</div>

				<div class="pm-panel pm-panel--gap">
					<div class="pm-panel__head">
						<h3>Identificação</h3>
						<details class="rc-edit">
							<summary class="ge-btn ge-btn--ghost">Editar dados</summary>
							<form method="post" class="rc-ficha" style="margin-top:16px">
								<?php wp_nonce_field( 'rc_pac_ed', 'rc_nonce' ); ?>
								<input type="hidden" name="rc_action" value="paciente_editar">
								<input type="hidden" name="id" value="<?php echo (int) $hist_id; ?>">
								<div class="rc-ficha__g">
									<label class="ge-field"><span>Nº do BI</span><input type="text" name="bi" value="<?php echo esc_attr( $hist_pac['bi'] ?? '' ); ?>" placeholder="110100123456A"></label>
									<label class="ge-field"><span>Género</span>
										<select name="genero">
											<option value="">Selecione</option>
											<option value="F" <?php selected( ( $hist_pac['genero'] ?? '' ), 'F' ); ?>>Feminino</option>
											<option value="M" <?php selected( ( $hist_pac['genero'] ?? '' ), 'M' ); ?>>Masculino</option>
										</select>
									</label>
									<label class="ge-field"><span>Data de nascimento</span><input type="date" name="data_nascimento" value="<?php echo esc_attr( $h_nasc ); ?>" max="<?php echo esc_attr( $hoje_ymd ); ?>"></label>
									<label class="ge-field"><span>Telefone</span><input type="tel" name="telefone" value="<?php echo esc_attr( $hist_pac['telefone'] ); ?>" placeholder="+258 …"></label>
									<label class="ge-field rc-w2"><span>Morada</span><input type="text" name="endereco" value="<?php echo esc_attr( $hist_pac['endereco'] ?? '' ); ?>" placeholder="Bairro, avenida, número, cidade"></label>
								</div>
								<button class="ge-btn ge-btn--primary" type="submit">Guardar alterações</button>
							</form>
						</details>
					</div>
					<dl class="rc-drow">
						<div><dt>Processo</dt><dd><?php echo esc_html( $hist_proc['numero'] ?? 'Sem processo' ); ?></dd></div>
						<div><dt>Nº do BI</dt><dd><?php echo $hist_pac['bi'] ? esc_html( $hist_pac['bi'] ) : 'Não registado'; ?></dd></div>
						<div><dt>Data de nascimento</dt><dd><?php echo $h_nasc ? esc_html( date_i18n( 'd/m/Y', strtotime( $h_nasc ) ) ) : 'Não registada'; ?></dd></div>
						<div><dt>Idade</dt><dd><?php echo null !== $h_idade ? (int) $h_idade . ' anos' : 'Não registada'; ?></dd></div>
						<div><dt>Género</dt><dd><?php echo $h_gen ? esc_html( $h_gen ) : 'Não indicado'; ?></dd></div>
						<div><dt>Telefone</dt><dd><?php echo $hist_pac['telefone'] ? esc_html( $hist_pac['telefone'] ) : 'Não registado'; ?></dd></div>
						<div class="rc-drow--wide"><dt>Email</dt><dd><?php echo esc_html( $hist_pac['email'] ); ?></dd></div>
						<div class="rc-drow--wide"><dt>Morada</dt><dd><?php echo $hist_pac['endereco'] ? esc_html( $hist_pac['endereco'] ) : 'Não registada'; ?></dd></div>
					</dl>
					<p class="rc-aviso">Só dados administrativos e de segurança. Diagnósticos e prontuário são exclusivos do médico.</p>
				</div>

				<div class="pm-panel pm-panel--gap">
					<div class="pm-panel__head">
						<h3>Marcações</h3>
						<span class="ad-head__meta"><?php echo count( $hist_cons ); ?> consulta<?php echo 1 === count( $hist_cons ) ? '' : 's'; ?></span>
					</div>
					<?php if ( $hist_cons ) : ?>
						<div class="rc-htbl">
							<div class="rc-htbl__head"><span>Data</span><span>Sector · Médico</span><span>Chegada</span><span>Estado</span></div>
							<?php foreach ( $hist_cons as $h ) : ?>
								<div class="rc-htrow">
									<span class="rc-htrow__d"><b><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $h['inicio'] ) ) ); ?></b><small><?php echo esc_html( date_i18n( 'H:i', strtotime( $h['inicio'] ) ) ); ?></small></span>
									<span class="rc-htrow__t"><?php echo esc_html( $h['esp_nome'] ); ?> · <?php echo esc_html( $h['med_nome'] ); ?></span>
									<span class="rc-htrow__c"><?php echo ! empty( $h['checkin_em'] ) ? esc_html( date_i18n( 'H:i', strtotime( $h['checkin_em'] ) ) ) : ''; ?></span>
									<span class="rc-htrow__s"><span class="pm-st pm-st--<?php echo esc_attr( $h['status'] ); ?>"><?php echo esc_html( $labels[ $h['status'] ] ?? $h['status'] ); ?></span></span>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="pm-empty">Sem marcações registadas.</p>
					<?php endif; ?>
				</div>

				<div class="pm-panel pm-panel--gap">
					<div class="pm-panel__head">
						<h3>Pagamentos e recibos</h3>
						<span class="ad-head__meta"><?php echo count( $hist_pgs ); ?> registo(s)</span>
					</div>
					<?php if ( $hist_pgs ) : ?>
						<div class="pm-list">
							<?php foreach ( $hist_pgs as $pg ) : ?>
								<article class="pm-item">
									<div class="pm-item__who"><div>
										<div class="pm-item__name"><?php echo esc_html( Clinica_Pagamentos::numero_recibo( (int) $pg['id'] ) ); ?> · <?php echo number_format( (float) $pg['valor'], 2, ',', '.' ); ?> <?php echo esc_html( $moeda ); ?></div>
										<div class="pm-item__meta"><?php echo esc_html( strtoupper( $pg['metodo'] ) ); ?><?php echo $pg['esp'] ? ' · ' . esc_html( $pg['esp'] ) : ''; ?><?php echo $pg['referencia'] ? ' · Ref. ' . esc_html( $pg['referencia'] ) : ''; ?> · <?php echo esc_html( $fmt_dt( $pg['criado_em'] ) ); ?></div>
									</div></div>
									<div class="pm-item__right">
										<span class="pm-st pm-st--<?php echo 'pago' === $pg['estado'] ? 'realizada' : 'pendente'; ?>"><?php echo esc_html( ucfirst( $pg['estado'] ) ); ?></span>
										<?php if ( 'pago' === $pg['estado'] && current_user_can( 'clinica_emitir_recibo' ) ) : ?>
											<a class="pm-act pm-act--ok" href="<?php echo esc_url( add_query_arg( 'recibo', (int) $pg['id'], remove_query_arg( array( 'cl', 'recibo', 'hist' ) ) ) ); ?>" target="_blank" rel="noopener">Emitir recibo</a>
										<?php endif; ?>
									</div>
								</article>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<p class="pm-empty">Sem pagamentos registados.</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<p class="pm-empty">Paciente não encontrado.</p>
			<?php endif; ?>
		</section>
		<!-- Históricos -->
		<section class="pm-view" data-view="historico" hidden>
			<div class="ad-head"><div class="ad-head__t"><h2>Históricos</h2><p>Arquivo de fichas e de todas as marcações da clínica</p></div></div>

			<div class="rc-paytop">
				<div class="rc-tabs" id="rc-histtabs" role="tablist">
					<button type="button" class="rc-tab is-active" data-tab="fichas">Fichas <b><?php echo count( $pacientes ); ?></b></button>
					<button type="button" class="rc-tab" data-tab="agendas">Agendas <b><?php echo count( $consultas ); ?></b></button>
				</div>
				<input type="search" class="pm-search" id="rc-histsearch" placeholder="Procurar por nome…" aria-label="Procurar no histórico">
			</div>

			<!-- Fichas -->
			<div class="rc-histpanel" data-tab="fichas">
				<div class="pm-panel">
					<?php if ( $pacientes ) : ?>
						<div class="rc-hftbl">
							<div class="rc-hftbl__head"><span>Paciente</span><span>Nº do BI</span><span>Contacto</span><span class="r">Consultas</span><span>Ficha</span></div>
							<?php foreach ( $pacientes as $p ) :
								$pnome  = trim( (string) $p['nome'] );
								$pparts = preg_split( '/\s+/', $pnome ) ?: array( $pnome );
								$pini   = mb_strtoupper( mb_substr( $pparts[0], 0, 1 ) . ( count( $pparts ) > 1 ? mb_substr( end( $pparts ), 0, 1 ) : '' ) );
							?>
								<div class="rc-hfrow" data-name="<?php echo esc_attr( mb_strtolower( $p['nome'] . ' ' . ( $p['telefone'] ?? '' ) . ' ' . ( $p['bi'] ?? '' ) ) ); ?>">
									<span class="rc-ptr__pac"><span class="rc-pav"><?php echo esc_html( $pini ); ?></span><span class="rc-ptr__n"><?php echo esc_html( $p['nome'] ); ?></span></span>
									<span class="rc-ptr__c rc-ptr__bi"><?php echo $p['bi'] ? esc_html( $p['bi'] ) : ''; ?></span>
									<span class="rc-ptr__c"><?php echo esc_html( $p['telefone'] ?: $p['email'] ); ?></span>
									<span class="rc-ptr__c r rc-ptr__num"><?php echo (int) $p['n_consultas']; ?></span>
									<span class="rc-hf__a">
										<a class="pm-act" href="<?php echo esc_url( add_query_arg( 'hist', (int) $p['id'], remove_query_arg( array( 'cl', 'hist', 'dia' ) ) ) ); ?>">Abrir</a>
										<a class="pm-act pm-act--ok" href="<?php echo esc_url( add_query_arg( 'ficha', (int) $p['id'], remove_query_arg( array( 'cl', 'hist', 'dia' ) ) ) ); ?>" target="_blank" rel="noopener">PDF</a>
									</span>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="pm-empty rc-histnores" hidden>Nenhum registo corresponde à pesquisa</p>
					<?php else : ?>
						<p class="pm-empty">Ainda não há fichas de pacientes</p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Agendas -->
			<div class="rc-histpanel" data-tab="agendas" hidden>
				<div class="pm-panel">
					<?php
					$todas_desc = $consultas;
					usort( $todas_desc, static function ( $a, $b ) { return strtotime( $b['inicio'] ) <=> strtotime( $a['inicio'] ); } );
					?>
					<?php if ( $todas_desc ) : ?>
						<div class="rc-hatbl">
							<div class="rc-hatbl__head"><span>Data</span><span>Paciente</span><span>Sector · Médico</span><span>Estado</span></div>
							<?php foreach ( $todas_desc as $c ) : ?>
								<div class="rc-harow" data-name="<?php echo esc_attr( mb_strtolower( ( $c['pac_nome'] ?? '' ) . ' ' . ( $c['med_nome'] ?? '' ) ) ); ?>">
									<span class="rc-ha__d"><b><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $c['inicio'] ) ) ); ?></b><small><?php echo esc_html( date_i18n( 'H:i', strtotime( $c['inicio'] ) ) ); ?></small></span>
									<span class="rc-ha__c rc-ha__n"><?php echo esc_html( $c['pac_nome'] ); ?></span>
									<span class="rc-ha__c"><?php echo esc_html( $c['esp_nome'] ); ?> · <?php echo esc_html( $c['med_nome'] ); ?></span>
									<span class="rc-ha__s"><span class="pm-st pm-st--<?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $labels[ $c['status'] ] ?? $c['status'] ); ?></span></span>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="pm-empty rc-histnores" hidden>Nenhum registo corresponde à pesquisa</p>
					<?php else : ?>
						<p class="pm-empty">Ainda não há marcações no histórico</p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<!-- Pagamentos -->
		<section class="pm-view cx" data-view="pagamentos" hidden>
			<?php
			// Dados da Caixa
			$mp_on    = class_exists( 'Clinica_Pagamentos' ) && Clinica_Pagamentos::gateway()->configurado();
			$fmt_mz   = static fn( $v ) => number_format( (float) $v, 0, ',', ' ' ) . ' ' . $moeda;
			$hoje_rec = 0.0;
			$hoje_rn  = 0;
			foreach ( $pg_pagos as $pg ) {
				if ( substr( (string) ( $pg['atualizado_em'] ?? '' ), 0, 10 ) === $hoje_ymd ) {
					$hoje_rec += (float) $pg['valor'];
					$hoje_rn++;
				}
			}
			// Por cobrar: primeiro quem já foi atendido (consulta passada), do mais antigo.
			$cx_pend = $pagamentos;
			usort( $cx_pend, static fn( $a, $b ) => strtotime( (string) ( $a['inicio'] ?: $a['criado_em'] ) ) <=> strtotime( (string) ( $b['inicio'] ?: $b['criado_em'] ) ) );
			$atraso_n = count( array_filter( $cx_pend, static fn( $p ) => ! empty( $p['inicio'] ) && strtotime( $p['inicio'] ) < $agora ) );
			$metodos_cx = array(
				'numerario' => array( 'Numerário', 'dinheiro' ),
				'mpesa'     => array( 'M-Pesa', 'telemovel' ),
				'emola'     => array( 'e-Mola', 'telemovel' ),
				'cartao'    => array( 'Cartão', 'cartao' ),
			);
			// Acabado de pagar (?pago=ID depois do registo): ecrã de sucesso com o recibo.
			$cx_pago = null;
			if ( isset( $_GET['pago'] ) && 'pg_recebido' === ( $_GET['cl'] ?? '' ) ) {
				foreach ( $pg_pagos as $pg ) {
					if ( (int) $pg['id'] === (int) $_GET['pago'] ) {
						$cx_pago = $pg;
						break;
					}
				}
			}
			$cx_tiles = array(
				array( 'dinheiro', 'Recebido hoje', $hoje_rec, 'mil', sprintf( _n( '%d pagamento', '%d pagamentos', $hoje_rn, 'clinica' ), $hoje_rn ), true ),
				array( 'verificar', 'Pagamentos hoje', $hoje_rn, 'int', 'Registados na caixa', false ),
				array( 'financeiro', 'Por cobrar', (float) $pg_tot['pendente_valor'], 'mil', sprintf( _n( '%d pagamento', '%d pagamentos', (int) $pg_tot['pendente_n'], 'clinica' ), (int) $pg_tot['pendente_n'] ), false ),
				array( 'relogio', 'Em atraso', $atraso_n, 'int', 'Consulta feita, ainda por pagar', false ),
			);
			?>
			<header class="cx-cab">
				<h2 class="cx-cab__t">Pagamentos</h2>
				<p class="cx-cab__s">Cobre as consultas e registe como foram pagas.</p>
			</header>

			<div class="gt-tiles gt-tiles--4 cx-tiles" data-gt-entrada>
				<?php foreach ( $cx_tiles as $t ) :
					$moe = 'mil' === $t[3];
					?>
					<div class="gt-tile" data-gt-3d>
						<span class="gt-tile__l"><?php echo Clinica_Painel::icone( $t[0] ); ?><?php echo esc_html( $t[1] ); ?></span>
						<b class="gt-tile__n<?php echo $t[5] ? ' is-destaque' : ''; ?>"<?php echo $moe ? ' title="' . esc_attr( $fmt_mz( $t[2] ) ) . '"' : ''; ?>><span data-gt-n="<?php echo esc_attr( (string) round( (float) $t[2] ) ); ?>" data-gt-fmt="<?php echo esc_attr( $t[3] ); ?>"><?php echo esc_html( $moe ? Clinica_Grafico::compacto( (float) $t[2] ) : number_format_i18n( (int) $t[2] ) ); ?></span><?php echo $moe ? ' <small>' . esc_html( $moeda ) . '</small>' : ''; ?></b>
						<span class="gt-tile__d"><?php echo esc_html( $t[4] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="cx-grelha">
				<!-- Lista: por cobrar (escolher para cobrar) e pagos (recibos) -->
				<section class="gt-x cx-lista" aria-label="Pagamentos">
					<div class="cx-lista__cab">
						<div class="cx-abas" id="rc-paytabs" role="tablist" aria-label="Estado">
							<button type="button" class="rc-tab cx-aba is-active" role="tab" aria-selected="true" data-tab="cobrar">Por cobrar <span><?php echo (int) $pg_tot['pendente_n']; ?></span></button>
							<button type="button" class="rc-tab cx-aba" role="tab" aria-selected="false" data-tab="pagos">Pagos <span><?php echo (int) $pg_tot['pago_n']; ?></span></button>
							<i class="cx-abas__linha" aria-hidden="true"></i>
						</div>
						<label class="cx-procura">
							<?php echo Clinica_Painel::icone( 'procurar' ); ?>
							<span class="db-sr">Procurar paciente</span>
							<input type="search" id="rc-pgsearch" placeholder="Procurar paciente" autocomplete="off">
						</label>
					</div>

					<div class="rc-paypanel" data-tab="cobrar">
						<?php if ( $cx_pend ) : ?>
							<ul class="cx-itens">
								<?php foreach ( $cx_pend as $pg ) :
									$nome_pg = (string) ( $pg['paciente'] ?: 'Paciente' );
									$feita   = ! empty( $pg['inicio'] ) && strtotime( $pg['inicio'] ) < $agora;
									$det_pg  = trim( ( $pg['esp'] ?: 'Consulta' ) . ( $pg['medico'] ? ' · ' . $pg['medico'] : '' ) );
									$quando  = ! empty( $pg['inicio'] ) ? $fmt( $pg['inicio'] ) : '';
									?>
									<li class="rc-payrow" data-name="<?php echo esc_attr( mb_strtolower( $nome_pg ) ); ?>">
										<button type="button" class="cx-item rc-cx__item" aria-pressed="false" data-pg="<?php echo (int) $pg['id']; ?>" data-nome="<?php echo esc_attr( $nome_pg ); ?>" data-det="<?php echo esc_attr( $det_pg . ( $quando ? ' · ' . $quando : '' ) ); ?>" data-valor="<?php echo esc_attr( (string) (float) $pg['valor'] ); ?>" data-ini="<?php echo esc_attr( Clinica_Painel::iniciais( $nome_pg ) ); ?>">
											<span class="cx-item__av" aria-hidden="true"><?php echo esc_html( Clinica_Painel::iniciais( $nome_pg ) ); ?></span>
											<span class="cx-item__t">
												<b><?php echo esc_html( $nome_pg ); ?></b>
												<small><?php echo esc_html( $det_pg ); ?></small>
												<?php if ( $quando ) : ?><small class="<?php echo $feita ? 'cx-item__feita' : ''; ?>"><?php echo esc_html( ( $feita ? 'Atendido a ' : 'Consulta a ' ) . $quando ); ?></small><?php endif; ?>
											</span>
											<span class="cx-item__v"><?php echo esc_html( $fmt_mz( $pg['valor'] ) ); ?></span>
											<svg class="cx-item__seta" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
										</button>
									</li>
								<?php endforeach; ?>
							</ul>
							<p class="cx-nada rc-pgnores" hidden>Nenhum paciente corresponde à pesquisa.</p>
						<?php else : ?>
							<p class="cx-nada">Nada por cobrar. Os pagamentos das consultas marcadas aparecem aqui.</p>
						<?php endif; ?>
					</div>

					<div class="rc-paypanel" data-tab="pagos" hidden>
						<?php if ( $pg_pagos ) : ?>
							<div class="bi-rtbl-wrap">
								<table class="gt-tabela cx-pagos">
									<thead><tr><th scope="col">Recibo</th><th scope="col">Paciente</th><th scope="col">Método</th><th scope="col" class="r">Valor</th><th scope="col"><span class="db-sr">Ações</span></th></tr></thead>
									<tbody>
										<?php foreach ( array_slice( $pg_pagos, 0, 40 ) as $pg ) :
											$nome_pg = (string) ( $pg['paciente'] ?: 'Paciente' );
											?>
											<tr class="rc-payrow" data-name="<?php echo esc_attr( mb_strtolower( $nome_pg ) ); ?>">
												<td class="bi-rtbl__k"><?php echo esc_html( Clinica_Pagamentos::numero_recibo( (int) $pg['id'] ) ); ?><small class="cx-pagos__q"><?php echo esc_html( ! empty( $pg['atualizado_em'] ) ? $fmt( $pg['atualizado_em'] ) : '' ); ?></small></td>
												<td><?php echo esc_html( $nome_pg ); ?></td>
												<td><span class="cx-met cx-met--<?php echo esc_attr( (string) $pg['metodo'] ); ?>"><?php echo esc_html( Clinica_Pagamentos::METODOS[ $pg['metodo'] ] ?? $pg['metodo'] ); ?></span></td>
												<td class="r"><?php echo esc_html( $fmt_mz( $pg['valor'] ) ); ?></td>
												<td class="r"><?php if ( current_user_can( 'clinica_emitir_recibo' ) ) : ?><a class="rc-link" href="<?php echo esc_url( add_query_arg( 'recibo', (int) $pg['id'], remove_query_arg( array( 'cl', 'recibo', 'pg', 'pago' ) ) ) ); ?>" target="_blank" rel="noopener">Recibo</a><?php endif; ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
							<p class="cx-nada rc-pgnores" hidden>Nenhum paciente corresponde à pesquisa.</p>
						<?php else : ?>
							<p class="cx-nada">Ainda sem pagamentos confirmados.</p>
						<?php endif; ?>
					</div>
				</section>

				<!-- Cobrança: palco animado do método + formulário -->
				<section class="cx-checkout rc-cx__checkout" aria-label="Cobrar" data-moeda="<?php echo esc_attr( $moeda ); ?>" data-mp-push="<?php echo $mp_on ? '1' : '0'; ?>">
					<div class="cx-palco" id="rc-palco" aria-hidden="true">
						<p class="cx-palco__rot" data-palco-rot><?php echo $cx_pago ? 'Recibo pronto' : 'Caixa pronta'; ?></p>

						<div class="cx-cena cx-cena--movel" data-cena="movel"<?php echo $cx_pago ? ' hidden' : ''; ?>>
							<img class="cx-mao" src="<?php echo esc_url( CLINICA_URI . '/assets/img/caixa/mao-telemovel.webp' ); ?>"
								srcset="<?php echo esc_url( CLINICA_URI . '/assets/img/caixa/mao-telemovel-340.webp' ); ?> 340w, <?php echo esc_url( CLINICA_URI . '/assets/img/caixa/mao-telemovel.webp' ); ?> 680w"
								sizes="(max-width: 640px) 320px, 360px" width="680" height="906" alt="" decoding="async">
							<div class="cx-tel" data-tema="idle">
								<span class="cx-tel__notch"></span>
								<div class="cx-tel__estado"><span>09:41</span><span class="cx-tel__sinal"><i></i><i></i><i></i><i></i></span></div>

								<div class="cx-tel__ecra cx-tel__ecra--idle" data-ecra="idle">
									<span class="cx-tel__logo"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="48" height="48" alt=""></span>
									<b>Clínica Vida</b>
									<small>Caixa pronta</small>
								</div>

								<div class="cx-tel__ecra cx-tel__ecra--app" data-ecra="pedido" hidden>
									<div class="cx-tel__notif"><span class="cx-tel__appic" data-tel-ini>M</span><span><b data-tel-marca>M-Pesa</b><small>Pedido de pagamento recebido</small></span></div>
									<div class="cx-tel__topo">
										<b class="cx-tel__marca" data-tel-marca>M-Pesa</b>
										<small>Pagar a comerciante</small>
									</div>
									<div class="cx-tel__cartao">
										<span class="cx-tel__com"><span class="cx-tel__comic"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="24" height="24" alt=""></span><span><b>Clínica Vida</b><small>Comerciante</small></span></span>
										<span class="cx-tel__valor" data-tel-valor>0 MZN</span>
										<span class="cx-tel__de"><small>Do número</small><b data-tel-num>—</b></span>
									</div>
									<div class="cx-tel__pin">
										<small>Introduza o PIN</small>
										<span class="cx-tel__dots"><i></i><i></i><i></i><i></i></span>
										<span class="cx-tel__teclado">
											<?php foreach ( array( '1', '2', '3', '4', '5', '6', '7', '8', '9', '', '0', '⌫' ) as $tk ) : ?>
												<i<?php echo '' !== $tk ? ' data-k="' . esc_attr( $tk ) . '"' : ''; ?>><?php echo esc_html( $tk ); ?></i>
											<?php endforeach; ?>
										</span>
									</div>
								</div>
							</div>
						</div>

						<div class="cx-cena cx-cena--cartao" data-cena="cartao" hidden>
							<span class="cx-ondas"><i></i><i></i><i></i></span>
							<div class="rc-bci cx-bci">
								<span class="rc-bci__petala rc-bci__petala--a"></span>
								<span class="rc-bci__petala rc-bci__petala--b"></span>
								<span class="rc-bci__brilho"></span>
								<span class="rc-bci__marca"><svg viewBox="0 0 40 24"><ellipse cx="11" cy="14" rx="4.2" ry="9" transform="rotate(-38 11 14)"/><ellipse cx="20" cy="10" rx="4.2" ry="9.5"/><ellipse cx="29" cy="14" rx="4.2" ry="9" transform="rotate(38 29 14)"/></svg><b>BCI</b><i>Classic</i></span>
								<span class="rc-bci__chip"><i></i><i></i><i></i></span>
								<svg class="rc-bci__nfc" viewBox="0 0 24 24"><path d="M8 7a7 7 0 0 1 0 10M11.5 5a10 10 0 0 1 0 14M15 3a13 13 0 0 1 0 18"/></svg>
								<span class="rc-bci__num">•••• •••• •••• ••••</span>
								<span class="rc-bci__nome" data-cartao-nome>CLIENTE</span>
								<span class="rc-bci__visa">VISA</span>
							</div>
							<div class="cx-cartao__info">
								<b data-cartao-valor>0 MZN</b>
								<span>Aproxime ou insira o cartão no terminal</span>
							</div>
						</div>

						<div class="cx-cena cx-cena--cash" data-cena="cash" hidden>
							<div class="cx-cash">
								<div class="cx-cash__linha"><span>Total</span><b data-cash-total>—</b></div>
								<div class="cx-cash__linha"><span>Recebido</span><b data-cash-rec>—</b></div>
								<div class="cx-cash__linha cx-cash__linha--troco"><span>Troco</span><b data-cash-troco>—</b></div>
								<p class="cx-cash__t">Dar de troco</p>
								<div class="cx-cash__notas" data-cash-notas></div>
								<p class="cx-cash__dica" data-cash-dica>Escreva o valor recebido para ver as notas a devolver.</p>
							</div>
						</div>

						<div class="cx-cena cx-cena--ok" data-cena="ok"<?php echo $cx_pago ? '' : ' hidden'; ?>>
							<svg class="cx-ok__ic" viewBox="0 0 96 96"><circle class="cx-ok__aro" cx="48" cy="48" r="40" pathLength="100"/><path class="cx-ok__visto" d="M31 49l11 11 23-25" pathLength="100"/></svg>
							<b class="cx-ok__t">Pagamento registado</b>
							<?php if ( $cx_pago ) : ?>
								<span class="cx-ok__v"><?php echo esc_html( $fmt_mz( $cx_pago['valor'] ) ); ?></span>
								<span class="cx-ok__s"><?php echo esc_html( ( $cx_pago['paciente'] ?: 'Paciente' ) . ' · ' . ( Clinica_Pagamentos::METODOS[ $cx_pago['metodo'] ] ?? $cx_pago['metodo'] ) ); ?></span>
							<?php endif; ?>
						</div>
					</div>

					<div class="cx-lado">
						<?php if ( $cx_pago ) : ?>
							<div class="cx-feito" id="rc-cx-feito">
								<p class="cx-feito__t">Recibo <?php echo esc_html( Clinica_Pagamentos::numero_recibo( (int) $cx_pago['id'] ) ); ?></p>
								<dl class="cx-feito__dl">
									<div><dt>Paciente</dt><dd><?php echo esc_html( $cx_pago['paciente'] ?: 'Paciente' ); ?></dd></div>
									<div><dt>Consulta</dt><dd><?php echo esc_html( trim( ( $cx_pago['esp'] ?: 'Consulta' ) . ( $cx_pago['medico'] ? ' · ' . $cx_pago['medico'] : '' ) ) ); ?></dd></div>
									<div><dt>Método</dt><dd><?php echo esc_html( Clinica_Pagamentos::METODOS[ $cx_pago['metodo'] ] ?? $cx_pago['metodo'] ); ?></dd></div>
									<?php if ( ! empty( $cx_pago['referencia'] ) && ( Clinica_Pagamentos::METODOS[ $cx_pago['metodo'] ] ?? '' ) !== $cx_pago['referencia'] ) : ?><div><dt>Referência</dt><dd><?php echo esc_html( $cx_pago['referencia'] ); ?></dd></div><?php endif; ?>
									<div class="is-total"><dt>Valor</dt><dd><?php echo esc_html( $fmt_mz( $cx_pago['valor'] ) ); ?></dd></div>
								</dl>
								<?php if ( current_user_can( 'clinica_emitir_recibo' ) ) : ?>
									<a class="cx-go" href="<?php echo esc_url( add_query_arg( 'recibo', (int) $cx_pago['id'], remove_query_arg( array( 'cl', 'recibo', 'pg', 'pago' ) ) ) ); ?>" target="_blank" rel="noopener">Emitir recibo</a>
								<?php endif; ?>
								<button type="button" class="cx-sec" data-cx-proximo>Cobrar outro pagamento</button>
							</div>
						<?php endif; ?>

						<div class="cx-vazio" id="rc-cx-vazio"<?php echo $cx_pago ? ' hidden' : ''; ?>>
							<b>Escolha um pagamento</b>
							<p>Toque num paciente da lista para cobrar. O método aparece aqui ao lado, passo a passo.</p>
						</div>

						<form method="post" class="cx-form" id="rc-cx-form" hidden>
							<?php wp_nonce_field( 'rc_pg', 'rc_nonce' ); ?>
							<input type="hidden" name="rc_action" id="rc-cx-acao" value="pagamento">
							<input type="hidden" name="id" id="rc-cx-id">
							<input type="hidden" name="metodo" id="rc-cx-metodo" value="numerario">

							<div class="cx-form__quem">
								<span class="cx-item__av" id="rc-cx-ini" aria-hidden="true"></span>
								<span><b id="rc-cx-nome"></b><small id="rc-cx-det"></small></span>
							</div>
							<p class="cx-form__total"><span>Total a pagar</span><b id="rc-cx-total"></b></p>

							<fieldset class="cx-metodos">
								<legend>Método de pagamento</legend>
								<div class="cx-metodos__grelha" role="radiogroup" aria-label="Método de pagamento">
									<i class="cx-metodos__ind" aria-hidden="true"></i>
									<?php $prim = true; foreach ( $metodos_cx as $mk => $mm ) : ?>
										<button type="button" role="radio" class="cx-met-b cx-met-b--<?php echo esc_attr( $mk ); ?>" data-metodo="<?php echo esc_attr( $mk ); ?>" aria-checked="<?php echo $prim ? 'true' : 'false'; ?>"><?php echo Clinica_Painel::icone( $mm[1] ); ?><span><?php echo esc_html( $mm[0] ); ?></span></button>
									<?php $prim = false; endforeach; ?>
								</div>
							</fieldset>

							<div class="cx-extra" data-para="numerario">
								<label class="rc-c"><span>Valor recebido</span><input type="number" id="rc-cx-recebido" min="0" step="1" inputmode="numeric" placeholder="0"></label>
								<div class="cx-notas-r" id="rc-cx-notas"></div>
								<p class="cx-troco"><span>Troco</span><b id="rc-cx-troco">—</b></p>
							</div>
							<div class="cx-extra" data-para="mpesa emola" hidden>
								<label class="rc-c"><span>Número do cliente</span><input type="tel" name="telefone" id="rc-cx-tel" inputmode="tel" placeholder="84 123 4567" autocomplete="off"></label>
								<label class="rc-c" data-ref-movel><span>ID da transação <small>opcional</small></span><input type="text" name="referencia" maxlength="64" placeholder="Ex.: 8FG3K2L9QX" autocomplete="off"></label>
							</div>
							<div class="cx-extra" data-para="cartao" hidden>
								<label class="rc-c"><span>Referência do terminal <small>opcional</small></span><input type="text" name="referencia" maxlength="64" placeholder="Nº de autorização do talão" autocomplete="off"></label>
							</div>

							<button type="submit" class="cx-go" id="rc-cx-go">Confirmar pagamento</button>
							<p class="cx-hint" id="rc-cx-hint"></p>
						</form>
					</div>
				</section>
			</div>
		</section>

		<!-- Espera durante a cobrança M-Pesa (o cliente introduz o PIN no telemóvel). -->
		<div class="rc-mpwait" id="rc-mpwait" hidden role="alertdialog" aria-labelledby="rc-mpwait-t" aria-describedby="rc-mpwait-d">
			<div class="rc-mpwait__card">
				<div class="rc-mpwait__spin" aria-hidden="true"></div>
				<h3 class="rc-mpwait__t" id="rc-mpwait-t">A cobrar via M-Pesa…</h3>
				<p class="rc-mpwait__d" id="rc-mpwait-d">Enviámos o pedido para o telemóvel do cliente. <b>Peça-lhe para confirmar e introduzir o PIN M-Pesa.</b></p>
				<p class="rc-mpwait__num"></p>
				<p class="rc-mpwait__hint">A confirmação pode demorar até 1 minuto. Não feche nem atualize esta página.</p>
			</div>
		</div>

		<!-- A Caixa (escolha, método, troco e palco animado) vive em assets/js/caixa.js. -->

			<!-- Mensagens
			     Caixa de entrada do formulário de contacto do site: lista à
			     esquerda, leitura à direita (no telemóvel, uma por baixo da outra). -->
		<?php
		$iniciais = static function ( string $nome ): string {
			$p = preg_split( '/\s+/', trim( $nome ) ) ?: array();
			$i = mb_substr( $p[0] ?? '', 0, 1 ) . ( count( $p ) > 1 ? mb_substr( (string) end( $p ), 0, 1 ) : '' );
			return mb_strtoupper( $i ?: '?' );
		};
		$quando = static function ( string $iso ) use ( $hoje_ymd ): string {
			return substr( $iso, 0, 10 ) === $hoje_ymd ? date_i18n( 'H:i', strtotime( $iso ) ) : date_i18n( 'd/m', strtotime( $iso ) );
		};
		?>
		<header class="db-secao pm-view" data-view="mensagens" hidden>
			<div>
				<p class="db-secao__eyebrow">Mensagens</p>
				<h2 class="db-secao__titulo">Caixa de entrada</h2>
				<p class="db-secao__sub">Pedidos enviados pelo formulário de contacto do site.</p>
			</div>
			<?php if ( $mensagens ) : ?>
				<nav class="db-periodo" id="rc-msg-filtro" aria-label="Filtrar mensagens">
					<a href="#mensagens" data-filtro="todas" aria-current="true">Todas <b><?php echo count( $mensagens ); ?></b></a>
					<a href="#mensagens" data-filtro="novas">Por ler <b><?php echo (int) $msg_novas; ?></b></a>
				</nav>
			<?php endif; ?>
		</header>

		<section class="db-cartao db-caixa pm-view" data-view="mensagens" hidden aria-label="Mensagens recebidas">
			<?php if ( $mensagens ) : ?>
				<ul class="db-caixa__lista" id="rc-msg-lista">
					<?php foreach ( $mensagens as $i => $msg ) :
						$nova = empty( $msg['lido'] );
						?>
						<li<?php echo $nova ? ' class="is-nova"' : ''; ?>>
							<button type="button" class="db-caixa__item<?php echo 0 === $i ? ' is-ativa' : ''; ?>" data-msg="rc-msg-<?php echo (int) $msg['id']; ?>" aria-controls="rc-msg-<?php echo (int) $msg['id']; ?>" aria-pressed="<?php echo 0 === $i ? 'true' : 'false'; ?>">
								<span class="db-avatar" aria-hidden="true"><?php echo esc_html( $iniciais( $msg['nome'] ) ); ?></span>
								<span class="db-caixa__resumo">
									<span class="db-caixa__linha"><b><?php echo esc_html( $msg['nome'] ); ?></b><time datetime="<?php echo esc_attr( $msg['criado_em'] ); ?>"><?php echo esc_html( $quando( $msg['criado_em'] ) ); ?></time></span>
									<span class="db-caixa__assunto"><?php if ( $nova ) : ?><i class="db-caixa__ponto" aria-label="Por ler"></i><?php endif; ?><?php echo esc_html( $msg['assunto'] ?: 'Sem assunto' ); ?></span>
									<span class="db-caixa__previa"><?php echo esc_html( wp_trim_words( $msg['mensagem'], 14, '…' ) ); ?></span>
								</span>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="db-caixa__leitura">
					<?php foreach ( $mensagens as $i => $msg ) :
						$nova = empty( $msg['lido'] );
						?>
						<article class="db-msg" id="rc-msg-<?php echo (int) $msg['id']; ?>"<?php echo $i ? ' hidden' : ''; ?>>
							<header class="db-msg__cab">
								<span class="db-avatar db-avatar--grande" aria-hidden="true"><?php echo esc_html( $iniciais( $msg['nome'] ) ); ?></span>
								<div class="db-msg__quem">
									<b><?php echo esc_html( $msg['nome'] ); ?></b>
									<span><?php echo esc_html( $msg['email'] . ( $msg['telefone'] ? ' · ' . $msg['telefone'] : '' ) ); ?></span>
								</div>
								<time class="db-msg__data" datetime="<?php echo esc_attr( $msg['criado_em'] ); ?>"><?php echo esc_html( $fmt_dt( $msg['criado_em'] ) ); ?></time>
							</header>
							<h3 class="db-msg__assunto"><?php echo esc_html( $msg['assunto'] ?: 'Sem assunto' ); ?></h3>
							<div class="db-msg__corpo"><?php echo wpautop( esc_html( $msg['mensagem'] ) ); ?></div>
							<div class="db-msg__acoes">
								<a class="db-botao" href="mailto:<?php echo esc_attr( $msg['email'] ); ?>?subject=<?php echo rawurlencode( 'Re: ' . ( $msg['assunto'] ?: 'O seu contacto com a Clínica Vida' ) ); ?>">Responder por email</a>
								<?php if ( $msg['telefone'] ) : ?>
									<a class="db-botao db-botao--claro" href="<?php echo esc_attr( Clinica_Homepage::tel_href( $msg['telefone'] ) ); ?>">Ligar</a>
								<?php endif; ?>
								<?php if ( $nova ) : ?>
									<form method="post" class="db-msg__form">
										<?php wp_nonce_field( 'rc_msg', 'rc_nonce' ); ?>
										<input type="hidden" name="rc_action" value="msg_lida">
										<input type="hidden" name="id" value="<?php echo (int) $msg['id']; ?>">
										<button type="submit" class="db-botao db-botao--claro">Marcar como lida</button>
									</form>
								<?php else : ?>
									<span class="db-msg__lida">Lida</span>
								<?php endif; ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<?php Clinica_Painel::vazio( 'Caixa de entrada vazia', 'As mensagens enviadas pelo formulário de contacto do site aparecem aqui, com o contacto de quem escreveu.', 'mensagem' ); ?>
			<?php endif; ?>
		</section>
		</main>

</div>
</div>
<script>
( function () {
	var side = document.querySelector( '.pm-side' );
	if ( ! side ) { return; }
	var items = side.querySelectorAll( '[data-go]' );
	var clickers = document.querySelectorAll( '[data-go]' );
	var views = document.querySelectorAll( '.pm-view' );
	var groups = side.querySelectorAll( '.pm-nav__group' );
	var shell = document.querySelector( '.pm-shell' );
	function grupoDe( v ) {
		for ( var i = 0; i < groups.length; i++ ) {
			if ( groups[ i ].querySelector( '[data-go="' + v + '"]' ) ) { return groups[ i ]; }
		}
		return null;
	}
	var titulos = {
		painel: 'Painel', consultas: 'Agenda do dia', fila: 'Fila de atendimento', marcar: 'Nova consulta',
		pacientes: 'Pacientes', 'novo-paciente': 'Novo paciente', historico: 'Históricos',
		histadmin: 'Histórico do paciente', pagamentos: 'Pagamentos', mensagens: 'Mensagens'
	};
	var tituloEl = document.querySelector( '[data-dz-titulo]' );
	function show( v ) {
		views.forEach( function ( s ) { s.hidden = ( s.dataset.view !== v ); } );
		document.dispatchEvent( new CustomEvent( 'clinica:vista', { detail: v } ) );
		if ( tituloEl ) { tituloEl.textContent = titulos[ v ] || 'Receção'; }
		// O histórico é um detalhe de Pacientes: mantém esse item ativo.
		var nav = ( 'histadmin' === v ) ? 'pacientes' : v;
		items.forEach( function ( i ) { i.classList.toggle( 'is-active', i.dataset.go === nav ); } );
		var atual = grupoDe( nav );
		groups.forEach( function ( g ) {
			g.classList.toggle( 'is-current', g === atual );
			if ( g === atual ) { g.classList.add( 'is-open' ); }
		} );
		if ( shell ) { shell.classList.toggle( 'is-full', v !== 'painel' ); }
		if ( window.history && history.replaceState ) { history.replaceState( null, '', location.pathname + location.search + '#' + v ); }
		window.scrollTo( 0, 0 );
	}
	function vistaDoHash() {
		var h = location.hash.replace( '#', '' );
		return h && document.querySelector( '.pm-view[data-view="' + h + '"]' ) ? h : '';
	}
	window.addEventListener( 'hashchange', function () { var h = vistaDoHash(); if ( h ) { show( h ); } } );
	clickers.forEach( function ( i ) { i.addEventListener( 'click', function ( e ) { e.preventDefault(); show( i.dataset.go ); } ); } );
	// Cabeçalhos de grupo: clicar abre/fecha o submenu (acordeão, um aberto de cada vez).
	side.querySelectorAll( '[data-toggle]' ).forEach( function ( t ) {
		t.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var g = t.parentNode;
			var abrir = ! g.classList.contains( 'is-open' );
			groups.forEach( function ( gr ) { gr.classList.remove( 'is-open' ); } );
			if ( abrir ) { g.classList.add( 'is-open' ); }
		} );
	} );
	var inicial =<?php echo $hist_pac ? "'histadmin'" : ( isset( $_GET['dia'] ) ? "'consultas'" : "'painel'" ); ?>;
	if ( vistaDoHash() ) { inicial = vistaDoHash(); }
	if ( /[?&]cl=nc_cobrar/.test( location.search ) ) { inicial = 'pagamentos'; }
	else if ( /[?&]cl=rc_pac_(dados|existe)/.test( location.search ) ) { inicial = 'novo-paciente'; }
	else if ( /[?&]cl=rc_pac_ok/.test( location.search ) ) { inicial = 'pacientes'; }
	else if ( /[?&]cl=nc_/.test( location.search ) ) { inicial = 'marcar'; }
	else if ( /[?&]cl=pg_/.test( location.search ) ) { inicial = 'pagamentos'; }
	show( inicial );

	// Pesquisa do topo: abre Pacientes já filtrado pelo que se escreveu.
	( function () {
		var f = document.querySelector( '[data-dz-procura]' ), campo = document.getElementById( 'dz-procura' );
		var alvo = document.getElementById( 'rc-psearch' );
		if ( ! f || ! campo || ! alvo ) { return; }
		function procurar() {
			show( 'pacientes' );
			alvo.value = campo.value;
			alvo.dispatchEvent( new Event( 'input' ) );
		}
		f.addEventListener( 'submit', function ( e ) { e.preventDefault(); procurar(); } );
		campo.addEventListener( 'input', function () { if ( campo.value.trim().length > 1 ) { procurar(); campo.focus(); } } );
	} )();
	var perfil = document.querySelector( '.dz-perfil' );
	if ( perfil ) {
		document.addEventListener( 'click', function ( e ) {
			if ( perfil.open && ( ! perfil.contains( e.target ) || e.target.closest( '.dz-perfil__menu a' ) ) ) { perfil.open = false; }
		} );
	}

	// Pesquisa no diretório de pacientes.
	( function () {
		var ps = document.getElementById( 'rc-psearch' );
		if ( ! ps ) { return; }
		var linhas = document.querySelectorAll( '.rc-ptr' );
		var vazio = document.getElementById( 'rc-pac-noresult' );
		ps.addEventListener( 'input', function () {
			var q = ps.value.trim().toLowerCase(), n = 0;
			linhas.forEach( function ( r ) {
				var ok = ! q || ( r.dataset.name || '' ).indexOf( q ) !== -1;
				r.hidden = ! ok;
				if ( ok ) { n++; }
			} );
			if ( vazio ) { vazio.hidden = 0 !== n; }
		} );
	} )();

	// Agenda do dia: pesquisa + filtros combinados.
	var s = document.getElementById( 'rc-search' ),
		lista = document.getElementById( 'rc-list' ),
		vazio = document.getElementById( 'rc-noresult' ),
		filtros = document.getElementById( 'rc-filters' ),
		filtro = 'todas';

	function aplicar() {
		if ( ! lista ) { return; }
		var q = s ? s.value.trim().toLowerCase() : '', n = 0;
		lista.querySelectorAll( '.rc-ag' ).forEach( function ( it ) {
			var d = it.dataset,
				passaBusca = ! q || ( d.nome || '' ).indexOf( q ) !== -1,
				passaFiltro =
					'todas' === filtro ? true :
					'fila' === filtro ? '1' === d.fila :
					'feito' === filtro ? '1' === d.feito :
					d.status === filtro;
			var ok = passaBusca && passaFiltro;
			it.hidden = ! ok;
			if ( ok ) { n++; }
		} );
		if ( vazio ) { vazio.hidden = 0 !== n; }
	}

	if ( s ) { s.addEventListener( 'input', aplicar ); }

		// Nova consulta: paciente (procura ou pessoa nova), médico e hora
		( function () {
			var form = document.getElementById( 'rc-form' );
			if ( ! form ) { return; }
			var AJAX = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			var PACS = <?php echo wp_json_encode( $pac_ac ); ?>;
			var $ = function ( id ) { return document.getElementById( id ); };
			var reuse = $( 'rc-reuse' ), med = $( 'rc-med' ), inicio = $( 'rc-inicio' ), data = $( 'rc-data' ),
				caixa = $( 'rc-slots' ), nslots = $( 'rc-nslots' ), dias = $( 'rc-days' ), go = $( 'rc-go' ),
				falta = $( 'rc-falta' ), primeira = $( 'rc-primeira' ), q = $( 'rc-pac-q' ), res = $( 'rc-pac-res' ),
				sel = $( 'rc-pac-sel' ), nome = $( 'rc-nome' ), tel = $( 'rc-tel' ), email = $( 'rc-email' ), foto = $( 'rc-res-foto' );
			var modo = 'existente', espSel = null;
			var SEM = [ 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado' ], MES = [ 'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez' ];

			function dataLonga( v ) {
				if ( ! v ) { return ''; }
				var p = v.split( '-' ), d = new Date( +p[0], +p[1] - 1, +p[2] );
				return SEM[ d.getDay() ] + ', ' + d.getDate() + ' ' + MES[ d.getMonth() ];
			}
			function txt( id, v ) { var el = $( id ); if ( el ) { el.textContent = v || '—'; el.classList.toggle( 'is-set', !! v ); } }
			function telOk( v ) { return /^(\+?258|00258)?\s*8[2-7](\s*\d){7}$/.test( ( v || '' ).trim() ); }
			function pessoa() {
				if ( 'existente' === modo ) { return reuse.value ? sel.querySelector( '[data-sel-nome]' ).textContent : ''; }
				var n = nome.value.trim();
				return n.length >= 3 && ( telOk( tel.value ) || /.+@.+\..+/.test( email.value.trim() ) ) ? n : '';
			}
			function resumo() {
				var p = pessoa(), f = [];
				if ( ! p ) { f.push( 'paciente' ); }
				if ( ! med.value ) { f.push( 'médico' ); }
				if ( ! inicio.value ) { f.push( 'hora' ); }
				go.disabled = f.length > 0;
				falta.textContent = f.length ? 'Falta: ' + f.join( ', ' ) + '.' : 'Tudo pronto.';
				falta.classList.toggle( 'is-ok', ! f.length );
				txt( 's-pac', p );
				txt( 's-esp', espSel ? espSel.dataset.nome : '' );
				var mb = form.querySelector( '.rc-medc[aria-checked="true"]' );
				txt( 's-med', mb ? mb.dataset.nome : '' );
				txt( 's-data', inicio.value ? dataLonga( inicio.value.split( ' ' )[0] ) : '' );
				txt( 's-hora', inicio.value ? inicio.value.split( ' ' )[1] : '' );
				// Passos: o atual fica marcado, os completos com visto.
				var feito = [ !! p, !! med.value, !! inicio.value ];
				document.querySelectorAll( '.rc-passos li' ).forEach( function ( li, i ) {
					li.classList.toggle( 'is-feito', feito[ i ] );
					li.classList.toggle( 'is-atual', ! feito[ i ] && ( 0 === i || feito[ i - 1 ] ) );
				} );
			}

			// Paciente: já registado (procura) ou pessoa nova.
			form.querySelectorAll( '.rc-seg [data-modo]' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					modo = b.dataset.modo;
					form.querySelectorAll( '.rc-seg [data-modo]' ).forEach( function ( o ) { o.setAttribute( 'aria-checked', o === b ? 'true' : 'false' ); } );
					form.querySelectorAll( '.rc-nc__modo' ).forEach( function ( m ) { m.hidden = m.dataset.modo !== modo; } );
					if ( 'novo' === modo ) { reuse.value = ''; nome.focus(); } else { q.focus(); }
					resumo();
				} );
			} );
			var ativo = -1;
			function fecharRes() { res.hidden = true; q.setAttribute( 'aria-expanded', 'false' ); ativo = -1; }
			function escolher( p ) {
				reuse.value = p.id;
				sel.querySelector( '[data-sel-ini]' ).textContent = p.i;
				sel.querySelector( '[data-sel-nome]' ).textContent = p.n;
				sel.querySelector( '[data-sel-tel]' ).textContent = p.m || 'Sem contacto registado';
				sel.hidden = false; q.parentNode.hidden = true; fecharRes(); resumo();
			}
			q.addEventListener( 'input', function () {
				var t = q.value.trim().toLowerCase();
				res.innerHTML = '';
				if ( t.length < 2 ) { fecharRes(); return; }
				var achados = PACS.filter( function ( p ) { return p.b.indexOf( t ) !== -1; } ).slice( 0, 6 );
				if ( ! achados.length ) {
					res.innerHTML = '<li class="rc-pac-res__vazio">Sem resultados. <button type="button" class="rc-link" data-novo>Registar pessoa nova</button></li>';
					res.querySelector( '[data-novo]' ).addEventListener( 'click', function () {
						form.querySelector( '.rc-seg [data-modo="novo"]' ).click(); nome.value = q.value.trim(); resumo();
					} );
				}
				achados.forEach( function ( p, i ) {
					var li = document.createElement( 'li' );
					li.setAttribute( 'role', 'option' ); li.id = 'rc-pac-o' + i; li.className = 'rc-pac-res__i';
					li.innerHTML = '<span class="dz-av"></span><span><b></b><small></small></span>';
					li.querySelector( '.dz-av' ).textContent = p.i;
					li.querySelector( 'b' ).textContent = p.n;
					li.querySelector( 'small' ).textContent = p.m || '';
					li.addEventListener( 'mousedown', function ( e ) { e.preventDefault(); escolher( p ); } );
					li._p = p;
					res.appendChild( li );
				} );
				res.hidden = false; q.setAttribute( 'aria-expanded', 'true' );
			} );
			q.addEventListener( 'keydown', function ( e ) {
				var ops = res.querySelectorAll( '[role="option"]' );
				if ( ! ops.length ) { return; }
				if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
					e.preventDefault();
					ativo = ( ativo + ( 'ArrowDown' === e.key ? 1 : ops.length - 1 ) ) % ops.length;
					ops.forEach( function ( o, i ) { o.classList.toggle( 'is-ativo', i === ativo ); o.setAttribute( 'aria-selected', i === ativo ? 'true' : 'false' ); } );
					q.setAttribute( 'aria-activedescendant', ops[ ativo ].id );
				} else if ( 'Enter' === e.key && ativo >= 0 ) {
					e.preventDefault(); escolher( ops[ ativo ]._p );
				} else if ( 'Escape' === e.key ) { fecharRes(); }
			} );
			q.addEventListener( 'blur', function () { setTimeout( fecharRes, 120 ); } );
			sel.querySelector( '[data-sel-trocar]' ).addEventListener( 'click', function () {
				reuse.value = ''; sel.hidden = true; q.parentNode.hidden = false; q.value = ''; q.focus(); resumo();
			} );
			[ nome, tel, email ].forEach( function ( el ) { el.addEventListener( 'input', resumo ); } );
			var nasc = $( 'rc-nasc' ), idade = $( 'rc-idade' );
			if ( nasc && idade ) {
				nasc.addEventListener( 'change', function () {
					if ( ! nasc.value ) { idade.value = ''; return; }
					var d = new Date( nasc.value ), n = new Date(), a = n.getFullYear() - d.getFullYear();
					if ( n.getMonth() < d.getMonth() || ( n.getMonth() === d.getMonth() && n.getDate() < d.getDate() ) ) { a--; }
					idade.value = ( a >= 0 && a < 130 ) ? a + ' anos' : '';
				} );
			}

			// Especialidade -> médicos dessa especialidade (o primeiro fica escolhido).
			var meds = form.querySelectorAll( '.rc-medc' ), dicaMeds = form.querySelector( '[data-meds-dica]' );
			function escolherMed( b ) {
				meds.forEach( function ( o ) { o.setAttribute( 'aria-checked', o === b ? 'true' : 'false' ); } );
				med.value = b ? b.dataset.med : '';
				primeira.disabled = ! med.value;
				carregar();
			}
			form.querySelectorAll( '.rc-esp' ).forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					espSel = b;
					form.querySelectorAll( '.rc-esp' ).forEach( function ( o ) { o.setAttribute( 'aria-checked', o === b ? 'true' : 'false' ); } );
					var prim = null;
					meds.forEach( function ( m ) { var on = m.dataset.esp === b.dataset.esp; m.hidden = ! on; if ( on && ! prim ) { prim = m; } } );
					dicaMeds.hidden = true;
					if ( foto && b.dataset.img ) { foto.style.backgroundImage = 'url(' + b.dataset.img + ')'; }
					escolherMed( prim );
				} );
			} );
			meds.forEach( function ( b ) { b.addEventListener( 'click', function () { escolherMed( b ); } ); } );

			// Dia e horas livres.
			function msg( t ) { caixa.innerHTML = '<p class="rc-slots__msg">' + t + '</p>'; nslots.textContent = ''; }
			function marcarDia( v ) {
				data.value = v;
				dias.querySelectorAll( '.rc-dchip' ).forEach( function ( o ) { var on = o.dataset.v === v; o.classList.toggle( 'is-on', on ); o.setAttribute( 'aria-pressed', on ? 'true' : 'false' ); } );
			}
			function slots( dia ) { return fetch( AJAX + '?action=clinica_slots&medico=' + encodeURIComponent( med.value ) + '&data=' + encodeURIComponent( dia ), { credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ); }
			function grupo( titulo, lista, escolherH ) {
				var w = document.createElement( 'div' ); w.className = 'rc-sg';
				var hh = document.createElement( 'span' ); hh.className = 'rc-sg__h'; hh.textContent = titulo; w.appendChild( hh );
				var g = document.createElement( 'div' ); g.className = 'rc-sg__b';
				lista.forEach( function ( t ) {
					var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'rc-slot'; b.textContent = t; b.setAttribute( 'aria-pressed', 'false' );
					b.addEventListener( 'click', function () {
						caixa.querySelectorAll( '.rc-slot' ).forEach( function ( o ) { o.classList.remove( 'is-on' ); o.setAttribute( 'aria-pressed', 'false' ); } );
						b.classList.add( 'is-on' ); b.setAttribute( 'aria-pressed', 'true' );
						inicio.value = data.value + ' ' + t; resumo();
					} );
					g.appendChild( b );
					if ( escolherH === t ) { setTimeout( function () { b.click(); }, 0 ); }
				} );
				w.appendChild( g ); return w;
			}
			function desenhar( lista, escolherH ) {
				if ( ! lista || ! lista.length ) { msg( 'Sem horas livres neste dia. Experimente outro dia ou "Primeira vaga".' ); return; }
				nslots.textContent = lista.length + ( 1 === lista.length ? ' livre' : ' livres' );
				var manha = lista.filter( function ( h ) { return parseInt( h, 10 ) < 13; } ), tarde = lista.filter( function ( h ) { return parseInt( h, 10 ) >= 13; } );
				caixa.innerHTML = '';
				if ( manha.length ) { caixa.appendChild( grupo( 'Manhã', manha, escolherH ) ); }
				if ( tarde.length ) { caixa.appendChild( grupo( 'Tarde', tarde, escolherH ) ); }
			}
			function carregar() {
				inicio.value = ''; resumo();
				if ( ! med.value ) { msg( 'Escolha o médico para ver as horas livres.' ); return; }
				if ( ! data.value ) { msg( 'Escolha o dia para ver as horas livres.' ); return; }
				msg( 'A procurar horas livres…' );
				slots( data.value ).then( function ( l ) { desenhar( l ); } ).catch( function () { msg( 'Não foi possível obter as horas. Tente de novo.' ); } );
			}
			dias.querySelectorAll( '.rc-dchip' ).forEach( function ( b ) { b.addEventListener( 'click', function () { marcarDia( b.dataset.v ); carregar(); } ); } );
			data.addEventListener( 'change', function () { marcarDia( data.value ); carregar(); } );
			primeira.addEventListener( 'click', function () {
				if ( ! med.value ) { return; }
				primeira.disabled = true; msg( 'A procurar a primeira vaga…' );
				var base = new Date( dias.querySelector( '.rc-dchip' ).dataset.v + 'T00:00:00' ), i = 0;
				( function proximo() {
					if ( i > 20 ) { msg( 'Sem vagas nas próximas três semanas para este médico.' ); primeira.disabled = false; return; }
					var d = new Date( base ); d.setDate( base.getDate() + i ); i++;
					var v = d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
					slots( v ).then( function ( l ) {
						if ( l && l.length ) { marcarDia( v ); desenhar( l, l[0] ); primeira.disabled = false; return; }
						proximo();
					} ).catch( function () { msg( 'Não foi possível procurar. Tente de novo.' ); primeira.disabled = false; } );
				} )();
			} );

			form.addEventListener( 'submit', function ( e ) {
				if ( go.disabled ) { e.preventDefault(); return; }
				if ( ! confirm( 'Marcar a consulta de ' + ( pessoa() || 'paciente' ) + '?' ) ) { e.preventDefault(); }
			} );
			resumo();
		} )();

	// Mensagens: escolher na lista mostra a mensagem ao lado; filtro "Por ler".
	( function () {
		var lista = document.getElementById( 'rc-msg-lista' );
		if ( ! lista ) { return; }
		var itens = lista.querySelectorAll( '.db-caixa__item' );
		itens.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				itens.forEach( function ( o ) {
					var on = o === b;
					o.classList.toggle( 'is-ativa', on );
					o.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
					var art = document.getElementById( o.dataset.msg );
					if ( art ) { art.hidden = ! on; }
				} );
				var alvo = document.getElementById( b.dataset.msg );
				if ( alvo && window.matchMedia && window.matchMedia( '(max-width: 900px)' ).matches ) {
					alvo.scrollIntoView( { block: 'start', behavior: 'smooth' } );
				}
			} );
		} );
		var filtro = document.getElementById( 'rc-msg-filtro' );
		if ( ! filtro ) { return; }
		filtro.querySelectorAll( 'a' ).forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				filtro.querySelectorAll( 'a' ).forEach( function ( o ) {
					if ( o === a ) { o.setAttribute( 'aria-current', 'true' ); } else { o.removeAttribute( 'aria-current' ); }
				} );
				var so = 'novas' === a.dataset.filtro;
				lista.querySelectorAll( 'li' ).forEach( function ( li ) { li.hidden = so && ! li.classList.contains( 'is-nova' ); } );
			} );
		} );
	} )();

	// Históricos: abas (Fichas / Agendas) + pesquisa na aba ativa.
	( function () {
		var tabs = document.getElementById( 'rc-histtabs' );
		if ( ! tabs ) { return; }
		var panels = document.querySelectorAll( '.rc-histpanel' );
		var busca = document.getElementById( 'rc-histsearch' );
		function filtrar() {
			var q = busca ? busca.value.trim().toLowerCase() : '';
			panels.forEach( function ( p ) {
				if ( p.hidden ) { return; }
				var n = 0;
				p.querySelectorAll( '[data-name]' ).forEach( function ( r ) {
					var ok = ! q || ( r.dataset.name || '' ).indexOf( q ) !== -1;
					r.hidden = ! ok; if ( ok ) { n++; }
				} );
				var vazio = p.querySelector( '.rc-histnores' );
				if ( vazio ) { vazio.hidden = ! q || 0 !== n; }
			} );
		}
		function activar( t ) {
			tabs.querySelectorAll( '.rc-tab' ).forEach( function ( b ) { b.classList.toggle( 'is-active', b.dataset.tab === t ); } );
			panels.forEach( function ( p ) { p.hidden = ( p.dataset.tab !== t ); } );
			filtrar();
		}
		tabs.querySelectorAll( '.rc-tab' ).forEach( function ( b ) { b.addEventListener( 'click', function () { activar( b.dataset.tab ); } ); } );
		if ( busca ) { busca.addEventListener( 'input', filtrar ); }
	} )();

	// Pagamentos: abas (Por cobrar / Pagos) + pesquisa por paciente.
	( function () {
		var tabs = document.getElementById( 'rc-paytabs' );
		if ( ! tabs ) { return; }
		var panels = document.querySelectorAll( '.rc-paypanel' );
		var busca = document.getElementById( 'rc-pgsearch' );
		function filtrar() {
			var q = busca ? busca.value.trim().toLowerCase() : '';
			panels.forEach( function ( p ) {
				if ( p.hidden ) { return; }
				var n = 0;
				p.querySelectorAll( '.rc-payrow' ).forEach( function ( r ) {
					var ok = ! q || ( r.dataset.name || '' ).indexOf( q ) !== -1;
					r.hidden = ! ok; if ( ok ) { n++; }
				} );
				var vazio = p.querySelector( '.rc-pgnores' );
				if ( vazio ) { vazio.hidden = ! q || 0 !== n; }
			} );
		}
		function activar( t ) {
			tabs.querySelectorAll( '.rc-tab' ).forEach( function ( b ) { b.classList.toggle( 'is-active', b.dataset.tab === t ); } );
			panels.forEach( function ( p ) { p.hidden = ( p.dataset.tab !== t ); } );
			filtrar();
		}
		tabs.querySelectorAll( '.rc-tab' ).forEach( function ( b ) { b.addEventListener( 'click', function () { activar( b.dataset.tab ); } ); } );
		if ( busca ) { busca.addEventListener( 'input', filtrar ); }
	} )();

	if ( filtros ) {
		filtros.querySelectorAll( '.rc-f' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				filtro = b.dataset.f;
				filtros.querySelectorAll( '.rc-f' ).forEach( function ( o ) { o.classList.toggle( 'is-active', o === b ); } );
				aplicar();
			} );
		} );
	}

	// Ordenação da tabela da agenda (clicar no cabeçalho; setas como na referência).
	( function () {
		var head = document.getElementById( 'rc-aghead' ),
			lst = document.getElementById( 'rc-list' );
		if ( ! head || ! lst ) { return; }
		var col = null, dir = 1;
		head.querySelectorAll( '.rc-sort' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var k = b.dataset.sort;
				if ( col === k ) { dir = -dir; } else { col = k; dir = 1; }
				head.querySelectorAll( '.rc-sort' ).forEach( function ( o ) {
					o.classList.toggle( 'is-asc', o === b && dir === 1 );
					o.classList.toggle( 'is-desc', o === b && dir === -1 );
				} );
				var rows = Array.prototype.slice.call( lst.querySelectorAll( '.rc-ag' ) );
				rows.sort( function ( a, c ) {
					var va = a.dataset[ k ] || '', vc = c.dataset[ k ] || '';
					return va < vc ? -dir : ( va > vc ? dir : 0 );
				} );
				rows.forEach( function ( r ) { lst.appendChild( r ); } );
			} );
		} );
	} )();
} )();
</script>
<?php get_footer();
