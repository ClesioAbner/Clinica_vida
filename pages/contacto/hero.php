<?php
/**
 * Contacto · Hero — banner limpo (.cv-hero): fotografia em moldura, telefone e
 * horário, título e CTA para o formulário, que vive na secção seguinte
 * (pages/contacto/mensagem.php) para não tapar a fotografia.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$c         = Clinica_Homepage::contacto();
$telefones = Clinica_Homepage::telefones();
$img       = CLINICA_URI . '/assets/img/contacto/';

$ic_tel     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>';
$ic_relogio = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
?>
<section class="cv-hero" aria-labelledby="con-titulo">
	<picture class="cv-hero__bg">
		<source media="(max-width: 640px)" srcset="<?php echo esc_url( $img . 'hero-m.webp' ); ?>" width="658" height="1024">
		<img src="<?php echo esc_url( $img . 'hero-1600.webp' ); ?>" width="1536" height="864"
		     alt="Rececionista da clínica ao telefone, a tomar notas"
		     fetchpriority="high" decoding="async" data-entrada-foto>
	</picture>

	<div class="hp-container cv-hero__inner">
		<div class="cv-hero__text">
			<ul class="cv-hero__info" data-entrada>
				<?php if ( $telefones ) : ?>
					<li><?php echo $ic_tel; // phpcs:ignore WordPress.Security.EscapeOutput ?><a href="<?php echo esc_attr( Clinica_Homepage::tel_href( $telefones[0] ) ); ?>"><?php echo esc_html( $telefones[0] ); ?></a></li>
				<?php endif; ?>
				<li><?php echo $ic_relogio; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php echo esc_html( $c['horario'] ); ?></li>
			</ul>
			<h1 id="con-titulo" class="cv-hero__title" data-entrada>Estamos aqui para si</h1>
			<a class="cv-btn" data-entrada href="#contacto">Enviar mensagem</a>
		</div>
	</div>
</section>
