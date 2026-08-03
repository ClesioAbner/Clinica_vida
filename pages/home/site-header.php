<?php
/**
 * Homepage · Cabeçalho fixo (logo + navegação + ações).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

// URL de autenticação (funcionalidade a sério por trás do design).
$url_entrar = home_url( '/entrar/' );

// Ligações da navegação apontam para a HOMEPAGE + âncora (URLs absolutos),
// para funcionarem a partir de qualquer página (ex.: /especialidades/).
$url_esp = home_url( '/especialidades/' );
$is_esp  = is_page_template( 'page-especialidades.php' );

$url_med = home_url( '/medicos/' );
$is_med  = is_page_template( 'page-medicos.php' );

$url_sob = home_url( '/sobre/' );
$is_sob  = is_page_template( 'page-sobre.php' );

$url_con = home_url( '/contacto/' );
$is_con  = is_page_template( 'page-contacto.php' );

$nav = array(
	home_url( '/' ) => 'Home',
	$url_esp        => 'Especialidades',
	$url_med        => 'Médicos',
	$url_sob        => 'Sobre Nós',
	$url_con        => 'Contacto',
);
?>
<a class="hp-skip" href="#conteudo">Saltar para o conteúdo</a>

<header class="hp-header" data-screen="Header">
	<div class="hp-header__inner">

		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hp-logo">
			<img class="hp-logo__img" src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo1.png' ); ?>"
			     alt="Clínica Vida — cuidar é viver melhor">
		</a>

		<nav class="hp-nav" aria-label="Navegação principal">
			<?php foreach ( $nav as $href => $label ) :
				// Cada página marca o seu link como ativo; "Home" ativo na homepage.
				$ativo = ( $href === $url_esp && $is_esp )
					|| ( $href === $url_med && $is_med )
					|| ( $href === $url_sob && $is_sob )
					|| ( $href === $url_con && $is_con )
					|| ( $href === home_url( '/' ) && is_front_page() );
			?>
				<a class="hp-nav__link<?php echo $ativo ? ' is-active' : ''; ?>"
				   href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="hp-header__actions">
			<?php if ( is_user_logged_in() ) : ?>
				<?php
				$u_obj  = wp_get_current_user();
				$u_foto = function_exists( 'clinica_avatar_url' ) ? clinica_avatar_url( $u_obj->ID, 72 ) : get_avatar_url( $u_obj->ID );
				?>
				<a class="hp-account" href="<?php echo esc_url( function_exists( 'clinica_painel_do_utilizador' ) ? clinica_painel_do_utilizador() : home_url( '/conta/' ) ); ?>" title="O meu painel">
					<img class="hp-account__img" src="<?php echo esc_url( $u_foto ); ?>" alt="<?php echo esc_attr( $u_obj->display_name ); ?>">
					<span class="hp-account__name"><?php echo esc_html( $u_obj->display_name ); ?></span>
				</a>
				<a class="hp-nav__link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Sair</a>
			<?php else : ?>
				<a class="hp-btn hp-btn--ghost" href="<?php echo esc_url( $url_entrar ); ?>">Entrar</a>
			<?php endif; ?>
		</div>

	</div>
</header>
