<?php
/**
 * inc/xlsx.php
 *
 * Gerador de ficheiros Excel REAIS (.xlsx — Office Open XML), sem bibliotecas
 * externas. Um .xlsx é um ZIP de ficheiros XML; construímo-lo à mão com o
 * ZipArchive. Produz larguras de coluna, cabeçalho formatado e números como
 * números (o Excel soma-os), ao contrário da tabela HTML disfarçada.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Xlsx {

	/** Letra da coluna a partir do índice 0 (0=A, 26=AA…). */
	private static function col( int $i ): string {
		$s = '';
		$i++;
		while ( $i > 0 ) {
			$m = ( $i - 1 ) % 26;
			$s = chr( 65 + $m ) . $s;
			$i = intdiv( $i - 1, 26 );
		}
		return $s;
	}

	private static function esc( string $s ): string {
		return htmlspecialchars( $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Gera e envia um .xlsx. Termina o pedido.
	 *
	 * @param string $titulo Título (1.ª linha, em destaque).
	 * @param array  $cols   Cabeçalhos das colunas.
	 * @param array  $linhas Linhas (cada uma um array de células).
	 * @param array  $tipos  Tipo por coluna: 't' texto, 'n' inteiro, 'v' valor.
	 * @param string $nome   Nome do ficheiro sem extensão.
	 */
	public static function enviar( string $titulo, array $cols, array $linhas, array $tipos, string $nome ): void {
		$ncols = count( $cols );

		// Larguras de coluna a partir do conteúdo (com um mínimo confortável).
		$larg = array();
		foreach ( $cols as $i => $c ) {
			$larg[ $i ] = max( 12, mb_strlen( (string) $c ) + 2 );
		}
		foreach ( $linhas as $linha ) {
			foreach ( $linha as $i => $cel ) {
				$larg[ $i ] = max( $larg[ $i ] ?? 12, min( 40, mb_strlen( (string) $cel ) + 3 ) );
			}
		}

		// ---- sheet1.xml ----
		$cols_xml = '<cols>';
		foreach ( $larg as $i => $w ) {
			$cols_xml .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . round( $w, 1 ) . '" customWidth="1"/>';
		}
		$cols_xml .= '</cols>';

		$rows_xml = '';
		$r        = 1;

		// Linha 1: título (célula única, estilo 3), fundida sobre as colunas.
		$rows_xml .= '<row r="1" ht="22" customHeight="1"><c r="A1" t="inlineStr" s="3"><is><t>' . self::esc( $titulo ) . '</t></is></c></row>';
		$merge     = 'A1:' . self::col( $ncols - 1 ) . '1';
		$r         = 3; // deixa uma linha em branco

		// Cabeçalho (estilo 1).
		$rows_xml .= '<row r="' . $r . '">';
		foreach ( $cols as $i => $c ) {
			$rows_xml .= '<c r="' . self::col( $i ) . $r . '" t="inlineStr" s="1"><is><t>' . self::esc( (string) $c ) . '</t></is></c>';
		}
		$rows_xml .= '</row>';
		$r++;

		// Dados.
		foreach ( $linhas as $linha ) {
			$rows_xml .= '<row r="' . $r . '">';
			foreach ( $linha as $i => $cel ) {
				$ref = self::col( $i ) . $r;
				$t   = $tipos[ $i ] ?? 't';
				if ( 'n' === $t ) {
					$rows_xml .= '<c r="' . $ref . '" s="4"><v>' . (int) $cel . '</v></c>';
				} elseif ( 'v' === $t ) {
					$rows_xml .= '<c r="' . $ref . '" s="5"><v>' . round( (float) $cel, 2 ) . '</v></c>';
				} else {
					$rows_xml .= '<c r="' . $ref . '" t="inlineStr" s="2"><is><t>' . self::esc( (string) $cel ) . '</t></is></c>';
				}
			}
			$rows_xml .= '</row>';
			$r++;
		}

		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="15"/>'
			. $cols_xml
			. '<sheetData>' . $rows_xml . '</sheetData>'
			. '<mergeCells count="1"><mergeCell ref="' . $merge . '"/></mergeCells>'
			. '</worksheet>';

		// ---- styles.xml ----
		$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0"/><numFmt numFmtId="165" formatCode="#,##0.00"/></numFmts>'
			. '<fonts count="4">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
			. '<font><sz val="11"/><color rgb="FF24313A"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="15"/><color rgb="FF33566B"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="3">'
			. '<fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FF33566B"/></patternFill></fill>'
			. '</fills>'
			. '<borders count="2">'
			. '<border><left/><right/><top/><bottom/><diagonal/></border>'
			. '<border><left/><right/><top/><bottom style="thin"><color rgb="FFD7DEE4"/></bottom><diagonal/></border>'
			. '</borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="6">'
			// 0 default
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			// 1 cabeçalho
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="left" vertical="center"/></xf>'
			// 2 texto (com linha inferior)
			. '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
			// 3 título
			. '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
			// 4 inteiro (direita)
			. '<xf numFmtId="164" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>'
			// 5 valor (direita, 2 casas)
			. '<xf numFmtId="165" fontId="2" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment horizontal="right"/></xf>'
			. '</cellXfs>'
			. '</styleSheet>';

		$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';

		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';

		$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Relatório" sheetId="1" r:id="rId1"/></sheets></workbook>';

		$wb_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '</Relationships>';

		$tmp = tempnam( get_temp_dir(), 'clx' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			wp_die( esc_html__( 'Não foi possível gerar o ficheiro Excel.', 'clinica' ) );
		}
		$zip->addFromString( '[Content_Types].xml', $content_types );
		$zip->addFromString( '_rels/.rels', $rels );
		$zip->addFromString( 'xl/workbook.xml', $workbook );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $wb_rels );
		$zip->addFromString( 'xl/styles.xml', $styles );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
		$zip->close();

		$dados = file_get_contents( $tmp );
		@unlink( $tmp );

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $nome ) . '.xlsx"' );
		header( 'Content-Length: ' . strlen( $dados ) );
		echo $dados; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
}
