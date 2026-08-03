<?php
/**
 * Configuracao base do tema: capacidades de apresentacao.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Regista os theme supports, menus e tamanhos de imagem.
 * Closure anonima -> nao polui o namespace global com funcoes soltas.
 */
add_action( 'after_setup_theme', static function (): void {

	// Traducoes (pt_MZ / pt_PT). Os .mo ficam em /languages.
	load_theme_textdomain( 'clinica', CLINICA_DIR . '/languages' );

	// O WordPress passa a gerir a tag <title> (SEO correto por pagina).
	add_theme_support( 'title-tag' );

	// Imagem destacada -> a foto do medico usa este mecanismo.
	add_theme_support( 'post-thumbnails' );

	// Marcacao HTML5 semantica em vez de <div> legadas.
	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script',
	) );

	// Logotipo gerivel no admin (Aparencia -> Personalizar).
	add_theme_support( 'custom-logo', array(
		'height' => 48, 'width' => 180, 'flex-height' => true, 'flex-width' => true,
	) );

	// Tema hibrido: liga o pipeline de blocos e os estilos do editor.
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );

	// Menus registados por localizacao (nao por conteudo).
	register_nav_menus( array(
		'primary' => __( 'Menu Principal', 'clinica' ),
		'footer'  => __( 'Menu do Rodape', 'clinica' ),
	) );

	// Tamanhos de imagem intencionais -> evita servir originais gigantes.
	add_image_size( 'clinica-medico-card', 480, 600, true ); // retrato 4:5
	add_image_size( 'clinica-hero', 1600, 900, true );        // 16:9
} );

/**
 * /portal-cliente/ foi substituido por /marcar/ (marcacao) e /conta/ (area do
 * utente). A pagina fica publicada so para este redirecionamento nao partir
 * links e favoritos antigos; quando deixar de haver trafego, pode ir ao lixo.
 */
add_action( 'template_redirect', static function (): void {
	if ( is_page( 'portal-cliente' ) ) {
		wp_safe_redirect( home_url( '/marcar/' ), 301 );
		exit;
	}
} );