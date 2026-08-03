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

// ---- Agenda do dia: dia selecionado (?dia=Y-m-d), navegação e resumo -------
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

// ---- Gráficos do dashboard (só dados de HOJE, já em memória — sem query pesada).
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
$hora_pico = max( 1, max( $por_hora ) );
$hora_atual = (int) date_i18n( 'G', $agora );

// Distribuição por estado (hoje) — donut igual ao do portal médico.
$h_pend = 0; $h_conf = 0; $h_feito = 0;
foreach ( $hoje_lista as $c ) {
	if ( 'pendente' === $c['status'] ) { $h_pend++; }
	elseif ( 'confirmada' === $c['status'] ) { $h_conf++; }
	elseif ( in_array( $c['status'], array( 'realizada', 'faltou' ), true ) ) { $h_feito++; }
}
$rc_seg = array(
	array( 'Por confirmar', $h_pend, '#A9C9DB' ),
	array( 'Confirmadas', $h_conf, '#4E7E9B' ),
	array( 'Concluídas', $h_feito, '#2E4E60' ),
);
$rc_seg_total = array_sum( array_column( $rc_seg, 1 ) );
$rc_acc = 0; $rc_stops = array();
foreach ( $rc_seg as $s ) {
	if ( $s[1] <= 0 ) { continue; }
	$from = $rc_seg_total ? ( $rc_acc / $rc_seg_total * 100 ) : 0;
	$rc_acc += $s[1];
	$to = $rc_seg_total ? ( $rc_acc / $rc_seg_total * 100 ) : 0;
	$rc_stops[] = sprintf( '%s %.2f%% %.2f%%', $s[2], $from, $to );
}
$rc_donut_bg = $rc_seg_total ? ( 'conic-gradient(' . implode( ',', $rc_stops ) . ')' ) : '#E7ECF2';

// Dashboard nunca vazio: sem consultas reais hoje, mostra um exemplo (marcado).
$dash_demo = ( 0 === $rc_seg_total );
if ( $dash_demo ) {
	$por_hora   = array( 8 => 1, 9 => 3, 10 => 4, 11 => 2, 12 => 1, 13 => 0, 14 => 3, 15 => 5, 16 => 4, 17 => 2, 18 => 1, 19 => 0 );
	$hora_pico  = 5;
	$hora_atual = 15;
	$rc_seg = array(
		array( 'Por confirmar', 6, '#A9C9DB' ),
		array( 'Confirmadas', 9, '#4E7E9B' ),
		array( 'Concluídas', 5, '#2E4E60' ),
	);
	$rc_seg_total = 20;
	$rc_acc = 0; $rc_stops = array();
	foreach ( $rc_seg as $s ) {
		$from = $rc_acc / $rc_seg_total * 100;
		$rc_acc += $s[1];
		$to = $rc_acc / $rc_seg_total * 100;
		$rc_stops[] = sprintf( '%s %.2f%% %.2f%%', $s[2], $from, $to );
	}
	$rc_donut_bg = 'conic-gradient(' . implode( ',', $rc_stops ) . ')';
}

