<?php
/**
 * Template Name: Clínica — Entrar
 *
 * Autenticação em ecrã dividido: formulário à esquerda, fotografia à direita.
 * Sem a navegação completa — apenas a marca (padrão senior). Backend: inc/auth.php.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

clinica_controller_auth();

// Já autenticado -> não faz sentido ficar aqui.
if ( is_user_logged_in() ) {
	wp_safe_redirect( clinica_auth_destino() );
	exit;
}

// Aba ativa (registo se veio de um erro de registo).
$aba = ( isset( $_GET['form'] ) && 'reg' === $_GET['form'] ) ? 'reg' : 'login';

get_header();
?>
<div class="hp">
	<main class="au">
		<div class="au-wrap">

			<section class="au-panel" data-aba="<?php echo esc_attr( $aba ); ?>">

				<a class="au-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Clínica Vida — início">
					<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo1.png' ); ?>" alt="Clínica Vida">
				</a>

				<div class="au-inner">

					<div class="au-tabs" role="tablist">
						<button class="au-tab" data-tab="login" type="button">Entrar</button>
						<button class="au-tab" data-tab="reg" type="button">Criar conta</button>
					</div>

					<?php clinica_render_flash(); ?>

					<!-- Iniciar sessão -->
					<form class="au-form au-form--login" method="post" novalidate>
						<?php wp_nonce_field( 'auth_login', 'auth_nonce' ); ?>
						<input type="hidden" name="auth_action" value="login">

						<h1 class="au-title">Bem-vindo de volta</h1>
						<p class="au-sub">Introduza os seus dados para continuar.</p>

						<label class="au-label" for="au-log">Email ou utilizador</label>
						<input class="au-input" id="au-log" name="log" type="text" autocomplete="username" required>

						<label class="au-label" for="au-pwd">Palavra-passe</label>
						<input class="au-input" id="au-pwd" name="pwd" type="password" autocomplete="current-password" required>

						<div class="au-row">
							<label class="au-check"><input type="checkbox" name="rememberme" value="1"> Manter sessão</label>
							<a class="au-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Esqueceu-se?</a>
						</div>

						<button class="au-btn" type="submit">Entrar</button>
						<p class="au-switch">Ainda não tem conta? <a href="#" data-goto="reg">Criar conta</a></p>
					</form>

					<!-- Criar conta -->
					<form class="au-form au-form--reg" method="post" novalidate>
						<?php wp_nonce_field( 'auth_register', 'auth_nonce' ); ?>
						<input type="hidden" name="auth_action" value="register">

						<h1 class="au-title">Criar a sua conta</h1>
						<p class="au-sub">É rápido — e fica tudo guardado com segurança.</p>

						<label class="au-label" for="au-nome">Nome completo</label>
						<input class="au-input" id="au-nome" name="nome" type="text" autocomplete="name" required>

						<label class="au-label" for="au-email">Email</label>
						<input class="au-input" id="au-email" name="email" type="email" autocomplete="email" required>

						<label class="au-label" for="au-rpwd">Palavra-passe <span class="au-opt">(mín. 6 caracteres)</span></label>
						<input class="au-input" id="au-rpwd" name="pwd" type="password" autocomplete="new-password" minlength="6" required>

						<button class="au-btn" type="submit">Criar conta</button>
						<p class="au-switch">Já tem conta? <a href="#" data-goto="login">Iniciar sessão</a></p>
					</form>

				</div>

				<a class="au-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Voltar ao site</a>

			</section>

			<aside class="au-media" aria-hidden="true">
				<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/img2.png' ); ?>" alt="">
				<div class="au-media__cover">
					<p class="au-media__text">&ldquo;Cuidamos de si com tempo, rigor e proximidade.&rdquo;</p>
					<div class="au-media__brand">Clínica Vida · Sommerschield, Maputo</div>
				</div>
			</aside>

		</div>
	</main>
</div>

<script>
( function () {
	var panel = document.querySelector( '.au-panel' );
	if ( ! panel ) { return; }
	function set( aba ) { panel.setAttribute( 'data-aba', aba ); }
	panel.querySelectorAll( '[data-tab]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () { set( b.dataset.tab ); } );
	} );
	panel.querySelectorAll( '[data-goto]' ).forEach( function ( a ) {
		a.addEventListener( 'click', function ( e ) { e.preventDefault(); set( a.dataset.goto ); } );
	} );
} )();
</script>
<?php
get_footer();
