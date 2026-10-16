<?php
/**
 * Marcar · Hero — o hero "visit us" da referência (.cv-hero--reserva): título
 * à esquerda e, à direita, o formulário de marcação em vidro (sessão iniciada)
 * ou o convite a entrar (sem sessão). Por baixo do painel: telefone e horário.
 *
 * Args: paciente_id (0 = sem sessão).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$paciente_id = (int) ( $args['paciente_id'] ?? 0 );
$editar      = $paciente_id && isset( $_GET['editar'] );
$img         = CLINICA_URI . '/assets/img/marcar/';
$c           = Clinica_Homepage::contacto();
$telefones   = Clinica_Homepage::telefones();
?>
<section class="cv-hero cv-hero--reserva" aria-labelledby="mk-titulo">
	<picture class="cv-hero__bg">
		<source media="(max-width: 640px)" srcset="<?php echo esc_url( $img . 'hero-m.webp' ); ?>" width="900" height="1400">
		<img src="<?php echo esc_url( $img . 'hero-1600.webp' ); ?>"
		     srcset="<?php echo esc_url( $img . 'hero-1600.webp' ); ?> 1600w, <?php echo esc_url( $img . 'hero-2400.webp' ); ?> 2400w"
		     sizes="100vw" width="1600" height="900"
		     alt="Médico ao computador no balcão da receção da clínica"
		     fetchpriority="high" decoding="async" data-entrada-foto>
	</picture>

	<div class="hp-container cv-hero__inner">
		<div class="cv-hero__text">
			<nav class="cv-migalhas" aria-label="Está aqui" data-entrada>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a>
				<span aria-hidden="true">/</span>
				<span aria-current="page">Marcar consulta</span>
			</nav>
			<h1 id="mk-titulo" class="cv-hero__title" data-entrada><?php echo $editar ? 'Atualize a sua consulta' : 'Marque hoje e comece a sentir-se melhor'; ?></h1>
			<p class="cv-hero__lead" data-entrada><?php echo $paciente_id ? 'Escolha a especialidade, o médico e a hora livre que lhe convém. Recebe um lembrete por SMS antes da consulta.' : 'Sem criar conta: diga-nos o nome e o telefone, escolha a hora livre e nós ligamos a confirmar.'; ?></p>
		</div>

		<div class="cv-reserva cv-reserva--largo" id="marcar" data-entrada>
			<?php get_template_part( $paciente_id ? 'pages/marcar/form' : 'pages/marcar/rapida' ); ?>

			<dl class="cv-reserva__info">
				<div>
					<dt>Telefone</dt>
					<dd><a href="<?php echo esc_attr( Clinica_Homepage::tel_href( $telefones[0] ?? '' ) ); ?>"><?php echo esc_html( $telefones[0] ?? '' ); ?></a></dd>
				</div>
				<div>
					<dt>Horário</dt>
					<dd><?php echo esc_html( $c['horario'] ); ?></dd>
				</div>
			</dl>
		</div>
	</div>
</section>
