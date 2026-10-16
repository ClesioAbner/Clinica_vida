<?php
/**
 * Início · Manifesto — faixa petróleo com um parágrafo em serifa grande que
 * "acende" palavra a palavra com o scroll, e uma colagem de fotografias de
 * famílias à volta do título.
 *
 * Sem JS (ou com movimento reduzido) o texto aparece todo aceso: o efeito é só
 * melhoria progressiva (assets/js/main.js → data-acender).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$texto = 'Cuidar da saúde não devia significar longas esperas nem especialistas espalhados pela cidade. Por isso reunimos várias especialidades sob o mesmo teto, para que a sua família tenha o cuidado que merece, sem voltas.';

$img = CLINICA_URI . '/assets/img/inicio/';
// Posições da colagem (em % da área) e velocidade de paralaxe de cada foto.
$colagem = array(
	array( 'f' => 'colagem-1.webp', 'x' => 22, 'y' => 4,  'v' => .10 ),
	array( 'f' => 'colagem-2.webp', 'x' => 50, 'y' => 0,  'v' => .18 ),
	array( 'f' => 'colagem-3.webp', 'x' => 70, 'y' => 12, 'v' => .08 ),
	array( 'f' => 'colagem-4.webp', 'x' => 14, 'y' => 42, 'v' => .16 ),
	array( 'f' => 'colagem-5.webp', 'x' => 76, 'y' => 46, 'v' => .12 ),
	array( 'f' => 'colagem-6.webp', 'x' => 34, 'y' => 74, 'v' => .20 ),
	array( 'f' => 'colagem-7.webp', 'x' => 60, 'y' => 72, 'v' => .06 ),
);
?>
<section class="ini-manifesto" aria-labelledby="ini-manifesto-titulo">
	<div class="hp-container">
		<p class="ini-manifesto__texto" data-acender>
			<?php
			// Cada palavra num <span> para o efeito; o leitor de ecrã lê a frase inteira.
			foreach ( preg_split( '/\s+/u', $texto ) as $palavra ) {
				echo '<span class="ini-palavra">' . esc_html( $palavra ) . '</span> ';
			}
			?>
		</p>
	</div>

	<div class="ini-colagem" data-colagem>
		<?php foreach ( $colagem as $i => $f ) : ?>
			<span class="ini-colagem__foto" style="--x:<?php echo (int) $f['x']; ?>%;--y:<?php echo (int) $f['y']; ?>%" data-paralaxe="<?php echo esc_attr( (string) $f['v'] ); ?>" aria-hidden="true">
				<img src="<?php echo esc_url( $img . $f['f'] ); ?>" width="300" height="300" alt="" loading="lazy" decoding="async">
			</span>
		<?php endforeach; ?>
		<h2 id="ini-manifesto-titulo" class="ini-colagem__titulo" data-revelar>Conheça a Clínica Vida, cuidado que parece família</h2>
	</div>
</section>
