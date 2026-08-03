<?php
/**
 * inc/relatorios.php
 *
 * Relatórios executivos (Direção) e a sua exportação em PDF e CSV.
 * Só leitura consolidada — financeiro, por médico e pacientes por período.
 * A geração de PDF reutiliza o pipeline de inc/pdf.php (Dompdf).
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Relatorios {

	/** Agrupamentos aceites para os relatórios por período. */
	const GRUPOS = array( 'dia' => 'Diário', 'semana' => 'Semanal', 'mes' => 'Mensal' );

	/** Expressão SQL e rótulo de um agrupamento temporal sobre uma coluna. */
	private static function bucket( string $col, string $grupo ): array {
		switch ( $grupo ) {
			case 'dia':
				return array( "DATE({$col})", 12 );      // últimos 12 dias
			case 'semana':
				return array( "YEARWEEK({$col}, 3)", 12 ); // últimas 12 semanas
			default:
				return array( "DATE_FORMAT({$col}, '%Y-%m')", 12 ); // últimos 12 meses
		}
	}

	/** Rótulo legível de uma chave de período. */
	private static function rotulo( string $chave, string $grupo ): string {
		if ( 'dia' === $grupo ) {
			return date_i18n( 'd/m/Y', strtotime( $chave ) );
		}
		if ( 'mes' === $grupo ) {
			return ucfirst( date_i18n( 'F Y', strtotime( $chave . '-01' ) ) );
		}
		// Semana no formato YYYYWW (ISO).
		$ano  = (int) substr( $chave, 0, 4 );
		$sem  = (int) substr( $chave, 4 );
		$dt   = new DateTime();
		$dt->setISODate( $ano, $sem );
		return 'Sem. ' . $sem . ' · ' . $dt->format( 'd/m' );
	}

	/** Sequência das últimas N chaves de período (com zeros incluídos). */
	private static function chaves( string $grupo, int $n ): array {
		$out = array();
		for ( $i = $n - 1; $i >= 0; $i-- ) {
			if ( 'dia' === $grupo ) {
				$out[] = date( 'Y-m-d', strtotime( "-{$i} day" ) );
			} elseif ( 'semana' === $grupo ) {
				$out[] = date( 'oW', strtotime( "-{$i} week" ) );
			} else {
				$out[] = date( 'Y-m', strtotime( "first day of -{$i} month" ) );
			}
		}
		return $out;
	}

	/** Relatório financeiro: receita, nº de pagamentos e ticket médio por período. */
	public static function financeiro( string $grupo = 'mes' ): array {
		global $wpdb;
		$P = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::TABELA : 'clinica_pagamentos';
		list( $expr, $n ) = self::bucket( 'criado_em', $grupo );

		$rows = $wpdb->get_results(
			"SELECT {$expr} k, COALESCE(SUM(valor),0) receita, COUNT(*) pagamentos
			 FROM {$P} WHERE estado='pago' AND criado_em >= (CURDATE() - INTERVAL 400 DAY)
			 GROUP BY k",
			ARRAY_A
		) ?: array();
		$mapa = array();
		foreach ( $rows as $r ) {
			$mapa[ (string) $r['k'] ] = $r;
		}

		$linhas = array();
		foreach ( self::chaves( $grupo, $n ) as $k ) {
			$r   = $mapa[ $k ] ?? array( 'receita' => 0, 'pagamentos' => 0 );
			$rec = (float) $r['receita'];
			$pag = (int) $r['pagamentos'];
			$linhas[] = array(
				self::rotulo( $k, $grupo ),
				$rec,
				$pag,
				$pag > 0 ? $rec / $pag : 0,
			);
		}
		return array(
			'titulo' => 'Relatório financeiro (' . self::GRUPOS[ $grupo ] . ')',
			'cols'   => array( 'Período', 'Receita', 'Pagamentos', 'Ticket médio' ),
			'tipos'  => array( 't', 'v', 'n', 'v' ),
			'linhas' => $linhas,
		);
	}

	/** Relatório por médico: consultas e receita nos últimos 90 dias. */
	public static function medicos(): array {
		global $wpdb;
		$C = Clinica_Repo::T_CONSULTAS;
		$M = Clinica_Repo::T_MEDICOS;
		$E = Clinica_Repo::T_ESPECIALIDADES;
		$P = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::TABELA : 'clinica_pagamentos';

		$rows = $wpdb->get_results(
			"SELECT u.display_name nome, e.nome esp,
			        COUNT(DISTINCT c.id) consultas, COALESCE(SUM(pg.valor),0) receita
			 FROM {$M} md
			 JOIN {$wpdb->users} u ON md.user_id=u.ID
			 JOIN {$E} e ON md.especialidade_id=e.id
			 LEFT JOIN {$C} c ON c.medico_id=md.id AND c.inicio >= (CURDATE() - INTERVAL 90 DAY)
			 LEFT JOIN {$P} pg ON pg.consulta_id=c.id AND pg.estado='pago'
			 WHERE md.ativo=1
			 GROUP BY md.id, u.display_name, e.nome
			 ORDER BY u.display_name ASC",
			ARRAY_A
		) ?: array();

		// Ordem alfabética pelo nome real (ignorando o título Dr./Dra.).
		usort( $rows, static function ( $a, $b ) {
			$na = preg_replace( '/^\s*(Dra?|Dr\(a\))\.?\s*/i', '', $a['nome'] );
			$nb = preg_replace( '/^\s*(Dra?|Dr\(a\))\.?\s*/i', '', $b['nome'] );
			return strcoll( mb_strtolower( $na ), mb_strtolower( $nb ) );
		} );

		$linhas = array();
		foreach ( $rows as $r ) {
			$c   = (int) $r['consultas'];
			$rec = (float) $r['receita'];
			$linhas[] = array( $r['nome'], $r['esp'], $c, $rec, $c > 0 ? $rec / $c : 0 );
		}
		return array(
			'titulo' => 'Relatório por médico (90 dias)',
			'cols'   => array( 'Médico', 'Especialidade', 'Consultas', 'Receita', 'Ticket médio' ),
			'tipos'  => array( 't', 't', 'n', 'v', 'v' ),
			'linhas' => $linhas,
		);
	}

	/** Relatório de pacientes: novos por período e consultas realizadas. */
	public static function pacientes( string $grupo = 'semana' ): array {
		global $wpdb;
		$C  = Clinica_Repo::T_CONSULTAS;
		$PA = Clinica_Repo::T_PACIENTES;
		list( $expr_p, $n ) = self::bucket( 'created_at', $grupo );
		list( $expr_c )     = self::bucket( 'inicio', $grupo );

		$novos = array();
		foreach ( (array) $wpdb->get_results(
			"SELECT {$expr_p} k, COUNT(*) n FROM {$PA} WHERE created_at >= (CURDATE() - INTERVAL 400 DAY) GROUP BY k",
			ARRAY_A
		) as $r ) {
			$novos[ (string) $r['k'] ] = (int) $r['n'];
		}
		$cons = array();
		foreach ( (array) $wpdb->get_results(
			"SELECT {$expr_c} k, COUNT(*) n FROM {$C} WHERE status='realizada' AND inicio >= (CURDATE() - INTERVAL 400 DAY) GROUP BY k",
			ARRAY_A
		) as $r ) {
			$cons[ (string) $r['k'] ] = (int) $r['n'];
		}

		$linhas = array();
		foreach ( self::chaves( $grupo, $n ) as $k ) {
			$linhas[] = array( self::rotulo( $k, $grupo ), (int) ( $novos[ $k ] ?? 0 ), (int) ( $cons[ $k ] ?? 0 ) );
		}
		return array(
			'titulo' => 'Relatório de pacientes (' . self::GRUPOS[ $grupo ] . ')',
			'cols'   => array( 'Período', 'Novos pacientes', 'Consultas realizadas' ),
			'tipos'  => array( 't', 'n', 'n' ),
			'linhas' => $linhas,
		);
	}

	/** Resolve um relatório pelo tipo + agrupamento. */
	public static function obter( string $tipo, string $grupo ): array {
		if ( 'medicos' === $tipo ) {
			return self::medicos();
		}
		if ( 'pacientes' === $tipo ) {
			return self::pacientes( $grupo );
		}
		return self::financeiro( $grupo );
	}
}

