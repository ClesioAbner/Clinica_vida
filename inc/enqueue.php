<?php
/**
 * Enfileiramento dos assets (CSS/JS) do frontend.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

/**
 * Versão de um asset pela data de modificação do ficheiro: muda o ficheiro,
 * muda a versão e o browser invalida a cache. Sem ficheiro, usa a do tema.
 */
function clinica_asset_version( string $rel ): string {
	$path = CLINICA_DIR . '/' . ltrim( $rel, '/' );
	return file_exists( $path ) ? (string) filemtime( $path ) : CLINICA_VERSION;
}

// Animações: html.hp-motion põe os elementos no estado inicial ANTES da 1.ª
// pintura (sem "piscar"). Quem pede menos movimento não a recebe.
// Plano B: os scripts "defer" (Motion + animacoes.js) correm sempre antes do
// DOMContentLoaded; se nessa altura não houver hp-motion-pronto, falharam e a
// classe sai (tudo aparece). Um temporizador fixo a contar do <head> disparava
// cedo demais quando o servidor demorava a enviar o HTML.
add_action( 'wp_head', static function (): void {
	if ( ! ( is_front_page() || is_page_template( 'page-especialidades.php' ) || is_page_template( 'page-medicos.php' ) || is_page_template( 'page-marcar.php' ) || is_page_template( 'page-sobre.php' ) || is_page_template( 'page-contacto.php' ) ) ) {
		return;
	}
	echo "<script>(function(d){if(!window.matchMedia||matchMedia('(prefers-reduced-motion: reduce)').matches){return;}d.classList.add('hp-motion');function b(){if(!d.classList.contains('hp-motion-pronto')){d.classList.remove('hp-motion');}}document.addEventListener('DOMContentLoaded',function(){setTimeout(b,0);});setTimeout(b,10000);})(document.documentElement);</script>\n";
}, 1 );

