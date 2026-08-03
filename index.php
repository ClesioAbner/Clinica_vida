<?php
/**
 * Template fallback principal.
 * Renderiza o Loop padrao do WordPress dentro do wrapper header/footer.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="site-content" class="site-content">

	<?php if ( have_posts() ) : ?>

		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
				</header>
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</article>
		<?php endwhile; ?>

		<?php the_posts_pagination(); ?>

	<?php else : ?>

		<p><?php esc_html_e( 'Nada encontrado.', 'clinica' ); ?></p>

	<?php endif; ?>

</main>

<?php
get_footer();