/**
 * Exporta um relatório em PDF ou CSV. Disparado por ?relatorio=TIPO na página
 * da Direção, antes de get_header(). Termina o pedido.
 */
function clinica_relatorio_exportar(): void {
	if ( empty( $_GET['relatorio'] ) || ! current_user_can( 'clinica_ver_relatorios' ) ) {
		return;
	}
	$tipo  = sanitize_key( $_GET['relatorio'] );
	$grupo = isset( $_GET['g'] ) && isset( Clinica_Relatorios::GRUPOS[ sanitize_key( $_GET['g'] ) ] ) ? sanitize_key( $_GET['g'] ) : 'mes';
	$fmt   = ( isset( $_GET['fmt'] ) && in_array( $_GET['fmt'], array( 'csv', 'xls' ), true ) ) ? sanitize_key( $_GET['fmt'] ) : 'pdf';
	$rel   = Clinica_Relatorios::obter( $tipo, $grupo );
	$moeda = class_exists( 'Clinica_Config' ) ? Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';

	$fmoeda = static function ( $v ) use ( $moeda ) {
		return number_format( (float) $v, 0, ',', ' ' ) . ' ' . $moeda;
	};
	$nome = sanitize_file_name( $tipo . '-' . $grupo . '-' . date_i18n( 'Y-m-d' ) );

	if ( class_exists( 'Clinica_Auditoria' ) ) {
		Clinica_Auditoria::registar( 'Direção: relatório exportado', $rel['titulo'] . ' · ' . strtoupper( $fmt ) );
	}

	// ---- CSV (abre no Excel) ----
	if ( 'csv' === $fmt ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $nome . '.csv"' );
		echo "\xEF\xBB\xBF"; // BOM para o Excel reconhecer UTF-8
		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, $rel['cols'], ';' );
		foreach ( $rel['linhas'] as $linha ) {
			$fmt_linha = array();
			foreach ( $linha as $i => $cel ) {
				$t = $rel['tipos'][ $i ] ?? 't';
				$fmt_linha[] = ( 'v' === $t ) ? number_format( (float) $cel, 2, ',', '' ) : $cel;
			}
			fputcsv( $out, $fmt_linha, ';' );
		}
		fclose( $out );
		exit;
	}

	// ---- Excel real (.xlsx via Office Open XML) ----
	if ( 'xls' === $fmt && class_exists( 'Clinica_Xlsx' ) ) {
		Clinica_Xlsx::enviar( $rel['titulo'], $rel['cols'], $rel['linhas'], $rel['tipos'], $nome );
	}

	// ---- PDF (via Dompdf) ----
	if ( ! function_exists( 'clinica_pdf_saida' ) ) {
		wp_die( esc_html__( 'Exportação PDF indisponível.', 'clinica' ) );
	}
	$agora = date_i18n( 'd/m/Y \à\s H\hi' );
	ob_start();
	echo clinica_pdf_topo( 'Relatório executivo', $rel['titulo'] );
	?>
	<p class="doc-crumb">Direção · <b><?php echo esc_html( $rel['titulo'] ); ?></b></p>
	<div class="fx-sec"><h3><?php echo esc_html( $rel['titulo'] ); ?></h3><span><?php echo count( $rel['linhas'] ); ?> linhas</span></div>
	<table class="fx-tbl">
		<thead>
			<tr><?php foreach ( $rel['cols'] as $i => $c ) : ?><th<?php echo in_array( $rel['tipos'][ $i ] ?? 't', array( 'v', 'n' ), true ) ? ' style="text-align:right"' : ''; ?>><?php echo esc_html( $c ); ?></th><?php endforeach; ?></tr>
		</thead>
		<tbody>
			<?php foreach ( $rel['linhas'] as $linha ) : ?>
				<tr>
					<?php foreach ( $linha as $i => $cel ) :
						$t = $rel['tipos'][ $i ] ?? 't';
						$val = ( 'v' === $t ) ? $fmoeda( $cel ) : ( 'n' === $t ? number_format_i18n( (int) $cel ) : $cel );
						$al  = in_array( $t, array( 'v', 'n' ), true ) ? ' style="text-align:right"' : '';
					?>
						<td<?php echo $al; ?>><?php echo esc_html( $val ); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="fx-footer">
		<span>Documento gerado em <?php echo esc_html( $agora ); ?></span>
		<span>Clínica Vida · Relatório executivo · Confidencial</span>
	</div>
	<?php
	$corpo = ob_get_clean();
	clinica_pdf_saida( $rel['titulo'], $nome, $corpo, home_url( '/direcao/' ) );
}
