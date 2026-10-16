<?php
/**
 * Site público · Cabeçalho fixo em "pílulas" de vidro (marca · navegação · CTA).
 * Partilhado por todas as páginas públicas. Abaixo de 960px vira uma barra
 * única; as três barras abrem uma gaveta que desliza da direita (estado em
 * aria-expanded / .is-aberta, ver assets/js/main.js).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$url_entrar = home_url( '/entrar/' );
$url_marcar = home_url( '/marcar/' );
$url_esp    = home_url( '/especialidades/' );
$url_med    = home_url( '/medicos/' );
$url_sob    = home_url( '/sobre/' );
$url_con    = home_url( '/contacto/' );

// Página atual de cada ligação (para aria-current e realce).
$atual = array(
	$url_esp => is_page_template( 'page-especialidades.php' ),
	$url_med => is_page_template( 'page-medicos.php' ),
	$url_sob => is_page_template( 'page-sobre.php' ),
	$url_con => is_page_template( 'page-contacto.php' ),
);

$nav = array(
	$url_esp => 'Especialidades',
	$url_med => 'Médicos',
	$url_sob => 'Sobre Nós',
	$url_con => 'Contacto',
);

// Sessão iniciada: "Entrar" dá lugar ao painel do próprio utilizador.
$com_sessao = is_user_logged_in();
$url_conta  = $com_sessao && function_exists( 'clinica_painel_do_utilizador' ) ? clinica_painel_do_utilizador() : home_url( '/conta/' );
$conta      = $com_sessao ? array( $url_conta, 'A minha conta' ) : array( $url_entrar, 'Entrar' );
?>
<a class="hp-skip" href="#conteudo">Saltar para o conteúdo</a>

<header class="hp-header" data-screen="Header">
	<div class="hp-header__inner">

		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="hp-logo hp-pill" aria-label="Clínica Vida, página inicial">
			<span class="hp-logo__tile" aria-hidden="true">
				<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="26" height="26" alt="">
			</span>
			<span class="hp-logo__text" aria-hidden="true">Clínica Vida</span>
		</a>

		<div class="hp-header__right">
			<nav class="hp-nav hp-pill" aria-label="Navegação principal">
				<?php foreach ( $nav as $href => $label ) : $ativo = ! empty( $atual[ $href ] ); ?>
					<a class="hp-nav__link<?php echo $ativo ? ' is-active' : ''; ?>"
					   href="<?php echo esc_url( $href ); ?>"<?php echo $ativo ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
				<a class="hp-nav__link" href="<?php echo esc_url( $conta[0] ); ?>"><?php echo esc_html( $conta[1] ); ?></a>
			</nav>

			<div class="hp-header__cta hp-pill">
				<a class="hp-header__btn" href="<?php echo esc_url( $url_marcar ); ?>">Marcar consulta</a>
			</div>

			<button class="hp-menu-toggle" type="button" aria-expanded="false" aria-controls="hp-menu-movel" data-menu-toggle>
				<span class="hp-menu-toggle__bars" aria-hidden="true"><span></span><span></span><span></span></span>
				<span class="hp-sr-only">Abrir menu</span>
			</button>
		</div>

	</div>

</header>

<?php
// Gaveta do menu móvel: desliza da direita, com fundo escurecido. Fica fora do
// cabeçalho (que é fixo e deixa passar cliques) para cobrir o ecrã inteiro.
// Estado e foco em assets/js/main.js; fecha com o X, Esc, toque fora ou deslizar.
$gav_links = array( home_url( '/' ) => 'Início' ) + $nav;
$gav_atual = array( home_url( '/' ) => is_front_page() ) + $atual;
$gav_tels  = Clinica_Homepage::telefones();
$gav_hor   = Clinica_Homepage::contacto()['horario'] ?? '';
?>
<div class="hp-gaveta-fundo" data-gaveta-fundo aria-hidden="true"></div>
<aside id="hp-menu-movel" class="hp-gaveta" aria-label="Menu" aria-hidden="true" inert data-gaveta>
	<div class="hp-gaveta__topo">
		<a class="hp-gaveta__marca" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<span class="hp-logo__tile" aria-hidden="true"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="26" height="26" alt=""></span>
			<span>Clínica Vida</span>
		</a>
		<button type="button" class="hp-gaveta__fechar" data-gaveta-fechar aria-label="Fechar menu">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
		</button>
	</div>

	<nav class="hp-gaveta__nav" aria-label="Navegação principal (móvel)">
		<?php $i = 0; foreach ( $gav_links as $href => $label ) : $ativo = ! empty( $gav_atual[ $href ] ); ?>
			<a class="hp-gaveta__link<?php echo $ativo ? ' is-active' : ''; ?>" style="--i:<?php echo (int) $i++; ?>"
			   href="<?php echo esc_url( $href ); ?>"<?php echo $ativo ? ' aria-current="page"' : ''; ?>>
				<span><?php echo esc_html( $label ); ?></span>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="hp-gaveta__acoes" style="--i:<?php echo (int) $i; ?>">
		<a class="hp-btn hp-btn--white hp-btn--lg" href="<?php echo esc_url( $url_marcar ); ?>">Marcar consulta</a>
		<a class="hp-btn hp-btn--lg hp-btn--vidro" href="<?php echo esc_url( $conta[0] ); ?>"><?php echo esc_html( $conta[1] ); ?></a>
		<?php if ( $com_sessao ) : ?>
			<a class="hp-gaveta__sair" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Terminar sessão</a>
		<?php endif; ?>
	</div>

	<dl class="hp-gaveta__info" style="--i:<?php echo (int) $i + 1; ?>">
		<?php if ( $gav_tels ) : ?>
			<div><dt>Telefone</dt><dd><a href="<?php echo esc_attr( Clinica_Homepage::tel_href( $gav_tels[0] ) ); ?>"><?php echo esc_html( $gav_tels[0] ); ?></a></dd></div>
		<?php endif; ?>
		<?php if ( $gav_hor ) : ?>
			<div><dt>Horário</dt><dd><?php echo esc_html( $gav_hor ); ?></dd></div>
		<?php endif; ?>
	</dl>
</aside>
