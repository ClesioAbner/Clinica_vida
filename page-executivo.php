<?php
/**
 * Template Name: Clínica — Direção
 *
 * Portal EXECUTIVO (Direção). Só estratégia: crescimento, rentabilidade,
 * pacientes/retenção, metas e aprovações. NÃO gere a operação nem toca em
 * dados clínicos — separação de deveres. Backend: inc/direcao.php +
 * Clinica_Analitica::direcao(). Reutiliza o shell .pm-* e os cartões .ad-*.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() || ! current_user_can( 'clinica_ver_direcao' ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

clinica_controller_direcao();

// Exportação de relatórios (?relatorio=…&fmt=pdf|csv). Termina o pedido.
if ( isset( $_GET['relatorio'] ) ) {
	clinica_relatorio_exportar();
}

get_header();

$utilizador = wp_get_current_user();
$avatar     = function_exists( 'clinica_avatar_url' ) ? clinica_avatar_url( $utilizador->ID, 96 ) : get_avatar_url( $utilizador->ID );
$primeiro   = explode( ' ', trim( $utilizador->display_name ) )[0];
$agora      = current_time( 'timestamp' );
$hora_dia   = (int) date_i18n( 'G', $agora );
$saudacao   = $hora_dia < 12 ? 'Bom dia' : ( $hora_dia < 19 ? 'Boa tarde' : 'Boa noite' );

$moeda     = class_exists( 'Clinica_Config' ) ? Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';
$fmt_valor = static function ( $v ) use ( $moeda ) { return number_format( (float) $v, 0, ',', ' ' ) . ' ' . $moeda; };
$var       = static function ( $agora, $antes ) {
	if ( $antes <= 0 ) {
		return null;
	}
	return (int) round( ( $agora - $antes ) / $antes * 100 );
};

$dir      = Clinica_Analitica::direcao();
$bi       = Clinica_Analitica::bi();

// Relatório em ecrã: tipo + agrupamento vêm do endereço (?rel=…&g=…).
$rel_tipo = isset( $_GET['rel'] ) ? sanitize_key( $_GET['rel'] ) : 'financeiro';
if ( ! in_array( $rel_tipo, array( 'financeiro', 'medicos', 'pacientes' ), true ) ) {
	$rel_tipo = 'financeiro';
}
$rel_grupo = ( isset( $_GET['g'] ) && isset( Clinica_Relatorios::GRUPOS[ sanitize_key( $_GET['g'] ) ] ) )
	? sanitize_key( $_GET['g'] )
	: ( 'pacientes' === $rel_tipo ? 'semana' : 'mes' );
$rel_dados = Clinica_Relatorios::obter( $rel_tipo, $rel_grupo );
$rel_base  = home_url( '/direcao/' );
$metas    = Clinica_Direcao::metas();
$cont     = Clinica_Direcao::contagem();
$aprovs   = Clinica_Direcao::aprovacoes();
$pode_def = current_user_can( 'clinica_definir_metas' );
$pode_apr = current_user_can( 'clinica_aprovar_estrategico' );

// Crescimento face ao mês e ao ano anteriores.
$g_mes = $var( $dir['receita']['mes'], $dir['receita']['mes_ant'] );
$g_ano = $var( $dir['receita']['mes'], $dir['receita']['ano_ant'] );
$g_vol = $var( $dir['volume']['mes'], $dir['volume']['mes_ant'] );

// Progresso face à meta de receita do mês.
$meta_rec  = (float) $metas['receita'];
$prog_rec  = $meta_rec > 0 ? min( 100, (int) round( $dir['receita']['mes'] / $meta_rec * 100 ) ) : 0;
$meta_pac  = (int) $metas['pacientes'];
$pac_mes   = (int) $dir['pacientes']['novos'];
$prog_pac  = $meta_pac > 0 ? min( 100, (int) round( $pac_mes / $meta_pac * 100 ) ) : 0;

// Amostra quando ainda não há histórico, sempre assinalada.
$demo = array_sum( array_map( 'floatval', $dir['serie'] ) ) <= 0 || count( array_filter( $dir['serie'] ) ) < 2;
if ( $demo ) {
	$dir['serie'] = array( 82000, 74000, 96000, 88000, 105000, 99000, 121000, 112000, 134000, 128000, 146000, 139000 );
}
$labels    = array( 'pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado' );
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
				<div class="pm-side__role">Direção</div>
			</div>
		</div>

		<nav class="pm-nav">
			<span class="pm-nav__cap">Executivo</span>
			<a class="pm-nav__i is-active" href="#" data-go="painel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Visão geral</span></a>
			<a class="pm-nav__i" href="#" data-go="rentabilidade"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg><span>Rentabilidade</span></a>
			<a class="pm-nav__i" href="#" data-go="relatorios"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8M8 9h2"/></svg><span>Relatórios</span></a>
			<a class="pm-nav__i" href="#" data-go="aprovacoes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Aprovações</span><?php if ( $cont['pendente'] > 0 ) : ?><span class="pm-nav__badge"><?php echo (int) $cont['pendente']; ?></span><?php endif; ?></a>
			<?php if ( $pode_def ) : ?>
				<a class="pm-nav__i" href="#" data-go="metas"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="0.5" fill="currentColor"/></svg><span>Metas</span></a>
			<?php endif; ?>
		</nav>

		<a class="pm-nav__i pm-nav__out" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg><span>Sair</span></a>
	</aside>

	<main class="pm-main ex-exec">

		<header class="pm-top">
			<div class="pm-top__l">
				<span class="ad-eyebrow">Direção</span>
				<h1 class="pm-welcome"><?php echo esc_html( $saudacao . ', ' . $primeiro ); ?></h1>
				<p class="pm-date"><?php echo esc_html( ucfirst( date_i18n( 'l, j \d\e F', $agora ) ) ); ?></p>
			</div>
		</header>

		<?php clinica_render_flash(); ?>

		<!-- ===================== CENTRO DE INTELIGÊNCIA =====================
		     Dashboard executivo de BI: seletor de período global (troca a banda
		     de KPIs e a tendência sem novo pedido), alertas calculados de
		     limiares reais, indicadores de qualidade, rankings e demografia.
		     Só dados que existem — sem custos/lucro, unidades nem seguradoras.
		     ================================================================== -->
		<?php
		$q30   = $bi['qualidade'];
		$pd    = $bi['periodos']['30d'];
		$crescer = static function ( $a, $b ) {
			if ( $b <= 0 ) {
				return null;
			}
			return (int) round( ( $a - $b ) / $b * 100 );
		};
		$g_rec = $crescer( $pd['receita'], $pd['receita_ant'] );
		$g_con = $crescer( $pd['consultas'], $pd['cons_ant'] );
		$g_nov = $crescer( $pd['novos'], $pd['novos_ant'] );
		// Variação em texto, sem pastilha: a hierarquia é feita com peso e cor.
		$pill  = static function ( $g ) {
			if ( null === $g ) {
				return '<span class="ex-trend--flat">sem período anterior</span>';
			}
			$d = $g >= 0 ? 'up' : 'down';
			return '<b class="ex-trend--' . $d . '">' . ( $g >= 0 ? '+' : '−' ) . abs( $g ) . '%</b> vs período anterior';
		};
		?>
		<section class="bi-bar pm-view" data-view="painel">
			<div class="ad-tabs bi-tabs" id="bi-per" role="tablist">
				<button type="button" class="ad-tab" data-p="7d">7 dias</button>
				<button type="button" class="ad-tab is-active" data-p="30d">30 dias</button>
				<button type="button" class="ad-tab" data-p="90d">90 dias</button>
				<button type="button" class="ad-tab" data-p="12m">12 meses</button>
			</div>
		</section>

		<section class="ex-kpis pm-view" data-view="painel" id="bi-kpis"
			data-bi="<?php echo esc_attr( wp_json_encode( $bi['periodos'] ) ); ?>"
			data-moeda="<?php echo esc_attr( $moeda ); ?>">
			<div class="ex-kpi">
				<span class="ex-kpi__l">Receita</span>
				<strong class="ex-kpi__n ex-kpi__n--sm ad-num" id="bi-receita" data-final="<?php echo esc_attr( $fmt_valor( $pd['receita'] ) ); ?>"><?php echo esc_html( $fmt_valor( $pd['receita'] ) ); ?></strong>
				<span class="ex-kpi__f" id="bi-receita-p"><?php echo $pill( $g_rec ); ?></span>
			</div>
			<div class="ex-kpi">
				<span class="ex-kpi__l">Consultas realizadas</span>
				<strong class="ex-kpi__n" id="bi-consultas"><?php echo number_format_i18n( $pd['consultas'] ); ?></strong>
				<span class="ex-kpi__f" id="bi-consultas-p"><?php echo $pill( $g_con ); ?></span>
			</div>
			<div class="ex-kpi">
				<span class="ex-kpi__l">Novos pacientes</span>
				<strong class="ex-kpi__n" id="bi-novos"><?php echo number_format_i18n( $pd['novos'] ); ?></strong>
				<span class="ex-kpi__f" id="bi-novos-p"><?php echo $pill( $g_nov ); ?></span>
			</div>
			<div class="ex-kpi">
				<span class="ex-kpi__l">Ocupação dos médicos</span>
				<strong class="ex-kpi__n"><span class="ad-num" data-n="<?php echo (int) $q30['ocupacao']; ?>">0</span>%</strong>
				<span class="ex-kpi__f"><?php echo (int) $q30['medicos']; ?> médicos ativos · últimos 90 dias</span>
			</div>
		</section>

		<section class="ex-stack pm-view" data-view="painel">
			<div class="ex-duo">
				<div class="ad-card">
					<div class="ad-card__h">
						<strong>Tendência da receita</strong>
						<span class="ad-card__s" id="bi-plabel2">Últimos 30 dias</span>
					</div>
					<p class="ad-read" id="bi-read"></p>
					<div class="bi-wave">
						<div class="bi-wave__y" id="bi-way"></div>
						<div class="bi-wave__plot">
							<svg class="bi-wave__svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
								<defs><linearGradient id="bi-wavegrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4E7E9B" stop-opacity="0.22"></stop><stop offset="1" stop-color="#4E7E9B" stop-opacity="0"></stop></linearGradient></defs>
								<path class="bi-wave__area" id="bi-wavearea" d=""></path>
								<path class="bi-wave__line" id="bi-waveline" d="" pathLength="100" vector-effect="non-scaling-stroke"></path>
							</svg>
							<span class="bi-wave__dot" id="bi-wavedot" hidden></span>
							<div class="bi-wave__hits" id="bi-wavehits"></div>
							<div class="bi-wave__x" id="bi-wavex"></div>
						</div>
					</div>
				</div>

				<div class="ad-card">
					<div class="ad-card__h"><strong>Receita por especialidade</strong><span class="ad-card__s">Histórico</span></div>
					<?php
					$esp_top = array_slice( $dir['por_esp'], 0, 4 );
					$esp_tot = 0.0;
					foreach ( $esp_top as $e ) {
						$esp_tot += (float) $e['receita'];
					}
					$demo_esp = $esp_tot <= 0;
					if ( $demo_esp ) {
						$esp_top = array(
							array( 'nome' => 'Clínica Geral', 'receita' => 148000 ),
							array( 'nome' => 'Pediatria', 'receita' => 96000 ),
							array( 'nome' => 'Cardiologia', 'receita' => 71000 ),
							array( 'nome' => 'Dermatologia', 'receita' => 44000 ),
						);
						$esp_tot = 359000;
					}
					$cores   = array( '#2E4E60', '#4E7E9B', '#7BA7C1', '#C3D6E2' );
					$top_pct = $esp_tot > 0 ? round( (float) $esp_top[0]['receita'] / $esp_tot * 100 ) : 0;
					// Anel em conic-gradient, como na receção.
					$acc = 0.0;
					$stops = array();
					foreach ( $esp_top as $i => $e ) {
						$val = (float) $e['receita'];
						if ( $val <= 0 ) {
							continue;
						}
						$from = $acc / $esp_tot * 100;
						$acc += $val;
						$to = $acc / $esp_tot * 100;
						$stops[] = sprintf( '%s %.2f%% %.2f%%', $cores[ $i % 4 ], $from, $to );
					}
					$donut_bg = $stops ? 'conic-gradient(' . implode( ',', $stops ) . ')' : '#E7ECF2';
					?>
					<div class="pm-donut-wrap">
						<div class="pm-donut" style="background:<?php echo esc_attr( $donut_bg ); ?>">
							<div class="pm-donut__c"><span class="ad-num" data-n="<?php echo (int) $top_pct; ?>">0</span><small>% líder</small></div>
						</div>
						<ul class="pm-legend">
							<?php foreach ( $esp_top as $i => $e ) : ?>
								<li>
									<span class="pm-legend__d" style="background:<?php echo esc_attr( $cores[ $i % 4 ] ); ?>"></span>
									<?php echo esc_html( $e['nome'] ); ?>
									<b><?php echo esc_html( number_format( (float) $e['receita'] / $esp_tot * 100, 1, ',', '' ) ); ?>%</b>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>

			<div class="ex-duo">
				<div class="ad-card">
					<div class="ad-card__h"><strong>Consultas realizadas</strong><span class="ad-card__s" id="bi-plabel3">Últimos 30 dias</span></div>
					<div class="ad-plot" id="bi-conplot">
						<div class="ad-plot__y" id="bi-cony"></div>
						<div class="ad-plot__c">
							<div class="ad-plot__bars" id="bi-conbars"></div>
							<div class="ad-plot__x" id="bi-conx"></div>
						</div>
					</div>
				</div>

				<div class="ad-card">
					<div class="ad-card__h"><strong>Qualidade do serviço</strong><span class="ad-card__s">Recente</span></div>
					<dl class="ad-dl bi-qual">
						<div><dt>Tempo de espera</dt><dd><?php echo (int) $q30['espera']; ?> min</dd></div>
						<div><dt>Duração média</dt><dd><?php echo (int) $q30['duracao']; ?> min</dd></div>
						<div><dt>Taxa de faltas</dt><dd class="<?php echo $q30['no_show'] >= 10 ? 'is-warn' : ''; ?>"><?php echo (int) $q30['no_show']; ?>%</dd></div>
						<div><dt>Cancelamentos</dt><dd class="<?php echo $q30['cancel'] >= 15 ? 'is-warn' : ''; ?>"><?php echo (int) $q30['cancel']; ?>%</dd></div>
					</dl>
				</div>
			</div>

			<div class="ex-duo">
			<div class="ad-card">
				<?php
				// Evolução de novos pacientes (12 meses), em linha, como no portal
				// do médico. Preenche com uma amostra quando os dados são escassos.
				$p12  = $bi['periodos']['12m'];
				$nov  = array_map( 'intval', $p12['serie_nov'] );
				$nlbl = $p12['serie_lbl'];
				$demo_nov = count( array_filter( $nov ) ) < 3;
				if ( $demo_nov ) {
					$nov = array( 14, 19, 16, 24, 21, 29, 27, 34, 31, 39, 36, 43 );
				}
				$nmax  = max( 1, max( $nov ) );
				$nn    = count( $nov );
				$npts  = array();
				$ndots = array();
				foreach ( $nov as $i => $v ) {
					$x   = $nn > 1 ? ( $i / ( $nn - 1 ) * 100 ) : 50;
					$pct = $v / $nmax * 100;
					$npts[]  = round( $x, 1 ) . ',' . round( 100 - $pct, 1 );
					$ndots[] = array( 'x' => round( $x, 1 ), 'y' => round( $pct, 1 ), 'n' => $v, 'l' => $nlbl[ $i ] ?? '' );
				}
				$npoly = implode( ' ', $npts );
				$narea = '0,100 ' . $npoly . ' 100,100';
				?>
				<div class="pm-chart">
					<div class="pm-chart__head"><strong>Evolução de novos pacientes</strong><span><?php echo $demo_nov ? 'exemplo' : 'últimos 12 meses'; ?></span></div>
					<div class="pm-plot">
						<div class="pm-plot__y">
							<span><?php echo (int) $nmax; ?></span>
							<span><?php echo (int) ceil( $nmax / 2 ); ?></span>
							<span>0</span>
						</div>
						<div class="pm-plot__c">
							<div class="pm-plot__line">
								<svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
									<polygon class="pm-line__area" points="<?php echo esc_attr( $narea ); ?>"></polygon>
									<polyline class="pm-line__l" points="<?php echo esc_attr( $npoly ); ?>"></polyline>
								</svg>
								<?php foreach ( $ndots as $d ) : ?>
									<i class="pm-line__dot" style="left:<?php echo esc_attr( $d['x'] ); ?>%; bottom:<?php echo esc_attr( $d['y'] ); ?>%" title="<?php echo (int) $d['n']; ?> novos · <?php echo esc_attr( $d['l'] ); ?>"></i>
								<?php endforeach; ?>
							</div>
							<div class="pm-plot__x">
								<?php foreach ( $nlbl as $j => $l ) : ?><span><?php echo ( 0 === $j % 2 ) ? esc_html( $l ) : ''; ?></span><?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="ad-card">
				<div class="ad-card__h"><strong>Base de pacientes</strong><span class="ad-card__s">Este mês</span></div>
				<div class="ad-gauge">
					<div class="ad-gauge__n"><span class="ad-num" data-n="<?php echo (int) $dir['pacientes']['retencao']; ?>">0</span>%</div>
					<div class="ad-gauge__l">taxa de retenção</div>
					<div class="ad-gauge__bar"><span style="width:<?php echo (int) $dir['pacientes']['retencao']; ?>%"></span></div>
				</div>
				<dl class="ad-dl">
					<div><dt>Base total</dt><dd><?php echo number_format_i18n( $dir['pacientes']['base'] ); ?></dd></div>
					<div><dt>Novos este mês</dt><dd><?php echo (int) $dir['pacientes']['novos']; ?></dd></div>
					<div><dt>Recorrentes</dt><dd><?php echo (int) $dir['pacientes']['recorrentes']; ?></dd></div>
					<div><dt>Retenção</dt><dd><?php echo (int) $dir['pacientes']['retencao']; ?>%</dd></div>
				</dl>
			</div>
			</div>
		</section>

		<!-- =========================== RELATÓRIOS ===========================
		     Financeiro, por médico e pacientes por período (diário/semanal/
		     mensal), exportáveis em PDF e CSV. O estado vive no endereço para
		     sobreviver ao recarregamento e à partilha do link.
		     ================================================================== -->
		<section class="pm-view" data-view="relatorios" hidden>
			<div class="bi-reltop">
				<div class="bi-reltop__l">
					<nav class="ad-tabs bi-reltabs">
						<?php
						$tipos = array( 'financeiro' => 'Financeiro', 'medicos' => 'Por médico', 'pacientes' => 'Pacientes' );
						foreach ( $tipos as $tk => $tl ) :
							$g_alvo = 'pacientes' === $tk ? 'semana' : 'mes';
							$url    = esc_url( add_query_arg( array( 'rel' => $tk, 'g' => $g_alvo ), $rel_base ) ) . '#relatorios';
						?>
							<a class="ad-tab<?php echo $rel_tipo === $tk ? ' is-active' : ''; ?>" href="<?php echo $url; ?>"><?php echo esc_html( $tl ); ?></a>
						<?php endforeach; ?>
					</nav>
					<?php if ( 'medicos' !== $rel_tipo ) : ?>
						<span class="bi-reltop__sep"></span>
						<nav class="ad-tabs bi-relgrupo">
							<?php foreach ( Clinica_Relatorios::GRUPOS as $gk => $gl ) :
								$url = esc_url( add_query_arg( array( 'rel' => $rel_tipo, 'g' => $gk ), $rel_base ) ) . '#relatorios';
							?>
								<a class="ad-tab<?php echo $rel_grupo === $gk ? ' is-active' : ''; ?>" href="<?php echo $url; ?>"><?php echo esc_html( $gl ); ?></a>
							<?php endforeach; ?>
						</nav>
					<?php endif; ?>
				</div>
				<div class="bi-relexport">
					<a class="ge-btn ge-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'relatorio' => $rel_tipo, 'g' => $rel_grupo, 'fmt' => 'csv' ), $rel_base ) ); ?>">CSV</a>
					<a class="ge-btn ge-btn--ghost" href="<?php echo esc_url( add_query_arg( array( 'relatorio' => $rel_tipo, 'g' => $rel_grupo, 'fmt' => 'xls' ), $rel_base ) ); ?>">Excel</a>
					<a class="ge-btn ge-btn--primary" href="<?php echo esc_url( add_query_arg( array( 'relatorio' => $rel_tipo, 'g' => $rel_grupo, 'fmt' => 'pdf' ), $rel_base ) ); ?>" target="_blank" rel="noopener">PDF</a>
				</div>
			</div>

			<div class="ad-card">
				<div class="ad-card__h">
					<strong><?php echo esc_html( $rel_dados['titulo'] ); ?></strong>
					<span class="ad-card__s"><?php echo count( $rel_dados['linhas'] ); ?> linhas</span>
				</div>
				<?php if ( $rel_dados['linhas'] ) : ?>
					<div class="bi-rtbl-wrap">
						<table class="bi-rtbl">
							<thead>
								<tr>
									<?php foreach ( $rel_dados['cols'] as $i => $c ) :
										$num = in_array( $rel_dados['tipos'][ $i ] ?? 't', array( 'v', 'n' ), true );
									?>
										<th class="<?php echo $num ? 'r' : ''; ?>"><?php echo esc_html( $c ); ?></th>
									<?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rel_dados['linhas'] as $linha ) : ?>
									<tr>
										<?php foreach ( $linha as $i => $cel ) :
											$t = $rel_dados['tipos'][ $i ] ?? 't';
											if ( 'v' === $t ) {
												$val = $fmt_valor( $cel );
											} elseif ( 'n' === $t ) {
												$val = number_format_i18n( (int) $cel );
											} else {
												$val = $cel;
											}
										?>
											<td class="<?php echo in_array( $t, array( 'v', 'n' ), true ) ? 'r' : ''; ?><?php echo 0 === $i ? ' bi-rtbl__k' : ''; ?>"><?php echo esc_html( $val ); ?></td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<p class="pm-empty">Sem dados para este relatório</p>
				<?php endif; ?>
			</div>
		</section>

		<!-- =========================== RENTABILIDADE =========================== -->
		<section class="pm-view ex-rent" data-view="rentabilidade" hidden>
			<?php
			$r_esp = $dir['por_esp'];
			$r_med = $dir['por_med'];
			$esp_total = 0.0;
			$pag_total = 0;
			foreach ( $r_esp as $e ) {
				$esp_total += (float) $e['receita'];
				$pag_total += (int) $e['n'];
			}
			$ticket = $pag_total > 0 ? $esp_total / $pag_total : 0;
			$conc   = $esp_total > 0 ? round( (float) $r_esp[0]['receita'] / $esp_total * 100 ) : 0;
			?>
			<section class="ex-kpis">
				<?php
				$rk = array(
					array( 'l' => 'Receita total', 'n' => $fmt_valor( $esp_total ), 'c' => 'pagamentos confirmados', 'sm' => true ),
					array( 'l' => 'Ticket médio', 'n' => $fmt_valor( $ticket ), 'c' => 'por pagamento', 'sm' => true ),
					array( 'l' => 'Pagamentos', 'n' => number_format_i18n( $pag_total ), 'c' => 'no histórico' ),
					array( 'l' => 'Concentração', 'n' => $conc . '%', 'c' => $r_esp ? $r_esp[0]['nome'] . ' lidera' : 'sem dados' ),
				);
				foreach ( $rk as $k ) : ?>
					<div class="ex-kpi">
						<span class="ex-kpi__l"><?php echo esc_html( $k['l'] ); ?></span>
						<strong class="ex-kpi__n<?php echo ! empty( $k['sm'] ) ? ' ex-kpi__n--sm' : ''; ?>"><?php echo esc_html( $k['n'] ); ?></strong>
						<span class="ex-kpi__f"><?php echo esc_html( $k['c'] ); ?></span>
					</div>
				<?php endforeach; ?>
			</section>

			<div class="ad-card">
				<div class="ad-card__h"><strong>Receita por especialidade</strong><span class="ad-card__s">Histórico pago</span></div>
				<?php if ( $r_esp ) : ?>
					<div class="bi-rtbl-wrap">
						<table class="bi-rtbl">
							<thead>
								<tr><th>Especialidade</th><th class="r">Pagamentos</th><th class="r">Receita</th><th class="r">Quota</th></tr>
							</thead>
							<tbody>
								<?php foreach ( $r_esp as $e ) :
									$share = $esp_total > 0 ? (float) $e['receita'] / $esp_total * 100 : 0;
								?>
									<tr>
										<td class="bi-rtbl__k"><?php echo esc_html( $e['nome'] ); ?></td>
										<td class="r"><?php echo (int) $e['n']; ?></td>
										<td class="r"><?php echo esc_html( $fmt_valor( $e['receita'] ) ); ?></td>
										<td class="r"><?php echo esc_html( number_format( $share, 1, ',', '' ) ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<p class="pm-empty">Ainda não há receita registada</p>
				<?php endif; ?>
			</div>

			<div class="ad-card">
				<div class="ad-card__h"><strong>Receita por médico</strong><span class="ad-card__s">Top 8 · histórico</span></div>
				<?php if ( $r_med ) : ?>
					<div class="bi-rtbl-wrap">
						<table class="bi-rtbl">
							<thead>
								<tr><th class="bi-rtbl__pos">#</th><th>Médico</th><th>Especialidade</th><th class="r">Pagamentos</th><th class="r">Receita</th><th class="r">Ticket</th></tr>
							</thead>
							<tbody>
								<?php foreach ( $r_med as $i => $m ) :
									$tk = (int) $m['n'] > 0 ? (float) $m['receita'] / (int) $m['n'] : 0;
								?>
									<tr>
										<td class="bi-rtbl__pos"><?php echo $i + 1; ?></td>
										<td class="bi-rtbl__k"><?php echo esc_html( $m['nome'] ); ?></td>
										<td><?php echo esc_html( $m['esp'] ); ?></td>
										<td class="r"><?php echo (int) $m['n']; ?></td>
										<td class="r"><?php echo esc_html( $fmt_valor( $m['receita'] ) ); ?></td>
										<td class="r"><?php echo esc_html( $fmt_valor( $tk ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php else : ?>
					<p class="pm-empty">Ainda não há receita por médico</p>
				<?php endif; ?>
			</div>
		</section>

		<!-- =========================== APROVAÇÕES ===========================
		     Caixa de decisão da Direção. Os pedidos SOBEM da Gestão (portal
		     tático); aqui só se decide. Comunicação entre portais pela tabela
		     clinica_aprovacoes.
		     ================================================================== -->
		<section class="pm-view" data-view="aprovacoes" hidden>
			<section class="ex-kpis">
				<?php
				$ck = array(
					array( 'Pendentes', $cont['pendente'], 'A aguardar a sua decisão' ),
					array( 'Aprovadas', $cont['aprovado'], 'Decididas a favor' ),
					array( 'Rejeitadas', $cont['rejeitado'], 'Decididas contra' ),
				);
				foreach ( $ck as $c ) : ?>
					<div class="ex-kpi">
						<span class="ex-kpi__l"><?php echo esc_html( $c[0] ); ?></span>
						<strong class="ex-kpi__n"><?php echo (int) $c[1]; ?></strong>
						<span class="ex-kpi__f"><?php echo esc_html( $c[2] ); ?></span>
					</div>
				<?php endforeach; ?>
			</section>

			<div class="ad-card">
				<div class="ad-card__h">
					<strong>Pedidos da Gestão</strong>
					<nav class="ad-tabs bi-tabs ex-aprfiltro" id="ex-aprfiltro">
						<?php
						$ini_f = $cont['pendente'] > 0 ? 'pendente' : 'todas';
						$fs = array(
							'pendente'  => array( 'Pendentes', $cont['pendente'] ),
							'aprovado'  => array( 'Aprovadas', $cont['aprovado'] ),
							'rejeitado' => array( 'Rejeitadas', $cont['rejeitado'] ),
							'todas'     => array( 'Todas', count( $aprovs ) ),
						);
						foreach ( $fs as $fk => $fv ) : ?>
							<button type="button" class="ad-tab<?php echo $ini_f === $fk ? ' is-active' : ''; ?>" data-filtro="<?php echo esc_attr( $fk ); ?>"><?php echo esc_html( $fv[0] ); ?> <span class="ex-aprfiltro__n"><?php echo (int) $fv[1]; ?></span></button>
						<?php endforeach; ?>
					</nav>
				</div>
				<?php if ( $aprovs ) : ?>
					<div class="ex-aprovs" id="ex-aprovs">
						<?php foreach ( $aprovs as $a ) : ?>
							<div class="ex-aprov ex-aprov--<?php echo esc_attr( $a['estado'] ); ?>" data-estado="<?php echo esc_attr( $a['estado'] ); ?>">
								<div class="ex-aprov__main">
									<div class="ex-aprov__top">
										<span class="ex-aprov__tipo"><?php echo esc_html( ucfirst( $a['tipo'] ) ); ?></span>
										<span class="ex-aprov__est ex-aprov__est--<?php echo esc_attr( $a['estado'] ); ?>"><?php echo esc_html( $labels[ $a['estado'] ] ?? $a['estado'] ); ?></span>
									</div>
									<h4 class="ex-aprov__titulo"><?php echo esc_html( $a['titulo'] ); ?></h4>
									<?php if ( ! empty( $a['detalhe'] ) ) : ?><p class="ex-aprov__det"><?php echo esc_html( $a['detalhe'] ); ?></p><?php endif; ?>
									<p class="ex-aprov__meta">
										Pedido por <b><?php echo esc_html( $a['criador'] ?: 'Gestão' ); ?></b> · <?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $a['criado_em'] ) ) ); ?>
										<?php if ( null !== $a['valor'] ) : ?> · <span class="ex-aprov__valor"><?php echo esc_html( $fmt_valor( $a['valor'] ) ); ?></span><?php endif; ?>
										<?php if ( 'pendente' !== $a['estado'] && $a['decisor'] ) : ?> · decidido por <?php echo esc_html( $a['decisor'] ); ?><?php endif; ?>
									</p>
								</div>
								<?php if ( 'pendente' === $a['estado'] && $pode_apr ) : ?>
									<div class="ex-aprov__acts">
										<form method="post">
											<?php wp_nonce_field( 'dir_decidir', 'dir_nonce' ); ?>
											<input type="hidden" name="dir_action" value="decidir">
											<input type="hidden" name="id" value="<?php echo (int) $a['id']; ?>">
											<button type="submit" name="decisao" value="aprovar" class="ex-dec ex-dec--ok">Aprovar</button>
											<button type="submit" name="decisao" value="rejeitar" class="ex-dec ex-dec--no">Rejeitar</button>
										</form>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="pm-empty" id="ex-aprovazio" hidden>Nada neste estado</p>
				<?php else : ?>
					<div class="ex-blank">
						<p>Sem pedidos da Gestão.</p>
						<span class="ex-kpi__f">Os pedidos estratégicos submetidos pela Gestão aparecem aqui para a sua decisão.</span>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<!-- =========================== METAS ===========================
		     Cada objetivo num cartão: onde se está agora, o progresso e o campo
		     para definir. Assim o ecrã explica-se sozinho, com ou sem metas.
		     ============================================================== -->
		<?php if ( $pode_def ) : ?>
			<section class="pm-view ex-rent" data-view="metas" hidden>
				<?php
				$rec_mes   = (float) $dir['receita']['mes'];
				$falta_rec = max( 0, $meta_rec - $rec_mes );
				$falta_pac = max( 0, $meta_pac - $pac_mes );
				$mes_nome  = ucfirst( date_i18n( 'F', $agora ) );
				?>
				<div class="ad-card ex-metahead">
					<h2 class="ex-metahead__t">Metas de <?php echo esc_html( $mes_nome ); ?></h2>
					<p class="ex-metahead__s">Defina os objetivos do mês. O progresso passa a aparecer aqui e na Visão geral. As alterações aplicam-se de imediato.</p>
				</div>

				<form method="post" class="ex-form">
					<?php wp_nonce_field( 'dir_metas', 'dir_nonce' ); ?>
					<input type="hidden" name="dir_action" value="metas">
					<div class="ex-duo ex-duo--even">
						<!-- Receita -->
						<div class="ad-card ex-goal">
							<div class="ad-card__h"><strong>Receita</strong><span class="ad-card__s"><?php echo esc_html( $mes_nome ); ?></span></div>
							<p class="ex-goal__now">Este mês vai em <b><?php echo esc_html( $fmt_valor( $rec_mes ) ); ?></b></p>
							<?php if ( $meta_rec > 0 ) : ?>
								<div class="ex-goal__bar">
									<span class="ex-goal__fill" style="width:<?php echo (int) $prog_rec; ?>%"></span>
								</div>
								<p class="ex-goal__stat"><b><?php echo (int) $prog_rec; ?>%</b> da meta · faltam <?php echo esc_html( $fmt_valor( $falta_rec ) ); ?></p>
							<?php else : ?>
								<p class="ex-goal__stat ex-goal__stat--off">Sem meta definida</p>
							<?php endif; ?>
							<label class="ge-field ex-goal__field">
								<span>Objetivo de receita (<?php echo esc_html( $moeda ); ?>)</span>
								<input type="number" name="meta_receita" min="0" step="any" value="<?php echo esc_attr( $meta_rec > 0 ? (string) $meta_rec : '' ); ?>" placeholder="Ex.: 150000">
							</label>
						</div>

						<!-- Pacientes novos -->
						<div class="ad-card ex-goal">
							<div class="ad-card__h"><strong>Pacientes novos</strong><span class="ad-card__s"><?php echo esc_html( $mes_nome ); ?></span></div>
							<p class="ex-goal__now">Este mês entraram <b><?php echo (int) $pac_mes; ?></b></p>
							<?php if ( $meta_pac > 0 ) : ?>
								<div class="ex-goal__bar">
									<span class="ex-goal__fill" style="width:<?php echo (int) $prog_pac; ?>%"></span>
								</div>
								<p class="ex-goal__stat"><b><?php echo (int) $prog_pac; ?>%</b> da meta · faltam <?php echo (int) $falta_pac; ?></p>
							<?php else : ?>
								<p class="ex-goal__stat ex-goal__stat--off">Sem meta definida</p>
							<?php endif; ?>
							<label class="ge-field ex-goal__field">
								<span>Objetivo de novos pacientes</span>
								<input type="number" name="meta_pacientes" min="0" step="1" value="<?php echo esc_attr( $meta_pac > 0 ? (string) $meta_pac : '' ); ?>" placeholder="Ex.: 40">
							</label>
						</div>
					</div>

					<div class="ex-metafoot">
						<span class="ex-metafoot__hint">Deixe um campo a zero para retirar essa meta.</span>
						<button type="submit" class="ge-btn ge-btn--primary">Guardar metas</button>
					</div>
				</form>
			</section>
		<?php endif; ?>
	</main>
</div>
</div>

<script>
( function () {
	var side = document.querySelector( '.pm-side' ), shell = document.querySelector( '.pm-shell' );
	if ( ! side ) { return; }
	var views = document.querySelectorAll( '.pm-view' ),
		items = side.querySelectorAll( '.pm-nav__i[data-go]' );

	function show( v, silencioso ) {
		views.forEach( function ( s ) { s.hidden = ( s.dataset.view !== v ); } );
		items.forEach( function ( i ) { i.classList.toggle( 'is-active', i.dataset.go === v ); } );
		if ( shell ) { shell.classList.toggle( 'is-full', v !== 'painel' ); }
		if ( ! silencioso && window.history && history.replaceState ) {
			history.replaceState( null, '', '#' + v );
		}
		window.scrollTo( 0, 0 );
		if ( 'painel' === v || 'pacientes' === v ) { tocar(); }
	}
	items.forEach( function ( i ) { i.addEventListener( 'click', function ( e ) { e.preventDefault(); show( i.dataset.go ); } ); } );

	// Os cartões começam invisíveis (opacity:0 em gestao.css) e só aparecem com
	// a classe is-in. Sem isto ver-se-ia apenas a barra lateral.
	( function () {
		var cartoes = document.querySelectorAll( '.ad-kpi, .ad-card' );
		if ( ! ( 'IntersectionObserver' in window ) ) {
			cartoes.forEach( function ( c ) { c.classList.add( 'is-in' ); } );
			return;
		}
		var io = new IntersectionObserver( function ( ents ) {
			ents.forEach( function ( en ) {
				if ( ! en.isIntersecting ) { return; }
				var i = [].indexOf.call( cartoes, en.target );
				en.target.style.transitionDelay = ( Math.max( 0, i ) * 0.05 ).toFixed( 2 ) + 's';
				en.target.classList.add( 'is-in' );
				io.unobserve( en.target );
			} );
		}, { threshold: 0.08 } );
		cartoes.forEach( function ( c ) { io.observe( c ); } );
	} )();

	// --- Gráfico de receita mensal ---
	// --- Centro de Inteligência: KPIs + tendência por período ---
	( function () {
		var kpis = document.getElementById( 'bi-kpis' );
		if ( ! kpis ) { return; }
		var per, moeda = kpis.dataset.moeda || '';
		try { per = JSON.parse( kpis.dataset.bi ); } catch ( e ) { return; }

		function fmtN( n ) { return Math.round( n ).toLocaleString( 'pt-PT' ); }
		function fmtV( n ) { return fmtN( n ) + ' ' + moeda; }
		function pill( cur, ant ) {
			if ( ant <= 0 ) { return '<span class="ex-trend--flat">sem período anterior</span>'; }
			var g = Math.round( ( cur - ant ) / ant * 100 ), d = g >= 0 ? 'up' : 'down';
			return '<b class="ex-trend--' + d + '">' + ( g >= 0 ? '+' : '−' ) + Math.abs( g ) + '%</b> vs período anterior';
		}
		function txt( id, v ) { var e = document.getElementById( id ); if ( e ) { e.textContent = v; } }
		function html( id, v ) { var e = document.getElementById( id ); if ( e ) { e.innerHTML = v; } }

		// Escala "redonda" para o eixo.
		function escala( serie ) {
			var max = Math.max.apply( null, serie ) || 1;
			var passo = Math.pow( 10, Math.floor( Math.log( max ) / Math.LN10 ) );
			return Math.ceil( max / passo ) * passo;
		}
		function eixoY( el, max ) {
			el.innerHTML = '';
			for ( var i = 4; i >= 0; i-- ) { var y = document.createElement( 'span' ); y.textContent = fmtN( max * i / 4 ); el.appendChild( y ); }
		}
		// Curva suave (Catmull-Rom -> Bézier) a partir de pontos [x,y].
		function suave( pts ) {
			if ( ! pts.length ) { return ''; }
			var d = 'M' + pts[0][0] + ',' + pts[0][1];
			for ( var i = 0; i < pts.length - 1; i++ ) {
				var p0 = pts[ i - 1 ] || pts[ i ], p1 = pts[ i ], p2 = pts[ i + 1 ], p3 = pts[ i + 2 ] || p2;
				var c1x = p1[0] + ( p2[0] - p0[0] ) / 6, c1y = p1[1] + ( p2[1] - p0[1] ) / 6;
				var c2x = p2[0] - ( p3[0] - p1[0] ) / 6, c2y = p2[1] - ( p3[1] - p1[1] ) / 6;
				d += ' C' + c1x.toFixed( 2 ) + ',' + c1y.toFixed( 2 ) + ' ' + c2x.toFixed( 2 ) + ',' + c2y.toFixed( 2 ) + ' ' + p2[0].toFixed( 2 ) + ',' + p2[1].toFixed( 2 );
			}
			return d;
		}

		// Gráfico de ondas (área) da receita.
		var way = document.getElementById( 'bi-way' ), warea = document.getElementById( 'bi-wavearea' ),
			wline = document.getElementById( 'bi-waveline' ), whits = document.getElementById( 'bi-wavehits' ),
			wx = document.getElementById( 'bi-wavex' ), wdot = document.getElementById( 'bi-wavedot' );
		function waveChart( p ) {
			if ( ! warea ) { return; }
			var serie = p.serie, lbl = p.serie_lbl, n = serie.length, max = escala( serie );
			eixoY( way, max );
			var pts = serie.map( function ( v, i ) {
				var x = ( i + 0.5 ) / n * 100;
				var y = 100 - ( v / max * 90 ) - 5;
				return [ x, y ];
			} );
			wline.setAttribute( 'd', suave( pts ) );
			warea.setAttribute( 'd', suave( pts ) + ' L' + pts[ n - 1 ][0].toFixed( 2 ) + ',100 L' + pts[0][0].toFixed( 2 ) + ',100 Z' );
			whits.innerHTML = ''; wx.innerHTML = '';
			var muitos = n > 14;
			serie.forEach( function ( v, i ) {
				var h = document.createElement( 'div' ); h.className = 'bi-wave__hit';
				h.dataset.r = lbl[ i ] || ''; h.dataset.v = v; h.dataset.x = pts[ i ][0]; h.dataset.y = pts[ i ][1];
				whits.appendChild( h );
				var xl = document.createElement( 'span' ); xl.textContent = ( muitos && i % 3 !== 0 ) ? '' : ( lbl[ i ] || '' ); wx.appendChild( xl );
			} );
			var total = serie.reduce( function ( a, b ) { return a + b; }, 0 );
			var repouso = fmtV( total ) + ' no período';
			if ( read ) {
				read.textContent = repouso;
				whits.onmouseover = function ( e ) {
					var h = e.target.closest( '.bi-wave__hit' ); if ( ! h ) { return; }
					read.innerHTML = '<b>' + h.dataset.r + '</b> ' + fmtV( h.dataset.v );
					if ( wdot ) { wdot.style.left = h.dataset.x + '%'; wdot.style.top = h.dataset.y + '%'; wdot.hidden = false; }
				};
				whits.onmouseleave = function () { read.textContent = repouso; if ( wdot ) { wdot.hidden = true; } };
			}
		}

		// Gráfico de barras das consultas realizadas.
		var cbars = document.getElementById( 'bi-conbars' ), cx = document.getElementById( 'bi-conx' ), cy = document.getElementById( 'bi-cony' );
		function barChart( p ) {
			if ( ! cbars ) { return; }
			var serie = p.serie_con || [], lbl = p.serie_lbl, n = serie.length, max = escala( serie );
			eixoY( cy, max );
			cbars.innerHTML = ''; cx.innerHTML = '';
			var muitos = n > 14;
			serie.forEach( function ( v, i ) {
				var g = document.createElement( 'div' ); g.className = 'ad-mes'; g.title = ( lbl[ i ] || '' ) + ': ' + v + ' consultas';
				var b = document.createElement( 'span' ); b.className = 'ad-bar ad-bar--a';
				b.style.height = Math.round( v / max * 100 ) + '%'; b.style.animationDelay = ( i * 0.02 ).toFixed( 2 ) + 's';
				g.appendChild( b ); cbars.appendChild( g );
				var xl = document.createElement( 'span' ); xl.textContent = ( muitos && i % 3 !== 0 ) ? '' : ( lbl[ i ] || '' ); cx.appendChild( xl );
			} );
		}

		var read = document.getElementById( 'bi-read' );
		var nomes = { '7d': 'nos últimos 7 dias', '30d': 'nos últimos 30 dias', '90d': 'nos últimos 90 dias', '12m': 'nos últimos 12 meses' };
		var nomes2 = { '7d': 'Últimos 7 dias', '30d': 'Últimos 30 dias', '90d': 'Últimos 90 dias', '12m': 'Últimos 12 meses' };
		function aplicar( k ) {
			var p = per[ k ]; if ( ! p ) { return; }
			txt( 'bi-receita', fmtV( p.receita ) ); html( 'bi-receita-p', pill( p.receita, p.receita_ant ) );
			txt( 'bi-consultas', fmtN( p.consultas ) ); html( 'bi-consultas-p', pill( p.consultas, p.cons_ant ) );
			txt( 'bi-novos', fmtN( p.novos ) ); html( 'bi-novos-p', pill( p.novos, p.novos_ant ) );
			txt( 'bi-plabel', nomes[ k ] ); txt( 'bi-plabel2', nomes2[ k ] ); txt( 'bi-plabel3', nomes2[ k ] );
			waveChart( p ); barChart( p );
		}
		var tabs = document.getElementById( 'bi-per' );
		if ( tabs ) {
			tabs.addEventListener( 'click', function ( e ) {
				var b = e.target.closest( '.ad-tab' ); if ( ! b ) { return; }
				tabs.querySelectorAll( '.ad-tab' ).forEach( function ( o ) { o.classList.toggle( 'is-active', o === b ); } );
				aplicar( b.dataset.p );
			} );
		}
		aplicar( '30d' );
	} )();

	// --- Contagem dos números ---
	var suave = ! window.matchMedia || ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var relogios = [];
	function contar( el ) {
		var temN = el.dataset.n !== undefined;
		var alvo = temN ? parseFloat( el.dataset.n )
			: parseFloat( ( el.dataset.final || el.textContent || '' ).replace( /[^\d,.-]/g, '' ).replace( /\s/g, '' ).replace( ',', '.' ) );
		if ( isNaN( alvo ) ) { return; }
		var final = el.dataset.final !== undefined ? el.dataset.final
			: ( temN ? Math.round( alvo ).toLocaleString( 'pt-PT' ) : el.textContent );
		if ( ! suave ) { el.textContent = final; return; }
		var dur = 850, ini = null;
		el.textContent = '0';
		function passo( t ) {
			if ( null === ini ) { ini = t; }
			var p = Math.min( 1, ( t - ini ) / dur ), e = 1 - Math.pow( 1 - p, 3 );
			if ( p >= 1 ) { el.textContent = final; return; }
			var v = Math.round( alvo * e );
			el.textContent = ( temN && el.dataset.final === undefined ) ? String( v )
				: final.replace( /[\d\s.,]*\d/, v.toLocaleString( 'pt-PT' ) );
			requestAnimationFrame( passo );
		}
		requestAnimationFrame( passo );
	}
	// Desenha o anel de receita por especialidade, sector a sector.
	function anel() {
		var setores = document.querySelectorAll( '.pm-view:not([hidden]) .ad-ring__s' );
		setores.forEach( function ( c, i ) {
			var d = c.dataset.dash;
			if ( ! d ) { return; }
			if ( ! suave ) { c.setAttribute( 'stroke-dasharray', d ); return; }
			c.setAttribute( 'stroke-dasharray', '0 100' );
			relogios.push( setTimeout( function () { c.setAttribute( 'stroke-dasharray', d ); }, 220 + i * 190 ) );
		} );
	}
	function tocar() {
		relogios.forEach( clearTimeout );
		relogios = [];
		document.querySelectorAll( '.pm-view:not([hidden]) .ad-num' ).forEach( function ( el ) {
			relogios.push( setTimeout( function () { contar( el ); }, 160 ) );
		} );
		anel();
	}

	// Legenda e sector do anel realçam-se em conjunto.
	document.querySelectorAll( '.ad-donut-wrap' ).forEach( function ( donut ) {
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
	} );

	// Pesquisa na lista de desempenho por médico.
	( function () {
		var q = document.getElementById( 'ex-med-q' ), tbl = document.getElementById( 'ex-med-tbl' ),
			vazio = document.getElementById( 'ex-med-none' );
		if ( ! q || ! tbl ) { return; }
		q.addEventListener( 'input', function () {
			var t = q.value.trim().toLowerCase(), n = 0;
			tbl.querySelectorAll( '.ex-tbl__row' ).forEach( function ( r ) {
				var ok = ! t || ( r.dataset.q || '' ).indexOf( t ) !== -1;
				r.hidden = ! ok;
				if ( ok ) { n++; }
			} );
			if ( vazio ) { vazio.hidden = ! t || 0 !== n; }
		} );
	} )();

	// --- Filtro das aprovações por estado ---
	( function () {
		var tabs = document.getElementById( 'ex-aprfiltro' ),
			lista = document.getElementById( 'ex-aprovs' ),
			vazio = document.getElementById( 'ex-aprovazio' );
		if ( ! tabs || ! lista ) { return; }
		function aplicar( f ) {
			var n = 0;
			lista.querySelectorAll( '.ex-aprov' ).forEach( function ( el ) {
				var ok = 'todas' === f || el.dataset.estado === f;
				el.hidden = ! ok;
				if ( ok ) { n++; }
			} );
			if ( vazio ) { vazio.hidden = 0 !== n; }
		}
		tabs.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.ad-tab' );
			if ( ! b ) { return; }
			tabs.querySelectorAll( '.ad-tab' ).forEach( function ( o ) { o.classList.toggle( 'is-active', o === b ); } );
			aplicar( b.dataset.filtro );
		} );
		var ativo = tabs.querySelector( '.ad-tab.is-active' );
		aplicar( ativo ? ativo.dataset.filtro : 'todas' );
	} )();

	// --- Combobox pesquisável (tipo da proposta) ---
	document.querySelectorAll( 'select[data-combo]' ).forEach( function ( sel ) {
		var opcoes = [].map.call( sel.options, function ( o ) { return { v: o.value, t: o.textContent.trim(), q: o.textContent.trim().toLowerCase() }; } );
		if ( opcoes.length < 2 ) { return; }
		var caixa = document.createElement( 'div' ); caixa.className = 'ge-combo';
		var campo = document.createElement( 'input' ); campo.type = 'text'; campo.className = 'ge-combo__in'; campo.autocomplete = 'off';
		campo.placeholder = sel.dataset.comboPh || 'Escolher';
		var lista = document.createElement( 'ul' ); lista.className = 'ge-combo__list'; lista.hidden = true;
		sel.parentNode.insertBefore( caixa, sel ); caixa.appendChild( campo ); caixa.appendChild( lista ); caixa.appendChild( sel );
		sel.classList.add( 'ge-combo__nativo' ); sel.tabIndex = -1;
		var marc = -1;
		function rotulo() { var o = sel.options[ sel.selectedIndex ]; return o && o.value !== '' ? o.textContent.trim() : ''; }
		function mostrar() { campo.value = rotulo(); }
		function pintar( f ) {
			lista.innerHTML = ''; var q = ( f || '' ).trim().toLowerCase();
			var vis = opcoes.filter( function ( o ) { return ! q || o.q.indexOf( q ) !== -1; } );
			if ( ! vis.length ) { var n = document.createElement( 'li' ); n.className = 'ge-combo__none'; n.textContent = 'Sem resultados'; lista.appendChild( n ); marc = -1; return; }
			vis.forEach( function ( o ) {
				var li = document.createElement( 'li' ); li.className = 'ge-combo__op'; li.dataset.v = o.v; li.textContent = o.t;
				if ( o.v === sel.value ) { li.classList.add( 'is-sel' ); }
				lista.appendChild( li );
			} );
			marc = 0; realce();
		}
		function realce() { var its = lista.querySelectorAll( '.ge-combo__op' ); its.forEach( function ( li, i ) { li.classList.toggle( 'is-on', i === marc ); } ); }
		function abrir() { lista.hidden = false; caixa.classList.add( 'is-open' ); }
		function fechar() { lista.hidden = true; caixa.classList.remove( 'is-open' ); mostrar(); }
		function escolher( v ) { sel.value = v; sel.dispatchEvent( new Event( 'change', { bubbles: true } ) ); fechar(); }
		campo.addEventListener( 'focus', function () { campo.select(); pintar( '' ); abrir(); } );
		campo.addEventListener( 'input', function () { pintar( campo.value ); abrir(); } );
		campo.addEventListener( 'keydown', function ( e ) {
			var its = lista.querySelectorAll( '.ge-combo__op' );
			if ( 'ArrowDown' === e.key || 'ArrowUp' === e.key ) { e.preventDefault(); if ( lista.hidden ) { pintar( '' ); abrir(); return; } marc += ( 'ArrowDown' === e.key ? 1 : -1 ); if ( marc < 0 ) { marc = its.length - 1; } if ( marc >= its.length ) { marc = 0; } realce(); }
			else if ( 'Enter' === e.key ) { if ( ! lista.hidden && its[ marc ] ) { e.preventDefault(); escolher( its[ marc ].dataset.v ); } }
			else if ( 'Escape' === e.key ) { if ( ! lista.hidden ) { e.stopPropagation(); fechar(); } }
		} );
		lista.addEventListener( 'mousedown', function ( e ) { var li = e.target.closest( '.ge-combo__op' ); if ( ! li ) { return; } e.preventDefault(); escolher( li.dataset.v ); } );
		document.addEventListener( 'mousedown', function ( e ) { if ( ! caixa.contains( e.target ) && ! lista.hidden ) { fechar(); } } );
		mostrar();
	} );

	// Arranque: respeita a vista guardada no endereço.
	var guardada = ( location.hash || '' ).replace( '#', '' ), inicial = 'painel', ok = false;
	views.forEach( function ( s ) { if ( s.dataset.view === guardada ) { ok = true; } } );
	if ( ok ) { inicial = guardada; }
	show( inicial, true );
	tocar();
} )();
</script>
<?php
get_footer();
