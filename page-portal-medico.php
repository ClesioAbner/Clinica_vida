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
$papel     = 'Dra.' === $titulo( $nome_med ) ? 'Médica' : 'Médico';
$hoje_ymd  = date_i18n( 'Y-m-d', $agora );
$futuras   = array_values( array_filter( $todas, static function ( $c ) { return strtotime( $c['inicio'] ) >= time() && in_array( $c['status'], array( 'pendente', 'confirmada' ), true ); } ) );
usort( $futuras, static function ( $a, $b ) { return strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] ); } );
// Acesso a todas as fichas de pacientes (continuidade de cuidados — decisão do dono).
$pacientes = Clinica_Repo::pacientes_do_medico( 0 );

// Ficha do paciente (?pac=ID) — só pacientes do próprio médico
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
// Atendimento (?atender=ID) — só consultas do próprio médico, não encerradas
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

// Desempenho do período (só dados reais)
// O período escolhido (?periodo=) filtra os números e o gráfico do Painel.
// A médica não vê valores financeiros: isso é da receção e da gestão.
$dp = Clinica_Painel::dados( $e_clinico ? (int) $medico_id : 0 );

// Painel na forma da referência (MEDIX)
// Variação de um número face ao período anterior (sem base, não se inventa).
$mx_var = static function ( $a, $b ): ?int {
	return $b > 0 ? (int) round( ( $a - $b ) / $b * 100 ) : null;
};
$mx_delta = static function ( ?int $v ): string {
	return null === $v ? '—' : ( $v >= 0 ? '+' : '−' ) . abs( $v ) . '%';
};
$mx_iniciais = static fn( string $nome ): string => Clinica_Painel::iniciais( $nome );
$mx_periodo_url = static function ( string $k, string $vista ): string {
	return add_query_arg( 'periodo', $k, remove_query_arg( array( 'periodo', 'cl', 'estado', 'pac', 'atender' ) ) ) . '#' . $vista;
};

// Frase do dia, por baixo da saudação.
if ( $hoje_total ) {
	$mx_resumo = sprintf( _n( 'Tem %d consulta hoje', 'Tem %d consultas hoje', $hoje_total, 'clinica' ), $hoje_total )
		. ( $aguardam ? sprintf( _n( ' e %d paciente já na sala de espera.', ' e %d pacientes já na sala de espera.', $aguardam, 'clinica' ), $aguardam ) : '.' );
} elseif ( $proxima ) {
	$ts_prox   = strtotime( $proxima['inicio'] );
	$mx_resumo = 'Sem consultas hoje. A próxima é a ' . date_i18n( 'j \d\e F', $ts_prox ) . ', às ' . date_i18n( 'H:i', $ts_prox ) . '.';
} else {
	$mx_resumo = 'Sem consultas agendadas. As novas marcações aparecem aqui assim que forem feitas.';
}

