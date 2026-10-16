<?php
/**
 * Template Name: Clínica — Médicos
 *
 * Página pública da equipa, montada com os blocos da referência ClinicHub
 * (os mesmos componentes .cv-* da página inicial, outra organização):
 * hero → equipa ("our team") → forma de cuidar ("reviews") → FAQ.
 * Cabeçalho e rodapé (com a faixa de marcação) são os do site público.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="hp hp--medicos">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo">
		<?php
		// A ordem aqui é a ordem visual da página.
		get_template_part( 'pages/medicos/hero' );
		get_template_part( 'pages/medicos/equipa' );
		get_template_part( 'pages/medicos/cuidar' );
		get_template_part(
			'pages/partials/faq',
			null,
			array(
				'id'        => 'perguntas',
				'titulo'    => 'Escolher o seu médico',
				'perguntas' => Clinica_Homepage::faq_medicos(),
			)
		);
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>

<?php
get_footer();
