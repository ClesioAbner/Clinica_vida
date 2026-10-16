<?php
/**
 * Template Name: Clínica — Especialidades
 *
 * Página pública das especialidades, montada com os blocos da referência
 * ClinicHub (componentes .cv-* partilhados com a página inicial):
 * hero com marcação rápida ("visit us") → vitrine das especialidades (foto
 * fixa que muda com a leitura) → passos ("how it works") → FAQ.
 * Cabeçalho e rodapé (com a faixa de marcação) são os do site público.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="hp hp--especialidades">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo">
		<?php
		// A ordem aqui é a ordem visual da página.
		get_template_part( 'pages/especialidades/hero' );
		get_template_part( 'pages/especialidades/vitrine' );
		get_template_part(
			'pages/partials/passos',
			null,
			array(
				'id'      => 'como-marcar',
				'eyebrow' => 'Como marcar',
				'titulo'  => 'Da escolha à consulta, em três passos',
			)
		);
		get_template_part(
			'pages/partials/faq',
			null,
			array(
				'id'        => 'perguntas',
				'titulo'    => 'Escolher a especialidade',
				'perguntas' => Clinica_Homepage::faq_especialidades(),
			)
		);
		?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>

<?php
get_footer();
