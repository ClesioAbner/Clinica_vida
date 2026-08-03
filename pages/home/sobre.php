<?php
/**
 * Homepage · Serviços — título + cartões de serviço + fotografia.
 * Dados: Clinica_Homepage::servicos().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$servicos = Clinica_Homepage::servicos();
?>
<section id="sobre" class="hp-sobre" data-screen="Servicos">
	<div class="hp-container hp-sobre__inner">

		<div>
			<h2 class="hp-sobre__title">Serviços de saúde de excelência para si e para os seus</h2>
			<p class="hp-sobre__lead">Da urgência ao diagnóstico, reunimos num só lugar os serviços essenciais para cuidar de si com rigor e proximidade.</p>
			<a class="hp-btn hp-btn--white hp-btn--lg" href="<?php echo esc_url( home_url( '/especialidades/' ) ); ?>">Ver serviços →</a>
		</div>

		<div class="hp-servicos">
			<?php foreach ( $servicos as $s ) : ?>
				<div class="hp-servico">
					<span class="hp-servico__icon"><?php echo $s['icone']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<div class="hp-servico__title"><?php echo esc_html( $s['titulo'] ); ?></div>
					<div class="hp-servico__text"><?php echo esc_html( $s['texto'] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="hp-sobre__media">
			<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/medico2.png' ); ?>"
			     alt="Médico da Clínica Vida" loading="lazy">
		</div>

	</div>
</section>