// Agenda da semana: consultas por dia (sem canceladas), da semana passada a
// quatro semanas à frente. As setas trocam de semana sem novo pedido.
$mx_por_dia = array();
foreach ( $todas as $c ) {
	if ( 'cancelada' !== $c['status'] ) {
		$mx_por_dia[ substr( $c['inicio'], 0, 10 ) ][] = $c;
	}
}
foreach ( $mx_por_dia as $k => $lista ) {
	usort( $lista, static fn( $a, $b ) => strtotime( $a['inicio'] ) <=> strtotime( $b['inicio'] ) );
	$mx_por_dia[ $k ] = $lista;
}
$mx_segunda = strtotime( 'monday this week', $agora );
$mx_semanas = array();
for ( $w = -1; $w <= 4; $w++ ) {
	$ini  = strtotime( sprintf( '%+d week', $w ), $mx_segunda );
	$dias = array();
	for ( $d = 0; $d < 7; $d++ ) {
		$dias[] = strtotime( "+{$d} day", $ini );
	}
	$mx_semanas[] = array( 'mes' => ucfirst( date_i18n( 'F Y', strtotime( '+3 day', $ini ) ) ), 'dias' => $dias, 'atual' => 0 === $w );
}
$mx_sem_nomes = array( 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom' );

// Retornos previstos (definidos ao concluir uma consulta em Atender).
$mx_retornos = array_values( array_filter( $todas, static fn( $c ) => ! empty( $c['retorno_em'] ) && $c['retorno_em'] >= $hoje_ymd ) );
usort( $mx_retornos, static fn( $a, $b ) => strcmp( (string) $a['retorno_em'], (string) $b['retorno_em'] ) );

// Pacientes de hoje; sem consultas hoje, os próximos.
$mx_lista_hoje = (bool) $fila_hoje;
$mx_pacientes  = array_slice( $fila_hoje ?: $prox, 0, 4 );

// Próxima consulta: ficha e processo (idade, alergias) do paciente.
$mx_prox_ficha = $proxima ? Clinica_Repo::paciente_ficha( (int) $proxima['paciente_id'] ) : null;
$mx_prox_proc  = ( $proxima && class_exists( 'Clinica_Processo' ) ) ? Clinica_Processo::do_paciente( (int) $proxima['paciente_id'] ) : null;
$mx_prox_idade = $mx_prox_ficha ? Clinica_Repo::idade( $mx_prox_ficha['data_nascimento'] ?? null ) : null;
$mx_foto       = function_exists( 'clinica_avatar_url' ) ? clinica_avatar_url( $utilizador->ID, 400 ) : $avatar;

// Ícones e iniciais partilhados pelos portais (Clinica_Painel).
$mx_svg = static fn( string $k ): string => Clinica_Painel::icone( $k );

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

?>
<div class="hp">
<div class="pm-shell mx">

	<!-- Topo: marca à esquerda; pesquisa, avisos e perfil à direita -->
	<header class="mx-topo">
		<a class="mx-marca" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="30" height="30" alt="">
			<span>Clínica Vida</span>
		</a>
		<div class="mx-topo__acoes">
			<a class="mx-icone" href="#pacientes" data-ir="pacientes" aria-label="Procurar paciente"><?php echo $mx_svg( 'procurar' ); ?></a>
			<a class="mx-icone" href="<?php echo esc_url( add_query_arg( 'estado', 'pendente', remove_query_arg( array( 'estado', 'pac', 'atender', 'cl', 'periodo' ) ) ) . '#consultas' ); ?>" aria-label="<?php echo esc_attr( sprintf( _n( '%d consulta por confirmar', '%d consultas por confirmar', (int) $kpis['pendente'], 'clinica' ), (int) $kpis['pendente'] ) ); ?>">
				<?php echo $mx_svg( 'sino' ); ?>
				<?php if ( (int) $kpis['pendente'] ) : ?><i class="mx-icone__ponto"></i><?php endif; ?>
			</a>
			<details class="mx-perfil">
				<summary>
					<img src="<?php echo esc_url( $avatar ); ?>" width="40" height="40" alt="">
					<span class="mx-perfil__t"><b><?php echo esc_html( $titulo( $nome_med ) . ' ' . $nome_med ); ?></b><small><?php echo esc_html( $papel ); ?></small></span>
					<svg class="mx-perfil__seta" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
				</summary>
				<div class="mx-perfil__menu">
					<a href="#agenda" data-ir="agenda">A minha agenda</a>
					<a href="#consultas" data-ir="consultas">As minhas consultas</a>
					<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Sair</a>
				</div>
			</details>
		</div>
	</header>

	<!-- Barra de ícones (a pílula escura da referência) -->
	<aside class="pm-side mx-rail" aria-label="Menu">
		<nav class="mx-rail__nav">
			<?php
			$mx_menu = array(
				'painel'     => 'Painel',
				'pacientes'  => 'Pacientes',
				'consultas'  => 'Consultas',
				'agenda'     => 'Agenda',
			);
			foreach ( $mx_menu as $k => $rot ) : ?>
				<a class="pm-nav__i<?php echo 'painel' === $k ? ' is-active' : ''; ?>" href="#<?php echo esc_attr( $k ); ?>" data-go="<?php echo esc_attr( $k ); ?>" aria-label="<?php echo esc_attr( $rot ); ?>">
					<?php echo $mx_svg( $k ); ?><span class="mx-dica"><?php echo esc_html( $rot ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<nav class="mx-rail__fim">
			<a class="pm-nav__i" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" aria-label="Sair"><?php echo $mx_svg( 'sair' ); ?><span class="mx-dica">Sair</span></a>
		</nav>
	</aside>

	<main class="pm-main mx-main">

		<?php clinica_render_flash(); ?>


			<!-- Painel
			     Forma da referência (MEDIX): saudação e período, quatro números
			     com ícone, o gráfico dos pacientes com pílulas, retornos e
			     pacientes de hoje; à direita a agenda da semana e a próxima
			     consulta. Só dados reais, com estados vazios por extenso. -->
			<section class="mx-painel pm-view" data-view="painel">

				<div class="mx-ola">
					<div>
						<h1 class="mx-ola__t"><?php echo esc_html( $saudacao . ', ' . $titulo( $nome_med ) . ' ' . $primeiro ); ?></h1>
						<p class="mx-ola__s"><?php echo esc_html( $mx_resumo ); ?></p>
					</div>
					<label class="mx-select">
						<span class="mx-sr">Período dos números e do gráfico</span>
						<select data-gf-ir>
							<?php foreach ( Clinica_Analitica::PERIODOS_MEDICO as $k => $p ) : ?>
								<option value="<?php echo esc_url( $mx_periodo_url( $k, 'painel' ) ); ?>"<?php selected( $k, $dp['periodo'] ); ?>><?php echo esc_html( 'Últimos ' . $p['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
					</label>
				</div>

				<div class="mx-kpis">
					<div class="mx-kpi">
						<span class="mx-kpi__ic mx-kpi__ic--menta"><?php echo $mx_svg( 'feito' ); ?></span>
						<span class="mx-kpi__l">Consultas realizadas</span>
						<strong class="mx-kpi__v"><?php echo esc_html( number_format_i18n( $dp['atual']['realizadas'] ) ); ?></strong>
					</div>
					<div class="mx-kpi">
						<span class="mx-kpi__ic mx-kpi__ic--terra"><?php echo $mx_svg( 'pacientes' ); ?></span>
						<span class="mx-kpi__l">Pacientes atendidos</span>
						<strong class="mx-kpi__v"><?php echo esc_html( number_format_i18n( $dp['atual']['pacientes'] ) ); ?></strong>
					</div>
					<div class="mx-kpi">
						<span class="mx-kpi__ic mx-kpi__ic--areia"><?php echo $mx_svg( 'tendencia' ); ?></span>
						<span class="mx-kpi__l">Taxa de comparência</span>
						<strong class="mx-kpi__v"><?php echo esc_html( Clinica_Grafico::pct( $dp['comparencia'] ) ); ?></strong>
					</div>
					<a class="mx-kpi" href="#agenda" data-ir="agenda">
						<span class="mx-kpi__ic mx-kpi__ic--agua"><?php echo $mx_svg( 'agenda' ); ?></span>
						<span class="mx-kpi__l">Consultas agendadas</span>
						<strong class="mx-kpi__v"><?php echo esc_html( number_format_i18n( count( $prox ) ) ); ?></strong>
					</a>
				</div>

				<section class="mx-cartao mx-grafico" aria-labelledby="mx-g-pac">
					<div class="mx-cartao__cab">
						<h2 class="mx-cartao__t" id="mx-g-pac">Os seus pacientes</h2>
						<div class="gf-filtro mx-pilulas" role="group" aria-label="Séries do gráfico" data-gf-alvo="mx-graf-pac">
							<button type="button" data-so="" aria-pressed="true">Todas</button>
							<button type="button" data-so="0" aria-pressed="false"><i class="gf-chave gf-c--c1"></i>Realizadas</button>
							<button type="button" data-so="1" aria-pressed="false"><i class="gf-chave gf-c--c2"></i>Marcadas</button>
						</div>
					</div>
					<div class="mx-grafico__corpo">
						<div class="mx-stats">
							<div class="mx-stat">
								<strong><?php echo esc_html( number_format_i18n( $dp['atual']['realizadas'] ) ); ?> <span class="mx-delta mx-delta--c1"><?php echo esc_html( $mx_delta( $mx_var( $dp['atual']['realizadas'], $dp['anterior']['realizadas'] ) ) ); ?></span></strong>
								<span>Consultas realizadas</span>
							</div>
							<div class="mx-stat">
								<strong><?php echo esc_html( number_format_i18n( $dp['atual']['marcadas'] ) ); ?> <span class="mx-delta mx-delta--c2"><?php echo esc_html( $mx_delta( $mx_var( $dp['atual']['marcadas'], $dp['anterior']['marcadas'] ) ) ); ?></span></strong>
								<span>Consultas marcadas</span>
							</div>
							<p class="mx-stats__nota"><?php echo esc_html( 'Últimos ' . $dp['label'] . ', face aos ' . $dp['label'] . ' anteriores.' ); ?></p>
						</div>
						<?php
						echo Clinica_Grafico::linhas(
							array(
								'id'      => 'mx-graf-pac',
								'titulo'  => 'Consultas ' . $dp['por_unidade'],
								'rotulos' => $dp['rotulos'],
								'longos'  => $dp['longos'],
								'saltar'  => $dp['saltar'],
								'suave'   => true,
								'legenda' => false,
								'final'   => false,
								'series'  => array(
									array( 'nome' => 'Realizadas', 'valores' => $dp['serie']['realizadas'], 'cor' => 'c1' ),
									array( 'nome' => 'Marcadas', 'valores' => $dp['serie']['marcadas'], 'cor' => 'c2' ),
								),
								'formato' => static fn( $v ) => sprintf( _n( '%d consulta', '%d consultas', (int) $v, 'clinica' ), (int) $v ),
								'eixo'    => static fn( $v ) => number_format_i18n( (int) $v ),
								'vazio'   => 'Sem consultas nos últimos ' . $dp['label'] . '.',
							)
						);
						?>
					</div>
				</section>

				<div class="mx-baixo">
					<section class="mx-cartao" aria-labelledby="mx-ret">
						<div class="mx-cartao__cab">
							<h2 class="mx-cartao__t" id="mx-ret">Retornos previstos</h2>
						</div>
						<?php if ( $mx_retornos ) : ?>
							<ul class="mx-eventos">
								<?php foreach ( array_slice( $mx_retornos, 0, 3 ) as $r ) : ?>
									<li class="mx-evento">
										<span class="mx-evento__t">
											<b><?php echo esc_html( $r['pac_nome'] ); ?></b>
											<small><?php echo esc_html( 'Retorno a ' . date_i18n( 'd/m/Y', strtotime( $r['retorno_em'] ) ) . ( ! empty( $r['retorno_nota'] ) ? ' · ' . $r['retorno_nota'] : '' ) ); ?></small>
										</span>
										<a class="mx-evento__ir" href="<?php echo esc_url( add_query_arg( 'pac', (int) $r['paciente_id'], remove_query_arg( array( 'pac', 'atender', 'estado', 'cl' ) ) ) ); ?>" aria-label="<?php echo esc_attr( 'Abrir ficha de ' . $r['pac_nome'] ); ?>"><?php echo $mx_svg( 'seta' ); ?></a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<div class="mx-vazio">
								<span class="mx-vazio__ic"><?php echo $mx_svg( 'retorno' ); ?></span>
								<p><b>Sem retornos previstos</b>Ao concluir uma consulta em Atender, pode marcar quando o paciente deve voltar.</p>
							</div>
						<?php endif; ?>
					</section>

					<section class="mx-cartao" aria-labelledby="mx-hoje">
						<div class="mx-cartao__cab">
							<h2 class="mx-cartao__t" id="mx-hoje"><?php echo $mx_lista_hoje ? 'Pacientes de hoje' : 'Próximos pacientes'; ?></h2>
							<a class="mx-cartao__ligacao" href="#consultas" data-ir="consultas">Ver todos</a>
						</div>
						<?php if ( $mx_pacientes ) : ?>
							<ul class="mx-pessoas">
								<?php foreach ( $mx_pacientes as $c ) : ?>
									<li class="mx-pessoa">
										<span class="mx-pessoa__av" aria-hidden="true"><?php echo esc_html( $mx_iniciais( $c['pac_nome'] ) ); ?></span>
										<span class="mx-pessoa__t">
											<b><?php echo esc_html( $c['pac_nome'] ); ?></b>
											<small><?php echo esc_html( ( $labels[ $c['status'] ] ?? $c['status'] ) . ( ! empty( $c['checkin_em'] ) && ! in_array( $c['status'], $terminado, true ) ? ' · na sala de espera' : '' ) ); ?></small>
										</span>
										<time class="mx-pessoa__h" datetime="<?php echo esc_attr( $c['inicio'] ); ?>"><?php echo esc_html( $mx_lista_hoje ? date_i18n( 'H:i', strtotime( $c['inicio'] ) ) : date_i18n( 'd/m · H:i', strtotime( $c['inicio'] ) ) ); ?></time>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<div class="mx-vazio">
								<span class="mx-vazio__ic"><?php echo $mx_svg( 'pacientes' ); ?></span>
								<p><b>Sem pacientes agendados</b>As consultas marcadas pela receção aparecem aqui.</p>
							</div>
						<?php endif; ?>
					</section>
				</div>

				<section class="mx-cartao mx-agenda" aria-labelledby="mx-ag">
					<div class="mx-cartao__cab">
						<h2 class="mx-cartao__t" id="mx-ag">Agenda</h2>
						<a class="mx-mais" href="#agenda" data-ir="agenda" aria-label="Abrir a agenda completa"><?php echo $mx_svg( 'mais' ); ?></a>
					</div>
					<div class="mx-cal">
						<div class="mx-cal__nav">
							<button type="button" class="mx-cal__seta" data-cal="-1" aria-label="Semana anterior"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
							<span class="mx-cal__mes" aria-live="polite"><?php echo esc_html( $mx_semanas[1]['mes'] ); ?></span>
							<button type="button" class="mx-cal__seta" data-cal="1" aria-label="Semana seguinte"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
						</div>
						<?php foreach ( $mx_semanas as $sem ) : ?>
							<div class="mx-cal__semana" data-mes="<?php echo esc_attr( $sem['mes'] ); ?>"<?php echo $sem['atual'] ? '' : ' hidden'; ?>>
								<?php foreach ( $sem['dias'] as $di => $ts ) :
									$ymd  = date( 'Y-m-d', $ts );
									$n    = count( $mx_por_dia[ $ymd ] ?? array() );
									$hoje = $ymd === $hoje_ymd;
									?>
									<button type="button" class="mx-cal__dia<?php echo $hoje ? ' is-hoje is-sel' : ''; ?><?php echo $n ? ' tem' : ''; ?>" data-dia="<?php echo esc_attr( $ymd ); ?>" data-rotulo="<?php echo esc_attr( ucfirst( date_i18n( 'l, j \d\e F', $ts ) ) ); ?>" aria-pressed="<?php echo $hoje ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( date_i18n( 'j \d\e F', $ts ) . ( $n ? ', ' . sprintf( _n( '%d consulta', '%d consultas', $n, 'clinica' ), $n ) : '' ) ); ?>">
										<span class="mx-cal__sem"><?php echo esc_html( $mx_sem_nomes[ $di ] ); ?></span>
										<span class="mx-cal__num"><?php echo esc_html( date( 'd', $ts ) ); ?></span>
									</button>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
						<div class="mx-cal__resumo">
							<p class="mx-cal__data"><?php echo esc_html( ucfirst( date_i18n( 'l, j \d\e F', $agora ) ) ); ?></p>
							<?php
							foreach ( $mx_semanas as $sem ) :
								foreach ( $sem['dias'] as $ts ) :
									$ymd = date( 'Y-m-d', $ts );
									if ( empty( $mx_por_dia[ $ymd ] ) ) {
										continue;
									}
									?>
									<ul class="mx-cal__lista" data-resumo="<?php echo esc_attr( $ymd ); ?>"<?php echo $ymd === $hoje_ymd ? '' : ' hidden'; ?>>
										<?php foreach ( array_slice( $mx_por_dia[ $ymd ], 0, 3 ) as $c ) : ?>
											<li><time><?php echo esc_html( date_i18n( 'H:i', strtotime( $c['inicio'] ) ) ); ?></time><?php echo esc_html( $c['pac_nome'] ); ?></li>
										<?php endforeach; ?>
										<?php if ( count( $mx_por_dia[ $ymd ] ) > 3 ) : ?>
											<li class="mx-cal__mais"><?php echo esc_html( sprintf( 'e mais %d', count( $mx_por_dia[ $ymd ] ) - 3 ) ); ?></li>
										<?php endif; ?>
									</ul>
									<?php
								endforeach;
							endforeach;
							?>
							<p class="mx-cal__vazio"<?php echo empty( $mx_por_dia[ $hoje_ymd ] ) ? '' : ' hidden'; ?>>Sem consultas neste dia.</p>
						</div>
					</div>
				</section>

				<section class="mx-cartao mx-proxima" aria-labelledby="mx-prox">
					<div class="mx-cartao__cab">
						<h2 class="mx-cartao__t" id="mx-prox">Próxima consulta</h2>
						<a class="mx-seta" href="#agenda" data-ir="agenda" aria-label="Abrir agenda"><?php echo $mx_svg( 'seta' ); ?></a>
					</div>
					<?php if ( $proxima ) :
						$ficha_url = add_query_arg( 'pac', (int) $proxima['paciente_id'], remove_query_arg( array( 'pac', 'atender', 'estado', 'cl' ) ) );
						$alergias  = trim( (string) ( $mx_prox_proc['alergias'] ?? '' ) );
						?>
						<p class="mx-proxima__nome"><?php echo esc_html( $proxima['pac_nome'] ); ?></p>
						<span class="mx-chip"><?php echo $mx_svg( 'coracao' ); ?><?php echo esc_html( $proxima['esp_nome'] ); ?></span>
						<div class="mx-proxima__visual" aria-hidden="true">
							<svg class="mx-proxima__traco" viewBox="0 0 320 200" preserveAspectRatio="none"><path d="M-10 150 C 60 40, 150 30, 190 90 S 290 190, 330 60"/><path d="M-10 175 C 80 80, 170 70, 210 120 S 300 200, 330 110"/></svg>
							<span class="mx-proxima__av"><?php echo esc_html( $mx_iniciais( $proxima['pac_nome'] ) ); ?></span>
						</div>
						<p class="mx-proxima__sobre-t">Sobre o paciente</p>
						<p class="mx-proxima__sobre">
							<?php
							$partes = array();
							if ( null !== $mx_prox_idade ) {
								$partes[] = (int) $mx_prox_idade . ' anos';
							}
							if ( ! empty( $mx_prox_proc['numero'] ) ) {
								$partes[] = 'processo ' . $mx_prox_proc['numero'];
							}
							$frase = $partes ? ucfirst( implode( ', ', $partes ) ) . '. ' : '';
							$frase .= $alergias ? 'Alergias: ' . $alergias . '.' : 'Sem alergias registadas no processo.';
							echo esc_html( $frase );
							?>
							<a href="<?php echo esc_url( $ficha_url ); ?>">Ver ficha</a>
						</p>
						<div class="mx-proxima__quando">
							<span><?php echo $mx_svg( 'agenda' ); ?><?php echo esc_html( date_i18n( 'j M, H:i', strtotime( $proxima['inicio'] ) ) ); ?></span>
							<span><?php echo $mx_svg( 'relogio' ); ?><?php echo esc_html( (int) $proxima['duracao_min'] . ' minutos' ); ?></span>
						</div>
						<a class="mx-botao" href="<?php echo esc_url( $ficha_url ); ?>">Abrir ficha do paciente</a>
					<?php else : ?>
						<p class="mx-proxima__nome">Sem consultas agendadas</p>
						<span class="mx-chip"><?php echo $mx_svg( 'coracao' ); ?><?php echo esc_html( $papel ); ?></span>
						<div class="mx-proxima__visual mx-proxima__visual--foto" aria-hidden="true">
							<svg class="mx-proxima__traco" viewBox="0 0 320 200" preserveAspectRatio="none"><path d="M-10 150 C 60 40, 150 30, 190 90 S 290 190, 330 60"/><path d="M-10 175 C 80 80, 170 70, 210 120 S 300 200, 330 110"/></svg>
							<img src="<?php echo esc_url( $mx_foto ); ?>" alt="">
						</div>
						<p class="mx-proxima__sobre">Quando a receção marcar uma consulta consigo, o paciente, as alergias registadas e a hora aparecem aqui.</p>
						<a class="mx-botao" href="#agenda" data-ir="agenda">Abrir agenda</a>
					<?php endif; ?>
				</section>

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

		<!-- Consultas
		     Lista no tema do painel: data num mosaico de calendário, paciente
		     com iniciais, chegada, estado em pastilha e a ação do fluxo.
		     O médico só atende quem a receção já deu entrada (check-in). -->
		<section id="consultas" class="mx-vista pm-view" data-view="consultas" hidden>
			<div class="mx-vista__cab">
				<div>
					<h1 class="mx-vista__t">Consultas</h1>
					<p class="mx-vista__s"><?php echo esc_html( sprintf( _n( '%d consulta no seu histórico, da mais recente para a mais antiga.', '%d consultas no seu histórico, da mais recente para a mais antiga.', (int) array_sum( $kpis ), 'clinica' ), (int) array_sum( $kpis ) ) ); ?></p>
				</div>
				<label class="mx-procura">
					<?php echo $mx_svg( 'procurar' ); ?>
					<span class="mx-sr">Procurar paciente</span>
					<input type="search" id="pm-search" placeholder="Procurar paciente">
				</label>
			</div>

			<div class="mx-cartao mx-tabela-cartao">
				<nav class="mx-abas" aria-label="Filtrar por estado">
					<?php foreach ( $abas as $val => $info ) :
						$url   = $val ? add_query_arg( 'estado', $val, remove_query_arg( array( 'cl', 'estado' ) ) ) : remove_query_arg( array( 'cl', 'estado' ) );
						$ativa = ( $estado_filtro === $val );
						?>
						<a class="mx-aba<?php echo $ativa ? ' is-ativa' : ''; ?>" href="<?php echo esc_url( $url ); ?>#consultas"<?php echo $ativa ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $info[0] ); ?><span><?php echo (int) $info[1]; ?></span></a>
					<?php endforeach; ?>
				</nav>

				<?php if ( $consultas ) : ?>
					<div class="mx-tabela" id="pm-list" role="table" aria-label="Consultas">
						<div class="mx-tabela__cab" role="row">
							<span role="columnheader">Data</span>
							<span role="columnheader">Paciente</span>
							<span role="columnheader">Chegada</span>
							<span role="columnheader">Estado</span>
							<span role="columnheader" class="r">Ação</span>
						</div>
						<?php foreach ( $consultas as $c ) :
							$terminada = in_array( $c['status'], array( 'realizada', 'cancelada', 'faltou' ), true );
							$ts_c      = strtotime( $c['inicio'] );
							?>
							<div class="mx-tabela__linha pm-crow<?php echo $terminada ? ' is-fechada' : ''; ?>" role="row" data-nome="<?php echo esc_attr( mb_strtolower( $c['pac_nome'] ) ); ?>">
								<span class="mx-data" role="cell">
									<span class="mx-data__dia"><b><?php echo esc_html( date_i18n( 'd', $ts_c ) ); ?></b><small><?php echo esc_html( date_i18n( 'M', $ts_c ) ); ?></small></span>
									<span class="mx-data__hora"><b><?php echo esc_html( date_i18n( 'H:i', $ts_c ) ); ?></b><small><?php echo (int) $c['duracao_min']; ?> min</small></span>
								</span>
								<span class="mx-pac" role="cell"<?php echo ! empty( $c['observacoes'] ) ? ' title="' . esc_attr( $c['observacoes'] ) . '"' : ''; ?>>
									<span class="mx-pac__av" aria-hidden="true"><?php echo esc_html( $mx_iniciais( $c['pac_nome'] ) ); ?></span>
									<span class="mx-pac__t"><b><?php echo esc_html( $c['pac_nome'] ); ?></b><small><?php echo esc_html( $c['esp_nome'] ); ?></small></span>
								</span>
								<span class="mx-chegada" role="cell">
									<?php if ( ! empty( $c['checkin_em'] ) ) : ?>
										<?php echo $mx_svg( 'relogio' ); ?><?php echo esc_html( date_i18n( 'H:i', strtotime( $c['checkin_em'] ) ) ); ?>
									<?php else : ?>
										<span class="mx-mudo">—</span>
									<?php endif; ?>
								</span>
								<span role="cell"><span class="pm-st pm-st--<?php echo esc_attr( $c['status'] ); ?>"><?php echo esc_html( $labels[ $c['status'] ] ?? $c['status'] ); ?></span></span>
								<span class="mx-acao" role="cell">
									<?php if ( $terminada ) : ?>
										<a class="mx-acao__ficha" href="<?php echo esc_url( add_query_arg( 'pac', (int) $c['paciente_id'], remove_query_arg( array( 'pac', 'atender', 'estado', 'cl' ) ) ) ); ?>">Ver ficha</a>
									<?php elseif ( empty( $c['checkin_em'] ) ) : ?>
										<span class="mx-acao__espera">Aguarda receção</span>
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
					<div class="mx-vazio mx-vazio--meio" id="pm-noresult" hidden>
						<span class="mx-vazio__ic"><?php echo $mx_svg( 'procurar' ); ?></span>
						<p><b>Nenhum paciente encontrado</b>Experimente outro nome ou limpe a pesquisa.</p>
					</div>
				<?php else : ?>
					<div class="mx-vazio mx-vazio--meio">
						<span class="mx-vazio__ic"><?php echo $mx_svg( 'consultas' ); ?></span>
						<p><b>Sem consultas neste filtro</b>Escolha outro estado acima para ver as restantes.</p>
					</div>
				<?php endif; ?>
			</div>
		</section>

		<!-- Agenda
		     Calendário do mês à esquerda (dias com consultas marcados) e as
		     próximas consultas por dia à direita, numa linha do tempo. -->
		<?php
		$ag_meses = array();
		for ( $mi = 0; $mi < 3; $mi++ ) {
			$ag_meses[] = strtotime( date( 'Y-m-01', strtotime( "first day of +{$mi} month", $agora ) ) );
		}
		?>
		<section class="mx-vista pm-view" data-view="agenda" hidden>
			<div class="mx-vista__cab">
				<div>
					<h1 class="mx-vista__t">Agenda</h1>
					<p class="mx-vista__s"><?php echo esc_html( $prox ? sprintf( _n( '%d consulta por atender.', '%d consultas por atender.', count( $prox ), 'clinica' ), count( $prox ) ) : 'Sem consultas por atender.' ); ?></p>
				</div>
			</div>

			<div class="mx-agenda-grelha">
				<section class="mx-cartao mx-mes" aria-label="Calendário">
					<div class="mx-cal__nav">
						<button type="button" class="mx-cal__seta" data-mes="-1" aria-label="Mês anterior"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button>
						<span class="mx-mes__t" aria-live="polite"><?php echo esc_html( ucfirst( date_i18n( 'F Y', $ag_meses[0] ) ) ); ?></span>
						<button type="button" class="mx-cal__seta" data-mes="1" aria-label="Mês seguinte"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button>
					</div>
					<?php foreach ( $ag_meses as $mi => $m_ts ) :
						$m_dias = (int) date( 't', $m_ts );
						$m_off  = (int) date( 'N', $m_ts ) - 1;
						?>
						<div class="mx-mes__grelha" data-titulo="<?php echo esc_attr( ucfirst( date_i18n( 'F Y', $m_ts ) ) ); ?>"<?php echo $mi ? ' hidden' : ''; ?>>
							<?php foreach ( $mx_sem_nomes as $sn ) : ?><span class="mx-mes__sem"><?php echo esc_html( mb_substr( $sn, 0, 1 ) ); ?></span><?php endforeach; ?>
							<?php for ( $b = 0; $b < $m_off; $b++ ) : ?><span></span><?php endfor; ?>
							<?php for ( $dd = 1; $dd <= $m_dias; $dd++ ) :
								$ymd = date( 'Y-m-', $m_ts ) . str_pad( (string) $dd, 2, '0', STR_PAD_LEFT );
								$tem = ! empty( $agenda_por_dia[ $ymd ] );
								$cls = 'mx-mes__dia' . ( $ymd === $hoje_ymd ? ' is-hoje' : '' ) . ( $tem ? ' tem' : '' ) . ( $ymd < $hoje_ymd ? ' is-passado' : '' );
								if ( $tem ) :
									?>
									<a class="<?php echo esc_attr( $cls ); ?>" href="#ag-<?php echo esc_attr( $ymd ); ?>" aria-label="<?php echo esc_attr( date_i18n( 'j \d\e F', strtotime( $ymd ) ) . ', ' . sprintf( _n( '%d consulta', '%d consultas', count( $agenda_por_dia[ $ymd ] ), 'clinica' ), count( $agenda_por_dia[ $ymd ] ) ) ); ?>"><?php echo (int) $dd; ?></a>
								<?php else : ?>
									<span class="<?php echo esc_attr( $cls ); ?>"><?php echo (int) $dd; ?></span>
								<?php endif; ?>
							<?php endfor; ?>
						</div>
					<?php endforeach; ?>
					<ul class="mx-mes__legenda">
						<li><i class="mx-mes__ponto"></i>Dia com consultas</li>
						<li><i class="mx-mes__anel"></i>Hoje</li>
					</ul>
				</section>

				<section class="mx-cartao mx-linhatempo" aria-label="Próximas consultas">
					<?php if ( $agenda_por_dia ) : ?>
						<?php foreach ( $agenda_por_dia as $dia => $items ) :
							$ts_d    = strtotime( $dia );
							$is_hoje = ( $dia === $hoje_ymd );
							?>
							<div class="mx-dia" id="ag-<?php echo esc_attr( $dia ); ?>">
								<div class="mx-dia__cab">
									<span class="mx-dia__data"><b><?php echo esc_html( date_i18n( 'd', $ts_d ) ); ?></b><small><?php echo esc_html( date_i18n( 'M', $ts_d ) ); ?></small></span>
									<span class="mx-dia__t"><b><?php echo esc_html( ucfirst( date_i18n( 'l', $ts_d ) ) ); ?><?php echo $is_hoje ? ' · Hoje' : ''; ?></b><small><?php echo esc_html( sprintf( _n( '%d consulta', '%d consultas', count( $items ), 'clinica' ), count( $items ) ) ); ?></small></span>
								</div>
								<ol class="mx-tempo">
									<?php foreach ( $items as $f ) : ?>
										<li class="mx-tempo__i">
											<time class="mx-tempo__h"><?php echo esc_html( $fmt_hora( $f['inicio'] ) ); ?></time>
											<div class="mx-tempo__c">
												<span class="mx-pac__av" aria-hidden="true"><?php echo esc_html( $mx_iniciais( $f['pac_nome'] ) ); ?></span>
												<span class="mx-pac__t"><b><?php echo esc_html( $f['pac_nome'] ); ?></b><small><?php echo esc_html( $f['esp_nome'] . ' · ' . (int) $f['duracao_min'] . ' min · ' . ( ! empty( $f['checkin_em'] ) ? 'chegou às ' . $fmt_hora( $f['checkin_em'] ) : 'a aguardar chegada' ) ); ?></small></span>
												<span class="pm-st pm-st--<?php echo esc_attr( $f['status'] ); ?>"><?php echo esc_html( $labels[ $f['status'] ] ?? $f['status'] ); ?></span>
											</div>
										</li>
									<?php endforeach; ?>
								</ol>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<div class="mx-vazio mx-vazio--meio">
							<span class="mx-vazio__ic"><?php echo $mx_svg( 'agenda' ); ?></span>
							<p><b>Sem consultas agendadas</b>Quando a receção marcar consultas consigo, aparecem aqui por dia e por hora.</p>
						</div>
					<?php endif; ?>
				</section>
			</div>
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
	var perfil = document.querySelector( '.mx-perfil' );
	if ( perfil ) {
		document.addEventListener( 'click', function ( e ) {
			if ( perfil.open && ( ! perfil.contains( e.target ) || e.target.closest( '.mx-perfil__menu a' ) ) ) { perfil.open = false; }
		} );
	}
	// Atalhos dentro das vistas (ex.: "Ver agenda" na faixa Hoje).
	document.querySelectorAll( '[data-ir]' ).forEach( function ( a ) {
		a.addEventListener( 'click', function ( e ) { e.preventDefault(); show( a.dataset.ir ); } );
	} );
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

	// Agenda da semana (painel): as setas mudam de semana e um dia mostra as
	// suas consultas por baixo. Tudo já vem do servidor; aqui só se mostra.
	( function () {
		var cal = document.querySelector( '.mx-cal' );
		if ( ! cal ) { return; }
		var semanas = cal.querySelectorAll( '.mx-cal__semana' ), mes = cal.querySelector( '.mx-cal__mes' );
		var setas = cal.querySelectorAll( '[data-cal]' ), i = 0;
		semanas.forEach( function ( s, k ) { if ( ! s.hidden ) { i = k; } } );
		function ir( n ) {
			i = Math.max( 0, Math.min( semanas.length - 1, n ) );
			semanas.forEach( function ( s, k ) { s.hidden = k !== i; } );
			mes.textContent = semanas[ i ].dataset.mes;
			setas[0].disabled = 0 === i;
			setas[1].disabled = semanas.length - 1 === i;
		}
		setas.forEach( function ( b ) {
			b.addEventListener( 'click', function () { ir( i + parseInt( b.dataset.cal, 10 ) ); } );
		} );
		cal.addEventListener( 'click', function ( e ) {
			var d = e.target.closest( '.mx-cal__dia' );
			if ( ! d ) { return; }
			cal.querySelectorAll( '.mx-cal__dia' ).forEach( function ( o ) {
				o.classList.toggle( 'is-sel', o === d );
				o.setAttribute( 'aria-pressed', o === d ? 'true' : 'false' );
			} );
			var achou = false;
			cal.querySelectorAll( '[data-resumo]' ).forEach( function ( l ) {
				l.hidden = l.dataset.resumo !== d.dataset.dia;
				if ( ! l.hidden ) { achou = true; }
			} );
			cal.querySelector( '.mx-cal__vazio' ).hidden = achou;
			cal.querySelector( '.mx-cal__data' ).textContent = d.dataset.rotulo;
		} );
		ir( i );
	} )();

	// Agenda: as setas mudam o mês do calendário (três meses já desenhados).
	( function () {
		var caixa = document.querySelector( '.mx-mes' );
		if ( ! caixa ) { return; }
		var meses = caixa.querySelectorAll( '.mx-mes__grelha' ), titulo = caixa.querySelector( '.mx-mes__t' );
		var setas = caixa.querySelectorAll( '[data-mes]' ), i = 0;
		function ir( n ) {
			i = Math.max( 0, Math.min( meses.length - 1, n ) );
			meses.forEach( function ( m, k ) { m.hidden = k !== i; } );
			titulo.textContent = meses[ i ].dataset.titulo;
			setas[0].disabled = 0 === i;
			setas[1].disabled = meses.length - 1 === i;
		}
		setas.forEach( function ( b ) { b.addEventListener( 'click', function () { ir( i + parseInt( b.dataset.mes, 10 ) ); } ); } );
		ir( 0 );
	} )();

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
