<?php

  //Enfileiramento de assets (CSS/JS) do frontend.
 
 // @package Clinica
 
	
declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;


 // Versao de um asset baseada na data de modificacao do ficheiro.
 // Muda o ficheiro -> muda a versao -> o browser invalida a cache.
 //Fallback para a versao do tema se o ficheiro ainda nao existir.
 
function clinica_asset_version( string $rel ): string {
	$path = CLINICA_DIR . '/' . ltrim( $rel, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : CLINICA_VERSION;
}

add_action( 'wp_enqueue_scripts', static function (): void {

	// JS partilhado (melhoria progressiva) -> defer para nao bloquear o render.
	wp_enqueue_script(
		'clinica-main',
		CLINICA_URI . '/assets/js/main.js',
		array(),
		clinica_asset_version( 'assets/js/main.js' ),
		array( 'in_footer' => true, 'strategy' => 'defer' )  // WP 6.3+
	);
} );

/**
 * Assets do "site público": fonte Inter + home.css (header/footer partilhados)
 * na homepage E na página de especialidades; especialidades.css só nesta.
 */
add_action( 'wp_enqueue_scripts', static function (): void {

	$e_especialidades = is_page_template( 'page-especialidades.php' );
	$e_marcar         = is_page_template( 'page-marcar.php' );
	$e_medicos        = is_page_template( 'page-medicos.php' );
	$e_sobre          = is_page_template( 'page-sobre.php' );
	$e_contacto       = is_page_template( 'page-contacto.php' );
	$e_entrar         = is_page_template( 'page-entrar.php' );
	$e_conta          = is_page_template( 'page-conta.php' );
	$e_gestao         = is_page_template( 'page-gestao.php' );
	$e_pmedico        = is_page_template( 'page-portal-medico.php' );
	$e_agenda         = is_page_template( 'page-agenda.php' );
	$e_recepcao       = is_page_template( 'page-recepcao.php' );
	$e_executivo      = is_page_template( 'page-executivo.php' );
	$site_publico     = is_front_page() || $e_especialidades || $e_marcar || $e_medicos || $e_sobre || $e_contacto || $e_entrar || $e_conta || $e_gestao || $e_pmedico || $e_agenda || $e_recepcao || $e_executivo;

	if ( ! $site_publico ) {
		return;
	}

	// Fonte Inter (Google Fonts).
	wp_enqueue_style(
		'clinica-inter',
		'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
		array(),
		null
	);

	// Estilos base do site público (classes .hp-*).
	wp_enqueue_style(
		'clinica-home',
		CLINICA_URI . '/assets/css/home.css',
		array( 'clinica-inter' ),
		clinica_asset_version( 'assets/css/home.css' )
	);

	// Estilos próprios da página de especialidades (classes .esp-*).
	if ( $e_especialidades ) {
		wp_enqueue_style(
			'clinica-especialidades',
			CLINICA_URI . '/assets/css/especialidades.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/especialidades.css' )
		);
	}

	// Estilos próprios da página de marcação (classes .mk-*).
	if ( $e_marcar ) {
		wp_enqueue_style(
			'clinica-marcar',
			CLINICA_URI . '/assets/css/marcar.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/marcar.css' )
		);
	}

	// Estilos próprios da página de médicos (classes .med-*).
	if ( $e_medicos ) {
		wp_enqueue_style(
			'clinica-medicos',
			CLINICA_URI . '/assets/css/medicos.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/medicos.css' )
		);
	}

	// Estilos próprios da página Sobre Nós (classes .sob-*).
	if ( $e_sobre ) {
		wp_enqueue_style(
			'clinica-sobre',
			CLINICA_URI . '/assets/css/sobre.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/sobre.css' )
		);
	}

	// Estilos próprios da página de Contacto (classes .con-*).
	if ( $e_contacto ) {
		wp_enqueue_style(
			'clinica-contacto',
			CLINICA_URI . '/assets/css/contacto.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/contacto.css' )
		);
	}

	// Estilos próprios da página de autenticação (classes .au-*).
	if ( $e_entrar ) {
		wp_enqueue_style(
			'clinica-entrar',
			CLINICA_URI . '/assets/css/entrar.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/entrar.css' )
		);
	}

	// Estilos próprios da página Minha Conta (classes .ac-*).
	if ( $e_conta ) {
		wp_enqueue_style(
			'clinica-conta',
			CLINICA_URI . '/assets/css/conta.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/conta.css' )
		);
	}

	// Estilos próprios do painel de Gestão + Receção (classes .ge-* / .ad-*).
	// A Direção reutiliza os cartões e gráficos .ad-* do painel de gestão.
	if ( $e_gestao || $e_recepcao || $e_executivo ) {
		wp_enqueue_style(
			'clinica-gestao',
			CLINICA_URI . '/assets/css/gestao.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/gestao.css' )
		);
	}

	// Estilos próprios do portal Direção (classes .ex-*).
	if ( $e_executivo ) {
		wp_enqueue_style(
			'clinica-executivo',
			CLINICA_URI . '/assets/css/executivo.css',
			array( 'clinica-gestao' ),
			clinica_asset_version( 'assets/css/executivo.css' )
		);
	}

	// Estilos do shell de painel (sidebar/dashboard) — médico, gestão e receção (classes .pm-*).
	if ( $e_pmedico || $e_gestao || $e_recepcao || $e_executivo ) {
		wp_enqueue_style(
			'clinica-painel-medico',
			CLINICA_URI . '/assets/css/painel-medico.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/painel-medico.css' )
		);
	}

	// Estilos próprios do portal da Receção (classes .rc-*).
	if ( $e_recepcao ) {
		wp_enqueue_style(
			'clinica-recepcao',
			CLINICA_URI . '/assets/css/recepcao.css',
			array( 'clinica-gestao' ),
			clinica_asset_version( 'assets/css/recepcao.css' )
		);
	}

	// Estilos da gestão de agenda (classes .ag-*).
	if ( $e_agenda ) {
		wp_enqueue_style(
			'clinica-agenda',
			CLINICA_URI . '/assets/css/agenda.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/agenda.css' )
		);
	}
} );