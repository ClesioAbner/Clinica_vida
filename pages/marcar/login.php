<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;
?>
<section class="mk-section">
	<div class="mk-login">
		<h2>Área reservada</h2>
		<p>Para marcar a sua consulta, inicie sessão na sua conta. Ainda não tem conta? Crie uma em segundos.</p>
		<a class="mk-btn mk-btn--primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Iniciar sessão</a>
	</div>
</section>
