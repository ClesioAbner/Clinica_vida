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

// Atividade recente e equipa. Os números do painel e das finanças vêm de
// Clinica_Painel::dados() (cache curta por período), mais abaixo.
$moeda        = class_exists( 'Clinica_Config' ) ? Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';
$audit        = class_exists( 'Clinica_Auditoria' ) ? Clinica_Auditoria::listar( 6 ) : array();
$med_inativos = count( $medicos ) - count( $med_ativos );
$fmt_valor    = static function ( $v ) use ( $moeda ) { return number_format( (float) $v, 0, ',', ' ' ) . ' ' . $moeda; };

// Gestão de Horários (sub-vista "horarios") — dados por médico selecionado
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
<div class="pm-shell pm-shell--admin mt">

	<aside class="pm-side">
		<a class="mt-marca" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="28" height="28" alt="">
			<span>Clínica Vida<i>.</i></span>
		</a>

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
				<a class="pm-nav__i" href="#" data-go="propostas"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 19V5M5 12l7-7 7 7"/></svg><span>Propor à Direção</span></a>
			<?php endif; ?>
			<a class="pm-nav__i" href="#" data-go="auditoria"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><span>Auditoria</span></a>
			<?php if ( $pode_config ) : ?>
				<a class="pm-nav__i" href="#" data-go="definicoes"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><span>Definições</span></a>
			<?php endif; ?>
		</nav>

		<a class="pm-nav__i pm-nav__out" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg><span>Sair</span></a>
	</aside>

	<main class="pm-main">

		<?php
		// Período do painel (filtra os números e os gráficos da Visão geral).
		$dp      = Clinica_Painel::dados( 0 );
		$np_prop = 0;
		foreach ( $propostas as $pp ) {
			if ( 'pendente' === $pp['estado'] ) {
				$np_prop++;
			}
		}
		$mt_url = static fn( string $k ): string => add_query_arg( 'periodo', $k, remove_query_arg( array( 'periodo', 'cl' ) ) ) . '#painel';
		?>
		<!-- Topo: título da vista e período à esquerda; atalhos e saudação à direita -->
		<header class="mt-topo">
			<div class="mt-topo__l">
				<h1 class="mt-topo__t" data-mt-titulo>Visão geral</h1>
				<details class="mt-datas">
					<summary>
						<span><?php echo esc_html( date_i18n( 'j M Y', $dp['desde'] ) . ' – ' . date_i18n( 'j M Y', $agora ) ); ?></span>
						<svg class="mt-datas__seta" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
					</summary>
					<div class="mt-datas__menu">
						<?php foreach ( Clinica_Analitica::PERIODOS_MEDICO as $k => $p ) : ?>
							<a href="<?php echo esc_url( $mt_url( $k ) ); ?>"<?php echo $k === $dp['periodo'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( 'Últimos ' . $p['label'] ); ?></a>
						<?php endforeach; ?>
					</div>
				</details>
			</div>
			<div class="mt-topo__r">
				<span class="mt-ola"><img src="<?php echo esc_url( $avatar ); ?>" width="34" height="34" alt=""><?php echo esc_html( 'Olá, ' . $primeiro . '!' ); ?></span>
			</div>
		</header>

		<?php clinica_render_flash(); ?>

		<!-- Painel
		     Forma da referência "Mar Tech": cinco números com ícone e variação,
		     atividade da clínica (gráfico + especialidades), pacientes por
		     idade, faturação à direita (valor, curva e médicos), métodos de
		     pagamento e atividade recente. Período no topo; só dados reais. -->
		<?php
		$at      = $dp['atual'];
		$ant     = $dp['anterior'];
		$var_txt = static function ( ?int $v, string $un = '%' ): string {
			if ( null === $v ) {
				return '';
			}
			return '<span class="mt-delta mt-delta--' . ( $v >= 0 ? 'bom' : 'mau' ) . '">' . esc_html( ( $v >= 0 ? '+' : '−' ) . abs( $v ) . $un ) . ' <span>vs período anterior</span></span>';
		};
		$total_esp = array_sum( array_column( $dp['por_esp'], 'marcadas' ) );
		$total_met = array_sum( array_column( $dp['metodos'], 'v' ) );
		$nomes_met = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::METODOS : array();
		$mt_kpis   = array(
			array( 'feito', number_format_i18n( $at['realizadas'] ), Clinica_Painel::var_pct( $at['realizadas'], $ant['realizadas'] ), 'Consultas realizadas', 'agenda' ),
			array( 'paciente', number_format_i18n( $dp['novos']['atual'] ), Clinica_Painel::var_pct( $dp['novos']['atual'], $dp['novos']['anterior'] ), 'Novos pacientes', 'agenda' ),
			array( 'financeiro', Clinica_Grafico::compacto( $at['faturado'] ), Clinica_Painel::var_pct( $at['faturado'], $ant['faturado'] ), 'Faturado (' . Clinica_Grafico::moeda_codigo() . ')', 'financas' ),
			array( 'tendencia', Clinica_Grafico::pct( $dp['comparencia'] ), null === $dp['comparencia'] || null === $dp['comparencia_ant'] ? null : (int) round( $dp['comparencia'] - $dp['comparencia_ant'] ), 'Taxa de comparência', 'agenda' ),
			array( 'medico', number_format_i18n( count( $med_ativos ) ), null, 'Médicos ativos', 'equipa' ),
		);
		?>
		<section class="mt-painel pm-view" data-view="painel">

			<div class="mt-kpis mt-kpis--texto">
				<?php foreach ( $mt_kpis as $k ) : ?>
					<a class="mt-kpi" href="#<?php echo esc_attr( $k[4] ); ?>" data-ir="<?php echo esc_attr( $k[4] ); ?>">
						<span class="mt-kpi__l"><?php echo esc_html( $k[3] ); ?></span>
						<span class="mt-kpi__n"><?php echo esc_html( $k[1] ); ?></span>
						<?php echo $var_txt( $k[2], 'Taxa de comparência' === $k[3] ? ' p.p.' : '%' ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="mt-esq">
				<div class="mt-linha mt-linha--a">
					<section class="mt-cartao mt-atividade" aria-labelledby="mt-g-ativ">
						<div class="mt-cartao__cab">
							<h2 class="mt-cartao__t" id="mt-g-ativ">Atividade da clínica</h2>
							<div class="mt-cartao__fim">
								<ul class="mt-legenda" aria-label="Legenda">
									<li><i class="gf-chave gf-chave--barra gf-c--s1-claro"></i>Marcadas</li>
									<li><i class="gf-chave gf-chave--barra gf-c--s1"></i>Realizadas</li>
								</ul>
							</div>
						</div>
						<?php
						echo Clinica_Grafico::colunas(
							array(
								'titulo'   => 'Consultas ' . $dp['por_unidade'],
								'rotulos'  => $dp['rotulos'],
								'longos'   => $dp['longos'],
								'saltar'   => $dp['saltar'],
								'agrupado' => true,
								'legenda'  => false,
								'series'   => array(
									array( 'nome' => 'Marcadas', 'valores' => $dp['serie']['marcadas'], 'cor' => 's1-claro' ),
									array( 'nome' => 'Realizadas', 'valores' => $dp['serie']['realizadas'], 'cor' => 's1' ),
								),
								'formato'  => static fn( $v ) => sprintf( _n( '%d consulta', '%d consultas', (int) $v, 'clinica' ), (int) $v ),
								'eixo'     => static fn( $v ) => number_format_i18n( (int) $v ),
								'vazio'    => 'Sem consultas nos últimos ' . $dp['label'] . '.',
							)
						);
						?>
						<dl class="mt-resumo">
							<div><dt>Marcadas</dt><dd><?php echo esc_html( number_format_i18n( $at['marcadas'] ) ); ?></dd></div>
							<div><dt>Realizadas</dt><dd><?php echo esc_html( number_format_i18n( $at['realizadas'] ) ); ?></dd></div>
							<div><dt>Canceladas</dt><dd><?php echo esc_html( number_format_i18n( $at['canceladas'] ) ); ?></dd></div>
							<div><dt>Faltas</dt><dd><?php echo esc_html( number_format_i18n( $at['faltas'] ) ); ?></dd></div>
						</dl>
					</section>

					<section class="mt-cartao mt-idade" aria-labelledby="mt-g-idade">
						<div class="mt-cartao__cab">
							<h2 class="mt-cartao__t" id="mt-g-idade">Comparência e cobrança</h2>
						</div>
						<?php
						echo Clinica_Grafico::aneis(
							array(
								'titulo' => 'Comparência e cobrança no período',
								'itens'  => array(
									array(
										'rotulo' => 'Comparência',
										'pct'    => $dp['comparencia'],
										'cor'    => 'c1',
										'det'    => sprintf( '%d realizadas, %d faltas', $at['realizadas'], $at['faltas'] ),
									),
									array(
										'rotulo' => 'Cobrança',
										'pct'    => $dp['cobranca'],
										'cor'    => 'c2',
									),
								),
							)
						);
						?>
					</section>
				</div>

				<div class="mt-linha mt-linha--b">
					<section class="mt-cartao" aria-labelledby="mt-g-met">
						<div class="mt-cartao__cab">
							<h2 class="mt-cartao__t" id="mt-g-met">Métodos de pagamento</h2>
						</div>
						<?php if ( $dp['metodos'] ) : ?>
							<ul class="mt-icones">
								<?php foreach ( $dp['metodos'] as $m => $v ) : ?>
									<li><span><?php echo esc_html( $nomes_met[ $m ] ?? ucfirst( (string) $m ) ); ?></span><span class="mt-mudo"><?php echo esc_html( Clinica_Grafico::moeda( $v['v'] ) ); ?></span><b><?php echo (int) round( $total_met ? $v['v'] / $total_met * 100 : 0 ); ?>%</b></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="mt-vazio">Sem pagamentos neste período.</p>
						<?php endif; ?>
						<a class="mt-ver" href="#financas" data-ir="financas">Ver finanças</a>
					</section>

					<section class="mt-cartao" aria-labelledby="mt-g-rec">
						<div class="mt-cartao__cab">
							<h2 class="mt-cartao__t" id="mt-g-rec">Atividade recente</h2>
						</div>
						<?php if ( $audit ) : ?>
							<ul class="mt-icones mt-icones--feed">
								<?php foreach ( array_slice( $audit, 0, 5 ) as $ev ) : ?>
									<li><span><b><?php echo esc_html( $ev['acao'] ); ?></b> <?php echo esc_html( $ev['user_nome'] ?: 'Sistema' ); ?></span><span class="mt-mudo"><?php echo esc_html( 'há ' . human_time_diff( strtotime( $ev['criado_em'] ), $agora ) ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="mt-vazio">Sem atividade registada.</p>
						<?php endif; ?>
						<a class="mt-ver" href="#auditoria" data-ir="auditoria">Ver auditoria</a>
					</section>
				</div>
			</div>

			<section class="mt-cartao mt-receita" aria-labelledby="mt-g-fat">
				<div class="mt-cartao__cab">
					<h2 class="mt-cartao__t" id="mt-g-fat">Faturação</h2>
				</div>
				<div class="mt-receita__caixa">
					<span><?php echo esc_html( 'Faturado nos últimos ' . $dp['label'] ); ?></span>
					<b><?php echo esc_html( Clinica_Grafico::moeda( $at['faturado'] ) ); ?></b>
				<?php
				echo Clinica_Grafico::linhas(
					array(
						'titulo'   => 'Faturado ' . $dp['por_unidade'],
						'rotulos'  => $dp['rotulos'],
						'longos'   => $dp['longos'],
						'saltar'   => $dp['saltar'] * 2,
						'suave'    => true,
						'final'    => false,
						'com_eixo' => false,
						'minimo'   => 1000,
						'series'   => array( array( 'nome' => 'Faturado', 'valores' => $dp['serie_faturado'], 'cor' => 'c1' ) ),
						'formato'  => static fn( $v ) => Clinica_Grafico::moeda( (float) $v ),
						'vazio'    => 'Sem faturação.',
					)
				);
				?>
				</div>
				<?php if ( $dp['por_medico'] ) : ?>
					<ul class="mt-pessoas">
						<?php
						$med_fat = $dp['por_medico'];
						uasort( $med_fat, static fn( $x, $y ) => $y['faturado'] <=> $x['faturado'] );
						foreach ( array_slice( $med_fat, 0, 4, true ) as $nome => $m ) :
							?>
							<li>
								<span class="mt-pessoas__t"><b><?php echo esc_html( $nome ); ?></b><small><?php echo esc_html( $m['especialidade'] ); ?></small></span>
								<span class="mt-pessoas__v"><?php echo esc_html( Clinica_Grafico::moeda( $m['faturado'] ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<a class="mt-ver" href="#financas" data-ir="financas">Ver finanças</a>
			</section>

		</section>

		<!-- Finanças
		     Forma da referência "FinEdge" (vidro sobre fundo teal suave):
		     cabeçalho com pesquisa, avisos e período; cinco números; evolução
		     da receita, receita por especialidade e pagamentos recentes; o
		     cartão da clínica, métodos de pagamento e fluxo de cobrança.
		     Animações com Motion (Framer Motion em JS) em assets/js/financas.js. -->
		<?php
		$fa       = $dp['atual'];
		$fant     = $dp['anterior'];
		$nomes_mt = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::METODOS : array();
		$mzn      = static fn( $v ) => Clinica_Grafico::moeda( (float) $v );
		$contra   = 'vs ' . $dp['label'] . ' anteriores';
		$fx_var   = static function ( ?int $v, bool $sobe_bom = true ) use ( $contra ): string {
			if ( null === $v ) {
				return '';
			}
			$bom = ( $v >= 0 ) === $sobe_bom;
			return '<span class="fx-var" title="' . esc_attr( $contra ) . '"><b class="fx-var--' . ( $bom ? 'bom' : 'mau' ) . '">' . ( $v >= 0 ? '↑' : '↓' ) . ' ' . esc_html( abs( $v ) . '%' ) . '</b></span>';
		};
		$total_mt = array_sum( array_column( $dp['metodos'], 'v' ) );
		$cartao   = $dp['metodos']['cartao'] ?? array( 'n' => 0, 'v' => 0.0 );
		$moveis   = ( $dp['metodos']['mpesa']['v'] ?? 0 ) + ( $dp['metodos']['emola']['v'] ?? 0 );
		// Receita por especialidade: até quatro fatias; o resto junta-se em "Outras".
		$esp_f = $dp['por_esp'];
		uasort( $esp_f, static fn( $x, $y ) => $y['faturado'] <=> $x['faturado'] );
		$esp_f = array_filter( $esp_f, static fn( $e ) => $e['faturado'] > 0 );
		if ( count( $esp_f ) > 4 ) {
			$resto = array_slice( $esp_f, 3, null, true );
			$esp_f = array_slice( $esp_f, 0, 3, true ) + array( 'Outras' => array( 'faturado' => array_sum( array_column( $resto, 'faturado' ) ) ) );
		}
		$esp_tot   = array_sum( array_column( $esp_f, 'faturado' ) );
		$fx_kpis   = array(
			array( 'financeiro', 'menta', 'Faturado', $fa['faturado'], $fx_var( Clinica_Painel::var_pct( $fa['faturado'], $fant['faturado'] ) ) ),
			array( 'dinheiro', 'menta', 'Recebido', $fa['recebido'], $fx_var( Clinica_Painel::var_pct( $fa['recebido'], $fant['recebido'] ) ) ),
			array( 'pendente', 'terra', 'Por receber', $fa['por_receber'], $fx_var( Clinica_Painel::var_pct( $fa['por_receber'], $fant['por_receber'] ), false ) ),
		);
		?>
		<section class="pm-view fx" data-view="financas" hidden>

			<header class="fx-topo">
				<div>
					<h1 class="fx-topo__t">Finanças</h1>
				</div>
				<?php Clinica_Painel::filtro( 'financas', $dp['periodo'] ); ?>
			</header>

			<div class="fx-kpis" data-fx-grupo>
				<?php foreach ( $fx_kpis as $k ) : ?>
					<div class="fx-cartao fx-kpi">
						<div class="fx-kpi__t">
							<span class="fx-kpi__l"><?php echo esc_html( $k[2] ); ?></span>
							<b class="fx-kpi__v" data-fx-n="<?php echo esc_attr( (string) round( (float) $k[3] ) ); ?>" data-fx-suf=" <?php echo esc_attr( $moeda ); ?>"><?php echo esc_html( $mzn( $k[3] ) ); ?></b>
							<?php echo $k[4]; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<div class="fx-cartao fx-kpi">
					<div class="fx-kpi__t">
						<span class="fx-kpi__l">Cobrança</span>
						<b class="fx-kpi__v"<?php echo null !== $dp['cobranca'] ? ' data-fx-n="' . esc_attr( (string) round( (float) $dp['cobranca'] ) ) . '" data-fx-suf="%"' : ''; ?>><?php echo esc_html( Clinica_Grafico::pct( $dp['cobranca'] ) ); ?></b>
					</div>
				</div>
				<div class="fx-cartao fx-kpi">
					<div class="fx-kpi__t">
						<span class="fx-kpi__l">Prazo de cobrança</span>
						<b class="fx-kpi__v"><?php echo esc_html( null === $dp['prazo'] ? '—' : number_format( (float) $dp['prazo'], 1, ',', ' ' ) . ' dias' ); ?></b>
					</div>
				</div>
			</div>

			<div class="fx-linha fx-linha--a" data-fx-grupo>
				<section class="fx-cartao fx-saldo" aria-labelledby="fx-t-saldo">
					<div class="fx-cab">
						<h2 class="fx-cab__t" id="fx-t-saldo">Evolução da receita</h2>
					</div>
					<p class="fx-grande"><b data-fx-n="<?php echo esc_attr( (string) round( (float) $fa['recebido'] ) ); ?>" data-fx-suf=" <?php echo esc_attr( $moeda ); ?>"><?php echo esc_html( $mzn( $fa['recebido'] ) ); ?></b><?php echo $fx_var( Clinica_Painel::var_pct( $fa['recebido'], $fant['recebido'] ) ); ?></p>
					<?php
					echo Clinica_Grafico::linhas(
						array(
							'titulo'  => 'Recebido ' . $dp['por_unidade'],
							'rotulos' => $dp['rotulos'],
							'longos'  => $dp['longos'],
							'saltar'  => $dp['saltar'],
							'suave'   => true,
							'final'   => false,
							'minimo'  => 1000,
							'series'  => array( array( 'nome' => 'Recebido', 'valores' => $dp['serie']['recebido'], 'cor' => 'c1' ) ),
							'formato' => $mzn,
							'vazio'   => 'Sem receita nos últimos ' . $dp['label'] . '.',
						)
					);
					?>
				</section>

				<section class="fx-cartao fx-categorias" aria-labelledby="fx-t-cat">
					<div class="fx-cab"><h2 class="fx-cab__t" id="fx-t-cat">Receita por especialidade</h2></div>
					<?php
					// Um anel por especialidade (parte do total); acima de três, as
					// restantes juntam-se em "Outras".
					$aneis = array();
					$ci    = 0;
					$esp_a = $esp_f;
					if ( count( $esp_a ) > 3 ) {
						$resto = array_slice( $esp_a, 2, null, true );
						$esp_a = array_slice( $esp_a, 0, 2, true ) + array( 'Outras' => array( 'faturado' => array_sum( array_column( $resto, 'faturado' ) ) ) );
					}
					foreach ( $esp_a as $nome => $e ) {
						$aneis[] = array(
							'rotulo' => $nome,
							'pct'    => $esp_tot ? $e['faturado'] / $esp_tot * 100 : null,
							'cor'    => array( 'c1', 'c2', 'c3' )[ $ci++ ],
						);
					}
					if ( $aneis ) {
						echo Clinica_Grafico::aneis( array( 'titulo' => 'Receita por especialidade', 'itens' => $aneis ) );
					} else {
						echo '<p class="fx-vazio">Sem receita neste período.</p>';
					}
					?>
				</section>

				<section class="fx-cartao fx-transacoes" id="fx-pagamentos" aria-labelledby="fx-t-tr">
					<div class="fx-cab">
						<h2 class="fx-cab__t" id="fx-t-tr">Pagamentos recentes</h2>
						<?php if ( count( $dp['pagamentos'] ) > 5 ) : ?><button type="button" class="fx-pilula fx-pilula--btn" data-fx-todos aria-expanded="false">Ver todos</button><?php endif; ?>
					</div>
					<?php if ( $dp['pagamentos'] ) : ?>
						<ul class="fx-lista" id="fx-lista">
							<?php foreach ( $dp['pagamentos'] as $i => $pg ) :
								$pago = 'pago' === $pg['estado'];
								?>
								<li class="fx-item<?php echo $i >= 5 ? ' is-extra' : ''; ?>" data-q="<?php echo esc_attr( mb_strtolower( $pg['paciente'] . ' ' . $pg['medico'] ) ); ?>"<?php echo $i >= 5 ? ' hidden' : ''; ?>>
									<span class="fx-item__t"><b><?php echo esc_html( $pg['paciente'] ); ?></b><small><?php echo esc_html( date_i18n( 'j M', strtotime( $pg['inicio'] ) ) ); ?></small></span>
									<span class="fx-item__v<?php echo $pago ? ' is-entrada' : ''; ?>"><?php echo esc_html( ( $pago ? '+ ' : '' ) . $mzn( $pg['valor'] ) ); ?><?php echo $pago ? '' : '<small>Pendente</small>'; ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
						<p class="fx-vazio" id="fx-lista-nada" hidden>Nenhum pagamento com esse nome.</p>
					<?php else : ?>
						<p class="fx-vazio">Sem pagamentos neste período.</p>
					<?php endif; ?>
				</section>
			</div>

			<div class="fx-linha fx-linha--b" data-fx-grupo>
				<section class="fx-cartao fx-cartoes" aria-labelledby="fx-t-cart">
					<div class="fx-cab">
						<h2 class="fx-cab__t" id="fx-t-cart">Contas de recebimento</h2>
					</div>
					<div class="fx-carrossel" data-fx-carrossel>
						<div class="fx-carrossel__pista">
							<div class="fx-slide" data-fx-slide>
								<div class="fx-bci" data-fx-inclina role="img" aria-label="Cartão BCI Classic Visa da Clínica Vida">
									<span class="fx-bci__petala fx-bci__petala--a" aria-hidden="true"></span>
									<span class="fx-bci__petala fx-bci__petala--b" aria-hidden="true"></span>
									<span class="fx-bci__brilho" aria-hidden="true"></span>
									<span class="fx-bci__marca" aria-hidden="true">
										<svg viewBox="0 0 40 24"><ellipse cx="11" cy="14" rx="4.2" ry="9" transform="rotate(-38 11 14)"/><ellipse cx="20" cy="10" rx="4.2" ry="9.5"/><ellipse cx="29" cy="14" rx="4.2" ry="9" transform="rotate(38 29 14)"/></svg>
										<b>BCI</b><i>Classic</i>
									</span>
									<span class="fx-bci__chip" aria-hidden="true"><i></i><i></i><i></i></span>
									<svg class="fx-bci__nfc" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7a7 7 0 0 1 0 10M11.5 5a10 10 0 0 1 0 14M15 3a13 13 0 0 1 0 18"/></svg>
									<span class="fx-bci__num">4338 •••• •••• 9010</span>
									<span class="fx-bci__nome">CLÍNICA VIDA</span>
									<span class="fx-bci__val"><small>VALID<br>THRU</small>05/27</span>
									<span class="fx-bci__visa">VISA</span>
								</div>
								<div class="fx-cartao-info">
									<span>Cartão</span>
									<b><?php echo esc_html( $mzn( $cartao['v'] ) ); ?></b>
								</div>
							</div>
							<div class="fx-slide" data-fx-slide hidden>
								<div class="fx-movel" data-fx-inclina>
									<span class="fx-movel__marca">Carteiras móveis</span>
									<span class="fx-movel__chip" aria-hidden="true"></span>
									<span class="fx-movel__nomes">M-Pesa · e-Mola</span>
									<span class="fx-movel__rodape"><span>Clínica Vida</span><b><?php echo esc_html( $mzn( $moveis ) ); ?></b></span>
								</div>
								<div class="fx-cartao-info">
									<span>Carteiras móveis</span>
									<b><?php echo esc_html( $mzn( $moveis ) ); ?></b>
								</div>
							</div>
						</div>
						<div class="fx-pontos" role="tablist" aria-label="Contas">
							<button type="button" role="tab" aria-selected="true" aria-label="Cartão bancário" data-fx-ir="0"></button>
							<button type="button" role="tab" aria-selected="false" aria-label="Carteiras móveis" data-fx-ir="1"></button>
						</div>
					</div>
				</section>

				<section class="fx-cartao fx-metodos" aria-labelledby="fx-t-met">
					<div class="fx-cab"><h2 class="fx-cab__t" id="fx-t-met">Métodos de pagamento</h2></div>
					<?php if ( $dp['metodos'] ) : ?>
						<ul class="fx-lista">
							<?php foreach ( $dp['metodos'] as $m => $v ) : ?>
								<li class="fx-item">
									<span class="fx-item__t"><b><?php echo esc_html( $nomes_mt[ $m ] ?? ucfirst( (string) $m ) ); ?></b></span>
									<span class="fx-item__n fx-quota"><?php echo esc_html( number_format( $total_mt ? $v['v'] / $total_mt * 100 : 0, 0, ',', ' ' ) . '%' ); ?></span>
									<span class="fx-item__v"><?php echo esc_html( $mzn( $v['v'] ) ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="fx-vazio">Sem pagamentos recebidos neste período.</p>
					<?php endif; ?>
				</section>

				<section class="fx-cartao fx-fluxo" aria-labelledby="fx-t-fluxo">
					<div class="fx-cab">
						<h2 class="fx-cab__t" id="fx-t-fluxo">Fluxo de cobrança</h2>
					</div>
					<?php
					echo Clinica_Grafico::divergente(
						array(
							'titulo'  => 'Recebido e por receber ' . $dp['por_unidade'],
							'rotulos' => $dp['rotulos'],
							'longos'  => $dp['longos'],
							'saltar'  => $dp['saltar'],
							'minimo'  => 1000,
							'cima'    => array( 'nome' => 'Recebido', 'valores' => $dp['serie']['recebido'], 'cor' => 'c1' ),
							'baixo'   => array( 'nome' => 'Por receber', 'valores' => $dp['serie']['por_receber'], 'cor' => 'c2' ),
							'formato' => $mzn,
							'vazio'   => 'Sem movimentos neste período.',
						)
					);
					?>
				</section>
			</div>
		</section>

		<!-- Auditoria
		     A Administração consulta os registos. A investigação técnica é do
		     departamento de IT, por isso aqui só se lê, não se apaga. -->
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
		<!-- Equipa médica -->
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

		<!-- Novo médico (página dedicada)
		     Três passos à esquerda (quem é, o que faz na clínica, como entra no
		     sistema) e, à direita, a fotografia e a pré-visualização de como o
		     médico vai aparecer, que se atualiza enquanto se escreve. -->
		<section class="pm-view mt-novo" data-view="novo-medico" hidden>
			<div class="mt-barra">
				<a class="mt-voltar" href="#equipa" data-go="equipa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>Voltar à equipa</a>
			</div>

			<form method="post" class="mt-novo__form" enctype="multipart/form-data" id="mt-novo-form">
				<?php wp_nonce_field( 'ge_med', 'ge_nonce' ); ?>
				<input type="hidden" name="ge_action" value="med_criar">

				<div class="mt-novo__corpo">
					<section class="mt-cartao mt-passo" aria-labelledby="mt-n1">
						<div class="mt-passo__cab"><h2 class="mt-cartao__t" id="mt-n1">Identificação</h2></div>
						<div class="mt-form">
							<label class="ge-field mt-form__larga">
								<span>Nome completo</span>
								<input type="text" name="nome" required placeholder="Ex.: Ana Come" data-ver="nome">
							</label>
							<label class="ge-field mt-form__larga">
								<span>Telefone</span>
								<input type="tel" name="telefone" placeholder="+258 84 000 0000">
							</label>
						</div>
					</section>

					<section class="mt-cartao mt-passo" aria-labelledby="mt-n2">
						<div class="mt-passo__cab"><h2 class="mt-cartao__t" id="mt-n2">Atividade clínica</h2></div>
						<div class="mt-form">
							<label class="ge-field mt-form__larga">
								<span>Especialidade</span>
								<select name="especialidade_id" required data-combo data-combo-ph="Escreva para procurar" data-ver="esp">
									<option value="">Selecione</option>
									<?php foreach ( $esp_ativas as $e ) : ?><option value="<?php echo (int) $e['id']; ?>"><?php echo esc_html( $e['nome'] ); ?></option><?php endforeach; ?>
								</select>
							</label>
							<label class="ge-field">
								<span>Nº de ordem</span>
								<input type="text" name="num_ordem" placeholder="OM-0000" data-ver="ordem">
							</label>
							<label class="ge-field">
								<span>Consultório</span>
								<input type="text" name="sala" placeholder="Ex.: Consultório 3" data-ver="sala">
							</label>
						</div>
					</section>

					<section class="mt-cartao mt-passo" aria-labelledby="mt-n3">
						<div class="mt-passo__cab"><h2 class="mt-cartao__t" id="mt-n3">Acesso ao sistema</h2></div>
						<div class="mt-form">
							<label class="ge-field">
								<span>Email</span>
								<input type="email" name="email" required placeholder="nome@clinica.mz">
							</label>
							<label class="ge-field">
								<span>Palavra-passe</span>
								<input type="password" name="pwd" minlength="6" required placeholder="Mínimo 6 caracteres">
							</label>
						</div>
					</section>
				</div>

				<aside class="mt-novo__lado">
					<section class="mt-cartao mt-previa" aria-labelledby="mt-nv">
						<h2 class="mt-cartao__t" id="mt-nv">Pré-visualização</h2>
						<div class="mt-previa__foto">
							<img class="ge-foto__preview" id="med-foto-preview" src="" alt="" hidden>
							<span class="mt-previa__ini" id="med-foto-ph" aria-hidden="true">?</span>
						</div>
						<p class="mt-previa__nome" data-ver-saida="nome">Nome do médico</p>
						<p class="mt-previa__esp" data-ver-saida="esp">Especialidade</p>
						<dl class="mt-previa__dados">
							<div><dt>Nº de ordem</dt><dd data-ver-saida="ordem">—</dd></div>
							<div><dt>Consultório</dt><dd data-ver-saida="sala">—</dd></div>
						</dl>
						<label class="ge-btn ge-btn--ghost mt-previa__btn">
							Escolher fotografia
							<input type="file" name="foto" accept="image/*" id="med-foto-input" hidden>
						</label>
					</section>
				</aside>

				<div class="mt-novo__pe">
					<a class="ge-btn ge-btn--ghost" href="#equipa" data-go="equipa">Cancelar</a>
					<button type="submit" class="ge-btn ge-btn--primary">Criar médico</button>
				</div>
			</form>
		</section>

		<!-- Especialidades
		     Uma linha por especialidade: nome e descrição, a equipa que a faz,
		     a procura e o faturado do período do painel, o estado e a edição
		     (que abre por baixo da linha, sem sair da página). -->
		<?php
		$med_por_esp = array();
		foreach ( $medicos as $m ) {
			if ( (int) $m['ativo'] === 1 ) {
				$med_por_esp[ $m['esp_nome'] ][] = $m['nome'];
			}
		}
		?>
		<section class="pm-view mt-esp" data-view="especialidades" hidden>
			<div class="mt-barra">
				<label class="mt-procura">
					<span class="db-sr">Procurar especialidade</span>
					<input type="search" id="mt-esp-q" placeholder="Procurar especialidade">
				</label>
				<span class="mt-mudo"><?php echo esc_html( sprintf( '%d especialidades', count( $especialidades ) ) ); ?></span>
				<a class="ge-btn ge-btn--primary" href="#nova-especialidade" data-go="nova-especialidade">Nova especialidade</a>
			</div>

			<div class="mt-cartao mt-tabela-cartao">
				<?php if ( $especialidades ) : ?>
					<div class="mt-tabela mt-tabela--esp" id="mt-esp-lista">
						<div class="mt-tabela__cab"><span>Especialidade</span><span>Equipa</span><span class="r">Consultas</span><span class="r">Faturado</span><span>Estado</span><span class="r">Ação</span></div>
						<?php foreach ( $especialidades as $e ) :
							$equipa = $med_por_esp[ $e['nome'] ] ?? array();
							$proc   = $dp['por_esp'][ $e['nome'] ] ?? array( 'marcadas' => 0, 'faturado' => 0 );
							?>
							<details class="mt-esp__item" data-q="<?php echo esc_attr( mb_strtolower( $e['nome'] . ' ' . $e['descricao'] ) ); ?>">
								<summary class="mt-tabela__linha">
									<span class="mt-esp__nome">
										<span><b><?php echo esc_html( $e['nome'] ); ?></b><small><?php echo esc_html( $e['descricao'] ?: 'Sem descrição' ); ?></small></span>
									</span>
									<span class="mt-esp__equipa"><?php echo $equipa ? esc_html( implode( ', ', $equipa ) ) : '<span class="mt-mudo">Sem médico ativo</span>'; ?></span>
									<span class="r mt-hora"><?php echo (int) $proc['marcadas']; ?></span>
									<span class="r mt-hora"><?php echo esc_html( Clinica_Grafico::moeda( (float) $proc['faturado'] ) ); ?></span>
									<span><span class="mt-estado mt-estado--<?php echo $e['ativo'] ? 'ativa' : 'inativa'; ?>"><?php echo $e['ativo'] ? 'Ativa' : 'Inativa'; ?></span></span>
									<span class="r"><span class="mt-editar">Editar</span></span>
								</summary>
								<div class="mt-esp__painel">
									<form method="post" class="mt-esp__form">
										<?php wp_nonce_field( 'ge_esp', 'ge_nonce' ); ?>
										<input type="hidden" name="ge_action" value="esp_editar">
										<input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
										<label class="ge-field"><span>Nome</span><input type="text" name="nome" value="<?php echo esc_attr( $e['nome'] ); ?>"></label>
										<label class="ge-field mt-esp__desc"><span>Descrição</span><input type="text" name="descricao" value="<?php echo esc_attr( $e['descricao'] ); ?>"></label>
										<label class="ge-check"><input type="checkbox" name="ativo" value="1" <?php checked( (int) $e['ativo'], 1 ); ?>> Ativa</label>
										<div class="mt-esp__acoes">
											<button class="ge-btn ge-btn--primary" type="submit">Guardar alterações</button>
										</div>
									</form>
									<form method="post" class="mt-esp__apagar" onsubmit="return confirm('Remover esta especialidade?');">
										<?php wp_nonce_field( 'ge_esp', 'ge_nonce' ); ?>
										<input type="hidden" name="ge_action" value="esp_apagar">
										<input type="hidden" name="id" value="<?php echo (int) $e['id']; ?>">
										<button class="ge-btn ge-btn--ghost" type="submit">Remover especialidade</button>
									</form>
								</div>
							</details>
						<?php endforeach; ?>
					</div>
					<p class="mt-vazio mt-filtro-vazio" id="mt-esp-nada" hidden>Nenhuma especialidade encontrada.</p>
				<?php else : ?>
					<p class="mt-vazio">Sem especialidades.</p>
				<?php endif; ?>
			</div>
		</section>

		<!-- Nova especialidade (página dedicada) -->
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

		<!-- Agenda
		     Calendário do mês à esquerda (dias com consultas marcados) e as
		     próximas consultas da clínica à direita, por dia, com filtros por
		     médico e por estado. Resumo do dia e da semana na barra de cima. -->
		<?php
		$ag_hoje  = count( array_filter( $consultas, static fn( $c ) => substr( $c['inicio'], 0, 10 ) === $hoje_ymd && 'cancelada' !== $c['status'] ) );
		$ag_7d    = count( array_filter( $futuras, static fn( $c ) => strtotime( $c['inicio'] ) < strtotime( '+7 day', $agora ) ) );
		$ag_pend  = count( array_filter( $futuras, static fn( $c ) => 'pendente' === $c['status'] ) );
		$ag_meds  = array_values( array_unique( array_filter( array_column( $futuras, 'med_nome' ) ) ) );
		sort( $ag_meds );
		$ag_meses = array();
		for ( $mi = 0; $mi < 3; $mi++ ) {
			$ag_meses[] = strtotime( date( 'Y-m-01', strtotime( "first day of +{$mi} month", $agora ) ) );
		}
		$ag_sem = array( 'S', 'T', 'Q', 'Q', 'S', 'S', 'D' );
		?>
		<section class="pm-view mt-agenda" data-view="agenda" hidden>

			<div class="mt-barra">
				<dl class="mt-barra__resumo">
					<div><dt>Hoje</dt><dd><?php echo (int) $ag_hoje; ?></dd></div>
					<div><dt>Próximos 7 dias</dt><dd><?php echo (int) $ag_7d; ?></dd></div>
					<div><dt>Por confirmar</dt><dd><?php echo (int) $ag_pend; ?></dd></div>
					<div><dt>Médicos com agenda</dt><dd><?php echo count( $ag_meds ); ?></dd></div>
				</dl>
				<?php if ( $pode_agenda ) : ?>
					<a class="ge-btn ge-btn--primary" href="#horarios" data-go="horarios">Horários e folgas</a>
				<?php endif; ?>
			</div>

			<div class="mt-agenda__grelha">
				<section class="mt-cartao mt-mes" aria-label="Calendário">
					<div class="mt-mes__nav">
						<button type="button" class="mt-mes__seta" data-mes="-1" aria-label="Mês anterior"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
						<span class="mt-mes__t" aria-live="polite"><?php echo esc_html( ucfirst( date_i18n( 'F Y', $ag_meses[0] ) ) ); ?></span>
						<button type="button" class="mt-mes__seta" data-mes="1" aria-label="Mês seguinte"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
					</div>
					<?php foreach ( $ag_meses as $mi => $m_ts ) :
						$m_dias = (int) date( 't', $m_ts );
						$m_off  = (int) date( 'N', $m_ts ) - 1;
						?>
						<div class="mt-mes__grelha" data-titulo="<?php echo esc_attr( ucfirst( date_i18n( 'F Y', $m_ts ) ) ); ?>"<?php echo $mi ? ' hidden' : ''; ?>>
							<?php foreach ( $ag_sem as $sn ) : ?><span class="mt-mes__sem"><?php echo esc_html( $sn ); ?></span><?php endforeach; ?>
							<?php for ( $b = 0; $b < $m_off; $b++ ) : ?><span></span><?php endfor; ?>
							<?php for ( $dd = 1; $dd <= $m_dias; $dd++ ) :
								$ymd = date( 'Y-m-', $m_ts ) . str_pad( (string) $dd, 2, '0', STR_PAD_LEFT );
								$n_d = count( $agenda_por_dia[ $ymd ] ?? array() );
								$cls = 'mt-mes__dia' . ( $ymd === $hoje_ymd ? ' is-hoje' : '' ) . ( $n_d ? ' tem' : '' ) . ( $ymd < $hoje_ymd ? ' is-passado' : '' );
								if ( $n_d ) :
									?>
									<a class="<?php echo esc_attr( $cls ); ?>" href="#mt-ag-<?php echo esc_attr( $ymd ); ?>" aria-label="<?php echo esc_attr( date_i18n( 'j \d\e F', strtotime( $ymd ) ) . ', ' . sprintf( _n( '%d consulta', '%d consultas', $n_d, 'clinica' ), $n_d ) ); ?>"><?php echo (int) $dd; ?></a>
								<?php else : ?>
									<span class="<?php echo esc_attr( $cls ); ?>"><?php echo (int) $dd; ?></span>
								<?php endif; ?>
							<?php endfor; ?>
						</div>
					<?php endforeach; ?>
					<ul class="mt-mes__legenda">
						<li><i class="mt-mes__ponto"></i>Dia com consultas</li>
						<li><i class="mt-mes__anel"></i>Hoje</li>
					</ul>
				</section>

				<section class="mt-cartao mt-tabela-cartao" aria-labelledby="mt-ag-t">
					<div class="mt-cartao__cab">
						<h2 class="mt-cartao__t" id="mt-ag-t">Próximas consultas</h2>
						<div class="mt-ferramentas">
							<label class="mt-select">
								<span class="db-sr">Filtrar por médico</span>
								<select id="mt-ag-med">
									<option value="">Todos os médicos</option>
									<?php foreach ( $ag_meds as $nm ) : ?>
										<option value="<?php echo esc_attr( $nm ); ?>"><?php echo esc_html( $nm ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
							<label class="mt-select">
								<span class="db-sr">Filtrar por estado</span>
								<select id="mt-ag-est">
									<option value="">Todos os estados</option>
									<option value="pendente">Por confirmar</option>
									<option value="confirmada">Confirmadas</option>
								</select>
							</label>
						</div>
					</div>

					<?php if ( $agenda_por_dia ) : ?>
						<div class="mt-tabela mt-tabela--agenda" id="mt-ag-lista">
							<div class="mt-tabela__cab">
								<span>Hora</span><span>Paciente</span><span>Médico</span><span>Especialidade</span><span>Estado</span>
							</div>
							<?php foreach ( $agenda_por_dia as $dia => $items ) :
								$ts_d = strtotime( $dia );
								?>
								<div class="mt-tabela__dia" id="mt-ag-<?php echo esc_attr( $dia ); ?>" data-dia="<?php echo esc_attr( $dia ); ?>">
									<span class="mt-tabela__diat"><?php echo esc_html( ucfirst( date_i18n( 'l, j \d\e F', $ts_d ) ) . ( $dia === $hoje_ymd ? ' · Hoje' : '' ) ); ?></span>
									<span class="mt-tabela__dian"><?php echo esc_html( sprintf( _n( '%d consulta', '%d consultas', count( $items ), 'clinica' ), count( $items ) ) ); ?></span>
								</div>
								<?php foreach ( $items as $f ) : ?>
									<div class="mt-tabela__linha" data-dia="<?php echo esc_attr( $dia ); ?>" data-med="<?php echo esc_attr( $f['med_nome'] ?? '' ); ?>" data-est="<?php echo esc_attr( $f['status'] ); ?>">
										<span class="mt-hora"><?php echo esc_html( $fmt_hora( $f['inicio'] ) ); ?></span>
										<span class="mt-pessoa"><b><?php echo esc_html( $f['pac_nome'] ); ?></b></span>
										<span><?php echo esc_html( $f['med_nome'] ?? '' ); ?></span>
										<span class="mt-mudo"><?php echo esc_html( $f['esp_nome'] ); ?></span>
										<span><span class="mt-estado mt-estado--<?php echo esc_attr( $f['status'] ); ?>"><?php echo esc_html( 'pendente' === $f['status'] ? 'Por confirmar' : ( $labels[ $f['status'] ] ?? $f['status'] ) ); ?></span></span>
									</div>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</div>
						<p class="mt-vazio mt-filtro-vazio" id="mt-ag-nada" hidden>Sem consultas com estes filtros.</p>
					<?php else : ?>
						<p class="mt-vazio">Sem consultas agendadas.</p>
					<?php endif; ?>
				</section>
			</div>
		</section>

		<!-- Horários e folgas (agenda no shell) -->
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
								<option value="<?php echo (int) $m['id']; ?>" <?php selected( (int) $m['id'], $ag_sel ); ?>><?php echo esc_html( $ag_nome( $m['nome'] ) . ' · ' . $m['esp'] ); ?></option>
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
							<div class="ad-panel__head"><h3>Horário semanal de <?php echo esc_html( $ag_nome( $ag_sel_med['nome'] ) ); ?></h3></div>
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

		<!-- Definições -->
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
						<label class="ge-field"><span>Almoço (início)</span><input type="time" name="intervalo_inicio" value="<?php echo esc_attr( Clinica_Config::get( 'intervalo_inicio', '13:00' ) ); ?>"></label>
						<label class="ge-field"><span>Almoço (fim)</span><input type="time" name="intervalo_fim" value="<?php echo esc_attr( Clinica_Config::get( 'intervalo_fim', '14:00' ) ); ?>"></label>
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
						<p>Travões de segurança: quando o limite é atingido, deixam de aparecer vagas, mesmo com o médico livre.</p>
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

				<div class="ad-grp">
					<div class="ad-grp__t">
						<h3>Redes sociais</h3>
						<p>Aparecem no rodapé do site. Sem endereço, a rede não aparece; o WhatsApp usa o primeiro telefone da clínica se ficar vazio.</p>
					</div>
					<div class="ad-grp__f">
						<label class="ge-field"><span>WhatsApp (número ou link)</span><input type="text" name="rede_whatsapp" inputmode="tel" placeholder="+258 84 000 0000" value="<?php echo esc_attr( Clinica_Config::get( 'rede_whatsapp', '' ) ); ?>"></label>
						<label class="ge-field"><span>Facebook</span><input type="url" name="rede_facebook" placeholder="https://www.facebook.com/…" value="<?php echo esc_attr( Clinica_Config::get( 'rede_facebook', '' ) ); ?>"></label>
						<label class="ge-field"><span>Instagram</span><input type="url" name="rede_instagram" placeholder="https://www.instagram.com/…" value="<?php echo esc_attr( Clinica_Config::get( 'rede_instagram', '' ) ); ?>"></label>
						<label class="ge-field"><span>TikTok</span><input type="url" name="rede_tiktok" placeholder="https://www.tiktok.com/@…" value="<?php echo esc_attr( Clinica_Config::get( 'rede_tiktok', '' ) ); ?>"></label>
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

		<!-- Propor à Direção (Gestão -> Direção)
		     Formulário à esquerda, como funciona e o resumo à direita, e a lista
		     das propostas em baixo, com filtro por estado. A Gestão submete; a
		     Direção decide; a resposta volta a aparecer aqui. -->
		<?php
		$pr_cont = array( 'pendente' => 0, 'aprovado' => 0, 'rejeitado' => 0 );
		foreach ( $propostas as $pp ) {
			if ( isset( $pr_cont[ $pp['estado'] ] ) ) {
				$pr_cont[ $pp['estado'] ]++;
			}
		}
		$pr_tipos = array(
			'especialidade' => 'Nova especialidade',
			'orcamento'     => 'Orçamento',
			'investimento'  => 'Investimento em equipamento',
			'contratacao'   => 'Contratação',
			'precos'        => 'Alteração de preços',
			'geral'         => 'Geral',
		);
		?>
		<section class="pm-view mt-prop" data-view="propostas" hidden>
			<div class="mt-prop__topo">
				<?php if ( $pode_propor ) : ?>
					<section class="mt-cartao mt-prop__form" aria-labelledby="mt-pr-t">
						<div class="mt-cartao__cab">
							<h2 class="mt-cartao__t" id="mt-pr-t">Nova proposta</h2>
						</div>
						<form method="post" class="mt-form">
							<?php wp_nonce_field( 'dir_propor', 'dir_nonce' ); ?>
							<input type="hidden" name="dir_action" value="propor">
							<label class="ge-field mt-form__larga"><span>O que propõe</span><input type="text" name="titulo" required placeholder="Ex.: Abrir a especialidade de Oftalmologia"></label>
							<label class="ge-field"><span>Tipo de decisão</span>
								<select name="tipo">
									<?php foreach ( $pr_tipos as $k => $rot ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $rot ); ?></option><?php endforeach; ?>
								</select>
							</label>
							<label class="ge-field"><span>Valor estimado (<?php echo esc_html( $moeda ); ?>)</span><input type="number" name="valor" min="0" step="any" placeholder="Opcional"></label>
							<label class="ge-field mt-form__larga"><span>Justificação</span><textarea name="detalhe" rows="5" placeholder="Contexto, impacto e custo"></textarea></label>
							<div class="mt-form__pe mt-form__larga">
								<button type="submit" class="ge-btn ge-btn--primary">Enviar à Direção</button>
							</div>
						</form>
					</section>
				<?php endif; ?>

				<aside class="mt-prop__lado">
					<section class="mt-cartao" aria-label="Resumo das propostas">
						<dl class="mt-prop__resumo">
							<div><dt>Pendentes</dt><dd><?php echo (int) $pr_cont['pendente']; ?></dd></div>
							<div><dt>Aprovadas</dt><dd><?php echo (int) $pr_cont['aprovado']; ?></dd></div>
							<div><dt>Rejeitadas</dt><dd><?php echo (int) $pr_cont['rejeitado']; ?></dd></div>
						</dl>
					</section>
				</aside>
			</div>

			<section class="mt-cartao mt-tabela-cartao" aria-labelledby="mt-pr-lista">
				<div class="mt-cartao__cab">
					<h2 class="mt-cartao__t" id="mt-pr-lista">Propostas submetidas</h2>
					<?php if ( $propostas ) : ?>
						<nav class="mt-abas" id="mt-pr-abas" aria-label="Filtrar por estado">
							<button type="button" data-est="" aria-pressed="true">Todas <span><?php echo count( $propostas ); ?></span></button>
							<button type="button" data-est="pendente" aria-pressed="false">Pendentes <span><?php echo (int) $pr_cont['pendente']; ?></span></button>
							<button type="button" data-est="aprovado" aria-pressed="false">Aprovadas <span><?php echo (int) $pr_cont['aprovado']; ?></span></button>
							<button type="button" data-est="rejeitado" aria-pressed="false">Rejeitadas <span><?php echo (int) $pr_cont['rejeitado']; ?></span></button>
						</nav>
					<?php endif; ?>
				</div>
				<?php if ( $propostas ) : ?>
					<div class="mt-tabela mt-tabela--prop" id="mt-pr-tab">
						<div class="mt-tabela__cab"><span>Proposta</span><span>Tipo</span><span class="r">Valor</span><span>Estado</span><span>Decisão</span><span class="r">Submetida</span></div>
						<?php foreach ( $propostas as $p ) : ?>
							<div class="mt-tabela__linha" data-est="<?php echo esc_attr( $p['estado'] ); ?>">
								<span class="mt-prop__k"><b><?php echo esc_html( $p['titulo'] ); ?></b><?php if ( ! empty( $p['detalhe'] ) ) : ?><small><?php echo esc_html( wp_trim_words( $p['detalhe'], 18, '…' ) ); ?></small><?php endif; ?></span>
								<span class="mt-mudo"><?php echo esc_html( $pr_tipos[ $p['tipo'] ] ?? ucfirst( $p['tipo'] ) ); ?></span>
								<span class="r mt-hora"><?php echo null !== $p['valor'] ? esc_html( $fmt_valor( $p['valor'] ) ) : '—'; ?></span>
								<span><span class="mt-estado mt-estado--<?php echo esc_attr( $p['estado'] ); ?>"><?php echo esc_html( $prop_labels[ $p['estado'] ] ?? $p['estado'] ); ?></span></span>
								<span class="mt-mudo"><?php echo 'pendente' !== $p['estado'] && $p['decisor'] ? esc_html( $p['decisor'] . ( ! empty( $p['nota'] ) ? ' · ' . $p['nota'] : '' ) ) : 'A aguardar'; ?></span>
								<span class="r mt-mudo"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $p['criado_em'] ) ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="mt-vazio">Sem propostas submetidas.</p>
				<?php endif; ?>
			</section>
		</section>

	</main>

</div>
</div>
<script>
( function () {
	// Navegação de vistas + submenu da barra lateral
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
		painel: 'Visão geral', equipa: 'Equipa médica', 'novo-medico': 'Novo médico',
		especialidades: 'Especialidades', 'nova-especialidade': 'Nova especialidade',
		agenda: 'Agenda da clínica', horarios: 'Horários e folgas', financas: 'Finanças',
		propostas: 'Propor à Direção', auditoria: 'Auditoria', definicoes: 'Definições'
	};
	var tituloEl = document.querySelector( '[data-mt-titulo]' );
	function show( v ) {
		views.forEach( function ( s ) { s.hidden = ( s.dataset.view !== v ); } );
		if ( tituloEl ) { tituloEl.textContent = titulos[ v ] || 'Gestão'; }
		if ( shell ) { shell.setAttribute( 'data-vista', v ); }
		document.dispatchEvent( new CustomEvent( 'clinica:vista', { detail: v } ) );
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
	// Atalhos dentro das vistas (cartões e ligações dos painéis).
	document.querySelectorAll( '[data-ir]' ).forEach( function ( a ) {
		a.addEventListener( 'click', function ( e ) { e.preventDefault(); show( a.dataset.ir ); } );
	} );
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
	// O filtro de período recarrega a página com #painel ou #financas: volta lá.
	var marca = ( location.hash || '' ).replace( '#', '' );
	if ( 'painel' === inicial && marca && document.querySelector( '.pm-view[data-view="' + marca + '"]' ) ) { inicial = marca; }
	show( inicial );
	window.addEventListener( 'hashchange', function () {
		var v = ( location.hash || '' ).replace( '#', '' );
		if ( v && document.querySelector( '.pm-view[data-view="' + v + '"]' ) ) { show( v ); }
	} );

	// Filtro da equipa: pesquisa (texto) + categoria (especialidade)
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

	// Confirmação no sistema antes de desativar (em vez do confirm do browser)
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

	// Pré-visualização da foto do novo médico
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

	// Agenda da clínica: filtros por médico e estado; meses do calendário
	( function () {
		var lista = document.getElementById( 'mt-ag-lista' );
		var med = document.getElementById( 'mt-ag-med' ), est = document.getElementById( 'mt-ag-est' );
		var nada = document.getElementById( 'mt-ag-nada' );
		function filtrar() {
			if ( ! lista ) { return; }
			var m = med ? med.value : '', e = est ? est.value : '', dias = {}, n = 0;
			lista.querySelectorAll( '.mt-tabela__linha' ).forEach( function ( l ) {
				var ok = ( ! m || l.dataset.med === m ) && ( ! e || l.dataset.est === e );
				l.hidden = ! ok;
				if ( ok ) { dias[ l.dataset.dia ] = true; n++; }
			} );
			lista.querySelectorAll( '.mt-tabela__dia' ).forEach( function ( d ) { d.hidden = ! dias[ d.dataset.dia ]; } );
			if ( nada ) { nada.hidden = n > 0; }
		}
		if ( med ) { med.addEventListener( 'change', filtrar ); }
		if ( est ) { est.addEventListener( 'change', filtrar ); }

		var caixa = document.querySelector( '.mt-mes' );
		if ( ! caixa ) { return; }
		var meses = caixa.querySelectorAll( '.mt-mes__grelha' ), titulo = caixa.querySelector( '.mt-mes__t' );
		var setas = caixa.querySelectorAll( '[data-mes]' ), i = 0;
		function ir( k ) {
			i = Math.max( 0, Math.min( meses.length - 1, k ) );
			meses.forEach( function ( m, x ) { m.hidden = x !== i; } );
			titulo.textContent = meses[ i ].dataset.titulo;
			setas[0].disabled = 0 === i;
			setas[1].disabled = meses.length - 1 === i;
		}
		setas.forEach( function ( b ) { b.addEventListener( 'click', function () { ir( i + parseInt( b.dataset.mes, 10 ) ); } ); } );
		ir( 0 );
	} )();

	// Especialidades: pesquisa por nome ou descrição
	( function () {
		var q = document.getElementById( 'mt-esp-q' ), lista = document.getElementById( 'mt-esp-lista' );
		var nada = document.getElementById( 'mt-esp-nada' );
		if ( ! q || ! lista ) { return; }
		q.addEventListener( 'input', function () {
			var t = q.value.trim().toLowerCase(), n = 0;
			lista.querySelectorAll( '.mt-esp__item' ).forEach( function ( it ) {
				var ok = ! t || ( it.dataset.q || '' ).indexOf( t ) !== -1;
				it.hidden = ! ok;
				if ( ok ) { n++; }
			} );
			if ( nada ) { nada.hidden = n > 0; }
		} );
	} )();

	// Propostas: abas por estado
	( function () {
		var abas = document.getElementById( 'mt-pr-abas' ), tab = document.getElementById( 'mt-pr-tab' );
		if ( ! abas || ! tab ) { return; }
		abas.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( 'button' );
			if ( ! b ) { return; }
			abas.querySelectorAll( 'button' ).forEach( function ( o ) { o.setAttribute( 'aria-pressed', o === b ? 'true' : 'false' ); } );
			tab.querySelectorAll( '.mt-tabela__linha' ).forEach( function ( l ) { l.hidden = !! b.dataset.est && l.dataset.est !== b.dataset.est; } );
		} );
	} )();

	// Novo médico: pré-visualização enquanto se escreve
	( function () {
		var f = document.getElementById( 'mt-novo-form' );
		if ( ! f ) { return; }
		var vazio = { nome: 'Nome do médico', esp: 'Especialidade', ordem: '—', sala: '—' };
		var ini = document.getElementById( 'med-foto-ph' );
		function atualizar( campo ) {
			var k = campo.dataset.ver, v = campo.value.trim();
			if ( 'SELECT' === campo.tagName ) { v = campo.value ? campo.options[ campo.selectedIndex ].text : ''; }
			var saida = f.querySelector( '[data-ver-saida="' + k + '"]' );
			if ( saida ) { saida.textContent = v || vazio[ k ]; }
			if ( 'nome' === k && ini ) {
				var p = v.split( /\s+/ ).filter( Boolean );
				ini.textContent = p.length ? ( p[0][0] + ( p.length > 1 ? p[ p.length - 1 ][0] : '' ) ).toUpperCase() : '?';
			}
		}
		f.querySelectorAll( '[data-ver]' ).forEach( function ( c ) {
			c.addEventListener( 'input', function () { atualizar( c ); } );
			c.addEventListener( 'change', function () { atualizar( c ); } );
		} );
	} )();

	// Pesquisa no registo de auditoria
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

	// Animações do painel
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

	// Combobox pesquisável
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
