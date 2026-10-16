<?php
/**
 * Partilhado · Como funciona — três cartões menta com ilustração de traço
 * (SVG inline, herdam a cor) e etiqueta recortada ("Passo N" por omissão).
 * Usado na página inicial, em Especialidades e (com cartões próprios) em Sobre.
 *
 * Uso: get_template_part( 'pages/partials/passos', null, array(
 *     'id'      => 'como-funciona',                       // âncora da secção
 *     'eyebrow' => 'Como funciona',
 *     'titulo'  => 'Começar na Clínica Vida é simples',
 *     'passos'  => array( array( 'titulo' => '', 'texto' => '', 'svg' => '<path …/>', 'etiqueta' => 'Missão' ) ),
 * ) );   // 'passos' e 'etiqueta' são opcionais (por omissão: os 3 passos da marcação).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$a = wp_parse_args(
	$args ?? array(),
	array(
		'id'      => 'como-funciona',
		'eyebrow' => 'Como funciona',
		'titulo'  => 'Começar na Clínica Vida é simples',
	)
);

// Brilhos decorativos partilhados pelas três ilustrações.
$brilhos = '<path d="M24 34v10M19 39h10"/><path d="M134 22l2.4 5.6L142 30l-5.6 2.4L134 38l-2.4-5.6L126 30l5.6-2.4z"/><circle cx="140" cy="62" r="1.8"/><circle cx="22" cy="96" r="1.8"/><path d="M138 104v8M134 108h8"/>';

$passos = $a['passos'] ?? array(
	array(
		'titulo' => 'Marque online',
		'texto'  => 'Escolha a especialidade e o médico no site e o horário que mais lhe convém.',
		'svg'    => '<rect x="38" y="32" width="80" height="70" rx="10"/><path d="M38 52h80M58 24v16M98 24v16"/><rect x="50" y="62" width="12" height="10" rx="2"/><rect x="72" y="62" width="12" height="10" rx="2"/><rect x="94" y="62" width="12" height="10" rx="2"/><rect x="50" y="80" width="12" height="10" rx="2"/><rect x="72" y="80" width="12" height="10" rx="2"/><path d="M100 84l26 9-10 4 9 11-6 5-9-11-6 9z"/>',
	),
	array(
		'titulo' => 'Venha à consulta',
		'texto'  => 'Faça o check-in na receção e seja visto pelo seu médico, com tempo para o ouvir.',
		'svg'    => '<circle cx="64" cy="42" r="16"/><path d="M30 112c0-21 15-36 34-36 8 0 15 2 21 7"/><circle cx="98" cy="88" r="20"/><path d="M112 103l18 18" stroke-width="5"/><path d="M84 88h6l4-8 5 15 4-7h7"/>',
	),
	array(
		'titulo' => 'Acompanhamento',
		'texto'  => 'Recebe lembretes por SMS e continuamos consigo depois da consulta, no que for preciso.',
		'svg'    => '<path d="M80 92c-16-11-26-20-26-32 0-8 6-14 13-14 6 0 10 3 13 8 3-5 7-8 13-8 7 0 13 6 13 14 0 12-10 21-26 32z"/><path d="M80 56v18M71 65h18"/><path d="M80 34v-8M98 38l5-6M62 38l-5-6"/><path d="M36 72c-2 14 4 28 16 36l10 16M36 72c0-6 8-7 10-1l6 20"/><path d="M124 72c2 14-4 28-16 36l-10 16M124 72c0-6-8-7-10-1l-6 20"/>',
	),
);
?>
<section id="<?php echo esc_attr( $a['id'] ); ?>" class="cv-sec cv-passos" aria-labelledby="<?php echo esc_attr( $a['id'] ); ?>-titulo">
	<div class="hp-container">

		<div class="cv-head" data-revelar>
			<p class="cv-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></p>
			<h2 id="<?php echo esc_attr( $a['id'] ); ?>-titulo" class="cv-h2"><?php echo esc_html( $a['titulo'] ); ?></h2>
		</div>

		<ol class="cv-passos__lista" data-revelar-grupo>
			<?php foreach ( $passos as $i => $p ) : ?>
				<li class="cv-passo">
					<div class="cv-passo__cartao">
						<svg class="cv-passo__ilustracao" viewBox="0 0 160 140" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
							<?php echo $p['svg'] . $brilhos; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</svg>
						<span class="cv-tab"><?php echo esc_html( $p['etiqueta'] ?? 'Passo ' . ( $i + 1 ) ); ?></span>
					</div>
					<h3 class="cv-passo__titulo"><?php echo esc_html( $p['titulo'] ); ?></h3>
					<p class="cv-passo__texto"><?php echo esc_html( $p['texto'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>

	</div>
</section>
