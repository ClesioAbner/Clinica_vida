<?php
/**
 * inc/pdf.php
 *
 * Geração de PDF no servidor (Dompdf). Os documentos clínicos passam a
 * descarregar direto, sem a janela de impressão do browser.
 *
 * O Dompdf só entende CSS 2.1: nada de flexbox nem de grid. Por isso o
 * cabeçalho e o bloco de dados dos documentos são montados em tabela, aqui,
 * num sítio só — assim os dois documentos (processo e receita) ficam iguais.
 *
 * Se a biblioteca não estiver instalada, o sistema não parte: volta ao
 * documento em HTML com a janela de impressão, como antes.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/** A biblioteca está instalada? */
function clinica_pdf_disponivel(): bool {
	return file_exists( get_theme_file_path( 'lib/dompdf/autoload.inc.php' ) );
}

/** Cabeçalho do documento: marca à esquerda, título à direita. */
function clinica_pdf_topo( string $titulo, string $subtitulo ): string {
	$logo = get_theme_file_path( 'assets/img/logo1.png' );
	$img  = file_exists( $logo ) ? '<img class="doc-logo" src="' . esc_attr( $logo ) . '" alt="">' : '';

	return '<table class="doc-top"><tr>'
		. '<td class="doc-top__b">' . $img
			. '<span class="doc-top__n">Clínica Vida</span>'
			. '<span class="doc-top__c">Maputo, Moçambique</span>'
		. '</td>'
		. '<td class="doc-top__d">'
			. '<span class="doc-top__t">' . esc_html( $titulo ) . '</span>'
			. '<span class="doc-top__s">' . esc_html( $subtitulo ) . '</span>'
		. '</td>'
		. '</tr></table>';
}

/** Bloco de dados em duas colunas de pares etiqueta/valor. */
function clinica_pdf_meta( array $pares ): string {
	$linhas = '';
	$pares  = array_values( $pares );
	for ( $i = 0; $i < count( $pares ); $i += 2 ) {
		$a = $pares[ $i ];
		$b = $pares[ $i + 1 ] ?? null;
		$linhas .= '<tr>'
			. '<td class="doc-meta__k">' . esc_html( $a[0] ) . '</td>'
			. '<td class="doc-meta__v">' . esc_html( $a[1] ) . '</td>'
			. '<td class="doc-meta__k">' . ( $b ? esc_html( $b[0] ) : '' ) . '</td>'
			. '<td class="doc-meta__v">' . ( $b ? esc_html( $b[1] ) : '' ) . '</td>'
			. '</tr>';
	}
	return '<table class="doc-meta">' . $linhas . '</table>';
}

/**
 * Folha de estilo dos documentos. Escrita em CSS 2.1 de propósito, para
 * dar o mesmo resultado no PDF e no ecrã.
 */
