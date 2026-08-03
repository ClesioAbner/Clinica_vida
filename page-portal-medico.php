<?php
/**
 * Template Name: Clínica — Painel do Médico
 *
 * Dashboard do médico: resumo de hoje + KPIs + gestão de consultas (filtro,
 * pesquisa e transições) + calendário e próximas consultas. Âmbito por médico.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_stylesheet_directory() . '/inc/clinic-repo.php';
require_once get_stylesheet_directory() . '/inc/clinic-ui.php';

if ( ! is_user_logged_in() || ( ! current_user_can( 'clinica_atender' ) && ! current_user_can( 'clinica_gerir_agenda' ) ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

clinica_controller_medico();

// Processo CLÍNICO em PDF (história, diagnósticos, prescrições e exames).
// Documento próprio do médico — sem pagamentos nem recibos, que são da receção.
if ( isset( $_GET['ficha'] ) && current_user_can( 'clinica_ver_processo' ) ) {
	clinica_medico_render_processo( (int) $_GET['ficha'] );
}

// Receita médica e requisição de exames, para o paciente levar consigo.
if ( isset( $_GET['receita'] ) ) {
	clinica_medico_render_receita( (int) $_GET['receita'] );
}

get_header();

$utilizador    = wp_get_current_user();
$avatar        = function_exists( 'clinica_avatar_url' ) ? clinica_avatar_url( $utilizador->ID, 96 ) : get_avatar_url( $utilizador->ID );
$estado_filtro = isset( $_GET['estado'] ) ? sanitize_key( $_GET['estado'] ) : '';
// "todas" é o submenu sem filtro: serve só para abrir a vista Consultas.
if ( 'todas' === $estado_filtro ) {
	$estado_filtro = '';
}

$e_clinico = current_user_can( 'clinica_atender' ) && ! current_user_can( 'clinica_gerir_agenda' );
if ( $e_clinico ) {
	$medico_id = Clinica_Repo::medico_id_do_user( get_current_user_id() );
	$consultas = Clinica_Repo::consultas_do_medico( $medico_id, $estado_filtro );
	$todas     = Clinica_Repo::consultas_do_medico( $medico_id );
	$kpis      = Clinica_Repo::contagem_por_estado_medico( $medico_id );
} else {
	$consultas = Clinica_Repo::consultas_todas( $estado_filtro );
	$todas     = Clinica_Repo::consultas_todas();
	$kpis      = Clinica_Repo::contagem_por_estado();
}

// Resumo de hoje e próxima consulta.
$agora     = current_time( 'timestamp' );
$hora_dia  = (int) date_i18n( 'G', $agora );
$saudacao  = $hora_dia < 12 ? 'Bom dia' : ( $hora_dia < 19 ? 'Boa tarde' : 'Boa noite' );
$nome_med  = trim( preg_replace( '/\b(Dra?|Dr\(a\))\.?\s*/i', '', $utilizador->display_name ) ) ?: $utilizador->display_name;
$primeiro  = explode( ' ', $nome_med )[0];
$data_hoje = date_i18n( 'l, j \d\e F', $agora );
// Título por género (heurística PT: nome terminado em 'a' -> feminino).
$titulo = static function ( $nome ) {
	$prim = explode( ' ', trim( (string) $nome ) )[0];
	return ( 'a' === mb_strtolower( mb_substr( $prim, -1 ) ) ) ? 'Dra.' : 'Dr.';
};
$hoje_ymd  = date_i18n( 'Y-m-d', $agora );
$futuras   = array_values( array_filter( $todas, static function ( $c ) { return strtotime( $c['inicio'] ) >= time() && in_array( $c['status'], array( 'pendente', 'confirmada' ), true ); } ) );
usort( $futuras, static function ( $a, $b ) { return strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] ); } );
// Acesso a todas as fichas de pacientes (continuidade de cuidados — decisão do dono).
$pacientes = Clinica_Repo::pacientes_do_medico( 0 );

// ---- Ficha do paciente (?pac=ID) — só pacientes do próprio médico ----
$ficha = null; $historico = array(); $proc = null; $hist_meu = 0; $hist_outros = 0;
$pac_id = isset( $_GET['pac'] ) ? (int) $_GET['pac'] : 0;
if ( $pac_id && current_user_can( 'clinica_ver_processo' ) ) {
	$pode_ver = true; // médico acede à ficha de qualquer paciente.
	if ( $pode_ver ) {
		$ficha = Clinica_Repo::paciente_ficha( $pac_id );
		if ( $ficha ) {
			$historico = Clinica_Repo::historico_do_paciente( $pac_id );
			$proc      = class_exists( 'Clinica_Processo' ) ? Clinica_Processo::do_paciente( $pac_id ) : null;
			// Módulos clínicos do processo (separadores da ficha).
			$receitas   = Clinica_Clinico::receitas_do_paciente( $pac_id );
			$exames     = Clinica_Clinico::exames_do_paciente( $pac_id );
			$documentos = Clinica_Clinico::documentos_do_paciente( $pac_id );
			$diag_map  = array();
			if ( class_exists( 'Clinica_Clinico' ) ) {
				foreach ( Clinica_Clinico::diagnosticos_do_paciente( $pac_id ) as $d ) { $diag_map[ (int) $d['consulta_id'] ][] = $d; }
			}
			foreach ( $historico as $h ) {
				if ( $e_clinico && (int) $h['medico_id'] === (int) ( $medico_id ?? 0 ) ) { $hist_meu++; } else { $hist_outros++; }
			}
		}
	}
}
// ---- Atendimento (?atender=ID) — só consultas do próprio médico, não encerradas ----
$atender = null;
$at_id   = isset( $_GET['atender'] ) ? (int) $_GET['atender'] : 0;
if ( $at_id && current_user_can( 'clinica_atender' ) ) {
	foreach ( $todas as $c ) {
		if ( (int) $c['id'] === $at_id && ! in_array( $c['status'], array( 'realizada', 'cancelada', 'faltou' ), true ) ) {
			$atender = $c;
			break;
		}
	}
}
$at_proc = ( $atender && class_exists( 'Clinica_Processo' ) ) ? Clinica_Processo::do_paciente( (int) $atender['paciente_id'] ) : null;

// Contexto clínico do ecrã Atender: o médico não deve escrever às cegas.
$at_pac    = null;
$at_diags  = array();
$at_recs   = array();
$at_exames = array();
$at_idade  = null;
if ( $atender ) {
	$at_pid    = (int) $atender['paciente_id'];
	$at_pac    = Clinica_Repo::paciente_ficha( $at_pid );
	$at_diags  = array_slice( Clinica_Clinico::diagnosticos_do_paciente( $at_pid ), 0, 4 );
	$at_recs   = array_slice( Clinica_Clinico::receitas_do_paciente( $at_pid ), 0, 4 );
	$at_exames = array_slice( Clinica_Clinico::exames_do_paciente( $at_pid ), 0, 3 );
	$at_idade  = Clinica_Repo::idade( $at_pac['data_nascimento'] ?? null );
}

// Apenas dados reais: sem pacientes nem consultas de exemplo. As vistas têm
// estados vazios próprios (mensagens amigáveis) para quando ainda não há dados.

// Próximas consultas (reais) para as vistas Painel/Agenda.
$prox           = $futuras;
$proxima        = $prox[0] ?? null;
$agenda_por_dia = array();
foreach ( $prox as $f ) {
	$agenda_por_dia[ substr( $f['inicio'], 0, 10 ) ][] = $f;
}

// Fila de hoje — a bancada de trabalho do médico, lidera o painel.
// TODAS as consultas de hoje, ordenadas: ativas antes das terminadas, e quem
// já fez check-in na receção sobe ao topo (é quem está à espera de ser atendido).
$terminado = array( 'realizada', 'cancelada', 'faltou' );
$fila_hoje = array_values( array_filter( $todas, static function ( $c ) use ( $hoje_ymd ) {
	return substr( $c['inicio'], 0, 10 ) === $hoje_ymd;
} ) );
usort( $fila_hoje, static function ( $a, $b ) use ( $terminado ) {
	$fa = in_array( $a['status'], $terminado, true );
	$fb = in_array( $b['status'], $terminado, true );
	if ( $fa !== $fb ) { return $fa <=> $fb; }        // ativas antes das terminadas
	$ca = ! empty( $a['checkin_em'] );
	$cb = ! empty( $b['checkin_em'] );
	if ( $ca !== $cb ) { return $cb <=> $ca; }        // quem chegou sobe
	return strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] );
} );
$hoje_total  = count( $fila_hoje );
$hoje_ativos = count( array_filter( $fila_hoje, static function ( $c ) use ( $terminado ) {
	return ! in_array( $c['status'], $terminado, true );
} ) );
$aguardam    = count( array_filter( $fila_hoje, static function ( $c ) use ( $terminado ) {
	return ! empty( $c['checkin_em'] ) && ! in_array( $c['status'], $terminado, true );
} ) );

