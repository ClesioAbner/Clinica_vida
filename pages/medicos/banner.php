<?php
/**
 * Médicos · Hero — texto à esquerda, fotografia à direita (o médico do CTA).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;
?>

<section class="med-hero" data-screen="MedHero">
	<div class="med-hero__inner">

		<div class="med-hero__text">
			<div 
				class="med-eyebrow">Equipa do todos os nossos médicos
			</div>
			<h1 
				class="med-hero__title">Médicos que cuidam de si como pessoa, não como um número
			</h1>
			<p 
				class="med-hero__lead">Reunimos especialistas experientes de várias áreas, para  ouvir com tempo, explicar com clareza e decidir em conjunto consigo.
			</p>

			<a class="med-hero__anchor" href="#equipa">Conhecer a equipa 
				<svg width="18" 
						height="18" 
						viewBox="0 0 24 24" fill="none" stroke="currentColor" 
						stroke-width="2" stroke-linecap="round"
						stroke-linejoin="round">
					<line 
						x1="12" y1="5" x2="12" y2="19">
					</line>
					<polyline
						points="19 12 12 19 5 12">
					</polyline>
			  </svg>
			</a>
		</div>

		<div class="med-hero__media">
			<div class="med-hero__photo">
				<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/cta.png' ); ?>" alt="Médico da Clínica Vida" loading="eager">
			</div>
		</div>

	</div>
</section>
