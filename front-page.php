<?php
/**
 * front-page.php — Página inicial pública da Clínica.
 *
 * O WordPress obriga este ficheiro a estar na raiz do tema, mas ele é apenas
 * um ORQUESTRADOR: cada secção da homepage vive num ficheiro próprio dentro de
 * /pages/home (frontend dividido, fácil de ler e manter).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="hp">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo">
		<?php
		// A ordem aqui é a ordem visual da página.
		get_template_part( 'pages/home/hero' );
		get_template_part( 'pages/home/marcacao' );
		get_template_part( 'pages/home/especialidades' );
		get_template_part( 'pages/home/sobre' );
		get_template_part( 'pages/home/medicos' );
		get_template_part( 'pages/home/testemunhos' );
		get_template_part( 'pages/home/cta' );
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>

<?php
get_footer();
