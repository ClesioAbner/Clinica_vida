<?php
/**
 * Sobre · Localização e contactos — lê da fonte única Clinica_Homepage::contacto().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$c      = Clinica_Homepage::contacto();
$morada = implode( ', ', $c['morada'] );
$tel_no = preg_replace( '/[^+\d]/', '', $c['telefone'] );
?>
<section class="sob-local" data-screen="SobreLocal">
	<div class="sob-container">

		<div class="sob-local__head">
			<div class="sob-eyebrow">Onde Estamos</div>
			<h2 class="sob-local__title">Fáceis de encontrar, em Sommerschield</h2>
		</div>

		<div class="sob-local__grid">
			<div class="sob-info">
				<div class="sob-info__rotulo">Morada</div>
				<p class="sob-info__valor"><?php echo esc_html( $morada ); ?></p>
			</div>
			<div class="sob-info">
				<div class="sob-info__rotulo">Telefone</div>
				<p class="sob-info__valor"><a href="tel:<?php echo esc_attr( $tel_no ); ?>"><?php echo esc_html( $c['telefone'] ); ?></a></p>
			</div>
			<div class="sob-info">
				<div class="sob-info__rotulo">Email</div>
				<p class="sob-info__valor"><a href="mailto:<?php echo esc_attr( $c['email'] ); ?>"><?php echo esc_html( $c['email'] ); ?></a></p>
			</div>
			<div class="sob-info">
				<div class="sob-info__rotulo">Horário</div>
				<p class="sob-info__valor"><?php echo esc_html( $c['horario'] ); ?></p>
			</div>
		</div>

	</div>
</section>
