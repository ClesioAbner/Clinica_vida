<?php
/**
 * Médicos · A nossa forma de cuidar — bloco partilhado "relatos" (o "reviews"
 * da referência) com os três princípios que orientam cada consulta: o número
 * em serifa ocupa o lugar do "97%" da referência, sem estatísticas inventadas.
 *
 * Fotografias: assets/img/medicos/cuidar-*.webp (Freepik).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$img        = CLINICA_URI . '/assets/img/medicos/';
$principios = array(
	array( 'ouvir', 'Tempo para ouvir', 'As consultas têm a duração necessária para compreender a sua história. Um bom diagnóstico começa por uma boa conversa, sem pressa.', 'Médica a ouvir uma paciente, sorridente, durante a consulta' ),
	array( 'decidir', 'Decisões partilhadas', 'Explicamos as opções em linguagem simples e decidimos consigo. É o seu corpo e a sua vida, e a última palavra é sempre sua.', 'Médica a mostrar os resultados de um exame a uma paciente' ),
	array( 'acompanhar', 'Acompanhamento contínuo', 'Quando é preciso mais do que uma área, os nossos médicos falam entre si e coordenam um plano único, para não andar de porta em porta.', 'Equipa médica reunida a analisar resultados de diagnóstico' ),
);

$itens = array();
foreach ( $principios as $i => $p ) {
	$itens[] = array(
		'foto'    => $img . 'cuidar-' . $p[0] . '.webp',
		'alt'     => $p[3],
		'num'     => str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ),
		'titulo'  => $p[1],
		'texto'   => $p[2],
		'nome'    => 'Clínica Vida',
		'detalhe' => sprintf( 'Princípio %d de %d', $i + 1, count( $principios ) ),
	);
}

get_template_part(
	'pages/partials/relatos',
	null,
	array(
		'id'      => 'forma-de-cuidar',
		'eyebrow' => 'A nossa forma de cuidar',
		'titulo'  => 'O que orienta cada consulta',
		'rotulo'  => 'Princípios da equipa',
		'item'    => 'Princípio',
		'itens'   => $itens,
	)
);
