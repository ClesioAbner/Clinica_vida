<?php
/**
 * Homepage · Rodapé — logo (PNG), redes sociais, colunas e nota legal.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$navegacao      = array(
	home_url( '/' )                => 'Home',
	home_url( '/especialidades/' ) => 'Especialidades',
	home_url( '/medicos/' )        => 'Médicos',
	home_url( '/sobre/' )          => 'Sobre Nós',
	home_url( '/contacto/' )       => 'Contacto',
);
$especialidades = Clinica_Homepage::especialidades();  // fonte única
$contacto       = Clinica_Homepage::contacto();         // fonte única
$redes          = Clinica_Homepage::redes_sociais();
$ano            = (int) gmdate( 'Y' );

// Telefone em formato clicável (só dígitos e +).
$tel_href = 'tel:' . preg_replace( '/[^0-9+]/', '', $contacto['telefone'] );
?>
<footer class="hp-footer" data-screen="Footer">
	<div class="hp-footer__inner hp-container">

		<div class="hp-footer__top">

			<div class="hp-footer__brand-col">
				<span class="hp-footer__logo" role="img" aria-label="Clínica Vida"
				      style="background-image:url('<?php echo esc_url( CLINICA_URI . '/assets/img/logo2.png' ); ?>')"></span>

				<p class="hp-footer__about">Cuidados de saúde de excelência em Maputo, no bairro de Sommerschield. Medicina com tempo, rigor e proximidade.</p>

				<div class="hp-social" aria-label="Redes sociais">
					<?php foreach ( $redes as $r ) : ?>
						<a class="hp-social__btn hp-social__btn--<?php echo esc_attr( $r['rede'] ); ?>"
						   href="<?php echo esc_url( $r['url'] ); ?>"
						   aria-label="<?php echo esc_attr( $r['label'] ); ?>" target="_blank" rel="noopener">
							<span class="hp-social__bg" aria-hidden="true"></span>
							<span class="hp-social__icon"><?php echo $r['svg']; // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="hp-footer__col">
				<div class="hp-footer__col-title">Navegação</div>
				<?php foreach ( $navegacao as $href => $label ) : ?>
					<a class="hp-footer__link" href="<?php echo esc_attr( $href ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</div>

			<div class="hp-footer__col">
				<div class="hp-footer__col-title">Especialidades</div>
				<?php foreach ( $especialidades as $e ) : ?>
					<a class="hp-footer__link" href="<?php echo esc_url( home_url( '/especialidades/' ) ); ?>"><?php echo esc_html( $e['nome'] ); ?></a>
				<?php endforeach; ?>
			</div>

			<div class="hp-footer__col">
				<div class="hp-footer__col-title">Contacto</div>
				<address class="hp-footer__contact">
					<?php echo esc_html( $contacto['morada'][0] ); ?><br>
					<?php echo esc_html( $contacto['morada'][1] ); ?>
				</address>
				<a class="hp-footer__link" href="<?php echo esc_attr( $tel_href ); ?>"><?php echo esc_html( $contacto['telefone'] ); ?></a>
				<a class="hp-footer__link" href="mailto:<?php echo esc_attr( $contacto['email'] ); ?>"><?php echo esc_html( $contacto['email'] ); ?></a>
			</div>

		</div>

		<div class="hp-footer__bottom">
			<div>© <?php echo esc_html( (string) $ano ); ?> Clínica Vida. Todos os direitos reservados.</div>
			<div class="hp-footer__legal">
				<a class="hp-footer__link" href="#">Política de Privacidade</a>
				<a class="hp-footer__link" href="#">Termos de Utilização</a>
				<a class="hp-footer__link" href="#">Livro de Reclamações</a>
			</div>
		</div>

	</div>
</footer>
