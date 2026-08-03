<?php
/**
 * Homepage · Testemunhos de pacientes.
 * Dados: Clinica_Homepage::testemunhos().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$testemunhos = Clinica_Homepage::testemunhos();

// Uma estrela preenchida (usada 5x). Marcação confiável, definida aqui.
$estrela = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
?>
<section class="hp-testemunhos" data-screen="Testemunhos">
	<div class="hp-container">

		<div class="hp-head">
			<div class="hp-eyebrow">Testemunhos</div>
			<h2 class="hp-head__title">O que dizem os nossos pacientes</h2>
		</div>

		<div class="hp-test-grid">
			<?php foreach ( $testemunhos as $t ) : ?>
				<figure class="hp-test">
					<span class="hp-test__quote" aria-hidden="true">&rdquo;</span>
					<blockquote class="hp-test__text"><?php echo esc_html( $t['texto'] ); ?></blockquote>
					<figcaption class="hp-test__author">
						<span class="hp-test__avatar"><?php echo esc_html( Clinica_Homepage::iniciais( $t['nome'] ) ); ?></span>
						<div class="hp-test__who">
							<div class="hp-test__name"><?php echo esc_html( $t['nome'] ); ?></div>
							<div class="hp-test__detail"><?php echo esc_html( $t['detalhe'] ); ?></div>
						</div>
						<span class="hp-test__stars" aria-label="5 estrelas">
							<?php echo str_repeat( $estrela, 5 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</span>
					</figcaption>
				</figure>
			<?php endforeach; ?>
		</div>

	</div>
</section>
