<?php
declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$paciente_id    = Clinica_Repo::ensure_paciente( get_current_user_id() );
$especialidades = Clinica_Repo::especialidades();
$medicos        = Clinica_Repo::medicos();

$edit    = isset( $_GET['editar'] ) ? Clinica_Repo::consulta_do_paciente( intval( $_GET['editar'] ), $paciente_id ) : null;
$is_edit = (bool) $edit;

// Fora do modo edição, a especialidade/médico/data podem vir pré-selecionados
// da barra de pesquisa da homepage (GET). Os IDs são validados abaixo.
$sel_esp  = $is_edit ? intval( $edit['esp_id'] ) : Clinica_Input::inteiro( $_GET, 'especialidade' );
$sel_med  = $is_edit ? intval( $edit['medico_id'] ) : Clinica_Input::inteiro( $_GET, 'medico' );
$v_inicio = $is_edit ? Clinica_Repo::para_input( $edit['inicio'] ) : '';
$v_obs    = $is_edit ? $edit['observacoes'] : '';

$user     = wp_get_current_user();
$pdados   = Clinica_Repo::paciente_dados( $paciente_id );
$v_nome   = $is_edit && ! empty( $edit['nome_cliente'] ) ? $edit['nome_cliente'] : $user->display_name;
$v_tel    = $pdados['telefone'] ?? '';
$v_nasc   = ! empty( $pdados['data_nascimento'] ) ? substr( $pdados['data_nascimento'], 0, 10 ) : '';
$hoje     = gmdate( 'Y-m-d' );
$v_data   = $v_inicio ? substr( $v_inicio, 0, 10 ) : Clinica_Input::data( $_GET, 'data' );
$v_hora   = $v_inicio ? substr( $v_inicio, 11, 5 ) : '';

