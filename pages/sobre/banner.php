<?php
/**
 * Sobre · Banner de topo (banner20). Imagem só na content-box, para o header
 * fixo assentar numa faixa clara e não cortar a médica.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;
?>
<section class="sob-banner" style="--sob-bg:url('<?php echo esc_url( CLINICA_URI . '/assets/img/banner20.png' ); ?>')">
	<div class="sob-banner__inner">
		<div class="sob-banner__content">
			<div class="sob-eyebrow">Quem Somos</div>
			<h1 class="sob-banner__title">Uma clínica de Maputo, ao lado das famílias moçambicanas</h1>
			<p class="sob-banner__lead">Nascemos em Sommerschield com um propósito simples: aproximar cuidados de saúde de qualidade das pessoas, com tempo, rigor e humanidade.</p>
		</div>
	</div>
</section>
