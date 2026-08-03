<?php
/**
 * Template Name: Clínica — Sobre Nós
 *
 * Página institucional: banner + história/missão + localização (Maputo).
 * Reutiliza o cabeçalho e o rodapé da homepage para manter a identidade.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="hp">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo" class="sob">
		<?php
		get_template_part( 'pages/sobre/banner' );
		get_template_part( 'pages/sobre/historia' );
		get_template_part( 'pages/sobre/localizacao' );
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>

<?php
get_footer();
