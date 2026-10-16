<?php
/**
 * front-page.php — Página inicial pública da Clínica.
 *
 * O WordPress obriga este ficheiro a estar na raiz do tema, mas ele é apenas
 * um ORQUESTRADOR: cada secção vive num ficheiro próprio em /pages/inicio
 * (desenho 2026, referência ClinicHub). O cabeçalho e o rodapé são os do
 * site público inteiro (pages/home/site-header.php e site-footer.php).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="hp hp--inicio">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo">
		<?php
		// A ordem aqui é a ordem visual da página.
		get_template_part( 'pages/inicio/hero' );
		get_template_part( 'pages/partials/numeros' );
		get_template_part( 'pages/inicio/manifesto' );
		get_template_part( 'pages/inicio/especialidades' );
		get_template_part( 'pages/partials/passos' );
		get_template_part( 'pages/inicio/porque' );
		get_template_part( 'pages/inicio/equipa' );
		get_template_part( 'pages/inicio/historias' );
		get_template_part( 'pages/partials/faq', null, array( 'perguntas' => Clinica_Homepage::faq(), 'telemovel' => true ) );
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>

<?php
get_footer();
