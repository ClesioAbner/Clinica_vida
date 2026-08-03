<?php
/**
 * Template Name: Clínica — Especialidades
 *
 * Página pública das especialidades: banner + grelha (da BD) + CTA.
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

	<main id="conteudo" class="esp">
		<?php
		get_template_part( 'pages/especialidades/banner' );
		get_template_part( 'pages/especialidades/como-funciona' );
		get_template_part( 'pages/especialidades/grid' );
		get_template_part( 'pages/especialidades/cta' );
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>

<?php
get_footer();
