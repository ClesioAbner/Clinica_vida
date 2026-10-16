<?php
/**
 * Template Name: Clínica — Contacto
 *
 * Contacto montado com os blocos da referência ClinicHub (componentes .cv-*):
 * banner limpo → fale connosco (formulário real, grava em clinica_mensagens)
 * → onde estamos → perguntas frequentes.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

// Controlador PRG — corre antes de qualquer output para poder redirecionar.
clinica_controller_contacto();

get_header();
?>
<div class="hp hp--contacto">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo">
		<?php
		// A ordem aqui é a ordem visual da página.
		get_template_part( 'pages/contacto/hero' );
		get_template_part( 'pages/contacto/mensagem' );
		get_template_part( 'pages/partials/local', null, array( 'titulo' => 'Venha visitar-nos em Sommerschield' ) );
		get_template_part(
			'pages/partials/faq',
			null,
			array(
				'id'        => 'perguntas',
				'perguntas' => Clinica_Homepage::faq(),
				'cartao'    => false,
			)
		);
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>
<?php
get_footer();
