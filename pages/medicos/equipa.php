<?php
/**
 * Médicos · Equipa — o bloco "our team" da referência (cabeçalho centrado +
 * cartões .cv-medico com a etiqueta recortada), aqui em grelha com a equipa
 * toda. Cada retrato abre o perfil completo num painel (Popover API nativa:
 * funciona sem JS, fecha com Esc e com clique fora, e o browser trata do
 * aria-expanded).
 *
 * "Marcar" leva à especialidade certa na BD; se ela ainda não tiver médicos
 * na BD (ou não existir lá), leva a /contacto/ — marcar online falharia.
 *
 * Dados: Clinica_Homepage::equipa() (perfis públicos — substituir pelos reais).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$equipa = Clinica_Homepage::equipa();
if ( ! $equipa ) {
	return;
}

// Especialidades da BD por slug → id e nº de médicos (para o "Marcar").
$na_bd    = array();
$contagem = Clinica_Repo::contar_medicos_por_especialidade();
foreach ( Clinica_Repo::especialidades() as $e ) {
	$na_bd[ sanitize_title( $e['nome'] ) ] = array( (int) $e['id'], $contagem[ (int) $e['id'] ] ?? 0 );
}

$fechar = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>';
$mais   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg>';
?>
<section id="equipa" class="cv-sec med-equipa" aria-labelledby="med-equipa-titulo">
	<div class="hp-container">

		<div class="cv-head" data-revelar>
			<p class="cv-eyebrow">A nossa equipa</p>
			<h2 id="med-equipa-titulo" class="cv-h2">Conheça quem vai cuidar de si</h2>
		</div>

		<ul class="med-grelha" data-revelar-grupo>
			<?php foreach ( $equipa as $i => $m ) :
				$slug   = sanitize_title( $m['especialidade'] );
				$id     = 'perfil-' . ( $i + 1 );
				$bd     = $na_bd[ $slug ] ?? null;
				$marcar = $bd && $bd[1] > 0
					? array( add_query_arg( 'especialidade', $bd[0], home_url( '/marcar/' ) ), 'Marcar consulta' )
					: array( home_url( '/contacto/' ), 'Pedir informação' );
			?>
				<li>
					<button class="cv-medico" type="button" popovertarget="<?php echo esc_attr( $id ); ?>">
						<img src="<?php echo esc_url( $m['foto'] ); ?>" width="600" height="800" alt="" loading="lazy" decoding="async">
						<span class="med-mais" aria-hidden="true"><?php echo $mais; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<span class="cv-tab">
							<strong><?php echo esc_html( $m['nome'] ); ?></strong>
							<span><?php echo esc_html( $m['especialidade'] ); ?></span>
						</span>
						<span class="hp-sr-only">Ver perfil</span>
					</button>

					<div class="med-perfil" id="<?php echo esc_attr( $id ); ?>" popover role="dialog" aria-labelledby="<?php echo esc_attr( $id ); ?>-nome">
						<img class="med-perfil__foto" src="<?php echo esc_url( $m['foto'] ); ?>" width="600" height="800" alt="" loading="lazy" decoding="async">
						<div class="med-perfil__corpo">
							<button class="med-perfil__fechar" type="button" popovertarget="<?php echo esc_attr( $id ); ?>" popovertargetaction="hide" aria-label="Fechar perfil"><?php echo $fechar; // phpcs:ignore WordPress.Security.EscapeOutput ?></button>
							<p class="cv-eyebrow"><?php echo esc_html( $m['especialidade'] ); ?></p>
							<h3 id="<?php echo esc_attr( $id ); ?>-nome" class="med-perfil__nome"><?php echo esc_html( $m['nome'] ); ?></h3>
							<p class="med-perfil__cargo"><?php echo esc_html( $m['cargo'] ); ?> · <?php echo esc_html( $m['experiencia'] ); ?> de experiência</p>
							<p class="med-perfil__desc"><?php echo esc_html( $m['descricao'] ); ?></p>
							<dl class="med-perfil__dados">
								<div><dt>Formação</dt><dd><?php echo esc_html( implode( ' · ', $m['formacao'] ) ); ?></dd></div>
								<div><dt>Idiomas</dt><dd><?php echo esc_html( implode( ', ', $m['idiomas'] ) ); ?></dd></div>
							</dl>
							<ul class="med-perfil__focos" aria-label="Áreas de foco">
								<?php foreach ( $m['focos'] as $f ) : ?>
									<li><?php echo esc_html( $f ); ?></li>
								<?php endforeach; ?>
							</ul>
							<a class="cv-btn cv-btn--escuro" href="<?php echo esc_url( $marcar[0] ); ?>"><?php echo esc_html( $marcar[1] ); ?></a>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>

	</div>
</section>
