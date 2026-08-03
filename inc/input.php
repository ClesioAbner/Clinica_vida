<?php
/**
 * inc/input.php
 *
 * Helper único de validação/sanitização de input.
 * Centraliza a limpeza de $_POST/$_GET -> os controladores ficam legíveis
 * e não repetem as funções de sanitização em todo o lado.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Input {

	/** Texto de uma linha, sanitizado. */
	public static function texto( array $fonte, string $chave, string $default = '' ): string {
		return isset( $fonte[ $chave ] ) ? sanitize_text_field( wp_unslash( $fonte[ $chave ] ) ) : $default;
	}

	/** Texto multilinha (observações). */
	public static function textarea( array $fonte, string $chave, string $default = '' ): string {
		return isset( $fonte[ $chave ] ) ? sanitize_textarea_field( wp_unslash( $fonte[ $chave ] ) ) : $default;
	}

	/** Inteiro >= 0. */
	public static function inteiro( array $fonte, string $chave, int $default = 0 ): int {
		return isset( $fonte[ $chave ] ) ? absint( $fonte[ $chave ] ) : $default;
	}

	/** Chave/slug seguro (a-z, 0-9, _-). */
	public static function chave( array $fonte, string $chave, string $default = '' ): string {
		return isset( $fonte[ $chave ] ) ? sanitize_key( wp_unslash( $fonte[ $chave ] ) ) : $default;
	}

	/** Email validado (string vazia se inválido). */
	public static function email( array $fonte, string $chave ): string {
		$valor = isset( $fonte[ $chave ] ) ? sanitize_email( wp_unslash( $fonte[ $chave ] ) ) : '';
		return is_email( $valor ) ? $valor : '';
	}

	/** Data ISO (YYYY-MM-DD) existente no calendário; default se inválida. */
	public static function data( array $fonte, string $chave, string $default = '' ): string {
		$valor = self::texto( $fonte, $chave );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $m ) ) {
			return $default;
		}
		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ? $valor : $default;
	}

	/** Verifica um nonce de forma booleana. */
	public static function nonce_ok( array $fonte, string $campo, string $acao ): bool {
		return isset( $fonte[ $campo ] ) && (bool) wp_verify_nonce( $fonte[ $campo ], $acao );
	}
}
