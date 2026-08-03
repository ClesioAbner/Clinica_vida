<?php
/**
 * Template Name: Clínica — Contacto
 *
 * Página de contacto: informação da clínica + imagem + formulário com backend
 * real (grava em clinica_mensagens). Reutiliza cabeçalho/rodapé da homepage.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

// Controlador PRG — corre antes de qualquer output para poder redirecionar.
clinica_controller_contacto();

get_header();
?>

<div class="hp">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo" class="con">
		<?php get_template_part( 'pages/contacto/conteudo' ); ?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>

<?php
get_footer();
