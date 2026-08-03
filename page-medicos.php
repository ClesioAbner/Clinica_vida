<?php
/**
 * Template Name: Clínica — Médicos
 *
 * Página pública da equipa: hero (texto + foto à direita) + grelha da equipa + CTA.
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

	<main id="conteudo" class="med">
		<?php
		get_template_part( 'pages/medicos/banner' );
		get_template_part( 'pages/medicos/valores' );
		get_template_part( 'pages/medicos/equipa' );
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>

<?php
get_footer();
