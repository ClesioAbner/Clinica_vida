<?php
/**
 * Sobre · A nossa história + missão, visão e valores (editorial, sem badges).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$pilares = array(
	array(
		'rotulo' => 'Missão',
		'texto'  => 'Oferecer a cada pessoa que nos procura um cuidado próximo, competente e acessível, do primeiro contacto ao acompanhamento continuado.',
	),
	array(
		'rotulo' => 'Visão',
		'texto'  => 'Ser uma referência de confiança na saúde em Maputo, reconhecida pela qualidade clínica e pela forma como tratamos quem cuidamos.',
	),
	array(
		'rotulo' => 'Valores',
		'texto'  => 'Rigor clínico, escuta honesta, respeito pela pessoa e transparência em cada decisão e em cada explicação que damos.',
	),
);
?>
<section class="sob-historia" data-screen="SobreHistoria">
	<div class="sob-container">

		<div class="sob-historia__grid">
			<div class="sob-historia__aside">
				<div class="sob-eyebrow">A Nossa História</div>
				<h2 class="sob-historia__title">Construída para cuidar de perto</h2>
			</div>

			<div class="sob-historia__body">
				<p>A Clínica Vida abriu portas em Sommerschield, no coração de Maputo, para responder a uma necessidade concreta: um sítio onde marcar uma consulta fosse simples e onde o médico tivesse tempo para ouvir.</p>
				<p>Hoje reunimos várias especialidades sob o mesmo teto — cardiologia, pediatria, ortopedia, neurologia, medicina interna e medicina geral — para que uma família encontre num só lugar a maior parte do que precisa, sem andar de porta em porta.</p>
				<p>Acreditamos que a boa medicina se faz de duas coisas que não se compram: competência e atenção. É esse o equilíbrio que procuramos em cada consulta, todos os dias.</p>
			</div>
		</div>

		<div class="sob-pilares">
			<?php foreach ( $pilares as $p ) : ?>
				<div class="sob-pilar">
					<div class="sob-pilar__rotulo"><?php echo esc_html( $p['rotulo'] ); ?></div>
					<p class="sob-pilar__texto"><?php echo esc_html( $p['texto'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>

	</div>
</section>
