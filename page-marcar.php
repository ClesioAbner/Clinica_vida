<?php
/**
 * Template Name: Clínica — Marcar Consulta
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$paciente_id = 0;
if ( is_user_logged_in() ) {
	$paciente_id = Clinica_Repo::ensure_paciente( get_current_user_id() );
	clinica_controller_cliente( $paciente_id );
}

get_header();
?>
<div class="hp">
	<?php get_template_part( 'pages/home/site-header' ); ?>
	<main id="conteudo" class="mk">
		<?php
		get_template_part( 'pages/marcar/banner' );
		get_template_part( $paciente_id ? 'pages/marcar/form' : 'pages/marcar/login' );
		?>
	</main>
	<?php get_template_part( 'pages/home/site-footer' ); ?>
</div>
<?php
get_footer();
