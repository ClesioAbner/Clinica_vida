<?php
/**
 * Partilhado · Onde estamos — o bloco "our location" da referência:
 * fotografia com a etiqueta-botão "Ver no mapa" e, ao lado, morada, horário
 * e contactos. Fonte única: Clinica_Homepage::contacto().
 *
 * Uso: get_template_part( 'pages/partials/local', null, array(
 *     'id'      => 'localizacao',
 *     'eyebrow' => 'Onde estamos',
 *     'titulo'  => 'Fácil de encontrar, em Sommerschield',
 * ) );
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'id'      => 'localizacao',
		'eyebrow' => 'Onde estamos',
		'titulo'  => 'Fácil de encontrar, em Sommerschield',
	)
);
$c         = Clinica_Homepage::contacto();
$telefones = Clinica_Homepage::telefones();
$morada    = implode( ', ', $c['morada'] );
$mapa      = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $morada );
$seta      = '<span class="cv-tab__seta" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg></span>';
?>
<section id="<?php echo esc_attr( $a['id'] ); ?>" class="cv-sec cv-local" aria-labelledby="<?php echo esc_attr( $a['id'] ); ?>-titulo">
	<div class="hp-container cv-local__grelha">

		<div class="cv-local__moldura" data-revelar>
			<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/partilhado/local.webp' ); ?>" width="626" height="404" alt="Médico a conversar com uma doente idosa na sala de espera" loading="lazy" decoding="async">
			<a class="cv-tab cv-tab--botao" href="<?php echo esc_url( $mapa ); ?>" target="_blank" rel="noopener">
				Ver no mapa<span class="hp-sr-only"> (abre o Google Maps numa nova janela)</span>
				<?php echo $seta; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</a>
		</div>

		<div>
			<div class="cv-head cv-head--esq" data-revelar="0.08">
				<p class="cv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></p>
				<h2 id="<?php echo esc_attr( $a['id'] ); ?>-titulo" class="cv-h2"><?php echo esc_html( $a['titulo'] ); ?></h2>
			</div>
			<dl class="cv-local__dados" data-revelar-grupo>
				<div>
					<dt>Morada</dt>
					<dd><?php echo esc_html( $c['morada'][0] ); ?><br><?php echo esc_html( $c['morada'][1] ); ?></dd>
				</div>
				<div>
					<dt>Horário</dt>
					<dd><?php echo esc_html( $c['horario'] ); ?></dd>
				</div>
				<div>
					<dt>Contactos</dt>
					<dd>
						<?php foreach ( $telefones as $i => $t ) : ?>
							<?php echo $i ? ' · ' : ''; ?><a href="<?php echo esc_attr( Clinica_Homepage::tel_href( $t ) ); ?>"><?php echo esc_html( $t ); ?></a>
						<?php endforeach; ?>
						<br><a href="mailto:<?php echo esc_attr( $c['email'] ); ?>"><?php echo esc_html( $c['email'] ); ?></a>
					</dd>
				</div>
			</dl>
		</div>

	</div>
</section>
