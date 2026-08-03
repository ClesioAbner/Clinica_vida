<?php
/**
 * Médicos · Faixa editorial — a forma de trabalhar da equipa (sem ícones/badges).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$valores = array(
	array(
		'rotulo' => 'Princípio 01',
		'titulo' => 'Tempo para ouvir',
		'texto'  => 'As consultas têm a duração necessária para compreender a sua história. Um bom diagnóstico começa por uma boa conversa, sem pressa.',
	),
	array(
		'rotulo' => 'Princípio 02',
		'titulo' => 'Decisões partilhadas',
		'texto'  => 'Explicamos as opções em linguagem simples e decidimos consigo. É o seu corpo e a sua vida  a última palavra é sempre sua.',
	),
	array(
		'rotulo' => 'Princípio 03',
		'titulo' => 'Acompanhamento contínuo',
		'texto'  => 'Quando é preciso mais do que uma área, os nossos médicos falam entre si e coordenam um plano único, para não andar de porta em porta.',
	),
);
?>
<section class="med-valores" data-screen="MedValores">
	<div class="med-container">

		<div class="med-valores__head">
			<div class="med-eyebrow">A Nossa Forma de Cuidar</div>
			<h2 class="med-valores__title">O que orienta cada consulta</h2>
		</div>

		<div class="med-valores__grid">
			<?php foreach ( $valores as $v ) : ?>
				<div class="med-valor">
					<div class="med-valor__rotulo"><?php echo esc_html( $v['rotulo'] ); ?></div>
					<h3 class="med-valor__titulo"><?php echo esc_html( $v['titulo'] ); ?></h3>
					<p class="med-valor__texto"><?php echo esc_html( $v['texto'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>

	</div>
</section>
