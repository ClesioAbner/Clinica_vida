<?php
/**
 * Início · Histórias de pacientes — bloco partilhado "relatos" (o "reviews"
 * da referência) com os testemunhos. A referência mostrava "97% de
 * satisfação": sem um número real, fica de fora (topo do cartão = aspas).
 *
 * Dados: Clinica_Homepage::testemunhos(). Fotografias: inicio/historia-<n>.webp.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$img   = CLINICA_URI . '/assets/img/inicio/';
$itens = array();
foreach ( Clinica_Homepage::testemunhos() as $i => $h ) {
	$itens[] = array(
		'foto'    => $img . 'historia-' . ( $i + 1 ) . '.webp',
		'texto'   => $h['texto'],
		'nome'    => $h['nome'],
		'detalhe' => $h['detalhe'],
	);
}

get_template_part(
	'pages/partials/relatos',
	null,
	array(
		'id'      => 'testemunhos',
		'eyebrow' => 'Testemunhos',
		'titulo'  => 'Histórias de quem cuidamos',
		'rotulo'  => 'Histórias de pacientes',
		'item'    => 'História',
		'itens'   => $itens,
	)
);
