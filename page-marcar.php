<?php
/**
 * Template Name: Clínica — Marcar Consulta
 *
 * A página "visit us" da referência ClinicHub, com o sistema de marcação real:
 * hero com o formulário em vidro (ou o convite a entrar) → onde estamos →
 * perguntas frequentes dentro de um telemóvel.
 * Montada com os blocos partilhados .cv-* (componentes.css); o controlador
 * (clinica_controller_cliente) trata o POST antes de qualquer HTML.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$paciente_id = 0;
if ( is_user_logged_in() ) {
	$paciente_id = Clinica_Repo::ensure_paciente( get_current_user_id() );
	clinica_controller_cliente( $paciente_id );
} else {
	// Marcação rápida sem conta (nome + telefone): PRG antes de qualquer HTML.
	Clinica_Marcacao_Rapida::controlador();
}

get_header();
?>
<div class="hp hp--marcar">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo">
		<?php
		// A ordem aqui é a ordem visual da página.
		get_template_part( 'pages/marcar/hero', null, array( 'paciente_id' => $paciente_id ) );
		get_template_part( 'pages/partials/local' );
		get_template_part( 'pages/marcar/perguntas' );
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>
<?php
get_footer();
