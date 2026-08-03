<?php
/**
 * Especialidades · Grelha — cartões da BD (sem ícones), com descrição rica.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$especialidades = Clinica_Repo::especialidades();
$contagem       = Clinica_Repo::contar_medicos_por_especialidade();
?>
<section class="esp-grid-sec">
	<div class="esp-container">

		<div class="esp-grid__head">
			<h2 class="esp-grid__title">As nossas especialidades</h2>
			<p class="esp-grid__lead">Do diagnóstico ao tratamento, cobrimos as principais áreas da medicina para si e para a sua família.</p>
		</div>

		<?php if ( $especialidades ) : ?>
			<div class="esp-grid">
				<?php foreach ( $especialidades as $e ) :
					$id = (int) $e['id'];
					$n  = $contagem[ $id ] ?? 0;
				?>
					<article class="esp-card">
						<h3 class="esp-card__name"><?php echo esc_html( $e['nome'] ); ?></h3>
						<p class="esp-card__desc"><?php echo esc_html( $e['descricao'] ); ?></p>
						<span class="esp-card__meta">
							<?php echo $n > 0 ? esc_html( (string) $n ) . ( 1 === $n ? ' médico' : ' médicos' ) : 'Equipa dedicada'; ?>
						</span>
					</article>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="esp-empty">Ainda não há especialidades disponíveis.</p>
		<?php endif; ?>

	</div>
</section>
