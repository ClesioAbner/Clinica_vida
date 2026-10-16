<?php
/**
 * Template Name: Clínica — Entrar
 *
 * Autenticação em ecrã dividido, na linguagem da referência ClinicHub:
 * fotografia em moldura arredondada com legenda em vidro (esquerda) e o
 * formulário (direita), com a marca em pílula e o seletor Entrar / Criar
 * conta. Sem a navegação completa. Backend: inc/auth.php (campos e nonces).
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

				<?php // A mesma marca em pílula do cabeçalho público. ?>
				<a class="au-marca" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Clínica Vida, página inicial">
					<span class="au-marca__tile" aria-hidden="true"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="26" height="26" alt=""></span>
					<span aria-hidden="true">Clínica Vida</span>
				</a>

				<div class="au-inner">

					<div class="au-tabs" role="group" aria-label="Escolher entre entrar e criar conta">
						<button class="au-tab" data-tab="login" type="button" aria-pressed="<?php echo 'login' === $aba ? 'true' : 'false'; ?>">Entrar</button>
						<button class="au-tab" data-tab="reg" type="button" aria-pressed="<?php echo 'reg' === $aba ? 'true' : 'false'; ?>">Criar conta</button>
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
						<div class="au-password">
							<input class="au-input" id="au-pwd" name="pwd" type="password" autocomplete="current-password" required>
							<button class="au-password__toggle" type="button" data-password-toggle="au-pwd" aria-controls="au-pwd" aria-pressed="false">Mostrar</button>
						</div>

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
						<p class="au-sub">É rápido e fica tudo guardado com segurança.</p>

						<label class="au-label" for="au-nome">Nome completo</label>
						<input class="au-input" id="au-nome" name="nome" type="text" autocomplete="name" required>

						<label class="au-label" for="au-email">Email</label>
						<input class="au-input" id="au-email" name="email" type="email" autocomplete="email" required>

						<label class="au-label" for="au-rpwd">Palavra-passe <span class="au-opt">(mín. 6 caracteres)</span></label>
						<div class="au-password">
							<input class="au-input" id="au-rpwd" name="pwd" type="password" autocomplete="new-password" minlength="6" required>
							<button class="au-password__toggle" type="button" data-password-toggle="au-rpwd" aria-controls="au-rpwd" aria-pressed="false">Mostrar</button>
						</div>

						<button class="au-btn" type="submit">Criar conta</button>
						<p class="au-switch">Já tem conta? <a href="#" data-goto="login">Iniciar sessão</a></p>
					</form>

				</div>

				<a class="au-back" href="<?php echo esc_url( home_url( '/' ) ); ?>">&larr; Voltar ao site</a>

			</section>

			<aside class="au-media" aria-hidden="true">
				<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/login-clinica-2026-v2.webp' ); ?>" width="1023" height="1537" alt="">
				<div class="au-media__vidro">
					<p class="au-media__text">Cuidamos de si com tempo, rigor e proximidade.</p>
					<p class="au-media__local">Clínica Vida · Sommerschield, Maputo</p>
				</div>
			</aside>

		</div>
	</main>
</div>

<script>
( function () {
	var panel = document.querySelector( '.au-panel' );
	if ( ! panel ) { return; }
	function set( aba ) {
		panel.setAttribute( 'data-aba', aba );
		panel.querySelectorAll( '[data-tab]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b.dataset.tab === aba ? 'true' : 'false' );
		} );
	}
	panel.querySelectorAll( '[data-tab]' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () { set( b.dataset.tab ); } );
	} );
	panel.querySelectorAll( '[data-goto]' ).forEach( function ( a ) {
		a.addEventListener( 'click', function ( e ) { e.preventDefault(); set( a.dataset.goto ); } );
	} );
	document.querySelectorAll( '[data-password-toggle]' ).forEach( function ( button ) {
		var input = document.getElementById( button.dataset.passwordToggle );
		if ( ! input ) { return; }
		button.addEventListener( 'click', function () {
			var visible = input.type === 'text';
			input.type = visible ? 'password' : 'text';
			button.textContent = visible ? 'Mostrar' : 'Ocultar';
			button.setAttribute( 'aria-pressed', visible ? 'false' : 'true' );
		} );
	} );
} )();
</script>
<?php
get_footer();