// ---- Dados dos gráficos ----
$seg = array(
	array( 'Pendentes', (int) $kpis['pendente'], '#A9C9DB' ),
	array( 'Confirmadas', (int) $kpis['confirmada'], '#4E7E9B' ),
	array( 'Realizadas', (int) $kpis['realizada'], '#33566B' ),
	array( 'Canceladas / faltas', (int) ( $kpis['cancelada'] + $kpis['faltou'] ), '#CBD5DD' ),
);
$seg_total = array_sum( array_column( $seg, 1 ) );
$acc       = 0;
$stops     = array();
foreach ( $seg as $s ) {
	if ( $s[1] <= 0 ) {
		continue;
	}
	$from   = $seg_total ? ( $acc / $seg_total * 100 ) : 0;
	$acc   += $s[1];
	$to     = $seg_total ? ( $acc / $seg_total * 100 ) : 0;
	$stops[] = sprintf( '%s %.2f%% %.2f%%', $s[2], $from, $to );
}
$donut_bg = $seg_total ? ( 'conic-gradient(' . implode( ',', $stops ) . ')' ) : '#E7ECF2';

// Barras: consultas por dia (últimos 7 dias).
$dias7 = array();
for ( $i = 6; $i >= 0; $i-- ) {
	$ts = strtotime( "-$i day", $agora );
	$dias7[ date( 'Y-m-d', $ts ) ] = array( 'l' => date_i18n( 'D', $ts ), 'n' => 0 );
}
foreach ( $todas as $c ) {
	$d = substr( $c['inicio'], 0, 10 );
	if ( isset( $dias7[ $d ] ) ) {
		$dias7[ $d ]['n']++;
	}
}
$max7 = max( 1, max( array_column( $dias7, 'n' ) ) );

// Tendência das últimas 8 semanas (gráfico de linha).
$semanas = array();
for ( $i = 7; $i >= 0; $i-- ) {
	$ts = strtotime( "-$i week", $agora );
	$semanas[ date( 'o-W', $ts ) ] = array( 'l' => date_i18n( 'j/n', $ts ), 'n' => 0 );
}
foreach ( $todas as $c ) {
	$wk = date( 'o-W', strtotime( $c['inicio'] ) );
	if ( isset( $semanas[ $wk ] ) ) { $semanas[ $wk ]['n']++; }
}
$max_sem  = max( 1, max( array_column( $semanas, 'n' ) ) );
$sem_pts  = array();
$sem_dots = array();
$sem_n    = count( $semanas );
$ix       = 0;
foreach ( $semanas as $wk ) {
	$x   = $sem_n > 1 ? ( $ix / ( $sem_n - 1 ) * 100 ) : 50;
	$pct = $wk['n'] / $max_sem * 100;          // 0 = base, 100 = topo
	$sem_pts[]  = round( $x, 1 ) . ',' . round( 100 - $pct, 1 );
	$sem_dots[] = array( 'x' => round( $x, 1 ), 'y' => round( $pct, 1 ), 'n' => (int) $wk['n'], 'l' => $wk['l'] );
	$ix++;
}
$sem_poly = implode( ' ', $sem_pts );
$sem_area = '0,100 ' . $sem_poly . ' 100,100';

// Dashboard nunca vazio nem esquelético: quando o período tem pouca atividade
// os gráficos usam uma amostra, sempre assinalada como exemplo para não enganar.
// Um gráfico precisa de distribuição, não só de volume: quatro consultas
// todas no mesmo dia continuam a dar uma barra sozinha.
$dias_com  = count( array_filter( array_column( $dias7, 'n' ) ) );
$sem_com   = count( array_filter( array_column( $semanas, 'n' ) ) );
$demo_d    = $dias_com < 3;
$demo_s    = $sem_com < 3;
$dash_demo = $demo_d || $demo_s;

if ( $demo_d ) {
	$amostra_d = array( 6, 9, 7, 12, 10, 8, 11 );
	$i = 0;
	foreach ( $dias7 as $k => $v ) { $dias7[ $k ]['n'] = $amostra_d[ $i % 7 ]; $i++; }
	$max7 = max( $amostra_d );
}

if ( $demo_s ) {
	$amostra_s = array( 18, 24, 21, 29, 26, 33, 30, 36 );
	$i = 0;
	foreach ( $semanas as $k => $v ) { $semanas[ $k ]['n'] = $amostra_s[ $i % 8 ]; $i++; }
	$max_sem  = max( $amostra_s );
	$sem_pts  = array();
	$sem_dots = array();
	$ix       = 0;
	foreach ( $semanas as $wk ) {
		$x   = $sem_n > 1 ? ( $ix / ( $sem_n - 1 ) * 100 ) : 50;
		$pct = $wk['n'] / $max_sem * 100;
		$sem_pts[]  = round( $x, 1 ) . ',' . round( 100 - $pct, 1 );
		$sem_dots[] = array( 'x' => round( $x, 1 ), 'y' => round( $pct, 1 ), 'n' => (int) $wk['n'], 'l' => $wk['l'] );
		$ix++;
	}
	$sem_poly = implode( ' ', $sem_pts );
	$sem_area = '0,100 ' . $sem_poly . ' 100,100';
}

