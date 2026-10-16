<?php
/**
 * Template Name: Clínica — Minha Conta
 *
 * Área do utente: perfil com foto, CRUD das suas consultas, respostas do médico
 * e mensagens de contacto enviadas. Tudo ligado à BD.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/entrar/' ) );
	exit;
}

$user_id     = get_current_user_id();
$paciente_id = Clinica_Repo::ensure_paciente( $user_id );

// Controladores (antes do output): cancelar/apagar consultas + guardar perfil.
clinica_controller_cliente( $paciente_id );
clinica_controller_perfil( $paciente_id, $user_id );

get_header();

$user      = wp_get_current_user();
$p         = Clinica_Repo::paciente_dados( $paciente_id );
$consultas = Clinica_Repo::consultas_do_paciente( $paciente_id );
$mensagens = clinica_mensagens_do_email( $user->user_email );
$avatar    = clinica_avatar_url( $user_id, 220 );

$nasc  = $p['data_nascimento'] ?? '';
$idade = null;
if ( $nasc && '0000-00-00' !== $nasc ) {
	try { $idade = ( new DateTime( $nasc ) )->diff( new DateTime( 'now' ) )->y; } catch ( Exception $e ) {}
}

$labels    = array( 'pendente' => 'Pendente', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada', 'realizada' => 'Realizada', 'faltou' => 'Faltou' );
$respostas = array_filter( $consultas, static function ( $c ) { return 'pendente' !== $c['status']; } );

$fmt = static function ( $iso ) { return date_i18n( 'd/m/Y · H:i', strtotime( $iso ) ); };
?>
<div class="hp">
	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo" class="ac">
		<div class="ac-container">

			<?php clinica_render_flash(); ?>

			<!-- Perfil -->
			<details class="ac-profile">
				<summary class="ac-profile__head">
					<span class="ac-profile__avatar">
						<img src="<?php echo esc_url( $avatar ); ?>" alt="Foto de perfil de <?php echo esc_attr( $user->display_name ); ?>">
					</span>
					<span class="ac-profile__info">
						<span class="ac-profile__name"><?php echo esc_html( $user->display_name ); ?></span>
						<span class="ac-profile__meta">
							<?php echo esc_html( $user->user_email ); ?>
							<?php if ( ! empty( $p['telefone'] ) ) : ?><i>·</i> <?php echo esc_html( $p['telefone'] ); ?><?php endif; ?>
							<?php if ( null !== $idade ) : ?><i>·</i> <?php echo (int) $idade; ?> anos<?php endif; ?>
						</span>
					</span>
					<span class="ac-profile__edit">Editar perfil</span>
				</summary>

				<form method="post" enctype="multipart/form-data" class="ac-editform">
					<?php wp_nonce_field( 'perfil_save', 'perfil_nonce' ); ?>
					<input type="hidden" name="perfil_action" value="1">
					<div class="ac-editgrid">
						<label class="ac-field"><span>Nome</span><input type="text" name="nome" value="<?php echo esc_attr( $user->display_name ); ?>"></label>
						<label class="ac-field"><span>Telefone</span><input type="tel" name="telefone" value="<?php echo esc_attr( $p['telefone'] ?? '' ); ?>" placeholder="+258 …"></label>
						<label class="ac-field"><span>Data de nascimento</span><input type="date" name="data_nascimento" value="<?php echo esc_attr( $nasc ? substr( $nasc, 0, 10 ) : '' ); ?>" max="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>"></label>
						<label class="ac-field"><span>Foto de perfil</span><input type="file" name="foto" accept="image/png, image/jpeg"></label>
					</div>
					<button type="submit" class="ac-btn ac-btn--primary">Guardar alterações</button>
				</form>
			</details>

			<!-- Consultas (CRUD) -->
			<section class="ac-card">
				<div class="ac-card__head">
					<h2>As minhas consultas</h2>
					<a class="ac-btn ac-btn--primary" href="<?php echo esc_url( home_url( '/marcar/' ) ); ?>">Nova consulta</a>
				</div>

				<?php if ( $consultas ) : ?>
					<div class="ac-list">
						<?php foreach ( $consultas as $c ) :
							$st       = $c['status'];
							$editavel = ( 'pendente' === $st );
							$cancel   = in_array( $st, array( 'pendente', 'confirmada' ), true );
							$remover  = in_array( $st, array( 'cancelada', 'realizada', 'faltou' ), true );
							$del_url  = wp_nonce_url( add_query_arg( array( 'acao' => 'apagar', 'id' => $c['id'] ), get_permalink() ), 'cl_delete_' . $c['id'] );
						?>
							<article class="ac-item">
								<div class="ac-item__main">
									<span class="ac-pill ac-pill--<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $labels[ $st ] ?? $st ); ?></span>
									<div class="ac-item__title"><?php echo esc_html( $c['esp_nome'] ); ?> · <?php echo esc_html( clinica_nome_medico( $c['med_nome'] ) ); ?></div>
									<div class="ac-item__sub"><?php echo esc_html( $fmt( $c['inicio'] ) ); ?> · <?php echo (int) $c['duracao_min']; ?> min</div>
									<?php if ( ! empty( $c['observacoes'] ) ) : ?><p class="ac-item__note"><?php echo esc_html( $c['observacoes'] ); ?></p><?php endif; ?>
								</div>
								<div class="ac-item__actions">
									<?php if ( $editavel ) : ?>
										<a class="ac-act" href="<?php echo esc_url( add_query_arg( 'editar', $c['id'], home_url( '/marcar/' ) ) ); ?>">Editar</a>
									<?php endif; ?>
									<?php if ( $cancel ) : ?>
										<form method="post" class="ac-inline" onsubmit="return confirm('Cancelar esta consulta?');">
											<?php wp_nonce_field( 'cl_cancel', 'cl_cancel_nonce' ); ?>
											<input type="hidden" name="cl_cancelar" value="1">
											<input type="hidden" name="consulta_id" value="<?php echo esc_attr( $c['id'] ); ?>">
											<button type="submit" class="ac-act ac-act--danger">Cancelar</button>
										</form>
									<?php endif; ?>
									<?php if ( $remover ) : ?>
										<a class="ac-act" href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('Remover do histórico?');">Remover</a>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="ac-empty">Ainda não tem consultas. Use <strong>Nova consulta</strong> para marcar a primeira.</p>
				<?php endif; ?>
			</section>

			<!-- Respostas do médico -->
			<section class="ac-card">
				<div class="ac-card__head"><h2>Respostas do médico</h2></div>
				<?php if ( $respostas ) : ?>
					<div class="ac-list">
						<?php foreach ( $respostas as $c ) : $st = $c['status']; ?>
							<article class="ac-item">
								<div class="ac-item__main">
									<span class="ac-pill ac-pill--<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $labels[ $st ] ?? $st ); ?></span>
									<div class="ac-item__title"><?php echo esc_html( $c['esp_nome'] ); ?> · <?php echo esc_html( clinica_nome_medico( $c['med_nome'] ) ); ?></div>
									<div class="ac-item__sub"><?php echo esc_html( $fmt( $c['inicio'] ) ); ?></div>
									<?php if ( 'cancelada' === $st && ! empty( $c['motivo_cancelamento'] ) ) : ?>
										<p class="ac-item__note">Motivo: <?php echo esc_html( $c['motivo_cancelamento'] ); ?></p>
									<?php endif; ?>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="ac-empty">Sem respostas do médico por agora. As decisões das suas marcações aparecem aqui.</p>
				<?php endif; ?>
			</section>

			<!-- Mensagens enviadas -->
			<section class="ac-card">
				<div class="ac-card__head"><h2>Mensagens enviadas</h2></div>
				<?php if ( $mensagens ) : ?>
					<div class="ac-list">
						<?php foreach ( $mensagens as $m ) : ?>
							<article class="ac-item">
								<div class="ac-item__main">
									<div class="ac-item__title"><?php echo esc_html( $m['assunto'] ?: 'Mensagem de contacto' ); ?></div>
									<div class="ac-item__sub"><?php echo esc_html( $fmt( $m['criado_em'] ) ); ?> · <?php echo $m['lido'] ? 'Lida' : 'Enviada'; ?></div>
									<p class="ac-item__note"><?php echo esc_html( wp_trim_words( $m['mensagem'], 30 ) ); ?></p>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="ac-empty">Ainda não enviou mensagens. Fale connosco pela página de <a href="<?php echo esc_url( home_url( '/contacto/' ) ); ?>">Contacto</a>.</p>
				<?php endif; ?>
			</section>

		</div>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>
</div>
<?php
get_footer();
