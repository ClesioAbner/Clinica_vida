<?php
/**
 * Homepage · Hero — título, chamada à ação, fotografia e estatísticas.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$estatisticas = Clinica_Homepage::estatisticas();
?>
<section id="inicio" class="hp-hero" data-screen="Hero">
	<div class="hp-container hp-hero__inner">

		<div class="hp-hero__text">
			<div class="hp-eyebrow">Clínica Vida</div>
			<h1 class="hp-hero__title">Cuidados de saúde de excelência, perto de si</h1>
			<p class="hp-hero__lead">Reunimos uma equipa médica experiente, tecnologia de diagnóstico avançada e um atendimento centrado tudo num só lugar.</p>
			<div class="hp-hero__actions">
				<a class="hp-btn hp-btn--primary hp-btn--lg" href="<?php echo esc_url( home_url( '/marcar/' ) ); ?>">Marcar Consulta</a>
				<a class="hp-btn hp-btn--outline hp-btn--lg" href="<?php echo esc_url( home_url( '/especialidades/' ) ); ?>">Ver Especialidades</a>
			</div>
		</div>

		<div class="hp-hero__media">
			<div class="hp-hero__photo">
				<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/medica3.png' ); ?>"
				     alt="Médica da Clínica Vida" loading="eager">
			</div>
		</div>

	</div>

	<div class="hp-container hp-stats">
		<?php foreach ( $estatisticas as $e ) : ?>
			<div class="hp-stat">
				<div class="hp-stat__n"><?php echo esc_html( $e['numero'] ); ?></div>
				<div class="hp-stat__l"><?php echo esc_html( $e['rotulo'] ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
