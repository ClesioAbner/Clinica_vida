<?php
/**
 * Partilhado · Números — três valores em serifa grande, separados por filetes
 * (a faixa de números da referência). Usado na página inicial e em Sobre Nós.
 *
 * Só números que o sistema sabe: especialidades ativas (BD) e o horário de
 * atendimento (fonte única Clinica_Homepage::contacto, ex. "Seg a Sáb, 08h00 às 20h00").
 * Se o horário mudar de formato e não der para ler, esses números não aparecem.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$numeros = array();

$n_esp = count( Clinica_Repo::especialidades() );
if ( $n_esp > 0 ) {
	$numeros[] = array( (string) $n_esp, 'Especialidades médicas num só lugar' );
}

$horario = Clinica_Homepage::contacto()['horario'];
$dias    = array( 'Seg a Sex' => 5, 'Seg a Sáb' => 6, 'Seg a Dom' => 7, 'Seg-Sex' => 5, 'Seg-Sáb' => 6, 'Seg-Dom' => 7 );
foreach ( $dias as $intervalo => $n ) {
	if ( str_starts_with( $horario, $intervalo ) ) {
		$numeros[] = array( (string) $n, 'Dias de atendimento por semana' );
		break;
	}
}
if ( preg_match( '/(\d{1,2})h\d{2}\s*(?:-|às|a)\s*(\d{1,2})h\d{2}/u', $horario, $m ) && (int) $m[2] > (int) $m[1] ) {
	$numeros[] = array( ( (int) $m[2] - (int) $m[1] ) . 'h', 'De atendimento por dia' );
}

if ( ! $numeros ) {
	return;
}
?>
<section class="cv-numeros" aria-label="A clínica em números">
	<div class="hp-container">
		<ul class="cv-numeros__lista" data-revelar-grupo>
			<?php foreach ( $numeros as $n ) : ?>
				<li class="cv-numero">
					<?php // data-contar: o número sobe de 0 até ao valor (o texto já traz o valor final). ?>
					<span class="cv-numero__valor" data-contar="<?php echo (int) $n[0]; ?>" data-sufixo="<?php echo esc_attr( preg_replace( '/^\d+/', '', $n[0] ) ); ?>"><?php echo esc_html( $n[0] ); ?></span>
					<span class="cv-numero__rotulo"><?php echo esc_html( $n[1] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
