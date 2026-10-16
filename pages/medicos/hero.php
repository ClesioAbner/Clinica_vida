<?php
/**
 * Médicos · Hero — o hero da referência (componente .cv-hero): fotografia da
 * equipa em moldura, linha de factos, título, CTA branco e o cartão com
 * miniatura que leva à "forma de cuidar".
 *
 * No lugar das "estrelas / avaliações" da referência: nº de médicos e de
 * áreas (contados a partir dos perfis) e o horário.
 *
 * Dados: Clinica_Homepage::equipa() e ::contacto().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$equipa = Clinica_Homepage::equipa();
$c      = Clinica_Homepage::contacto();
$n_med  = count( $equipa );
$n_esp  = count( array_unique( array_column( $equipa, 'especialidade' ) ) );
$img    = CLINICA_URI . '/assets/img/medicos/';

$ic_pessoas = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6.5 6.5-6.5s6.5 2.9 6.5 6.5"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8M18 13.8c2.1.7 3.5 2.9 3.5 6.2"/></svg>';
$ic_relogio = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
?>
<section class="cv-hero" aria-labelledby="med-hero-titulo">
	<picture class="cv-hero__bg">
		<source media="(max-width: 640px)" srcset="<?php echo esc_url( $img . 'hero-m.webp' ); ?>" width="900" height="1400">
		<img src="<?php echo esc_url( $img . 'hero-1600.webp' ); ?>"
		     srcset="<?php echo esc_url( $img . 'hero-1600.webp' ); ?> 1600w, <?php echo esc_url( $img . 'hero-2400.webp' ); ?> 2400w"
		     sizes="100vw" width="1600" height="900"
		     alt="Três profissionais de saúde da clínica, sorridentes, num corredor"
		     fetchpriority="high" decoding="async" data-entrada-foto>
	</picture>

	<div class="hp-container cv-hero__inner">
		<div class="cv-hero__text">
			<ul class="cv-hero__info" data-entrada>
				<?php if ( $n_med ) : ?>
					<li><?php echo $ic_pessoas; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php
						echo esc_html(
							sprintf(
								'%1$s em %2$s',
								sprintf( _n( '%d médico', '%d médicos', $n_med, 'clinica' ), $n_med ),
								sprintf( _n( '%d área', '%d áreas', $n_esp, 'clinica' ), $n_esp )
							)
						);
						?>
					</li>
				<?php endif; ?>
				<li><?php echo $ic_relogio; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $c['horario'] ); ?></li>
			</ul>
			<h1 id="med-hero-titulo" class="cv-hero__title" data-entrada>Médicos que cuidam de si como pessoa, não como um número</h1>
			<a class="cv-btn" data-entrada href="#equipa">Conhecer a equipa</a>
		</div>

		<a class="cv-hero__card" data-entrada href="#forma-de-cuidar">
			<span class="cv-hero__thumb">
				<img src="<?php echo esc_url( $img . 'hero-cartao.webp' ); ?>" width="534" height="300" alt="" loading="lazy" decoding="async">
				<span class="cv-hero__seta" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
				</span>
			</span>
			<span class="cv-hero__legenda">Tempo para ouvir, decisões partilhadas e um plano único: a forma como cuidamos. <span class="hp-sr-only">Ver a nossa forma de cuidar</span></span>
		</a>
	</div>
</section>
