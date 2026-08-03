<?php
/**
 * inc/analitica.php
 *
 * Camada ANALÍTICA da pirâmide — níveis TÁTICO (gestores) e EXECUTIVO (direção).
 *
 * Princípio §4 (não-negociável): os dashboards NUNCA fazem GROUP BY/SUM pesado
 * sobre as tabelas transacionais vivas a cada carregamento. Aqui os agregados
 * são calculados uma vez por CADÊNCIA e servidos de cache (transient):
 *   • Tático    — atualiza a cada HORA  (gestão de recursos, curto prazo).
 *   • Executivo — atualiza uma vez por DIA (direção, tendência/longo prazo).
 *
 * Assim a carga analítica não compete com o atendimento operacional ao vivo.
 * Sem tempo real nestes níveis — é por design.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Analitica {

	const CACHE_TATICO = 'clinica_analitica_tatico';
	const CACHE_EXEC   = 'clinica_analitica_exec';

	const CACHE_OPER = 'clinica_analitica_oper';
	const CACHE_ANO  = 'clinica_analitica_ano';

	/**
	 * Nível OPERACIONAL: o pulso da clínica hoje. É o único nível que precisa de
	 * estar quase ao vivo, por isso a cache é curta (2 minutos) — o suficiente
	 * para aguentar vários administradores a abrir o painel ao mesmo tempo sem
	 * repetir as contagens a cada carregamento.
	 */
	public static function operacional(): array {
		$cache = get_transient( self::CACHE_OPER );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		global $wpdb;
		$C  = Clinica_Repo::T_CONSULTAS;
		$PA = Clinica_Repo::T_PACIENTES;
		$P  = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::TABELA : 'clinica_pagamentos';

		$c = $wpdb->get_row(
			"SELECT
			   SUM( DATE(inicio) = CURDATE() )                                   AS hoje,
			   SUM( YEARWEEK(inicio, 3) = YEARWEEK(CURDATE(), 3) )               AS semana,
			   SUM( DATE_FORMAT(inicio, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') ) AS mes,
			   SUM( DATE(inicio) = CURDATE() AND status = 'realizada' )          AS atendidos,
			   SUM( DATE(inicio) = CURDATE() AND status = 'cancelada' )          AS canceladas,
			   SUM( DATE(inicio) = CURDATE() AND status = 'faltou' )             AS faltas,
			   SUM( DATE(inicio) = CURDATE() AND status IN ('confirmada','chamada') AND checkin_em IS NOT NULL ) AS espera,
			   SUM( status IN ('em_atendimento','pausada') )                     AS a_decorrer
			 FROM {$C}",
			ARRAY_A
		) ?: array();

		// Tempo médio de espera: da chegada à chamada, só de hoje.
		$espera_min = (float) $wpdb->get_var(
			"SELECT AVG( TIMESTAMPDIFF(MINUTE, checkin_em, chamado_em) )
			 FROM {$C}
			 WHERE DATE(inicio) = CURDATE() AND checkin_em IS NOT NULL AND chamado_em IS NOT NULL"
		);

		// Tempo médio de atendimento: do início à conclusão.
		$consulta_min = (float) $wpdb->get_var(
			"SELECT AVG( TIMESTAMPDIFF(MINUTE, iniciado_em, updated_at) )
			 FROM {$C}
			 WHERE status = 'realizada' AND iniciado_em IS NOT NULL
			   AND inicio >= ( CURDATE() - INTERVAL 30 DAY )"
		);

		$novos = $wpdb->get_row(
			"SELECT
			   SUM( DATE(created_at) = CURDATE() ) AS hoje,
			   SUM( DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') ) AS mes
			 FROM {$PA}",
			ARRAY_A
		) ?: array();

		$receita = $wpdb->get_row(
			"SELECT
			   COALESCE( SUM( CASE WHEN DATE(criado_em) = CURDATE() THEN valor END ), 0 ) AS dia,
			   COALESCE( SUM( CASE WHEN YEARWEEK(criado_em, 3) = YEARWEEK(CURDATE(), 3) THEN valor END ), 0 ) AS semana,
			   COALESCE( SUM( CASE WHEN DATE_FORMAT(criado_em, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN valor END ), 0 ) AS mes
			 FROM {$P} WHERE estado = 'pago'",
			ARRAY_A
		) ?: array();

		// Mês anterior, para as variações dos cartões serem comparações reais e
		// não percentagens inventadas.
		$ant = $wpdb->get_row(
			"SELECT
			   SUM( DATE_FORMAT(inicio, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m') ) AS consultas
			 FROM {$C}",
			ARRAY_A
		) ?: array();

		$pac_total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$PA}" );
		$pac_ant     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$PA} WHERE created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')" );
		$receita_ant = (float) $wpdb->get_var(
			"SELECT COALESCE( SUM(valor), 0 ) FROM {$P}
			 WHERE estado = 'pago'
			   AND DATE_FORMAT(criado_em, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m')"
		);

		$exames_mes = 0;
		$exames_ant = 0;
		if ( $wpdb->get_var( "SHOW TABLES LIKE 'clinica_exames'" ) ) {
			$e = $wpdb->get_row(
				"SELECT
				   SUM( DATE_FORMAT(criado_em, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') ) AS mes,
				   SUM( DATE_FORMAT(criado_em, '%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m') ) AS ant
				 FROM clinica_exames",
				ARRAY_A
			) ?: array();
			$exames_mes = (int) ( $e['mes'] ?? 0 );
			$exames_ant = (int) ( $e['ant'] ?? 0 );
		}

		// Ocupação: consultas de hoje contra a capacidade dos médicos ativos.
		$medicos_ativos = (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . Clinica_Repo::T_MEDICOS . " WHERE ativo = 1" );
		$capacidade     = $medicos_ativos * 16; // 8 horas úteis em marcações de 30 minutos
		$ocupacao       = $capacidade > 0 ? min( 100, round( (int) ( $c['hoje'] ?? 0 ) / $capacidade * 100 ) ) : 0;

		$out = array(
			'consultas'      => array(
				'hoje'   => (int) ( $c['hoje'] ?? 0 ),
				'semana' => (int) ( $c['semana'] ?? 0 ),
				'mes'    => (int) ( $c['mes'] ?? 0 ),
			),
			'atendidos'      => (int) ( $c['atendidos'] ?? 0 ),
			'canceladas'     => (int) ( $c['canceladas'] ?? 0 ),
			'faltas'         => (int) ( $c['faltas'] ?? 0 ),
			'espera'         => (int) ( $c['espera'] ?? 0 ),
			'a_decorrer'     => (int) ( $c['a_decorrer'] ?? 0 ),
			'novos_hoje'     => (int) ( $novos['hoje'] ?? 0 ),
			'novos_mes'      => (int) ( $novos['mes'] ?? 0 ),
			'espera_min'     => (int) round( $espera_min ),
			'consulta_min'   => (int) round( $consulta_min ),
			'medicos_ativos' => $medicos_ativos,
			'ocupacao'       => (int) $ocupacao,
			'receita'        => array(
				'dia'    => (float) ( $receita['dia'] ?? 0 ),
				'semana' => (float) ( $receita['semana'] ?? 0 ),
				'mes'    => (float) ( $receita['mes'] ?? 0 ),
			),
			'pacientes'      => $pac_total,
			'exames_mes'     => $exames_mes,
			// Base de comparação do mês anterior, para calcular as variações.
			'anterior'       => array(
				'consultas' => (int) ( $ant['consultas'] ?? 0 ),
				'pacientes' => $pac_ant,
				'exames'    => $exames_ant,
				'receita'   => $receita_ant,
			),
			'em'             => current_time( 'timestamp' ),
		);
		set_transient( self::CACHE_OPER, $out, 2 * MINUTE_IN_SECONDS );
		return $out;
	}

	/**
	 * Volume em duas séries (consultas realizadas e exames pedidos), em três
	 * escalas: semanal, mensal e anual. O painel recebe as três de uma vez e
	 * troca entre elas no browser, sem novo pedido ao servidor.
	 * Cadência executiva (1 dia).
	 */
	public static function series_volume(): array {
		$cache = get_transient( self::CACHE_ANO );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		global $wpdb;
		$C       = Clinica_Repo::T_CONSULTAS;
		$tem_ex  = (bool) $wpdb->get_var( "SHOW TABLES LIKE 'clinica_exames'" );
		$ano     = (int) current_time( 'Y' );
		$agora   = current_time( 'timestamp' );

		// Consulta única por fonte: monta-se o balde em PHP, não em 3 queries.
		$conta = static function ( string $tabela, string $coluna, string $extra ) use ( $wpdb ) {
			return $wpdb->get_results(
				"SELECT DATE({$coluna}) AS d, COUNT(*) AS n FROM {$tabela}
				 WHERE {$coluna} >= ( CURDATE() - INTERVAL 5 YEAR ) {$extra}
				 GROUP BY DATE({$coluna})",
				ARRAY_A
			) ?: array();
		};
		$dias_c = $conta( $C, 'inicio', "AND status = 'realizada'" );
		$dias_e = $tem_ex ? $conta( 'clinica_exames', 'criado_em', '' ) : array();

		$balde = static function ( array $dias, string $fmt, array $chaves ) {
			$mapa = array_fill_keys( $chaves, 0 );
			foreach ( $dias as $r ) {
				$k = date( $fmt, strtotime( $r['d'] ) );
				if ( isset( $mapa[ $k ] ) ) {
					$mapa[ $k ] += (int) $r['n'];
				}
			}
			return array_values( $mapa );
		};

		// Semanal: últimas 12 semanas.
		$sem_k = array();
		$sem_l = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$ts      = strtotime( "-{$i} week", $agora );
			$sem_k[] = date( 'oW', $ts );
			$sem_l[] = date_i18n( 'j/n', strtotime( 'monday this week', $ts ) );
		}
		// Mensal: os 12 meses do ano corrente.
		$mes_k = array();
		$mes_l = array( 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez' );
		for ( $m = 1; $m <= 12; $m++ ) {
			$mes_k[] = sprintf( '%d-%02d', $ano, $m );
		}
		// Anual: últimos 5 anos.
		$ano_k = array();
		for ( $i = 4; $i >= 0; $i-- ) {
			$ano_k[] = (string) ( $ano - $i );
		}

		$out = array(
			'ano'     => $ano,
			'semanal' => array(
				'labels'    => $sem_l,
				'consultas' => $balde( $dias_c, 'oW', $sem_k ),
				'exames'    => $balde( $dias_e, 'oW', $sem_k ),
			),
			'mensal'  => array(
				'labels'    => $mes_l,
				'consultas' => $balde( $dias_c, 'Y-m', $mes_k ),
				'exames'    => $balde( $dias_e, 'Y-m', $mes_k ),
			),
			'anual'   => array(
				'labels'    => $ano_k,
				'consultas' => $balde( $dias_c, 'Y', $ano_k ),
				'exames'    => $balde( $dias_e, 'Y', $ano_k ),
			),
			'em'      => current_time( 'timestamp' ),
		);
		set_transient( self::CACHE_ANO, $out, DAY_IN_SECONDS );
		return $out;
	}

	/**
	 * Nível TÁTICO: desempenho por especialidade + faltas (no-show) por médico.
	 * Cache de 1 hora. Devolve também 'em' (timestamp do cálculo).
	 */
	public static function tatico(): array {
		$cache = get_transient( self::CACHE_TATICO );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		global $wpdb;
		$C = Clinica_Repo::T_CONSULTAS;
		$M = Clinica_Repo::T_MEDICOS;
		$E = Clinica_Repo::T_ESPECIALIDADES;

		$por_esp = $wpdb->get_results(
			"SELECT e.nome AS esp,
			        COUNT(c.id) AS total,
			        SUM(c.status = 'realizada') AS realizadas,
			        SUM(c.status = 'faltou') AS faltas
			 FROM {$C} c
			 JOIN {$M} md ON c.medico_id = md.id
			 JOIN {$E} e  ON md.especialidade_id = e.id
			 GROUP BY e.id, e.nome
			 ORDER BY total DESC",
			ARRAY_A
		) ?: array();

		$no_show = $wpdb->get_results(
			"SELECT u.display_name AS med,
			        COUNT(c.id) AS total,
			        SUM(c.status = 'faltou') AS faltas
			 FROM {$C} c
			 JOIN {$M} md ON c.medico_id = md.id
			 JOIN {$wpdb->users} u ON md.user_id = u.ID
			 GROUP BY md.id, u.display_name
			 HAVING faltas > 0
			 ORDER BY faltas DESC
			 LIMIT 8",
			ARRAY_A
		) ?: array();

		$out = array(
			'por_especialidade' => $por_esp,
			'no_show_medico'    => $no_show,
			'em'                => current_time( 'timestamp' ),
		);
		set_transient( self::CACHE_TATICO, $out, HOUR_IN_SECONDS );
		return $out;
	}

	/**
	 * Nível EXECUTIVO: receita consolidada, receita por especialidade e tendência
	 * de volume (consultas realizadas por semana, últimas 8). Cache de 1 dia.
	 */
	public static function executivo(): array {
		$cache = get_transient( self::CACHE_EXEC );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		global $wpdb;
		$C = Clinica_Repo::T_CONSULTAS;
		$M = Clinica_Repo::T_MEDICOS;
		$E = Clinica_Repo::T_ESPECIALIDADES;
		$P = Clinica_Pagamentos::TABELA;

		$totais = class_exists( 'Clinica_Pagamentos' )
			? Clinica_Pagamentos::totais()
			: array( 'pago_valor' => 0.0, 'pendente_valor' => 0.0, 'pago_n' => 0, 'pendente_n' => 0 );

		$receita_esp = $wpdb->get_results(
			"SELECT e.nome AS esp, COALESCE(SUM(pg.valor), 0) AS receita
			 FROM {$P} pg
			 JOIN {$C} c  ON pg.consulta_id = c.id
			 JOIN {$M} md ON c.medico_id = md.id
			 JOIN {$E} e  ON md.especialidade_id = e.id
			 WHERE pg.estado = 'pago'
			 GROUP BY e.id, e.nome
			 ORDER BY receita DESC",
			ARRAY_A
		) ?: array();

		// Tendência de volume: consultas realizadas por semana (últimas 8).
		$tendencia = array();
		$rows = $wpdb->get_results(
			"SELECT YEARWEEK(inicio, 3) AS sem, COUNT(*) AS n
			 FROM {$C}
			 WHERE status = 'realizada' AND inicio >= ( CURDATE() - INTERVAL 8 WEEK )
			 GROUP BY YEARWEEK(inicio, 3)
			 ORDER BY sem",
			ARRAY_A
		) ?: array();
		foreach ( $rows as $r ) {
			$tendencia[] = (int) $r['n'];
		}

		$out = array(
			'totais'      => $totais,
			'receita_esp' => $receita_esp,
			'tendencia'   => $tendencia,
			'em'          => current_time( 'timestamp' ),
		);
		set_transient( self::CACHE_EXEC, $out, DAY_IN_SECONDS );
		return $out;
	}

	const CACHE_DIRECAO = 'clinica_analitica_direcao';

	/**
	 * Nível DIREÇÃO (executivo, cadência de 1 dia): a leitura estratégica.
	 * Crescimento face ao mês anterior e ao ano anterior, receita por
	 * especialidade e por médico, pacientes novos vs recorrentes e retenção.
	 * Só receita — nunca margem, que exigiria dados de custo que não existem.
	 */
	public static function direcao(): array {
		$cache = get_transient( self::CACHE_DIRECAO );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		global $wpdb;
		$C  = Clinica_Repo::T_CONSULTAS;
		$M  = Clinica_Repo::T_MEDICOS;
		$E  = Clinica_Repo::T_ESPECIALIDADES;
		$PA = Clinica_Repo::T_PACIENTES;
		$P  = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::TABELA : 'clinica_pagamentos';

		// Receita: mês corrente, mês anterior e mesmo mês do ano passado.
		$rec = $wpdb->get_row(
			"SELECT
			   COALESCE(SUM(CASE WHEN DATE_FORMAT(criado_em,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') THEN valor END),0) AS mes,
			   COALESCE(SUM(CASE WHEN DATE_FORMAT(criado_em,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m') THEN valor END),0) AS mes_ant,
			   COALESCE(SUM(CASE WHEN DATE_FORMAT(criado_em,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 YEAR,'%Y-%m') THEN valor END),0) AS ano_ant,
			   COALESCE(SUM(CASE WHEN YEAR(criado_em) = YEAR(CURDATE()) THEN valor END),0) AS ano
			 FROM {$P} WHERE estado = 'pago'",
			ARRAY_A
		) ?: array();

		// Consultas realizadas: mês corrente vs mês anterior (crescimento de volume).
		$vol = $wpdb->get_row(
			"SELECT
			   SUM(DATE_FORMAT(inicio,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')) AS mes,
			   SUM(DATE_FORMAT(inicio,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m')) AS mes_ant
			 FROM {$C} WHERE status = 'realizada'",
			ARRAY_A
		) ?: array();

		// Receita por especialidade (todo o histórico pago).
		$por_esp = $wpdb->get_results(
			"SELECT e.nome AS nome, COALESCE(SUM(pg.valor),0) AS receita, COUNT(pg.id) AS n
			 FROM {$P} pg
			 JOIN {$C} c  ON pg.consulta_id = c.id
			 JOIN {$M} md ON c.medico_id = md.id
			 JOIN {$E} e  ON md.especialidade_id = e.id
			 WHERE pg.estado = 'pago'
			 GROUP BY e.id, e.nome ORDER BY receita DESC",
			ARRAY_A
		) ?: array();

		// Receita por médico.
		$por_med = $wpdb->get_results(
			"SELECT u.display_name AS nome, e.nome AS esp, COALESCE(SUM(pg.valor),0) AS receita, COUNT(pg.id) AS n
			 FROM {$P} pg
			 JOIN {$C} c  ON pg.consulta_id = c.id
			 JOIN {$M} md ON c.medico_id = md.id
			 JOIN {$wpdb->users} u ON md.user_id = u.ID
			 JOIN {$E} e  ON md.especialidade_id = e.id
			 WHERE pg.estado = 'pago'
			 GROUP BY md.id, u.display_name, e.nome ORDER BY receita DESC LIMIT 8",
			ARRAY_A
		) ?: array();

		// Série de receita mensal (12 meses), para a tendência de topo.
		$serie = array_fill( 0, 12, 0.0 );
		$rows  = $wpdb->get_results(
			"SELECT DATE_FORMAT(criado_em,'%Y-%m') AS m, SUM(valor) AS v
			 FROM {$P}
			 WHERE estado = 'pago' AND criado_em >= ( DATE_FORMAT(CURDATE(),'%Y-%m-01') - INTERVAL 11 MONTH )
			 GROUP BY m ORDER BY m",
			ARRAY_A
		) ?: array();
		$serie_lbl = array();
		for ( $i = 11; $i >= 0; $i-- ) {
			$ts = strtotime( "first day of -{$i} month" );
			$serie_lbl[ date( 'Y-m', $ts ) ] = date_i18n( 'M', $ts );
		}
		$mapa = array_fill_keys( array_keys( $serie_lbl ), 0.0 );
		foreach ( $rows as $r ) {
			if ( isset( $mapa[ $r['m'] ] ) ) {
				$mapa[ $r['m'] ] = (float) $r['v'];
			}
		}
		$serie     = array_values( $mapa );
		$serie_lbl = array_values( $serie_lbl );

		// Pacientes: novos vs recorrentes no mês (pela data da 1.ª consulta).
		$pac = $wpdb->get_row(
			"SELECT
			   SUM(t.primeira >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS novos,
			   SUM(t.primeira <  DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS recorrentes
			 FROM (
			   SELECT paciente_id, MIN(inicio) AS primeira
			   FROM {$C}
			   WHERE paciente_id IN (
			     SELECT DISTINCT paciente_id FROM {$C}
			     WHERE DATE_FORMAT(inicio,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
			   )
			   GROUP BY paciente_id
			 ) t",
			ARRAY_A
		) ?: array();

		// Retenção: dos pacientes com consulta no mês anterior, quantos voltaram.
		$prev = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT paciente_id) FROM {$C}
			 WHERE DATE_FORMAT(inicio,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m')"
		);
		$voltou = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT a.paciente_id) FROM {$C} a
			 WHERE DATE_FORMAT(a.inicio,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m')
			   AND EXISTS (
			     SELECT 1 FROM {$C} b WHERE b.paciente_id = a.paciente_id
			     AND DATE_FORMAT(b.inicio,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')
			   )"
		);
		$retencao = $prev > 0 ? (int) round( $voltou / $prev * 100 ) : 0;

		$base_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$PA}" );

		$out = array(
			'receita'     => array(
				'mes'     => (float) ( $rec['mes'] ?? 0 ),
				'mes_ant' => (float) ( $rec['mes_ant'] ?? 0 ),
				'ano_ant' => (float) ( $rec['ano_ant'] ?? 0 ),
				'ano'     => (float) ( $rec['ano'] ?? 0 ),
			),
			'volume'      => array(
				'mes'     => (int) ( $vol['mes'] ?? 0 ),
				'mes_ant' => (int) ( $vol['mes_ant'] ?? 0 ),
			),
			'por_esp'     => $por_esp,
			'por_med'     => $por_med,
			'serie'       => $serie,
			'serie_lbl'   => $serie_lbl,
			'pacientes'   => array(
				'novos'       => (int) ( $pac['novos'] ?? 0 ),
				'recorrentes' => (int) ( $pac['recorrentes'] ?? 0 ),
				'base'        => $base_total,
				'retencao'    => $retencao,
			),
			'em'          => current_time( 'timestamp' ),
		);
		set_transient( self::CACHE_DIRECAO, $out, DAY_IN_SECONDS );
		return $out;
	}

	const CACHE_BI = 'clinica_analitica_bi';

	/**
	 * Centro de Inteligência (executivo). Uma passagem diária de 730 dias
	 * distribuída por PHP nos quatro períodos (7d/30d/90d/12m), para o painel
	 * poder trocar de janela sem novo pedido ao servidor. Inclui rankings,
	 * demografia e alertas calculados de limiares reais. Cadência 1 dia.
	 *
	 * Só usa dados que existem: receita (pagamentos), consultas, pacientes.
	 * Não há custos/lucro, unidades nem seguradoras no modelo — por isso não
	 * são calculados aqui.
	 */
	public static function bi(): array {
		$cache = get_transient( self::CACHE_BI );
		if ( is_array( $cache ) ) {
			return $cache;
		}

		global $wpdb;
		$C  = Clinica_Repo::T_CONSULTAS;
		$M  = Clinica_Repo::T_MEDICOS;
		$E  = Clinica_Repo::T_ESPECIALIDADES;
		$PA = Clinica_Repo::T_PACIENTES;
		$P  = class_exists( 'Clinica_Pagamentos' ) ? Clinica_Pagamentos::TABELA : 'clinica_pagamentos';

		$hoje = current_time( 'timestamp' );

		// --- Séries diárias, uma query por fonte, 730 dias ---
		$diario = static function ( string $sql ) use ( $wpdb ) {
			$out = array();
			foreach ( (array) $wpdb->get_results( $sql, ARRAY_A ) as $r ) {
				$out[ $r['d'] ] = (float) $r['v'];
			}
			return $out;
		};
		$rec_d = $diario( "SELECT DATE(criado_em) d, SUM(valor) v FROM {$P} WHERE estado='pago' AND criado_em >= (CURDATE() - INTERVAL 730 DAY) GROUP BY DATE(criado_em)" );
		$con_d = $diario( "SELECT DATE(inicio) d, COUNT(*) v FROM {$C} WHERE status='realizada' AND inicio >= (CURDATE() - INTERVAL 730 DAY) GROUP BY DATE(inicio)" );
		$can_d = $diario( "SELECT DATE(inicio) d, COUNT(*) v FROM {$C} WHERE status='cancelada' AND inicio >= (CURDATE() - INTERVAL 730 DAY) GROUP BY DATE(inicio)" );
		$fal_d = $diario( "SELECT DATE(inicio) d, COUNT(*) v FROM {$C} WHERE status='faltou' AND inicio >= (CURDATE() - INTERVAL 730 DAY) GROUP BY DATE(inicio)" );
		$nov_d = $diario( "SELECT DATE(created_at) d, COUNT(*) v FROM {$PA} WHERE created_at >= (CURDATE() - INTERVAL 730 DAY) GROUP BY DATE(created_at)" );

		// Soma de um mapa diário entre dois instantes (inclusivo/exclusivo).
		$soma = static function ( array $mapa, int $de, int $ate ) {
			$t = 0.0;
			foreach ( $mapa as $d => $v ) {
				$ts = strtotime( $d );
				if ( $ts >= $de && $ts < $ate ) {
					$t += $v;
				}
			}
			return $t;
		};
		// Série de N pontos para a tendência de um período.
		$serie = static function ( array $mapa, int $fim, string $unidade, int $passos ) {
			$pts = array();
			$lbl = array();
			for ( $i = $passos - 1; $i >= 0; $i-- ) {
				if ( 'dia' === $unidade ) {
					$a = strtotime( "-{$i} day", $fim );
					$b = strtotime( '+1 day', $a );
					$lbl[] = date_i18n( 'j/n', $a );
				} elseif ( 'semana' === $unidade ) {
					$a = strtotime( 'monday this week', strtotime( "-{$i} week", $fim ) );
					$b = strtotime( '+1 week', $a );
					$lbl[] = date_i18n( 'j/n', $a );
				} else {
					$a = strtotime( 'first day of ' . ( $i ? "-{$i} month" : 'this month' ), $fim );
					$b = strtotime( '+1 month', $a );
					$lbl[] = date_i18n( 'M', $a );
				}
				$t = 0.0;
				foreach ( $mapa as $d => $v ) {
					$ts = strtotime( $d );
					if ( $ts >= $a && $ts < $b ) {
						$t += $v;
					}
				}
				$pts[] = round( $t, 2 );
			}
			return array( 'serie' => $pts, 'lbl' => $lbl );
		};

		$defs = array(
			'7d'  => array( 'label' => '7 dias',   'dias' => 7,   'un' => 'dia',    'passos' => 7 ),
			'30d' => array( 'label' => '30 dias',  'dias' => 30,  'un' => 'dia',    'passos' => 30 ),
			'90d' => array( 'label' => '90 dias',  'dias' => 90,  'un' => 'semana', 'passos' => 13 ),
			'12m' => array( 'label' => '12 meses', 'dias' => 365, 'un' => 'mes',    'passos' => 12 ),
		);
		$periodos = array();
		foreach ( $defs as $chave => $d ) {
			$n     = $d['dias'];
			$ini   = strtotime( "-{$n} day", $hoje );
			$iniAnt = strtotime( '-' . ( 2 * $n ) . ' day', $hoje );
			$st    = $serie( $rec_d, $hoje, $d['un'], $d['passos'] );
			$stc   = $serie( $con_d, $hoje, $d['un'], $d['passos'] );
			$stn   = $serie( $nov_d, $hoje, $d['un'], $d['passos'] );
			$periodos[ $chave ] = array(
				'label'       => $d['label'],
				'receita'     => round( $soma( $rec_d, $ini, $hoje ), 2 ),
				'receita_ant' => round( $soma( $rec_d, $iniAnt, $ini ), 2 ),
				'consultas'   => (int) $soma( $con_d, $ini, $hoje ),
				'cons_ant'    => (int) $soma( $con_d, $iniAnt, $ini ),
				'canceladas'  => (int) $soma( $can_d, $ini, $hoje ),
				'faltou'      => (int) $soma( $fal_d, $ini, $hoje ),
				'novos'       => (int) $soma( $nov_d, $ini, $hoje ),
				'novos_ant'   => (int) $soma( $nov_d, $iniAnt, $ini ),
				'serie'       => $st['serie'],
				'serie_lbl'   => $st['lbl'],
				'serie_con'   => $stc['serie'],
				'serie_nov'   => $stn['serie'],
			);
		}

		// --- Métricas de qualidade (últimos 30 dias) ---
		// Limites de sanidade: descarta registos incoerentes (esquecimentos de
		// fecho, entradas de teste) que inflacionariam a média — 0 a 8h de
		// espera, 3 a 180 min de consulta.
		$espera = (int) round( (float) $wpdb->get_var(
			"SELECT AVG(w) FROM (
			   SELECT TIMESTAMPDIFF(MINUTE, checkin_em, chamado_em) w FROM {$C}
			   WHERE checkin_em IS NOT NULL AND chamado_em IS NOT NULL AND inicio >= (CURDATE() - INTERVAL 30 DAY)
			 ) t WHERE w BETWEEN 0 AND 480"
		) );
		$duracao = (int) round( (float) $wpdb->get_var(
			"SELECT AVG(w) FROM (
			   SELECT TIMESTAMPDIFF(MINUTE, iniciado_em, updated_at) w FROM {$C}
			   WHERE status='realizada' AND iniciado_em IS NOT NULL AND inicio >= (CURDATE() - INTERVAL 30 DAY)
			 ) t WHERE w BETWEEN 3 AND 180"
		) );
		$q = $wpdb->get_row(
			"SELECT
			   SUM(status='realizada') r, SUM(status='faltou') f, SUM(status='cancelada') c, COUNT(*) t
			 FROM {$C} WHERE inicio >= (CURDATE() - INTERVAL 90 DAY)",
			ARRAY_A
		) ?: array();
		$rf        = (int) ( $q['r'] ?? 0 ) + (int) ( $q['f'] ?? 0 );
		$no_show   = $rf > 0 ? (int) round( (int) $q['f'] / $rf * 100 ) : 0;
		$cancel    = (int) ( $q['t'] ?? 0 ) > 0 ? (int) round( (int) $q['c'] / (int) $q['t'] * 100 ) : 0;
		$med_ativos = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$M} WHERE ativo=1" );
		$cons_90    = (int) ( $q['r'] ?? 0 );
		$capacidade = $med_ativos * 16 * 64; // 16 slots/dia úteis * ~64 dias úteis em 90d
		$ocupacao   = $capacidade > 0 ? min( 100, (int) round( $cons_90 / $capacidade * 100 ) ) : 0;

		// --- Rankings (90 dias) ---
		$rk_med = $wpdb->get_results(
			"SELECT u.display_name nome, e.nome esp,
			        COALESCE(SUM(pg.valor),0) receita, COUNT(DISTINCT c.id) consultas
			 FROM {$M} md
			 JOIN {$wpdb->users} u ON md.user_id=u.ID
			 JOIN {$E} e ON md.especialidade_id=e.id
			 LEFT JOIN {$C} c ON c.medico_id=md.id AND c.inicio >= (CURDATE() - INTERVAL 90 DAY)
			 LEFT JOIN {$P} pg ON pg.consulta_id=c.id AND pg.estado='pago'
			 WHERE md.ativo=1
			 GROUP BY md.id, u.display_name, e.nome
			 ORDER BY receita DESC, consultas DESC LIMIT 6",
			ARRAY_A
		) ?: array();
		$rk_esp = $wpdb->get_results(
			"SELECT e.nome nome, COALESCE(SUM(pg.valor),0) receita, COUNT(DISTINCT c.id) consultas
			 FROM {$E} e
			 LEFT JOIN {$M} md ON md.especialidade_id=e.id
			 LEFT JOIN {$C} c ON c.medico_id=md.id AND c.inicio >= (CURDATE() - INTERVAL 90 DAY)
			 LEFT JOIN {$P} pg ON pg.consulta_id=c.id AND pg.estado='pago'
			 WHERE e.ativo=1
			 GROUP BY e.id, e.nome ORDER BY receita DESC, consultas DESC LIMIT 6",
			ARRAY_A
		) ?: array();

		// --- Demografia dos pacientes ---
		$idade = $wpdb->get_row(
			"SELECT
			   SUM(a < 18) g0, SUM(a BETWEEN 18 AND 34) g1, SUM(a BETWEEN 35 AND 49) g2,
			   SUM(a BETWEEN 50 AND 64) g3, SUM(a >= 65) g4
			 FROM (
			   SELECT TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) a FROM {$PA}
			   WHERE data_nascimento IS NOT NULL AND data_nascimento <> '0000-00-00'
			 ) t",
			ARRAY_A
		) ?: array();
		$genero = $wpdb->get_row(
			"SELECT SUM(genero='F') f, SUM(genero='M') m, SUM(genero NOT IN ('F','M') OR genero IS NULL) o FROM {$PA}",
			ARRAY_A
		) ?: array();

		// --- Alertas inteligentes (limiares reais) ---
		$alertas   = array();
		$meta_rec  = (float) get_option( 'clinica_meta_receita_mensal', 0 );
		$rec_mes   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(valor),0) FROM {$P} WHERE estado='pago' AND DATE_FORMAT(criado_em,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')" );
		if ( $meta_rec > 0 && $rec_mes < $meta_rec ) {
			$falta = (int) round( ( 1 - $rec_mes / $meta_rec ) * 100 );
			$alertas[] = array( 'n' => 'alta', 't' => 'Receita abaixo da meta', 'd' => $falta . '% abaixo do objetivo do mês' );
		}
		if ( $cancel >= 15 ) {
			$alertas[] = array( 'n' => 'alta', 't' => 'Cancelamentos elevados', 'd' => $cancel . '% das consultas canceladas (90 dias)' );
		}
		if ( $no_show >= 10 ) {
			$alertas[] = array( 'n' => 'media', 't' => 'Faltas acima do normal', 'd' => $no_show . '% de não comparências (90 dias)' );
		}
		if ( $espera >= 30 ) {
			$alertas[] = array( 'n' => 'media', 't' => 'Tempo de espera alto', 'd' => 'Média de ' . $espera . ' min até à chamada' );
		}
		// Especialidade em queda: receita 90d < receita dos 90d anteriores.
		$queda = $wpdb->get_results(
			"SELECT e.nome nome,
			   COALESCE(SUM(CASE WHEN c.inicio >= (CURDATE() - INTERVAL 90 DAY) THEN pg.valor END),0) atual,
			   COALESCE(SUM(CASE WHEN c.inicio >= (CURDATE() - INTERVAL 180 DAY) AND c.inicio < (CURDATE() - INTERVAL 90 DAY) THEN pg.valor END),0) antes
			 FROM {$E} e
			 JOIN {$M} md ON md.especialidade_id=e.id
			 JOIN {$C} c ON c.medico_id=md.id
			 JOIN {$P} pg ON pg.consulta_id=c.id AND pg.estado='pago'
			 WHERE e.ativo=1 GROUP BY e.id, e.nome HAVING antes > 0 AND atual < antes",
			ARRAY_A
		) ?: array();
		foreach ( array_slice( $queda, 0, 2 ) as $qd ) {
			$dif = (int) round( ( 1 - (float) $qd['atual'] / (float) $qd['antes'] ) * 100 );
			$alertas[] = array( 'n' => 'media', 't' => $qd['nome'] . ' em queda', 'd' => 'Receita ' . $dif . '% abaixo do trimestre anterior' );
		}

		$out = array(
			'periodos'   => $periodos,
			'qualidade'  => array(
				'espera'   => $espera,
				'duracao'  => $duracao,
				'no_show'  => $no_show,
				'cancel'   => $cancel,
				'ocupacao' => $ocupacao,
				'medicos'  => $med_ativos,
			),
			'rankings'   => array( 'medicos' => $rk_med, 'especialidades' => $rk_esp ),
			'demografia' => array(
				'idade'  => array(
					array( 'l' => '0-17', 'n' => (int) ( $idade['g0'] ?? 0 ) ),
					array( 'l' => '18-34', 'n' => (int) ( $idade['g1'] ?? 0 ) ),
					array( 'l' => '35-49', 'n' => (int) ( $idade['g2'] ?? 0 ) ),
					array( 'l' => '50-64', 'n' => (int) ( $idade['g3'] ?? 0 ) ),
					array( 'l' => '65+', 'n' => (int) ( $idade['g4'] ?? 0 ) ),
				),
				'genero' => array(
					'f' => (int) ( $genero['f'] ?? 0 ),
					'm' => (int) ( $genero['m'] ?? 0 ),
					'o' => (int) ( $genero['o'] ?? 0 ),
				),
			),
			'alertas'    => $alertas,
			'em'         => $hoje,
		);
		set_transient( self::CACHE_BI, $out, DAY_IN_SECONDS );
		return $out;
	}

	/** Força o recálculo no próximo pedido (após alterações em massa). */
	public static function limpar(): void {
		delete_transient( self::CACHE_TATICO );
		delete_transient( self::CACHE_EXEC );
		delete_transient( self::CACHE_OPER );
		delete_transient( self::CACHE_ANO );
		delete_transient( self::CACHE_DIRECAO );
		delete_transient( self::CACHE_BI );
	}
}

// Invalida os agregados quando o transacional muda (fresco no próximo pedido;
// a cadência TTL é o limite máximo de desatualização, não a única atualização).
add_action( 'clinica_consulta_estado', array( 'Clinica_Analitica', 'limpar' ) );
add_action( 'clinica_consulta_atendida', array( 'Clinica_Analitica', 'limpar' ) );
add_action( 'clinica_consulta_criada', array( 'Clinica_Analitica', 'limpar' ) );
