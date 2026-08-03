<?php
/**
 * Homepage · Faixa final de call-to-action (full-bleed com fotografia + overlay).
 *
 * Fecho editorial da página antes do rodapé: imagem da clínica em fundo, véu em
 * gradiente teal para legibilidade, e o apelo à esquerda. Substitui o antigo
 * formulário — os formulários vivem na página /contacto/.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$url_marcar   = home_url( '/marcar/' );
$url_contacto = home_url( '/contacto/' );
?>
<section id="cta" class="hp-cta" data-screen="CTA" aria-labelledby="hp-cta-titulo">
	<div class="hp-cta__inner">
		<div class="hp-cta__content">
			<span class="hp-cta__eyebrow">Clínica Vida</span>
			<h2 id="hp-cta-titulo" class="hp-cta__title">Cuidamos de si, do primeiro contacto ao diagnóstico</h2>
			<p class="hp-cta__lead">Marque a sua consulta em poucos minutos ou fale com a nossa equipa. Estamos prontos para o receber.</p>

			<div class="hp-cta__actions">
				<a class="hp-btn hp-btn--white hp-btn--lg" href="<?php echo esc_url( $url_marcar ); ?>">Marcar consulta</a>
				<a class="hp-btn hp-btn--lg hp-cta__ghost" href="<?php echo esc_url( $url_contacto ); ?>">Falar connosco</a>
			</div>
		</div>
	</div>
</section>
