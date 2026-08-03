<?php
/**
 * Especialidades · "Como funciona" — explica o percurso em 3 passos.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$passos = array(
	array( '1', 'Escolha a especialidade', 'Veja as áreas disponíveis e escolha a que melhor responde ao que precisa.' ),
	array( '2', 'Marque a consulta',       'Entre na sua conta e escolha o médico, o dia e a hora que lhe der jeito.' ),
	array( '3', 'Seja atendido',           'Receba o cuidado da nossa equipa, com tempo, rigor e proximidade.' ),
);
?>
<section class="esp-steps" data-screen="EspComoFunciona">
	<div class="esp-container">
		<div class="esp-steps__head">
			<div class="esp-eyebrow">Como funciona</div>
			<h2 class="esp-steps__title">Marcar a sua consulta é simples</h2>
		</div>
		<div class="esp-steps__grid">
			<?php foreach ( $passos as $p ) : ?>
				<div class="esp-step">
					<div class="esp-step__label">Passo <?php echo esc_html( $p[0] ); ?></div>
					<h3 class="esp-step__title"><?php echo esc_html( $p[1] ); ?></h3>
					<p class="esp-step__text"><?php echo esc_html( $p[2] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
