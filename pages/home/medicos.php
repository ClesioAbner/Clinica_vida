<?php
/**
 * Homepage · Equipa médica — grelha de cartões com fotografia.
 * Dados: Clinica_Homepage::equipa() (mostra os 4 primeiros; ver todos em /medicos/).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$medicos     = array_slice( Clinica_Homepage::equipa(), 0, 4 );
$url_medicos = home_url( '/medicos/' );
?>
<section id="medicos" class="hp-medicos" data-screen="Medicos">
	<div class="hp-container">

		<div class="hp-medicos__head">
			<div class="hp-medicos__intro">
				<div class="hp-eyebrow">A Nossa Equipa</div>
				<h2 class="hp-medicos__title">Médicos que fazem a diferença</h2>
			</div>
			<a class="hp-btn hp-btn--ghost" href="<?php echo esc_url( $url_medicos ); ?>">Ver todos os médicos</a>
		</div>

		<div class="hp-med-grid">
			<?php foreach ( $medicos as $med ) : ?>
				<a class="hp-med" href="<?php echo esc_url( $url_medicos ); ?>">
					<div class="hp-med__photo">
						<img src="<?php echo esc_url( $med['foto'] ); ?>" alt="<?php echo esc_attr( $med['nome'] ); ?>" loading="lazy">
					</div>
					<div class="hp-med__body">
						<div class="hp-med__spec"><?php echo esc_html( $med['especialidade'] ); ?></div>
						<div class="hp-med__name"><?php echo esc_html( $med['nome'] ); ?></div>
						<div class="hp-med__cargo"><?php echo esc_html( $med['cargo'] ); ?></div>
					</div>
				</a>
			<?php endforeach; ?>
		</div>

	</div>
</section>
