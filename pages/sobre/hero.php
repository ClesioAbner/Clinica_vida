<?php
/**
 * Sobre · Hero — o hero da referência (.cv-hero): fotografia em moldura,
 * morada e horário, título, CTA e o cartão com miniatura que leva à história.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$c   = Clinica_Homepage::contacto();
$img = CLINICA_URI . '/assets/img/sobre/';

$ic_local   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>';
$ic_relogio = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
?>
<section class="cv-hero" aria-labelledby="sob-hero-titulo">
	<picture class="cv-hero__bg">
		<source media="(max-width: 640px)" srcset="<?php echo esc_url( $img . 'hero-m.webp' ); ?>" width="900" height="1400">
		<img src="<?php echo esc_url( $img . 'hero-1600.webp' ); ?>"
		     srcset="<?php echo esc_url( $img . 'hero-1600.webp' ); ?> 1600w, <?php echo esc_url( $img . 'hero-2400.webp' ); ?> 2400w"
		     sizes="100vw" width="1600" height="900"
		     alt="Médica a conversar com uma mãe que tem o filho ao colo, no consultório"
		     fetchpriority="high" decoding="async" data-entrada-foto>
	</picture>

	<div class="hp-container cv-hero__inner">
		<div class="cv-hero__text">
			<ul class="cv-hero__info" data-entrada>
				<li><?php echo $ic_local; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $c['morada'][1] ); ?></li>
				<li><?php echo $ic_relogio; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $c['horario'] ); ?></li>
			</ul>
			<h1 id="sob-hero-titulo" class="cv-hero__title" data-entrada>Uma clínica construída para cuidar de perto</h1>
			<a class="cv-btn" data-entrada href="<?php echo esc_url( home_url( '/medicos/' ) ); ?>">Conhecer a equipa</a>
		</div>

		<a class="cv-hero__card" data-entrada href="#historia">
			<span class="cv-hero__thumb">
				<img src="<?php echo esc_url( $img . 'hero-cartao.webp' ); ?>" width="534" height="300" alt="" loading="lazy" decoding="async">
				<span class="cv-hero__seta" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
				</span>
			</span>
			<span class="cv-hero__legenda">Porque abrimos portas em Sommerschield, e o que nos guia em cada consulta. <span class="hp-sr-only">Ler a nossa história</span></span>
		</a>
	</div>
</section>
