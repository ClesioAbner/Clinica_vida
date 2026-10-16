<?php
/**
 * Início · Equipa — fila de retratos em movimento contínuo (marquee).
 *
 * A lista é impressa duas vezes para o ciclo não ter salto; a cópia fica
 * escondida dos leitores de ecrã e fora da ordem de tabulação. Pausa ao passar
 * o rato / com foco, e para por completo com prefers-reduced-motion (aí vira
 * uma fila com scroll horizontal).
 *
 * Dados: Clinica_Homepage::equipa().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$equipa = Clinica_Homepage::equipa();
if ( ! $equipa ) {
	return;
}
$url_med = home_url( '/medicos/' );
?>
<section id="medicos" class="cv-sec ini-equipa" aria-labelledby="ini-equipa-titulo">
	<div class="hp-container">
		<div class="cv-head" data-revelar>
			<p class="cv-eyebrow">A nossa equipa</p>
			<h2 id="ini-equipa-titulo" class="cv-h2">Os médicos por trás do seu cuidado</h2>
		</div>
	</div>

	<div class="ini-marquee" data-revelar="0.1">
		<div class="ini-marquee__pista">
			<?php foreach ( array( false, true ) as $copia ) : ?>
				<ul class="ini-marquee__grupo"<?php echo $copia ? ' aria-hidden="true"' : ''; ?>>
					<?php foreach ( $equipa as $m ) : ?>
						<li>
							<a class="cv-medico" href="<?php echo esc_url( $url_med ); ?>"<?php echo $copia ? ' tabindex="-1"' : ''; ?>>
								<img src="<?php echo esc_url( $m['foto'] ); ?>" width="400" height="530" alt="" loading="lazy" decoding="async">
								<span class="cv-tab">
									<strong><?php echo esc_html( $m['nome'] ); ?></strong>
									<span><?php echo esc_html( $m['especialidade'] ); ?></span>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endforeach; ?>
		</div>
	</div>
</section>
