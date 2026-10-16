<?php
/**
 * Marcar · Antes de marcar — as perguntas frequentes DENTRO do ecrã de um
 * telemóvel: a fotografia (mão com telemóvel, assets/img/marcar/telemovel.*)
 * é um cartão largo; o título fica na zona livre à esquerda e o acordeão
 * <details> vive no ecrã, com scroll próprio, como uma app.
 *
 * O ecrã é posicionado em % da fotografia (--ecra-*: medido no original
 * 2393×1540). Sem a fotografia no tema, cai no bloco FAQ normal.
 *
 * Dados: Clinica_Homepage::faq().
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$faq  = Clinica_Homepage::faq();
$foto = '';
foreach ( array( 'webp', 'jpg', 'jpeg', 'png' ) as $ext ) {
	if ( file_exists( CLINICA_DIR . '/assets/img/marcar/telemovel.' . $ext ) ) {
		$foto = CLINICA_URI . '/assets/img/marcar/telemovel.' . $ext;
		break;
	}
}

if ( ! $foto ) {
	get_template_part(
		'pages/partials/faq',
		null,
		array(
			'id'        => 'perguntas',
			'titulo'    => 'Antes de marcar',
			'perguntas' => $faq,
			'cartao'    => false,
		)
	);
	return;
}
?>
<section id="perguntas" class="cv-sec cv-faq-tel" aria-labelledby="perguntas-titulo">
	<div class="hp-container">
		<div class="cv-faq-tel__cartao" data-revelar>
			<img class="cv-faq-tel__foto" src="<?php echo esc_url( $foto ); ?>" width="2393" height="1540" alt="" loading="lazy" decoding="async">

			<div class="cv-faq-tel__texto">
				<p class="cv-eyebrow">Perguntas frequentes</p>
				<h2 id="perguntas-titulo" class="cv-h2">Antes de marcar</h2>
				<p>Tudo o que costumam perguntar-nos, à distância de um toque. Ainda com dúvidas? <a href="<?php echo esc_url( home_url( '/contacto/' ) ); ?>">Fale connosco</a>.</p>
			</div>

			<div class="cv-faq-tel__ecra">
				<div class="cv-faq-tel__app" aria-hidden="true">
					<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="22" height="22" alt="">
					<span>Clínica Vida · Ajuda</span>
				</div>
				<div class="cv-faq-tel__lista">
					<?php foreach ( $faq as $i => $q ) : ?>
						<details class="cv-pergunta"<?php echo 0 === $i ? ' open' : ''; ?>>
							<summary><?php echo esc_html( $q['p'] ); ?><span class="cv-pergunta__mais" aria-hidden="true"></span></summary>
							<p><?php echo esc_html( $q['r'] ); ?></p>
						</details>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
</section>