// Abre cedo a ligação aos servidores de fontes (poupa um round-trip).
add_filter( 'wp_resource_hints', static function ( array $urls, string $tipo ): array {
	if ( 'preconnect' === $tipo ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}, 10, 2 );

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
	$e_legal          = is_page_template( Clinica_Legal::TEMPLATE );
	$site_publico     = is_front_page() || $e_especialidades || $e_marcar || $e_medicos || $e_sobre || $e_contacto || $e_entrar || $e_conta || $e_gestao || $e_pmedico || $e_agenda || $e_recepcao || $e_executivo || $e_legal;

	if ( ! $site_publico ) {
		return;
	}

	// Inter (texto) + Lora (títulos, var --hp-serif) num só pedido ao Google Fonts.
	wp_enqueue_style(
		'clinica-inter',
		'https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400&family=Inter:wght@400;500;600;700;800&display=swap',
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

	// Componentes partilhados da linguagem 2026 (.cv-*: botão, etiqueta, passos…)
	// — em todas as páginas com o cabeçalho/rodapé públicos.
	$com_rodape = is_front_page() || $e_especialidades || $e_marcar || $e_medicos || $e_sobre || $e_contacto || $e_conta || $e_agenda || $e_legal;
	if ( $com_rodape ) {
		wp_enqueue_style(
			'clinica-componentes',
			CLINICA_URI . '/assets/css/componentes.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/componentes.css' )
		);
		// Aviso de cookies, impresso por baixo do rodapé público.
		wp_enqueue_style(
			'clinica-cookies',
			CLINICA_URI . '/assets/css/cookies.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/cookies.css' )
		);
		wp_enqueue_script(
			'clinica-cookies',
			CLINICA_URI . '/assets/js/cookies.js',
			array(),
			clinica_asset_version( 'assets/js/cookies.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}

	// Termos, Privacidade e Cookies (classes .lg-*).
	if ( $e_legal ) {
		wp_enqueue_style(
			'clinica-legal',
			CLINICA_URI . '/assets/css/legal.css',
			array( 'clinica-componentes' ),
			clinica_asset_version( 'assets/css/legal.css' )
		);
		wp_enqueue_script(
			'clinica-legal',
			CLINICA_URI . '/assets/js/legal.js',
			array(),
			clinica_asset_version( 'assets/js/legal.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}

	// Homepage 2026 (secções .ini-*).
	if ( is_front_page() ) {
		wp_enqueue_style(
			'clinica-inicio',
			CLINICA_URI . '/assets/css/inicio.css',
			array( 'clinica-componentes' ),
			clinica_asset_version( 'assets/css/inicio.css' )
		);
	}

	// Animações (Motion = Framer Motion em JS puro, MIT, alojado no tema) nas
	// páginas com a linguagem 2026. animacoes.js é melhoria progressiva.
	if ( is_front_page() || $e_especialidades || $e_medicos || $e_marcar || $e_sobre || $e_contacto ) {
		wp_enqueue_script( 'motion', CLINICA_URI . '/assets/js/vendor/motion-11.18.2.js', array(), '11.18.2', array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_enqueue_script(
			'clinica-animacoes',
			CLINICA_URI . '/assets/js/animacoes.js',
			array( 'motion' ),
			clinica_asset_version( 'assets/js/animacoes.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}

	// Estilos próprios da página de especialidades (classes .esp-*).
	if ( $e_especialidades ) {
		wp_enqueue_style(
			'clinica-especialidades',
			CLINICA_URI . '/assets/css/especialidades.css',
			array( 'clinica-componentes' ),
			clinica_asset_version( 'assets/css/especialidades.css' )
		);
	}

	// Marcar, Sobre e Contacto: só blocos partilhados de componentes.css
	// (.cv-hero, .cv-hero--reserva, .cv-local, .cv-editorial, .cv-faq…) — sem CSS próprio.

	// Estilos próprios da página de médicos (classes .med-*).
	if ( $e_medicos ) {
		wp_enqueue_style(
			'clinica-medicos',
			CLINICA_URI . '/assets/css/medicos.css',
			array( 'clinica-componentes' ),
			clinica_asset_version( 'assets/css/medicos.css' )
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

	// Camada visual partilhada dos portais. Carrega por último para consolidar
	// shell, formulários, tabelas e estados sem alterar os templates existentes.
	if ( $e_pmedico || $e_gestao || $e_recepcao || $e_executivo || $e_agenda ) {
		wp_enqueue_style(
			'clinica-enterprise',
			CLINICA_URI . '/assets/css/clinic-enterprise.css',
			array( 'clinica-home' ),
			clinica_asset_version( 'assets/css/clinic-enterprise.css' )
		);
	}

	// Blocos de painel e gráficos partilhados (classes .db-* / .gf-*) e a dica
	// dos gráficos. Depois da camada enterprise, de que herda os tokens --cl-*.
	if ( $e_pmedico || $e_gestao || $e_recepcao || $e_executivo ) {
		wp_enqueue_style(
			'clinica-dashboard',
			CLINICA_URI . '/assets/css/dashboard.css',
			array( 'clinica-enterprise' ),
			clinica_asset_version( 'assets/css/dashboard.css' )
		);
		// Pele de cada portal, por cima das peças partilhadas.
		$pele = $e_pmedico ? 'medico' : ( $e_gestao ? 'gestao' : ( $e_recepcao ? 'recepcao' : 'direcao' ) );
		if ( file_exists( CLINICA_DIR . '/assets/css/portais/' . $pele . '.css' ) ) {
			wp_enqueue_style(
				'clinica-portal-' . $pele,
				CLINICA_URI . '/assets/css/portais/' . $pele . '.css',
				array( 'clinica-dashboard' ),
				clinica_asset_version( 'assets/css/portais/' . $pele . '.css' )
			);
		}
		// Receção: a mesma forma "Gentelella" da Direção, por cima da sua pele.
		if ( $e_recepcao ) {
			wp_enqueue_style(
				'clinica-portal-gentelella',
				CLINICA_URI . '/assets/css/portais/direcao.css',
				array( 'clinica-portal-recepcao' ),
				clinica_asset_version( 'assets/css/portais/direcao.css' )
			);
		}
		// Direção e Receção: animações com Motion (entradas, gráficos, mostradores, 3D).
		if ( $e_executivo || $e_recepcao ) {
			wp_enqueue_script( 'motion', CLINICA_URI . '/assets/js/vendor/motion-11.18.2.js', array(), '11.18.2', array( 'in_footer' => true, 'strategy' => 'defer' ) );
			wp_enqueue_script(
				'clinica-direcao',
				CLINICA_URI . '/assets/js/direcao.js',
				array( 'motion' ),
				clinica_asset_version( 'assets/js/direcao.js' ),
				array( 'in_footer' => true, 'strategy' => 'defer' )
			);
		}
		// Receção: a Caixa (palco animado do método de pagamento).
		if ( $e_recepcao ) {
			wp_enqueue_script(
				'clinica-caixa',
				CLINICA_URI . '/assets/js/caixa.js',
				array( 'motion' ),
				clinica_asset_version( 'assets/js/caixa.js' ),
				array( 'in_footer' => true, 'strategy' => 'defer' )
			);
		}
		// Finanças da gestão: animações com Motion (Framer Motion em JS puro).
		if ( $e_gestao ) {
			wp_enqueue_script( 'motion', CLINICA_URI . '/assets/js/vendor/motion-11.18.2.js', array(), '11.18.2', array( 'in_footer' => true, 'strategy' => 'defer' ) );
			wp_enqueue_script(
				'clinica-financas',
				CLINICA_URI . '/assets/js/financas.js',
				array( 'motion' ),
				clinica_asset_version( 'assets/js/financas.js' ),
				array( 'in_footer' => true, 'strategy' => 'defer' )
			);
		}
		wp_enqueue_script(
			'clinica-graficos',
			CLINICA_URI . '/assets/js/graficos.js',
			array(),
			clinica_asset_version( 'assets/js/graficos.js' ),
			array( 'in_footer' => true, 'strategy' => 'defer' )
		);
	}
} );
