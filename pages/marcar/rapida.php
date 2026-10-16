<?php
/**
 * Marcar · Sem sessão — marcação rápida sem conta, no painel de vidro do hero.
 *
 * Numa aflição ninguém quer criar conta: basta nome, telefone, médico e hora.
 * A receção liga a confirmar. Quem tem conta continua a poder entrar (aba
 * "Já tenho conta"). O POST é tratado por Clinica_Marcacao_Rapida::controlador
 * antes de qualquer HTML; as horas livres vêm de admin-ajax (clinica_slots).
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

$especialidades = Clinica_Repo::especialidades();
$medicos        = Clinica_Repo::medicos();
$telefones      = Clinica_Homepage::telefones();
$feito          = Clinica_Marcacao_Rapida::confirmacao();
$erro           = Clinica_Marcacao_Rapida::erro();
$v              = Clinica_Marcacao_Rapida::campos();
$hoje           = current_time( 'Y-m-d' );

// Pré-seleção vinda da pesquisa da homepage ou de um erro anterior.
$sel_med = (int) ( $v['medico'] ?? Clinica_Input::inteiro( $_GET, 'medico' ) );
$sel_esp = Clinica_Input::inteiro( $_GET, 'especialidade' );
foreach ( $medicos as $m ) {
	if ( (int) $m['id'] === $sel_med ) {
		$sel_esp = (int) $m['especialidade_id'];
	}
}
// O dia é a pessoa que escolhe: só vem marcado se chegar na ligação (?data=).
$v_data = Clinica_Input::data( $_GET, 'data' );
if ( $v_data < $hoje ) {
	$v_data = '';
}

// Atalhos de dia: hoje e os seis seguintes.
$dias = array();
for ( $i = 0; $i < 7; $i++ ) {
	$ts     = strtotime( "+{$i} day", strtotime( $hoje ) );
	$dias[] = array(
		'v' => gmdate( 'Y-m-d', $ts ),
		'l' => 0 === $i ? 'Hoje' : ( 1 === $i ? 'Amanhã' : ucfirst( date_i18n( 'D', $ts ) ) ),
		'd' => date_i18n( 'j M', $ts ),
	);
}

$entrar = wp_login_url( add_query_arg( array_filter( array( 'medico' => $sel_med ?: null ) ), get_permalink() ) );
$tel_1  = $telefones[0] ?? '';
?>
<?php if ( $feito ) : ?>

	<div class="cv-reserva__form cv-rapida-ok" role="status" tabindex="-1" data-rapida-ok>
		<span class="cv-rapida-ok__ic" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>
		<p class="cv-reserva__titulo">Pedido recebido, <?php echo esc_html( explode( ' ', trim( $feito['nome'] ) )[0] ); ?></p>
		<p class="cv-reserva__sub">Vamos ligar-lhe para <b><?php echo esc_html( $feito['telefone'] ); ?></b> a confirmar a consulta.</p>
		<dl class="cv-rapida-ok__resumo">
			<div><dt>Quando</dt><dd><?php echo esc_html( ucfirst( date_i18n( 'l, j \d\e F · H:i', strtotime( $feito['inicio'] ) ) ) ); ?></dd></div>
			<div><dt>Médico</dt><dd><?php echo esc_html( $feito['medico'] ); ?></dd></div>
			<?php if ( $feito['esp'] ) : ?><div><dt>Especialidade</dt><dd><?php echo esc_html( $feito['esp'] ); ?></dd></div><?php endif; ?>
		</dl>
		<div class="cv-reserva__acoes">
			<?php if ( $tel_1 ) : ?><a class="cv-btn" href="<?php echo esc_attr( Clinica_Homepage::tel_href( $tel_1 ) ); ?>">Ligar para a clínica</a><?php endif; ?>
			<a class="cv-btn cv-btn--vidro" href="<?php echo esc_url( remove_query_arg( array( 'rapida', 'mr', 'f' ) ) . '#marcar' ); ?>">Marcar outra consulta</a>
		</div>
		<p class="cv-reserva__nota">Traga um documento de identificação no dia. Se não puder vir, ligue-nos para libertar a hora.</p>
	</div>

<?php elseif ( ! $especialidades || ! $medicos ) : ?>

	<div class="cv-reserva__form" role="status">
		<p class="cv-reserva__titulo">Marcação online indisponível</p>
		<p class="cv-reserva__nota">Ainda não há especialidades ou médicos com horário. Ligue-nos e marcamos consigo.</p>
	</div>

<?php else : ?>

	<div class="cv-rapida" data-rapida>
		<div class="cv-rapida__abas" role="tablist" aria-label="Como prefere marcar">
			<button type="button" role="tab" id="mr-aba-1" aria-controls="mr-painel-1" aria-selected="true">Marcação rápida</button>
			<button type="button" role="tab" id="mr-aba-2" aria-controls="mr-painel-2" aria-selected="false" tabindex="-1">Já tenho conta</button>
		</div>

		<form method="post" class="cv-reserva__form cv-rapida__form" id="mr-painel-1" role="tabpanel" aria-labelledby="mr-aba-1" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" novalidate>
			<?php wp_nonce_field( 'clinica_marcacao_rapida', 'mr_nonce' ); ?>
			<input type="hidden" name="mr_action" value="marcar">
			<input type="hidden" name="inicio" id="mr-inicio">
			<p class="cv-rapida__armadilha" aria-hidden="true"><label>Não preencher <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></p>

			<p class="cv-reserva__titulo">Marque sem criar conta</p>
			<p class="cv-reserva__sub">Só o nome e o telefone. Ligamos a confirmar.</p>

			<?php if ( $erro ) : ?>
				<p class="cv-rapida__erro" role="alert"><?php echo esc_html( $erro ); ?></p>
			<?php endif; ?>

			<div class="cv-campo">
				<label for="mr-nome">Nome</label>
				<input type="text" id="mr-nome" name="nome" value="<?php echo esc_attr( $v['nome'] ?? '' ); ?>" autocomplete="name" required minlength="3" maxlength="120">
			</div>
			<div class="cv-campo">
				<label for="mr-tel">Telefone</label>
				<input type="tel" id="mr-tel" name="telefone" value="<?php echo esc_attr( $v['telefone'] ?? '' ); ?>" placeholder="84 123 4567" autocomplete="tel" inputmode="tel" required>
			</div>
			<div class="cv-campo">
				<label for="mr-esp">Especialidade</label>
				<select id="mr-esp" required>
					<option value="">Selecione</option>
					<?php foreach ( $especialidades as $e ) : ?>
						<option value="<?php echo (int) $e['id']; ?>" <?php selected( (int) $e['id'], $sel_esp ); ?>><?php echo esc_html( $e['nome'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="cv-campo">
				<label for="mr-med">Médico</label>
				<select id="mr-med" name="medico_id" required>
					<option value="">Escolha a especialidade</option>
					<?php foreach ( $medicos as $m ) : ?>
						<option value="<?php echo (int) $m['id']; ?>" data-esp="<?php echo (int) $m['especialidade_id']; ?>" <?php selected( (int) $m['id'], $sel_med ); ?>><?php echo esc_html( clinica_nome_medico( $m['nome'] ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<fieldset class="cv-campo cv-campo--largo cv-rapida__dias">
				<legend>Dia</legend>
				<div class="cv-rapida__chips" id="mr-dias">
					<?php foreach ( $dias as $d ) : ?>
						<button type="button" class="cv-rapida__dia<?php echo $d['v'] === $v_data ? ' is-on' : ''; ?>" data-v="<?php echo esc_attr( $d['v'] ); ?>" aria-pressed="<?php echo $d['v'] === $v_data ? 'true' : 'false'; ?>"><b><?php echo esc_html( $d['l'] ); ?></b><span><?php echo esc_html( $d['d'] ); ?></span></button>
					<?php endforeach; ?>
				</div>
				<input type="hidden" id="mr-data" value="<?php echo esc_attr( $v_data ); ?>">
			</fieldset>

			<fieldset class="cv-campo cv-campo--largo cv-rapida__horas">
				<legend>Hora <em id="mr-nhoras"></em></legend>
				<div class="cv-rapida__chips" id="mr-horas" aria-live="polite"><p class="cv-rapida__msg">Escolha a especialidade e o médico.</p></div>
				<button type="button" class="cv-rapida__primeira" id="mr-primeira" disabled>Primeira vaga livre</button>
			</fieldset>

			<div class="cv-campo cv-campo--largo">
				<label for="mr-motivo">Motivo <small>(opcional)</small></label>
				<textarea id="mr-motivo" name="motivo" rows="2" maxlength="500" placeholder="Ex.: febre há 3 dias, dor no peito, consulta de rotina…"><?php echo esc_textarea( $v['motivo'] ?? '' ); ?></textarea>
			</div>

			<label class="cv-rapida__aceito cv-campo--largo">
				<input type="checkbox" name="aceito" value="1" required>
				<span>Aceito que a clínica me ligue para confirmar a marcação.</span>
			</label>

			<button type="submit" class="cv-btn cv-reserva__enviar" id="mr-enviar">Pedir esta hora</button>
			<?php if ( $tel_1 ) : ?>
				<p class="cv-reserva__nota">Se for uma emergência, não espere: ligue já para <a href="<?php echo esc_attr( Clinica_Homepage::tel_href( $tel_1 ) ); ?>"><?php echo esc_html( $tel_1 ); ?></a>.</p>
			<?php endif; ?>
		</form>

		<div class="cv-reserva__form cv-rapida__conta" id="mr-painel-2" role="tabpanel" aria-labelledby="mr-aba-2" hidden>
			<p class="cv-reserva__titulo">Entrar na minha conta</p>
			<p class="cv-reserva__nota">Com conta, a marcação fica no seu histórico e pode editá-la ou cancelá-la sozinho. Depois de entrar, volta a esta página.</p>
			<div class="cv-reserva__acoes">
				<a class="cv-btn" href="<?php echo esc_url( $entrar ); ?>">Entrar ou criar conta</a>
				<?php if ( $tel_1 ) : ?><a class="cv-btn cv-btn--vidro" href="<?php echo esc_attr( Clinica_Homepage::tel_href( $tel_1 ) ); ?>">Prefiro ligar</a><?php endif; ?>
			</div>
		</div>
	</div>

<?php endif; ?>

<script>
( function () {
	var raiz = document.querySelector( '[data-rapida]' );
	var ok = document.querySelector( '[data-rapida-ok]' );
	if ( ok ) { ok.focus( { preventScroll: true } ); }
	if ( ! raiz ) { return; }

	// Abas: marcação rápida / já tenho conta (setas mudam de aba).
	var abas = raiz.querySelectorAll( '[role="tab"]' );
	function abrir( aba ) {
		abas.forEach( function ( a ) {
			var on = a === aba;
			a.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			a.tabIndex = on ? 0 : -1;
			document.getElementById( a.getAttribute( 'aria-controls' ) ).hidden = ! on;
		} );
	}
	abas.forEach( function ( a, i ) {
		a.addEventListener( 'click', function () { abrir( a ); } );
		a.addEventListener( 'keydown', function ( e ) {
			if ( 'ArrowRight' !== e.key && 'ArrowLeft' !== e.key ) { return; }
			var n = abas[ ( i + ( 'ArrowRight' === e.key ? 1 : abas.length - 1 ) ) % abas.length ];
			abrir( n ); n.focus();
		} );
	} );

	var form = document.getElementById( 'mr-painel-1' ),
		ajax = form.getAttribute( 'data-ajax' ),
		esp = document.getElementById( 'mr-esp' ),
		med = document.getElementById( 'mr-med' ),
		data = document.getElementById( 'mr-data' ),
		dias = document.getElementById( 'mr-dias' ),
		horas = document.getElementById( 'mr-horas' ),
		nh = document.getElementById( 'mr-nhoras' ),
		inicio = document.getElementById( 'mr-inicio' ),
		primeira = document.getElementById( 'mr-primeira' );

	function msg( t ) { horas.innerHTML = '<p class="cv-rapida__msg">' + t + '</p>'; nh.textContent = ''; }
	function slots( dia ) {
		return fetch( ajax + '?action=clinica_slots&medico=' + encodeURIComponent( med.value ) + '&data=' + encodeURIComponent( dia ) )
			.then( function ( r ) { return r.json(); } );
	}
	function marcarDia( v ) {
		data.value = v;
		dias.querySelectorAll( '.cv-rapida__dia' ).forEach( function ( b ) {
			var on = b.dataset.v === v;
			b.classList.toggle( 'is-on', on );
			b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
	}
	function desenhar( lista, escolher ) {
		inicio.value = '';
		if ( ! lista || ! lista.length ) { msg( 'Sem horas livres neste dia. Experimente outro dia ou "Primeira vaga livre".' ); return; }
		nh.textContent = lista.length + ( 1 === lista.length ? ' livre' : ' livres' );
		horas.innerHTML = '';
		lista.forEach( function ( h ) {
			var b = document.createElement( 'button' );
			b.type = 'button'; b.className = 'cv-rapida__hora'; b.textContent = h;
			b.setAttribute( 'aria-pressed', 'false' );
			b.addEventListener( 'click', function () {
				horas.querySelectorAll( '.cv-rapida__hora' ).forEach( function ( o ) { o.classList.remove( 'is-on' ); o.setAttribute( 'aria-pressed', 'false' ); } );
				b.classList.add( 'is-on' ); b.setAttribute( 'aria-pressed', 'true' );
				inicio.value = data.value + 'T' + h;
			} );
			horas.appendChild( b );
			if ( escolher === h ) { b.click(); }
		} );
	}
	function carregar() {
		primeira.disabled = ! med.value;
		if ( ! med.value ) { msg( 'Escolha a especialidade e o médico.' ); return; }
		if ( ! data.value ) { msg( 'Escolha o dia para ver as horas livres.' ); return; }
		msg( 'A procurar horas livres…' );
		slots( data.value ).then( function ( l ) { desenhar( l ); } ).catch( function () { msg( 'Não foi possível ver as horas. Tente de novo.' ); } );
	}
	function filtrar( auto ) {
		var v = esp.value, prim = null;
		Array.prototype.forEach.call( med.options, function ( o ) {
			if ( ! o.value ) { return; }
			var on = String( o.dataset.esp ) === String( v );
			o.hidden = ! on;
			if ( on && ! prim ) { prim = o; }
		} );
		med.options[0].textContent = v ? 'Selecione o médico' : 'Escolha a especialidade';
		if ( auto ) { med.value = prim ? prim.value : ''; }
	}

	esp.addEventListener( 'change', function () { filtrar( true ); carregar(); } );
	med.addEventListener( 'change', carregar );
	dias.querySelectorAll( '.cv-rapida__dia' ).forEach( function ( b ) {
		b.addEventListener( 'click', function () { marcarDia( b.dataset.v ); carregar(); } );
	} );

	// Primeira vaga livre: procura dia a dia (até 14 dias) e escolhe a primeira hora.
	primeira.addEventListener( 'click', function () {
		if ( ! med.value ) { return; }
		primeira.disabled = true;
		msg( 'A procurar a primeira vaga…' );
		var base = new Date( dias.querySelector( '.cv-rapida__dia' ).dataset.v + 'T00:00:00' ), i = 0; // a partir de hoje
		function proximo() {
			if ( i > 13 ) { msg( 'Sem vagas nas próximas duas semanas. Ligue-nos, por favor.' ); primeira.disabled = false; return; }
			var d = new Date( base ); d.setDate( base.getDate() + i ); i++;
			var v = d.getFullYear() + '-' + String( d.getMonth() + 1 ).padStart( 2, '0' ) + '-' + String( d.getDate() ).padStart( 2, '0' );
			slots( v ).then( function ( l ) {
				if ( l && l.length ) { marcarDia( v ); desenhar( l, l[0] ); primeira.disabled = false; return; }
				proximo();
			} ).catch( function () { msg( 'Não foi possível procurar. Tente de novo.' ); primeira.disabled = false; } );
		}
		proximo();
	} );

	// Validação antes de enviar: diz o que falta junto do campo.
	form.addEventListener( 'submit', function ( e ) {
		var falta = null;
		if ( form.nome.value.trim().length < 3 ) { falta = form.nome; }
		else if ( ! /^(\+?258|00258)?\s*8[2-7](\s*\d){7}$/.test( form.telefone.value.trim() ) ) { falta = form.telefone; }
		else if ( ! med.value ) { falta = esp.value ? med : esp; }
		else if ( ! inicio.value ) { falta = primeira; msg( 'Escolha uma hora livre (ou carregue em "Primeira vaga livre").' ); }
		else if ( ! form.aceito.checked ) { falta = form.aceito; }
		if ( falta ) {
			e.preventDefault();
			form.querySelectorAll( '.is-falta' ).forEach( function ( x ) { x.classList.remove( 'is-falta' ); } );
			( falta.closest( '.cv-campo, .cv-rapida__aceito' ) || falta ).classList.add( 'is-falta' );
			falta.focus();
		}
	} );

	if ( esp.value ) { filtrar( ! med.value ); }
	if ( med.value ) { carregar(); }
} )();
</script>
