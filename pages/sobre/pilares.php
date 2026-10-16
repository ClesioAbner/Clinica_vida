<?php
/**
 * Sobre · Missão, visão e valores — os cartões menta do bloco "how it works"
 * (pages/partials/passos) com ilustrações e etiquetas próprias.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

get_template_part(
	'pages/partials/passos',
	null,
	array(
		'id'      => 'missao',
		'eyebrow' => 'O que nos guia',
		'titulo'  => 'Missão, visão e valores',
		'passos'  => array(
			array(
				'etiqueta' => 'Missão',
				'titulo'   => 'Cuidar de perto',
				'texto'    => 'Oferecer a cada pessoa que nos procura um cuidado próximo, competente e acessível, do primeiro contacto ao acompanhamento continuado.',
				'svg'      => '<path d="M80 74c-15-10-24-18-24-29 0-7 5-12 11-12 5 0 10 3 13 8 3-5 8-8 13-8 6 0 11 5 11 12 0 11-9 19-24 29z"/><path d="M28 96c12-4 24 0 32 8l10 8h22c5 0 8 4 6 8-1 2-3 3-6 3H72"/><path d="M28 96v26M132 96c-12-4-24 0-32 8"/><path d="M132 96v26"/>',
			),
			array(
				'etiqueta' => 'Visão',
				'titulo'   => 'Uma referência de confiança',
				'texto'    => 'Ser uma referência de confiança na saúde em Maputo, reconhecida pela qualidade clínica e pela forma como tratamos quem cuidamos.',
				'svg'      => '<path d="M28 72s20-32 52-32 52 32 52 32-20 32-52 32-52-32-52-32z"/><circle cx="80" cy="72" r="15"/><circle cx="80" cy="72" r="5"/><path d="M80 22v8M80 114v8M40 30l5 6M120 30l-5 6"/>',
			),
			array(
				'etiqueta' => 'Valores',
				'titulo'   => 'Rigor, escuta e respeito',
				'texto'    => 'Rigor clínico, escuta honesta, respeito pela pessoa e transparência em cada decisão e em cada explicação que damos.',
				'svg'      => '<path d="M80 26l38 13v27c0 25-17 42-38 50-21-8-38-25-38-50V39z"/><path d="M63 70l12 12 23-25"/>',
			),
		),
	)
);
