<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$editar = isset( $_GET['editar'] );
?>
<section class="mk-banner" style="--mk-bg:url('<?php echo esc_url( CLINICA_URI . '/assets/img/banner11.png' ); ?>')">
	<div class="mk-banner__inner">
		<div class="mk-banner__content">
			<div class="mk-eyebrow">Clínica Vida</div>
			<h1 class="mk-banner__title"><?php echo $editar ? 'Atualize a sua consulta' : 'Marque a sua consulta'; ?></h1>
			<p class="mk-banner__lead">Preencha os seus dados e escolha a especialidade, o médico e o horário. É rápido e seguro.</p>
		</div>
	</div>
</section>
