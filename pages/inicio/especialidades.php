<?php
/**
 * Início · Especialidades — leque de cartões inclinados, título e grelha
 * "bento": 3 fotografias de cada lado e um cartão menta ao centro.
 *
 * Dados: Clinica_Repo::especialidades() (BD). A fotografia de cada uma é
 * assets/img/inicio/esp-<slug>.webp (+ -largo.webp para as células largas);
 * especialidades sem fotografia ficam de fora desta montra (continuam todas em
 * /especialidades/).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$dir = CLINICA_DIR . '/assets/img/inicio/';
$url = CLINICA_URI . '/assets/img/inicio/';

// Ordem de montagem: as 3 primeiras à esquerda, as 3 seguintes à direita;
// a 3.ª de cada lado ocupa a célula larga.
$preferencia = array( 'cardiologia', 'dermatologia', 'ortopedia', 'neurologia', 'oftalmologia', 'pediatria' );

$com_foto = array();
foreach ( Clinica_Repo::especialidades() as $e ) {
	$slug = sanitize_title( $e['nome'] );
	if ( file_exists( $dir . 'esp-' . $slug . '.webp' ) ) {
		$com_foto[ $slug ] = $e['nome'];
	}
}
uksort( $com_foto, static function ( $a, $b ) use ( $preferencia ) {
	$ia = array_search( $a, $preferencia, true );
	$ib = array_search( $b, $preferencia, true );
	return ( false === $ia ? 99 : $ia ) <=> ( false === $ib ? 99 : $ib );
} );
$lista = array_slice( $com_foto, 0, 6, true );

if ( ! $lista ) {
	return;
}

$url_esp = home_url( '/especialidades/' );
$bento   = 6 === count( $lista );

// Um cartão de especialidade (fotografia + etiqueta recortada).
// $leque: as células pequenas são as cartas que descem do leque (animacoes.js).
$cartao = static function ( string $slug, string $nome, bool $largo = false, bool $leque = false ) use ( $url, $url_esp ): string {
	$f = 'esp-' . $slug . ( $largo ? '-largo' : '' ) . '.webp';
	return sprintf(
		'<a class="ini-esp%1$s" href="%2$s"%7$s><img src="%3$s" width="%4$d" height="%5$d" alt="" loading="lazy" decoding="async"><span class="cv-tab">%6$s</span></a>',
		$largo ? ' ini-esp--largo' : '',
		esc_url( $url_esp . '#esp-' . $slug ),
		esc_url( $url . $f ),
		$largo ? 820 : 400,
		$largo ? 410 : 420,
		esc_html( $nome ),
		$leque ? ' data-leque' : ( $largo ? ' data-revelar="0.2"' : '' )
	);
};
$slugs = array_keys( $lista );
?>
<section id="especialidades" class="cv-sec ini-especialidades" aria-labelledby="ini-esp-titulo">
	<div class="hp-container cv-leque-zona">

		<?php
		// Vagas do leque: onde as 4 fotos começam, inclinadas "na mão", antes de
		// descerem para o bento. Invisíveis; só existem com animação (componentes.css).
		?>
		<div class="cv-leque" aria-hidden="true">
			<?php foreach ( array( -9, -3, 3, 9 ) as $graus ) : ?>
				<span class="cv-leque__vaga" data-leque-vaga="<?php echo (int) $graus; ?>"></span>
			<?php endforeach; ?>
		</div>

		<div class="cv-head" data-revelar>
			<p class="cv-eyebrow">As nossas especialidades</p>
			<h2 id="ini-esp-titulo" class="cv-h2">Cuidado para cada necessidade</h2>
		</div>

		<?php if ( $bento ) : ?>
			<div class="ini-bento" data-leque-grelha data-deslizar>
				<div class="ini-bento__lado">
					<?php
					echo $cartao( $slugs[0], $lista[ $slugs[0] ], false, true ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo $cartao( $slugs[1], $lista[ $slugs[1] ], false, true ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo $cartao( $slugs[2], $lista[ $slugs[2] ], true ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</div>

				<div class="ini-bento__centro" data-revelar="0.12">
					<img class="ini-bento__marca" src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-192.webp' ); ?>" width="64" height="64" alt="">
					<p class="cv-h3">A sua saúde nas mãos de especialistas experientes</p>
					<a class="cv-btn cv-btn--sombra" href="<?php echo esc_url( home_url( '/marcar/' ) ); ?>">Marcar consulta</a>
				</div>

				<div class="ini-bento__lado">
					<?php
					echo $cartao( $slugs[3], $lista[ $slugs[3] ], false, true ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo $cartao( $slugs[4], $lista[ $slugs[4] ], false, true ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo $cartao( $slugs[5], $lista[ $slugs[5] ], true ); // phpcs:ignore WordPress.Security.EscapeOutput
					?>
				</div>
			</div>
		<?php else : ?>
			<div class="ini-bento ini-bento--simples" data-deslizar>
				<?php foreach ( $lista as $slug => $nome ) { echo $cartao( $slug, $nome ); } // phpcs:ignore WordPress.Security.EscapeOutput ?>
			</div>
		<?php endif; ?>

	</div>
</section>
