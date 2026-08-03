<?php
/**
 * Especialidades · Banner de topo (imagem banner1 + texto à esquerda).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$url_cliente = home_url( '/marcar/' );
?>
<section class="esp-banner" data-screen="EspBanner"
         style="background-image:url('<?php echo esc_url( CLINICA_URI . '/assets/img/banner1.png' ); ?>')">
	<div class="esp-banner__inner">
		<div class="esp-banner__content">
			<div class="esp-eyebrow">Especialidades</div>
			<h1 class="esp-banner__title">O especialista certo para cada cuidado</h1>
			<p class="esp-banner__lead">Uma equipa multidisciplinar, tecnologia moderna e um atendimento com tempo. Escolha a área de que precisa e marque a sua consulta em minutos.</p>
			<a class="esp-btn esp-btn--primary esp-btn--lg" href="<?php echo esc_url( $url_cliente ); ?>">Marque a sua consulta</a>
		</div>
	</div>
</section>
