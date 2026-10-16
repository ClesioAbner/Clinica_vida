<?php
/**
 * Início · Porquê a Clínica Vida — fotografia de fundo a desvanecer para
 * petróleo, com cartões de vidro que mostram "mini-interfaces" do que o
 * sistema faz de facto: médicos, marcação online e lembretes por SMS.
 *
 * As mini-interfaces são ilustrações (aria-hidden): o texto de cada cartão é
 * que transmite a informação.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$img    = CLINICA_URI . '/assets/img/inicio/';
$medico = Clinica_Homepage::equipa()[0] ?? null;

// Mini-calendário do mês corrente (semana a começar à segunda, como em PT).
$meses   = array( 1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro' );
$hoje    = (int) wp_date( 'j' );
$mes     = (int) wp_date( 'n' );
$ano     = (int) wp_date( 'Y' );
$n_dias  = (int) gmdate( 't', gmmktime( 0, 0, 0, $mes, 1, $ano ) );
$vazios  = (int) gmdate( 'N', gmmktime( 0, 0, 0, $mes, 1, $ano ) ) - 1; // 0 = segunda
$amanha  = $hoje < $n_dias ? $hoje + 1 : $hoje;
?>
<section id="porque" class="ini-porque" aria-labelledby="ini-porque-titulo">
	<picture class="ini-porque__bg" aria-hidden="true">
		<source media="(max-width: 640px)" srcset="<?php echo esc_url( $img . 'porque-m.webp' ); ?>">
		<img src="<?php echo esc_url( $img . 'porque.webp' ); ?>" width="2400" height="1400" alt="" loading="lazy" decoding="async">
	</picture>

	<div class="hp-container ini-porque__inner">
		<div class="cv-head cv-head--claro" data-revelar>
			<p class="cv-eyebrow">Porquê a Clínica Vida</p>
			<h2 id="ini-porque-titulo" class="cv-h2">Tudo o que a sua família precisa, numa só clínica</h2>
		</div>

		<div class="ini-vidros" data-revelar-grupo>
			<article class="ini-vidro">
				<h3 class="ini-vidro__titulo">Especialistas experientes</h3>
				<p class="ini-vidro__texto">Médicos de várias áreas a trabalhar em conjunto, com tempo para ouvir, explicar e decidir consigo.</p>
				<?php if ( $medico ) : ?>
					<div class="ini-mock ini-mock--medico" aria-hidden="true">
						<img src="<?php echo esc_url( $medico['foto'] ); ?>" width="56" height="56" alt="" loading="lazy" decoding="async">
						<strong><?php echo esc_html( $medico['nome'] ); ?></strong>
						<span><?php echo esc_html( $medico['especialidade'] ); ?></span>
					</div>
				<?php endif; ?>
			</article>

			<article class="ini-vidro ini-vidro--alto">
				<h3 class="ini-vidro__titulo">Marcação online simples</h3>
				<p class="ini-vidro__texto">Sem filas nem chamadas em espera. Escolha a especialidade, o médico e a hora e confirme a consulta no site.</p>
				<div class="ini-mock ini-mock--agenda" aria-hidden="true">
					<div class="ini-agenda">
						<strong>Marcar consulta</strong>
						<span class="ini-agenda__sub">Escolha o dia e a hora</span>
						<span class="ini-agenda__campo">Cardiologia</span>
						<span class="ini-agenda__campo"><?php echo esc_html( $amanha . ' de ' . mb_strtolower( $meses[ $mes ] ) ); ?> · 10:00</span>
						<span class="ini-agenda__acoes"><span class="ini-agenda__ok">Confirmar</span><span>Cancelar</span></span>
					</div>
					<div class="ini-cal">
						<div class="ini-cal__mes"><?php echo esc_html( $meses[ $mes ] . ' ' . $ano ); ?></div>
						<div class="ini-cal__grelha">
							<?php foreach ( array( 'S', 'T', 'Q', 'Q', 'S', 'S', 'D' ) as $d ) : ?>
								<span class="ini-cal__dia-semana"><?php echo esc_html( $d ); ?></span>
							<?php endforeach; ?>
							<?php for ( $i = 0; $i < $vazios; $i++ ) : ?><span></span><?php endfor; ?>
							<?php for ( $d = 1; $d <= $n_dias; $d++ ) : ?>
								<span class="<?php echo $d === $amanha ? 'is-escolhido' : ( $d < $hoje ? 'is-passado' : '' ); ?>"><?php echo (int) $d; ?></span>
							<?php endfor; ?>
						</div>
					</div>
				</div>
			</article>

			<article class="ini-vidro">
				<h3 class="ini-vidro__titulo">Lembretes por SMS</h3>
				<p class="ini-vidro__texto">Recebe um SMS um dia antes e outro uma hora antes da consulta. Menos esquecimentos, menos esperas.</p>
				<div class="ini-mock ini-mock--sms" aria-hidden="true">
					<span class="ini-sms__icone">
						<img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo-simbolo-96.webp' ); ?>" width="28" height="28" alt="">
					</span>
					<span class="ini-sms__corpo">
						<strong>Clínica Vida</strong>
						<span>Lembrete: a sua consulta é amanhã às 10:00. Até já!</span>
					</span>
					<span class="ini-sms__hora">agora</span>
				</div>
			</article>
		</div>
	</div>
</section>