function clinica_pdf_estilo(): string {
	return <<<'CSS'
	@page { margin: 22mm 16mm; }
	body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; color: #24313a; margin: 0; }
	h1, h2, h3, p, table { margin: 0; }

	.doc-top { width: 100%; border-bottom: 2px solid #4E7E9B; padding-bottom: 10px; margin-bottom: 4px; }
	.doc-top td { vertical-align: top; }
	.doc-top__d { text-align: right; }
	.doc-logo { width: 44px; height: 44px; }
	.doc-top__n { display: block; font-size: 13pt; font-weight: bold; color: #24313a; }
	.doc-top__c { display: block; font-size: 8.5pt; color: #74838d; }
	.doc-top__t { display: block; font-size: 15pt; font-weight: bold; color: #33566B; }
	.doc-top__s { display: block; font-size: 9pt; color: #74838d; }

	.doc-crumb { font-size: 8.5pt; color: #74838d; padding: 8px 0 12px; }

	.doc-meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
	.doc-meta td { padding: 5px 8px; border-bottom: 1px solid #e6ecef; font-size: 9pt; }
	.doc-meta__k { width: 16%; color: #74838d; }
	.doc-meta__v { width: 34%; font-weight: bold; color: #24313a; }

	.doc-alerta { border: 1px solid #e7b7b7; background: #fdf3f3; color: #8c2f2f; padding: 8px 10px; font-size: 9pt; margin-bottom: 14px; }

	.fx-sec { border-bottom: 1px solid #cfd9de; padding: 14px 0 6px; margin-bottom: 8px; }
	.fx-sec h3 { display: inline; font-size: 10.5pt; color: #33566B; }
	.fx-sec span { float: right; font-size: 8.5pt; color: #74838d; font-weight: normal; }

	.fx-tbl { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
	.fx-tbl th { background: #f2f6f8; border-bottom: 1px solid #cfd9de; padding: 6px 8px; text-align: left; font-size: 8pt; text-transform: uppercase; color: #5b6b75; }
	.fx-tbl td { border-bottom: 1px solid #eef2f4; padding: 6px 8px; font-size: 9pt; vertical-align: top; }
	.fx-sub { display: block; font-size: 8pt; color: #74838d; }
	.fx-st--bad { color: #a33; font-weight: bold; }

	.rb-note-p { font-size: 9pt; color: #74838d; padding: 4px 0 8px; }

	.fx-assina { margin-top: 42px; text-align: center; }
	.fx-assina__l { display: block; width: 55%; margin: 0 auto; border-top: 1px solid #333; }
	.fx-assina__n { display: block; font-size: 9pt; font-weight: bold; padding-top: 5px; }

	.fx-footer { border-top: 1px solid #e6ecef; margin-top: 22px; padding-top: 8px; font-size: 8pt; color: #74838d; }
	.fx-footer span { display: block; }
CSS;
}

/**
 * Fecha o documento e entrega-o.
 *
 * Com o Dompdf instalado devolve um PDF que o browser descarrega logo. Sem
 * ele, devolve a mesma página em HTML com a janela de impressão, para o
 * sistema continuar a funcionar.
 *
 * @param string $titulo    Título do documento.
 * @param string $ficheiro  Nome do ficheiro, sem extensão.
 * @param string $corpo     Corpo já montado.
 * @param string $voltar    Endereço do botão de voltar (só no modo HTML).
 */
function clinica_pdf_saida( string $titulo, string $ficheiro, string $corpo, string $voltar = '' ): void {
	$nome = sanitize_file_name( $ficheiro );
	$html = '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( $titulo ) . '</title>'
		. '<style>' . clinica_pdf_estilo() . '</style></head><body>' . $corpo . '</body></html>';

	if ( clinica_pdf_disponivel() ) {
		require_once get_theme_file_path( 'lib/dompdf/autoload.inc.php' );

		$opcoes = new \Dompdf\Options();
		// O logótipo é lido do disco, não da rede: nada sai para fora.
		$opcoes->set( 'isRemoteEnabled', false );
		$opcoes->set( 'chroot', get_theme_file_path() );
		$opcoes->set( 'defaultFont', 'DejaVu Sans' );

		$pdf = new \Dompdf\Dompdf( $opcoes );
		$pdf->loadHtml( $html, 'UTF-8' );
		$pdf->setPaper( 'A4', 'portrait' );
		$pdf->render();

		$saida = $pdf->output();
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . $nome . '.pdf"' );
		header( 'Content-Length: ' . strlen( $saida ) );
		header( 'X-Content-Type-Options: nosniff' );
		echo $saida; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	// Recurso: sem biblioteca, o documento abre em HTML e imprime-se.
	?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $titulo ); ?></title>
	<style><?php echo clinica_pdf_estilo(); ?>
		body { max-width: 210mm; margin: 0 auto; padding: 24px; }
		.rb-bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
		.rb-bar a, .rb-bar button { font: inherit; font-size: 9pt; cursor: pointer; }
		@media print { .rb-bar { display: none; } body { padding: 0; } }
	</style>
</head>
<body>
	<div class="rb-bar">
		<a href="<?php echo esc_url( $voltar ?: home_url( '/' ) ); ?>">← Voltar</a>
		<button onclick="window.print()">Guardar em PDF</button>
	</div>
	<?php echo $corpo; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<script>
		window.addEventListener( 'load', function () { setTimeout( function () { window.print(); }, 300 ); } );
	</script>
</body>
</html>
	<?php
	exit;
}
