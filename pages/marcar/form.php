<?php
/**
 * Marcar · Formulário de marcação (sessão iniciada), no painel de vidro do
 * hero. Os campos sem name (especialidade, data, hora) só alimentam o filtro
 * e o campo escondido "inicio"; as horas livres vêm de admin-ajax
 * (action clinica_slots). O POST é tratado por clinica_controller_cliente.
 *
 * @package Clinica
 */

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
<?php clinica_render_flash(); ?>

<?php if ( ! $especialidades || ! $medicos ) : ?>

	<div class="cv-reserva__form" role="status">
		<p class="cv-reserva__titulo">Marcação online indisponível</p>
		<p class="cv-reserva__nota">Ainda não há especialidades ou médicos com horário. Ligue-nos e marcamos consigo.</p>
	</div>

<?php else : ?>

	<form method="post" class="cv-reserva__form" id="mk-form" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<?php wp_nonce_field( 'cl_save', 'cl_nonce' ); ?>
		<input type="hidden" name="cl_action" value="<?php echo $is_edit ? 'atualizar' : 'criar'; ?>">
		<input type="hidden" name="consulta_id" value="<?php echo esc_attr( $is_edit ? $edit['id'] : '' ); ?>">

		<p class="cv-reserva__titulo"><?php echo $is_edit ? 'Atualizar consulta' : 'Dados da marcação'; ?></p>
		<p class="cv-reserva__sub">Sessão iniciada como <?php echo esc_html( $user->user_email ); ?></p>

		<div class="cv-campo cv-campo--largo">
			<label for="mk-nome">Nome completo</label>
			<input type="text" id="mk-nome" name="nome_cliente" value="<?php echo esc_attr( $v_nome ); ?>" autocomplete="name" required>
		</div>
		<div class="cv-campo">
			<label for="mk-tel">Telefone</label>
			<input type="tel" id="mk-tel" name="telefone" value="<?php echo esc_attr( $v_tel ); ?>" placeholder="+258 84 000 0000" autocomplete="tel" required>
		</div>
		<div class="cv-campo">
			<label for="mk-nasc">Data de nascimento</label>
			<input type="date" id="mk-nasc" name="data_nascimento" value="<?php echo esc_attr( $v_nasc ); ?>" max="<?php echo esc_attr( $hoje ); ?>" required>
		</div>
		<div class="cv-campo">
			<label for="mk-esp">Especialidade</label>
			<select id="mk-esp" required>
				<option value="">Selecione</option>
				<?php foreach ( $especialidades as $e ) : ?>
					<option value="<?php echo esc_attr( $e['id'] ); ?>" <?php selected( intval( $e['id'] ), $sel_esp ); ?>><?php echo esc_html( $e['nome'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="cv-campo">
			<label for="mk-medico">Médico</label>
			<select id="mk-medico" name="medico_id" required>
				<option value="">Escolha a especialidade</option>
				<?php foreach ( $medicos as $m ) : ?>
					<option value="<?php echo esc_attr( $m['id'] ); ?>" data-esp="<?php echo esc_attr( $m['especialidade_id'] ); ?>" <?php selected( intval( $m['id'] ), $sel_med ); ?>><?php echo esc_html( clinica_nome_medico( $m['nome'] ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="cv-campo">
			<label for="mk-data">Data</label>
			<input type="date" id="mk-data" min="<?php echo esc_attr( $hoje ); ?>" value="<?php echo esc_attr( $v_data ); ?>" required>
		</div>
		<div class="cv-campo">
			<label for="mk-hora">Hora disponível</label>
			<select id="mk-hora" required data-sel="<?php echo esc_attr( $v_hora ); ?>">
				<option value="">Escolha médico e data</option>
			</select>
		</div>
		<input type="hidden" name="inicio" id="mk-inicio" value="<?php echo esc_attr( $v_inicio ); ?>">
		<div class="cv-campo cv-campo--largo">
			<label for="mk-obs">Motivo da consulta</label>
			<textarea id="mk-obs" name="observacoes" rows="2" required placeholder="Descreva brevemente os sintomas ou o motivo."><?php echo esc_textarea( $v_obs ); ?></textarea>
		</div>

		<button type="submit" class="cv-btn cv-reserva__enviar"><?php echo $is_edit ? 'Guardar alterações' : 'Confirmar e enviar'; ?></button>
		<p class="cv-reserva__nota">A consulta fica <strong>pendente</strong> até o médico confirmar. Pode editá-la em «A minha conta» enquanto não for confirmada.</p>
	</form>

<?php endif; ?>

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
