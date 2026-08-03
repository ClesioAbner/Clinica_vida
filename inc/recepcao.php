<?php
/**
 * inc/recepcao.php
 *
 * Portal da Secretária / Receção (Fase 3 — Operação). Controlador PRG e helpers.
 * Reutiliza os motores existentes: Clinica_Repo (consultas/pacientes),
 * Clinica_Pagamentos (financeiro), Clinica_Contacto (mensagens). Cada ação é
 * protegida pela capability própria da receção (menor privilégio).
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Migração: coluna checkin_em em clinica_consultas (hora de chegada do
 * paciente). A receção confirma a PRESENÇA; o estado clínico ("realizada") é
 * exclusivo do médico.
 */
function clinica_recepcao_instalar(): void {
	if ( get_option( 'clinica_checkin_db' ) === '1' ) {
		return;
	}
	global $wpdb;
	$t   = Clinica_Repo::T_CONSULTAS;
	$col = $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE 'checkin_em'" );
	if ( ! $col ) {
		$wpdb->query( "ALTER TABLE {$t} ADD COLUMN checkin_em DATETIME NULL DEFAULT NULL AFTER status" );
	}
	update_option( 'clinica_checkin_db', '1' );
}
add_action( 'after_setup_theme', 'clinica_recepcao_instalar' );

/**
 * Migração: ficha do paciente — nº do BI e género em clinica_pacientes.
 * São dados de identificação (administrativos), geridos pela receção.
 */
function clinica_pacientes_instalar(): void {
	if ( get_option( 'clinica_pacientes_ficha_db' ) === '1' ) {
		return;
	}
	global $wpdb;
	$t = Clinica_Repo::T_PACIENTES;
	$cols = array(
		'bi'     => "ALTER TABLE {$t} ADD COLUMN bi VARCHAR(30) NULL DEFAULT NULL AFTER user_id",
		'genero' => "ALTER TABLE {$t} ADD COLUMN genero VARCHAR(1) NULL DEFAULT NULL AFTER data_nascimento",
	);
	foreach ( $cols as $col => $sql ) {
		if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$t} LIKE '{$col}'" ) ) {
			$wpdb->query( $sql );
		}
	}
	update_option( 'clinica_pacientes_ficha_db', '1' );
}
add_action( 'after_setup_theme', 'clinica_pacientes_instalar' );

/** Fila de atendimento: quem já chegou hoje e ainda não foi atendido. */
function clinica_recepcao_fila(): array {
	global $wpdb;
	$hoje = current_time( 'Y-m-d' );
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT c.id, c.paciente_id, c.duracao_min, c.inicio, c.checkin_em, c.status, e.nome AS esp_nome,
				u.display_name AS med_nome, pu.display_name AS pac_nome, md.sala AS sala,
				pr.alergias AS alergias,
				pg.valor AS pg_pendente
		 FROM " . Clinica_Repo::T_CONSULTAS . " c
		 JOIN " . Clinica_Repo::T_MEDICOS . " md ON c.medico_id = md.id
		 JOIN {$wpdb->users} u ON md.user_id = u.ID
		 JOIN " . Clinica_Repo::T_ESPECIALIDADES . " e ON md.especialidade_id = e.id
		 JOIN " . Clinica_Repo::T_PACIENTES . " p ON c.paciente_id = p.id
		 JOIN {$wpdb->users} pu ON p.user_id = pu.ID
		 LEFT JOIN " . Clinica_Processo::TABELA . " pr ON pr.paciente_id = c.paciente_id
		 LEFT JOIN " . Clinica_Pagamentos::TABELA . " pg ON pg.consulta_id = c.id AND pg.estado = 'pendente'
		 WHERE c.checkin_em IS NOT NULL AND DATE(c.inicio) = %s
		   AND c.status NOT IN ('realizada','cancelada','faltou')
		 ORDER BY c.checkin_em ASC",
		$hoje
	), ARRAY_A ) ?: array();
}

