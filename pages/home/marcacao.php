<?php
/**
 * Homepage · Barra de pesquisa rápida de marcação.
 *
 * Envia (GET) para /marcar/, que pré-seleciona especialidade/médico/data e faz
 * a marcação real. Só viajam IDs e uma data — nunca dados pessoais.
 * As listas vêm da base de dados: o site público nunca anuncia quem não existe.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$url_marcar     = home_url( '/marcar/' );
$especialidades = Clinica_Repo::especialidades();
$medicos        = Clinica_Repo::medicos();

// Sem equipa configurada não há nada para pesquisar: a secção não se imprime.
if ( ! $especialidades || ! $medicos ) {
	return;
}

$hoje = gmdate( 'Y-m-d' );

// Ícones (feather, traço) de cada campo — marcação confiável definida aqui.
$ic_esp    = '<svg class="hp-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>';
$ic_medico = '<svg class="hp-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
$ic_data   = '<svg class="hp-field__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
?>


<section class="hp-marcacao" data-screen="Marcacao" aria-labelledby="hp-marcacao-titulo">
	<div class="hp-container">
		<h2 id="hp-marcacao-titulo" class="hp-sr-only">Pesquisar consulta</h2>

		<form class="hp-search__bar" method="get" action="<?php echo esc_url( $url_marcar ); ?>" data-filtrar-medicos>

			<div class="hp-field">
				<?php echo $ic_esp; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="hp-field__body">
					<label class="hp-field__label" for="hp-esp">Especialidade</label>
					<select class="hp-field__control" id="hp-esp" name="especialidade" data-papel="especialidade">
						<option value="">Qualquer especialidade</option>
						<?php foreach ( $especialidades as $e ) : ?>
							<option value="<?php echo esc_attr( $e['id'] ); ?>"><?php echo esc_html( $e['nome'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="hp-field">
				<?php echo $ic_medico; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="hp-field__body">
					<label class="hp-field__label" for="hp-med">Médico</label>
					<select class="hp-field__control" id="hp-med" name="medico" data-papel="medico">
						<option value="">Qualquer médico</option>
						<?php foreach ( $medicos as $m ) : ?>
							<option value="<?php echo esc_attr( $m['id'] ); ?>" data-esp="<?php echo esc_attr( $m['especialidade_id'] ); ?>"><?php echo esc_html( clinica_nome_medico( $m['nome'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="hp-field">
				<?php echo $ic_data; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<div class="hp-field__body">
					<label class="hp-field__label" for="hp-data">Data</label>
					<input class="hp-field__control" id="hp-data" type="date" name="data" min="<?php echo esc_attr( $hoje ); ?>">
				</div>
			</div>

			<button class="hp-search__submit" type="submit">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
				Pesquisar
			</button>

		</form>
	</div>
</section>
