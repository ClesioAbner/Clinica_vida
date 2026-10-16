<?php
/**
 * Template Name: Clínica — Gestão de Agenda
 *
 * Gerir horários semanais, folgas e feriados. Backend: inc/agenda.php.
 * Acesso: clinica_gerir_agenda (secretária/admin).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() || ! current_user_can( 'clinica_gerir_agenda' ) ) {
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

clinica_controller_agenda();

get_header();

global $wpdb;
$meds = $wpdb->get_results(
	"SELECT md.id, u.ID AS user_id, u.display_name AS nome, e.nome AS esp
	 FROM " . Clinica_Repo::T_MEDICOS . " md
	 JOIN {$wpdb->users} u ON md.user_id = u.ID
	 JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
	 WHERE md.ativo = 1 ORDER BY u.display_name",
	ARRAY_A
) ?: array();

$sel = Clinica_Input::inteiro( $_GET, 'medico' );
if ( ! $sel && $meds ) {
	$sel = (int) $meds[0]['id'];
}
$sel_med = null;
foreach ( $meds as $m ) {
	if ( (int) $m['id'] === $sel ) {
		$sel_med = $m;
	}
}

$dias = array( 1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo' );

$horarios = $sel ? Clinica_Agenda::horarios_medico( $sel ) : array();
$por_dia  = array_fill( 1, 7, array() );
foreach ( $horarios as $h ) {
	$por_dia[ (int) $h['dia_semana'] ][] = $h;
}
$bloqs    = array_filter( Clinica_Agenda::bloqueios_todos(), static function ( $b ) use ( $sel ) { return (int) $b['medico_id'] === $sel; } );
$feriados = Clinica_Agenda::feriados();
$hhmm     = static function ( $t ) { return substr( (string) $t, 0, 5 ); };
$fdata    = static function ( $d ) { return date_i18n( 'd/m/Y', strtotime( $d ) ); };
$base_url = remove_query_arg( array( 'cl' ) );
?>
<div class="hp">
	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo" class="ag">
		<div class="ag-container">

			<header class="ag-hero">
				<div class="ag-hero__text">
					<a class="ag-back" href="<?php echo esc_url( home_url( '/gestao/' ) ); ?>">&larr; Voltar à Gestão</a>
					<h1>Gestão de Agenda</h1>
					<p>Defina os horários de cada médico, as folgas e os feriados da clínica. As horas livres que o paciente vê saem daqui.</p>
				</div>
				<div class="ag-hero__art" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
				</div>
			</header>

			<?php clinica_render_flash(); ?>

			<!-- Seletor de médico (com foto) -->
			<section class="ag-docs">
				<?php foreach ( $meds as $m ) :
					$ativo = ( (int) $m['id'] === $sel );
					$foto  = function_exists( 'clinica_avatar_url' ) ? clinica_avatar_url( (int) $m['user_id'], 96 ) : get_avatar_url( (int) $m['user_id'] );
				?>
					<a class="ag-doc <?php echo $ativo ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'medico', (int) $m['id'], $base_url ) ); ?>">
						<span class="ag-doc__ph"><img src="<?php echo esc_url( $foto ); ?>" alt=""></span>
						<span class="ag-doc__info">
							<span class="ag-doc__name"><?php echo esc_html( clinica_nome_medico( $m['nome'] ) ); ?></span>
							<span class="ag-doc__esp"><?php echo esc_html( $m['esp'] ); ?></span>
						</span>
					</a>
				<?php endforeach; ?>
				<?php if ( ! $meds ) : ?><p class="ag-empty">Ainda não há médicos ativos. Crie-os na Gestão.</p><?php endif; ?>
			</section>

			<?php if ( $sel_med ) : ?>
			<div class="ag-grid">

				<!-- Horário semanal -->
				<section class="ag-card ag-card--wide">
					<div class="ag-card__head">
						<h2>Horário semanal de <?php echo esc_html( clinica_nome_medico( $sel_med['nome'] ) ); ?></h2>
					</div>

					<form method="post" class="ag-addrow">
						<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
						<input type="hidden" name="ag_action" value="hor_add">
						<input type="hidden" name="medico_id" value="<?php echo (int) $sel; ?>">
						<select name="dia_semana" required>
							<?php foreach ( $dias as $n => $d ) : ?><option value="<?php echo (int) $n; ?>"><?php echo esc_html( $d ); ?></option><?php endforeach; ?>
						</select>
						<input type="time" name="hora_inicio" value="08:00" required>
						<span class="ag-sep">→</span>
						<input type="time" name="hora_fim" value="17:00" required>
						<button class="ag-btn ag-btn--primary" type="submit">Adicionar</button>
					</form>

					<div class="ag-week">
						<?php foreach ( $dias as $n => $d ) : ?>
							<div class="ag-day <?php echo empty( $por_dia[ $n ] ) ? 'is-off' : ''; ?>">
								<div class="ag-day__name"><?php echo esc_html( $d ); ?></div>
								<div class="ag-day__slots">
									<?php if ( empty( $por_dia[ $n ] ) ) : ?>
										<span class="ag-day__off">Folga</span>
									<?php else : ?>
										<?php foreach ( $por_dia[ $n ] as $h ) : ?>
											<span class="ag-chip">
												<?php echo esc_html( $hhmm( $h['hora_inicio'] ) . '–' . $hhmm( $h['hora_fim'] ) ); ?>
												<form method="post" onsubmit="return confirm('Remover este horário?');">
													<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
													<input type="hidden" name="ag_action" value="hor_del">
													<input type="hidden" name="id" value="<?php echo (int) $h['id']; ?>">
													<button type="submit" aria-label="Remover">&times;</button>
												</form>
											</span>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</section>

				<!-- Folgas / bloqueios -->
				<section class="ag-card">
					<div class="ag-card__head"><h2>Folgas / ausências</h2></div>
					<form method="post" class="ag-form">
						<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
						<input type="hidden" name="ag_action" value="blq_add">
						<input type="hidden" name="medico_id" value="<?php echo (int) $sel; ?>">
						<div class="ag-form__row">
							<label>De <input type="date" name="data_inicio" required></label>
							<label>Até <input type="date" name="data_fim" required></label>
						</div>
						<input type="text" name="motivo" placeholder="Motivo (ex.: férias)" maxlength="120">
						<button class="ag-btn ag-btn--primary" type="submit">Adicionar folga</button>
					</form>
					<ul class="ag-list">
						<?php foreach ( $bloqs as $b ) : ?>
							<li>
								<span><strong><?php echo esc_html( $fdata( $b['data_inicio'] ) ); ?></strong> → <strong><?php echo esc_html( $fdata( $b['data_fim'] ) ); ?></strong><?php echo $b['motivo'] ? ' · ' . esc_html( $b['motivo'] ) : ''; ?></span>
								<form method="post">
									<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
									<input type="hidden" name="ag_action" value="blq_del">
									<input type="hidden" name="id" value="<?php echo (int) $b['id']; ?>">
									<button class="ag-del" type="submit" aria-label="Remover">&times;</button>
								</form>
							</li>
						<?php endforeach; ?>
						<?php if ( ! $bloqs ) : ?><li class="ag-empty">Sem folgas registadas.</li><?php endif; ?>
					</ul>
				</section>

				<!-- Feriados (global) -->
				<section class="ag-card">
					<div class="ag-card__head"><h2>Feriados da clínica</h2></div>
					<form method="post" class="ag-form">
						<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
						<input type="hidden" name="ag_action" value="fer_add">
						<div class="ag-form__row">
							<label>Data <input type="date" name="data" required></label>
						</div>
						<input type="text" name="descricao" placeholder="Descrição (ex.: Dia da Independência)" maxlength="120">
						<button class="ag-btn ag-btn--primary" type="submit">Adicionar feriado</button>
					</form>
					<ul class="ag-list">
						<?php foreach ( $feriados as $f ) : ?>
							<li>
								<span><strong><?php echo esc_html( $fdata( $f['data'] ) ); ?></strong><?php echo $f['descricao'] ? ' · ' . esc_html( $f['descricao'] ) : ''; ?></span>
								<form method="post">
									<?php wp_nonce_field( 'ag_manage', 'ag_nonce' ); ?>
									<input type="hidden" name="ag_action" value="fer_del">
									<input type="hidden" name="id" value="<?php echo (int) $f['id']; ?>">
									<button class="ag-del" type="submit" aria-label="Remover">&times;</button>
								</form>
							</li>
						<?php endforeach; ?>
						<?php if ( ! $feriados ) : ?><li class="ag-empty">Sem feriados registados.</li><?php endif; ?>
					</ul>
				</section>

			</div>
			<?php endif; ?>

		</div>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>
</div>
<?php
get_footer();
