<?php
/**
 * Partilhado · Perguntas frequentes — o bloco "FAQ" da referência: cartão
 * menta de marcação à esquerda e acordeão à direita. O acordeão é
 * <details>/<summary> nativo: acessível por teclado e funciona sem JS (a
 * animação de abrir vive em assets/js/animacoes.js).
 *
 * Uso: get_template_part( 'pages/partials/faq', null, array(
 *     'id'        => 'faq',
 *     'eyebrow'   => 'Perguntas frequentes',
 *     'titulo'    => 'Respostas rápidas',
 *     'perguntas' => array( array( 'p' => 'Pergunta?', 'r' => 'Resposta.' ) ),
 *     'cartao'    => true,   // false: variante "visit us" (título à esquerda, sem cartão)
 * ) );
 *
 * Só perguntas cuja resposta o sistema cumpre de facto.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'id'        => 'faq',
		'eyebrow'   => 'Perguntas frequentes',
		'titulo'    => 'Respostas rápidas',
		'perguntas' => array(),
		'cartao'    => true,
		'telemovel' => false, // true: no lugar do cartão menta, a mão com o telemóvel sobre um fundo clínico animado
	)
);
if ( ! $a['perguntas'] ) {
	return;
}
$img  = CLINICA_URI . '/assets/img/';
$tels = Clinica_Homepage::telefones();
// Cruzes a flutuar no fundo do telemóvel: posição, atraso, duração e tamanho.
$cruzes = array( array( 10, 0, 15, 12 ), array( 34, 5, 18, 9 ), array( 62, 2, 13, 14 ), array( 86, 7, 17, 10 ) );
?>
<section id="<?php echo esc_attr( $a['id'] ); ?>" class="cv-sec cv-faq<?php echo $a['cartao'] ? '' : ' cv-faq--simples'; ?>" aria-labelledby="<?php echo esc_attr( $a['id'] ); ?>-titulo">
	<div class="hp-container cv-faq__grelha">

		<?php if ( $a['cartao'] && $a['telemovel'] ) : ?>
		<!-- A mão com o telemóvel (fundo transparente) sobre um fundo clínico
		     animado; no ecrã, o convite a marcar. Movimento: animacoes.js [data-fqt]
		     e o script no fim; o fundo só anima à vista (.is-viva). -->
		<aside class="cv-faq__tel cv-fqt" id="<?php echo esc_attr( $a['id'] ); ?>-tel" data-revelar data-fqt>
			<div class="cv-fqt__fundo" aria-hidden="true">
				<img class="cv-fqt__foto" src="<?php echo esc_url( $img . 'inicio/porque.webp' ); ?>" width="1493" height="871" alt="" loading="lazy" decoding="async">
				<span class="cv-fqt__luz cv-fqt__luz--a"></span>
				<span class="cv-fqt__luz cv-fqt__luz--b"></span>
				<svg class="cv-fqt__ecg" viewBox="0 0 400 120" preserveAspectRatio="none"><path d="M0 76 H90 l10 -6 l10 6 h18 l8 -50 l14 86 l10 -46 l8 10 h24 l10 -10 l10 10 H400" pathLength="100"/></svg>
				<?php foreach ( $cruzes as $c ) : ?>
					<span class="cv-fqt__cruz" style="--x:<?php echo (int) $c[0]; ?>%;--d:<?php echo (int) $c[1]; ?>s;--t:<?php echo (int) $c[2]; ?>s;--s:<?php echo (int) $c[3]; ?>px"></span>
				<?php endforeach; ?>
			</div>
			<div class="cv-fqt__tel" data-fqt-tel>
				<span class="cv-fqt__pulso" aria-hidden="true"><i></i><i></i><i></i></span>
				<div class="cv-fqt__flutua">
					<img class="cv-fqt__mao" src="<?php echo esc_url( $img . 'partilhado/mao-telemovel-faq.webp' ); ?>"
						srcset="<?php echo esc_url( $img . 'partilhado/mao-telemovel-faq-560.webp' ); ?> 560w, <?php echo esc_url( $img . 'partilhado/mao-telemovel-faq.webp' ); ?> 860w"
						sizes="(max-width: 760px) 120vw, 480px" width="860" height="1158" alt="" loading="lazy" decoding="async">
					<div class="cv-fqt__ecra">
						<span class="cv-fqt__notch" aria-hidden="true"></span>
						<div class="cv-fqt__estado" aria-hidden="true"><span>09:41</span><span class="cv-fqt__sinal"><i></i><i></i><i></i><i></i></span></div>
						<div class="cv-faq__app">
							<span class="cv-faq__logo"><img src="<?php echo esc_url( $img . 'logo-simbolo-96.webp' ); ?>" width="30" height="30" alt=""></span>
							<h3 class="cv-faq__app-t">Marque uma consulta</h3>
							<p class="cv-faq__app-s">Cuidar da saúde pode ser simples, rápido e sem stress.</p>
							<span class="cv-faq__aviso" aria-hidden="true"><i></i><span><b>Clínica Vida</b>Lembrete por SMS antes da consulta</span></span>
							<a class="cv-faq__app-btn" href="<?php echo esc_url( home_url( '/marcar/' ) ); ?>">Marcar consulta</a>
							<?php if ( $tels ) : ?><a class="cv-faq__app-tel" href="<?php echo esc_attr( Clinica_Homepage::tel_href( $tels[0] ) ); ?>">ou ligue <?php echo esc_html( $tels[0] ); ?></a><?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</aside>
		<script>
		( function () {
			var sec = document.getElementById( <?php echo wp_json_encode( $a['id'] . '-tel' ); ?> );
			if ( ! sec ) { return; }
			if ( 'IntersectionObserver' in window ) {
				new IntersectionObserver( function ( es ) { es.forEach( function ( e ) { sec.classList.toggle( 'is-viva', e.isIntersecting ); } ); }, { rootMargin: '80px 0px' } ).observe( sec );
			} else {
				sec.classList.add( 'is-viva' );
			}
		} )();
		</script>
		<?php elseif ( $a['cartao'] ) : ?>
		<aside class="cv-faq__cartao" data-revelar>
			<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-192.webp' ); ?>" width="60" height="60" alt="">
			<h3 class="cv-h3">Marque uma consulta</h3>
			<p>Cuidar da saúde pode ser simples, rápido e sem stress.</p>
			<a class="cv-tab cv-tab--botao" href="<?php echo esc_url( home_url( '/marcar/' ) ); ?>">
				Marcar consulta
				<span class="cv-tab__seta" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></span>
			</a>
		</aside>
		<?php endif; ?>

		<?php if ( ! $a['cartao'] ) : ?>
			<div class="cv-head cv-head--esq" data-revelar>
				<p class="cv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></p>
				<h2 id="<?php echo esc_attr( $a['id'] ); ?>-titulo" class="cv-h2"><?php echo esc_html( $a['titulo'] ); ?></h2>
			</div>
		<?php endif; ?>

		<div>
			<?php if ( $a['cartao'] ) : ?>
			<div class="cv-head cv-head--esq" data-revelar="0.08">
				<p class="cv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></p>
				<h2 id="<?php echo esc_attr( $a['id'] ); ?>-titulo" class="cv-h2"><?php echo esc_html( $a['titulo'] ); ?></h2>
			</div>
			<?php endif; ?>
			<div class="cv-acordeao" data-revelar-grupo>
				<?php foreach ( $a['perguntas'] as $i => $q ) : ?>
					<details class="cv-pergunta"<?php echo 0 === $i ? ' open' : ''; ?>>
						<summary><?php echo esc_html( $q['p'] ); ?><span class="cv-pergunta__mais" aria-hidden="true"></span></summary>
						<p><?php echo esc_html( $q['r'] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</div>

	</div>
</section>
