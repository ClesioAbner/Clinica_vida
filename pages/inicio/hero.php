<?php
/**
 * Início · Hero — fotografia de ponta a ponta numa moldura arredondada, título
 * em serifa sobre um véu escuro, CTA branco e um cartão de apresentação.
 *
 * No lugar das "4.9 estrelas / 1597 avaliações" da referência mostramos só
 * factos verificáveis: morada e horário (fonte única Clinica_Homepage::contacto).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$c   = Clinica_Homepage::contacto();
$img = CLINICA_URI . '/assets/img/inicio/';

$ic_local   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';
$ic_relogio = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
?>
<section class="cv-hero" aria-labelledby="cv-hero-titulo">
	<picture class="cv-hero__bg">
		<source media="(max-width: 640px)" srcset="<?php echo esc_url( $img . 'hero-m.webp' ); ?>" width="900" height="1400">
		<img src="<?php echo esc_url( $img . 'hero-1600.webp' ); ?>"
		     srcset="<?php echo esc_url( $img . 'hero-1600.webp' ); ?> 1600w, <?php echo esc_url( $img . 'hero-2400.webp' ); ?> 2400w"
		     sizes="100vw" width="1600" height="900"
		     alt="Médico a acompanhar uma paciente idosa na receção luminosa da clínica"
		     fetchpriority="high" decoding="async" data-entrada-foto>
	</picture>

	<div class="hp-container cv-hero__inner">
		<div class="cv-hero__text">
			<ul class="cv-hero__info" data-entrada>
				<li><?php echo $ic_local; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $c['morada'][1] ); ?></li>
				<li><?php echo $ic_relogio; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $c['horario'] ); ?></li>
			</ul>
			<h1 id="cv-hero-titulo" class="cv-hero__title" data-entrada>Cuidados de saúde de excelência, perto de si</h1>
			<a class="cv-btn" data-entrada href="<?php echo esc_url( home_url( '/marcar/' ) ); ?>">Marcar consulta</a>
		</div>

	</div>
</section>
