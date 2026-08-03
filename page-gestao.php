<?php
/**
 * Template Name: Clínica — Gestão
 *
 * Painel de Administração (app com sidebar): dashboard + equipa médica +
 * especialidades + agenda + definições. Acesso por capability. Backend:
 * inc/gestao.php. Reutiliza o shell do painel (classes .pm-*).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() || ! current_user_can( 'clinica_gerir_especialidades' ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

clinica_controller_gestao();
clinica_controller_agenda(); // horários/folgas/feriados (verifica a própria capability)
clinica_controller_direcao(); // propostas à Direção (só o ramo 'propor' passa a capability da gestão)

get_header();

$utilizador     = wp_get_current_user();
$avatar         = function_exists( 'clinica_avatar_url' ) ? clinica_avatar_url( $utilizador->ID, 96 ) : get_avatar_url( $utilizador->ID );
$especialidades = clinica_gestao_especialidades();
$medicos        = clinica_gestao_medicos();
$propostas      = class_exists( 'Clinica_Direcao' ) ? Clinica_Direcao::aprovacoes() : array();
$pode_propor    = current_user_can( 'clinica_propor_estrategico' );
$prop_labels    = array( 'pendente' => 'Pendente', 'aprovado' => 'Aprovada', 'rejeitado' => 'Rejeitada' );
$esp_ativas     = array_filter( $especialidades, static function ( $e ) { return (int) $e['ativo'] === 1; } );
$med_ativos     = array_filter( $medicos, static function ( $m ) { return (int) $m['ativo'] === 1; } );
$cont_med       = Clinica_Repo::contar_medicos_por_especialidade();
$kpis           = Clinica_Repo::contagem_por_estado();
$consultas      = Clinica_Repo::consultas_todas();
$pode_agenda    = current_user_can( 'clinica_gerir_agenda' );
$pode_config    = current_user_can( 'clinica_configurar' );

// Saudação.
$agora    = current_time( 'timestamp' );
$hora_dia = (int) date_i18n( 'G', $agora );
$saudacao = $hora_dia < 12 ? 'Bom dia' : ( $hora_dia < 19 ? 'Boa tarde' : 'Boa noite' );
$primeiro = explode( ' ', trim( $utilizador->display_name ) )[0];
$data_hoje = date_i18n( 'l, j \d\e F', $agora );
$hoje_ymd  = date_i18n( 'Y-m-d', $agora );

$labels   = array( 'pendente' => 'Pendente', 'confirmada' => 'Confirmada', 'realizada' => 'Realizada', 'cancelada' => 'Cancelada', 'faltou' => 'Faltou' );
$fmt_hora = static function ( $iso ) { return date_i18n( 'H:i', strtotime( $iso ) ); };
// Título por género (heurística PT: primeiro nome terminado em 'a' -> feminino).
$titulo = static function ( $nome ) {
	$prim = explode( ' ', trim( (string) preg_replace( '/\b(Dra?|Dr\(a\))\.?\s*/i', '', $nome ) ) )[0];
	return ( 'a' === mb_strtolower( mb_substr( $prim, -1 ) ) ) ? 'Dra.' : 'Dr.';
};

// Próximas consultas de toda a clínica (reais) — alimentam a agenda e o "hoje".
$futuras = array_values( array_filter( $consultas, static function ( $c ) { return strtotime( $c['inicio'] ) >= time() && in_array( $c['status'], array( 'pendente', 'confirmada' ), true ); } ) );
usort( $futuras, static function ( $a, $b ) { return strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] ); } );
$hoje_prox = array_values( array_filter( $futuras, static function ( $c ) use ( $hoje_ymd ) { return substr( $c['inicio'], 0, 10 ) === $hoje_ymd; } ) );
$agenda_por_dia = array();
foreach ( $futuras as $f ) {
	$agenda_por_dia[ substr( $f['inicio'], 0, 10 ) ][] = $f;
}

// Agregados do overview: financeiro, atividade recente e sinais de atenção.
// Níveis TÁTICO e EXECUTIVO: agregados de cache (§4 — não batem no transacional).
$exec         = class_exists( 'Clinica_Analitica' ) ? Clinica_Analitica::executivo() : array( 'totais' => null, 'receita_esp' => array(), 'tendencia' => array(), 'em' => 0 );
$tat          = class_exists( 'Clinica_Analitica' ) ? Clinica_Analitica::tatico() : array( 'por_especialidade' => array(), 'no_show_medico' => array(), 'em' => 0 );
$pag          = $exec['totais'] ?: ( class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::totais() : array( 'pago_valor' => 0.0, 'pendente_valor' => 0.0, 'pago_n' => 0, 'pendente_n' => 0 ) );
$moeda        = class_exists( 'Clinica_Config' ) ? Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';
$audit        = class_exists( 'Clinica_Auditoria' ) ? Clinica_Auditoria::listar( 6 ) : array();
$med_inativos = count( $medicos ) - count( $med_ativos );
$fmt_valor    = static function ( $v ) use ( $moeda ) { return number_format( (float) $v, 0, ',', ' ' ) . ' ' . $moeda; };
// Taxa de faltas (no-show) sobre as consultas já encerradas.
$encerradas   = (int) ( $kpis['realizada'] + $kpis['faltou'] );
$taxa_faltas  = $encerradas > 0 ? (int) round( $kpis['faltou'] / $encerradas * 100 ) : 0;

// KPIs do dashboard.
$total_consultas = array_sum( $kpis );

