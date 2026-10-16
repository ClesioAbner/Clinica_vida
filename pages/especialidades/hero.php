<?php
/**
 * Especialidades · Hero — fotografia em moldura arredondada (como a homepage),
 * título em baixo à esquerda e, à direita, a marcação rápida num painel de
 * vidro (referência ClinicHub "Visit us").
 *
 * O formulário é GET para /marcar/, que já pré-seleciona especialidade, médico
 * e data (pages/marcar/form.php). O filtro de médicos por especialidade é o de
 * assets/js/main.js (data-filtrar-medicos). Listas da BD: só aparece quem existe.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$especialidades = Clinica_Repo::especialidades();
$medicos        = Clinica_Repo::medicos();
$c              = Clinica_Homepage::contacto();
$telefones      = Clinica_Homepage::telefones();
$img            = CLINICA_URI . '/assets/img/especialidades/';
$hoje           = wp_date( 'Y-m-d' );
?>
<section class="cv-hero cv-hero--reserva" aria-labelledby="esp-hero-titulo">
	<picture class="cv-hero__bg">
		<source media="(max-width: 640px)" srcset="<?php echo esc_url( $img . 'hero-m.webp' ); ?>" width="900" height="1400">
		<img src="<?php echo esc_url( $img . 'hero-1600.webp' ); ?>"
		     srcset="<?php echo esc_url( $img . 'hero-1600.webp' ); ?> 1600w, <?php echo esc_url( $img . 'hero-2400.webp' ); ?> 2400w"
		     sizes="100vw" width="1600" height="900"
		     alt="Médica sorridente à secretária do consultório"
		     fetchpriority="high" decoding="async" data-entrada-foto>
	</picture>

	<div class="hp-container cv-hero__inner">
		<div class="cv-hero__text">
			<nav class="cv-migalhas" aria-label="Está aqui" data-entrada>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a>
				<span aria-hidden="true">/</span>
				<span aria-current="page">Especialidades</span>
			</nav>
			<h1 id="esp-hero-titulo" class="cv-hero__title" data-entrada>O especialista certo para cada cuidado</h1>
			<p class="cv-hero__lead" data-entrada>Escolha a área de que precisa e marque em minutos. Recebe um lembrete por SMS antes da consulta.</p>
		</div>

		<?php if ( $especialidades && $medicos ) : ?>
			<div class="cv-reserva" data-entrada>
				<form class="cv-reserva__form" method="get" action="<?php echo esc_url( home_url( '/marcar/' ) ); ?>" data-filtrar-medicos>
					<p class="cv-reserva__titulo">Marcação rápida</p>

					<label class="cv-campo cv-campo--largo">
						<span>Especialidade</span>
						<select name="especialidade" data-papel="especialidade">
							<option value="">Qualquer especialidade</option>
							<?php foreach ( $especialidades as $e ) : ?>
								<option value="<?php echo esc_attr( $e['id'] ); ?>"><?php echo esc_html( $e['nome'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label class="cv-campo">
						<span>Médico</span>
						<select name="medico" data-papel="medico">
							<option value="">Qualquer médico</option>
							<?php foreach ( $medicos as $m ) : ?>
								<option value="<?php echo esc_attr( $m['id'] ); ?>" data-esp="<?php echo esc_attr( $m['especialidade_id'] ); ?>"><?php echo esc_html( clinica_nome_medico( $m['nome'] ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>

					<label class="cv-campo">
						<span>Data preferida</span>
						<input type="date" name="data" min="<?php echo esc_attr( $hoje ); ?>">
					</label>

					<button class="cv-btn cv-reserva__enviar" type="submit">Ver horários disponíveis</button>
				</form>

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
		<?php endif; ?>
	</div>
</section>