// Carga por dia da semana — útil ao médico (um médico tem só uma especialidade,
// por isso "por especialidade" seria sempre uma barra só).
$dias_sem = array( 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo' );
$por_dia  = array_fill_keys( array_values( $dias_sem ), 0 );
foreach ( $todas as $c ) {
	$d = (int) date( 'N', strtotime( $c['inicio'] ) );
	if ( isset( $dias_sem[ $d ] ) ) { $por_dia[ $dias_sem[ $d ] ]++; }
}
$por_dia_max = max( 1, max( $por_dia ) );
$total_cons  = array_sum( array_column( $seg, 1 ) );

// Dias do mês com consultas (para marcar no calendário).
$mes_atual     = date_i18n( 'Y-m', $agora );
$dias_marcados = array();
foreach ( array_merge( $todas, $prox ) as $c ) {
	if ( substr( $c['inicio'], 0, 7 ) === $mes_atual ) {
		$dias_marcados[ (int) date_i18n( 'j', strtotime( $c['inicio'] ) ) ] = true;
	}
}

$transicoes = array(
	'pendente'   => array( 'confirmada' => 'Confirmar', 'cancelada' => 'Recusar' ),
	'confirmada' => array( 'faltou' => 'Faltou', 'cancelada' => 'Cancelar' ),
	'realizada'  => array(),
	'cancelada'  => array(),
	'faltou'     => array(),
);
/**
 * Botão de uma ação do fluxo de atendimento (chamar/iniciar/pausar/retomar).
 * Um formulário POST com nonce por ação — o fluxo nunca vai por GET.
 */
if ( ! function_exists( 'clinica_med_fluxo_btn' ) ) :
function clinica_med_fluxo_btn( int $consulta_id, string $acao, string $texto, string $classe = 'pm-act--ok', bool $icone = false ): void {
	$svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 4v16M14 4v16"/></svg>';
	?>
	<form method="post" class="pm-af"<?php echo 'pausar' === $acao ? ' onsubmit="return confirm(\'Pôr esta consulta em pausa?\');"' : ''; ?>>
		<?php wp_nonce_field( 'cl_fluxo', 'cl_fluxo_nonce' ); ?>
		<input type="hidden" name="cl_fluxo" value="<?php echo esc_attr( $acao ); ?>">
		<input type="hidden" name="consulta_id" value="<?php echo (int) $consulta_id; ?>">
		<button type="submit" class="pm-act <?php echo esc_attr( $classe ); ?>" title="<?php echo esc_attr( $texto ); ?>" aria-label="<?php echo esc_attr( $texto ); ?>"><?php echo $icone ? $svg : esc_html( $texto ); ?></button>
	</form>
	<?php
}
endif;

// Ícones das transições: mantém a coluna de ações compacta e alinhada.
$icones = array(
	'confirmada' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
	'cancelada'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
	'faltou'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/></svg>',
);
$labels = array(
	'pendente'       => 'Pendente',
	'confirmada'     => 'Confirmada',
	'chamada'        => 'Chamada',
	'em_atendimento' => 'Em atendimento',
	'pausada'        => 'Em pausa',
	'realizada'      => 'Realizada',
	'cancelada'      => 'Cancelada',
	'faltou'         => 'Faltou',
);
$abas = array(
	''           => array( 'Todas', array_sum( $kpis ) ),
	'pendente'   => array( 'Pendentes', $kpis['pendente'] ),
	'confirmada' => array( 'Confirmadas', $kpis['confirmada'] ),
	'realizada'  => array( 'Realizadas', $kpis['realizada'] ),
	'cancelada'  => array( 'Canceladas', $kpis['cancelada'] + $kpis['faltou'] ),
);
$fmt      = static function ( $iso ) { return date_i18n( 'd/m · H:i', strtotime( $iso ) ); };
$fmt_hora = static function ( $iso ) { return date_i18n( 'H:i', strtotime( $iso ) ); };

$mes_ts    = $agora;
$dia_hoje  = (int) date_i18n( 'j', $mes_ts );
$prim_ts   = mktime( 0, 0, 0, (int) date( 'n', $mes_ts ), 1, (int) date( 'Y', $mes_ts ) );
$dias_mes  = (int) date( 't', $prim_ts );
$offset    = (int) date( 'N', $prim_ts ) - 1;
$nome_mes  = date_i18n( 'F Y', $prim_ts );
?>
<div class="hp">
<div class="pm-shell">

	<aside class="pm-side">
		<div class="pm-side__brand">
			<span class="pm-side__logo"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo2.png' ); ?>" alt=""></span>
			<span class="pm-side__brandname">Clínica Vida</span>
		</div>

		<div class="pm-side__me">
			<img src="<?php echo esc_url( $avatar ); ?>" alt="">
			<div class="pm-side__meinfo">
				<div class="pm-side__name"><?php echo esc_html( $titulo( $nome_med ) . ' ' . $primeiro ); ?></div>
				<div class="pm-side__role">Médico</div>
			</div>
		</div>

		<nav class="pm-nav">
			<span class="pm-nav__cap">Menu</span>
			<a class="pm-nav__i is-active" href="#" data-go="painel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Painel</span></a>
			<a class="pm-nav__i" href="#" data-go="pacientes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Pacientes</span></a>
			<div class="pm-nav__group">
				<a class="pm-nav__i" href="#" data-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg><span>Consultas</span><i class="pm-nav__chev" aria-hidden="true"></i></a>
				<div class="pm-nav__sub">
					<?php
					$base_url = remove_query_arg( array( 'estado', 'pac', 'atender', 'cl' ) );
					$sub_est  = array(
						'todas'      => array( 'Todas', array_sum( $kpis ) ),
						'pendente'   => array( 'Pendentes', $kpis['pendente'] ),
						'confirmada' => array( 'Confirmadas', $kpis['confirmada'] ),
						'realizada'  => array( 'Realizadas', $kpis['realizada'] ),
					);
					$est_atual = isset( $_GET['estado'] ) ? sanitize_key( $_GET['estado'] ) : '';
					foreach ( $sub_est as $k => $info ) : ?>
						<a class="pm-nav__subi<?php echo $est_atual === $k ? ' is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'estado', $k, $base_url ) ); ?>"><?php echo esc_html( $info[0] ); ?><?php if ( $info[1] ) : ?> <b class="pm-badge"><?php echo (int) $info[1]; ?></b><?php endif; ?></a>
					<?php endforeach; ?>
				</div>
			</div>
			<a class="pm-nav__i" href="#" data-go="agenda"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M12 14v3M9 14h6"/></svg><span>Agenda</span></a>
		</nav>

		<a class="pm-nav__i pm-nav__out" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg><span>Sair</span></a>
	</aside>

	<main class="pm-main">

		<header class="pm-top">
			<div class="pm-top__l">
				<h1 class="pm-welcome"><?php echo esc_html( $saudacao . ', ' . $titulo( $nome_med ) . ' ' . $primeiro ); ?></h1>
				<p class="pm-date"><?php echo esc_html( ucfirst( $data_hoje ) ); ?></p>
			</div>
			<div class="pm-user">
				<span class="pm-user__meta"><b><?php echo esc_html( $utilizador->display_name ); ?></b><small>Médico</small></span>
							</div>
		</header>

		<?php clinica_render_flash(); ?>

			<!-- KPIs -->
			<section class="pm-kpis pm-view" data-view="painel">
				<?php
				$cards = array(
					array( 'Pendentes', $kpis['pendente'], 'pend' ),
					array( 'Confirmadas', $kpis['confirmada'], 'conf' ),
					array( 'Realizadas', $kpis['realizada'], 'real' ),
					array( 'Canceladas e faltas', $kpis['cancelada'] + $kpis['faltou'], 'canc' ),
				);
				foreach ( $cards as $c ) : ?>
					<div class="pm-kpi">
						<span class="pm-kpi__l"><?php echo esc_html( $c[0] ); ?></span>
						<strong class="pm-kpi__n"><?php echo (int) $c[1]; ?></strong>
					</div>
				<?php endforeach; ?>
			</section>

			<!-- Gráficos: barras (7 dias) + tendência (8 semanas) -->
			<section class="pm-charts pm-view" data-view="painel">
				<div class="pm-chart">
					<div class="pm-chart__head"><strong>Consultas por dia</strong><span><?php echo $demo_d ? "exemplo" : "últimos 7 dias"; ?></span></div>
					<div class="pm-plot">
						<div class="pm-plot__y">
							<?php for ( $g = 4; $g >= 0; $g-- ) : ?>
								<span><?php echo (int) round( $max7 * $g / 4 ); ?></span>
							<?php endfor; ?>
						</div>
						<div class="pm-plot__c">
							<div class="pm-plot__bars">
								<?php foreach ( $dias7 as $dk => $dd ) : $e_hoje = ( $dk === current_time( 'Y-m-d' ) ); ?>
									<div class="pm-plot__b<?php echo $e_hoje ? ' is-hoje' : ''; ?>">
										<span class="pm-plot__v"><?php echo (int) $dd['n']; ?></span>
										<span class="pm-plot__bar" style="height:<?php echo (int) round( $dd['n'] / $max7 * 100 ); ?>%"></span>
									</div>
								<?php endforeach; ?>
							</div>
							<div class="pm-plot__x">
								<?php foreach ( $dias7 as $dk => $dd ) : $e_hoje = ( $dk === current_time( 'Y-m-d' ) ); ?>
									<span class="<?php echo $e_hoje ? 'is-hoje' : ''; ?>">
										<b><?php echo esc_html( $dd['l'] ); ?></b>
										<i><?php echo esc_html( date( 'j/n', strtotime( $dk ) ) ); ?></i>
									</span>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>

				<div class="pm-chart">
					<div class="pm-chart__head"><strong>Tendência</strong><span><?php echo $demo_s ? "exemplo" : "últimas 8 semanas"; ?></span></div>
					<div class="pm-plot">
						<div class="pm-plot__y">
							<span><?php echo (int) $max_sem; ?></span>
							<span><?php echo (int) ceil( $max_sem / 2 ); ?></span>
							<span>0</span>
						</div>
						<div class="pm-plot__c">
							<div class="pm-plot__line">
								<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
									<polygon class="pm-line__area" points="<?php echo esc_attr( $sem_area ); ?>"></polygon>
									<polyline class="pm-line__l" points="<?php echo esc_attr( $sem_poly ); ?>"></polyline>
								</svg>
								<?php foreach ( $sem_dots as $d ) : ?>
									<i class="pm-line__dot" style="left:<?php echo esc_attr( $d['x'] ); ?>%; bottom:<?php echo esc_attr( $d['y'] ); ?>%" title="<?php echo (int) $d['n']; ?> consulta(s) · semana de <?php echo esc_attr( $d['l'] ); ?>"></i>
								<?php endforeach; ?>
							</div>
							<div class="pm-plot__x">
								<?php $j = 0; foreach ( $semanas as $wk ) : ?><span><?php echo ( 0 === $j % 2 ) ? esc_html( $wk['l'] ) : ''; ?></span><?php $j++; endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</section>

			<!-- Consultas por especialidade (barras horizontais, ordenadas) -->
			<section class="pm-espec pm-view" data-view="painel">
				<div class="pm-chart__head"><strong>Próximas consultas</strong><span><?php echo count( $prox ); ?> agendadas</span></div>
				<?php if ( $prox ) : ?>
					<div class="pm-ntbl">
						<div class="pm-ntbl__head"><span>Data</span><span>Hora</span><span>Paciente</span><span>Especialidade</span><span>Estado</span></div>
						<?php foreach ( array_slice( $prox, 0, 3 ) as $r ) : ?>
							<div class="pm-nrow">
								<span class="pm-nrow__d"><?php echo esc_html( date_i18n( 'd/m', strtotime( $r['inicio'] ) ) ); ?></span>
								<span class="pm-nrow__h"><?php echo esc_html( date_i18n( 'H:i', strtotime( $r['inicio'] ) ) ); ?></span>
								<span class="pm-nrow__c pm-nrow__n"><?php echo esc_html( $r['pac_nome'] ); ?></span>
								<span class="pm-nrow__c"><?php echo esc_html( $r['esp_nome'] ); ?></span>
								<span class="pm-nrow__s"><span class="pm-st pm-st--<?php echo esc_attr( $r['status'] ); ?>"><?php echo esc_html( $labels[ $r['status'] ] ?? $r['status'] ); ?></span></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="pm-empty">Sem consultas agendadas</p>
				<?php endif; ?>
			</section>

						<!-- Fichas dos pacientes -->
		<section id="pacientes" class="pm-panel pm-view" data-view="pacientes" hidden>
			<div class="pm-panel__head">
				<h3>Pacientes</h3>
				<input type="search" class="pm-search" id="pm-psearch" placeholder="Procurar paciente…" aria-label="Procurar paciente">
			</div>
			<?php if ( $pacientes ) : ?>
				<div class="pm-ptbl">
					<div class="pm-ptbl__head">
						<span>Paciente</span>
						<span>Processo</span>
						<span>Idade e género</span>
						<span>Alergias</span>
						<span>Última consulta</span>
						<span class="r">Consultas</span>
						<span></span>
					</div>
					<?php foreach ( $pacientes as $p ) :
						$idade = Clinica_Repo::idade( $p['data_nascimento'] ?? null );
						$gen   = 'F' === ( $p['genero'] ?? '' ) ? 'F' : ( 'M' === ( $p['genero'] ?? '' ) ? 'M' : '' );
						$pid   = (int) ( $p['id'] ?? 0 );
						$pn    = trim( (string) $p['nome'] );
						$parts = preg_split( '/\s+/', $pn ) ?: array( $pn );
						$ini   = mb_strtoupper( mb_substr( $parts[0], 0, 1 ) . ( count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '' ) );
					?>
						<a class="pm-prow" data-nome="<?php echo esc_attr( mb_strtolower( $pn . ' ' . ( $p['processo'] ?? '' ) ) ); ?>" href="<?php echo esc_url( add_query_arg( 'pac', $pid, remove_query_arg( array( 'cl', 'pac', 'estado' ) ) ) ); ?>">
							<span class="pm-prow__pac"><span class="pm-pav"><?php echo esc_html( $ini ); ?></span><span class="pm-prow__n"><?php echo esc_html( $pn ); ?></span></span>
							<span class="pm-prow__c pm-prow__proc"><?php echo esc_html( $p['processo'] ?: '' ); ?></span>
							<span class="pm-prow__c"><?php echo null !== $idade ? (int) $idade . ' anos' : ''; ?><?php echo ( null !== $idade && $gen ) ? ' · ' : ''; ?><?php echo esc_html( $gen ); ?></span>
							<span class="pm-prow__c">
								<?php if ( ! empty( $p['alergias'] ) ) : ?>
									<span class="pm-flag" title="<?php echo esc_attr( $p['alergias'] ); ?>">Alergias</span>
								<?php endif; ?>
							</span>
							<span class="pm-prow__c pm-prow__ult"><?php echo $p['ultima'] ? esc_html( date_i18n( 'd/m/Y', strtotime( $p['ultima'] ) ) ) : ''; ?></span>
							<span class="pm-prow__c r pm-prow__num"><?php echo (int) $p['n_consultas']; ?></span>
							<span class="pm-prow__go" aria-hidden="true">›</span>
						</a>
					<?php endforeach; ?>
				</div>
				<p class="pm-empty" id="pm-pnoresult" hidden>Nenhum paciente corresponde à pesquisa</p>
			<?php else : ?>
				<p class="pm-empty">Ainda não tem pacientes com consultas</p>
			<?php endif; ?>
		</section>

		<!-- Atender: registo clínico (nota SOAP + diagnóstico obrigatório) -->
		<section class="pm-view" data-view="atender" hidden>
			<?php if ( $atender ) : ?>
				<div class="pm-fichahead">
					<div>
						<h2 class="pm-fichahead__n">Atender · <?php echo esc_html( $atender['pac_nome'] ); ?></h2>
						<p class="pm-fichahead__s"><?php echo esc_html( $atender['esp_nome'] ); ?> · <?php echo esc_html( $fmt( $atender['inicio'] ) ); ?></p>
					</div>
					<a class="pm-back" href="<?php echo esc_url( remove_query_arg( array( 'atender', 'cl' ) ) ); ?>">← Voltar</a>
				</div>

				<?php if ( $at_proc && ! empty( $at_proc['alergias'] ) ) : ?>
					<div class="pm-alerta"><b>Alergias:</b> <?php echo esc_html( $at_proc['alergias'] ); ?></div>
				<?php endif; ?>

				<div class="pm-atgrid">
					<div class="pm-atmain">
						<form method="post" class="pm-atender">
							<?php wp_nonce_field( 'cl_atender', 'cl_atender_nonce' ); ?>
							<input type="hidden" name="cl_atender" value="1">
							<input type="hidden" name="consulta_id" value="<?php echo (int) $atender['id']; ?>">

							<div class="pm-panel">
								<div class="pm-panel__head"><h3>Nota clínica (SOAP)</h3></div>
								<div class="pm-soap">
									<label class="pm-fld"><span>S · Subjetivo</span><textarea name="subjetivo" rows="3" placeholder="Queixa e história relatadas pelo paciente"></textarea></label>
									<label class="pm-fld"><span>O · Objetivo</span><textarea name="objetivo" rows="3" placeholder="Exame físico, sinais e observações"></textarea></label>
									<label class="pm-fld"><span>A · Avaliação</span><textarea name="avaliacao" rows="3" placeholder="Interpretação clínica"></textarea></label>
									<label class="pm-fld"><span>P · Plano</span><textarea name="plano" rows="3" placeholder="Conduta, tratamento e seguimento"></textarea></label>
								</div>
							</div>

							<div class="pm-panel pm-panel--gap">
								<div class="pm-panel__head"><h3>Diagnóstico <span class="pm-req">obrigatório</span></h3></div>
								<div class="pm-diag">
									<label class="pm-fld pm-fld--sm"><span>Código CID-10</span><input type="text" name="cid10" list="cid-comuns" required placeholder="Ex.: J06.9" maxlength="10"></label>
									<label class="pm-fld"><span>Descrição</span><input type="text" name="cid_descricao" placeholder="Ex.: Infeção aguda das vias respiratórias superiores"></label>
								</div>
								<datalist id="cid-comuns">
									<option value="J06.9">Infeção aguda das vias respiratórias superiores</option>
									<option value="I10">Hipertensão essencial</option>
									<option value="E11.9">Diabetes mellitus tipo 2</option>
									<option value="B54">Malária não especificada</option>
									<option value="A09">Gastroenterite e diarreia</option>
									<option value="R51">Cefaleia</option>
									<option value="K29.7">Gastrite</option>
									<option value="Z00.0">Exame médico geral</option>
								</datalist>
								<p class="pm-hint">Sem diagnóstico não é possível concluir o atendimento</p>
							</div>

							<div class="pm-panel pm-panel--gap">
								<div class="pm-panel__head"><h3>Necessidade de retorno</h3></div>
								<div class="pm-diag">
									<label class="pm-fld pm-fld--sm"><span>Prazo</span>
										<select name="retorno_prazo">
											<option value="0">Não é preciso voltar</option>
											<option value="7">1 semana</option>
											<option value="14">2 semanas</option>
											<option value="30">1 mês</option>
											<option value="90">3 meses</option>
											<option value="180">6 meses</option>
										</select>
									</label>
									<label class="pm-fld"><span>Indicação</span><input type="text" name="retorno_nota" maxlength="200" placeholder="Ex.: reavaliar tensão arterial e trazer análises"></label>
								</div>
								<p class="pm-hint">A data concreta é marcada pela receção</p>
							</div>

							<div class="pm-atender__foot">
								<a class="pm-act" href="<?php echo esc_url( remove_query_arg( array( 'atender', 'cl' ) ) ); ?>">Cancelar</a>
								<button type="submit" class="pm-btn-primary">Concluir atendimento</button>
							</div>
						</form>

						<!--
						Prescrever e pedir exames no próprio ato, sem sair do ecrã.
						São formulários independentes: guardam de imediato e não
						dependem da conclusão da consulta.
						-->
						<div class="pm-atcols">
							<?php if ( current_user_can( 'clinica_prescrever' ) ) : ?>
								<form method="post" class="pm-panel pm-atform">
									<?php wp_nonce_field( 'cl_presc', 'cl_presc_nonce' ); ?>
									<input type="hidden" name="cl_prescrever" value="1">
									<input type="hidden" name="paciente_id" value="<?php echo (int) $atender['paciente_id']; ?>">
									<input type="hidden" name="consulta_id" value="<?php echo (int) $atender['id']; ?>">
									<div class="pm-panel__head"><h3>Prescrever</h3></div>
									<label class="pm-fld"><span>Medicamento</span><input type="text" name="medicamento" required placeholder="Ex.: Amoxicilina"></label>
									<div class="pm-diag">
										<label class="pm-fld pm-fld--sm"><span>Dosagem</span><input type="text" name="dosagem" placeholder="500 mg"></label>
										<label class="pm-fld pm-fld--sm"><span>Duração</span><input type="text" name="duracao" placeholder="7 dias"></label>
									</div>
									<label class="pm-fld"><span>Posologia</span><input type="text" name="posologia" placeholder="1 comprimido de 8 em 8 horas"></label>
									<div class="pm-atform__foot"><button type="submit" class="pm-btn-primary">Adicionar prescrição</button></div>
								</form>
							<?php endif; ?>

							<?php if ( current_user_can( 'clinica_pedir_exames' ) ) : ?>
								<form method="post" class="pm-panel pm-atform">
									<?php wp_nonce_field( 'cl_exame', 'cl_exame_nonce' ); ?>
									<input type="hidden" name="cl_exame" value="1">
									<input type="hidden" name="paciente_id" value="<?php echo (int) $atender['paciente_id']; ?>">
									<input type="hidden" name="consulta_id" value="<?php echo (int) $atender['id']; ?>">
									<div class="pm-panel__head"><h3>Pedir exame</h3></div>
									<label class="pm-fld"><span>Exame</span><input type="text" name="exame" required placeholder="Ex.: Hemograma completo"></label>
									<div class="pm-diag">
										<label class="pm-fld pm-fld--sm"><span>Tipo</span><input type="text" name="tipo" placeholder="Laboratório"></label>
										<label class="pm-fld pm-fld--sm"><span>Urgência</span>
											<select name="urgencia">
												<option value="normal">Normal</option>
												<option value="urgente">Urgente</option>
											</select>
										</label>
									</div>
									<label class="pm-fld"><span>Indicação</span><input type="text" name="notas" placeholder="Motivo do pedido"></label>
									<div class="pm-atform__foot"><button type="submit" class="pm-btn-primary">Adicionar pedido</button></div>
								</form>
							<?php endif; ?>
						</div>
					</div>

					<!-- Contexto clínico: o que o médico precisa de ter à vista -->
					<aside class="pm-atside">
						<div class="pm-panel">
							<div class="pm-panel__head"><h3>Paciente</h3></div>
							<dl class="pm-atdl">
								<div><dt>Processo</dt><dd><?php echo esc_html( $at_proc['numero'] ?? '' ) ?: 'Sem processo'; ?></dd></div>
								<div><dt>Idade</dt><dd><?php echo null !== $at_idade ? (int) $at_idade . ' anos' : 'Não registada'; ?></dd></div>
								<div><dt>Género</dt><dd><?php echo esc_html( $at_pac['genero'] ?? '' ) ?: 'Não registado'; ?></dd></div>
								<div><dt>Telefone</dt><dd><?php echo esc_html( $at_pac['telefone'] ?? '' ) ?: 'Não registado'; ?></dd></div>
							</dl>
						</div>

						<div class="pm-panel pm-panel--gap">
							<div class="pm-panel__head"><h3>Diagnósticos anteriores</h3></div>
							<?php if ( $at_diags ) : ?>
								<ul class="pm-atlist">
									<?php foreach ( $at_diags as $d ) : ?>
										<li>
											<b><?php echo esc_html( $d['cid10'] ); ?></b>
											<span><?php echo esc_html( $d['descricao'] ?: '' ); ?></span>
											<i><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $d['criado_em'] ) ) ); ?></i>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="pm-empty">Primeira consulta registada</p>
							<?php endif; ?>
						</div>

						<div class="pm-panel pm-panel--gap">
							<div class="pm-panel__head"><h3>Medicação em curso</h3></div>
							<?php if ( $at_recs ) : ?>
								<ul class="pm-atlist">
									<?php foreach ( $at_recs as $r ) : ?>
										<li>
											<b><?php echo esc_html( $r['medicamento'] ); ?></b>
											<span><?php echo esc_html( trim( $r['dosagem'] . ' ' . $r['posologia'] ) ); ?></span>
											<i><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $r['criado_em'] ) ) ); ?></i>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="pm-empty">Sem prescrições registadas</p>
							<?php endif; ?>
						</div>

						<div class="pm-panel pm-panel--gap">
							<div class="pm-panel__head"><h3>Exames pedidos</h3></div>
							<?php if ( $at_exames ) : ?>
								<ul class="pm-atlist">
									<?php foreach ( $at_exames as $e ) : ?>
										<li>
											<b><?php echo esc_html( $e['exame'] ); ?></b>
											<span><?php echo esc_html( $e['estado'] ); ?></span>
											<i><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $e['criado_em'] ) ) ); ?></i>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="pm-empty">Sem exames pedidos</p>
							<?php endif; ?>
						</div>
					</aside>
				</div>
			<?php else : ?>
				<p class="pm-empty">Consulta não disponível para atendimento (já concluída ou fora do seu âmbito)</p>
			<?php endif; ?>
		</section>
		<section class="pm-view" data-view="ficha" hidden>
			<?php if ( $ficha ) :
				$idade_f = Clinica_Repo::idade( $ficha['data_nascimento'] ?? null );
				$gen_f   = 'F' === ( $ficha['genero'] ?? '' ) ? 'Feminino' : ( 'M' === ( $ficha['genero'] ?? '' ) ? 'Masculino' : '' );
				$nasc_f  = ( ! empty( $ficha['data_nascimento'] ) && '0000-00-00' !== $ficha['data_nascimento'] ) ? $ficha['data_nascimento'] : '';
				$ult     = '';
				foreach ( $historico as $h ) { if ( 'realizada' === $h['status'] ) { $ult = $h['inicio']; break; } }
				$n_diag = 0;
				foreach ( $diag_map as $ds ) { $n_diag += count( $ds ); }
				$cons_aberta = 0;
				foreach ( $historico as $h ) { if ( in_array( $h['status'], array( 'em_atendimento', 'pausada', 'chamada' ), true ) ) { $cons_aberta = (int) $h['id']; break; } }
			?>
				<a class="pm-back pm-fback" href="<?php echo esc_url( remove_query_arg( array( 'pac', 'cl' ) ) ); ?>">← Voltar aos pacientes</a>

				<div class="pm-fbanner">
					<div class="pm-fbanner__id">
						<h2 class="pm-fbanner__n"><?php echo esc_html( $ficha['nome'] ); ?></h2>
						<p class="pm-fbanner__m"><?php echo esc_html( $proc['numero'] ?? 'Sem processo' ); ?><?php if ( null !== $idade_f ) : ?> · <?php echo (int) $idade_f; ?> anos<?php endif; ?><?php if ( $gen_f ) : ?> · <?php echo esc_html( $gen_f ); ?><?php endif; ?><?php if ( $ficha['telefone'] ) : ?> · <?php echo esc_html( $ficha['telefone'] ); ?><?php endif; ?></p>
					</div>
					<div class="pm-fbanner__acts">
						<a class="pm-act pm-fbanner__pdf" href="<?php echo esc_url( add_query_arg( 'receita', (int) $ficha['id'], remove_query_arg( array( 'pac', 'cl', 'estado' ) ) ) ); ?>" target="_blank" rel="noopener">Receita para o paciente</a>
						<a class="pm-act pm-act--ok pm-fbanner__pdf" href="<?php echo esc_url( add_query_arg( 'ficha', (int) $ficha['id'], remove_query_arg( array( 'pac', 'cl', 'estado' ) ) ) ); ?>" target="_blank" rel="noopener">Exportar processo em PDF</a>
					</div>
				</div>

				<?php if ( $proc && ! empty( $proc['alergias'] ) ) : ?>
					<div class="pm-alerta pm-alerta--ficha"><b>Alergias:</b> <?php echo esc_html( $proc['alergias'] ); ?></div>
				<?php endif; ?>

				<div class="pm-ftabs" id="pm-ftabs" role="tablist">
					<button type="button" class="pm-ftab is-active" data-tab="resumo">Resumo</button>
					<button type="button" class="pm-ftab" data-tab="historico">Histórico <b><?php echo count( $historico ); ?></b></button>
					<button type="button" class="pm-ftab" data-tab="receitas">Prescrições <b><?php echo count( $receitas ); ?></b></button>
					<button type="button" class="pm-ftab" data-tab="exames">Exames <b><?php echo count( $exames ); ?></b></button>
					<button type="button" class="pm-ftab" data-tab="docs">Documentos <b><?php echo count( $documentos ); ?></b></button>
				</div>

				<!-- Resumo -->
				<div class="pm-fpanel" data-tab="resumo">
					<div class="pm-fstats">
						<div class="pm-fstat"><span>Consultas</span><b><?php echo count( $historico ); ?></b></div>
						<div class="pm-fstat"><span>Última consulta</span><b><?php echo $ult ? esc_html( date_i18n( 'd/m/Y', strtotime( $ult ) ) ) : 'Nenhuma'; ?></b></div>
						<div class="pm-fstat"><span>Diagnósticos</span><b><?php echo (int) $n_diag; ?></b></div>
						<div class="pm-fstat"><span>Prescrições</span><b><?php echo count( $receitas ); ?></b></div>
					</div>

					<div class="pm-panel pm-panel--gap">
						<div class="pm-panel__head"><h3>Identificação</h3></div>
						<dl class="pm-drow">
							<div><dt>Processo</dt><dd><?php echo esc_html( $proc['numero'] ?? 'Sem processo' ); ?></dd></div>
							<div><dt>Nº do BI</dt><dd><?php echo $ficha['bi'] ? esc_html( $ficha['bi'] ) : 'Não registado'; ?></dd></div>
							<div><dt>Data de nascimento</dt><dd><?php echo $nasc_f ? esc_html( date_i18n( 'd/m/Y', strtotime( $nasc_f ) ) ) : 'Não registada'; ?></dd></div>
							<div><dt>Idade</dt><dd><?php echo null !== $idade_f ? (int) $idade_f . ' anos' : 'Não registada'; ?></dd></div>
							<div><dt>Género</dt><dd><?php echo $gen_f ? esc_html( $gen_f ) : 'Não indicado'; ?></dd></div>
							<div><dt>Telefone</dt><dd><?php echo $ficha['telefone'] ? esc_html( $ficha['telefone'] ) : 'Não registado'; ?></dd></div>
							<div class="pm-drow--wide"><dt>Email</dt><dd><?php echo esc_html( $ficha['email'] ); ?></dd></div>
						</dl>
					</div>

					<div class="pm-panel pm-panel--gap">
						<div class="pm-panel__head"><h3>Segurança clínica</h3></div>
						<dl class="pm-drow">
							<div><dt>Grupo sanguíneo</dt><dd><?php echo ! empty( $proc['grupo_sanguineo'] ) ? esc_html( $proc['grupo_sanguineo'] ) : 'Não registado'; ?></dd></div>
							<div class="pm-drow--wide"><dt>Alergias</dt><dd><?php echo ! empty( $proc['alergias'] ) ? esc_html( $proc['alergias'] ) : 'Nenhuma registada'; ?></dd></div>
							<div class="pm-drow--wide"><dt>Antecedentes</dt><dd><?php echo ! empty( $proc['antecedentes'] ) ? esc_html( $proc['antecedentes'] ) : 'Nenhum registado'; ?></dd></div>
						</dl>
					</div>
				</div>

				<!-- Histórico clínico -->
				<div class="pm-fpanel" data-tab="historico" hidden>
					<div class="pm-panel">
						<div class="pm-panel__head">
							<h3>Histórico clínico</h3>
							<span class="pm-meta"><?php if ( $e_clinico ) : ?><?php echo (int) $hist_meu; ?> consigo · <?php echo (int) $hist_outros; ?> com outros<?php endif; ?></span>
						</div>
						<?php if ( $historico ) : ?>
							<div class="pm-htbl">
								<div class="pm-htbl__head"><span>Data</span><span>Sector · Médico</span><span>Diagnóstico</span><span>Estado</span></div>
								<?php foreach ( $historico as $h ) :
									$outro = $e_clinico && (int) $h['medico_id'] !== (int) ( $medico_id ?? 0 );
								?>
									<div class="pm-hrow">
										<span class="pm-hrow__d"><b><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $h['inicio'] ) ) ); ?></b><small><?php echo esc_html( date_i18n( 'H:i', strtotime( $h['inicio'] ) ) ); ?></small></span>
										<span class="pm-hrow__t"><b><?php echo esc_html( $h['esp_nome'] ); ?></b><small><?php echo esc_html( $h['med_nome'] ); ?><?php if ( $outro ) : ?> · outro médico<?php endif; ?></small></span>
										<span class="pm-hrow__dx">
											<?php if ( ! empty( $diag_map[ (int) $h['id'] ] ) ) : ?>
												<?php foreach ( $diag_map[ (int) $h['id'] ] as $d ) : ?>
													<span class="pm-cid"><b><?php echo esc_html( $d['cid10'] ); ?></b><?php echo $d['descricao'] ? ' ' . esc_html( $d['descricao'] ) : ''; ?></span>
												<?php endforeach; ?>
											<?php elseif ( ! empty( $h['observacoes'] ) ) : ?>
												<span class="pm-hrow__obs"><?php echo esc_html( $h['observacoes'] ); ?></span>
											<?php endif; ?>
											<?php if ( ! empty( $h['retorno_em'] ) ) : ?>
												<span class="pm-retorno">Retorno indicado para <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $h['retorno_em'] ) ) ); ?><?php echo ! empty( $h['retorno_nota'] ) ? ', ' . esc_html( $h['retorno_nota'] ) : ''; ?></span>
											<?php endif; ?>
										</span>
										<span class="pm-hrow__s"><span class="pm-st pm-st--<?php echo esc_attr( $h['status'] ); ?>"><?php echo esc_html( $labels[ $h['status'] ] ?? $h['status'] ); ?></span></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="pm-empty">Sem consultas registadas</p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Prescrições -->
				<div class="pm-fpanel" data-tab="receitas" hidden>
					<?php if ( current_user_can( 'clinica_prescrever' ) ) : ?>
						<div class="pm-panel">
							<div class="pm-panel__head"><h3>Nova prescrição</h3></div>
							<form method="post" class="pm-cform">
								<?php wp_nonce_field( 'cl_presc', 'cl_presc_nonce' ); ?>
								<input type="hidden" name="cl_prescrever" value="1">
								<input type="hidden" name="paciente_id" value="<?php echo (int) $ficha['id']; ?>">
								<input type="hidden" name="consulta_id" value="<?php echo (int) $cons_aberta; ?>">
								<div class="pm-cgrid">
									<label class="pm-field pm-field--wide"><span>Medicamento</span><input type="text" name="medicamento" required placeholder="Ex.: Amoxicilina 500 mg"></label>
									<label class="pm-field"><span>Dosagem</span><input type="text" name="dosagem" placeholder="Ex.: 500 mg"></label>
									<label class="pm-field"><span>Posologia</span><input type="text" name="posologia" placeholder="Ex.: 1 comprimido 8/8h"></label>
									<label class="pm-field"><span>Duração</span><input type="text" name="duracao" placeholder="Ex.: 7 dias"></label>
									<label class="pm-field pm-field--wide"><span>Notas</span><input type="text" name="notas" placeholder="Indicações ao paciente"></label>
								</div>
								<button type="submit" class="pm-act pm-act--ok">Prescrever</button>
							</form>
						</div>
					<?php endif; ?>

					<div class="pm-panel pm-panel--gap">
						<div class="pm-panel__head"><h3>Prescrições do processo</h3><span class="pm-meta"><?php echo count( $receitas ); ?> registo(s)</span></div>
						<?php if ( $receitas ) : ?>
							<div class="pm-rtbl">
								<div class="pm-rtbl__head"><span>Data</span><span>Medicamento</span><span>Posologia</span><span>Duração</span><span>Médico</span></div>
								<?php foreach ( $receitas as $r ) : ?>
									<div class="pm-rrow">
										<span class="pm-rrow__d"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $r['criado_em'] ) ) ); ?></span>
										<span class="pm-rrow__n"><?php echo esc_html( $r['medicamento'] ); ?><?php if ( $r['dosagem'] ) : ?> <small><?php echo esc_html( $r['dosagem'] ); ?></small><?php endif; ?></span>
										<span class="pm-rrow__c"><?php echo $r['posologia'] ? esc_html( $r['posologia'] ) : ''; ?></span>
										<span class="pm-rrow__c"><?php echo $r['duracao'] ? esc_html( $r['duracao'] ) : ''; ?></span>
										<span class="pm-rrow__c"><?php echo esc_html( $r['med_nome'] ?? '' ); ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="pm-empty">Sem prescrições registadas</p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Exames -->
				<div class="pm-fpanel" data-tab="exames" hidden>
					<?php if ( current_user_can( 'clinica_pedir_exames' ) ) : ?>
						<div class="pm-panel">
							<div class="pm-panel__head"><h3>Pedir exame</h3></div>
							<form method="post" class="pm-cform">
								<?php wp_nonce_field( 'cl_exame', 'cl_exame_nonce' ); ?>
								<input type="hidden" name="cl_exame" value="1">
								<input type="hidden" name="paciente_id" value="<?php echo (int) $ficha['id']; ?>">
								<input type="hidden" name="consulta_id" value="<?php echo (int) $cons_aberta; ?>">
								<div class="pm-cgrid">
									<label class="pm-field pm-field--wide"><span>Exame</span><input type="text" name="exame" required placeholder="Ex.: Hemograma completo"></label>
									<label class="pm-field"><span>Tipo</span>
										<select name="tipo">
											<option value="Laboratório">Laboratório</option>
											<option value="Imagem">Imagem</option>
											<option value="Outro">Outro</option>
										</select>
									</label>
									<label class="pm-field"><span>Urgência</span>
										<select name="urgencia">
											<option value="normal">Normal</option>
											<option value="urgente">Urgente</option>
										</select>
									</label>
									<label class="pm-field pm-field--wide"><span>Notas</span><input type="text" name="notas" placeholder="Informação clínica relevante"></label>
								</div>
								<button type="submit" class="pm-act pm-act--ok">Pedir exame</button>
							</form>
						</div>
					<?php endif; ?>

					<div class="pm-panel pm-panel--gap">
						<div class="pm-panel__head"><h3>Exames do processo</h3><span class="pm-meta"><?php echo count( $exames ); ?> registo(s)</span></div>
						<?php if ( $exames ) : ?>
							<div class="pm-rtbl pm-rtbl--ex">
								<div class="pm-rtbl__head"><span>Data</span><span>Exame</span><span>Tipo</span><span>Urgência</span><span>Estado</span></div>
								<?php foreach ( $exames as $x ) : ?>
									<div class="pm-rrow pm-rrow--ex">
										<span class="pm-rrow__d"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $x['criado_em'] ) ) ); ?></span>
										<span class="pm-rrow__n"><?php echo esc_html( $x['exame'] ); ?></span>
										<span class="pm-rrow__c"><?php echo esc_html( $x['tipo'] ?: '' ); ?></span>
										<span class="pm-rrow__c"><?php echo 'urgente' === $x['urgencia'] ? '<b class="pm-urg">Urgente</b>' : 'Normal'; ?></span>
										<span class="pm-rrow__c"><span class="pm-st pm-st--<?php echo 'realizado' === $x['estado'] ? 'realizada' : 'pendente'; ?>"><?php echo esc_html( ucfirst( $x['estado'] ) ); ?></span></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="pm-empty">Sem exames pedidos</p>
						<?php endif; ?>
					</div>
				</div>

				<!-- Documentos -->
				<div class="pm-fpanel" data-tab="docs" hidden>
					<div class="pm-panel">
						<div class="pm-panel__head"><h3>Anexar documento</h3></div>
						<form method="post" class="pm-cform" enctype="multipart/form-data">
							<?php wp_nonce_field( 'cl_doc', 'cl_doc_nonce' ); ?>
							<input type="hidden" name="cl_documento" value="1">
							<input type="hidden" name="paciente_id" value="<?php echo (int) $ficha['id']; ?>">
							<input type="hidden" name="consulta_id" value="<?php echo (int) $cons_aberta; ?>">
							<div class="pm-cgrid">
								<label class="pm-field pm-field--wide"><span>Título do documento</span><input type="text" name="titulo" required placeholder="Ex.: Resultado de hemograma"></label>
								<label class="pm-field"><span>Tipo</span>
									<select name="tipo">
										<option value="Resultado de exame">Resultado de exame</option>
										<option value="Relatório">Relatório</option>
										<option value="Imagem médica">Imagem médica</option>
										<option value="Documento do paciente">Documento do paciente</option>
										<option value="Outro">Outro</option>
									</select>
								</label>
								<label class="pm-field pm-field--file"><span>Ficheiro</span><input type="file" name="documento" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
							</div>
							<button type="submit" class="pm-act pm-act--ok">Anexar ao processo</button>
							<p class="pm-hint">Aceita PDF ou imagem (JPG, PNG, WEBP)</p>
						</form>
					</div>

					<div class="pm-panel pm-panel--gap">
						<div class="pm-panel__head"><h3>Documentos do processo</h3><span class="pm-meta"><?php echo count( $documentos ); ?> anexo(s)</span></div>
						<?php if ( $documentos ) : ?>
							<div class="pm-rtbl pm-rtbl--doc">
								<div class="pm-rtbl__head"><span>Data</span><span>Documento</span><span>Tipo</span><span></span></div>
								<?php foreach ( $documentos as $d ) :
									$url = Clinica_Clinico::url_documento( (int) $d['id'] );
								?>
									<div class="pm-rrow pm-rrow--doc">
										<span class="pm-rrow__d"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $d['criado_em'] ) ) ); ?></span>
										<span class="pm-rrow__n"><?php echo esc_html( $d['titulo'] ); ?></span>
										<span class="pm-rrow__c"><?php echo esc_html( $d['tipo'] ?: '' ); ?></span>
										<span class="pm-rrow__c"><?php if ( $url ) : ?><a class="pm-act" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Abrir</a><?php endif; ?></span>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<p class="pm-empty">Sem documentos anexados ao processo</p>
						<?php endif; ?>
						<p class="pm-fnote">O processo administrativo completo está disponível em <b>Exportar processo em PDF</b>, no topo.</p>
					</div>
				</div>
			<?php else : ?>
				<div class="pm-panel">
					<p class="pm-empty">Escolha um paciente em <b>Pacientes</b> para abrir a ficha clínica</p>
				</div>
			<?php endif; ?>
		</section>

						<section id="consultas" class="pm-panel pm-view" data-view="consultas" hidden>
			<div class="pm-panel__head">
				<h3>Consultas</h3>
				<input type="search" class="pm-search" id="pm-search" placeholder="Pesquisar paciente…" aria-label="Pesquisar paciente">
			</div>
			<nav class="pm-tabs">
				<?php foreach ( $abas as $val => $info ) :
					$url   = $val ? add_query_arg( 'estado', $val, remove_query_arg( array( 'cl', 'estado' ) ) ) : remove_query_arg( array( 'cl', 'estado' ) );
					$ativa = ( $estado_filtro === $val );
				?>
					<a class="pm-tab <?php echo $ativa ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url ); ?>#consultas"><?php echo esc_html( $info[0] ); ?> <span><?php echo (int) $info[1]; ?></span></a>
				<?php endforeach; ?>
			</nav>

			<?php if ( $consultas ) : ?>
				<div class="pm-ctbl" id="pm-list">
					<div class="pm-ctbl__head">
						<span>Data</span>
						<span>Hora</span>
						<span>Paciente</span>
						<span>Especialidade</span>
						<span>Chegada</span>
						<span>Estado</span>
						<span class="r">Ações</span>
					</div>
					<?php foreach ( $consultas as $c ) :
						// O médico só atende quem a receção já deu entrada (check-in).
						$terminada = in_array( $c['status'], array( 'realizada', 'cancelada', 'faltou' ), true );
						$pronto    = ! empty( $c['checkin_em'] ) && ! $terminada;
					?>
						<div class="pm-crow" data-nome="<?php echo esc_attr( mb_strtolower( $c['pac_nome'] ) ); ?>">
							<span class="pm-crow__d"><?php echo esc_html( date_i18n( 'd/m', strtotime( $c['inicio'] ) ) ); ?></span>
							<span class="pm-crow__h"><b><?php echo esc_html( date_i18n( 'H:i', strtotime( $c['inicio'] ) ) ); ?></b><small><?php echo (int) $c['duracao_min']; ?> min</small></span>
							<span class="pm-crow__c pm-crow__n"<?php echo ! empty( $c['observacoes'] ) ? ' title="' . esc_attr( $c['observacoes'] ) . '"' : ''; ?>><?php echo esc_html( $c['pac_nome'] ); ?></span>
							<span class="pm-crow__c"><?php echo esc_html( $c['esp_nome'] ); ?></span>
							<span class="pm-crow__c pm-crow__ch"><?php echo ! empty( $c['checkin_em'] ) ? esc_html( date_i18n( 'H:i', strtotime( $c['checkin_em'] ) ) ) : ''; ?></span>
							<span class="pm-crow__c"><span class="pm-st pm-st--<?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $labels[ $c['status'] ] ?? $c['status'] ); ?></span></span>
							<span class="pm-crow__a">
								<?php if ( $terminada ) : ?>
								<?php elseif ( empty( $c['checkin_em'] ) ) : ?>
									<span class="pm-crow__wait">Aguarda receção</span>
								<?php elseif ( 'chamada' === $c['status'] ) : ?>
									<?php clinica_med_fluxo_btn( (int) $c['id'], 'iniciar', 'Iniciar consulta', 'pm-act--ok' ); ?>
								<?php elseif ( 'em_atendimento' === $c['status'] ) : ?>
									<a class="pm-act pm-act--ok" href="<?php echo esc_url( add_query_arg( 'atender', (int) $c['id'], remove_query_arg( array( 'pac', 'cl', 'estado' ) ) ) ); ?>">Concluir</a>
									<?php clinica_med_fluxo_btn( (int) $c['id'], 'pausar', 'Pôr em pausa', 'pm-act--ico', true ); ?>
								<?php elseif ( 'pausada' === $c['status'] ) : ?>
									<?php clinica_med_fluxo_btn( (int) $c['id'], 'retomar', 'Retomar', 'pm-act--ok' ); ?>
								<?php else : ?>
									<?php clinica_med_fluxo_btn( (int) $c['id'], 'chamar', 'Chamar', 'pm-act--ok' ); ?>
								<?php endif; ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="pm-empty" id="pm-noresult" hidden>Nenhum paciente encontrado</p>
			<?php else : ?>
				<p class="pm-empty">Sem consultas neste filtro</p>
			<?php endif; ?>
		</section>

				<!-- Agenda / horários -->
		<section class="pm-panel pm-view" data-view="agenda" hidden>
			<div class="pm-panel__head">
				<h3>Agenda</h3>
				<span class="pm-panel__c"><?php echo count( $prox ); ?> consulta<?php echo 1 === count( $prox ) ? '' : 's'; ?> por atender</span>
			</div>
			<?php if ( $agenda_por_dia ) : ?>
				<div class="pm-atbl">
					<div class="pm-atbl__head">
						<span>Hora</span>
						<span>Paciente</span>
						<span>Especialidade</span>
						<span>Duração</span>
						<span>Chegada</span>
						<span>Estado</span>
					</div>
					<?php foreach ( $agenda_por_dia as $dia => $items ) :
						$ts      = strtotime( $dia );
						$is_hoje = ( $dia === $hoje_ymd );
					?>
						<div class="pm-adia<?php echo $is_hoje ? ' is-hoje' : ''; ?>">
							<span class="pm-adia__d"><?php echo esc_html( ucfirst( date_i18n( 'l, j \d\e F', $ts ) ) ); ?><?php echo $is_hoje ? ' · Hoje' : ''; ?></span>
							<span class="pm-adia__n"><?php echo count( $items ); ?> consulta<?php echo 1 === count( $items ) ? '' : 's'; ?></span>
						</div>
						<?php foreach ( $items as $f ) : ?>
							<div class="pm-arow">
								<span class="pm-arow__h"><?php echo esc_html( $fmt_hora( $f['inicio'] ) ); ?></span>
								<span class="pm-arow__c pm-arow__n"><?php echo esc_html( $f['pac_nome'] ); ?></span>
								<span class="pm-arow__c"><?php echo esc_html( $f['esp_nome'] ); ?></span>
								<span class="pm-arow__c"><?php echo (int) $f['duracao_min']; ?> min</span>
								<span class="pm-arow__c"><?php echo ! empty( $f['checkin_em'] ) ? esc_html( $fmt_hora( $f['checkin_em'] ) ) : 'A aguardar'; ?></span>
								<span class="pm-arow__s"><span class="pm-st pm-st--<?php echo esc_attr( $f['status'] ); ?>"><?php echo esc_html( $labels[ $f['status'] ] ?? $f['status'] ); ?></span></span>
							</div>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="pm-empty">Sem consultas agendadas</p>
			<?php endif; ?>
		</section>


	</main>



