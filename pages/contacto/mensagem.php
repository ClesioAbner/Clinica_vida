<?php
/**
 * Contacto · Fale connosco — bloco editorial (.cv-editorial): à esquerda o
 * convite e os atalhos diretos (ligar / email); à direita o formulário real
 * num cartão petróleo (.cv-reserva--solido, os mesmos campos de vidro).
 *
 * Campos e nonce são os que clinica_controller_contacto() (inc/contacto-site.php)
 * espera; depois de enviar volta a #contacto com a mensagem de estado.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$c         = Clinica_Homepage::contacto();
$telefones = Clinica_Homepage::telefones();
?>
<section class="cv-sec cv-editorial" aria-labelledby="con-mensagem-titulo">
	<div class="hp-container cv-editorial__grelha">

		<div>
			<div class="cv-head cv-head--esq" data-revelar>
				<p class="cv-eyebrow">Fale connosco</p>
				<h2 id="con-mensagem-titulo" class="cv-h2">Uma dúvida, uma sugestão ou ajuda para marcar</h2>
			</div>
			<div class="cv-editorial__texto" data-revelar-grupo>
				<p>Deixe a sua mensagem e a nossa equipa responde-lhe assim que possível. Se preferir, ligue-nos no horário de atendimento.</p>
				<p class="cv-atalhos">
					<?php if ( $telefones ) : ?>
						<a class="cv-btn cv-btn--escuro" href="<?php echo esc_attr( Clinica_Homepage::tel_href( $telefones[0] ) ); ?>">Ligar <?php echo esc_html( $telefones[0] ); ?></a>
					<?php endif; ?>
					<a class="cv-btn cv-btn--sombra" href="mailto:<?php echo esc_attr( $c['email'] ); ?>"><?php echo esc_html( $c['email'] ); ?></a>
				</p>
			</div>
		</div>

		<div id="contacto" class="cv-reserva cv-reserva--solido" data-revelar="0.1">
			<?php clinica_render_flash(); ?>

			<form method="post" class="cv-reserva__form" novalidate>
				<?php wp_nonce_field( 'con_send', 'con_nonce' ); ?>
				<input type="hidden" name="con_action" value="1">

				<p class="cv-reserva__titulo">Envie-nos uma mensagem</p>

				<div class="cv-campo">
					<label for="con-nome">Nome completo</label>
					<input type="text" id="con-nome" name="nome" autocomplete="name" required>
				</div>
				<div class="cv-campo">
					<label for="con-email">Email</label>
					<input type="email" id="con-email" name="email" autocomplete="email" required>
				</div>
				<div class="cv-campo">
					<label for="con-tel">Telefone (opcional)</label>
					<input type="tel" id="con-tel" name="telefone" placeholder="+258 …" autocomplete="tel">
				</div>
				<div class="cv-campo">
					<label for="con-assunto">Assunto (opcional)</label>
					<input type="text" id="con-assunto" name="assunto">
				</div>
				<div class="cv-campo cv-campo--largo">
					<label for="con-msg">Mensagem</label>
					<textarea id="con-msg" name="mensagem" rows="5" required placeholder="Como podemos ajudar?"></textarea>
				</div>

				<button type="submit" class="cv-btn cv-reserva__enviar">Enviar mensagem</button>
				<p class="cv-reserva__nota">Os seus dados são usados apenas para lhe responder.</p>
			</form>
		</div>

	</div>
</section>