// ---- Histórico ADMINISTRATIVO de um paciente (?hist=ID) ----
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
<div class="pm-shell pm-shell--rec">

	<aside class="pm-side">
		<div class="pm-side__brand">
			<span class="pm-side__logo"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo2.png' ); ?>" alt=""></span>
			<span class="pm-side__brandname">Clínica Vida</span>
		</div>

		<div class="pm-side__me">
			<img src="<?php echo esc_url( $avatar ); ?>" alt="">
			<div class="pm-side__meinfo">
				<div class="pm-side__name"><?php echo esc_html( $primeiro ); ?></div>
				<div class="pm-side__role">Receção</div>
			</div>
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

		<div class="rc-help">
			<a class="pm-nav__i rc-help__i" href="<?php echo esc_url( home_url( '/contacto/' ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg><span>Precisa de ajuda?</span></a>
			<a class="pm-nav__i pm-nav__out" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg><span>Sair</span></a>
		</div>
	</aside>

	<main class="pm-main">

		<header class="pm-top">
			<div class="pm-top__l">
				<span class="ad-eyebrow ad-eyebrow--rec">Portal da Receção</span>
				<h1 class="pm-welcome"><?php echo esc_html( $saudacao . ', ' . $primeiro ); ?></h1>
				<p class="pm-date"><?php echo esc_html( ucfirst( $data_hoje ) ); ?></p>
			</div>
		</header>

		<?php clinica_render_flash(); ?>

		<!-- ============================ PAINEL ============================ -->
		<section class="rc-kpis pm-view" data-view="painel">
			<?php
			$rc_cards = array(
				array( 'Consultas hoje', count( $hoje_lista ), 'Ver agenda', 'consultas' ),
				array( 'Por confirmar', $por_confirmar, 'Ver pedidos', 'consultas' ),
				array( 'Na fila', count( $fila ), 'Ver fila', 'fila' ),
				array( 'Pagamentos pendentes', (int) $pg_tot['pendente_n'], 'Ver pagamentos', 'pagamentos' ),
			);
			foreach ( $rc_cards as $k ) : ?>
				<div class="rc-kpi">
					<span class="rc-kpi__l"><?php echo esc_html( $k[0] ); ?></span>
					<strong class="rc-kpi__n"><?php echo (int) $k[1]; ?></strong>
					<a class="rc-kpi__a" href="#" data-go="<?php echo esc_attr( $k[3] ); ?>"><?php echo esc_html( $k[2] ); ?></a>
				</div>
			<?php endforeach; ?>
		</section>

		<!-- Gráficos do dia (sempre visíveis no topo) -->
		<section class="rc-charts pm-view" data-view="painel">
			<div class="rc-card rc-card--chart">
				<div class="rc-card__h"><strong>Consultas por hora</strong><span class="<?php echo $dash_demo ? 'rc-tagex' : ''; ?>"><?php echo $dash_demo ? 'exemplo' : 'hoje'; ?></span></div>
				<?php if ( $por_hora ) : ?>
					<div class="rc-plot">
						<div class="rc-plot__y">
							<span><?php echo (int) $hora_pico; ?></span>
							<span><?php echo (int) ceil( $hora_pico / 2 ); ?></span>
							<span>0</span>
						</div>
						<div class="rc-plot__c">
							<div class="rc-plot__bars">
								<?php foreach ( $por_hora as $h => $n ) :
									$alt = $hora_pico ? round( $n / $hora_pico * 100 ) : 0;
								?>
									<div class="rc-b<?php echo $h === $hora_atual ? ' is-now' : ''; ?>" title="<?php echo (int) $n; ?> às <?php echo (int) $h; ?>h">
										<span style="height:<?php echo (int) $alt; ?>%"></span>
									</div>
								<?php endforeach; ?>
							</div>
							<div class="rc-plot__x">
								<?php foreach ( array_keys( $por_hora ) as $h ) : ?>
									<span><?php echo ( $h % 2 === 0 ) ? (int) $h . 'h' : ''; ?></span>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				<?php else : ?>
					<p class="pm-empty">Sem consultas hoje</p>
				<?php endif; ?>
			</div>

			<div class="rc-card rc-card--chart">
				<div class="rc-card__h"><strong>Distribuição por estado</strong><span class="<?php echo $dash_demo ? 'rc-tagex' : ''; ?>"><?php echo $dash_demo ? 'exemplo' : 'hoje'; ?></span></div>
				<?php if ( $rc_seg_total ) : ?>
					<div class="pm-donut-wrap">
						<div class="pm-donut" style="background:<?php echo esc_attr( $rc_donut_bg ); ?>">
							<div class="pm-donut__c"><span class="rc-donutnum" data-to="<?php echo (int) $rc_seg_total; ?>"><?php echo (int) $rc_seg_total; ?></span><small>consultas</small></div>
						</div>
						<ul class="pm-legend">
							<?php foreach ( $rc_seg as $s ) : ?>
								<li><span class="pm-legend__d" style="background:<?php echo esc_attr( $s[2] ); ?>"></span><?php echo esc_html( $s[0] ); ?><b><?php echo (int) $s[1]; ?></b></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php else : ?>
					<p class="pm-empty">Sem consultas hoje</p>
				<?php endif; ?>
			</div>
		</section>

		<!-- Cards operacionais (em baixo) — acionável | referência -->
		<section class="rc-dashgrid pm-view" data-view="painel">
			<div class="rc-dashcol">
				<div class="rc-card">
					<div class="rc-card__h"><strong>Próximo paciente</strong></div>
					<?php if ( $prox_c && $prox_fic ) : ?>
						<div class="rc-pat__n"><?php echo esc_html( $prox_fic['nome'] ); ?></div>
						<div class="rc-pat__t"><?php echo esc_html( $prox_c['esp_nome'] ); ?> · <?php echo esc_html( $fmt_hora( $prox_c['inicio'] ) ); ?></div>
						<dl class="rc-pat">
							<div><dt>Telefone</dt><dd><?php echo $prox_fic['telefone'] ? esc_html( $prox_fic['telefone'] ) : '<i>Não registado</i>'; ?></dd></div>
							<div><dt>Idade</dt><dd><?php echo null !== $prox_idade ? (int) $prox_idade . ' anos' : '<i>Não registada</i>'; ?></dd></div>
							<div><dt>Médico</dt><dd><?php echo esc_html( $prox_c['med_nome'] ); ?></dd></div>
							<div><dt>Chegada</dt><dd><?php echo ! empty( $prox_c['checkin_em'] ) ? '<b>' . esc_html( $fmt_hora( $prox_c['checkin_em'] ) ) . '</b>' : '<i>A aguardar</i>'; ?></dd></div>
						</dl>
						<a class="rc-card__a" href="<?php echo esc_url( add_query_arg( 'hist', (int) $prox_c['paciente_id'], remove_query_arg( array( 'cl', 'hist' ) ) ) ); ?>">Ver ficha do paciente</a>
					<?php else : ?>
						<p class="pm-empty">Sem pacientes em espera</p>
					<?php endif; ?>
				</div>

			</div>

			<div class="rc-dashcol rc-dashcol--side">
				<div class="rc-card">
					<div class="rc-card__h"><strong>Médicos hoje</strong></div>
					<?php if ( $medicos ) : ?>
						<ul class="rc-docs2">
							<?php foreach ( $medicos as $m ) :
								$n  = (int) ( $med_hoje[ $m['nome'] ] ?? 0 );
								$em = ( ( $med_estado[ $m['nome'] ] ?? '' ) === 'Em atendimento' );
								if ( $em ) { $st = 'Em atendimento'; $cls = 'on'; }
								elseif ( $n > 0 ) { $st = $n . ' hoje'; $cls = 'busy'; }
								else { $st = 'Disponível'; $cls = 'free'; }
							?>
								<li class="rc-doc2">
									<div class="rc-doc2__i">
										<div class="rc-doc2__n"><?php echo esc_html( $m['nome'] ); ?></div>
										<div class="rc-doc2__e"><?php echo esc_html( $m['esp_nome'] ); ?></div>
									</div>
									<span class="rc-doc2__s rc-doc2__s--<?php echo $cls; ?>"><i></i><?php echo esc_html( $st ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="pm-empty">Sem médicos ativos</p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<!-- ========================= AGENDA DO DIA ========================= -->
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

		<!-- ====================== FILA DE ATENDIMENTO ====================== -->
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
					<p class="rc-flnote">O médico chama o paciente e marca a consulta como <b>realizada</b> no portal dele — nessa altura sai da fila.</p>
				</div>
			<?php else : ?>
				<div class="ad-head"><div class="ad-head__t"><h2>Fila de atendimento</h2></div></div>
				<div class="pm-panel">
					<p class="pm-empty">Ninguém na fila. Confirme a presença dos pacientes em <b>Agenda do dia</b> à medida que chegam.</p>
				</div>
			<?php endif; ?>
		</section>

		<!-- ========================= MARCAR CONSULTA ========================= -->
		<section class="pm-view" data-view="marcar" hidden>
			<div class="ad-head"><div class="ad-head__t"><h2>Nova consulta</h2><p>Registe a pessoa e marque a consulta</p></div></div>

			<?php if ( ! $medicos ) : ?>
				<div class="pm-panel"><p class="pm-empty">Ainda não há médicos ativos. Peça à Administração para adicionar médicos.</p></div>
			<?php else : ?>
			<form method="post" class="ge-nform rc-book2" id="rc-form" onsubmit="return confirm('Deseja marcar a consulta?\n\nFica pendente até o médico confirmar. É criado um pagamento por cobrar.');">
				<?php wp_nonce_field( 'rc_nc', 'rc_nonce' ); ?>
				<input type="hidden" name="rc_action" value="nova_consulta">
				<input type="hidden" name="inicio" id="rc-inicio">

				<div class="ge-nform__body">
					<div class="ge-nform__main">

						<div class="ge-nform__grp">
							<h3 class="ge-nform__gt">1 · Dados da pessoa</h3>
							<div class="ge-grid">
								<label class="ge-field ge-field--wide"><span>Nome completo</span><input type="text" name="nome" id="rc-nome" placeholder="Como está no documento" autocomplete="off"></label>
								<label class="ge-field"><span>Telefone</span><input type="tel" name="telefone" id="rc-tel" placeholder="+258 84 000 0000" autocomplete="off"></label>
								<label class="ge-field"><span>Email</span><input type="email" name="email" id="rc-email" placeholder="Para criar o acesso ao portal" autocomplete="off"></label>
								<label class="ge-field"><span>Nº do documento (BI)</span><input type="text" name="bi" id="rc-bi" placeholder="110100123456A" autocomplete="off"></label>
								<label class="ge-field"><span>Data de nascimento</span><input type="date" name="data_nascimento" id="rc-nasc" max="<?php echo esc_attr( $hoje_ymd ); ?>"></label>
								<label class="ge-field"><span>Idade</span><input type="text" id="rc-idade" placeholder="Automático" readonly tabindex="-1"></label>
								<label class="ge-field"><span>Género</span>
									<select name="genero"><option value="">Selecione</option><option value="F">Feminino</option><option value="M">Masculino</option></select>
								</label>
								<label class="ge-field ge-field--wide"><span>Morada</span><input type="text" name="endereco" placeholder="Bairro, avenida, número, cidade"></label>
							</div>
						</div>

						<div class="ge-nform__grp">
							<h3 class="ge-nform__gt">2 · Motivo / sintomas</h3>
							<textarea class="rc-obs rc-ta" name="sintomas" rows="2" placeholder="O que traz a pessoa à clínica? Ex.: febre há 3 dias, dores no peito, seguimento…"></textarea>
						</div>

						<div class="ge-nform__grp">
							<h3 class="ge-nform__gt">3 · Sector e médico</h3>
							<div class="ge-grid">
								<label class="ge-field"><span>Sector / especialidade</span>
									<select id="rc-esp">
										<option value="">Selecione o sector</option>
										<?php foreach ( $esp_list as $e ) : ?><option value="<?php echo (int) $e['id']; ?>"><?php echo esc_html( $e['nome'] ); ?></option><?php endforeach; ?>
									</select>
								</label>
								<label class="ge-field"><span>Médico</span>
									<select name="medico_id" id="rc-med" required>
										<option value="">Selecione o médico</option>
										<?php foreach ( $med_list as $m ) : ?><option value="<?php echo (int) $m['id']; ?>" data-esp="<?php echo (int) $m['especialidade_id']; ?>"><?php echo esc_html( $m['nome'] ); ?></option><?php endforeach; ?>
									</select>
								</label>
							</div>
						</div>

						<div class="ge-nform__grp">
							<h3 class="ge-nform__gt">4 · Data e hora <em id="rc-nslots"></em></h3>
							<div class="rc-days" id="rc-days">
								<?php foreach ( $dias_rapidos as $i => $d ) : ?>
									<button type="button" class="rc-dchip<?php echo 0 === $i ? ' is-on' : ''; ?>" data-v="<?php echo esc_attr( $d['v'] ); ?>"><b><?php echo esc_html( $d['l'] ); ?></b><span><?php echo esc_html( $d['d'] ); ?></span></button>
								<?php endforeach; ?>
								<label class="rc-dother"><span>Outra data</span><input type="date" id="rc-data" min="<?php echo esc_attr( $hoje_ymd ); ?>" value="<?php echo esc_attr( $hoje_ymd ); ?>"></label>
							</div>
							<div class="rc-slots" id="rc-slots"><p class="rc-slots__msg">Escolha o médico para ver as horas livres.</p></div>
						</div>

					</div>
				</div>

				<div class="rc-confirm">
					<dl class="rc-confirm__grid">
						<div><dt>Pessoa</dt><dd id="s-pac"></dd></div>
						<div><dt>Sector</dt><dd id="s-esp"></dd></div>
						<div><dt>Médico</dt><dd id="s-med"></dd></div>
						<div><dt>Data</dt><dd id="s-data"></dd></div>
						<div><dt>Hora</dt><dd id="s-hora"></dd></div>
						<div><dt>Valor</dt><dd class="is-set"><?php echo number_format( (float) $valor_cons, 2, ',', '.' ); ?> <?php echo esc_html( $moeda ); ?></dd></div>
					</dl>
					<div class="rc-confirm__foot">
						<button class="ge-btn ge-btn--primary rc-confirm__go" type="submit" id="rc-go" disabled>Marcar consulta</button>
					</div>
				</div>
			</form>
			<?php endif; ?>
		</section>

		<!-- ============================ PACIENTES ============================ -->
		<!-- ==================== NOVO PACIENTE (página dedicada) ==================== -->
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
								<label class="ge-field ge-field--wide"><span>Morada</span><input type="text" name="endereco" placeholder="Bairro, avenida, nº — cidade"></label>
								<label class="ge-field"><span>Palavra-passe</span><input type="password" name="pwd" minlength="6" placeholder="Gerada se vazia"></label>
							</div>
						</div>
					</div>
					<aside class="ge-nform__side">
						<h3 class="ge-nform__gt">Nota</h3>
						<p class="ge-sidenote">Ao registar, cria-se <b>conta no portal</b> e <b>processo clínico</b> automaticamente. Sem palavra-passe, é gerada uma. Só dados administrativos — o prontuário é do médico.</p>
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
		<!-- ==================== HISTÓRICO ADMINISTRATIVO ==================== -->
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
		<!-- ============================ HISTÓRICOS ============================ -->
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

		<!-- =========================== PAGAMENTOS =========================== -->
		<section class="pm-view" data-view="pagamentos" hidden>
			<div class="ad-head"><div class="ad-head__t"><h2>Pagamentos</h2><p>Cobre as consultas por M-Pesa ou regista pagamentos em dinheiro — e emite a fatura.</p></div></div>

			<?php $esperado = (float) $pg_tot['pendente_valor'] + (float) $pg_tot['pago_valor']; ?>
			<div class="rc-fmetrics">
				<div class="rc-fmetric"><span>Por cobrar</span><b class="<?php echo (int) $pg_tot['pendente_n'] ? 'rc-pend' : ''; ?>"><?php echo (int) $pg_tot['pendente_n']; ?></b></div>
				<div class="rc-fmetric"><span>Valor pendente</span><b class="<?php echo (int) $pg_tot['pendente_n'] ? 'rc-pend' : ''; ?>"><?php echo number_format( (float) $pg_tot['pendente_valor'], 0, ',', ' ' ); ?> <?php echo esc_html( $moeda ); ?></b></div>
				<div class="rc-fmetric"><span>Recebido</span><b><?php echo number_format( (float) $pg_tot['pago_valor'], 0, ',', ' ' ); ?> <?php echo esc_html( $moeda ); ?></b></div>
				<div class="rc-fmetric"><span>Esperado</span><b><?php echo number_format( $esperado, 0, ',', ' ' ); ?> <?php echo esc_html( $moeda ); ?></b></div>
			</div>

			<?php if ( class_exists( 'Clinica_Pagamentos' ) && Clinica_Pagamentos::gateway()->configurado() ) : ?>
				<p class="rc-payhint"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg><span>Escreva o número do cliente e clique <b>Cobrar</b> — ele recebe o pedido de PIN no telemóvel e a confirmação é automática. Se pagar em numerário, use <b>Recebido em dinheiro</b>.</span></p>
			<?php endif; ?>

			<div class="rc-paytop">
				<div class="rc-tabs" id="rc-paytabs" role="tablist">
					<button type="button" class="rc-tab is-active" data-tab="cobrar">Por cobrar <b><?php echo (int) $pg_tot['pendente_n']; ?></b></button>
					<button type="button" class="rc-tab" data-tab="pagos">Pagos <b><?php echo (int) $pg_tot['pago_n']; ?></b></button>
				</div>
				<input type="search" class="pm-search" id="rc-pgsearch" placeholder="Procurar paciente…" aria-label="Procurar paciente">
			</div>

			<!-- Por cobrar -->
			<div class="rc-paypanel" data-tab="cobrar">
				<div class="pm-panel">
					<?php if ( $pagamentos ) : ?>
						<div class="rc-paytbl">
							<div class="rc-paytbl__head"><span>Paciente</span><span>Consulta</span><span class="r">Valor</span><span>Cobrança</span></div>
							<?php
							$mp_on = class_exists( 'Clinica_Pagamentos' ) && Clinica_Pagamentos::gateway()->configurado();
							foreach ( $pagamentos as $pg ) :
								$pgid = (int) $pg['id'];
							?>
								<div class="rc-payrow" data-name="<?php echo esc_attr( mb_strtolower( (string) ( $pg['paciente'] ?? '' ) ) ); ?>">
									<span class="rc-pay__n"><?php echo esc_html( $pg['paciente'] ?: 'Paciente' ); ?></span>
									<span class="rc-pay__c"><?php echo esc_html( $pg['esp'] ?: 'Consulta' ); ?><?php echo $pg['medico'] ? ' · ' . esc_html( $pg['medico'] ) : ''; ?><?php echo $pg['inicio'] ? ' · ' . esc_html( $fmt( $pg['inicio'] ) ) : ''; ?></span>
									<span class="rc-pay__v r"><?php echo number_format( (float) $pg['valor'], 2, ',', '.' ); ?> <small><?php echo esc_html( $moeda ); ?></small></span>
									<div class="rc-payact">
										<?php if ( $mp_on ) : ?>
											<form method="post" class="rc-payform rc-payform--mp" data-mpvalor="<?php echo esc_attr( number_format( (float) $pg['valor'], 2, ',', '.' ) . ' ' . $moeda ); ?>" onsubmit="return clMpesaCobrar(this);">
												<?php wp_nonce_field( 'rc_pg', 'rc_nonce' ); ?>
												<input type="hidden" name="rc_action" value="pg_cobrar">
												<input type="hidden" name="id" value="<?php echo $pgid; ?>">
												<input type="tel" name="telefone" inputmode="numeric" placeholder="Nº M-Pesa do cliente" class="rc-ref" autocomplete="off" required>
												<button type="submit" class="pm-act pm-act--mp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2.4"/><path d="M11 18h2"/></svg>Cobrar</button>
											</form>
										<?php endif; ?>
										<form method="post" class="rc-payform rc-payform--cash" onsubmit="return confirm('Confirmar que este pagamento foi recebido em dinheiro?');">
											<?php wp_nonce_field( 'rc_pg', 'rc_nonce' ); ?>
											<input type="hidden" name="rc_action" value="pagamento">
											<input type="hidden" name="id" value="<?php echo $pgid; ?>">
											<input type="hidden" name="referencia" value="Dinheiro">
											<button type="submit" class="<?php echo $mp_on ? 'rc-cashbtn' : 'pm-act pm-act--ok'; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg><?php echo $mp_on ? 'Recebido em dinheiro' : 'Confirmar recebido em dinheiro'; ?></button>
										</form>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="pm-empty rc-pgnores" hidden>Nenhum paciente corresponde à pesquisa.</p>
					<?php else : ?>
						<p class="pm-empty">Não há pagamentos por cobrar.</p>
					<?php endif; ?>
				</div>
			</div>

			<!-- Pagos -->
			<div class="rc-paypanel" data-tab="pagos" hidden>
				<div class="pm-panel">
					<?php if ( $pg_pagos ) : ?>
						<div class="rc-paytbl rc-paytbl--paid">
							<div class="rc-paytbl__head"><span>Recibo</span><span>Paciente</span><span>Detalhe</span><span class="r">Valor</span><span></span></div>
							<?php foreach ( $pg_pagos as $pg ) : ?>
								<div class="rc-payrow rc-payrow--paid" data-name="<?php echo esc_attr( mb_strtolower( (string) ( $pg['paciente'] ?? '' ) ) ); ?>">
									<span class="rc-pay__r"><?php echo esc_html( Clinica_Pagamentos::numero_recibo( (int) $pg['id'] ) ); ?></span>
									<span class="rc-pay__n"><?php echo esc_html( $pg['paciente'] ?: 'Paciente' ); ?></span>
									<span class="rc-pay__c"><?php echo $pg['esp'] ? esc_html( $pg['esp'] ) : 'Consulta'; ?><?php echo $pg['referencia'] ? ' · ' . esc_html( $pg['referencia'] ) : ''; ?></span>
									<span class="rc-pay__v r"><?php echo number_format( (float) $pg['valor'], 2, ',', '.' ); ?> <small><?php echo esc_html( $moeda ); ?></small></span>
									<span class="rc-pay__a">
										<?php if ( current_user_can( 'clinica_emitir_recibo' ) ) : ?>
											<a class="pm-act pm-act--ok" href="<?php echo esc_url( add_query_arg( 'recibo', (int) $pg['id'], remove_query_arg( array( 'cl', 'recibo' ) ) ) ); ?>" target="_blank" rel="noopener">Emitir fatura</a>
										<?php endif; ?>
									</span>
								</div>
							<?php endforeach; ?>
						</div>
						<p class="pm-empty rc-pgnores" hidden>Nenhum paciente corresponde à pesquisa.</p>
					<?php else : ?>
						<p class="pm-empty">Ainda não há pagamentos confirmados.</p>
					<?php endif; ?>
				</div>
			</div>
		</section>

		<!-- Modal de espera durante a cobrança M-Pesa (sistema real). -->
			<div class="rc-mpwait" id="rc-mpwait" hidden role="alertdialog" aria-labelledby="rc-mpwait-t" aria-describedby="rc-mpwait-d">
				<div class="rc-mpwait__card">
					<div class="rc-mpwait__spin" aria-hidden="true"></div>
					<h3 class="rc-mpwait__t" id="rc-mpwait-t">A cobrar via M-Pesa…</h3>
					<p class="rc-mpwait__d" id="rc-mpwait-d">Enviámos o pedido para o telemóvel do cliente. <b>Peça-lhe para confirmar e introduzir o PIN M-Pesa.</b></p>
					<p class="rc-mpwait__num"></p>
					<p class="rc-mpwait__hint">A confirmação pode demorar até 1 minuto. Não feche nem atualize esta página.</p>
				</div>
			</div>

			<script>
			function clMpesaCobrar( form ) {
				var campo = form.querySelector( 'input[name="telefone"]' );
				var tel = campo ? campo.value.trim() : '';
				if ( ! tel ) { return false; }
				if ( ! confirm( 'Cobrar via M-Pesa ao número ' + tel + '?\n\nO cliente vai receber o pedido de PIN no telemóvel.' ) ) { return false; }
				var w = document.getElementById( 'rc-mpwait' );
				if ( w ) {
					var num = w.querySelector( '.rc-mpwait__num' );
					if ( num ) { num.textContent = tel + ( form.dataset.mpvalor ? '  ·  ' + form.dataset.mpvalor : '' ); }
					w.hidden = false;
					requestAnimationFrame( function () { w.classList.add( 'is-on' ); } );
				}
				return true;
			}
			</script>

			<!-- ============================ MENSAGENS ============================ -->
		<section class="pm-view" data-view="mensagens" hidden>
			<div class="ad-head"><div class="ad-head__t"><h2>Mensagens</h2><p>Pedidos recebidos pelo formulário de contacto do site</p></div>
				<span class="ad-head__meta"><?php echo (int) $msg_novas; ?> por ler</span>
			</div>
			<?php if ( $mensagens ) : ?>
				<div class="rc-msgs">
					<?php foreach ( $mensagens as $msg ) : ?>
						<article class="rc-msg <?php echo empty( $msg['lido'] ) ? 'is-new' : ''; ?>">
							<div class="rc-msg__top">
								<div class="rc-msg__who">
									<div class="rc-msg__from"><?php echo esc_html( $msg['nome'] ); ?><?php if ( empty( $msg['lido'] ) ) : ?> <span class="rc-msg__tag">Novo</span><?php endif; ?></div>
									<div class="rc-msg__meta"><?php echo esc_html( $msg['email'] ); ?><?php echo $msg['telefone'] ? ' · ' . esc_html( $msg['telefone'] ) : ''; ?> · <?php echo esc_html( $fmt_dt( $msg['criado_em'] ) ); ?></div>
								</div>
								<div class="rc-msg__acts">
									<a class="rc-msg__reply" href="mailto:<?php echo esc_attr( $msg['email'] ); ?>?subject=<?php echo rawurlencode( 'Re: ' . ( $msg['assunto'] ?: 'O seu contacto — Clínica Vida' ) ); ?>">Responder por email</a>
									<?php if ( empty( $msg['lido'] ) ) : ?>
										<form method="post" class="rc-msg__act">
											<?php wp_nonce_field( 'rc_msg', 'rc_nonce' ); ?>
											<input type="hidden" name="rc_action" value="msg_lida">
											<input type="hidden" name="id" value="<?php echo (int) $msg['id']; ?>">
											<button type="submit" class="pm-act">Marcar como lida</button>
										</form>
									<?php endif; ?>
								</div>
							</div>
							<?php if ( ! empty( $msg['assunto'] ) ) : ?><div class="rc-msg__subj"><?php echo esc_html( $msg['assunto'] ); ?></div><?php endif; ?>
							<p class="rc-msg__body"><?php echo nl2br( esc_html( $msg['mensagem'] ) ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="pm-empty">Sem mensagens recebidas.</p>
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
	function show( v ) {
		views.forEach( function ( s ) { s.hidden = ( s.dataset.view !== v ); } );
		// O histórico é um detalhe de Pacientes: mantém esse item ativo.
		var nav = ( 'histadmin' === v ) ? 'pacientes' : v;
		items.forEach( function ( i ) { i.classList.toggle( 'is-active', i.dataset.go === nav ); } );
		var atual = grupoDe( nav );
		groups.forEach( function ( g ) {
			g.classList.toggle( 'is-current', g === atual );
			if ( g === atual ) { g.classList.add( 'is-open' ); }
		} );
		if ( shell ) { shell.classList.toggle( 'is-full', v !== 'painel' ); }
		window.scrollTo( 0, 0 );
	}
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
	if ( /[?&]cl=rc_pac_(dados|existe)/.test( location.search ) ) { inicial = 'novo-paciente'; }
	else if ( /[?&]cl=rc_pac_ok/.test( location.search ) ) { inicial = 'pacientes'; }
	else if ( /[?&]cl=nc_/.test( location.search ) ) { inicial = 'marcar'; }
	else if ( /[?&]cl=pg_/.test( location.search ) ) { inicial = 'pagamentos'; }
	show( inicial );

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

		// ---- Nova consulta (walk-in): sector, medico e horarios (AJAX) ----
		( function () {
			var esp = document.getElementById( 'rc-esp' ),
				med = document.getElementById( 'rc-med' ),
				data = document.getElementById( 'rc-data' ),
				caixa = document.getElementById( 'rc-slots' ),
				inicio = document.getElementById( 'rc-inicio' ),
				dias = document.getElementById( 'rc-days' ),
				nslots = document.getElementById( 'rc-nslots' ),
				go = document.getElementById( 'rc-go' ),
				nome = document.getElementById( 'rc-nome' ),
				email = document.getElementById( 'rc-email' ),
				nasc = document.getElementById( 'rc-nasc' ),
				idade = document.getElementById( 'rc-idade' ),
				sPac = document.getElementById( 's-pac' ),
				sEsp = document.getElementById( 's-esp' ),
				sMed = document.getElementById( 's-med' ),
				sData = document.getElementById( 's-data' ),
				sHora = document.getElementById( 's-hora' );
			if ( ! med || ! data || ! caixa ) { return; }
			var AJAX = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

			function msg( t ) { caixa.innerHTML = '<p class="rc-slots__msg">' + t + '</p>'; }
			function txt( el, v ) { if ( el ) { el.textContent = v || ''; el.classList.toggle( 'is-set', !! v ); } }
			function dataLonga( v ) {
				if ( ! v ) { return ''; }
				var p = v.split( '-' ), d = new Date( +p[0], +p[1] - 1, +p[2] ),
					sem = [ 'Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado' ],
					mes = [ 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez' ];
				return sem[ d.getDay() ] + ', ' + d.getDate() + ' ' + mes[ d.getMonth() ];
			}
			function resumo() {
				var temPessoa = !! ( nome && nome.value.trim() && email && email.value.trim() );
				var ok = temPessoa && !! med.value && !! inicio.value;
				if ( go ) { go.disabled = ! ok; }
				txt( sPac, nome ? nome.value.trim() : '' );
				txt( sEsp, esp && esp.value ? esp.options[ esp.selectedIndex ].text : '' );
				txt( sMed, med.value ? med.options[ med.selectedIndex ].text : '' );
				txt( sData, dataLonga( data.value ) );
				txt( sHora, inicio.value ? inicio.value.split( ' ' )[ 1 ] : '' );
			}

			if ( nasc && idade ) {
				nasc.addEventListener( 'change', function () {
					if ( ! nasc.value ) { idade.value = ''; return; }
					var d = new Date( nasc.value ), n = new Date(), a = n.getFullYear() - d.getFullYear();
					if ( n.getMonth() < d.getMonth() || ( n.getMonth() === d.getMonth() && n.getDate() < d.getDate() ) ) { a--; }
					idade.value = ( a >= 0 && a < 130 ) ? a + ' anos' : '';
				} );
			}

			if ( nome ) { nome.addEventListener( 'input', resumo ); }
			if ( email ) { email.addEventListener( 'input', resumo ); }

			if ( esp ) {
				esp.addEventListener( 'change', function () {
					var v = esp.value; med.value = '';
					Array.prototype.forEach.call( med.options, function ( o, i ) { if ( 0 === i ) { return; } o.hidden = !! v && o.dataset.esp !== v; } );
					carregar(); resumo();
				} );
			}

			if ( dias ) {
				dias.querySelectorAll( '.rc-dchip' ).forEach( function ( b ) {
					b.addEventListener( 'click', function () {
						dias.querySelectorAll( '.rc-dchip' ).forEach( function ( o ) { o.classList.remove( 'is-on' ); } );
						b.classList.add( 'is-on' ); data.value = b.dataset.v; carregar();
					} );
				} );
				data.addEventListener( 'change', function () { dias.querySelectorAll( '.rc-dchip' ).forEach( function ( o ) { o.classList.toggle( 'is-on', o.dataset.v === data.value ); } ); } );
			}

			function grupo( titulo, lista ) {
				var w = document.createElement( 'div' ); w.className = 'rc-sg';
				var hh = document.createElement( 'span' ); hh.className = 'rc-sg__h'; hh.textContent = titulo; w.appendChild( hh );
				var g = document.createElement( 'div' ); g.className = 'rc-sg__b';
				lista.forEach( function ( t ) {
					var b = document.createElement( 'button' ); b.type = 'button'; b.className = 'rc-slot'; b.textContent = t;
					b.addEventListener( 'click', function () {
						caixa.querySelectorAll( '.rc-slot' ).forEach( function ( o ) { o.classList.remove( 'is-on' ); } );
						b.classList.add( 'is-on' ); inicio.value = data.value + ' ' + t; resumo();
					} );
					g.appendChild( b );
				} );
				w.appendChild( g ); return w;
			}

			function carregar() {
				inicio.value = ''; resumo();
				if ( nslots ) { nslots.textContent = ''; }
				if ( ! med.value || ! data.value ) { msg( 'Escolha o médico para ver as horas livres.' ); return; }
				msg( 'A procurar horários…' );
				fetch( AJAX + '?action=clinica_slots&medico=' + encodeURIComponent( med.value ) + '&data=' + encodeURIComponent( data.value ), { credentials: 'same-origin' } )
					.then( function ( r ) { return r.json(); } )
					.then( function ( slots ) {
						if ( ! slots || ! slots.length ) { msg( 'Sem horários livres neste dia. Experimente outra data ou outro médico.' ); return; }
						if ( nslots ) { nslots.textContent = slots.length + ( 1 === slots.length ? ' livre' : ' livres' ); }
						var manha = slots.filter( function ( h ) { return parseInt( h, 10 ) < 13; } ), tarde = slots.filter( function ( h ) { return parseInt( h, 10 ) >= 13; } );
						caixa.innerHTML = '';
						if ( manha.length ) { caixa.appendChild( grupo( 'Manhã', manha ) ); }
						if ( tarde.length ) { caixa.appendChild( grupo( 'Tarde', tarde ) ); }
					} )
					.catch( function () { msg( 'Não foi possível obter os horários. Tente de novo.' ); } );
			}

			med.addEventListener( 'change', function () { carregar(); resumo(); } );
			data.addEventListener( 'change', carregar );
			resumo();
		} )();

		// Dashboard: contagem animada no centro do donut.
	( function () {
		var el = document.querySelector( '.rc-donutnum' );
		if ( ! el ) { return; }
		var to = parseInt( el.dataset.to, 10 ) || 0;
		if ( to <= 0 ) { return; }
		var steps = 28, i = 0;
		var t = setInterval( function () {
			i++;
			el.textContent = Math.round( to * i / steps );
			if ( i >= steps ) { clearInterval( t ); el.textContent = to; }
		}, 24 );
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
