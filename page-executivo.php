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

// Progresso face à meta de receita do mês.
$meta_rec  = (float) $metas['receita'];
$prog_rec  = $meta_rec > 0 ? min( 100, (int) round( $dir['receita']['mes'] / $meta_rec * 100 ) ) : 0;
$meta_pac  = (int) $metas['pacientes'];
$pac_mes   = (int) $dir['pacientes']['novos'];
$prog_pac  = $meta_pac > 0 ? min( 100, (int) round( $pac_mes / $meta_pac * 100 ) ) : 0;

$labels    = array( 'pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado' );
?>
<div class="hp">
<div class="pm-shell pm-shell--admin gt">

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
			<span class="pm-nav__cap">Executivo</span>
			<?php
			$gt_menu = array(
				'painel'        => array( 'Visão geral', 'painel' ),
				'rentabilidade' => array( 'Rentabilidade', 'tendencia' ),
				'relatorios'    => array( 'Relatórios', 'registo' ),
				'aprovacoes'    => array( 'Aprovações', 'verificar' ),
			);
			if ( $pode_def ) {
				$gt_menu['metas'] = array( 'Metas', 'alvo' );
			}
			foreach ( $gt_menu as $k => $it ) : ?>
				<a class="pm-nav__i<?php echo 'painel' === $k ? ' is-active' : ''; ?>" href="#<?php echo esc_attr( $k ); ?>" data-go="<?php echo esc_attr( $k ); ?>">
					<?php echo Clinica_Painel::icone( $it[1] ); ?><span><?php echo esc_html( $it[0] ); ?></span>
					<?php if ( 'aprovacoes' === $k && $cont['pendente'] > 0 ) : ?><span class="pm-nav__badge"><?php echo (int) $cont['pendente']; ?></span><?php endif; ?>
				</a>
			<?php endforeach; ?>
			<span class="pm-nav__cap gt-cap2">Ligações</span>
			<a class="pm-nav__i" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo Clinica_Painel::icone( 'coracao' ); ?><span>Site da clínica</span></a>
		</nav>

		<div class="gt-side__barra">
			<?php if ( $pode_def ) : ?><a href="#metas" data-ir="metas" aria-label="Metas" title="Metas"><?php echo Clinica_Painel::icone( 'alvo' ); ?></a><?php endif; ?>
			<a href="#relatorios" data-ir="relatorios" aria-label="Relatórios" title="Relatórios"><?php echo Clinica_Painel::icone( 'registo' ); ?></a>
			<a href="#aprovacoes" data-ir="aprovacoes" aria-label="Aprovações" title="Aprovações"><?php echo Clinica_Painel::icone( 'verificar' ); ?></a>
			<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" aria-label="Sair" title="Sair"><?php echo Clinica_Painel::icone( 'sair' ); ?></a>
		</div>
	</aside>

	<main class="pm-main ex-exec gt-main">

		<!-- Barra de topo (a da referência): menu, avisos e utilizador -->
		<header class="gt-topo">
			<button type="button" class="gt-menu" aria-label="Recolher ou abrir o menu" aria-pressed="false" data-gt-menu>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
			</button>
			<div class="gt-topo__r">
				<a class="gt-ic" href="#aprovacoes" data-ir="aprovacoes" aria-label="<?php echo esc_attr( sprintf( _n( '%d aprovação pendente', '%d aprovações pendentes', (int) $cont['pendente'], 'clinica' ), (int) $cont['pendente'] ) ); ?>">
					<?php echo Clinica_Painel::icone( 'mensagem' ); ?>
					<?php if ( $cont['pendente'] > 0 ) : ?><b class="gt-ic__n"><?php echo (int) $cont['pendente']; ?></b><?php endif; ?>
				</a>
				<details class="gt-user">
					<summary><img src="<?php echo esc_url( $avatar ); ?>" width="30" height="30" alt=""><span><?php echo esc_html( $utilizador->display_name ); ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
					<div class="gt-user__menu">
						<a href="#relatorios" data-ir="relatorios">Relatórios</a>
						<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Sair</a>
					</div>
				</details>
			</div>
		</header>

		<?php clinica_render_flash(); ?>

		<!-- Visão geral
		     Cópia da referência "Gentelella" com as cores da clínica: faixa de
		     seis números, painel grande de atividade com a procura por
		     especialidade ao lado, três painéis (médicos, métodos, metas com
		     mostrador) e, em baixo, aprovações e pontos de atenção. Cada painel
		     recolhe e fecha como no original. Animações em assets/js/direcao.js. -->
		<?php
		$dp      = Clinica_Painel::dados( 0 );
		$at      = $dp['atual'];
		$ant     = $dp['anterior'];
		$alertas = $bi['alertas'] ?? array();
		$gt_url  = static fn( string $k ): string => add_query_arg( 'periodo', $k, remove_query_arg( array( 'periodo', 'cl' ) ) ) . '#painel';
		$contra  = 'face aos ' . $dp['label'] . ' anteriores';
		$gt_var  = static function ( ?int $v, bool $sobe_bom = true, string $un = '%' ) use ( $contra ): string {
			if ( null === $v ) {
				return '<span class="gt-tile__d">Sem período anterior</span>';
			}
			$bom = ( $v >= 0 ) === $sobe_bom;
			return '<span class="gt-tile__d"><i class="gt-' . ( $bom ? 'bom' : 'mau' ) . '">' . ( $v >= 0 ? '▲' : '▼' ) . ' ' . esc_html( abs( $v ) . $un ) . '</i> ' . esc_html( $contra ) . '</span>';
		};
		$comp_var = null === $dp['comparencia'] || null === $dp['comparencia_ant'] ? null : (int) round( $dp['comparencia'] - $dp['comparencia_ant'] );
		// [ícone, rótulo, valor mostrado, valor numérico p/ contar, formato, variação, destaque]
		$gt_tiles = array(
			array( 'financeiro', 'Faturado', Clinica_Grafico::compacto( $at['faturado'] ), $at['faturado'], 'mil', $gt_var( Clinica_Painel::var_pct( $at['faturado'], $ant['faturado'] ) ), false ),
			array( 'verificar', 'Recebido', Clinica_Grafico::compacto( $at['recebido'] ), $at['recebido'], 'mil', $gt_var( Clinica_Painel::var_pct( $at['recebido'], $ant['recebido'] ) ), false ),
			array( 'feito', 'Consultas realizadas', number_format_i18n( $at['realizadas'] ), $at['realizadas'], 'int', $gt_var( Clinica_Painel::var_pct( $at['realizadas'], $ant['realizadas'] ) ), true ),
			array( 'paciente', 'Novos pacientes', number_format_i18n( $dp['novos']['atual'] ), $dp['novos']['atual'], 'int', $gt_var( Clinica_Painel::var_pct( $dp['novos']['atual'], $dp['novos']['anterior'] ) ), false ),
			array( 'tendencia', 'Comparência', Clinica_Grafico::pct( $dp['comparencia'] ), $dp['comparencia'], 'pct', $gt_var( $comp_var, true, ' p.p.' ), false ),
			array( 'pacientes', 'Retenção', (int) $dir['pacientes']['retencao'] . '%', (int) $dir['pacientes']['retencao'], 'pct', '<span class="gt-tile__d">Pacientes que voltaram este mês</span>', false ),
		);
		$total_esp = array_sum( array_column( $dp['por_esp'], 'marcadas' ) );
		$nomes_met = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::METODOS : array();
		$total_met = array_sum( array_column( $dp['metodos'], 'v' ) );
		$cores_met = array( 'c1', 'c2', 'c3', 'c4' );
		// Ferramentas do painel (recolher e fechar), como no original.
		$gt_ferr = static function (): string {
			return '<div class="gt-ferr">'
				. '<button type="button" data-gt-recolher aria-expanded="true" aria-label="Recolher painel"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg></button>'
				. '<button type="button" data-gt-fechar aria-label="Fechar painel"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg></button>'
				. '</div>';
		};
		?>
		<section class="gt-painel pm-view" data-view="painel">

			<div class="gt-tiles" data-gt-entrada>
				<?php foreach ( $gt_tiles as $t ) : ?>
					<div class="gt-tile" data-gt-3d>
						<span class="gt-tile__l"><?php echo Clinica_Painel::icone( $t[0] ); ?><?php echo esc_html( $t[1] ); ?></span>
						<b class="gt-tile__n<?php echo $t[6] ? ' is-destaque' : ''; ?>"<?php echo null !== $t[3] ? ' data-gt-n="' . esc_attr( (string) round( (float) $t[3], 1 ) ) . '" data-gt-fmt="' . esc_attr( $t[4] ) . '"' : ''; ?>><?php echo esc_html( $t[2] ); ?></b>
						<?php echo $t[5]; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<section class="gt-x gt-atividade" aria-labelledby="gt-g-ativ" data-gt-entrada>
				<div class="gt-x__cab">
					<h2 class="gt-x__t" id="gt-g-ativ">Atividade da clínica <small>consultas marcadas e realizadas</small></h2>
					<details class="gt-datas">
						<summary><?php echo Clinica_Painel::icone( 'agenda' ); ?><span><?php echo esc_html( date_i18n( 'j \d\e F \d\e Y', $dp['desde'] ) . ' – ' . date_i18n( 'j \d\e F \d\e Y', $agora ) ); ?></span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
						<div class="gt-datas__menu">
							<?php foreach ( Clinica_Analitica::PERIODOS_MEDICO as $k => $p ) : ?>
								<a href="<?php echo esc_url( $gt_url( $k ) ); ?>"<?php echo $k === $dp['periodo'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( 'Últimos ' . $p['label'] ); ?></a>
							<?php endforeach; ?>
						</div>
					</details>
				</div>
				<div class="gt-x__corpo gt-atividade__corpo">
					<?php
					echo Clinica_Grafico::linhas(
						array(
							'titulo'  => 'Consultas ' . $dp['por_unidade'],
							'rotulos' => $dp['rotulos'],
							'longos'  => $dp['longos'],
							'saltar'  => $dp['saltar'],
							'suave'   => true,
							'areas'   => 'todas',
							'final'   => false,
							'pontos'  => 'valores',
							'series'  => array(
								// Realizadas à frente (cabem sempre dentro das marcadas, atrás).
								array( 'nome' => 'Realizadas', 'valores' => $dp['serie']['realizadas'], 'cor' => 'c1' ),
								array( 'nome' => 'Marcadas', 'valores' => $dp['serie']['marcadas'], 'cor' => 'o3' ),
							),
							'formato' => static fn( $v ) => sprintf( _n( '%d consulta', '%d consultas', (int) $v, 'clinica' ), (int) $v ),
							'eixo'    => static fn( $v ) => number_format_i18n( (int) $v ),
							'vazio'   => 'Sem consultas nos últimos ' . $dp['label'] . '.',
						)
					);
					?>
					<div class="gt-campanha">
						<p class="gt-campanha__t">Procura por especialidade</p>
						<?php if ( $dp['por_esp'] ) :
							// As cinco mais procuradas; o nome inteiro fica na dica e na tabela.
							$esp_top = array_slice( $dp['por_esp'], 0, 5, true );
							echo Clinica_Grafico::colunas(
								array(
									'titulo'    => 'Procura por especialidade',
									'rotulos'   => array_keys( $esp_top ),
									'agrupado'  => true,
									'rotular'   => true,
									'cabecalho' => 'Especialidade',
									'series'    => array(
										array( 'nome' => 'Marcadas', 'valores' => array_column( $esp_top, 'marcadas' ), 'cor' => 'o3' ),
										array( 'nome' => 'Realizadas', 'valores' => array_column( $esp_top, 'realizadas' ), 'cor' => 'c1' ),
									),
									'formato'   => static fn( $v ) => sprintf( _n( '%d consulta', '%d consultas', (int) $v, 'clinica' ), (int) $v ),
									'eixo'      => static fn( $v ) => number_format_i18n( (int) $v ),
								)
							);
						else : ?>
							<p class="gt-nota">Sem consultas neste período.</p>
						<?php endif; ?>
					</div>
				</div>
			</section>

			<div class="gt-linha3" data-gt-entrada>
				<section class="gt-x" aria-labelledby="gt-g-med">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="gt-g-med">Médicos <small>realizadas de marcadas</small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<?php if ( $dp['por_medico'] ) :
							$itens = array();
							foreach ( $dp['por_medico'] as $nome => $m ) {
								$itens[] = array(
									'rotulo' => $nome,
									'sub'    => $m['especialidade'],
									'parte'  => $m['realizadas'],
									'total'  => $m['marcadas'],
									'extra'  => array( array( 'Faltas', number_format_i18n( $m['faltas'] ) ) ),
								);
							}
							echo Clinica_Grafico::barras_parte(
								array(
									'titulo'    => 'Consultas por médico',
									'nomes'     => array( 'Realizadas', 'Marcadas' ),
									'cores'     => array( 'c1', 'o3' ),
									'itens'     => $itens,
									'cabecalho' => 'Médico',
								)
							);
						else : ?>
							<p class="gt-nota">Sem consultas neste período.</p>
						<?php endif; ?>
					</div>
				</section>

				<section class="gt-x" aria-labelledby="gt-g-met">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="gt-g-met">Métodos de pagamento</h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<?php if ( $dp['metodos'] ) : ?>
							<div class="gt-metodos">
								<p class="gt-metodos__cab"><span>Top <?php echo count( $dp['metodos'] ); ?></span><span>Método</span><span>Quota</span></p>
								<?php
								$itens = array();
								$i     = 0;
								foreach ( $dp['metodos'] as $m => $v ) {
									$itens[] = array( 'rotulo' => $nomes_met[ $m ] ?? ucfirst( (string) $m ), 'valor' => $v['v'], 'cor' => $cores_met[ $i++ % 4 ], 'texto' => Clinica_Grafico::moeda( $v['v'] ) );
								}
								echo Clinica_Grafico::anel(
									array(
										'titulo'  => 'Valor recebido por método',
										'centro'  => Clinica_Grafico::compacto( $total_met ),
										'sub'     => Clinica_Grafico::moeda_codigo(),
										'itens'   => $itens,
										'legenda' => false,
									)
								);
								?>
								<ul class="gt-metodos__lista">
									<?php foreach ( $itens as $it ) : ?>
										<li><i class="gf-c--<?php echo esc_attr( $it['cor'] ); ?>"></i><span><?php echo esc_html( $it['rotulo'] ); ?></span><b><?php echo esc_html( number_format( $total_met ? $it['valor'] / $total_met * 100 : 0, 0, ',', ' ' ) . '%' ); ?></b></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php else : ?>
							<p class="gt-nota">Sem pagamentos recebidos neste período.</p>
						<?php endif; ?>
					</div>
				</section>

				<section class="gt-x" aria-labelledby="gt-g-metas">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="gt-g-metas">Metas do mês</h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo gt-metas">
						<ul class="gt-atalhos">
							<?php if ( $pode_def ) : ?><li><a href="#metas" data-ir="metas"><?php echo Clinica_Painel::icone( 'alvo' ); ?>Metas</a></li><?php endif; ?>
							<li><a href="#relatorios" data-ir="relatorios"><?php echo Clinica_Painel::icone( 'registo' ); ?>Relatórios</a></li>
							<li><a href="#rentabilidade" data-ir="rentabilidade"><?php echo Clinica_Painel::icone( 'tendencia' ); ?>Rentabilidade</a></li>
							<li><a href="#aprovacoes" data-ir="aprovacoes"><?php echo Clinica_Painel::icone( 'verificar' ); ?>Aprovações</a></li>
							<li><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><?php echo Clinica_Painel::icone( 'sair' ); ?>Sair</a></li>
						</ul>
						<div class="gt-metas__mostrador" data-gt-3d>
							<p class="gt-metas__t">Meta de receita</p>
							<?php echo Clinica_Grafico::ponteiro( $meta_rec > 0 ? (float) $prog_rec : null, 'Progresso da meta de receita', Clinica_Grafico::compacto( (float) $dir['receita']['mes'] ), $meta_rec > 0 ? Clinica_Grafico::compacto( $meta_rec ) : 'sem meta' ); ?>
							<?php if ( $meta_rec <= 0 && $pode_def ) : ?><a class="gt-metas__def" href="#metas" data-ir="metas">Definir meta</a><?php endif; ?>
						</div>
					</div>
				</section>
			</div>

			<div class="gt-linha2" data-gt-entrada>
				<section class="gt-x" aria-labelledby="gt-g-apr">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="gt-g-apr">Aprovações recentes <small>propostas da gestão</small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<?php if ( $aprovs ) : ?>
							<ul class="gt-linhatempo">
								<?php foreach ( array_slice( $aprovs, 0, 4 ) as $ap ) : ?>
									<li>
										<b><?php echo esc_html( $ap['titulo'] ?? 'Proposta' ); ?></b>
										<small><?php echo esc_html( ( $labels[ $ap['estado'] ] ?? $ap['estado'] ) . ( ! empty( $ap['criador'] ) ? ' · ' . $ap['criador'] : '' ) . ( ! empty( $ap['criado_em'] ) ? ' · há ' . human_time_diff( strtotime( $ap['criado_em'] ), $agora ) : '' ) ); ?></small>
										<?php if ( ! empty( $ap['detalhe'] ) ) : ?><p><?php echo esc_html( wp_trim_words( $ap['detalhe'], 22, '…' ) ); ?></p><?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="gt-nota gt-nota--meio">Sem propostas da gestão.</p>
						<?php endif; ?>
					</div>
				</section>

				<section class="gt-x" aria-labelledby="gt-g-alertas">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="gt-g-alertas">Pontos de atenção <small>limiares</small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<?php if ( $alertas ) : ?>
							<ul class="gt-alertas">
								<?php foreach ( $alertas as $al ) :
									$alta = 'alta' === $al['n'];
									?>
									<li class="gt-alerta gt-alerta--<?php echo $alta ? 'alta' : 'media'; ?>">
										<span class="gt-alerta__t"><b><?php echo esc_html( $al['t'] ); ?></b><small><?php echo esc_html( $al['d'] ); ?></small></span>
										<span class="gt-alerta__n"><?php echo $alta ? 'Alta' : 'Média'; ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="gt-nota gt-nota--meio">Todos os indicadores dentro dos limites.</p>
						<?php endif; ?>
					</div>
				</section>
			</div>
		</section>

		<!-- Relatórios
		     Financeiro, por médico e pacientes por período (diário/semanal/
		     mensal), exportáveis em PDF e CSV. O estado vive no endereço para
		     sobreviver ao recarregamento e à partilha do link. -->
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

			<?php
			// Painel do relatório: números do período, um gráfico principal e dois
			// de apoio, conforme o tipo (financeiro, por médico, pacientes). A
			// tabela completa fica por baixo e é o que se exporta.
			if ( $rel_dados['linhas'] ) :
				$rl      = $rel_dados['linhas'];
				$rn      = count( $rl );
				$rot     = array_map( static fn( $l ) => (string) $l[0], $rl );
				// Eixo curto: "Jul", "27/07" (semana: a data de início), "12/10" (dia).
				$curtos  = array_map(
					static function ( string $r ) use ( $rel_grupo ): string {
						if ( 'mes' === $rel_grupo ) {
							return mb_substr( $r, 0, 3 );
						}
						if ( 'semana' === $rel_grupo && false !== mb_strpos( $r, '· ' ) ) {
							return trim( mb_substr( $r, mb_strpos( $r, '· ' ) + 2 ) );
						}
						return 'dia' === $rel_grupo ? mb_substr( $r, 0, 5 ) : $r;
					},
					$rot
				);
				$saltar  = max( 1, (int) ceil( $rn / 12 ) );
				$poucos  = $rn <= 14;
				$f_int   = static fn( $v ) => number_format_i18n( (int) round( (float) $v ) );
				$f_comp  = static fn( $v ) => Clinica_Grafico::compacto( (float) $v );
				$acum    = static function ( array $v ): array {
					$t = 0.0;
					$r = array();
					foreach ( $v as $x ) {
						$t  += (float) $x;
						$r[] = $t;
					}
					return $r;
				};
				$por     = 'medicos' === $rel_tipo ? '' : mb_strtolower( Clinica_Relatorios::GRUPOS[ $rel_grupo ] );
				$melhor  = static function ( array $v ) use ( $rot ): array {
					$m = $v ? max( $v ) : 0;
					return $m > 0 ? array( $m, $rot[ array_search( $m, $v, true ) ] ) : array( 0, 'Sem registos' );
				};
				// Faixa de números: [ícone, rótulo, valor, 'moeda'|'int', nota, destaque].
				$rel_tile = static function ( array $t ) use ( $moeda, $fmt_valor ): string {
					$moe = 'moeda' === $t[3];
					$dec = 'dec' === $t[3];
					$h   = '<div class="gt-tile" data-gt-3d><span class="gt-tile__l">' . Clinica_Painel::icone( $t[0] ) . esc_html( $t[1] ) . '</span>';
					$h  .= '<b class="gt-tile__n' . ( $t[5] ? ' is-destaque' : '' ) . '"' . ( $moe ? ' title="' . esc_attr( $fmt_valor( $t[2] ) ) . '"' : '' ) . '>';
					$txt = $moe ? Clinica_Grafico::compacto( (float) $t[2] ) : ( $dec ? number_format( (float) $t[2], 1, ',', ' ' ) : number_format_i18n( (int) round( (float) $t[2] ) ) );
					$h  .= '<span data-gt-n="' . esc_attr( (string) round( (float) $t[2], $dec ? 1 : 0 ) ) . '" data-gt-fmt="' . ( $moe ? 'mil' : ( $dec ? 'dec' : 'int' ) ) . '">' . esc_html( $txt ) . '</span>';
					$h  .= $moe ? ' <small>' . esc_html( $moeda ) . '</small>' : '';
					return $h . '</b><span class="gt-tile__d">' . esc_html( $t[4] ) . '</span></div>';
				};
				$painel = static function ( string $id, string $titulo, string $sub, string $corpo, string $classe = '' ) use ( $gt_ferr ): string {
					return '<section class="gt-x' . ( $classe ? ' ' . $classe : '' ) . '" aria-labelledby="' . esc_attr( $id ) . '">'
						. '<div class="gt-x__cab"><h2 class="gt-x__t" id="' . esc_attr( $id ) . '">' . esc_html( $titulo ) . ( $sub ? ' <small>' . esc_html( $sub ) . '</small>' : '' ) . '</h2>' . $gt_ferr() . '</div>'
						. '<div class="gt-x__corpo">' . $corpo . '</div></section>';
				};

				if ( 'financeiro' === $rel_tipo ) {
					$rec   = array_map( static fn( $l ) => (float) $l[1], $rl );
					$pag   = array_map( static fn( $l ) => (float) $l[2], $rl );
					$tot   = array_sum( $rec );
					$npag  = array_sum( $pag );
					$best  = $melhor( $rec );
					$tiles = array(
						array( 'dinheiro', 'Receita', $tot, 'moeda', 'No período do relatório', true ),
						array( 'registo', 'Pagamentos', $npag, 'int', 'Pagamentos recebidos', false ),
						array( 'financeiro', 'Valor médio', $npag > 0 ? $tot / $npag : 0, 'moeda', 'Por pagamento', false ),
						array( 'tendencia', 'Melhor período', $best[0], 'moeda', $best[1], false ),
					);
					$g_main = $painel(
						'gt-rel-g1',
						'Receita',
						$por,
						Clinica_Grafico::colunas(
							array(
								'titulo'  => 'Receita ' . $por,
								'rotulos' => $curtos,
								'longos'  => $rot,
								'saltar'  => $saltar,
								'rotular' => $poucos,
								'series'  => array( array( 'nome' => 'Receita', 'valores' => $rec, 'cor' => 'c1' ) ),
								'formato' => $fmt_valor,
								'eixo'    => $f_comp,
								'minimo'  => 1000,
							)
						),
						'gt-rel'
					);
					$g_a = $painel(
						'gt-rel-g2',
						'Pagamentos',
						$por,
						Clinica_Grafico::linhas(
							array(
								'titulo'  => 'Pagamentos ' . $por,
								'rotulos' => $curtos,
								'longos'  => $rot,
								'saltar'  => $saltar,
								'suave'   => true,
								'pontos'  => 'valores',
								'final'   => false,
								'series'  => array( array( 'nome' => 'Pagamentos', 'valores' => $pag, 'cor' => 'o3' ) ),
								'formato' => static fn( $v ) => sprintf( _n( '%d pagamento', '%d pagamentos', (int) $v, 'clinica' ), (int) $v ),
								'eixo'    => $f_int,
							)
						)
					);
					$g_b = $painel(
						'gt-rel-g3',
						'Receita acumulada',
						'desde o início do relatório',
						Clinica_Grafico::linhas(
							array(
								'titulo'  => 'Receita acumulada',
								'rotulos' => $curtos,
								'longos'  => $rot,
								'saltar'  => $saltar,
								'suave'   => true,
								'series'  => array( array( 'nome' => 'Acumulado', 'valores' => $acum( $rec ), 'cor' => 'c3' ) ),
								'formato' => $fmt_valor,
								'eixo'    => $f_comp,
								'minimo'  => 1000,
							)
						)
					);
				} elseif ( 'medicos' === $rel_tipo ) {
					$meds = $rl;
					usort( $meds, static fn( $x, $y ) => (float) $y[3] <=> (float) $x[3] );
					$tot   = array_sum( array_map( static fn( $l ) => (float) $l[3], $meds ) );
					$ncon  = array_sum( array_map( static fn( $l ) => (float) $l[2], $meds ) );
					$ativos = count( array_filter( $meds, static fn( $l ) => (float) $l[2] > 0 ) );
					$tiles = array(
						array( 'dinheiro', 'Receita', $tot, 'moeda', 'Últimos 90 dias', true ),
						array( 'consultas', 'Consultas', $ncon, 'int', 'Realizadas', false ),
						array( 'financeiro', 'Valor médio', $ncon > 0 ? $tot / $ncon : 0, 'moeda', 'Por consulta', false ),
						array( 'medico', 'Médicos ativos', $ativos, 'int', 'Com consultas no período', false ),
					);
					$b_rec = array();
					$b_con = array();
					foreach ( $meds as $l ) {
						$b_rec[] = array( 'rotulo' => (string) $l[0], 'valor' => (float) $l[3], 'texto' => $fmt_valor( $l[3] ), 'det' => (string) $l[1] );
						$b_con[] = array( 'rotulo' => (string) $l[0], 'valor' => (float) $l[2], 'texto' => sprintf( _n( '%d consulta', '%d consultas', (int) $l[2], 'clinica' ), (int) $l[2] ), 'det' => (string) $l[1] );
					}
					usort( $b_con, static fn( $x, $y ) => $y['valor'] <=> $x['valor'] );
					$g_main = $painel( 'gt-rel-g1', 'Receita por médico', 'últimos 90 dias', '<div class="gt-barras-linha gt-barras-linha--rel">' . Clinica_Grafico::barras( array( 'titulo' => 'Receita por médico', 'itens' => $b_rec ) ) . '</div>', 'gt-rel' );
					// Quota: até quatro cores validadas; o resto junta-se em "Outros".
					$quota = array_slice( $meds, 0, count( $meds ) > 4 ? 3 : 4 );
					$itens = array();
					foreach ( $quota as $k => $l ) {
						$itens[] = array( 'rotulo' => (string) $l[0], 'valor' => (float) $l[3], 'cor' => array( 'c1', 'c2', 'c3', 'c4' )[ $k ], 'texto' => $fmt_valor( $l[3] ) );
					}
					if ( count( $meds ) > 4 ) {
						$resto   = array_sum( array_map( static fn( $l ) => (float) $l[3], array_slice( $meds, 3 ) ) );
						$itens[] = array( 'rotulo' => 'Outros', 'valor' => $resto, 'cor' => 'ctx', 'texto' => $fmt_valor( $resto ) );
					}
					$lista = '';
					foreach ( $itens as $it ) {
						$lista .= '<li><i class="gf-c--' . esc_attr( $it['cor'] ) . '"></i><span>' . esc_html( $it['rotulo'] ) . '</span><em>' . esc_html( Clinica_Grafico::compacto( $it['valor'] ) ) . '</em><b>' . esc_html( number_format( $tot > 0 ? $it['valor'] / $tot * 100 : 0, 0, ',', ' ' ) . '%' ) . '</b></li>';
					}
					$g_a = $painel(
						'gt-rel-g2',
						'Quota da receita',
						'',
						$tot > 0
							? '<div class="gt-metodos gt-metodos--valor"><p class="gt-metodos__cab"><span>Total</span><span>Médico</span><span>Receita</span></p>'
								. Clinica_Grafico::anel( array( 'titulo' => 'Quota da receita por médico', 'centro' => Clinica_Grafico::compacto( $tot ), 'sub' => $moeda, 'itens' => $itens, 'legenda' => false ) )
								. '<ul class="gt-metodos__lista">' . $lista . '</ul></div>'
							: '<p class="gt-nota gt-nota--meio">Sem receita nos últimos 90 dias.</p>'
					);
					$g_b = $painel( 'gt-rel-g3', 'Consultas por médico', 'realizadas', '<div class="gt-barras-linha gt-barras-linha--o3">' . Clinica_Grafico::barras( array( 'titulo' => 'Consultas por médico', 'itens' => $b_con ) ) . '</div>' );
				} else {
					$nov   = array_map( static fn( $l ) => (float) $l[1], $rl );
					$con   = array_map( static fn( $l ) => (float) $l[2], $rl );
					$best  = $melhor( $nov );
					$tiles = array(
						array( 'paciente', 'Novos pacientes', array_sum( $nov ), 'int', 'No período do relatório', true ),
						array( 'consultas', 'Consultas realizadas', array_sum( $con ), 'int', 'No mesmo período', false ),
						array( 'pacientes', 'Média de novos', $rn > 0 ? array_sum( $nov ) / $rn : 0, 'dec', 'Por período', false ),
						array( 'tendencia', 'Melhor período', $best[0], 'int', $best[1], false ),
					);
					$f_con  = static fn( $v ) => sprintf( _n( '%d consulta', '%d consultas', (int) $v, 'clinica' ), (int) $v );
					$f_pac  = static fn( $v ) => sprintf( _n( '%d paciente', '%d pacientes', (int) $v, 'clinica' ), (int) $v );
					$g_main = $painel(
						'gt-rel-g1',
						'Pacientes e consultas',
						$por,
						Clinica_Grafico::linhas(
							array(
								'titulo'  => 'Novos pacientes e consultas ' . $por,
								'rotulos' => $curtos,
								'longos'  => $rot,
								'saltar'  => $saltar,
								'suave'   => true,
								'areas'   => 'todas',
								'pontos'  => 'valores',
								'final'   => false,
								'series'  => array(
									array( 'nome' => 'Novos pacientes', 'valores' => $nov, 'cor' => 'c1' ),
									array( 'nome' => 'Consultas realizadas', 'valores' => $con, 'cor' => 'o3' ),
								),
								'formato' => $f_int,
								'eixo'    => $f_int,
							)
						),
						'gt-rel gt-atividade'
					);
					$g_a = $painel(
						'gt-rel-g2',
						'Novos pacientes',
						$por,
						Clinica_Grafico::colunas(
							array(
								'titulo'  => 'Novos pacientes ' . $por,
								'rotulos' => $curtos,
								'longos'  => $rot,
								'saltar'  => $saltar,
								'rotular' => $poucos,
								'series'  => array( array( 'nome' => 'Novos pacientes', 'valores' => $nov, 'cor' => 'c1' ) ),
								'formato' => $f_pac,
								'eixo'    => $f_int,
							)
						)
					);
					$g_b = $painel(
						'gt-rel-g3',
						'Novos pacientes acumulados',
						'desde o início do relatório',
						Clinica_Grafico::linhas(
							array(
								'titulo'  => 'Novos pacientes acumulados',
								'rotulos' => $curtos,
								'longos'  => $rot,
								'saltar'  => $saltar,
								'suave'   => true,
								'series'  => array( array( 'nome' => 'Acumulado', 'valores' => $acum( $nov ), 'cor' => 'c3' ) ),
								'formato' => $f_pac,
								'eixo'    => $f_int,
							)
						)
					);
				}
				?>
				<div class="gt-tiles gt-tiles--4" data-gt-entrada>
					<?php foreach ( $tiles as $t ) { echo $rel_tile( $t ); } ?>
				</div>
				<div data-gt-entrada><?php echo $g_main; ?></div>
				<div class="gt-linha2" data-gt-entrada><?php echo $g_a . $g_b; ?></div>
			<?php endif; ?>

			<section class="gt-x" aria-labelledby="gt-rel-tab" data-gt-entrada>
				<div class="gt-x__cab">
					<h2 class="gt-x__t" id="gt-rel-tab">Tabela <small><?php echo esc_html( sprintf( _n( '%d linha', '%d linhas', count( $rel_dados['linhas'] ), 'clinica' ), count( $rel_dados['linhas'] ) ) ); ?></small></h2>
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
					<p class="gt-nota gt-nota--meio">Sem dados para este relatório.</p>
				<?php endif; ?>
			</section>
		</section>

		<!-- Rentabilidade -->
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
			$fin      = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::totais() : array( 'pago_valor' => $esp_total, 'pendente_valor' => 0, 'pago_n' => $pag_total, 'pendente_n' => 0 );
				$faturado = (float) $fin['pago_valor'] + (float) $fin['pendente_valor'];
				$taxa     = $faturado > 0 ? round( (float) $fin['pago_valor'] / $faturado * 100 ) : 0;
			?>
			<div class="gt-tiles gt-tiles--4" data-gt-entrada>
				<?php
				// [ícone, rótulo, valor, em moeda?, nota, destaque]
				$rk = array(
					array( 'dinheiro', 'Receita recebida', (float) $fin['pago_valor'], true, sprintf( _n( '%d pagamento', '%d pagamentos', (int) $fin['pago_n'], 'clinica' ), (int) $fin['pago_n'] ), false ),
					array( 'pendente', 'Por cobrar', (float) $fin['pendente_valor'], true, sprintf( _n( '%d pendente', '%d pendentes', (int) $fin['pendente_n'], 'clinica' ), (int) $fin['pendente_n'] ), false ),
					array( 'tendencia', 'Taxa de cobrança', (float) $taxa, false, 'Recebido do faturado', true ),
					array( 'financeiro', 'Valor médio', (float) $ticket, true, 'Por pagamento', false ),
				);
				foreach ( $rk as $k ) : ?>
					<div class="gt-tile" data-gt-3d>
						<span class="gt-tile__l"><?php echo Clinica_Painel::icone( $k[0] ); ?><?php echo esc_html( $k[1] ); ?></span>
						<b class="gt-tile__n<?php echo $k[5] ? ' is-destaque' : ''; ?>"><span data-gt-n="<?php echo esc_attr( (string) round( $k[2] ) ); ?>" data-gt-fmt="<?php echo $k[3] ? 'int' : 'pct'; ?>"><?php echo esc_html( $k[3] ? number_format( $k[2], 0, ',', ' ' ) : (int) $k[2] . '%' ); ?></span><?php if ( $k[3] ) : ?> <small><?php echo esc_html( $moeda ); ?></small><?php endif; ?></b>
						<span class="gt-tile__d"><?php echo esc_html( $k[4] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="gt-linha2" data-gt-entrada>
				<section class="gt-x" aria-labelledby="gt-r-esp">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="gt-r-esp">Receita por especialidade <small>histórico pago</small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<?php if ( $r_esp && $esp_total > 0 ) : ?>
							<div class="gt-metodos gt-metodos--valor">
								<p class="gt-metodos__cab"><span>Total</span><span>Especialidade</span><span>Receita</span></p>
								<?php
								// Quatro cores validadas: além de quatro, as menores juntam-se em "Outras".
								$ordem = $r_esp;
								usort( $ordem, static fn( $x, $y ) => (float) $y['receita'] <=> (float) $x['receita'] );
								if ( count( $ordem ) > 4 ) {
									$resto = array_slice( $ordem, 3 );
									$ordem = array_slice( $ordem, 0, 3 );
									$ordem[] = array( 'nome' => 'Outras', 'receita' => array_sum( array_column( $resto, 'receita' ) ), 'n' => array_sum( array_column( $resto, 'n' ) ), 'cor' => 'ctx' );
								}
								// Pagamentos sem consulta/especialidade: entram à parte, para o
								// total do anel bater com a receita recebida da faixa de cima.
								$sem_esp = (float) $fin['pago_valor'] - $esp_total;
								if ( $sem_esp >= 1 ) {
									$n_sem = max( 0, (int) $fin['pago_n'] - $pag_total );
									if ( isset( $resto ) ) {
										$k_out                      = count( $ordem ) - 1;
										$ordem[ $k_out ]['receita'] += $sem_esp;
										$ordem[ $k_out ]['n']       += $n_sem;
									} else {
										$ordem[] = array( 'nome' => 'Sem especialidade', 'receita' => $sem_esp, 'n' => $n_sem, 'cor' => 'ctx' );
									}
									$esp_total += $sem_esp;
								}
								$itens = array();
								foreach ( $ordem as $k => $e ) {
									$itens[] = array(
										'rotulo' => $e['nome'],
										'valor'  => (float) $e['receita'],
										'cor'    => $e['cor'] ?? array( 'c1', 'c2', 'c3', 'c4' )[ $k ],
										'texto'  => $fmt_valor( $e['receita'] ) . ' · ' . sprintf( _n( '%d pagamento', '%d pagamentos', (int) $e['n'], 'clinica' ), (int) $e['n'] ),
									);
								}
								echo Clinica_Grafico::anel(
									array(
										'titulo'  => 'Receita por especialidade',
										'centro'  => Clinica_Grafico::compacto( $esp_total ),
										'sub'     => $moeda,
										'itens'   => $itens,
										'legenda' => false,
									)
								);
								?>
								<ul class="gt-metodos__lista">
									<?php foreach ( $itens as $it ) : ?>
										<li><i class="gf-c--<?php echo esc_attr( $it['cor'] ); ?>"></i><span><?php echo esc_html( $it['rotulo'] ); ?></span><em><?php echo esc_html( Clinica_Grafico::compacto( $it['valor'] ) ); ?></em><b><?php echo esc_html( number_format( $it['valor'] / $esp_total * 100, 0, ',', ' ' ) . '%' ); ?></b></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php else : ?>
							<p class="gt-nota gt-nota--meio">Ainda sem receita registada.</p>
						<?php endif; ?>
					</div>
				</section>

				<section class="gt-x" aria-labelledby="gt-r-med">
					<div class="gt-x__cab"><h2 class="gt-x__t" id="gt-r-med">Receita por médico <small>top 8</small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<?php if ( $r_med ) : ?>
							<ul class="gt-versoes gt-versoes--valor">
								<?php
								$max_m = max( 1.0, max( array_map( static fn( $m ) => (float) $m['receita'], $r_med ) ) );
								foreach ( $r_med as $m ) :
									?>
									<li>
										<span class="gt-versoes__r"><?php echo esc_html( $m['nome'] ); ?></span>
										<i class="gt-versoes__t"><em data-gt-barra style="width:<?php echo esc_attr( (string) round( (float) $m['receita'] / $max_m * 100, 1 ) ); ?>%"></em></i>
										<b title="<?php echo esc_attr( $fmt_valor( $m['receita'] ) ); ?>"><?php echo esc_html( Clinica_Grafico::compacto( (float) $m['receita'] ) ); ?></b>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p class="gt-nota gt-nota--meio">Ainda sem receita por médico.</p>
						<?php endif; ?>
					</div>
				</section>
			</div>

			<?php if ( $r_med ) : ?>
				<section class="gt-x" aria-labelledby="gt-r-tab" data-gt-entrada>
					<div class="gt-x__cab"><h2 class="gt-x__t" id="gt-r-tab">Detalhe por médico <small>histórico pago</small></h2><?php echo $gt_ferr(); ?></div>
					<div class="gt-x__corpo">
						<div class="bi-rtbl-wrap">
							<table class="bi-rtbl gt-tabela">
								<thead>
									<tr><th class="bi-rtbl__pos">#</th><th>Médico</th><th>Especialidade</th><th class="r">Pagamentos</th><th class="r">Receita</th><th class="r">Valor médio</th></tr>
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
					</div>
				</section>
			<?php endif; ?>
		</section>

		<!-- Aprovações
		     Caixa de decisão da Direção. Os pedidos SOBEM da Gestão (portal
		     tático); aqui só se decide. Comunicação entre portais pela tabela
		     clinica_aprovacoes. -->
		<section class="pm-view" data-view="aprovacoes" hidden>
			<div class="gt-tiles gt-tiles--3" data-gt-entrada>
				<?php
				$ck = array(
					array( 'pendente', 'Pendentes', $cont['pendente'], 'A aguardar decisão', $cont['pendente'] > 0 ),
					array( 'verificar', 'Aprovadas', $cont['aprovado'], 'Decididas a favor', false ),
					array( 'rejeitar', 'Rejeitadas', $cont['rejeitado'], 'Decididas contra', false ),
				);
				foreach ( $ck as $c ) : ?>
					<div class="gt-tile" data-gt-3d>
						<span class="gt-tile__l"><?php echo Clinica_Painel::icone( $c[0] ); ?><?php echo esc_html( $c[1] ); ?></span>
						<b class="gt-tile__n<?php echo $c[4] ? ' is-destaque' : ''; ?>" data-gt-n="<?php echo (int) $c[2]; ?>" data-gt-fmt="int"><?php echo (int) $c[2]; ?></b>
						<span class="gt-tile__d"><?php echo esc_html( $c[3] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<section class="gt-x gt-pedidos" aria-labelledby="gt-a-ped" data-gt-entrada>
				<div class="gt-x__cab">
					<h2 class="gt-x__t" id="gt-a-ped">Pedidos da Gestão</h2>
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
					<p class="gt-nota gt-nota--meio">Sem pedidos da Gestão.</p>
				<?php endif; ?>
			</section>
		</section>

		<!-- Metas
		     Cada objetivo num cartão: onde se está agora, o progresso e o campo
		     para definir. Assim o ecrã explica-se sozinho, com ou sem metas. -->
		<?php if ( $pode_def ) : ?>
			<section class="pm-view ex-rent" data-view="metas" hidden>
				<?php
				$rec_mes   = (float) $dir['receita']['mes'];
				$falta_rec = max( 0, $meta_rec - $rec_mes );
				$falta_pac = max( 0, $meta_pac - $pac_mes );
				$mes_nome  = ucfirst( date_i18n( 'F', $agora ) );
				?>
				<div class="gt-pagina" data-gt-entrada>
					<h2 class="gt-pagina__t">Metas de <?php echo esc_html( $mes_nome ); ?></h2>
					<p class="gt-pagina__s">Objetivos do mês e o progresso até agora.</p>
				</div>

				<form method="post" class="ex-form">
					<?php wp_nonce_field( 'dir_metas', 'dir_nonce' ); ?>
					<input type="hidden" name="dir_action" value="metas">
					<div class="gt-linha2" data-gt-entrada>
						<!-- Receita -->
						<section class="gt-x ex-goal">
							<div class="gt-x__cab"><h3 class="gt-x__t">Receita <small><?php echo esc_html( $mes_nome ); ?></small></h3></div>
							<div class="gt-x__corpo">
							<p class="ex-goal__now">Este mês vai em <b><?php echo esc_html( $fmt_valor( $rec_mes ) ); ?></b></p>
							<div class="gt-metas__mostrador" data-gt-3d>
								<?php echo Clinica_Grafico::ponteiro( $meta_rec > 0 ? (float) $prog_rec : null, 'Progresso da meta de receita', Clinica_Grafico::compacto( $rec_mes ), $meta_rec > 0 ? Clinica_Grafico::compacto( $meta_rec ) : 'sem meta' ); ?>
							</div>
							<?php if ( $meta_rec > 0 ) : ?>
								<p class="ex-goal__stat"><b><?php echo (int) $prog_rec; ?>%</b> da meta · faltam <?php echo esc_html( $fmt_valor( $falta_rec ) ); ?></p>
							<?php else : ?>
								<p class="ex-goal__stat ex-goal__stat--off">Sem meta definida</p>
							<?php endif; ?>
							<label class="ge-field ex-goal__field">
								<span>Objetivo de receita (<?php echo esc_html( $moeda ); ?>)</span>
								<input type="number" name="meta_receita" min="0" step="any" value="<?php echo esc_attr( $meta_rec > 0 ? (string) $meta_rec : '' ); ?>" placeholder="Ex.: 150000">
							</label>
							</div>
						</section>

						<!-- Pacientes novos -->
						<section class="gt-x ex-goal">
							<div class="gt-x__cab"><h3 class="gt-x__t">Pacientes novos <small><?php echo esc_html( $mes_nome ); ?></small></h3></div>
							<div class="gt-x__corpo">
							<p class="ex-goal__now">Este mês entraram <b><?php echo (int) $pac_mes; ?></b></p>
							<div class="gt-metas__mostrador" data-gt-3d>
								<?php echo Clinica_Grafico::ponteiro( $meta_pac > 0 ? (float) $prog_pac : null, 'Progresso da meta de novos pacientes', (string) $pac_mes, $meta_pac > 0 ? (string) $meta_pac : 'sem meta' ); ?>
							</div>
							<?php if ( $meta_pac > 0 ) : ?>
								<p class="ex-goal__stat"><b><?php echo (int) $prog_pac; ?>%</b> da meta · faltam <?php echo (int) $falta_pac; ?></p>
							<?php else : ?>
								<p class="ex-goal__stat ex-goal__stat--off">Sem meta definida</p>
							<?php endif; ?>
							<label class="ge-field ex-goal__field">
								<span>Objetivo de novos pacientes</span>
								<input type="number" name="meta_pacientes" min="0" step="1" value="<?php echo esc_attr( $meta_pac > 0 ? (string) $meta_pac : '' ); ?>" placeholder="Ex.: 40">
							</label>
							</div>
						</section>
					</div>

					<div class="ex-metafoot">
						<span class="ex-metafoot__hint">Zero retira a meta.</span>
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
		document.dispatchEvent( new CustomEvent( 'clinica:vista', { detail: v } ) );
		items.forEach( function ( i ) { i.classList.toggle( 'is-active', i.dataset.go === v ); } );
		if ( shell ) { shell.classList.toggle( 'is-full', v !== 'painel' ); }
		if ( ! silencioso && window.history && history.replaceState ) {
			history.replaceState( null, '', '#' + v );
		}
		window.scrollTo( 0, 0 );
		if ( 'painel' === v || 'pacientes' === v ) { tocar(); }
	}
	items.forEach( function ( i ) { i.addEventListener( 'click', function ( e ) { e.preventDefault(); show( i.dataset.go ); } ); } );
	var botaoMenu = document.querySelector( '[data-gt-menu]' );
	if ( botaoMenu && shell ) {
		var recolhido = false;
		try { recolhido = '1' === localStorage.getItem( 'clinica_gt_menu' ); } catch ( e ) {}
		var aplicarMenu = function () {
			shell.classList.toggle( 'gt--recolhido', recolhido );
			botaoMenu.setAttribute( 'aria-pressed', recolhido ? 'true' : 'false' );
		};
		aplicarMenu();
		botaoMenu.addEventListener( 'click', function () {
			recolhido = ! recolhido;
			try { localStorage.setItem( 'clinica_gt_menu', recolhido ? '1' : '0' ); } catch ( e ) {}
			aplicarMenu();
		} );
	}
	var conta = document.querySelector( '.gt-user' );
	if ( conta ) {
		document.addEventListener( 'click', function ( e ) {
			if ( conta.open && ( ! conta.contains( e.target ) || e.target.closest( '.gt-user__menu a' ) ) ) { conta.open = false; }
		} );
	}
	// Atalhos dentro das vistas (faixa do mês, cartões e ligações dos painéis).
	document.querySelectorAll( '[data-ir]' ).forEach( function ( a ) {
		a.addEventListener( 'click', function ( e ) { e.preventDefault(); show( a.dataset.ir ); } );
	} );

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

	// Contagem dos números
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

	// Filtro das aprovações por estado
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

	// Combobox pesquisável (tipo da proposta)
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
