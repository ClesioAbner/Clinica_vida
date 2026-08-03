<?php
/**
 * Homepage · Especialidades — grelha de cartões (ícone + nome).
 * Dados: Clinica_Homepage::especialidades().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$especialidades = Clinica_Homepage::especialidades();
$url_especialidades = home_url( '/especialidades/' );
?>
<section id="especialidades" class="hp-especialidades" data-screen="Especialidades">
	<div class="hp-container">

		<div class="hp-head">
			<h2 class="hp-head__title">Especialidades</h2>
			<p class="hp-head__lead">Da prevenção ao tratamento, as nossas equipas cobrem as principais áreas da medicina.</p>
		</div>

		<div class="hp-esp-grid">
			<?php foreach ( $especialidades as $esp ) : ?>
				<a class="hp-esp" href="<?php echo esc_url( $url_especialidades ); ?>">
					<?php // O ícone é SVG confiável definido no backend (não é input do utilizador). ?>
					<span class="hp-esp__icon"><?php echo $esp['icone']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					<span class="hp-esp__name"><?php echo esc_html( $esp['nome'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

	</div>
</section>
