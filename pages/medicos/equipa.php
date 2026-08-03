<?php
/**
 * Médicos · Grelha da equipa — cartão com retrato e perfil detalhado.
 * Dados: Clinica_Homepage::equipa().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$equipa = Clinica_Homepage::equipa();
?>
<section id="equipa" class="med-team" data-screen="MedTeam">
	<div class="med-container">

		<div class="med-team__head">
			<div class="med-eyebrow">Especialistas</div>
			<h2 class="med-team__title">Conheça os nossos médicos</h2>
			<p class="med-team__lead">Cada especialista traz anos de prática clínica e uma dedicação genuína a quem trata. Conheça o percurso de cada um.</p>
		</div>

		<div class="med-grid">
			<?php foreach ( $equipa as $m ) : ?>
				<article class="med-card">
					<div class="med-card__photo">
						<img src="<?php echo esc_url( $m['foto'] ); ?>" alt="<?php echo esc_attr( $m['nome'] ); ?>" loading="lazy">
					</div>
					<div class="med-card__body">
						<div class="med-card__spec"><?php echo esc_html( $m['especialidade'] ); ?></div>
						<h3 class="med-card__name"><?php echo esc_html( $m['nome'] ); ?></h3>
						<div class="med-card__cargo"><?php echo esc_html( $m['cargo'] ); ?></div>
						<p class="med-card__desc"><?php echo esc_html( $m['descricao'] ); ?></p>

						<dl class="med-card__meta">
							<div class="med-card__row">
								<dt>Experiência</dt>
								<dd><?php echo esc_html( $m['experiencia'] ); ?></dd>
							</div>
							<div class="med-card__row">
								<dt>Formação</dt>
								<dd><?php echo esc_html( $m['formacao'] ); ?></dd>
							</div>
							<div class="med-card__row">
								<dt>Áreas de foco</dt>
								<dd><?php echo esc_html( implode( ' · ', $m['focos'] ) ); ?></dd>
							</div>
							<div class="med-card__row">
								<dt>Idiomas</dt>
								<dd><?php echo esc_html( $m['idiomas'] ); ?></dd>
							</div>
						</dl>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

	</div>
</section>
