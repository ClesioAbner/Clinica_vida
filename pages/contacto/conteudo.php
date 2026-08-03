<?php
/**
 * Contacto · Hero (texto + img1 à direita) + informação + formulário (backend real).
 * Dados de contacto: fonte única Clinica_Homepage::contacto().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$c      = Clinica_Homepage::contacto();
$morada = implode( ', ', $c['morada'] );
$tel_no = preg_replace( '/[^+\d]/', '', $c['telefone'] );

$info = array(
	array( 'rotulo' => 'Morada',   'valor' => esc_html( $morada ) ),
	array( 'rotulo' => 'Telefone', 'valor' => '<a href="tel:' . esc_attr( $tel_no ) . '">' . esc_html( $c['telefone'] ) . '</a>' ),
	array( 'rotulo' => 'Email',    'valor' => '<a href="mailto:' . esc_attr( $c['email'] ) . '">' . esc_html( $c['email'] ) . '</a>' ),
	array( 'rotulo' => 'Horário',  'valor' => esc_html( $c['horario'] ) ),
);
?>
<section class="con-hero" data-screen="ContactoHero">
	<div class="con-hero__inner">

		<div class="con-hero__text">
			<div class="con-eyebrow">Contacto</div>
			<h1 class="con-hero__title">Estamos aqui para si</h1>
			<p class="con-hero__lead">Marcar uma consulta, esclarecer uma dúvida ou deixar uma sugestão  seja qual for o motivo, a nossa equipa ouve-o com atenção e responde no mesmo dia útil.</p>

			<ul class="con-hero__facts">
				<li>Resposta no mesmo dia útil</li>
				<li>Atendimento próximo e humano</li>
				<li>Em Sommerschield, no centro de Maputo</li>
			</ul>

			<a class="con-hero__anchor" href="#falar">Enviar mensagem
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline></svg>
			</a>
		</div>

		<div class="con-hero__media">
			<div class="con-hero__photo">
				<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/img1.png' ); ?>" alt="Médico da Clínica Vida" loading="eager">
			</div>
		</div>

	</div>
</section>

<section id="falar" class="con-main">
	<div class="con-container">
		<div class="con-grid">

			<aside class="con-info-col">
				<div class="con-eyebrow">Onde nos encontrar</div>
				<h2 class="con-info-col__title">Estamos em Sommerschield, Maputo</h2>
				<dl class="con-info">
					<?php foreach ( $info as $i ) : ?>
						<div class="con-info__row">
							<dt><?php echo esc_html( $i['rotulo'] ); ?></dt>
							<dd><?php echo wp_kses_post( $i['valor'] ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			</aside>

			<div class="con-formcard">
				<?php clinica_render_flash(); ?>

				<h2 class="con-formcard__title">Envie-nos uma mensagem</h2>
				<p class="con-formcard__sub">Preencha os campos abaixo e entramos em contacto consigo o mais breve possível.</p>

				<form method="post" class="con-form" novalidate>
					<?php wp_nonce_field( 'con_send', 'con_nonce' ); ?>
					<input type="hidden" name="con_action" value="1">

					<div class="con-fields">
						<div class="con-field">
							<label for="con-nome">Nome completo</label>
							<input type="text" id="con-nome" name="nome" required>
						</div>
						<div class="con-field">
							<label for="con-email">Email</label>
							<input type="email" id="con-email" name="email" required>
						</div>
						<div class="con-field">
							<label for="con-tel">Telefone <span class="con-opt">(opcional)</span></label>
							<input type="tel" id="con-tel" name="telefone" placeholder="+258 …">
						</div>
						<div class="con-field">
							<label for="con-assunto">Assunto <span class="con-opt">(opcional)</span></label>
							<input type="text" id="con-assunto" name="assunto">
						</div>
						<div class="con-field con-field--full">
							<label for="con-msg">Mensagem</label>
							<textarea id="con-msg" name="mensagem" rows="6" required placeholder="Como podemos ajudar?"></textarea>
						</div>
					</div>

					<div class="con-form__foot">
						<p class="con-note">Ao enviar, os seus dados são usados apenas para lhe responder.</p>
						<button type="submit" class="con-btn con-btn--primary">Enviar mensagem</button>
					</div>
				</form>
			</div>

		</div>
	</div>
</section>