/** Todos os pacientes (para o seletor de marcação e a lista). */
function clinica_recepcao_pacientes(): array {
	global $wpdb;
	return $wpdb->get_results(
		"SELECT pa.id, u.display_name AS nome, u.user_email AS email, pa.bi, pa.genero,
				pa.telefone, pa.data_nascimento, pa.endereco,
				( SELECT COUNT(*) FROM " . Clinica_Repo::T_CONSULTAS . " c WHERE c.paciente_id = pa.id ) AS n_consultas
		 FROM " . Clinica_Repo::T_PACIENTES . " pa
		 JOIN {$wpdb->users} u ON pa.user_id = u.ID
		 ORDER BY u.display_name ASC",
		ARRAY_A
	) ?: array();
}

/** Nome (display_name) de um paciente pelo id da ficha. */
function clinica_recepcao_nome_paciente( int $paciente_id ): string {
	global $wpdb;
	return (string) $wpdb->get_var( $wpdb->prepare(
		"SELECT u.display_name FROM " . Clinica_Repo::T_PACIENTES . " pa JOIN {$wpdb->users} u ON pa.user_id = u.ID WHERE pa.id = %d",
		$paciente_id
	) );
}

/**
 * Recibo imprimível (documento autónomo). Só quem pode emitir recibos.
 * Imprime e termina o pedido — não usa o header/footer do tema.
 */