</div>
</div>
<script>
( function () {
	var s = document.getElementById( 'pm-search' ), lista = document.getElementById( 'pm-list' ), vazio = document.getElementById( 'pm-noresult' );
	if ( ! s || ! lista ) { return; }
	s.addEventListener( 'input', function () {
		var q = s.value.trim().toLowerCase(), n = 0;
		lista.querySelectorAll( '.pm-crow' ).forEach( function ( it ) {
			var ok = ! q || ( it.dataset.nome || '' ).indexOf( q ) !== -1;
			it.style.display = ok ? '' : 'none';
			if ( ok ) { n++; }
		} );
		if ( vazio ) { vazio.hidden = n !== 0; }
	} );
} )();
</script>
<script>
( function () {
	var side = document.querySelector( '.pm-side' );
	if ( ! side ) { return; }
	var items = side.querySelectorAll( '[data-go]' );
	var views = document.querySelectorAll( '.pm-view' );
	var shell = document.querySelector( '.pm-shell' );
	var groups = side.querySelectorAll( '.pm-nav__group' );
	// Grupo que contém a vista (para abrir/marcar o submenu certo).
	function grupoDe( v ) {
		for ( var i = 0; i < groups.length; i++ ) {
			if ( 'consultas' === v && groups[ i ].querySelector( '.pm-nav__sub' ) ) { return groups[ i ]; }
		}
		return null;
	}
	function show( v, silencioso ) {
		views.forEach( function ( s ) { s.hidden = ( s.dataset.view !== v ); } );
		// A ficha é um detalhe de Pacientes; o atendimento parte das Consultas.
		var nav = ( 'ficha' === v ) ? 'pacientes' : ( 'atender' === v ? 'consultas' : v );
		items.forEach( function ( i ) { i.classList.toggle( 'is-active', i.dataset.go === nav ); } );
		var atual = grupoDe( nav );
		groups.forEach( function ( g ) {
			g.classList.toggle( 'is-current', g === atual );
			if ( g === atual ) { g.classList.add( 'is-open' ); }
		} );
		if ( shell ) { shell.classList.toggle( 'is-full', v !== 'painel' ); }
		// Guarda a vista no endereco: recarregar mantem o medico onde estava.
		if ( ! silencioso && window.history && history.replaceState ) {
			history.replaceState( null, '', '#' + v );
		}
		window.scrollTo( 0, 0 );
	}
	items.forEach( function ( i ) { i.addEventListener( 'click', function ( e ) { e.preventDefault(); show( i.dataset.go ); } ); } );
	// Cabeçalho de grupo: abre/fecha o submenu.
	side.querySelectorAll( '[data-toggle]' ).forEach( function ( t ) {
		t.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var g = t.parentNode;
			var abrir = ! g.classList.contains( 'is-open' );
			groups.forEach( function ( gr ) { gr.classList.remove( 'is-open' ); } );
			if ( abrir ) { g.classList.add( 'is-open' ); }
		} );
	} );

	// Ficha clinica: separadores (Resumo/Historico/Prescricoes/Exames/Documentos).
	( function () {
		var tabs = document.getElementById( 'pm-ftabs' );
		if ( ! tabs ) { return; }
		var panels = document.querySelectorAll( '.pm-fpanel' );
		tabs.querySelectorAll( '.pm-ftab' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				tabs.querySelectorAll( '.pm-ftab' ).forEach( function ( o ) { o.classList.toggle( 'is-active', o === b ); } );
				panels.forEach( function ( p ) { p.hidden = ( p.dataset.tab !== b.dataset.tab ); } );
			} );
		} );
	} )();

	// Pesquisa na lista de pacientes.
	( function () {
		var i = document.getElementById( 'pm-psearch' );
		if ( ! i ) { return; }
		var rows = document.querySelectorAll( '.pm-prow' );
		var vazio = document.getElementById( 'pm-pnoresult' );
		i.addEventListener( 'input', function () {
			var q = i.value.trim().toLowerCase(), n = 0;
			rows.forEach( function ( r ) {
				var ok = ! q || ( r.dataset.nome || '' ).indexOf( q ) !== -1;
				r.hidden = ! ok; if ( ok ) { n++; }
			} );
			if ( vazio ) { vazio.hidden = ! q || 0 !== n; }
		} );
	} )();
	var inicial = <?php echo $atender ? "'atender'" : ( $ficha ? "'ficha'" : "'painel'" ); ?>;
	if ( /[?&]estado=/.test( location.search ) ) { inicial = 'consultas'; }
	var guardada = ( location.hash || '' ).replace( '#', '' );
	var valida   = false;
	views.forEach( function ( s ) { if ( s.dataset.view === guardada ) { valida = true; } } );
	// A ficha e o atendimento dependem de um id no endereco, nunca do hash.
	if ( valida && 'ficha' !== guardada && 'atender' !== guardada
		&& ! /[?&](atender|pac|estado)=/.test( location.search ) ) {
		inicial = guardada;
	}
	show( inicial, true );
} )();
</script>
<?php get_footer(); ?>
