<?php
/**
 * inc/especialidades.php
 *
 * Catálogo de ícones por especialidade (slug -> SVG line-art).
 * A tabela clinica_especialidades não tem coluna de ícone; mapeamos aqui,
 * com um ícone genérico (estetoscópio) de reserva para slugs desconhecidos.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Devolve o SVG do ícone de uma especialidade a partir do seu slug.
 * O SVG é confiável (definido aqui, não vem de input) -> pode imprimir-se direto.
 */
function clinica_icone_especialidade( string $slug ): string {

	$mapa = array(
		'cardiologia'  => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>',
		'dermatologia' => '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="4"></circle>',
		'pediatria'    => '<circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line>',
		'neurologia'   => '<path d="M12 2a7 7 0 0 1 7 7c0 2.5-1.5 4-2.5 5.5S15 18 15 20h-6c0-2-.5-4-1.5-5.5S5 11.5 5 9a7 7 0 0 1 7-7z"></path><path d="M9 22h6"></path>',
		'ortopedia'    => '<path d="M12 2v4"></path><path d="M12 18v4"></path><circle cx="12" cy="8" r="3"></circle><circle cx="12" cy="16" r="3"></circle>',
		'oftalmologia' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>',
	);

	// Reserva: estetoscópio.
	$default = '<path d="M4 3v6a5 5 0 0 0 10 0V3"></path><path d="M9 14a4 4 0 0 0 8 0v-3"></path><circle cx="19" cy="9" r="2"></circle>';

	$interior = $mapa[ $slug ] ?? $default;

	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $interior . '</svg>';
}
