<?php
/**
 * Partilhado · Relatos — o bloco "reviews" da referência: carrossel de slides
 * com fotografia larga + cartão menta (texto e etiqueta recortada). O topo do
 * cartão são aspas (testemunhos) ou um número em serifa ('num', princípios).
 * Scroll-snap nativo; as setas são melhoria progressiva (assets/js/main.js).
 *
 * Uso: get_template_part( 'pages/partials/relatos', null, array(
 *     'id'      => 'testemunhos',
 *     'eyebrow' => 'Testemunhos',
 *     'titulo'  => 'Histórias de quem cuidamos',
 *     'rotulo'  => 'Histórias de pacientes',     // nome da pista para leitores de ecrã
 *     'item'    => 'História',                   // "História anterior / seguinte"
 *     'itens'   => array( array(
 *         'foto' => URL, 'alt' => '', 'num' => '01' (opcional), 'titulo' => '' (opcional),
 *         'texto' => '', 'nome' => '', 'detalhe' => '',
 *     ) ),
 * ) );
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'id'      => 'relatos',
		'eyebrow' => '',
		'titulo'  => '',
		'rotulo'  => '',
		'item'    => 'Slide',
		'itens'   => array(),
	)
);
if ( ! $a['itens'] ) {
	return;
}
$seta = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 6 6 6-6 6"/></svg>';
?>
<section id="<?php echo esc_attr( $a['id'] ); ?>" class="cv-sec cv-relatos" aria-labelledby="<?php echo esc_attr( $a['id'] ); ?>-titulo" data-carrossel>
	<div class="hp-container cv-relatos__topo">
		<div class="cv-head cv-head--esq" data-revelar>
			<p class="cv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></p>
			<h2 id="<?php echo esc_attr( $a['id'] ); ?>-titulo" class="cv-h2"><?php echo esc_html( $a['titulo'] ); ?></h2>
		</div>
		<div class="cv-setas">
			<button class="cv-seta cv-seta--ant" type="button" data-carrossel-ant aria-label="<?php echo esc_attr( $a['item'] . ' anterior' ); ?>"><?php echo $seta; // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
			<button class="cv-seta" type="button" data-carrossel-seg aria-label="<?php echo esc_attr( $a['item'] . ' seguinte' ); ?>"><?php echo $seta; // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
		</div>
	</div>

	<div class="cv-relatos__pista" data-revelar="0.1" data-carrossel-pista tabindex="0" aria-label="<?php echo esc_attr( $a['rotulo'] . ' (deslize para ver mais)' ); ?>">
		<?php foreach ( $a['itens'] as $r ) : ?>
			<figure class="cv-relato">
				<img class="cv-relato__foto" src="<?php echo esc_url( $r['foto'] ); ?>" width="1380" height="940" alt="<?php echo esc_attr( $r['alt'] ?? '' ); ?>" loading="lazy" decoding="async">
				<div class="cv-relato__cartao">
					<?php if ( ! empty( $r['num'] ) ) : ?>
						<span class="cv-relato__num" aria-hidden="true"><?php echo esc_html( $r['num'] ); ?></span>
					<?php else : ?>
						<span class="cv-relato__aspas" aria-hidden="true">&ldquo;</span>
					<?php endif; ?>
					<?php if ( ! empty( $r['titulo'] ) ) : ?>
						<h3 class="cv-relato__titulo"><?php echo esc_html( $r['titulo'] ); ?></h3>
					<?php endif; ?>
					<?php if ( empty( $r['num'] ) ) : ?>
						<blockquote class="cv-relato__texto"><?php echo esc_html( $r['texto'] ); ?></blockquote>
					<?php else : ?>
						<p class="cv-relato__texto"><?php echo esc_html( $r['texto'] ); ?></p>
					<?php endif; ?>
					<figcaption class="cv-tab">
						<strong><?php echo esc_html( $r['nome'] ); ?></strong>
						<span><?php echo esc_html( $r['detalhe'] ); ?></span>
					</figcaption>
				</div>
			</figure>
		<?php endforeach; ?>
	</div>
</section>
