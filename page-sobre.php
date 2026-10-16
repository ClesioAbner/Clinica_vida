<?php
/**
 * Template Name: Clínica — Sobre Nós
 *
 * Página institucional montada com os blocos da referência ClinicHub
 * (componentes .cv-* partilhados): hero → números → a nossa história →
 * missão, visão e valores (cartões "how it works") → onde estamos.
 * Cabeçalho e rodapé (com a faixa de marcação) são os do site público.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="hp hp--sobre">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo">
		<?php
		// A ordem aqui é a ordem visual da página.
		get_template_part( 'pages/sobre/hero' );
		get_template_part( 'pages/partials/numeros' );
		get_template_part( 'pages/sobre/historia' );
		get_template_part( 'pages/sobre/pilares' );
		get_template_part( 'pages/partials/local' );
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>
<?php
get_footer();