// O campo de data tem min=hoje: uma data passada vinda do GET não pré-preenche.
if ( $v_data < $hoje ) {
	$v_data = '';
}
?>
<section class="mk-section">
	<div class="mk-wrap">

		<?php clinica_render_flash(); ?>

		<?php if ( ! $especialidades || ! $medicos ) : ?>

			<div class="mk-alert mk-alert--error">Ainda não há especialidades ou médicos disponíveis. Por favor, contacte a clínica.</div>

		<?php else : ?>

		<div class="mk-head">
			<div class="mk-eyebrow">Marcação</div>
			<h2 class="mk-head__title"><?php echo $is_edit ? 'Atualizar consulta' : 'Dados da marcação'; ?></h2>
			<p class="mk-head__lead">Preencha os campos abaixo. A consulta fica pendente até à confirmação do médico.</p>
		</div>

		<form method="post" class="mk-form" id="mk-form" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<?php wp_nonce_field( 'cl_save', 'cl_nonce' ); ?>
			<input type="hidden" name="cl_action" value="<?php echo $is_edit ? 'atualizar' : 'criar'; ?>">
			<input type="hidden" name="consulta_id" value="<?php echo esc_attr( $is_edit ? $edit['id'] : '' ); ?>">

			<fieldset class="mk-block">
				<legend class="mk-block__title">Os seus dados</legend>
				<div class="mk-grid">
					<div class="mk-field">
						<label for="mk-nome">Nome completo</label>
						<input type="text" id="mk-nome" name="nome_cliente" value="<?php echo esc_attr( $v_nome ); ?>" required>
					</div>
					<div class="mk-field">
						<label for="mk-email">Email</label>
						<input type="email" id="mk-email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly>
					</div>
					<div class="mk-field">
						<label for="mk-tel">Telefone</label>
						<input type="tel" id="mk-tel" name="telefone" value="<?php echo esc_attr( $v_tel ); ?>" placeholder="Ex.: +258 84 000 0000" required>
					</div>
					<div class="mk-field">
						<label for="mk-nasc">Data de nascimento</label>
						<input type="date" id="mk-nasc" name="data_nascimento" value="<?php echo esc_attr( $v_nasc ); ?>" max="<?php echo esc_attr( $hoje ); ?>" required>
					</div>
				</div>
			</fieldset>

			<fieldset class="mk-block">
				<legend class="mk-block__title">A consulta</legend>
				<div class="mk-grid">
					<div class="mk-field">
						<label for="mk-esp">Especialidade</label>
						<select id="mk-esp" required>
							<option value="">Selecione</option>
							<?php foreach ( $especialidades as $e ) : ?>
								<option value="<?php echo esc_attr( $e['id'] ); ?>" <?php selected( intval( $e['id'] ), $sel_esp ); ?>><?php echo esc_html( $e['nome'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mk-field">
						<label for="mk-medico">Médico</label>
						<select id="mk-medico" name="medico_id" required>
							<option value="">Escolha a especialidade primeiro</option>
							<?php foreach ( $medicos as $m ) : ?>
								<option value="<?php echo esc_attr( $m['id'] ); ?>" data-esp="<?php echo esc_attr( $m['especialidade_id'] ); ?>" <?php selected( intval( $m['id'] ), $sel_med ); ?>><?php echo esc_html( clinica_nome_medico( $m['nome'] ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="mk-field">
						<label for="mk-data">Data</label>
						<input type="date" id="mk-data" min="<?php echo esc_attr( $hoje ); ?>" value="<?php echo esc_attr( $v_data ); ?>" required>
					</div>
					<div class="mk-field">
						<label for="mk-hora">Hora disponível</label>
						<select id="mk-hora" required data-sel="<?php echo esc_attr( $v_hora ); ?>">
							<option value="">Escolha médico e data</option>
						</select>
					</div>
					<input type="hidden" name="inicio" id="mk-inicio" value="<?php echo esc_attr( $v_inicio ); ?>">
					<div class="mk-field mk-field--full">
						<label for="mk-obs">Motivo da consulta</label>
						<textarea id="mk-obs" name="observacoes" rows="4" required placeholder="Descreva brevemente os sintomas ou o motivo da consulta."><?php echo esc_textarea( $v_obs ); ?></textarea>
					</div>
				</div>
			</fieldset>

			<div class="mk-form__foot">
				<p class="mk-note">A consulta fica <strong>pendente</strong> até o médico confirmar. Pode editar enquanto não for confirmada.</p>
				<button type="submit" class="mk-btn mk-btn--primary mk-btn--lg">
					<?php echo $is_edit ? 'Guardar alterações' : 'Confirmar e enviar'; ?>
				</button>
			</div>
		</form>

		<?php endif; ?>

	</div>
</section>

<script>
( function () {
	var form = document.getElementById( 'mk-form' );
	if ( ! form ) { return; }
	var esp  = document.getElementById( 'mk-esp' );
	var med  = document.getElementById( 'mk-medico' );
	var data = document.getElementById( 'mk-data' );
	var hora = document.getElementById( 'mk-hora' );
	var alvo = document.getElementById( 'mk-inicio' );
	var ajax = form.getAttribute( 'data-ajax' );

	function filtrar( auto ) {
		if ( ! esp ) { return; }
		var v = esp.value, primeiro = null;
		Array.prototype.forEach.call( med.options, function ( o ) {
			if ( ! o.value ) { return; }
			var ok = ( String( o.dataset.esp ) === String( v ) );
			o.hidden = ! ok;
			if ( ok && ! primeiro ) { primeiro = o; }
		} );
		if ( auto ) { med.value = primeiro ? primeiro.value : ''; }
	}

	function sincronizar() {
		alvo.value = ( data.value && hora.value ) ? ( data.value + 'T' + hora.value ) : '';
	}

	function preencher( lista ) {
		var sel = hora.getAttribute( 'data-sel' ) || '';
		hora.innerHTML = '';
		if ( ! lista.length ) {
			hora.appendChild( new Option( 'Sem horas livres neste dia', '' ) );
			sincronizar();
			return;
		}
		hora.appendChild( new Option( 'Selecione a hora', '' ) );
		// No modo edição, mantém a hora atual mesmo que já esteja "ocupada" por esta consulta.
		if ( sel && lista.indexOf( sel ) === -1 ) { lista.unshift( sel ); }
		lista.forEach( function ( h ) {
			var o = new Option( h, h );
			if ( h === sel ) { o.selected = true; }
			hora.appendChild( o );
		} );
		hora.removeAttribute( 'data-sel' );
		sincronizar();
	}

	function carregar() {
		if ( ! med.value || ! data.value ) {
			hora.innerHTML = '';
			hora.appendChild( new Option( 'Escolha médico e data', '' ) );
			alvo.value = '';
			return;
		}
		fetch( ajax + '?action=clinica_slots&medico=' + encodeURIComponent( med.value ) + '&data=' + encodeURIComponent( data.value ) )
			.then( function ( r ) { return r.json(); } )
			.then( preencher )
			.catch( function () {} );
	}

	if ( esp ) { esp.addEventListener( 'change', function () { filtrar( true ); carregar(); } ); }
	med.addEventListener( 'change', carregar );
	data.addEventListener( 'change', carregar );
	hora.addEventListener( 'change', sincronizar );

	if ( esp && esp.value ) { filtrar( false ); }
	if ( med.value && data.value ) { carregar(); }
} )();
</script>
