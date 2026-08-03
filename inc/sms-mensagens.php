<?php
/**
 * Textos das mensagens da clínica.
 *
 * Ficam juntos e num sítio só — espalhados pelos controladores, ninguém
 * consegue rever o que a clínica escreve aos pacientes.
 *
 * Sem acentos de propósito: nem todos os operadores entregam GSM-7 estendido
 * de forma fiável, e um acento mal codificado transforma o SMS em lixo.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Sms_Mensagens {

	public static function consulta_confirmada( string $telefone, string $paciente, string $medico, string $inicio ): Clinica_Resposta {
		return Clinica_Sms::enviar(
			$telefone,
			sprintf(
				'Clinica Vida: %s, a sua consulta com %s esta confirmada para %s. Chegue 10 min antes.',
				self::primeiro_nome( $paciente ),
				$medico,
				date_i18n( 'd/m \a\s H:i', strtotime( $inicio ) )
			)
		);
	}

	// Aviso automático um DIA antes (enviado pelo cron de lembretes).
	public static function lembrete_um_dia( string $telefone, string $paciente, string $medico, string $inicio ): Clinica_Resposta {
		return Clinica_Sms::enviar(
			$telefone,
			sprintf(
				'Clinica Vida: %s, lembrete: tem consulta com %s amanha, dia %s as %s. Ate breve.',
				self::primeiro_nome( $paciente ),
				$medico,
				date_i18n( 'd/m', strtotime( $inicio ) ),
				date_i18n( 'H:i', strtotime( $inicio ) )
			)
		);
	}

	// Aviso automático uma hora antes (enviado pelo cron de lembretes).
	public static function lembrete_uma_hora( string $telefone, string $paciente, string $medico, string $inicio ): Clinica_Resposta {
		return Clinica_Sms::enviar(
			$telefone,
			sprintf(
				'Clinica Vida: %s, a sua consulta com %s e daqui a 1 hora, as %s. Chegue 10 min antes.',
				self::primeiro_nome( $paciente ),
				$medico,
				date_i18n( 'H:i', strtotime( $inicio ) )
			)
		);
	}

	public static function lembrete( string $telefone, string $paciente, string $medico, string $inicio ): Clinica_Resposta {
		return Clinica_Sms::enviar(
			$telefone,
			sprintf(
				'Clinica Vida: %s, lembrete da consulta com %s amanha as %s.',
				self::primeiro_nome( $paciente ),
				$medico,
				date_i18n( 'H:i', strtotime( $inicio ) )
			)
		);
	}

	public static function consulta_cancelada( string $telefone, string $paciente, string $inicio ): Clinica_Resposta {
		return Clinica_Sms::enviar(
			$telefone,
			sprintf(
				'Clinica Vida: %s, a consulta de %s foi cancelada. Contacte-nos para remarcar.',
				self::primeiro_nome( $paciente ),
				date_i18n( 'd/m \a\s H:i', strtotime( $inicio ) )
			)
		);
	}

	public static function pagamento_confirmado( string $telefone, string $paciente, float $valor, string $moeda, string $recibo ): Clinica_Resposta {
		return Clinica_Sms::enviar(
			$telefone,
			sprintf(
				'Clinica Vida: %s, recebemos o pagamento de %s %s. Recibo %s. Obrigado.',
				self::primeiro_nome( $paciente ),
				number_format( $valor, 2, ',', '.' ),
				$moeda,
				$recibo
			)
		);
	}

	// Lembretes do dia seguinte, num pedido só. @param array<int,array> $consultas
	public static function lembretes_do_dia( array $consultas ): Clinica_Resposta {
		$lote = array();
		foreach ( $consultas as $c ) {
			$lote[] = array(
				'telefone' => (string) ( $c['telefone'] ?? '' ),
				'mensagem' => sprintf(
					'Clinica Vida: %s, lembrete da consulta com %s amanha as %s.',
					self::primeiro_nome( (string) ( $c['paciente'] ?? '' ) ),
					(string) ( $c['medico'] ?? '' ),
					date_i18n( 'H:i', strtotime( (string) ( $c['inicio'] ?? 'now' ) ) )
				),
			);
		}
		return Clinica_Sms::enviar_lote( $lote );
	}

	private static function primeiro_nome( string $nome ): string {
		return explode( ' ', trim( $nome ) )[0] ?: $nome;
	}
}