// ---- Gestão de Horários (sub-vista "horarios") — dados por médico selecionado ----
global $wpdb;
$ag_dias     = array( 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo' );
$ag_meds     = array();
$ag_sel      = 0;
$ag_sel_med  = null;
$ag_por_dia  = array_fill( 1, 7, array() );
$ag_bloqs    = array();
$ag_feriados = array();
if ( $pode_agenda ) {
	$ag_meds = $wpdb->get_results(
		"SELECT md.id, md.sala, u.ID AS user_id, u.display_name AS nome, e.nome AS esp
		 FROM " . Clinica_Repo::T_MEDICOS . " md
		 JOIN {$wpdb->users} u ON md.user_id = u.ID
		 JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
		 WHERE md.ativo = 1 ORDER BY u.display_name",
		ARRAY_A
	) ?: array();
	$ag_sel = Clinica_Input::inteiro( $_GET, 'medico' );
	if ( ! $ag_sel && $ag_meds ) { $ag_sel = (int) $ag_meds[0]['id']; }
	foreach ( $ag_meds as $m ) { if ( (int) $m['id'] === $ag_sel ) { $ag_sel_med = $m; } }
	foreach ( ( $ag_sel ? Clinica_Agenda::horarios_medico( $ag_sel ) : array() ) as $h ) {
		$ag_por_dia[ (int) $h['dia_semana'] ][] = $h;
	}
	$ag_bloqs    = array_filter( Clinica_Agenda::bloqueios_todos(), static function ( $b ) use ( $ag_sel ) { return (int) $b['medico_id'] === $ag_sel; } );
	$ag_feriados = Clinica_Agenda::feriados();
}
$ag_nome  = static function ( $n ) { return function_exists( 'clinica_nome_medico' ) ? clinica_nome_medico( $n ) : $n; };
$ag_hhmm  = static function ( $t ) { return substr( (string) $t, 0, 5 ); };
$ag_fdata = static function ( $d ) { return date_i18n( 'd/m/Y', strtotime( $d ) ); };
$ag_base  = remove_query_arg( array( 'cl' ) );

?>
<div class="hp">
<div class="pm-shell pm-shell--admin">

	<aside class="pm-side">
		<div class="pm-side__brand">
			<span class="pm-side__logo"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo2.png' ); ?>" alt=""></span>
			<span class="pm-side__brandname">Clínica Vida</span>
		</div>

		<div class="pm-side__me">
			<img src="<?php echo esc_url( $avatar ); ?>" alt="">
			<div class="pm-side__meinfo">
				<div class="pm-side__name"><?php echo esc_html( $primeiro ); ?></div>
				<div class="pm-side__role">Administração</div>
			</div>
		</div>

		<nav class="pm-nav">
			<span class="pm-nav__cap">Gestão</span>
			<a class="pm-nav__i is-active" href="#" data-go="painel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Painel</span></a>
			<div class="pm-nav__group">
				<a class="pm-nav__i" href="#" data-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg><span>Equipa médica</span><i class="pm-nav__chev" aria-hidden="true"></i></a>
				<div class="pm-nav__sub">
					<a class="pm-nav__subi" href="#" data-go="equipa">Todos os médicos</a>
					<a class="pm-nav__subi" href="#" data-go="novo-medico">Adicionar médico</a>
				</div>
			</div>
			<div class="pm-nav__group">
				<a class="pm-nav__i" href="#" data-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg><span>Especialidades</span><i class="pm-nav__chev" aria-hidden="true"></i></a>
				<div class="pm-nav__sub">
					<a class="pm-nav__subi" href="#" data-go="especialidades">Todas as especialidades</a>
					<a class="pm-nav__subi" href="#" data-go="nova-especialidade">Nova especialidade</a>
				</div>
			</div>
			<div class="pm-nav__group">
				<a class="pm-nav__i" href="#" data-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg><span>Agenda</span><i class="pm-nav__chev" aria-hidden="true"></i></a>
				<div class="pm-nav__sub">
					<a class="pm-nav__subi" href="#" data-go="agenda">Agenda da clínica</a>
					<a class="pm-nav__subi" href="#" data-go="horarios">Horários e folgas</a>
				</div>
			</div>
			<div class="pm-nav__group">
				<a class="pm-nav__i" href="#" data-toggle><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg><span>Finanças</span><i class="pm-nav__chev" aria-hidden="true"></i></a>
				<div class="pm-nav__sub">
					<a class="pm-nav__subi" href="#" data-go="financas">Receita da clínica</a>
				</div>
			</div>
			<?php if ( $pode_propor ) : ?>
				<a class="pm-nav__i" href="#" data-go="propostas"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 19V5M5 12l7-7 7 7"/></svg><span>Propor à Direção</span><?php $np = 0; foreach ( $propostas as $pp ) { if ( 'pendente' === $pp['estado'] ) { $np++; } } if ( $np > 0 ) : ?><span class="pm-nav__badge"><?php echo (int) $np; ?></span><?php endif; ?></a>
			<?php endif; ?>
			<a class="pm-nav__i" href="#" data-go="auditoria"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Auditoria</span></a>
			<?php if ( $pode_config ) : ?>
				<a class="pm-nav__i" href="#" data-go="definicoes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Definições</span></a>
			<?php endif; ?>
		</nav>

		<a class="pm-nav__i pm-nav__out" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg><span>Sair</span></a>
	</aside>

	<main class="pm-main">

		<header class="pm-top">
			<div class="pm-top__l">
				<span class="ad-eyebrow">Painel de Administração</span>
				<h1 class="pm-welcome"><?php echo esc_html( $saudacao . ', ' . $primeiro ); ?></h1>
				<p class="pm-date"><?php echo esc_html( ucfirst( $data_hoje ) ); ?></p>
			</div>
			<div class="pm-user">
				<span class="pm-user__meta"><b><?php echo esc_html( $utilizador->display_name ); ?></b><small>Administração</small></span>
			</div>
		</header>

		<?php clinica_render_flash(); ?>

		<!-- ============================ PAINEL ============================
		     Três camadas num painel só. Cada bloco diz a que camada pertence,
		     porque a cadência de atualização é diferente em cada uma:
		     operacional 2 min, tático 1 hora, executivo 1 dia.
		     ================================================================ -->
		<?php
		$oper = Clinica_Analitica::operacional();
		$vol  = Clinica_Analitica::series_volume();

		// Um painel vazio não diz nada a quem gere. Quando ainda não há
		// atividade suficiente, os blocos passam a mostrar uma amostra, sempre
		// assinalada no cabeçalho para nunca se confundir com o real.
		$ativos = 0;
		foreach ( $vol['mensal']['consultas'] as $n ) {
			if ( $n > 0 ) {
				$ativos++;
			}
		}
		$demo_vol = $ativos < 3;
		if ( $demo_vol ) {
			$vol['mensal']['consultas']  = array( 186, 164, 203, 178, 221, 195, 238, 210, 246, 229, 258, 241 );
			$vol['mensal']['exames']     = array( 74, 62, 88, 71, 96, 83, 104, 91, 108, 97, 115, 106 );
			$vol['semanal']['consultas'] = array( 48, 55, 43, 61, 52, 68, 57, 64, 49, 72, 60, 66 );
			$vol['semanal']['exames']    = array( 19, 24, 17, 28, 22, 31, 25, 29, 20, 33, 26, 30 );
			$vol['anual']['consultas']   = array( 1420, 1685, 1930, 2246, 2569 );
			$vol['anual']['exames']      = array( 540, 663, 792, 948, 1105 );
		}

		$demo_dia = 0 === (int) $oper['consultas']['hoje'];
		if ( $demo_dia ) {
			$oper['ocupacao']     = 78;
			$oper['consultas']    = array( 'hoje' => 38, 'semana' => $oper['consultas']['semana'], 'mes' => $oper['consultas']['mes'] );
			$oper['atendidos']    = 24;
			$oper['espera']       = 5;
			$oper['a_decorrer']   = 3;
			$oper['espera_min']   = 14;
			$oper['consulta_min'] = 26;
			$oper['canceladas']   = 2;
			$oper['faltas']       = 1;
		}

		// Variação face ao mês anterior. Sem base de comparação não se inventa
		// uma percentagem.
		$var = static function ( $agora, $antes ) {
			if ( $antes <= 0 ) {
				return null;
			}
			return round( ( $agora - $antes ) / $antes * 100 );
		};
		?>

		<section class="ad-kpis pm-view" data-view="painel">
			<?php
			// A ligação diz para onde vai. Onde ainda não existe destino, não
			// se põe ligação nenhuma: um botão que promete um relatório e abre
			// outra coisa é pior do que não ter botão.
			$kp = array(
				array(
					'l' => 'Pacientes registados',
					'n' => number_format_i18n( $oper['pacientes'] ),
					'v' => $var( $oper['pacientes'], $oper['anterior']['pacientes'] ),
					'p' => 'Total acumulado',
				),
				array(
					'l'  => 'Consultas do mês',
					'n'  => number_format_i18n( $oper['consultas']['mes'] ),
					'v'  => $var( $oper['consultas']['mes'], $oper['anterior']['consultas'] ),
					'p'  => ucfirst( date_i18n( 'F', $agora ) ),
					'go' => 'agenda',
					'a'  => 'Ver agenda',
				),
				array(
					'l' => 'Exames pedidos',
					'n' => number_format_i18n( $oper['exames_mes'] ),
					'v' => $var( $oper['exames_mes'], $oper['anterior']['exames'] ),
					'p' => ucfirst( date_i18n( 'F', $agora ) ),
				),
				array(
					'l'  => 'Receita do mês',
					'n'  => $fmt_valor( $oper['receita']['mes'] ),
					'v'  => $var( $oper['receita']['mes'], $oper['anterior']['receita'] ),
					'p'  => ucfirst( date_i18n( 'F', $agora ) ),
					'go' => 'financas',
					'a'  => 'Ver finanças',
					'sm' => true,
				),
			);
			foreach ( $kp as $k ) : ?>
				<div class="ad-kpi">
					<div class="ad-kpi__t">
						<span class="ad-kpi__l"><?php echo esc_html( $k['l'] ); ?></span>
						<span class="ad-kpi__p"><?php echo esc_html( $k['p'] ); ?></span>
					</div>
					<strong class="ad-kpi__n<?php echo ! empty( $k['sm'] ) ? ' ad-kpi__n--sm' : ''; ?> ad-num" data-final="<?php echo esc_attr( $k['n'] ); ?>"><?php echo esc_html( $k['n'] ); ?></strong>
					<div class="ad-kpi__f">
						<?php if ( null === $k['v'] ) : ?>
							<span class="ad-trend">Primeiro mês de registo</span>
						<?php else : ?>
							<span class="ad-trend">
								<b class="ad-trend--<?php echo $k['v'] >= 0 ? 'up' : 'down'; ?>"><?php echo $k['v'] >= 0 ? '+' : '−'; ?><?php echo abs( (int) $k['v'] ); ?>%</b>
								face ao mês anterior
							</span>
						<?php endif; ?>
						<?php if ( ! empty( $k['go'] ) ) : ?>
							<a class="ad-kpi__a" href="#" data-go="<?php echo esc_attr( $k['go'] ); ?>"><?php echo esc_html( $k['a'] ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</section>

		<!-- Volume e procura: camada tática -->
		<section class="ad-charts pm-view" data-view="painel">
			<div class="ad-card">
				<div class="ad-card__h">
					<strong>Atividade</strong>
					<span class="ad-legend">
						<i class="ad-dot ad-dot--a"></i><span>Consultas</span>
						<i class="ad-dot ad-dot--b"></i><span>Exames</span>
					</span>
					<?php if ( $demo_vol ) : ?><span class="ad-card__s">Dados de exemplo</span><?php endif; ?>
					<div class="ad-tabs" id="ad-tabs">
						<button type="button" class="ad-tab" data-esc="semanal">Semanal</button>
						<button type="button" class="ad-tab is-active" data-esc="mensal">Mensal</button>
						<button type="button" class="ad-tab" data-esc="anual">Anual</button>
					</div>
				</div>
				<p class="ad-read" id="ad-read"></p>
				<div class="ad-plot" id="ad-plot" data-series="<?php echo esc_attr( wp_json_encode( array(
					'semanal' => $vol['semanal'],
					'mensal'  => $vol['mensal'],
					'anual'   => $vol['anual'],
				) ) ); ?>">
					<div class="ad-plot__y" id="ad-y"></div>
					<div class="ad-plot__c">
						<div class="ad-plot__bars" id="ad-bars"></div>
						<div class="ad-plot__x" id="ad-x"></div>
					</div>
				</div>
			</div>

			<div class="ad-card">
				<?php
				$esp_top = array_slice( $tat['por_especialidade'], 0, 4 );
				$esp_tot = 0;
				foreach ( $esp_top as $e ) {
					$esp_tot += (int) $e['total'];
				}
				// Um donut com um só sector não mostra distribuição nenhuma.
				$demo_esp = count( $esp_top ) < 2 || $esp_tot < 8;
				if ( $demo_esp ) {
					$esp_top = array(
						array( 'esp' => 'Clínica Geral', 'total' => 148 ),
						array( 'esp' => 'Pediatria', 'total' => 96 ),
						array( 'esp' => 'Cardiologia', 'total' => 71 ),
						array( 'esp' => 'Dermatologia', 'total' => 44 ),
					);
					$esp_tot = 359;
				}
				$cores = array( '#33566B', '#4E7E9B', '#7BA7C1', '#C3D6E2' );
				?>
				<div class="ad-card__h">
					<strong>Especialidades mais procuradas</strong>
					<span class="ad-card__s"><?php echo $demo_esp ? 'Dados de exemplo' : 'Desde o início'; ?></span>
				</div>
				<div class="ad-donut-wrap">
					<div class="ad-donut">
						<?php
						// Anel em SVG: cada sector é um arco próprio, e por isso
						// pode desenhar-se sozinho. O perímetro é 100, para as
						// percentagens entrarem diretas no stroke-dasharray.
						// Começa a zero: quem roda o anel para o topo é o
						// transform do SVG, não o deslocamento.
						$off = 0;
						?>
						<svg class="ad-ring" viewBox="0 0 42 42" role="img" aria-label="Distribuição por especialidade">
							<circle class="ad-ring__bg" cx="21" cy="21" r="15.9155"></circle>
							<?php foreach ( $esp_top as $i => $e ) :
								$pct  = (int) $e['total'] / $esp_tot * 100;
								$dash = round( $pct, 2 );
								$off -= $pct;
							?>
								<circle class="ad-ring__s" data-seg="<?php echo (int) $i; ?>"
									cx="21" cy="21" r="15.9155"
									stroke="<?php echo esc_attr( $cores[ $i % 4 ] ); ?>"
									stroke-dasharray="0 100"
									data-dash="<?php echo esc_attr( $dash . ' ' . round( 100 - $dash, 2 ) ); ?>"
									stroke-dashoffset="<?php echo esc_attr( (string) round( $off + $pct, 2 ) ); ?>"></circle>
							<?php endforeach; ?>
						</svg>
						<div class="ad-donut__c">
							<span class="ad-num" data-n="<?php echo (int) $esp_tot; ?>">0</span>
							<small>consultas</small>
						</div>
					</div>
					<ul class="ad-legend2">
						<?php foreach ( $esp_top as $i => $e ) : ?>
							<li data-seg="<?php echo (int) $i; ?>">
								<i class="ad-dot" style="background:<?php echo esc_attr( $cores[ $i % 4 ] ); ?>"></i>
								<span><?php echo esc_html( $e['esp'] ); ?></span>
								<b><?php echo esc_html( number_format( (int) $e['total'] / $esp_tot * 100, 1, ',', '' ) ); ?>%</b>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</section>

		<!-- O dia e quem o está a fazer -->
		<section class="ad-grid2 pm-view" data-view="painel">
			<div class="ad-card">
				<div class="ad-card__h"><strong>Operação de hoje</strong><span class="ad-card__s"><?php echo $demo_dia ? "Dados de exemplo" : "Atualiza a cada 2 minutos"; ?></span></div>
				<div class="ad-gauge">
					<div class="ad-gauge__n"><span class="ad-num" data-n="<?php echo (int) $oper['ocupacao']; ?>">0</span>%</div>
					<div class="ad-gauge__l">ocupação dos <?php echo (int) $oper['medicos_ativos']; ?> médicos ativos</div>
					<div class="ad-gauge__bar"><span style="width:<?php echo (int) $oper['ocupacao']; ?>%"></span></div>
				</div>
				<dl class="ad-dl">
					<div><dt>Consultas hoje</dt><dd><?php echo (int) $oper['consultas']['hoje']; ?></dd></div>
					<div><dt>Já atendidos</dt><dd><?php echo (int) $oper['atendidos']; ?></dd></div>
					<div><dt>Em espera</dt><dd><?php echo (int) $oper['espera']; ?></dd></div>
					<div><dt>A decorrer</dt><dd><?php echo (int) $oper['a_decorrer']; ?></dd></div>
					<div><dt>Tempo de espera</dt><dd><?php echo (int) $oper['espera_min']; ?> min</dd></div>
					<div><dt>Duração média</dt><dd><?php echo (int) $oper['consulta_min']; ?> min</dd></div>
					<div><dt>Canceladas</dt><dd><?php echo (int) $oper['canceladas']; ?></dd></div>
					<div><dt>Faltas</dt><dd><?php echo (int) $oper['faltas']; ?></dd></div>
				</dl>
			</div>

			<div class="ad-card">
				<div class="ad-card__h"><strong>Médicos em atividade</strong><span class="ad-card__s"><?php echo $demo_dia ? "Dados de exemplo" : "Hoje"; ?></span></div>
				<?php
				// Carga de hoje por médico, a partir das consultas já carregadas.
				$carga = array();
				foreach ( $consultas as $c ) {
					if ( substr( $c['inicio'], 0, 10 ) !== $hoje_ymd ) {
						continue;
					}
					$n = $c['med_nome'];
					if ( ! isset( $carga[ $n ] ) ) {
						$carga[ $n ] = array( 'n' => 0, 'ativo' => false );
					}
					$carga[ $n ]['n']++;
					if ( in_array( $c['status'], array( 'em_atendimento', 'pausada', 'chamada' ), true ) ) {
						$carga[ $n ]['ativo'] = true;
					}
				}
				$lista_med = array_slice( $med_ativos, 0, 6 );
				// Com o dia em amostra, a carga por médico acompanha, senão o
				// cartão dizia 38 consultas e zero em cada médico.
				if ( $demo_dia ) {
					$amostra = array( array( 9, true ), array( 7, true ), array( 8, false ), array( 6, true ), array( 5, false ), array( 3, false ) );
					$carga   = array();
					foreach ( $lista_med as $i => $m ) {
						$carga[ $m['nome'] ] = array( 'n' => $amostra[ $i % 6 ][0], 'ativo' => $amostra[ $i % 6 ][1] );
					}
				}
				?>
				<?php if ( $lista_med ) : ?>
					<ul class="ad-docs">
						<?php foreach ( $lista_med as $m ) :
							$info   = $carga[ $m['nome'] ] ?? array( 'n' => 0, 'ativo' => false );
							$estado = $info['ativo'] ? 'Em atendimento' : ( $info['n'] > 0 ? 'Com agenda' : 'Disponível' );
							$cls    = $info['ativo'] ? 'on' : ( $info['n'] > 0 ? 'busy' : 'free' );
						?>
							<li class="ad-doc">
								<div class="ad-doc__i">
									<span class="ad-doc__n"><?php echo esc_html( $m['nome'] ); ?></span>
									<span class="ad-doc__e"><?php echo esc_html( $m['esp_nome'] ?? '' ); ?></span>
								</div>
								<span class="ad-doc__p"><?php echo (int) $info['n']; ?> hoje</span>
								<span class="ad-doc__s ad-doc__s--<?php echo $cls; ?>"><?php echo esc_html( $estado ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="pm-empty">Sem médicos ativos</p>
				<?php endif; ?>
			</div>
		</section>
		<!-- ============================ FINANÇAS ============================
		     Receita consolidada. Camada executiva: o valor vem dos agregados
		     em cache, nunca de um SUM ao vivo sobre os pagamentos.
		     ================================================================= -->
		<section class="pm-view" data-view="financas" hidden>

			<section class="ad-kpis">
				<?php
				$fin = array(
					array( 'Receita de hoje', $fmt_valor( $oper['receita']['dia'] ), 'pagamentos confirmados hoje' ),
					array( 'Receita da semana', $fmt_valor( $oper['receita']['semana'] ), 'semana em curso' ),
					array( 'Receita do mês', $fmt_valor( $oper['receita']['mes'] ), 'mês em curso' ),
					array( 'Por cobrar', $fmt_valor( $pag['pendente_valor'] ), (int) $pag['pendente_n'] . ' pagamentos pendentes' ),
				);
				foreach ( $fin as $c ) : ?>
					<div class="ad-kpi">
						<span class="ad-kpi__l"><?php echo esc_html( $c[0] ); ?></span>
						<strong class="ad-kpi__n ad-kpi__n--sm"><?php echo esc_html( $c[1] ); ?></strong>
						<div class="ad-kpi__f"><span class="ad-trend"><?php echo esc_html( $c[2] ); ?></span></div>
					</div>
				<?php endforeach; ?>
			</section>

			<section class="ad-charts">
				<div class="ad-card">
					<div class="ad-card__h"><strong>Receita por especialidade</strong></div>
					<?php if ( $exec['receita_esp'] ) : ?>
						<?php
						$rmax = 0;
						foreach ( $exec['receita_esp'] as $r ) {
							$rmax = max( $rmax, (float) $r['receita'] );
						}
						$rmax = max( 1, $rmax );
						?>
						<ul class="ad-bars">
							<?php foreach ( $exec['receita_esp'] as $r ) : ?>
								<li>
									<span class="ad-bars__l"><?php echo esc_html( $r['esp'] ); ?></span>
									<span class="ad-bars__t"><span style="width:<?php echo (int) round( (float) $r['receita'] / $rmax * 100 ); ?>%"></span></span>
									<b class="ad-bars__v"><?php echo esc_html( $fmt_valor( $r['receita'] ) ); ?></b>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="pm-empty">Ainda não há pagamentos confirmados</p>
					<?php endif; ?>
				</div>

				<div class="ad-card">
					<div class="ad-card__h"><strong>Cobrança</strong></div>
					<?php
					$total_fat = (float) $pag['pago_valor'] + (float) $pag['pendente_valor'];
					$pct_pago  = $total_fat > 0 ? round( (float) $pag['pago_valor'] / $total_fat * 100 ) : 0;
					?>
					<div class="ad-gauge">
						<div class="ad-gauge__n"><?php echo (int) $pct_pago; ?>%</div>
						<div class="ad-gauge__l">do faturado já foi cobrado</div>
						<div class="ad-gauge__bar"><span style="width:<?php echo (int) $pct_pago; ?>%"></span></div>
					</div>
					<dl class="ad-dl">
						<div><dt>Recebido</dt><dd><?php echo esc_html( $fmt_valor( $pag['pago_valor'] ) ); ?></dd></div>
						<div><dt>Pagamentos</dt><dd><?php echo (int) $pag['pago_n']; ?></dd></div>
						<div><dt>Por cobrar</dt><dd><?php echo esc_html( $fmt_valor( $pag['pendente_valor'] ) ); ?></dd></div>
						<div><dt>Pendentes</dt><dd><?php echo (int) $pag['pendente_n']; ?></dd></div>
					</dl>
				</div>
			</section>
		</section>
		<!-- =========================== AUDITORIA ===========================
		     A Administração consulta os registos. A investigação técnica é do
		     departamento de IT, por isso aqui só se lê, não se apaga.
		     ================================================================= -->
		<section class="pm-view" data-view="auditoria" hidden>

			<?php $audit_full = class_exists( 'Clinica_Auditoria' ) ? Clinica_Auditoria::listar( 60 ) : array(); ?>
			<div class="ad-card">
				<div class="ad-card__h">
					<strong>Atividade recente</strong>
					<input type="search" class="ge-search" id="ad-audit-q" placeholder="Procurar ação ou utilizador" aria-label="Procurar no registo">
				</div>
				<?php if ( $audit_full ) : ?>
					<div class="ad-tbl" id="ad-audit-list">
						<div class="ad-tbl__head">
							<span>Ação</span>
							<span>Detalhe</span>
							<span>Utilizador</span>
							<span class="r">Quando</span>
						</div>
						<?php foreach ( $audit_full as $ev ) : ?>
							<div class="ad-tbl__row" data-q="<?php echo esc_attr( mb_strtolower( $ev['acao'] . ' ' . $ev['detalhe'] . ' ' . $ev['user_nome'] ) ); ?>">
								<span class="ad-tbl__acao"><?php echo esc_html( $ev['acao'] ); ?></span>
								<span class="ad-tbl__c"><?php echo esc_html( $ev['detalhe'] ?: '' ); ?></span>
								<span class="ad-tbl__c"><?php echo esc_html( $ev['user_nome'] ?: 'Sistema' ); ?></span>
								<span class="ad-tbl__c r"><?php echo esc_html( human_time_diff( strtotime( $ev['criado_em'] ), $agora ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="pm-empty" id="ad-audit-none" hidden>Nenhum registo corresponde à pesquisa</p>
				<?php else : ?>
					<p class="pm-empty">Ainda não há atividade registada</p>
				<?php endif; ?>
			</div>
		</section>
		<!-- ========================= EQUIPA MÉDICA ========================= -->
		<section class="pm-view" data-view="equipa" hidden>
			<?php
			// Carga do mês por médico: sem isto a lista diz quem existe, mas não
			// diz quem está a trabalhar, que é a decisão que a gestão tem de tomar.
			$mes_atual  = date_i18n( 'Y-m', $agora );
			$carga_mes  = array();
			foreach ( $consultas as $c ) {
				if ( substr( $c['inicio'], 0, 7 ) !== $mes_atual ) {
					continue;
				}
				$k = $c['med_nome'];
				$carga_mes[ $k ] = ( $carga_mes[ $k ] ?? 0 ) + 1;
			}
			$esp_cobertas = array();
			foreach ( $med_ativos as $m ) {
				$esp_cobertas[ $m['esp_nome'] ] = true;
			}
			$sem_medico = count( $especialidades ) - count( $esp_cobertas );
			?>

			<section class="ad-kpis ge-resumo">
				<?php
				$rs = array(
					array(
						'l' => 'Médicos ativos',
						'n' => (string) count( $med_ativos ),
						'p' => 'Em serviço',
						't' => count( $medicos ) . ' no total',
					),
					array(
						'l' => 'Médicos inativos',
						'n' => (string) $med_inativos,
						'p' => 'Fora de serviço',
						't' => $med_inativos ? 'Não recebem marcações' : 'Toda a equipa em serviço',
					),
					array(
						'l' => 'Especialidades cobertas',
						'n' => count( $esp_cobertas ) . '/' . count( $especialidades ),
						'p' => 'Cobertura',
						't' => $sem_medico > 0 ? $sem_medico . ' sem médico ativo' : 'Todas com médico',
					),
					array(
						'l' => 'Consultas do mês',
						'n' => (string) array_sum( $carga_mes ),
						'p' => ucfirst( date_i18n( 'F', $agora ) ),
						't' => count( $med_ativos ) > 0
							? round( array_sum( $carga_mes ) / count( $med_ativos ) ) . ' por médico em média'
							: 'Sem médicos ativos',
					),
				);
				foreach ( $rs as $r ) : ?>
					<div class="ad-kpi">
						<div class="ad-kpi__t">
							<span class="ad-kpi__l"><?php echo esc_html( $r['l'] ); ?></span>
							<span class="ad-kpi__p"><?php echo esc_html( $r['p'] ); ?></span>
						</div>
						<strong class="ad-kpi__n"><?php echo esc_html( $r['n'] ); ?></strong>
						<div class="ad-kpi__f"><span class="ad-trend"><?php echo esc_html( $r['t'] ); ?></span></div>
					</div>
				<?php endforeach; ?>
			</section>

			<div class="ge-toolbar">
				<input type="search" id="med-search" class="ge-search" placeholder="Procurar por nome, email ou nº de ordem" aria-label="Procurar médico">
				<select id="med-filter" class="ge-filter" data-combo data-combo-ph="Todas as especialidades" aria-label="Filtrar por especialidade">
					<option value="">Todas as especialidades</option>
					<?php foreach ( $especialidades as $e ) : ?><option value="<?php echo esc_attr( mb_strtolower( $e['nome'] ) ); ?>"><?php echo esc_html( $e['nome'] ); ?></option><?php endforeach; ?>
				</select>
				<span class="ge-toolbar__count"><?php echo count( $medicos ); ?> médico<?php echo 1 === count( $medicos ) ? '' : 's'; ?></span>
				<a class="ge-btn ge-btn--primary" href="#" data-go="novo-medico">+ Novo médico</a>
			</div>

			<?php if ( $medicos ) : ?>
				<div class="ge-mtbl" id="med-tbl">
					<div class="ge-mtbl__head">
						<span>Médico</span>
						<span>Especialidade</span>
						<span>Nº de ordem</span>
						<span>Email</span>
						<span class="r">Consultas</span>
						<span>Estado</span>
						<span class="r">Ações</span>
					</div>
					<?php foreach ( $medicos as $m ) :
						$foto      = function_exists( 'clinica_avatar_url' ) ? clinica_avatar_url( (int) $m['user_id'], 80 ) : get_avatar_url( (int) $m['user_id'] );
						$nome_show = $titulo( $m['nome'] ) . ' ' . preg_replace( '/^\s*(Dra?|Dr\(a\))\.?\s*/i', '', $m['nome'] );
						$busca     = mb_strtolower( $m['nome'] . ' ' . $m['esp_nome'] . ' ' . $m['email'] . ' ' . $m['num_ordem'] );
						$n_mes     = (int) ( $carga_mes[ $m['nome'] ] ?? 0 );
					?>
						<div class="ge-mtbl__row<?php echo $m['ativo'] ? '' : ' is-off'; ?>" data-busca="<?php echo esc_attr( $busca ); ?>" data-esp="<?php echo esc_attr( mb_strtolower( $m['esp_nome'] ) ); ?>">
							<span class="ge-mtbl__med">
								<img class="ge-mtbl__ph" src="<?php echo esc_url( $foto ); ?>" alt="">
								<b><?php echo esc_html( $nome_show ); ?></b>
							</span>
							<span class="ge-mtbl__c ge-mtbl__esp"><?php echo esc_html( $m['esp_nome'] ); ?></span>
							<span class="ge-mtbl__c ge-mtbl__ordem"><?php echo esc_html( $m['num_ordem'] ); ?></span>
							<span class="ge-mtbl__c ge-mtbl__email" title="<?php echo esc_attr( $m['email'] ); ?>"><?php echo esc_html( $m['email'] ); ?></span>
							<span class="ge-mtbl__c ge-mtbl__n"><?php echo $n_mes; ?></span>
							<span class="ge-status ge-status--<?php echo $m['ativo'] ? 'on' : 'off'; ?>"><?php echo $m['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
							<span class="ge-mtbl__act">
								<form method="post"<?php echo $m['ativo'] ? ' data-confirm="' . esc_attr( 'Tem a certeza que deseja desativar ' . $nome_show . '? Deixa de aparecer para novas marcações.' ) . '"' : ''; ?>>
									<?php wp_nonce_field( 'ge_med', 'ge_nonce' ); ?>
									<input type="hidden" name="ge_action" value="med_estado">
									<input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
									<input type="hidden" name="ativo" value="<?php echo $m['ativo'] ? 0 : 1; ?>">
									<button class="ge-act<?php echo $m['ativo'] ? ' ge-act--danger' : ''; ?>" type="submit"><?php echo $m['ativo'] ? 'Desativar' : 'Ativar'; ?></button>
								</form>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="ge-empty" id="med-noresult" hidden>Nenhum médico corresponde à procura</p>
			<?php else : ?>
				<div class="pm-blank">
					<p class="pm-blank__t">Ainda não há médicos</p>
					<p class="pm-blank__s">Adicione o primeiro médico da clínica para começar a agendar consultas.</p>
					<p class="pm-blank__cta"><a class="ge-btn ge-btn--primary" href="#" data-go="novo-medico">+ Novo médico</a></p>
				</div>
			<?php endif; ?>
		</section>

		<!-- ===================== NOVO MÉDICO (página dedicada) =====================
		     A ordem dos grupos é a da conversa real: quem é a pessoa, o que faz
		     na clínica, e como entra no sistema. As credenciais ficam no fim
		     porque são o único ponto que exige explicação.
		     ========================================================================= -->
		<section class="pm-view" data-view="novo-medico" hidden>
			<div class="ad-actions">
				<a class="ge-btn ge-btn--ghost ge-btn--back" href="#" data-go="equipa">← Voltar à equipa</a>
			</div>

			<form method="post" class="ge-nform" enctype="multipart/form-data">
				<?php wp_nonce_field( 'ge_med', 'ge_nonce' ); ?>
				<input type="hidden" name="ge_action" value="med_criar">

				<div class="ge-nform__body">
					<div class="ge-nform__main">
						<div class="ge-nform__grp">
							<div class="ge-nform__gh">
								<h3 class="ge-nform__gt">Identificação</h3>
								<span class="ge-nform__gn">1 de 3</span>
							</div>
							<div class="ge-grid">
								<label class="ge-field ge-field--wide">
									<span>Nome completo</span>
									<input type="text" name="nome" required placeholder="Ex.: Ana Come">
									<small>Sem o título. O sistema trata disso ao mostrar o nome.</small>
								</label>
								<label class="ge-field ge-field--wide">
									<span>Telefone</span>
									<input type="tel" name="telefone" placeholder="+258 84 000 0000">
								</label>
							</div>
						</div>

						<div class="ge-nform__grp">
							<div class="ge-nform__gh">
								<h3 class="ge-nform__gt">Atividade clínica</h3>
								<span class="ge-nform__gn">2 de 3</span>
							</div>
							<div class="ge-grid">
								<label class="ge-field ge-field--wide">
									<span>Especialidade</span>
									<select name="especialidade_id" required data-combo data-combo-ph="Escreva para procurar">
										<option value="">Selecione</option>
										<?php foreach ( $esp_ativas as $e ) : ?><option value="<?php echo (int) $e['id']; ?>"><?php echo esc_html( $e['nome'] ); ?></option><?php endforeach; ?>
									</select>
									<small>Define em que agenda o médico aparece ao paciente.</small>
								</label>
								<label class="ge-field">
									<span>Nº de ordem</span>
									<input type="text" name="num_ordem" placeholder="OM-0000">
								</label>
								<label class="ge-field">
									<span>Consultório</span>
									<input type="text" name="sala" placeholder="Ex.: Consultório 3">
								</label>
							</div>
						</div>

						<div class="ge-nform__grp">
							<div class="ge-nform__gh">
								<h3 class="ge-nform__gt">Acesso ao sistema</h3>
								<span class="ge-nform__gn">3 de 3</span>
							</div>
							<div class="ge-grid">
								<label class="ge-field">
									<span>Email</span>
									<input type="email" name="email" required placeholder="nome@clinica.mz">
								</label>
								<label class="ge-field">
									<span>Palavra-passe</span>
									<input type="password" name="pwd" minlength="6" required placeholder="Mínimo 6 caracteres">
								</label>
							</div>
							<p class="ge-nform__nota">São estes os dados com que o médico entra no portal. A conta fica ativa
							de imediato e passa a receber marcações. Entregue a palavra-passe em mão e peça que a mude.</p>
						</div>
					</div>

					<aside class="ge-nform__side">
						<div class="ge-nform__gh">
							<h3 class="ge-nform__gt">Fotografia</h3>
						</div>
						<div class="ge-foto">
							<span class="ge-foto__frame">
								<img class="ge-foto__preview" id="med-foto-preview" src="" alt="" hidden>
								<span class="ge-foto__ph" id="med-foto-ph">Sem foto</span>
							</span>
							<label class="ge-foto__btn">
								<span>Escolher imagem</span>
								<input type="file" name="foto" accept="image/*" id="med-foto-input" hidden>
							</label>
							<p class="ge-foto__hint">JPG ou PNG, de preferência quadrada. Aparece na lista da equipa e na
							marcação do paciente.</p>
						</div>
					</aside>
				</div>

				<div class="ge-nform__foot">
					<span class="ge-nform__req">Os campos sem indicação são opcionais</span>
					<a class="ge-btn ge-btn--ghost" href="#" data-go="equipa">Cancelar</a>
					<button type="submit" class="ge-btn ge-btn--primary">Criar médico</button>
				</div>
			</form>
		</section>

		<!-- ========================= ESPECIALIDADES ========================= -->
		<section class="pm-view" data-view="especialidades" hidden>
			<div class="ad-actions">
				<a class="ge-btn ge-btn--primary" href="#" data-go="nova-especialidade">+ Nova especialidade</a>
			</div>
				<div class="ge-tbl">
					<div class="ge-tbl__head">
						<span>Especialidade</span><span>Descrição</span><span>Médicos</span><span>Estado</span><span></span>
					</div>
					<?php foreach ( $especialidades as $e ) :
						$n = (int) ( $cont_med[ (int) $e['id'] ] ?? 0 );
					?>
						<details class="ge-tr">
							<summary class="ge-tr__row">
								<span class="ge-tr__name"><?php echo esc_html( $e['nome'] ); ?></span>
								<span class="ge-tr__desc"><?php echo esc_html( $e['descricao'] ?: '—' ); ?></span>
								<span class="ge-tr__med"><?php echo $n; ?></span>
								<span><span class="ge-status ge-status--<?php echo $e['ativo'] ? 'on' : 'off'; ?>"><?php echo $e['ativo'] ? 'Ativa' : 'Inativa'; ?></span></span>
								<span class="ge-tr__edit">Editar</span>
							</summary>
							<div class="ge-tr__panel">
								<form method="post" class="ge-form">
									<?php wp_nonce_field( 'ge_esp', 'ge_nonce' ); ?>
									<input type="hidden" name="ge_action" value="esp_editar">
									<input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
									<div class="ge-grid">
										<label class="ge-field"><span>Nome</span><input type="text" name="nome" value="<?php echo esc_attr( $e['nome'] ); ?>"></label>
										<label class="ge-field ge-field--wide"><span>Descrição</span><input type="text" name="descricao" value="<?php echo esc_attr( $e['descricao'] ); ?>"></label>
										<label class="ge-check"><input type="checkbox" name="ativo" value="1" <?php checked( (int) $e['ativo'], 1 ); ?>> Ativa</label>
									</div>
									<button class="ge-btn ge-btn--primary" type="submit">Guardar alterações</button>
								</form>
								<form method="post" class="ge-tr__del" onsubmit="return confirm('Remover esta especialidade?');">
									<?php wp_nonce_field( 'ge_esp', 'ge_nonce' ); ?>
									<input type="hidden" name="ge_action" value="esp_apagar">
									<input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
									<button class="ge-act ge-act--danger" type="submit">Remover especialidade</button>
								</form>
							</div>
						</details>
					<?php endforeach; ?>
					<?php if ( ! $especialidades ) : ?><p class="ge-empty">Ainda não há especialidades.</p><?php endif; ?>
				</div>
		</section>

		<!-- ==================== NOVA ESPECIALIDADE (página dedicada) ==================== -->
		<section class="pm-view" data-view="nova-especialidade" hidden>
			<div class="ad-actions">
				<a class="ge-btn ge-btn--ghost ge-btn--back" href="#" data-go="especialidades">← Voltar às especialidades</a>
			</div>
			<form method="post" class="ge-nform">
				<?php wp_nonce_field( 'ge_esp', 'ge_nonce' ); ?>
				<input type="hidden" name="ge_action" value="esp_criar">
				<div class="ge-nform__body">
					<div class="ge-nform__main">
						<div class="ge-nform__grp">
							<h3 class="ge-nform__gt">Dados da especialidade</h3>
							<div class="ge-grid">
								<label class="ge-field ge-field--wide"><span>Nome</span><input type="text" name="nome" required placeholder="Ex.: Cardiologia"><small class="ge-hintline">Como aparece na marcação e na página pública.</small></label>
								<label class="ge-field ge-field--wide"><span>Descrição <em>(opcional)</em></span><input type="text" name="descricao" placeholder="Uma linha sobre a área"></label>
							</div>
						</div>
					</div>
					<aside class="ge-nform__side">
						<h3 class="ge-nform__gt">Nota</h3>
						<p class="ge-sidenote">É criada <b>ativa</b> e fica logo disponível para atribuir a médicos e para marcação. Pode desativá-la mais tarde sem a apagar.</p>
					</aside>
				</div>
				<div class="ge-nform__foot">
					<a class="ge-btn ge-btn--ghost" href="#" data-go="especialidades">Cancelar</a>
					<button type="submit" class="ge-btn ge-btn--primary">Criar especialidade</button>
				</div>
			</form>
		</section>

		<!-- ============================= AGENDA ============================= -->
		<section class="pm-view" data-view="agenda" hidden>
			<div class="ad-actions">
				<?php if ( $pode_agenda ) : ?>
					<a class="ge-btn ge-btn--primary" href="#" data-go="horarios">Gerir horários e folgas</a>
				<?php endif; ?>
			</div>
				<?php if ( $agenda_por_dia ) : ?>
					<div class="pm-agenda">
						<?php foreach ( $agenda_por_dia as $dia => $items ) :
							$ts = strtotime( $dia );
							$is_hoje = ( $dia === $hoje_ymd );
						?>
							<div class="pm-agenda__day">
								<div class="pm-agenda__date">
									<span class="pm-agenda__dnum"><?php echo esc_html( date_i18n( 'j', $ts ) ); ?></span>
									<span class="pm-agenda__dwrap">
										<span class="pm-agenda__dweek"><?php echo esc_html( date_i18n( 'l', $ts ) ); ?><?php echo $is_hoje ? ' · Hoje' : ''; ?></span>
										<span class="pm-agenda__dmon"><?php echo esc_html( date_i18n( 'F Y', $ts ) ); ?></span>
									</span>
									<span class="pm-agenda__dcount"><?php echo count( $items ); ?> consulta<?php echo 1 === count( $items ) ? '' : 's'; ?></span>
								</div>
								<div class="pm-agenda__slots">
									<?php foreach ( $items as $f ) : ?>
										<div class="pm-agenda__slot">
											<span class="pm-agenda__time"><?php echo esc_html( $fmt_hora( $f['inicio'] ) ); ?></span>
											<div class="pm-agenda__info">
												<div class="pm-agenda__name"><?php echo esc_html( $f['pac_nome'] ); ?></div>
												<div class="pm-agenda__meta"><?php echo esc_html( $f['esp_nome'] ); ?><?php echo ! empty( $f['med_nome'] ) ? ' · ' . esc_html( $f['med_nome'] ) : ''; ?></div>
											</div>
											<span class="pm-st pm-st--<?php echo esc_attr( $f['status'] ); ?>"><?php echo esc_html( $labels[ $f['status'] ] ?? $f['status'] ); ?></span>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="pm-empty">Sem consultas agendadas.</p>
				<?php endif; ?>
		</section>

		<!-- ==================== HORÁRIOS E FOLGAS (agenda no shell) ==================== -->
		<?php if ( $pode_agenda ) : ?>
		<section class="pm-view" data-view="horarios" hidden>

			<?php if ( ! $ag_meds ) : ?>
				<div class="pm-blank">
					<p class="pm-blank__t">Ainda não há médicos ativos</p>
					<p class="pm-blank__s">Crie médicos na Equipa para poder definir horários.</p>
				</div>
			<?php else : ?>
				<form method="get" class="gh-pick">
					<label class="ge-field"><span>Médico</span>
						<select name="medico" data-combo data-combo-ph="Escreva para procurar" onchange="this.form.submit()">
							<?php foreach ( $ag_meds as $m ) : ?>
								<option value="<?php echo (int) $m['id']; ?>" <?php selected( (int) $m['id'], $ag_sel ); ?>><?php echo esc_html( $ag_nome( $m['nome'] ) . ' — ' . $m['esp'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<span class="gh-pick__hint"><?php echo count( $ag_meds ); ?> médico(s) ativo(s)</span>
				</form>

				<?php if ( $ag_sel_med ) : ?>
					<form method="post" class="gh-sala">
						<?php wp_nonce_field( 'ge_med', 'ge_nonce' ); ?>
						<input type="hidden" name="ge_action" value="med_sala">
						<input type="hidden" name="id" value="<?php echo (int) $ag_sel; ?>">
						<label class="ge-field"><span>Consultório de <?php echo esc_html( $ag_nome( $ag_sel_med['nome'] ) ); ?></span><input type="text" name="sala" value="<?php echo esc_attr( $ag_sel_med['sala'] ?? '' ); ?>" placeholder="Ex.: Consultório 3"></label>
						<button class="ge-btn ge-btn--primary" type="submit">Guardar consultório</button>
					</form>
					<div class="gh-grid">
						<div class="ad-panel gh-col-wide">
							<div class="ad-panel__head"><h3>Horário semanal — <?php echo esc_html( $ag_nome( $ag_sel_med['nome'] ) ); ?></h3></div>
							<form method="post" class="gh-addrow">
								<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
								<input type="hidden" name="ag_action" value="hor_add">
								<input type="hidden" name="medico_id" value="<?php echo (int) $ag_sel; ?>">
								<select name="dia_semana" required>
									<?php foreach ( $ag_dias as $n => $d ) : ?><option value="<?php echo (int) $n; ?>"><?php echo esc_html( $d ); ?></option><?php endforeach; ?>
								</select>
								<input type="time" name="hora_inicio" value="08:00" required>
								<span class="gh-sep">→</span>
								<input type="time" name="hora_fim" value="17:00" required>
								<button class="ge-btn ge-btn--primary" type="submit">Adicionar</button>
							</form>
							<div class="gh-week">
								<?php foreach ( $ag_dias as $n => $d ) : ?>
									<div class="gh-day<?php echo empty( $ag_por_dia[ $n ] ) ? ' is-off' : ''; ?>">
										<span class="gh-day__name"><?php echo esc_html( $d ); ?></span>
										<div class="gh-day__slots">
											<?php if ( empty( $ag_por_dia[ $n ] ) ) : ?>
												<span class="gh-day__off">Folga</span>
											<?php else : foreach ( $ag_por_dia[ $n ] as $h ) : ?>
												<span class="gh-chip">
													<?php echo esc_html( $ag_hhmm( $h['hora_inicio'] ) . '–' . $ag_hhmm( $h['hora_fim'] ) ); ?>
													<form method="post" data-confirm="Remover o horário de <?php echo esc_attr( $d . ' (' . $ag_hhmm( $h['hora_inicio'] ) . '–' . $ag_hhmm( $h['hora_fim'] ) . ')' ); ?>?">
														<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
														<input type="hidden" name="ag_action" value="hor_del">
														<input type="hidden" name="id" value="<?php echo (int) $h['id']; ?>">
														<button type="submit" aria-label="Remover">&times;</button>
													</form>
												</span>
											<?php endforeach; endif; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

						<div class="ad-panel">
							<div class="ad-panel__head"><h3>Folgas / ausências</h3></div>
							<form method="post" class="gh-form">
								<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
								<input type="hidden" name="ag_action" value="blq_add">
								<input type="hidden" name="medico_id" value="<?php echo (int) $ag_sel; ?>">
								<div class="ge-grid">
									<label class="ge-field"><span>De</span><input type="date" name="data_inicio" required></label>
									<label class="ge-field"><span>Até</span><input type="date" name="data_fim" required></label>
								</div>
								<label class="ge-field ge-field--wide"><span>Motivo</span><input type="text" name="motivo" placeholder="Ex.: férias" maxlength="120"></label>
								<button class="ge-btn ge-btn--primary" type="submit">Adicionar folga</button>
							</form>
							<ul class="gh-list">
								<?php foreach ( $ag_bloqs as $b ) : ?>
									<li>
										<span><strong><?php echo esc_html( $ag_fdata( $b['data_inicio'] ) ); ?></strong> → <strong><?php echo esc_html( $ag_fdata( $b['data_fim'] ) ); ?></strong><?php echo $b['motivo'] ? ' · ' . esc_html( $b['motivo'] ) : ''; ?></span>
										<form method="post" data-confirm="Remover esta folga?">
											<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
											<input type="hidden" name="ag_action" value="blq_del">
											<input type="hidden" name="id" value="<?php echo (int) $b['id']; ?>">
											<button class="gh-del" type="submit" aria-label="Remover">&times;</button>
										</form>
									</li>
								<?php endforeach; ?>
								<?php if ( ! $ag_bloqs ) : ?><li class="gh-empty">Sem folgas registadas.</li><?php endif; ?>
							</ul>
						</div>

						<div class="ad-panel">
							<div class="ad-panel__head"><h3>Feriados da clínica</h3></div>
							<form method="post" class="gh-form">
								<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
								<input type="hidden" name="ag_action" value="fer_add">
								<div class="ge-grid">
									<label class="ge-field ge-field--wide"><span>Data</span><input type="date" name="data" required></label>
								</div>
								<label class="ge-field ge-field--wide"><span>Descrição</span><input type="text" name="descricao" placeholder="Ex.: Dia da Independência" maxlength="120"></label>
								<button class="ge-btn ge-btn--primary" type="submit">Adicionar feriado</button>
							</form>
							<ul class="gh-list">
								<?php foreach ( $ag_feriados as $f ) : ?>
									<li>
										<span><strong><?php echo esc_html( $ag_fdata( $f['data'] ) ); ?></strong><?php echo $f['descricao'] ? ' · ' . esc_html( $f['descricao'] ) : ''; ?></span>
										<form method="post" data-confirm="Remover este feriado?">
											<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
											<input type="hidden" name="ag_action" value="fer_del">
											<input type="hidden" name="id" value="<?php echo (int) $f['id']; ?>">
											<button class="gh-del" type="submit" aria-label="Remover">&times;</button>
										</form>
									</li>
								<?php endforeach; ?>
								<?php if ( ! $ag_feriados ) : ?><li class="gh-empty">Sem feriados registados.</li><?php endif; ?>
							</ul>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php endif; ?>

		<!-- ============================ DEFINIÇÕES ============================ -->
		<?php if ( $pode_config ) : ?>
		<section class="pm-view" data-view="definicoes" hidden>
			<form method="post" class="ad-cfg ad-panel">
				<?php wp_nonce_field( 'ge_cfg', 'ge_nonce' ); ?>
				<input type="hidden" name="ge_action" value="cfg_save">

				<div class="ad-grp">
					<div class="ad-grp__t">
						<h3>Horário de funcionamento</h3>
						<p>Define a janela em que há consultas. O almoço é excluído automaticamente das horas livres.</p>
					</div>
					<div class="ad-grp__f">
						<label class="ge-field"><span>Abertura</span><input type="time" name="hora_abertura" value="<?php echo esc_attr( Clinica_Config::get( 'hora_abertura', '08:00' ) ); ?>"></label>
						<label class="ge-field"><span>Fecho</span><input type="time" name="hora_fecho" value="<?php echo esc_attr( Clinica_Config::get( 'hora_fecho', '20:00' ) ); ?>"></label>
						<label class="ge-field"><span>Almoço — início</span><input type="time" name="intervalo_inicio" value="<?php echo esc_attr( Clinica_Config::get( 'intervalo_inicio', '13:00' ) ); ?>"></label>
						<label class="ge-field"><span>Almoço — fim</span><input type="time" name="intervalo_fim" value="<?php echo esc_attr( Clinica_Config::get( 'intervalo_fim', '14:00' ) ); ?>"></label>
					</div>
				</div>

				<div class="ad-grp">
					<div class="ad-grp__t">
						<h3>Consultas</h3>
						<p>A duração define de quanto em quanto tempo há uma vaga. A antecedência impede marcações em cima da hora.</p>
					</div>
					<div class="ad-grp__f">
						<label class="ge-field"><span>Duração (min)</span><input type="number" min="5" step="5" name="duracao_consulta_min" value="<?php echo esc_attr( Clinica_Config::int( 'duracao_consulta_min', 30 ) ); ?>"></label>
						<label class="ge-field"><span>Antecedência mínima (h)</span><input type="number" min="0" name="antecedencia_min_horas" value="<?php echo esc_attr( Clinica_Config::int( 'antecedencia_min_horas', 2 ) ); ?>"></label>
						<label class="ge-field"><span>Cancelar até (h antes)</span><input type="number" min="0" name="cancelamento_horas" value="<?php echo esc_attr( Clinica_Config::int( 'cancelamento_horas', 24 ) ); ?>"></label>
					</div>
				</div>

				<div class="ad-grp">
					<div class="ad-grp__t">
						<h3>Limites de carga</h3>
						<p>Travões de segurança: quando o limite é atingido, deixam de aparecer vagas — mesmo com o médico livre.</p>
					</div>
					<div class="ad-grp__f">
						<label class="ge-field"><span>Máx. por dia</span><input type="number" min="1" name="max_consultas_dia" value="<?php echo esc_attr( Clinica_Config::int( 'max_consultas_dia', 40 ) ); ?>"></label>
						<label class="ge-field"><span>Máx. por hora</span><input type="number" min="1" name="max_consultas_hora" value="<?php echo esc_attr( Clinica_Config::int( 'max_consultas_hora', 5 ) ); ?>"></label>
					</div>
				</div>

				<div class="ad-grp">
					<div class="ad-grp__t">
						<h3>Pagamento</h3>
						<p>O valor usado em cada consulta nova e no recibo. Alterar não muda as consultas já marcadas.</p>
					</div>
					<div class="ad-grp__f">
						<label class="ge-field"><span>Valor da consulta</span><input type="number" min="0" name="valor_consulta_padrao" value="<?php echo esc_attr( Clinica_Config::int( 'valor_consulta_padrao', 1000 ) ); ?>"></label>
						<label class="ge-field"><span>Moeda</span><input type="text" name="moeda" maxlength="6" value="<?php echo esc_attr( Clinica_Config::get( 'moeda', 'MZN' ) ); ?>"></label>
						<label class="ge-check ad-grp__ck"><input type="checkbox" name="pagamento_obrigatorio" value="1" <?php checked( Clinica_Config::bool( 'pagamento_obrigatorio' ), true ); ?>> Exigir pagamento antes da consulta</label>
					</div>
				</div>

				<div class="ad-cfg__foot">
					<p>Ao guardar, as novas regras aplicam-se <b>de imediato</b> às horas que o paciente e a receção veem.</p>
					<button class="ge-btn ge-btn--primary" type="submit">Guardar definições</button>
				</div>
			</form>
		</section>
		<?php endif; ?>

		<!-- Confirmação de ação (mensagem do sistema, não o confirm do browser) -->
		<div class="ge-modal" id="ge-confirm" hidden>
			<div class="ge-modal__backdrop" data-cclose></div>
			<div class="ge-modal__dialog ge-modal__dialog--sm" role="alertdialog" aria-modal="true" aria-labelledby="ge-confirm-t">
				<h2 id="ge-confirm-t" class="ge-modal__title">Confirmar</h2>
				<p class="ge-confirm__msg" id="ge-confirm-msg"></p>
				<div class="ge-mform__foot">
					<button type="button" class="ge-btn ge-btn--ghost" data-cclose>Cancelar</button>
					<button type="button" class="ge-btn ge-btn--danger" data-confirm-ok>Confirmar</button>
				</div>
			</div>
		</div>

		<!-- =================== PROPOR À DIREÇÃO (Gestão -> Direção) ===================
		     Comunicação entre portais: a Gestão submete um pedido estratégico que
		     sobe à Direção para decisão. A Gestão vê aqui o estado da resposta.
		     =========================================================================== -->
		<section class="pm-view" data-view="propostas" hidden>
			<?php if ( $pode_propor ) : ?>
				<div class="ad-card ge-propor">
					<div class="ad-card__h"><strong>Propor uma decisão à Direção</strong><span class="ad-card__s">Sobe para aprovação</span></div>
					<form method="post">
						<?php wp_nonce_field( 'dir_propor', 'dir_nonce' ); ?>
						<input type="hidden" name="dir_action" value="propor">
						<div class="ge-grid">
							<label class="ge-field ge-field--wide"><span>O que propõe</span><input type="text" name="titulo" required placeholder="Ex.: Abrir especialidade de Oftalmologia"></label>
							<label class="ge-field"><span>Tipo</span>
								<select name="tipo">
									<option value="especialidade">Nova especialidade</option>
									<option value="orcamento">Orçamento</option>
									<option value="investimento">Investimento em equipamento</option>
									<option value="contratacao">Contratação</option>
									<option value="precos">Alteração de preços</option>
									<option value="geral">Geral</option>
								</select>
							</label>
							<label class="ge-field"><span>Valor estimado (<?php echo esc_html( $moeda ); ?>, opcional)</span><input type="number" name="valor" min="0" step="any" placeholder="0"></label>
							<label class="ge-field ge-field--wide"><span>Justificação</span><textarea name="detalhe" rows="3" placeholder="Contexto, impacto esperado e porque precisa da decisão da Direção"></textarea></label>
						</div>
						<div class="ge-propor__foot"><button type="submit" class="ge-btn ge-btn--primary">Enviar à Direção</button></div>
					</form>
				</div>
			<?php endif; ?>

			<div class="ad-card">
				<div class="ad-card__h"><strong>Propostas submetidas</strong><span class="ad-card__s"><?php echo count( $propostas ); ?> no total</span></div>
				<?php if ( $propostas ) : ?>
					<div class="ge-prop-wrap">
						<table class="ge-prop">
							<thead>
								<tr><th>Proposta</th><th>Tipo</th><th class="r">Valor</th><th>Estado</th><th class="r">Submetida</th></tr>
							</thead>
							<tbody>
								<?php foreach ( $propostas as $p ) : ?>
									<tr>
										<td class="ge-prop__k">
											<b><?php echo esc_html( $p['titulo'] ); ?></b>
											<?php if ( ! empty( $p['detalhe'] ) ) : ?><small><?php echo esc_html( $p['detalhe'] ); ?></small><?php endif; ?>
											<?php if ( 'pendente' !== $p['estado'] && $p['decisor'] ) : ?><small>Decidido por <?php echo esc_html( $p['decisor'] ); ?><?php echo ! empty( $p['nota'] ) ? ' · ' . esc_html( $p['nota'] ) : ''; ?></small><?php endif; ?>
										</td>
										<td><?php echo esc_html( ucfirst( $p['tipo'] ) ); ?></td>
										<td class="r"><?php echo null !== $p['valor'] ? esc_html( $fmt_valor( $p['valor'] ) ) : '—'; ?></td>
										<td><span class="ge-pest ge-pest--<?php echo esc_attr( $p['estado'] ); ?>"><?php echo esc_html( $prop_labels[ $p['estado'] ] ?? $p['estado'] ); ?></span></td>
										<td class="r"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $p['criado_em'] ) ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<div class="pm-blank">
						<p class="pm-blank__t">Ainda não submeteu nenhuma proposta</p>
						<p class="pm-blank__s">Use o formulário acima para enviar um pedido estratégico à Direção.</p>
					</div>
				<?php endif; ?>
			</div>
		</section>

	</main>

</div>
</div>
<script>
( function () {
	// --- Navegação de vistas + submenu da barra lateral ---
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
		items.forEach( function ( i ) { i.classList.toggle( 'is-active', i.dataset.go === v ); } );
		var atual = grupoDe( v );
		groups.forEach( function ( g ) {
			g.classList.toggle( 'is-current', g === atual );
			if ( g === atual ) { g.classList.add( 'is-open' ); }  // abre a área para onde navegámos
		} );
		if ( shell ) { shell.classList.toggle( 'is-full', 'painel' !== v ); }
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
	// Vista inicial conforme o resultado da ação (fica no sítio certo após o PRG).
	var inicial = 'painel';
	if ( /[?&]cl=med_(dados|existe|erro)/.test( location.search ) ) { inicial = 'novo-medico'; }
	else if ( /[?&]cl=med_criado/.test( location.search ) ) { inicial = 'equipa'; }
	else if ( /[?&]cl=esp_criada/.test( location.search ) ) { inicial = 'especialidades'; }
	else if ( /[?&]medico=/.test( location.search ) || /[?&]cl=ag_/.test( location.search ) ) { inicial = 'horarios'; }
	show( inicial );

	// --- Filtro da equipa: pesquisa (texto) + categoria (especialidade) ---
	var busca = document.getElementById( 'med-search' ),
		cat = document.getElementById( 'med-filter' ),
		tbl = document.getElementById( 'med-tbl' ),
		vazio = document.getElementById( 'med-noresult' );
	if ( tbl ) {
		var filtrar = function () {
			var q = ( busca ? busca.value : '' ).trim().toLowerCase();
			var c = cat ? cat.value : '';
			var n = 0;
			tbl.querySelectorAll( '.ge-mtbl__row' ).forEach( function ( r ) {
				var ok = ( ! q || ( r.dataset.busca || '' ).indexOf( q ) !== -1 ) && ( ! c || ( r.dataset.esp || '' ) === c );
				r.style.display = ok ? '' : 'none';
				if ( ok ) { n++; }
			} );
			if ( vazio ) { vazio.hidden = 0 !== n; }
		};
		if ( busca ) { busca.addEventListener( 'input', filtrar ); }
		if ( cat ) { cat.addEventListener( 'change', filtrar ); }
	}

	// --- Confirmação no sistema antes de desativar (em vez do confirm do browser) ---
	var cmodal = document.getElementById( 'ge-confirm' );
	if ( cmodal ) {
		var pendente = null;
		var msg = cmodal.querySelector( '#ge-confirm-msg' );
		var cfechar = function () { cmodal.hidden = true; document.body.style.overflow = ''; pendente = null; };
		document.querySelectorAll( 'form[data-confirm]' ).forEach( function ( f ) {
			f.addEventListener( 'submit', function ( e ) {
				if ( f.dataset.ok ) { return; }        // já confirmado
				e.preventDefault();
				pendente = f;
				if ( msg ) { msg.textContent = f.dataset.confirm; }
				cmodal.hidden = false;
				document.body.style.overflow = 'hidden';
			} );
		} );
		cmodal.querySelectorAll( '[data-cclose]' ).forEach( function ( b ) { b.addEventListener( 'click', cfechar ); } );
		var okBtn = cmodal.querySelector( '[data-confirm-ok]' );
		if ( okBtn ) { okBtn.addEventListener( 'click', function () { if ( pendente ) { pendente.dataset.ok = '1'; pendente.submit(); } } ); }
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && ! cmodal.hidden ) { cfechar(); } } );
	}

	// --- Pré-visualização da foto do novo médico ---
	var fInput = document.getElementById( 'med-foto-input' ),
		fPrev = document.getElementById( 'med-foto-preview' ),
		fPh = document.getElementById( 'med-foto-ph' );
	if ( fInput && fPrev ) {
		fInput.addEventListener( 'change', function () {
			var file = fInput.files && fInput.files[0];
			if ( ! file ) { return; }
			fPrev.src = URL.createObjectURL( file );
			fPrev.hidden = false;
			if ( fPh ) { fPh.hidden = true; }
		} );
	}

	// --- Gráfico de atividade: barras agrupadas, três escalas ---
	( function () {
		var plot = document.getElementById( 'ad-plot' );
		if ( ! plot ) { return; }
		var series, barras = document.getElementById( 'ad-bars' ),
			eixoX = document.getElementById( 'ad-x' ),
			eixoY = document.getElementById( 'ad-y' ),
			leitura = document.getElementById( 'ad-read' ),
			repouso = '',
			tabs = document.getElementById( 'ad-tabs' );
		try { series = JSON.parse( plot.dataset.series ); } catch ( e ) { return; }

		function desenhar( escala ) {
			var d = series[ escala ];
			if ( ! d ) { return; }
			var max = 1, i;
			for ( i = 0; i < d.consultas.length; i++ ) {
				max = Math.max( max, d.consultas[ i ], d.exames[ i ] );
			}
			// Topo redondo, para o eixo não dar números estranhos.
			var passo = Math.pow( 10, Math.floor( Math.log( max ) / Math.LN10 ) );
			max = Math.ceil( max / passo ) * passo;

			eixoY.innerHTML = '';
			for ( i = 4; i >= 0; i-- ) {
				var y = document.createElement( 'span' );
				y.textContent = Math.round( max * i / 4 );
				eixoY.appendChild( y );
			}

			var tc = 0, te = 0;
			for ( i = 0; i < d.consultas.length; i++ ) { tc += d.consultas[ i ]; te += d.exames[ i ]; }
			repouso = tc.toLocaleString( 'pt-PT' ) + ' consultas e ' + te.toLocaleString( 'pt-PT' ) + ' exames no período';
			if ( leitura ) { leitura.textContent = repouso; }
			barras.innerHTML = '';
			eixoX.innerHTML = '';
			d.labels.forEach( function ( rotulo, k ) {
				var g = document.createElement( 'div' );
				g.className = 'ad-mes';
				g.dataset.r = rotulo;
				g.dataset.c = d.consultas[ k ];
				g.dataset.e = d.exames[ k ];
				[ 'a', 'b' ].forEach( function ( serie, j ) {
					var v = j === 0 ? d.consultas[ k ] : d.exames[ k ];
					var b = document.createElement( 'span' );
					b.className = 'ad-bar ad-bar--' + serie;
					b.style.height = Math.round( v / max * 100 ) + '%';
					b.style.animationDelay = ( k * 0.035 + j * 0.015 ).toFixed( 3 ) + 's';
					g.appendChild( b );
				} );
				barras.appendChild( g );

				var x = document.createElement( 'span' );
				// Em escala semanal só se rotula de duas em duas, para não amontoar.
				x.textContent = ( 'semanal' === escala && k % 2 !== 0 ) ? '' : rotulo;
				eixoX.appendChild( x );
			} );
		}

		if ( leitura ) {
			barras.addEventListener( 'mouseover', function ( e ) {
				var g = e.target.closest( '.ad-mes' );
				if ( ! g ) { return; }
				leitura.innerHTML = '<b>' + g.dataset.r + '</b> ' + g.dataset.c + ' consultas, ' + g.dataset.e + ' exames';
			} );
			barras.addEventListener( 'mouseleave', function () { leitura.textContent = repouso; } );
		}

		if ( tabs ) {
			tabs.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '.ad-tab' );
				if ( ! b ) { return; }
				tabs.querySelectorAll( '.ad-tab' ).forEach( function ( o ) {
					o.classList.toggle( 'is-active', o === b );
				} );
				desenhar( b.dataset.esc );
			} );
		}
		desenhar( 'mensal' );
	} )();

	// --- Pesquisa no registo de auditoria ---
	( function () {
		var q = document.getElementById( 'ad-audit-q' ),
			lista = document.getElementById( 'ad-audit-list' ),
			vazio = document.getElementById( 'ad-audit-none' );
		if ( ! q || ! lista ) { return; }
		q.addEventListener( 'input', function () {
			var t = q.value.trim().toLowerCase(), n = 0;
			lista.querySelectorAll( '.ad-tbl__row' ).forEach( function ( r ) {
				var ok = ! t || ( r.dataset.q || '' ).indexOf( t ) !== -1;
				r.hidden = ! ok;
				if ( ok ) { n++; }
			} );
			if ( vazio ) { vazio.hidden = ! t || 0 !== n; }
		} );
	} )();

	// --- Animações do painel ---
	( function () {
		var suave = ! window.matchMedia || ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		// Contagem de um número. Preserva o texto formatado (moeda, separadores)
		// interpolando só a parte numérica.
		function contar( el ) {
			// O valor final nao pode sair do texto no ecra: no arranque o
			// numero do anel e da ocupacao esta a zero de proposito.
			var temN = el.dataset.n !== undefined;
			var alvo = temN
				? parseFloat( el.dataset.n )
				: parseFloat( ( el.dataset.final || el.textContent || '' ).replace( /[^\d,.-]/g, '' ).replace( /\s/g, '' ).replace( ',', '.' ) );
			if ( isNaN( alvo ) ) { return; }
			var final = el.dataset.final !== undefined
				? el.dataset.final
				: ( temN ? Math.round( alvo ).toLocaleString( 'pt-PT' ) : el.textContent );
			if ( ! suave ) { el.textContent = final; return; }

			var dur = 900, ini = null;
			function passo( t ) {
				if ( null === ini ) { ini = t; }
				var p = Math.min( 1, ( t - ini ) / dur );
				var e = 1 - Math.pow( 1 - p, 3 );
				if ( p >= 1 ) { el.textContent = final; return; }
				var v = Math.round( alvo * e );
				el.textContent = ( temN && el.dataset.final === undefined )
					? String( v )
					: final.replace( /[\d\s.,]*\d/, v.toLocaleString( 'pt-PT' ) );
				requestAnimationFrame( passo );
			}
			el.textContent = '0';
			requestAnimationFrame( passo );
		}

		// Desenha o anel de fora para dentro, sector a sector.
		var relogios = [];
		function anel() {
			relogios.forEach( clearTimeout );
			relogios = [];
			var setores = document.querySelectorAll( '.ad-ring__s' );
			setores.forEach( function ( c, i ) {
				var d = c.dataset.dash;
				if ( ! d ) { return; }
				if ( ! suave ) { c.setAttribute( 'stroke-dasharray', d ); return; }
				c.setAttribute( 'stroke-dasharray', '0 100' );
				relogios.push( setTimeout( function () {
					c.setAttribute( 'stroke-dasharray', d );
				}, 220 + i * 190 ) );
			} );
		}

		// Barra de ocupação.
		function gauge() {
			var barra = document.querySelector( '.ad-gauge__bar span' );
			if ( ! barra ) { return; }
			if ( barra.dataset.w === undefined ) { barra.dataset.w = barra.style.width; }
			if ( ! suave ) { barra.style.width = barra.dataset.w; return; }
			barra.style.width = '0%';
			relogios.push( setTimeout( function () { barra.style.width = barra.dataset.w; }, 260 ) );
		}

		// Corre tudo o que é do painel. Chamado no arranque e sempre que se
		// volta ao painel, senão a segunda visita mostrava o gráfico já feito.
		function tocar() {
			anel();
			gauge();
			document.querySelectorAll( '.ad-num' ).forEach( function ( el ) {
				relogios.push( setTimeout( function () { contar( el ); }, 180 ) );
			} );
		}

		// Entrada dos blocos, em cascata e só quando entram no ecrã.
		var blocos = document.querySelectorAll( '.ad-kpi, .ad-card' );
		if ( ! suave || ! ( 'IntersectionObserver' in window ) ) {
			blocos.forEach( function ( b ) { b.classList.add( 'is-in' ); } );
		} else {
			var io = new IntersectionObserver( function ( ents ) {
				ents.forEach( function ( en ) {
					if ( ! en.isIntersecting ) { return; }
					var i = [].indexOf.call( blocos, en.target );
					en.target.style.transitionDelay = ( Math.max( 0, i ) * 0.06 ).toFixed( 2 ) + 's';
					en.target.classList.add( 'is-in' );
					io.unobserve( en.target );
				} );
			}, { threshold: 0.15 } );
			blocos.forEach( function ( b ) { io.observe( b ); } );
		}

		// Legenda e anel realçam-se em conjunto.
		var donut = document.querySelector( '.ad-donut-wrap' );
		if ( donut ) {
			donut.querySelectorAll( '.ad-legend2 li' ).forEach( function ( li ) {
				var seg = li.dataset.seg;
				function marcar( on ) {
					var alvo = donut.querySelector( '.ad-ring__s[data-seg="' + seg + '"]' );
					if ( alvo ) { alvo.classList.toggle( 'is-hi', on ); }
					li.classList.toggle( 'is-hi', on );
				}
				li.addEventListener( 'mouseenter', function () { marcar( true ); } );
				li.addEventListener( 'mouseleave', function () { marcar( false ); } );
			} );
		}

		// Repete quando o painel volta a ficar visível. Observa-se o atributo
		// hidden em vez de tocar no show(), para a navegação ficar independente
		// das animações.
		var painel = document.querySelector( '.ad-charts[data-view="painel"]' );
		if ( painel && 'MutationObserver' in window ) {
			var antes = painel.hidden;
			new MutationObserver( function () {
				if ( antes && ! painel.hidden ) { tocar(); }
				antes = painel.hidden;
			} ).observe( painel, { attributes: true, attributeFilter: [ 'hidden' ] } );
		}

		tocar();
	} )();

	// --- Combobox pesquisável ---
	// Melhora um <select> existente: mantém-no no DOM (escondido) para o
	// formulário continuar a submeter na mesma e para funcionar sem JS.
	( function () {
		var abertos = [];

		function montar( sel ) {
			var opcoes = [].map.call( sel.options, function ( o ) {
				return { v: o.value, t: o.textContent.trim(), q: o.textContent.trim().toLowerCase() };
			} );
			if ( opcoes.length < 2 ) { return; }

			var caixa = document.createElement( 'div' );
			caixa.className = 'ge-combo';
			var campo = document.createElement( 'input' );
			campo.type = 'text';
			campo.className = 'ge-combo__in';
			campo.autocomplete = 'off';
			campo.placeholder = sel.dataset.comboPh || 'Escreva para procurar';
			campo.setAttribute( 'role', 'combobox' );
			campo.setAttribute( 'aria-expanded', 'false' );
			campo.setAttribute( 'aria-autocomplete', 'list' );
			if ( sel.getAttribute( 'aria-label' ) ) {
				campo.setAttribute( 'aria-label', sel.getAttribute( 'aria-label' ) );
			}
			var lista = document.createElement( 'ul' );
			lista.className = 'ge-combo__list';
			lista.setAttribute( 'role', 'listbox' );
			lista.hidden = true;
			lista.id = 'combo-' + Math.random().toString( 36 ).slice( 2, 8 );
			campo.setAttribute( 'aria-controls', lista.id );

			sel.parentNode.insertBefore( caixa, sel );
			caixa.appendChild( campo );
			caixa.appendChild( lista );
			caixa.appendChild( sel );
			sel.classList.add( 'ge-combo__nativo' );
			sel.tabIndex = -1;
			sel.setAttribute( 'aria-hidden', 'true' );

			var marcado = -1;

			function rotulo() {
				var o = sel.options[ sel.selectedIndex ];
				return o && o.value !== '' ? o.textContent.trim() : '';
			}
			function mostrarValor() {
				campo.value = rotulo();
			}

			function pintar( filtro ) {
				lista.innerHTML = '';
				var q = ( filtro || '' ).trim().toLowerCase();
				var vis = opcoes.filter( function ( o ) {
					return ! q || o.q.indexOf( q ) !== -1;
				} );
				if ( ! vis.length ) {
					var vazio = document.createElement( 'li' );
					vazio.className = 'ge-combo__none';
					vazio.textContent = 'Sem resultados';
					lista.appendChild( vazio );
					marcado = -1;
					return;
				}
				vis.forEach( function ( o, i ) {
					var li = document.createElement( 'li' );
					li.className = 'ge-combo__op';
					li.setAttribute( 'role', 'option' );
					li.dataset.v = o.v;
					li.id = lista.id + '-' + i;
					// Realça o troço procurado, para se ver porque entrou na lista.
					if ( q ) {
						var p = o.q.indexOf( q );
						li.appendChild( document.createTextNode( o.t.slice( 0, p ) ) );
						var b = document.createElement( 'b' );
						b.textContent = o.t.slice( p, p + q.length );
						li.appendChild( b );
						li.appendChild( document.createTextNode( o.t.slice( p + q.length ) ) );
					} else {
						li.textContent = o.t;
					}
					if ( o.v === sel.value ) { li.classList.add( 'is-sel' ); }
					lista.appendChild( li );
				} );
				marcado = 0;
				realcar();
			}

			function realcar() {
				var its = lista.querySelectorAll( '.ge-combo__op' );
				its.forEach( function ( li, i ) { li.classList.toggle( 'is-on', i === marcado ); } );
				if ( its[ marcado ] ) {
					campo.setAttribute( 'aria-activedescendant', its[ marcado ].id );
					var li = its[ marcado ], t = li.offsetTop, b = t + li.offsetHeight;
					if ( t < lista.scrollTop ) { lista.scrollTop = t; }
					else if ( b > lista.scrollTop + lista.clientHeight ) { lista.scrollTop = b - lista.clientHeight; }
				} else {
					campo.removeAttribute( 'aria-activedescendant' );
				}
			}

			function abrir() {
				fechar_outros();
				lista.hidden = false;
				campo.setAttribute( 'aria-expanded', 'true' );
				caixa.classList.add( 'is-open' );
			}
			function fechar() {
				lista.hidden = true;
				campo.setAttribute( 'aria-expanded', 'false' );
				campo.removeAttribute( 'aria-activedescendant' );
				caixa.classList.remove( 'is-open' );
				mostrarValor();
			}
			function fechar_outros() {
				abertos.forEach( function ( fn ) { fn(); } );
			}
			abertos.push( function () { if ( ! lista.hidden ) { fechar(); } } );

			function escolher( v ) {
				sel.value = v;
				// Dispara o change no select real: quem já ouvia continua a ouvir.
				sel.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				fechar();
			}

			campo.addEventListener( 'focus', function () { campo.select(); pintar( '' ); abrir(); } );
			campo.addEventListener( 'input', function () { pintar( campo.value ); abrir(); } );
			campo.addEventListener( 'keydown', function ( e ) {
				var its = lista.querySelectorAll( '.ge-combo__op' );
				if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) {
					e.preventDefault();
					if ( lista.hidden ) { pintar( '' ); abrir(); return; }
					marcado += ( 'ArrowDown' === e.key ? 1 : -1 );
					if ( marcado < 0 ) { marcado = its.length - 1; }
					if ( marcado >= its.length ) { marcado = 0; }
					realcar();
				} else if ( 'Enter' === e.key ) {
					if ( ! lista.hidden && its[ marcado ] ) {
						e.preventDefault();
						escolher( its[ marcado ].dataset.v );
					}
				} else if ( 'Escape' === e.key ) {
					if ( ! lista.hidden ) { e.stopPropagation(); fechar(); }
				} else if ( 'Tab' === e.key ) {
					fechar();
				}
			} );
			lista.addEventListener( 'mousedown', function ( e ) {
				var li = e.target.closest( '.ge-combo__op' );
				if ( ! li ) { return; }
				e.preventDefault();
				escolher( li.dataset.v );
			} );
			document.addEventListener( 'mousedown', function ( e ) {
				if ( ! caixa.contains( e.target ) && ! lista.hidden ) { fechar(); }
			} );

			mostrarValor();
		}

		document.querySelectorAll( 'select[data-combo]' ).forEach( montar );
	} )();
} )();
</script>
<?php
get_footer();
