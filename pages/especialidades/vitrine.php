<?php
/**
 * Especialidades · Vitrine — índice de atalhos + lista editorial com uma
 * fotografia fixa (sticky) à esquerda que muda para a especialidade que está
 * a ser lida à direita (animacoes.js §9). Desenho diferente da grelha da
 * homepage, com a mesma linguagem (serifa, menta, etiqueta recortada).
 *
 * Sem JS: a fotografia mostra a 1.ª especialidade e todos os textos ficam
 * acesos; no telemóvel cada especialidade traz a sua própria fotografia.
 * Honestidade com os dados: nº de médicos da BD; sem médicos → "Pedir
 * informação" para /contacto/ (marcar online falharia).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$especialidades = Clinica_Repo::especialidades();
if ( ! $especialidades ) {
	return;
}
$contagem = Clinica_Repo::contar_medicos_por_especialidade();
$dir      = CLINICA_DIR . '/assets/img/especialidades/';
$url      = CLINICA_URI . '/assets/img/especialidades/';
$generica = CLINICA_URI . '/assets/img/inicio/hero-cartao.webp';

// Uma linha por especialidade, já com tudo o que a vista precisa.
$itens = array();
foreach ( $especialidades as $i => $e ) {
	$slug    = sanitize_title( $e['nome'] );
	$total   = $contagem[ (int) $e['id'] ] ?? 0;
	$itens[] = array(
		'n'     => str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ),
		'slug'  => $slug,
		'nome'  => $e['nome'],
		'desc'  => $e['descricao'],
		'foto'  => file_exists( $dir . $slug . '.webp' ) ? $url . $slug . '.webp' : $generica,
		'meta'  => $total > 0 ? sprintf( _n( '%d médico disponível', '%d médicos disponíveis', $total, 'clinica' ), $total ) : 'Marcação por contacto',
		'acao'  => $total > 0
			? array( add_query_arg( 'especialidade', (int) $e['id'], home_url( '/marcar/' ) ), 'Marcar consulta' )
			: array( home_url( '/contacto/' ), 'Pedir informação' ),
	);
}
$seta = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
?>
<section id="lista-especialidades" class="cv-sec esp-vitrine" aria-labelledby="esp-vitrine-titulo">
	<div class="hp-container">

		<div class="esp-vitrine__topo" data-revelar>
			<div class="cv-head cv-head--esq">
				<p class="cv-eyebrow">As nossas áreas</p>
				<h2 id="esp-vitrine-titulo" class="cv-h2"><?php echo esc_html( sprintf( _n( '%d especialidade, uma só equipa', '%d especialidades, uma só equipa', count( $itens ), 'clinica' ), count( $itens ) ) ); ?></h2>
			</div>
			<nav class="esp-indice" aria-label="Ir para a especialidade">
				<?php foreach ( $itens as $it ) : ?>
					<a href="#esp-<?php echo esc_attr( $it['slug'] ); ?>"><?php echo esc_html( $it['nome'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		</div>

		<div class="esp-vitrine__grelha" data-vitrine>
			<div class="esp-vitrine__media" aria-hidden="true">
				<div class="esp-vitrine__moldura">
					<?php foreach ( $itens as $i => $it ) : ?>
						<img class="esp-vitrine__foto<?php echo 0 === $i ? ' is-ativa' : ''; ?>" src="<?php echo esc_url( $it['foto'] ); ?>" width="800" height="560" alt="" loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>" decoding="async" data-vitrine-foto>
					<?php endforeach; ?>
					<span class="cv-tab esp-vitrine__tab">
						<strong data-vitrine-nome><?php echo esc_html( $itens[0]['nome'] ); ?></strong>
						<span data-vitrine-meta><?php echo esc_html( $itens[0]['meta'] ); ?></span>
					</span>
				</div>
			</div>

			<ol class="esp-vitrine__lista" data-deslizar>
				<?php foreach ( $itens as $i => $it ) : ?>
					<li class="esp-item" id="esp-<?php echo esc_attr( $it['slug'] ); ?>" data-vitrine-item data-nome="<?php echo esc_attr( $it['nome'] ); ?>" data-meta="<?php echo esc_attr( $it['meta'] ); ?>">
						<img class="esp-item__foto" src="<?php echo esc_url( $it['foto'] ); ?>" width="800" height="560" alt="" loading="lazy" decoding="async">
						<span class="esp-item__num" aria-hidden="true"><?php echo esc_html( $it['n'] ); ?></span>
						<h3 class="esp-item__nome"><?php echo esc_html( $it['nome'] ); ?></h3>
						<p class="esp-item__desc"><?php echo esc_html( $it['desc'] ); ?></p>
						<p class="esp-item__meta"><?php echo esc_html( $it['meta'] ); ?></p>
						<a class="cv-btn cv-btn--escuro esp-item__acao" href="<?php echo esc_url( $it['acao'][0] ); ?>">
							<?php echo esc_html( $it['acao'][1] ); ?><span class="hp-sr-only">: <?php echo esc_html( $it['nome'] ); ?></span>
							<?php echo $seta; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>

	</div>
</section>
