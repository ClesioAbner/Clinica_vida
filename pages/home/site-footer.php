<?php
/**
 * Site público · Fecho — CTA final + rodapé, numa só faixa petróleo
 * (referência ClinicHub). Partilhado por todas as páginas públicas.
 *
 * As redes sociais só aparecem com um endereço real (Gestão → Definições).
 * Por baixo do rodapé fica o aviso de cookies (cookies.js), que abre também
 * em «Preferências de cookies».
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$contacto  = Clinica_Homepage::contacto();          // fonte única
$telefones = Clinica_Homepage::telefones();         // um link tel: por número
$redes     = Clinica_Homepage::redes_sociais();
$ano       = (int) gmdate( 'Y' );
$inicio    = home_url( '/' );

$icone = static fn( string $d ): string => '<svg class="hp-fecho__ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
$ic    = array(
	'morada'   => $icone( '<path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>' ),
	'telefone' => $icone( '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>' ),
	'email'    => $icone( '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>' ),
	'horario'  => $icone( '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' ),
);

$com_sessao = is_user_logged_in();
$conta      = $com_sessao
	? array( function_exists( 'clinica_painel_do_utilizador' ) ? clinica_painel_do_utilizador() : home_url( '/conta/' ), 'A minha conta' )
	: array( home_url( '/entrar/' ), 'Entrar' );

$colunas = array(
	'Secções' => array(
		$inicio . '#especialidades' => 'Especialidades',
		$inicio . '#como-funciona'  => 'Como funciona',
		$inicio . '#medicos'        => 'Médicos',
		$inicio . '#testemunhos'    => 'Testemunhos',
	),
	'Páginas' => array(
		$inicio                         => 'Início',
		home_url( '/especialidades/' )  => 'Especialidades',
		home_url( '/medicos/' )         => 'Médicos',
		home_url( '/sobre/' )           => 'Sobre Nós',
		home_url( '/contacto/' )        => 'Contacto',
	),
	'Apoio'   => array(
		home_url( '/marcar/' )          => 'Marcar consulta',
		$conta[0]                       => $conta[1],
		$inicio . '#faq'                => 'Perguntas frequentes',
	),
);
?>
<footer class="hp-fecho" data-screen="Footer">
	<div class="hp-fecho__formas" aria-hidden="true"><span></span><span></span><span></span></div>

	<div class="hp-container hp-fecho__inner">

		<div class="hp-fecho__cta" data-revelar-grupo>
			<span class="hp-fecho__marca" aria-hidden="true">
				<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-192.webp' ); ?>" width="48" height="48" alt="" loading="lazy" decoding="async">
			</span>
			<h2 class="hp-fecho__titulo">Os cuidados de que precisa, sem esperas</h2>
			<a class="cv-btn" href="<?php echo esc_url( home_url( '/marcar/' ) ); ?>">Marcar consulta</a>
		</div>

		<div class="hp-fecho__grelha">
			<?php foreach ( $colunas as $titulo => $links ) : ?>
				<nav class="hp-fecho__col" aria-label="<?php echo esc_attr( $titulo ); ?>">
					<p class="hp-fecho__col-titulo"><?php echo esc_html( $titulo ); ?></p>
					<?php foreach ( $links as $href => $label ) : ?>
						<a class="hp-fecho__link" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endforeach; ?>

			<div class="hp-fecho__col hp-fecho__contacto">
				<p class="hp-fecho__col-titulo">Contacto</p>
				<ul class="hp-fecho__dados">
					<li>
						<?php echo $ic['morada']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<address><?php echo esc_html( $contacto['morada'][0] ); ?><br><?php echo esc_html( $contacto['morada'][1] ); ?></address>
					</li>
					<?php if ( $telefones ) : ?>
						<li>
							<?php echo $ic['telefone']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span class="hp-fecho__pilha">
								<?php foreach ( $telefones as $tel ) : ?>
									<a class="hp-fecho__link" href="<?php echo esc_attr( Clinica_Homepage::tel_href( $tel ) ); ?>"><?php echo esc_html( $tel ); ?></a>
								<?php endforeach; ?>
							</span>
						</li>
					<?php endif; ?>
					<li>
						<?php echo $ic['email']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<a class="hp-fecho__link" href="mailto:<?php echo esc_attr( $contacto['email'] ); ?>"><?php echo esc_html( $contacto['email'] ); ?></a>
					</li>
					<li>
						<?php echo $ic['horario']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<span class="hp-fecho__horario"><?php echo esc_html( $contacto['horario'] ); ?></span>
					</li>
				</ul>

				<?php if ( $redes ) : ?>
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
				<?php endif; ?>
			</div>
		</div>

		<div class="hp-fecho__base">
			<p>A Clínica Vida é uma clínica de especialidades médicas em <?php echo esc_html( $contacto['morada'][1] ); ?>. A informação deste site não substitui uma consulta médica.</p>
			<div class="hp-fecho__legal">
				<p>© <?php echo esc_html( (string) $ano ); ?> Clínica Vida. Todos os direitos reservados.</p>
				<nav class="hp-fecho__docs" aria-label="Informação legal">
					<?php foreach ( Clinica_Legal::ligacoes() as $href => $rotulo ) : ?>
						<a href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( $rotulo ); ?></a>
					<?php endforeach; ?>
					<button type="button" class="hp-fecho__prefs" data-cookies-abrir aria-controls="cv-cookies">Preferências de cookies</button>
				</nav>
				<p class="hp-fecho__autor">Desenvolvido por Eclesio Pembelane</p>
			</div>
		</div>

	</div>
</footer>

<aside class="cv-cookies" id="cv-cookies" aria-labelledby="cv-cookies-t" hidden data-cookies<?php echo Clinica_Legal::falta_escolha() ? ' data-pendente' : ''; ?>>
	<svg class="cv-cookies__icone" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
		<path class="cv-cookies__massa" d="M42.15 8.07A26 26 0 1 0 55.93 21.85 12 12 0 0 1 42.15 8.07Z"/>
		<g class="cv-cookies__pepitas">
			<circle cx="21" cy="25" r="3.4"/><circle cx="35" cy="30" r="2.6"/><circle cx="23" cy="42" r="3"/>
			<circle cx="40" cy="45" r="3.2"/><circle cx="31" cy="16" r="2.2"/><circle cx="48" cy="34" r="2.2"/>
		</g>
	</svg>
	<p class="cv-cookies__titulo" id="cv-cookies-t">Cookies</p>
	<p class="cv-cookies__texto">Usamos só os cookies necessários para iniciar sessão e marcar consultas. <a href="<?php echo esc_url( Clinica_Legal::url( 'cookies' ) ); ?>">Saber mais</a></p>
	<div class="cv-cookies__botoes">
		<button type="button" class="cv-cookies__btn cv-cookies__btn--aceitar" data-cookies-escolha="aceite">Aceitar</button>
		<button type="button" class="cv-cookies__btn cv-cookies__btn--recusar" data-cookies-escolha="recusado">Recusar</button>
	</div>
</aside>
