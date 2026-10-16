<?php
/**
 * Sobre · A nossa história — bloco editorial (.cv-editorial): título à
 * esquerda, texto à direita, como o FAQ da página "visit us" da referência.
 *
 * As especialidades citadas vêm da BD (antes estavam escritas à mão e já não
 * batiam certo com as que existem).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$nomes = array_map( static fn( array $e ): string => mb_strtolower( $e['nome'] ), Clinica_Repo::especialidades() );
$lista = count( $nomes ) > 1 ? implode( ', ', array_slice( $nomes, 0, -1 ) ) . ' e ' . end( $nomes ) : implode( '', $nomes );
?>
<section id="historia" class="cv-sec cv-editorial" aria-labelledby="sob-historia-titulo">
	<div class="hp-container cv-editorial__grelha">

		<div class="cv-head cv-head--esq" data-revelar>
			<p class="cv-eyebrow">A nossa história</p>
			<h2 id="sob-historia-titulo" class="cv-h2">Um sítio onde o médico tem tempo para ouvir</h2>
		</div>

		<div class="cv-editorial__texto" data-revelar-grupo>
			<p>A Clínica Vida abriu portas em Sommerschield, no coração de Maputo, para responder a uma necessidade concreta: um sítio onde marcar uma consulta fosse simples e onde o médico tivesse tempo para ouvir.</p>
			<?php if ( $lista ) : ?>
				<p>Hoje reunimos sob o mesmo teto <?php echo esc_html( $lista ); ?>. Assim, uma família encontra num só lugar a maior parte do que precisa, sem andar de porta em porta.</p>
			<?php endif; ?>
			<p>Acreditamos que a boa medicina se faz de duas coisas que não se compram: competência e atenção. É esse o equilíbrio que procuramos em cada consulta, todos os dias.</p>
		</div>

	</div>
</section>
