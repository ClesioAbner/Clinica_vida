<?php
/**
 * inc/loader.php
 *
 * Ecrã de carregamento da Clínica Vida (batimento cardíaco) em todas as páginas
 * do tema. O CSS e o JS vivem em assets/ e são impressos inline: têm de existir
 * antes da primeira pintura, e um pedido extra atrasaria precisamente o que o
 * ecrã tenta esconder.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

final class Clinica_Loader {

	private const CSS = 'assets/css/loader.css';
	private const JS  = 'assets/js/loader.js';

	/** Traçado do batimento (viewBox 64×48). */
	private const PULSO = '0.157 23.954, 14 23.954, 21.843 48, 43 0, 50 24, 64 24';

	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'cabeca' ), 1 );
		add_action( 'wp_body_open', array( self::class, 'ecra' ), 1 );
	}

	public static function cabeca(): void {
		$css = self::ler( self::CSS );
		$js  = self::ler( self::JS );
		if ( '' === $css || '' === $js ) {
			return;
		}
		printf( "<style id=\"clinica-loader-css\">\n%s</style>\n", $css ); // phpcs:ignore WordPress.Security.EscapeOutput -- ficheiro do tema.
		wp_print_inline_script_tag( $js, array( 'id' => 'clinica-loader-js' ) );
	}

	public static function ecra(): void {
		$logo = CLINICA_URI . '/assets/img/logo-simbolo-96.webp';
		?>
		<div class="cv-loader" id="cv-loader" role="status" aria-live="polite">
			<div class="cv-loader__caixa" aria-hidden="true">
				<svg class="cv-loader__pulso" width="64" height="48" viewBox="0 0 64 48">
					<polyline class="cv-loader__fundo" points="<?php echo esc_attr( self::PULSO ); ?>"></polyline>
					<polyline class="cv-loader__frente" points="<?php echo esc_attr( self::PULSO ); ?>"></polyline>
				</svg>
				<span class="cv-loader__marca">
					<img src="<?php echo esc_url( $logo ); ?>" width="28" height="28" alt="">
					Clínica Vida
				</span>
			</div>
			<span class="cv-loader__texto">A carregar…</span>
		</div>
		<?php
	}

	private static function ler( string $rel ): string {
		$caminho = CLINICA_DIR . '/' . $rel;
		return is_readable( $caminho ) ? (string) file_get_contents( $caminho ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}

Clinica_Loader::init();
