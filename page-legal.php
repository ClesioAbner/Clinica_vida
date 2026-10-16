<?php
/**
 * Template Name: Clínica — Página legal
 *
 * Termos, Privacidade e Cookies: faixa petróleo com o título e a data da
 * revisão, índice fixo à esquerda e o texto à direita. O conteúdo vem de
 * Clinica_Legal::documento() pelo slug da página.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$slug = (string) get_post_field( 'post_name', get_queried_object_id() );
$doc  = Clinica_Legal::documento( $slug );

get_header();
?>
<div class="hp hp--legal">

	<?php get_template_part( 'pages/home/site-header' ); ?>

	<main id="conteudo" class="lg">
		<?php if ( $doc ) : ?>
			<header class="lg-topo">
				<div class="hp-container lg-topo__inner">
					<nav class="cv-migalhas" aria-label="Estou em">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a>
						<span aria-hidden="true">/</span>
						<span aria-current="page"><?php echo esc_html( $doc['titulo'] ); ?></span>
					</nav>
					<h1 class="lg-topo__titulo"><?php echo esc_html( $doc['titulo'] ); ?></h1>
					<p class="lg-topo__resumo"><?php echo esc_html( $doc['resumo'] ); ?></p>
					<p class="lg-topo__data">Última revisão: <?php echo esc_html( Clinica_Legal::revisto() ); ?></p>
				</div>
			</header>

			<div class="hp-container lg-corpo">
				<aside class="lg-lado">
					<nav class="lg-indice" aria-label="Nesta página">
						<p class="lg-indice__t">Nesta página</p>
						<ol>
							<?php foreach ( $doc['seccoes'] as $s ) : ?>
								<li><a href="#<?php echo esc_attr( $s['id'] ); ?>"><?php echo esc_html( $s['titulo'] ); ?></a></li>
							<?php endforeach; ?>
						</ol>
					</nav>
					<nav class="lg-outros" aria-label="Outros documentos">
						<?php foreach ( Clinica_Legal::ligacoes() as $href => $rotulo ) : ?>
							<?php $atual = untrailingslashit( $href ) === untrailingslashit( Clinica_Legal::url( $slug ) ); ?>
							<a href="<?php echo esc_url( $href ); ?>"<?php echo $atual ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $rotulo ); ?></a>
						<?php endforeach; ?>
					</nav>
				</aside>

				<article class="lg-texto">
					<?php foreach ( $doc['seccoes'] as $i => $s ) : ?>
						<section id="<?php echo esc_attr( $s['id'] ); ?>" class="lg-sec" aria-labelledby="<?php echo esc_attr( $s['id'] ); ?>-t">
							<h2 id="<?php echo esc_attr( $s['id'] ); ?>-t"><span class="lg-sec__n"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><?php echo esc_html( $s['titulo'] ); ?></h2>
							<?php echo Clinica_Legal::html( $s['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- filtrado por wp_kses. ?>
						</section>
					<?php endforeach; ?>
				</article>
			</div>
		<?php else : ?>
			<div class="hp-container lg-corpo">
				<p>Documento não encontrado.</p>
			</div>
		<?php endif; ?>
	</main>

	<?php get_template_part( 'pages/home/site-footer' ); ?>

</div>
<?php
get_footer();
