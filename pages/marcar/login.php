<?php
/**
 * Marcar · Sem sessão — convite a entrar, no painel de vidro do hero.
 *
 * Depois de entrar, volta a /marcar/ COM as escolhas da marcação rápida
 * (homepage, especialidades): com get_permalink() simples perdiam-se no login.
 * Só passam os 3 parâmetros conhecidos, já sanitizados.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$volta = add_query_arg(
	array_filter(
		array(
			'especialidade' => Clinica_Input::inteiro( $_GET, 'especialidade' ) ?: null,
			'medico'        => Clinica_Input::inteiro( $_GET, 'medico' ) ?: null,
			'data'          => Clinica_Input::data( $_GET, 'data' ) ?: null,
		)
	),
	get_permalink()
);
$telefones = Clinica_Homepage::telefones();
?>
<div class="cv-reserva__form">
	<p class="cv-reserva__titulo">Entre para marcar</p>
	<p class="cv-reserva__nota">A marcação fica na sua conta, para a poder editar ou cancelar. Ainda não tem conta? Crie uma em segundos. Depois de entrar, volta logo a esta página.</p>
	<div class="cv-reserva__acoes">
		<a class="cv-btn" href="<?php echo esc_url( wp_login_url( $volta ) ); ?>">Entrar ou criar conta</a>
		<?php if ( $telefones ) : ?>
			<a class="cv-btn cv-btn--vidro" href="<?php echo esc_attr( Clinica_Homepage::tel_href( $telefones[0] ) ); ?>">Prefiro ligar</a>
		<?php endif; ?>
	</div>
</div>
