<?php
/**
 * Especialidades · Faixa final (CTA) para marcação, com imagem ao lado.
 * A imagem é assets/img/cta.png — substitui esse ficheiro manualmente.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$url_cliente = home_url( '/marcar/' );
?>
<section class="esp-cta">
	<div class="esp-cta__card">

		<div class="esp-cta__content">
			<h2 class="esp-cta__title">Não sabe qual a especialidade certa?</h2>
			<p class="esp-cta__lead">Marque uma consulta e a nossa equipa encaminha-o para o especialista indicado.</p>
			<a class="esp-btn esp-btn--white" href="<?php echo esc_url( $url_cliente ); ?>">Marque a sua consulta</a>
		</div>

		<div class="esp-cta__media">
			<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/cta1.png' ); ?>" alt="Equipa da Clínica Vida" loading="lazy">
		</div>

	</div>
</section>