function clinica_recepcao_render_recibo( int $pagamento_id ): void {
	if ( ! current_user_can( 'clinica_emitir_recibo' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	$pg = Clinica_Pagamentos::obter( $pagamento_id );
	if ( ! $pg ) {
		wp_die( esc_html__( 'Recibo não encontrado.', 'clinica' ) );
	}

	$moeda  = class_exists( 'Clinica_Config' ) ? Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';
	$valor  = (float) $pg['valor'];
	$numero = Clinica_Pagamentos::numero_recibo( (int) $pg['id'] );
	$data   = $pg['atualizado_em'] ?: $pg['criado_em'];
	$pago   = ( 'pago' === $pg['estado'] );
	$fmoeda = static function ( $v ) use ( $moeda ) { return number_format( (float) $v, 2, ',', '.' ) . ' ' . $moeda; };

	$desc = 'Consulta médica';
	if ( ! empty( $pg['esp'] ) ) {
		$desc = 'Consulta de ' . $pg['esp'];
	}
	$sub = array();
	if ( ! empty( $pg['medico'] ) ) { $sub[] = $pg['medico']; }
	if ( ! empty( $pg['inicio'] ) ) { $sub[] = date_i18n( 'd/m/Y \à\s H:i', strtotime( $pg['inicio'] ) ); }
	if ( ! empty( $pg['duracao_min'] ) ) { $sub[] = (int) $pg['duracao_min'] . ' min'; }

	if ( class_exists( 'Clinica_Auditoria' ) ) {
		Clinica_Auditoria::registar( 'Receção: recibo emitido', $numero );
	}

	$estados = array( 'pendente' => 'Pendente', 'pago' => 'Pago', 'cancelado' => 'Cancelado', 'falhou' => 'Falhou', 'reembolsado' => 'Reembolsado' );
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $numero . ' — Clínica Vida' ); ?></title>
	<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
	<link rel="stylesheet" href="<?php echo esc_url( CLINICA_URI . '/assets/css/recibo.css?v=' . ( function_exists( 'clinica_asset_version' ) ? clinica_asset_version( 'assets/css/recibo.css' ) : '1' ) ); ?>">
</head>
<body>
	<div class="rb-bar">
		<a class="rb-bar__back" href="<?php echo esc_url( home_url( '/recepcao/' ) ); ?>">← Voltar à receção</a>
		<button class="rb-bar__print" onclick="window.print()">Imprimir recibo</button>
	</div>

	<div class="rb-page">
		<header class="rb-top">
			<div class="rb-brand">
				<span class="rb-brand__logo"><img src="<?php echo esc_url( CLINICA_URI . '/assets/img/logo2.png' ); ?>" alt=""></span>
				<span class="rb-brand__n">Clínica<br><b>Vida.</b></span>
			</div>
			<div class="rb-doc">
				<h1>RECIBO</h1>
				<p><?php echo esc_html( date_i18n( 'j \d\e F \d\e Y', strtotime( $data ) ) ); ?></p>
				<p class="rb-doc__n"><?php echo esc_html( $numero ); ?></p>
			</div>
		</header>

		<section class="rb-parties">
			<div>
				<h4>Morada</h4>
				<p>Av. Julius Nyerere, Sommerschield<br>Maputo, Moçambique</p>
				<p>(+258) 21 000 000</p>
			</div>
			<div>
				<h4>Para:</h4>
				<p><b><?php echo esc_html( $pg['paciente'] ?: 'Paciente' ); ?></b></p>
				<?php if ( ! empty( $pg['paciente_email'] ) ) : ?><p><?php echo esc_html( $pg['paciente_email'] ); ?></p><?php endif; ?>
				<?php if ( ! empty( $pg['paciente_tel'] ) ) : ?><p><?php echo esc_html( $pg['paciente_tel'] ); ?></p><?php endif; ?>
			</div>
		</section>

		<table class="rb-tbl">
			<thead>
				<tr><th>Descrição</th><th class="r">Preço unit.</th><th class="c">Qtd</th><th class="r">Total</th></tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<b><?php echo esc_html( $desc ); ?></b>
						<?php if ( $sub ) : ?><span><?php echo esc_html( implode( ' · ', $sub ) ); ?></span><?php endif; ?>
					</td>
					<td class="r"><?php echo esc_html( $fmoeda( $valor ) ); ?></td>
					<td class="c">1</td>
					<td class="r"><?php echo esc_html( $fmoeda( $valor ) ); ?></td>
				</tr>
			</tbody>
		</table>

		<section class="rb-sum">
			<div class="rb-note">
				<h4>Nota:</h4>
				<p>Documento emitido pela receção da Clínica Vida. Guarde este recibo como comprovativo do pagamento da sua consulta.</p>
			</div>
			<div class="rb-tot">
				<div class="rb-tot__l"><span>Subtotal</span><b><?php echo esc_html( $fmoeda( $valor ) ); ?></b></div>
				<div class="rb-tot__l"><span>Desconto</span><b>0,00 <?php echo esc_html( $moeda ); ?></b></div>
				<div class="rb-tot__t"><span><?php echo $pago ? 'TOTAL PAGO' : 'TOTAL A PAGAR'; ?></span><b><?php echo esc_html( $fmoeda( $valor ) ); ?></b></div>
			</div>
		</section>

		<p class="rb-thanks">Obrigado pela sua preferência.</p>

		<footer class="rb-foot">
			<div>
				<h4>Dúvidas?</h4>
				<p>Email: geral@clinicavida.co.mz</p>
				<p>Telefone: (+258) 21 000 000</p>
			</div>
			<div>
				<h4>Pagamento</h4>
				<p>Método: <?php echo esc_html( strtoupper( $pg['metodo'] ?: 'M-PESA' ) ); ?></p>
				<p>Referência: <?php echo esc_html( $pg['referencia'] ?: '—' ); ?></p>
				<p>Estado: <?php echo esc_html( $estados[ $pg['estado'] ] ?? $pg['estado'] ); ?></p>
			</div>
			<div>
				<h4>Termos</h4>
				<p>Recibo válido sem assinatura. Emitido eletronicamente pelo sistema da Clínica Vida em <?php echo esc_html( date_i18n( 'd/m/Y H:i' ) ); ?>.</p>
			</div>
		</footer>
	</div>
</body>
</html>
	<?php
	exit;
}

/**
 * Ficha administrativa do paciente em página imprimível (PDF via print), no mesmo
 * registo visual do recibo. Só dados administrativos + segurança clínica (grupo
 * sanguíneo/alergias) — nunca diagnósticos nem conteúdo do prontuário.
 */
function clinica_recepcao_render_ficha( int $paciente_id ): void {
	// A receção emite a ficha administrativa; o médico também a consulta
	// (continuidade de cuidados), por isso aceita qualquer uma das capacidades.
	if ( ! current_user_can( 'clinica_cadastrar_paciente' ) && ! current_user_can( 'clinica_ver_processo' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
	$p = Clinica_Repo::paciente_ficha( $paciente_id );
	if ( ! $p ) {
		wp_die( esc_html__( 'Ficha não encontrada.', 'clinica' ) );
	}

	$proc   = class_exists( 'Clinica_Processo' ) ? Clinica_Processo::do_paciente( $paciente_id ) : null;
	$cons   = Clinica_Repo::historico_do_paciente( $paciente_id );
	$pgs    = Clinica_Pagamentos::do_paciente( $paciente_id );
	$moeda  = class_exists( 'Clinica_Config' ) ? Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';
	$idade  = Clinica_Repo::idade( $p['data_nascimento'] ?? null );
	$nasc   = ( ! empty( $p['data_nascimento'] ) && '0000-00-00' !== $p['data_nascimento'] ) ? date_i18n( 'd/m/Y', strtotime( $p['data_nascimento'] ) ) : '—';
	$g      = $p['genero'] ?? '';
	$gen    = 'F' === $g ? 'Feminino' : ( 'M' === $g ? 'Masculino' : '—' );
	$numero = $proc['numero'] ?? '—';
	$labels = array( 'pendente' => 'Pendente', 'confirmada' => 'Confirmada', 'realizada' => 'Realizada', 'cancelada' => 'Cancelada', 'faltou' => 'Faltou' );
	$fmoeda = static function ( $v ) use ( $moeda ) { return number_format( (float) $v, 2, ',', '.' ) . ' ' . $moeda; };

	$total_pago = 0.0;
	foreach ( $pgs as $pg ) {
		if ( 'pago' === $pg['estado'] ) { $total_pago += (float) $pg['valor']; }
	}

	if ( class_exists( 'Clinica_Auditoria' ) ) {
		Clinica_Auditoria::registar( 'Receção: ficha do paciente visualizada', $p['nome'] );
	}
	ob_start();
	$agora_doc = date_i18n( 'd/m/Y \à\s H\hi' );
	$st_marc   = static function ( $s ) {
		if ( 'realizada' === $s ) { return 'fx-st--ok'; }
		if ( 'faltou' === $s || 'cancelada' === $s ) { return 'fx-st--bad'; }
		return 'fx-st--pend';
	};
	echo clinica_pdf_topo( 'Ficha do Paciente', 'Processo ' . $numero );
	?>
	<p class="doc-crumb">Receção › Pacientes › <b><?php echo esc_html( $p['nome'] ); ?></b></p>

	<?php
	echo clinica_pdf_meta( array(
		array( 'Nome', $p['nome'] ),
		array( 'Nº do BI', $p['bi'] ?: 'Não registado' ),
		array( 'Nascimento', $nasc ),
		array( 'Idade', null !== $idade ? $idade . ' anos' : 'Não registada' ),
		array( 'Género', $gen ),
		array( 'Telefone', $p['telefone'] ?: 'Não registado' ),
		array( 'Email', $p['email'] ),
		array( 'Morada', $p['endereco'] ?: 'Não registada' ),
		array( 'Emitido', $agora_doc ),
	) );
	?>

		<?php if ( $proc && ! empty( $proc['alergias'] ) ) : ?>
			<div class="doc-alerta"><b>Alergias:</b> <?php echo esc_html( $proc['alergias'] ); ?></div>
		<?php endif; ?>

		<div class="fx-sec"><h3>Marcações</h3><span><?php echo count( $cons ); ?> registo(s)</span></div>
		<?php if ( $cons ) : ?>
			<table class="fx-tbl">
				<thead><tr><th>Data</th><th>Sector · Médico</th><th>Chegada</th><th>Estado</th></tr></thead>
				<tbody>
					<?php foreach ( $cons as $h ) : ?>
						<tr>
							<td><b><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $h['inicio'] ) ) ); ?></b><span class="fx-sub"><?php echo esc_html( date_i18n( 'H:i', strtotime( $h['inicio'] ) ) ); ?></span></td>
							<td><b><?php echo esc_html( $h['esp_nome'] ); ?></b><span class="fx-sub"><?php echo esc_html( $h['med_nome'] ); ?></span></td>
							<td><?php echo ! empty( $h['checkin_em'] ) ? esc_html( date_i18n( 'H:i', strtotime( $h['checkin_em'] ) ) ) : '—'; ?></td>
							<td><span class="fx-st <?php echo esc_attr( $st_marc( $h['status'] ) ); ?>"><?php echo esc_html( $labels[ $h['status'] ] ?? $h['status'] ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="rb-note-p">Sem marcações registadas.</p>
		<?php endif; ?>

		<div class="fx-sec"><h3>Pagamentos</h3><span>Total pago: <?php echo esc_html( $fmoeda( $total_pago ) ); ?></span></div>
		<?php if ( $pgs ) : ?>
			<table class="fx-tbl">
				<thead><tr><th>Recibo</th><th>Data</th><th>Método</th><th>Estado</th><th class="r">Valor (<?php echo esc_html( $moeda ); ?>)</th></tr></thead>
				<tbody>
					<?php foreach ( $pgs as $pg ) :
						$pago = 'pago' === $pg['estado'];
					?>
						<tr>
							<td><b><?php echo esc_html( Clinica_Pagamentos::numero_recibo( (int) $pg['id'] ) ); ?></b></td>
							<td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $pg['criado_em'] ) ) ); ?></td>
							<td><?php echo $pg['metodo'] ? esc_html( strtoupper( (string) $pg['metodo'] ) ) : '—'; ?></td>
							<td><span class="fx-st <?php echo $pago ? 'fx-st--ok' : 'fx-st--pend'; ?>"><?php echo esc_html( ucfirst( (string) $pg['estado'] ) ); ?></span></td>
							<td class="r"><?php echo esc_html( number_format( (float) $pg['valor'], 2, ',', '.' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<div class="fx-total"><span>TOTAL PAGO</span><b><?php echo esc_html( $fmoeda( $total_pago ) ); ?></b></div>
		<?php else : ?>
			<p class="rb-note-p">Sem pagamentos registados.</p>
		<?php endif; ?>

		<div class="fx-footer">
			<span>Documento gerado em <?php echo esc_html( $agora_doc ); ?></span>
			<span>Clínica Vida · Ficha administrativa · Só dados administrativos</span>
		</div>
	<?php
	$corpo = ob_get_clean();

	clinica_pdf_saida(
		'Ficha do paciente ' . $p['nome'],
		'ficha-' . $numero . '-' . $p['nome'],
		$corpo,
		home_url( '/recepcao/' )
	);
}

/** Controlador PRG do portal da receção. */
function clinica_controller_recepcao(): void {
	if ( empty( $_POST['rc_action'] ) ) {
		return;
	}
	$acao = sanitize_key( $_POST['rc_action'] );

	// ---- Confirmar marcação / faltou (NUNCA "realizada": isso é do médico) --
	if ( 'checkin' === $acao ) {
		if ( ! current_user_can( 'clinica_confirmar_presenca' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_checkin' ) ) {
			clinica_redirect( 'negado' );
		}
		$id   = Clinica_Input::inteiro( $_POST, 'id' );
		$novo = Clinica_Input::chave( $_POST, 'novo_estado' );
		// 'realizada' está deliberadamente fora: só o médico confirma o ato clínico.
		if ( ! in_array( $novo, array( 'confirmada', 'faltou' ), true ) ) {
			clinica_redirect( 'negado' );
		}
		Clinica_Repo::mudar_estado( $id, $novo );
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: consulta ' . $novo, '#' . $id );
		}
		clinica_redirect( 'estado' );
	}

	// ---- Confirmação de presença (check-in / chegada) -----------------------
	if ( 'presenca' === $acao ) {
		if ( ! current_user_can( 'clinica_confirmar_presenca' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_checkin' ) ) {
			clinica_redirect( 'negado' );
		}
		$id = Clinica_Input::inteiro( $_POST, 'id' );
		list( $ok, $code ) = Clinica_Repo::registar_checkin( $id );
		if ( $ok && class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: presença confirmada', '#' . $id );
		}
		clinica_redirect( $code );
	}

	// ---- Reagendar consulta -------------------------------------------------
	if ( 'reagendar' === $acao ) {
		if ( ! current_user_can( 'clinica_gerir_agenda' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_reag' ) ) {
			clinica_redirect( 'negado' );
		}
		$id     = Clinica_Input::inteiro( $_POST, 'id' );
		$medico = Clinica_Input::inteiro( $_POST, 'medico_id' );
		$inicio = Clinica_Repo::normalizar_inicio( (string) ( $_POST['inicio'] ?? '' ) );
		if ( ! $id || ! $medico || ! $inicio ) {
			clinica_redirect( 'invalido' );
		}
		if ( ! Clinica_Repo::e_futuro( $inicio ) ) {
			clinica_redirect( 'passado' );
		}
		list( $ok, $code ) = Clinica_Repo::reagendar( $id, $medico, $inicio );
		if ( $ok && class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: consulta reagendada', '#' . $id );
		}
		clinica_redirect( $code );
	}

	// ---- Cancelar consulta --------------------------------------------------
	if ( 'cancelar' === $acao ) {
		if ( ! current_user_can( 'clinica_gerir_agenda' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_canc' ) ) {
			clinica_redirect( 'negado' );
		}
		$id = Clinica_Input::inteiro( $_POST, 'id' );
		list( $ok, $code ) = Clinica_Repo::cancelar_staff( $id, Clinica_Input::texto( $_POST, 'motivo' ) ?: 'Cancelada na receção' );
		if ( $ok && class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: consulta cancelada', '#' . $id );
		}
		clinica_redirect( $code );
	}

	// ---- Atualizar dados do paciente (administrativo, nunca clínico) --------
	if ( 'paciente_editar' === $acao ) {
		if ( ! current_user_can( 'clinica_cadastrar_paciente' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_pac_ed' ) ) {
			clinica_redirect( 'negado' );
		}
		$pid = Clinica_Input::inteiro( $_POST, 'id' );
		if ( ! $pid ) {
			clinica_redirect( 'invalido' );
		}
		Clinica_Repo::guardar_perfil_paciente( $pid, array(
			'bi'              => Clinica_Input::texto( $_POST, 'bi' ),
			'genero'          => Clinica_Input::texto( $_POST, 'genero' ),
			'telefone'        => Clinica_Input::texto( $_POST, 'telefone' ),
			'endereco'        => Clinica_Input::texto( $_POST, 'endereco' ),
			'data_nascimento' => Clinica_Input::texto( $_POST, 'data_nascimento' ),
		) );
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: paciente atualizado', '#' . $pid );
		}
		clinica_redirect( 'rc_pac_ed_ok' );
	}

	// ---- Marcar consulta para um paciente -----------------------------------
	if ( 'marcar' === $acao ) {
		if ( ! current_user_can( 'clinica_marcar' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_marcar' ) ) {
			clinica_redirect( 'negado' );
		}
		$paciente_id = Clinica_Input::inteiro( $_POST, 'paciente_id' );
		$medico_id   = Clinica_Input::inteiro( $_POST, 'medico_id' );
		$inicio      = Clinica_Repo::normalizar_inicio( (string) ( $_POST['inicio'] ?? '' ) );
		if ( ! $paciente_id || ! $medico_id || ! $inicio ) {
			clinica_redirect( 'invalido' );
		}
		if ( ! Clinica_Repo::e_futuro( $inicio ) ) {
			clinica_redirect( 'passado' );
		}
		$nome = clinica_recepcao_nome_paciente( $paciente_id );
		list( $ok, $code ) = Clinica_Repo::criar( array(
			'nome_cliente' => $nome,
			'paciente_id'  => $paciente_id,
			'medico_id'    => $medico_id,
			'inicio'       => $inicio,
			'observacoes'  => Clinica_Input::texto( $_POST, 'observacoes' ),
		) );
		if ( $ok && class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: consulta marcada', $nome );
		}
		clinica_redirect( $code );
	}

	// ---- Registar novo paciente ---------------------------------------------
	if ( 'paciente_criar' === $acao ) {
		if ( ! current_user_can( 'clinica_cadastrar_paciente' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_pac' ) ) {
			clinica_redirect( 'negado' );
		}
		$nome  = Clinica_Input::texto( $_POST, 'nome' );
		$email = Clinica_Input::email( $_POST, 'email' );
		if ( '' === $nome || '' === $email ) {
			clinica_redirect( 'rc_pac_dados' );
		}
		if ( email_exists( $email ) ) {
			clinica_redirect( 'rc_pac_existe' );
		}
		$pass = (string) ( $_POST['pwd'] ?? '' );
		if ( strlen( $pass ) < 6 ) {
			$pass = wp_generate_password( 10, false );
		}
		$base  = sanitize_user( current( explode( '@', $email ) ), true ) ?: 'paciente';
		$login = $base; $i = 1;
		while ( username_exists( $login ) ) { $login = $base . $i; $i++; }
		$uid = wp_insert_user( array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $pass,
			'display_name' => $nome,
			'first_name'   => $nome,
			'role'         => 'clinica_paciente',
		) );
		if ( is_wp_error( $uid ) ) {
			clinica_redirect( 'rc_pac_dados' );
		}
		$pid = Clinica_Repo::ensure_paciente( (int) $uid );
		Clinica_Repo::guardar_perfil_paciente( $pid, array(
			'bi'              => Clinica_Input::texto( $_POST, 'bi' ),
			'genero'          => Clinica_Input::texto( $_POST, 'genero' ),
			'telefone'        => Clinica_Input::texto( $_POST, 'telefone' ),
			'endereco'        => Clinica_Input::texto( $_POST, 'endereco' ),
			'data_nascimento' => Clinica_Input::texto( $_POST, 'data_nascimento' ),
		) );
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: paciente registado', $nome );
		}
		clinica_redirect( 'rc_pac_ok' );
	}

	// ---- Nova consulta (walk-in): registar a pessoa + marcar a consulta -----
	if ( 'nova_consulta' === $acao ) {
		if ( ! current_user_can( 'clinica_cadastrar_paciente' ) || ! current_user_can( 'clinica_marcar' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_nc' ) ) {
			clinica_redirect( 'negado' );
		}
		$medico_id = Clinica_Input::inteiro( $_POST, 'medico_id' );
		$inicio    = Clinica_Repo::normalizar_inicio( (string) ( $_POST['inicio'] ?? '' ) );
		$sintomas  = Clinica_Input::texto( $_POST, 'sintomas' );
		$reuse     = Clinica_Input::inteiro( $_POST, 'reuse_paciente_id' );
		if ( ! $medico_id || ! $inicio ) {
			clinica_redirect( 'nc_invalido' );
		}
		if ( ! Clinica_Repo::e_futuro( $inicio ) ) {
			clinica_redirect( 'passado' );
		}

		// Reaproveitar ficha existente OU registar a pessoa nova.
		if ( $reuse ) {
			$pid  = $reuse;
			$nome = clinica_recepcao_nome_paciente( $pid );
			if ( '' === $nome ) {
				clinica_redirect( 'nc_invalido' );
			}
		} else {
			$nome  = Clinica_Input::texto( $_POST, 'nome' );
			$email = Clinica_Input::email( $_POST, 'email' );
			if ( '' === $nome || '' === $email ) {
				clinica_redirect( 'nc_dados' );
			}
			if ( email_exists( $email ) ) {
				clinica_redirect( 'nc_existe' );
			}
			$pass  = wp_generate_password( 10, false );
			$base  = sanitize_user( current( explode( '@', $email ) ), true ) ?: 'paciente';
			$login = $base; $i = 1;
			while ( username_exists( $login ) ) { $login = $base . $i; $i++; }
			$uid = wp_insert_user( array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $nome,
				'first_name'   => $nome,
				'role'         => 'clinica_paciente',
			) );
			if ( is_wp_error( $uid ) ) {
				clinica_redirect( 'nc_dados' );
			}
			$pid = Clinica_Repo::ensure_paciente( (int) $uid );
			Clinica_Repo::guardar_perfil_paciente( $pid, array(
				'bi'              => Clinica_Input::texto( $_POST, 'bi' ),
				'genero'          => Clinica_Input::texto( $_POST, 'genero' ),
				'telefone'        => Clinica_Input::texto( $_POST, 'telefone' ),
				'endereco'        => Clinica_Input::texto( $_POST, 'endereco' ),
				'data_nascimento' => Clinica_Input::texto( $_POST, 'data_nascimento' ),
			) );
		}

		list( $ok, $code ) = Clinica_Repo::criar( array(
			'nome_cliente' => $nome,
			'paciente_id'  => $pid,
			'medico_id'    => $medico_id,
			'inicio'       => $inicio,
			'observacoes'  => $sintomas,
		) );
		if ( $ok && class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: nova consulta (walk-in)', $nome );
		}
		clinica_redirect( $ok ? 'nc_ok' : $code );
	}

	// ---- Confirmar pagamento (receção financeira) ---------------------------
	if ( 'pagamento' === $acao ) {
		if ( ! current_user_can( 'clinica_confirmar_pagamento' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_pg' ) ) {
			clinica_redirect( 'negado' );
		}
		$id = Clinica_Input::inteiro( $_POST, 'id' );
		Clinica_Pagamentos::definir_estado( $id, 'pago', Clinica_Input::texto( $_POST, 'referencia' ) );
		if ( class_exists( 'Clinica_Auditoria' ) ) {
			Clinica_Auditoria::registar( 'Receção: pagamento confirmado', '#' . $id );
		}
		clinica_redirect( 'pg_ok' );
	}

	// ---- Cobrar via M-Pesa: empurra o pedido de PIN para o telemóvel --------
	// Síncrono por natureza: o cliente tem de introduzir o PIN. Só marca 'pago'
	// se o M-Pesa confirmar (INS-0); qualquer recusa mantém o pagamento pendente.
	if ( 'pg_cobrar' === $acao ) {
		if ( ! current_user_can( 'clinica_confirmar_pagamento' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_pg' ) ) {
			clinica_redirect( 'negado' );
		}
		$id  = Clinica_Input::inteiro( $_POST, 'id' );
		$tel = Clinica_Input::texto( $_POST, 'telefone' );
		if ( ! $id || '' === $tel ) {
			clinica_redirect( 'pg_mp_tel' );
		}
		$r = Clinica_Pagamentos::cobrar( $id, $tel );
		if ( $r->ok() ) {
			clinica_redirect( 'pg_mp_ok' );
		}
		clinica_flash_dinamico( $r->erro() );
		clinica_redirect( 'pg_mp_falhou' );
	}

	// ---- Marcar mensagem como lida ------------------------------------------
	if ( 'msg_lida' === $acao ) {
		if ( ! current_user_can( 'clinica_ver_mensagens' ) || ! Clinica_Input::nonce_ok( $_POST, 'rc_nonce', 'rc_msg' ) ) {
			clinica_redirect( 'negado' );
		}
		Clinica_Contacto::marcar_lida( Clinica_Input::inteiro( $_POST, 'id' ) );
		clinica_redirect( 'rc_msg_lida' );
	}
}
