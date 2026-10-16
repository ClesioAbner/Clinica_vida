<?php
/**
 * inc/graficos.php
 *
 * Biblioteca de gráficos dos portais (classes .gf-*), desenhada no servidor:
 * colunas (simples ou empilhadas), linhas com área, barras horizontais,
 * sparklines e medidores. O equivalente, nos painéis, ao componentes.css do
 * site público: um sítio só para a forma de todos os gráficos.
 *
 * Regras de desenho (iguais em todos os gráficos):
 *  - Marcas finas: colunas até 24px com topo arredondado de 4px; linhas de 2px;
 *    pontos de 8px com anel branco; área com véu de 10%.
 *  - Grelha em filetes sólidos e discretos; um só eixo Y, nunca dois.
 *  - Cor por papel: s1 (teal da marca), s1-claro (o passo claro do mesmo teal,
 *    validado como rampa ordinal) e ctx (cinzento de contexto). O texto nunca
 *    usa a cor da série: a identidade vem da chave ao lado.
 *  - Legenda sempre que há duas ou mais séries; rótulos diretos só no ponto
 *    que conta (máximo ou último), nunca em todos.
 *  - Cada gráfico traz a sua tabela ("Ver dados"): a dica ao passar o rato
 *    acrescenta, nunca é o único caminho para um valor.
 *
 * As dicas (tooltip) e o cursor vertical vivem em assets/js/graficos.js e
 * leem os atributos data-gf-titulo / data-gf-linhas. Os textos chegam já
 * formatados e escapados daqui.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Grafico {

	/** Moeda configurada (MZN por omissão). */
	public static function moeda_codigo(): string {
		return class_exists( 'Clinica_Config' ) ? (string) Clinica_Config::get( 'moeda', 'MZN' ) : 'MZN';
	}

	/** Valor monetário por extenso: "12 000 MZN". */
	public static function moeda( float $v ): string {
		return number_format( $v, 0, ',', ' ' ) . ' ' . self::moeda_codigo();
	}

	/** Número compacto para eixos e rótulos: 950, "2 mil", "12,5 mil", "1,2 M". */
	public static function compacto( float $v ): string {
		$a = abs( $v );
		if ( $a >= 1000000 ) {
			return rtrim( rtrim( number_format( $v / 1000000, 1, ',', ' ' ), '0' ), ',' ) . ' M';
		}
		if ( $a >= 1000 ) {
			return rtrim( rtrim( number_format( $v / 1000, 1, ',', ' ' ), '0' ), ',' ) . ' mil';
		}
		return number_format( $v, 0, ',', ' ' );
	}

	/** Percentagem inteira com sinal de % ("—" sem base). */
	public static function pct( ?float $v ): string {
		return null === $v ? '—' : number_format( $v, 0, ',', ' ' ) . '%';
	}

	/**
	 * Escala "limpa" para o eixo Y: passo de 1, 2, 2,5 ou 5 vezes uma potência
	 * de 10 com 3 a 5 intervalos, e um mínimo para não haver marcas repetidas.
	 *
	 * @return array{0: float, 1: float} [máximo, passo]
	 */
	public static function escala( float $max, int $marcas = 4, float $minimo = 4 ): array {
		$max   = max( $max, $minimo );
		$pot   = 10 ** floor( log10( $max / $marcas ) );
		$passo = $pot;
		$n     = (int) ceil( $max / $passo );
		foreach ( array( 1, 2, 2.5, 5, 10 ) as $m ) {
			// Contagens não têm meios: um passo de 2,5 daria marcas "3" e "8".
			if ( 2.5 === (float) $m && $pot < 10 ) {
				continue;
			}
			$passo = max( 1, $m * $pot );
			$n     = (int) ceil( $max / $passo - 1e-9 );
			if ( $n <= $marcas + 1 ) {
				break;
			}
		}
		return array( $n * $passo, $passo );
	}

	/**
	 * Variação face ao período anterior, em texto com seta (nunca só cor).
	 *
	 * @param bool   $sobe_bom Subir é bom (receita) ou mau (por receber)?
	 * @param string $tipo     'pct' (variação relativa) ou 'pp' (pontos percentuais).
	 */
	public static function variacao( ?float $atual, ?float $anterior, bool $sobe_bom = true, string $tipo = 'pct', string $contra = 'período anterior' ): string {
		if ( null === $atual || null === $anterior || ( 'pct' === $tipo && $anterior <= 0 ) ) {
			return '<span class="gf-var gf-var--neutra">Sem base de comparação</span>';
		}
		$d = 'pp' === $tipo ? $atual - $anterior : ( $atual - $anterior ) / $anterior * 100;
		$d = round( $d );
		if ( 0.0 === (float) $d ) {
			return '<span class="gf-var gf-var--neutra"><b>Sem variação</b> vs ' . esc_html( $contra ) . '</span>';
		}
		$sobe  = $d > 0;
		$bom   = $sobe === $sobe_bom;
		$seta  = $sobe ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6';
		$texto = ( $sobe ? '+' : '−' ) . number_format( abs( $d ), 0, ',', ' ' ) . ( 'pp' === $tipo ? ' p.p.' : '%' );
		return sprintf(
			'<span class="gf-var gf-var--%1$s"><b><svg viewBox="0 0 24 24" aria-hidden="true"><path d="%2$s"/></svg>%3$s</b> vs %4$s</span>',
			$bom ? 'boa' : 'ma',
			$seta,
			esc_html( $texto ),
			esc_html( $contra )
		);
	}

	/**
	 * Sparkline de um cartão de número: linha no cinzento de contexto e o ponto
	 * atual (o último) no teal da marca.
	 *
	 * @param array<int, float|int> $valores
	 */
	public static function sparkline( array $valores ): string {
		$valores = array_values( array_map( 'floatval', $valores ) );
		$n       = count( $valores );
		// Sem atividade não há tendência a mostrar: o cartão fica só com o número.
		if ( $n < 2 || ! array_sum( $valores ) ) {
			return '';
		}
		$max = max( $valores ) ?: 1;
		$pts = array();
		foreach ( $valores as $i => $v ) {
			$pts[] = round( $i / ( $n - 1 ) * 100, 2 ) . ',' . round( 26 - $v / $max * 22, 2 );
		}
		$ultimo = explode( ',', end( $pts ) );
		return sprintf(
			'<span class="gf-spark" aria-hidden="true"><svg viewBox="0 0 100 28" preserveAspectRatio="none"><polyline points="%1$s" vector-effect="non-scaling-stroke"/></svg><i style="left:%2$s%%;top:%3$s%%"></i></span>',
			esc_attr( implode( ' ', $pts ) ),
			esc_attr( $ultimo[0] ),
			esc_attr( (string) round( (float) $ultimo[1] / 28 * 100, 2 ) )
		);
	}

	/** Medidor (role=meter): enchimento teal sobre um trilho do mesmo teal, mais claro. */
	public static function medidor( ?float $pct, string $rotulo ): string {
		$v = null === $pct ? 0 : max( 0, min( 100, $pct ) );
		return sprintf(
			'<span class="gf-medidor" role="meter" aria-label="%1$s" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%2$d"><i style="width:%2$d%%"></i></span>',
			esc_attr( $rotulo ),
			(int) round( $v )
		);
	}

	/**
	 * Gráfico de colunas (uma série ou empilhadas).
	 *
	 * @param array $a {
	 *   @type string   $titulo  Nome do gráfico (legenda da tabela de dados).
	 *   @type string[] $rotulos Rótulos curtos do eixo X.
	 *   @type string[] $longos  Títulos das dicas (um por coluna).
	 *   @type array    $series  [ [ 'nome', 'valores' => [], 'cor' => 's1' ] ], de baixo para cima.
	 *   @type callable $formato Valor -> texto (dicas e tabela).
	 *   @type callable $eixo    Valor -> texto (marcas do eixo Y).
	 *   @type int      $saltar  Mostra 1 rótulo X em cada N.
	 *   @type string   $vazio   Mensagem quando tudo é zero.
	 *   @type bool     $rotular Valor escrito por cima de cada coluna (poucas colunas).
	 *   @type string   $cabecalho Primeira coluna da tabela "Ver dados" (por omissão "Período").
	 * }
	 */
	public static function colunas( array $a ): string {
		$series  = $a['series'];
		$rotulos = $a['rotulos'];
		$longos  = $a['longos'] ?? $rotulos;
		$fmt     = $a['formato'] ?? static fn( $v ) => self::compacto( (float) $v );
		$eixo    = $a['eixo'] ?? static fn( $v ) => self::compacto( (float) $v );
		$saltar  = max( 1, (int) ( $a['saltar'] ?? 1 ) );
		$n       = count( $rotulos );

		$totais = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$totais[ $i ] = 0.0;
			foreach ( $series as $s ) {
				$totais[ $i ] += (float) ( $s['valores'][ $i ] ?? 0 );
			}
		}
		$agrupado = ! empty( $a['agrupado'] );
		$rotular  = ! empty( $a['rotular'] );
		$maior    = $totais ? max( $totais ) : 0;
		if ( $agrupado ) {
			$maior = 0.0;
			foreach ( $series as $s ) {
				$maior = max( $maior, (float) max( $s['valores'] ?: array( 0 ) ) );
			}
		}
		list( $max, $passo ) = self::escala( (float) $maior, 4, (float) ( $a['minimo'] ?? 4 ) );
		$i_max = ( $maior > 0 && ! $agrupado ) ? array_search( $maior, $totais, true ) : -1;

		ob_start();
		?>
		<figure class="gf gf--colunas<?php echo $agrupado ? ' gf--agrupado' : ''; ?>" style="--gf-n:<?php echo (int) $n; ?>;--gf-s:<?php echo (int) count( $series ); ?>">
			<?php echo ( $a['legenda'] ?? true ) ? self::legenda( $series, 'barra' ) : ''; ?>
			<div class="gf-area">
				<?php echo self::eixo_y( $max, $passo, $eixo ); ?>
				<div class="gf-plot">
					<?php echo self::grelha( $max, $passo ); ?>
					<div class="gf-colunas">
						<?php for ( $i = 0; $i < $n; $i++ ) :
							$linhas = array();
							foreach ( $agrupado ? $series : array_reverse( $series ) as $s ) {
								$linhas[] = array( $s['cor'], $s['nome'], $fmt( (float) ( $s['valores'][ $i ] ?? 0 ) ) );
							}
							if ( count( $series ) > 1 && ! $agrupado ) {
								$linhas[] = array( '', 'Total', $fmt( $totais[ $i ] ) );
							}
							$alt = $max > 0 ? $totais[ $i ] / $max * 100 : 0;
							?>
							<div class="gf-col" tabindex="0" data-gf-titulo="<?php echo esc_attr( $longos[ $i ] ?? '' ); ?>" data-gf-linhas="<?php echo esc_attr( wp_json_encode( $linhas ) ); ?>">
								<?php if ( $agrupado ) : ?>
									<span class="gf-col__grupo">
										<?php foreach ( $series as $s ) :
											$v = (float) ( $s['valores'][ $i ] ?? 0 );
											?>
											<i class="gf-col__barra gf-c--<?php echo esc_attr( $s['cor'] ); ?>" style="height:<?php echo esc_attr( (string) round( $max > 0 ? $v / $max * 100 : 0, 2 ) ); ?>%"><?php if ( $rotular && $v > 0 ) : ?><em class="gf-col__rot"><?php echo esc_html( $eixo( $v ) ); ?></em><?php endif; ?></i>
										<?php endforeach; ?>
									</span>
								<?php elseif ( $totais[ $i ] > 0 ) : ?>
									<span class="gf-col__pilha" style="height:<?php echo esc_attr( (string) round( $alt, 2 ) ); ?>%">
										<?php foreach ( $series as $s ) :
											$v = (float) ( $s['valores'][ $i ] ?? 0 );
											if ( $v <= 0 ) {
												continue;
											}
											?>
											<i class="gf-seg gf-c--<?php echo esc_attr( $s['cor'] ); ?>" style="flex-grow:<?php echo esc_attr( (string) round( $v, 2 ) ); ?>"></i>
										<?php endforeach; ?>
									</span>
									<?php if ( $i === $i_max || $rotular ) : ?>
										<b class="gf-col__v" style="bottom:<?php echo esc_attr( (string) round( $alt, 2 ) ); ?>%"><?php echo esc_html( $eixo( $totais[ $i ] ) ); ?></b>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						<?php endfor; ?>
					</div>
					<?php if ( $maior <= 0 && ! empty( $a['vazio'] ) ) : ?>
						<p class="gf-vazio"><?php echo esc_html( $a['vazio'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php echo self::eixo_x( $rotulos, $saltar ); ?>
			</div>
			<?php echo self::tabela( $a['titulo'] ?? '', $longos, $series, $fmt, ( count( $series ) > 1 && ! $agrupado ) ? $totais : null, $a['cabecalho'] ?? 'Período' ); ?>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Gráfico de linhas. A primeira série é a principal (área e ponto final
	 * rotulado); as outras são linhas por cima. Cursor vertical por coluna, com
	 * todas as séries na mesma dica.
	 *
	 * Mesmos argumentos de colunas(), mais:
	 *  - id       id da figura (para as pílulas de filtro .gf-filtro).
	 *  - suave    curva monótona (passa nos pontos, sem inventar picos).
	 *  - areas    'primeira' (por omissão) ou 'todas' (áreas sobrepostas).
	 *  - pontos   true: pontos sempre visíveis na série principal;
	 *             'valores': pontos em todas as séries, só onde o valor não é zero.
	 *  - quedas   true: filete de cada ponto da série principal até ao eixo.
	 *  - legenda  false quando as pílulas de filtro já fazem de legenda.
	 *  - final    false para não rotular o último ponto.
	 *  - com_eixo false: sem eixo Y nem grelha (só a forma; valores na dica e na tabela).
	 */
	public static function linhas( array $a ): string {
		$series  = array_values( $a['series'] );
		$rotulos = $a['rotulos'];
		$longos  = $a['longos'] ?? $rotulos;
		$fmt     = $a['formato'] ?? static fn( $v ) => self::compacto( (float) $v );
		$eixo    = $a['eixo'] ?? static fn( $v ) => self::compacto( (float) $v );
		$saltar  = max( 1, (int) ( $a['saltar'] ?? 1 ) );
		$suave   = ! empty( $a['suave'] );
		$areas   = $a['areas'] ?? 'primeira';
		$n       = count( $rotulos );
		$uid     = 'gf' . wp_unique_id();

		$maior = 0.0;
		foreach ( $series as $s ) {
			$maior = max( $maior, (float) max( $s['valores'] ?: array( 0 ) ) );
		}
		list( $max, $passo ) = self::escala( $maior, 4, (float) ( $a['minimo'] ?? 4 ) );

		// Pontos no centro de cada coluna (alinhados com as zonas do cursor).
		$x   = static fn( int $i ): float => ( $i + 0.5 ) / max( 1, $n ) * 1000;
		$y   = static fn( float $v ): float => 300 - ( $max > 0 ? $v / $max * 300 : 0 );
		$alt = static fn( float $v ): float => round( $max > 0 ? $v / $max * 100 : 0, 2 );

		ob_start();
		?>
		<figure class="gf gf--linhas<?php echo ! empty( $a['quedas'] ) ? ' gf--quedas' : ''; ?><?php echo isset( $a['com_eixo'] ) && ! $a['com_eixo'] ? ' gf--limpo' : ''; ?>"<?php echo ! empty( $a['id'] ) ? ' id="' . esc_attr( $a['id'] ) . '"' : ''; ?> style="--gf-n:<?php echo (int) $n; ?>">
			<?php echo ( $a['legenda'] ?? true ) ? self::legenda( $series, 'linha' ) : ''; ?>
			<div class="gf-area">
				<?php echo self::eixo_y( $max, $passo, $eixo ); ?>
				<div class="gf-plot">
					<?php echo self::grelha( $max, $passo ); ?>
					<svg class="gf-svg" viewBox="0 0 1000 300" preserveAspectRatio="none" aria-hidden="true">
						<defs>
							<?php foreach ( $series as $k => $s ) : ?>
								<linearGradient id="<?php echo esc_attr( $uid . '-' . $k ); ?>" x1="0" y1="0" x2="0" y2="1">
									<stop offset="0" class="gf-veu-topo gf-c--<?php echo esc_attr( $s['cor'] ); ?>"></stop>
									<stop offset="1" class="gf-veu-base gf-c--<?php echo esc_attr( $s['cor'] ); ?>"></stop>
								</linearGradient>
							<?php endforeach; ?>
						</defs>
						<?php
						if ( ! empty( $a['quedas'] ) ) :
							for ( $i = 0; $i < $n; $i++ ) :
								$v = (float) ( $series[0]['valores'][ $i ] ?? 0 );
								?>
								<line class="gf-queda" data-gf-serie="0" x1="<?php echo esc_attr( (string) round( $x( $i ), 1 ) ); ?>" x2="<?php echo esc_attr( (string) round( $x( $i ), 1 ) ); ?>" y1="<?php echo esc_attr( (string) round( $y( $v ), 1 ) ); ?>" y2="300" vector-effect="non-scaling-stroke"></line>
								<?php
							endfor;
						endif;
						foreach ( array_reverse( $series, true ) as $k => $s ) :
							$pts = array();
							for ( $i = 0; $i < $n; $i++ ) {
								$pts[] = array( $x( $i ), $y( (float) ( $s['valores'][ $i ] ?? 0 ) ) );
							}
							$d = self::caminho( $pts, $suave );
							if ( 0 === $k || 'todas' === $areas ) :
								?>
								<path class="gf-area__veu" data-gf-serie="<?php echo (int) $k; ?>" fill="url(#<?php echo esc_attr( $uid . '-' . $k ); ?>)" d="<?php echo esc_attr( $d . ' L' . round( $x( $n - 1 ), 1 ) . ',300 L' . round( $x( 0 ), 1 ) . ',300 Z' ); ?>"></path>
							<?php endif; ?>
							<path class="gf-traco gf-c--<?php echo esc_attr( $s['cor'] ); ?>" data-gf-serie="<?php echo (int) $k; ?>" d="<?php echo esc_attr( $d ); ?>" vector-effect="non-scaling-stroke"></path>
						<?php endforeach; ?>
					</svg>
					<div class="gf-alvos">
						<?php for ( $i = 0; $i < $n; $i++ ) :
							$linhas = array();
							foreach ( $series as $k => $s ) {
								$linhas[] = array( $s['cor'], $s['nome'], $fmt( (float) ( $s['valores'][ $i ] ?? 0 ) ), $k );
							}
							$ultimo = ( $i === $n - 1 ) && $maior > 0 && ( $a['final'] ?? true );
							?>
							<div class="gf-alvo<?php echo $ultimo ? ' is-ultimo' : ''; ?>" tabindex="0" data-gf-titulo="<?php echo esc_attr( $longos[ $i ] ?? '' ); ?>" data-gf-linhas="<?php echo esc_attr( wp_json_encode( $linhas ) ); ?>">
								<?php foreach ( $series as $k => $s ) :
									$fixo = 'valores' === ( $a['pontos'] ?? null )
										? (float) ( $s['valores'][ $i ] ?? 0 ) > 0
										: ( 0 === $k ) && ( $ultimo || ( ! empty( $a['pontos'] ) && $maior > 0 ) );
									?>
									<i class="gf-ponto gf-c--<?php echo esc_attr( $s['cor'] ); ?><?php echo $fixo ? ' is-fixo' : ''; ?>" data-gf-serie="<?php echo (int) $k; ?>" style="bottom:<?php echo esc_attr( (string) $alt( (float) ( $s['valores'][ $i ] ?? 0 ) ) ); ?>%"></i>
								<?php endforeach; ?>
								<?php if ( $ultimo ) : ?>
									<b class="gf-alvo__v" data-gf-serie="0" style="bottom:<?php echo esc_attr( (string) $alt( (float) ( $series[0]['valores'][ $i ] ?? 0 ) ) ); ?>%"><?php echo esc_html( $eixo( (float) ( $series[0]['valores'][ $i ] ?? 0 ) ) ); ?></b>
								<?php endif; ?>
							</div>
						<?php endfor; ?>
					</div>
					<?php if ( $maior <= 0 && ! empty( $a['vazio'] ) ) : ?>
						<p class="gf-vazio"><?php echo esc_html( $a['vazio'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php echo self::eixo_x( $rotulos, $saltar ); ?>
			</div>
			<?php echo self::tabela( $a['titulo'] ?? '', $longos, $series, $fmt ); ?>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Caminho SVG por pontos. Suave = curva cúbica monótona (Fritsch-Carlson):
	 * passa em todos os pontos e nunca ultrapassa um máximo real nem desce
	 * abaixo de zero entre dois pontos, por isso não inventa valores.
	 *
	 * @param array<int, array{0: float, 1: float}> $p
	 */
	private static function caminho( array $p, bool $suave ): string {
		$n = count( $p );
		$r = static fn( float $v ): string => (string) round( $v, 1 );
		if ( ! $suave || $n < 3 ) {
			return 'M' . implode( ' L', array_map( static fn( $q ) => $r( $q[0] ) . ',' . $r( $q[1] ), $p ) );
		}
		$dx = array();
		$m  = array();
		for ( $i = 0; $i < $n - 1; $i++ ) {
			$dx[ $i ] = $p[ $i + 1 ][0] - $p[ $i ][0];
			$m[ $i ]  = $dx[ $i ] ? ( $p[ $i + 1 ][1] - $p[ $i ][1] ) / $dx[ $i ] : 0.0;
		}
		$t = array( $m[0] );
		for ( $i = 1; $i < $n - 1; $i++ ) {
			$t[ $i ] = ( $m[ $i - 1 ] * $m[ $i ] <= 0 ) ? 0.0 : ( $m[ $i - 1 ] + $m[ $i ] ) / 2;
		}
		$t[ $n - 1 ] = $m[ $n - 2 ];
		for ( $i = 0; $i < $n - 1; $i++ ) {
			if ( 0.0 === (float) $m[ $i ] ) {
				$t[ $i ]     = 0.0;
				$t[ $i + 1 ] = 0.0;
				continue;
			}
			$aa = $t[ $i ] / $m[ $i ];
			$bb = $t[ $i + 1 ] / $m[ $i ];
			$h  = $aa * $aa + $bb * $bb;
			if ( $h > 9 ) {
				$k           = 3 / sqrt( $h );
				$t[ $i ]     = $k * $aa * $m[ $i ];
				$t[ $i + 1 ] = $k * $bb * $m[ $i ];
			}
		}
		$d = 'M' . $r( $p[0][0] ) . ',' . $r( $p[0][1] );
		for ( $i = 0; $i < $n - 1; $i++ ) {
			$h  = $dx[ $i ] / 3;
			$d .= ' C' . $r( $p[ $i ][0] + $h ) . ',' . $r( $p[ $i ][1] + $t[ $i ] * $h )
				. ' ' . $r( $p[ $i + 1 ][0] - $h ) . ',' . $r( $p[ $i + 1 ][1] - $t[ $i + 1 ] * $h )
				. ' ' . $r( $p[ $i + 1 ][0] ) . ',' . $r( $p[ $i + 1 ][1] );
		}
		return $d;
	}

	/**
	 * Anel (parte de um todo, até 6 fatias): fatias com 2px de ar entre si,
	 * número ao centro e legenda com percentagens. Para categorias ordenadas
	 * (faixas etárias) usa a rampa ordinal o1→o3 do mesmo teal.
	 *
	 * @param array $a {
	 *   @type string $titulo  Nome (dica e leitores de ecrã).
	 *   @type array  $itens   [ [ 'rotulo', 'valor', 'cor' => 'o1' ] ].
	 *   @type string $centro  Número ao centro.
	 *   @type string $sub     Texto por baixo do número.
	 *   @type string $vazio   Mensagem quando o total é zero.
	 * }
	 */
	public static function anel( array $a ): string {
		$itens = $a['itens'];
		$total = array_sum( array_map( static fn( $i ) => (float) $i['valor'], $itens ) );
		$vivos = array_filter( $itens, static fn( $i ) => (float) $i['valor'] > 0 );
		$ar    = count( $vivos ) > 1 ? 0.8 : 0;
		$pct   = static fn( $v ) => $total > 0 ? (float) $v / $total * 100 : 0;
		ob_start();
		?>
		<figure class="gf gf--anel">
			<div class="gf-anel">
				<svg viewBox="0 0 120 120" role="img" aria-label="<?php echo esc_attr( $a['titulo'] ?? '' ); ?>">
					<circle class="gf-anel__fundo" cx="60" cy="60" r="48" pathLength="100"></circle>
					<?php
					$acc = 0.0;
					foreach ( $itens as $it ) :
						$p = $pct( $it['valor'] );
						if ( $p <= 0 ) {
							continue;
						}
						$linhas = array( array( $it['cor'], $it['rotulo'], number_format( $p, 0, ',', ' ' ) . '%' . ( isset( $it['texto'] ) ? ' · ' . $it['texto'] : '' ) ) );
						?>
						<circle class="gf-anel__fatia gf-c--<?php echo esc_attr( $it['cor'] ); ?>" cx="60" cy="60" r="48" pathLength="100"
							stroke-dasharray="<?php echo esc_attr( round( max( 0.5, $p - $ar ), 2 ) . ' ' . round( 100 - max( 0.5, $p - $ar ), 2 ) ); ?>"
							stroke-dashoffset="<?php echo esc_attr( (string) round( -$acc, 2 ) ); ?>"
							tabindex="0" data-gf-titulo="<?php echo esc_attr( $a['titulo'] ?? '' ); ?>" data-gf-linhas="<?php echo esc_attr( wp_json_encode( $linhas ) ); ?>"></circle>
						<?php
						$acc += $p;
					endforeach;
					?>
				</svg>
				<div class="gf-anel__centro">
					<b><?php echo esc_html( $a['centro'] ?? (string) $total ); ?></b>
					<?php if ( ! empty( $a['sub'] ) ) : ?><span><?php echo esc_html( $a['sub'] ); ?></span><?php endif; ?>
				</div>
			</div>
			<?php if ( $total > 0 && ( $a['legenda'] ?? true ) ) : ?>
				<ul class="gf-anel__legenda">
					<?php foreach ( $itens as $it ) : ?>
						<li><i class="gf-chave gf-chave--ponto gf-c--<?php echo esc_attr( $it['cor'] ); ?>"></i><?php echo esc_html( $it['rotulo'] ); ?><b><?php echo esc_html( number_format( $pct( $it['valor'] ), 0, ',', ' ' ) ); ?>%</b></li>
					<?php endforeach; ?>
				</ul>
			<?php elseif ( $total <= 0 ) : ?>
				<p class="gf-vazio gf-vazio--solto"><?php echo esc_html( $a['vazio'] ?? 'Sem dados.' ); ?></p>
			<?php endif; ?>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Mostrador semicircular com ponteiro (progresso face a uma meta).
	 * O arco teal é o feito; o resto do arco, no mesmo teal claro, é o que falta.
	 *
	 * @param float|null $pct    0–100 (null = sem meta).
	 * @param string     $rotulo Nome (leitores de ecrã).
	 * @param string     $min    Texto da ponta esquerda.
	 * @param string     $max    Texto da ponta direita.
	 */
	public static function ponteiro( ?float $pct, string $rotulo, string $min = '0', string $max = '100%' ): string {
		$v   = null === $pct ? 0.0 : max( 0.0, min( 100.0, $pct ) );
		$ang = M_PI * ( 1 - $v / 100 );
		$px  = round( 100 + 70 * cos( $ang ), 2 );
		$py  = round( 100 - 70 * sin( $ang ), 2 );
		ob_start();
		?>
		<figure class="gf gf--ponteiro" role="meter" aria-label="<?php echo esc_attr( $rotulo ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) round( $v ); ?>">
			<svg viewBox="0 0 200 116" aria-hidden="true">
				<path class="gf-ponteiro__fundo" d="M20 100 A80 80 0 0 1 180 100" pathLength="100"></path>
				<path class="gf-ponteiro__feito" d="M20 100 A80 80 0 0 1 180 100" pathLength="100" stroke-dasharray="<?php echo esc_attr( round( $v, 2 ) . ' 100' ); ?>"></path>
				<line class="gf-ponteiro__agulha" x1="100" y1="100" x2="<?php echo esc_attr( (string) $px ); ?>" y2="<?php echo esc_attr( (string) $py ); ?>"></line>
				<circle class="gf-ponteiro__eixo" cx="100" cy="100" r="7"></circle>
			</svg>
			<figcaption class="gf-ponteiro__leg"><span><?php echo esc_html( $min ); ?></span><b><?php echo null === $pct ? '—' : esc_html( number_format( $v, 0, ',', ' ' ) . '%' ); ?></b><span><?php echo esc_html( $max ); ?></span></figcaption>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Anéis de progresso concêntricos: cada anel é UMA taxa face a 100%
	 * (ex.: comparência, cobrança), com trilho claro e ponta arredondada.
	 * Um anel por indicador, cada um com o seu valor escrito na legenda,
	 * por isso a cor nunca é a única pista. Até três anéis.
	 *
	 * @param array $a {
	 *   @type string $titulo Nome (leitores de ecrã).
	 *   @type array  $itens  [ [ 'rotulo', 'pct' (0–100 ou null), 'cor' => 'c1', 'det' ] ], de fora para dentro.
	 * }
	 */
	public static function aneis( array $a ): string {
		$itens = array_slice( array_values( $a['itens'] ), 0, 3 );
		$raios = array( 52, 37, 22 );
		ob_start();
		?>
		<figure class="gf gf--aneis">
			<svg class="gf-aneis" viewBox="0 0 132 132" role="img" aria-label="<?php echo esc_attr( $a['titulo'] ?? '' ); ?>">
				<?php foreach ( $itens as $k => $it ) :
					$r = $raios[ $k ];
					$p = null === $it['pct'] ? 0.0 : max( 0.0, min( 100.0, (float) $it['pct'] ) );
					$linhas = array( array( $it['cor'], $it['rotulo'], null === $it['pct'] ? 'Sem dados' : number_format( $p, 0, ',', ' ' ) . '%' ) );
					if ( ! empty( $it['det'] ) ) {
						$linhas[] = array( '', '', $it['det'] );
					}
					?>
					<circle class="gf-aneis__trilho gf-c--<?php echo esc_attr( $it['cor'] ); ?>" cx="66" cy="66" r="<?php echo (int) $r; ?>"></circle>
					<?php if ( $p > 0 ) : ?>
						<circle class="gf-aneis__arco gf-c--<?php echo esc_attr( $it['cor'] ); ?>" cx="66" cy="66" r="<?php echo (int) $r; ?>" pathLength="100"
							stroke-dasharray="<?php echo esc_attr( round( $p, 2 ) . ' 100' ); ?>"
							tabindex="0" data-gf-titulo="<?php echo esc_attr( $a['titulo'] ?? '' ); ?>" data-gf-linhas="<?php echo esc_attr( wp_json_encode( $linhas ) ); ?>"></circle>
					<?php endif; ?>
				<?php endforeach; ?>
			</svg>
			<ul class="gf-aneis__legenda">
				<?php foreach ( $itens as $it ) : ?>
					<li>
						<i class="gf-chave gf-chave--ponto gf-c--<?php echo esc_attr( $it['cor'] ); ?>"></i>
						<span><?php echo esc_html( $it['rotulo'] ); ?></span>
						<b><?php echo null === $it['pct'] ? '—' : esc_html( number_format( (float) $it['pct'], 0, ',', ' ' ) . '%' ); ?></b>
						<?php if ( ! empty( $it['det'] ) ) : ?><small><?php echo esc_html( $it['det'] ); ?></small><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Colunas divergentes a partir de uma linha central: a primeira série
	 * sobe (ex.: recebido), a segunda desce (ex.: por receber). Um só eixo,
	 * simétrico, com as duas cores validadas como par (teal e terracota).
	 *
	 * @param array $a { titulo, rotulos, longos, cima => [nome, valores, cor], baixo => [nome, valores, cor], formato, eixo, saltar, vazio, minimo }
	 */
	public static function divergente( array $a ): string {
		$rotulos = $a['rotulos'];
		$longos  = $a['longos'] ?? $rotulos;
		$cima    = $a['cima'];
		$baixo   = $a['baixo'];
		$fmt     = $a['formato'] ?? static fn( $v ) => self::compacto( (float) $v );
		$eixo    = $a['eixo'] ?? static fn( $v ) => self::compacto( (float) $v );
		$n       = count( $rotulos );
		$maior   = max( (float) max( $cima['valores'] ?: array( 0 ) ), (float) max( $baixo['valores'] ?: array( 0 ) ) );
		list( $max ) = self::escala( $maior, 2, (float) ( $a['minimo'] ?? 4 ) );
		$marcas  = array( $max, $max / 2, 0, -$max / 2, -$max );
		$pct     = static fn( float $v ): float => $max > 0 ? round( $v / $max * 100, 2 ) : 0;
		ob_start();
		?>
		<figure class="gf gf--divergente" style="--gf-n:<?php echo (int) $n; ?>">
			<ul class="gf-legenda">
				<li><i class="gf-chave gf-chave--ponto gf-c--<?php echo esc_attr( $cima['cor'] ); ?>"></i><?php echo esc_html( $cima['nome'] ); ?></li>
				<li><i class="gf-chave gf-chave--ponto gf-c--<?php echo esc_attr( $baixo['cor'] ); ?>"></i><?php echo esc_html( $baixo['nome'] ); ?></li>
			</ul>
			<div class="gf-area">
				<div class="gf-y" aria-hidden="true">
					<?php foreach ( $marcas as $m ) : ?>
						<span><?php echo esc_html( ( $m < 0 ? '−' : '' ) . $eixo( abs( $m ) ) ); ?></span>
					<?php endforeach; ?>
				</div>
				<div class="gf-plot">
					<div class="gf-grelha gf-grelha--div" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
					<div class="gf-colunas">
						<?php for ( $i = 0; $i < $n; $i++ ) :
							$vc = (float) ( $cima['valores'][ $i ] ?? 0 );
							$vb = (float) ( $baixo['valores'][ $i ] ?? 0 );
							$linhas = array(
								array( $cima['cor'], $cima['nome'], $fmt( $vc ) ),
								array( $baixo['cor'], $baixo['nome'], $fmt( $vb ) ),
							);
							?>
							<div class="gf-col gf-div__col" tabindex="0" data-gf-titulo="<?php echo esc_attr( $longos[ $i ] ?? '' ); ?>" data-gf-linhas="<?php echo esc_attr( wp_json_encode( $linhas ) ); ?>">
								<span class="gf-div__cima"><i class="gf-c--<?php echo esc_attr( $cima['cor'] ); ?>" style="height:<?php echo esc_attr( (string) $pct( $vc ) ); ?>%"></i></span>
								<span class="gf-div__baixo"><i class="gf-c--<?php echo esc_attr( $baixo['cor'] ); ?>" style="height:<?php echo esc_attr( (string) $pct( $vb ) ); ?>%"></i></span>
							</div>
						<?php endfor; ?>
					</div>
					<?php if ( $maior <= 0 && ! empty( $a['vazio'] ) ) : ?>
						<p class="gf-vazio"><?php echo esc_html( $a['vazio'] ); ?></p>
					<?php endif; ?>
				</div>
				<?php echo self::eixo_x( $rotulos, max( 1, (int) ( $a['saltar'] ?? 1 ) ) ); ?>
			</div>
			<?php echo self::tabela( $a['titulo'] ?? '', $longos, array( $cima, $baixo ), $fmt ); ?>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Barras horizontais "parte de um total": a barra clara é o total (ex.:
	 * consultas marcadas) e a escura, por dentro, a parte (as realizadas).
	 * Comprimentos na mesma escala, valor escrito ("3 de 5") e dica com o
	 * detalhe, por isso a cor nunca é a única pista.
	 *
	 * @param array $a {
	 *   @type string $titulo Nome do gráfico (tabela "Ver dados").
	 *   @type array  $nomes  [ nome da parte, nome do total ].
	 *   @type array  $cores  [ cor da parte, cor do total ].
	 *   @type array  $itens  [ [ 'rotulo', 'sub', 'parte', 'total', 'extra' => [ [ 'Faltas', '1' ] ] ] ].
	 *   @type string $cabecalho Primeira coluna da tabela.
	 * }
	 */
	public static function barras_parte( array $a ): string {
		$itens = array_values( $a['itens'] );
		$nomes = $a['nomes'] ?? array( 'Parte', 'Total' );
		$cores = $a['cores'] ?? array( 'c1', 'o1' );
		$max   = 0.0;
		foreach ( $itens as $it ) {
			$max = max( $max, (float) $it['total'], (float) $it['parte'] );
		}
		ob_start();
		?>
		<figure class="gf gf--parte">
			<?php echo self::legenda( array( array( 'nome' => $nomes[0], 'cor' => $cores[0] ), array( 'nome' => $nomes[1], 'cor' => $cores[1] ) ), 'barra' ); ?>
			<ul class="gf-parte">
				<?php foreach ( $itens as $it ) :
					$parte  = (float) $it['parte'];
					$total  = max( (float) $it['total'], $parte );
					$linhas = array( array( $cores[0], $nomes[0], number_format_i18n( $parte ) ), array( $cores[1], $nomes[1], number_format_i18n( $total ) ) );
					foreach ( $it['extra'] ?? array() as $x ) {
						$linhas[] = array( '', $x[0], $x[1] );
					}
					?>
					<li class="gf-parte__i" tabindex="0" data-gf-titulo="<?php echo esc_attr( $it['rotulo'] ); ?>" data-gf-linhas="<?php echo esc_attr( wp_json_encode( $linhas ) ); ?>">
						<span class="gf-parte__r"><?php echo esc_html( $it['rotulo'] ); ?><?php if ( ! empty( $it['sub'] ) ) : ?><small><?php echo esc_html( $it['sub'] ); ?></small><?php endif; ?></span>
						<span class="gf-parte__v"><b><?php echo esc_html( number_format_i18n( $parte ) ); ?></b> de <?php echo esc_html( number_format_i18n( $total ) ); ?></span>
						<span class="gf-parte__trilho">
							<?php if ( $total > 0 ) : ?>
								<i class="gf-parte__total gf-c--<?php echo esc_attr( $cores[1] ); ?>" style="width:<?php echo esc_attr( (string) round( $total / max( 1, $max ) * 100, 2 ) ); ?>%"><i class="gf-parte__feito gf-c--<?php echo esc_attr( $cores[0] ); ?>" style="width:<?php echo esc_attr( (string) round( $parte / $total * 100, 2 ) ); ?>%"></i></i>
							<?php endif; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php
			echo self::tabela(
				$a['titulo'] ?? '',
				array_column( $itens, 'rotulo' ),
				array(
					array( 'nome' => $nomes[0], 'valores' => array_map( static fn( $it ) => (float) $it['parte'], $itens ) ),
					array( 'nome' => $nomes[1], 'valores' => array_map( static fn( $it ) => (float) $it['total'], $itens ) ),
				),
				static fn( $v ) => number_format_i18n( (int) $v ),
				null,
				$a['cabecalho'] ?? 'Nome'
			);
			?>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Barras horizontais de uma série (categorias nominais = uma só cor):
	 * rótulo e valor por cima, barra fina por baixo.
	 *
	 * @param array $a { titulo, itens => [ [ 'rotulo', 'valor' (número), 'texto', 'det' ] ], vazio }
	 */
	public static function barras( array $a ): string {
		$itens = $a['itens'];
		$max   = 0.0;
		foreach ( $itens as $it ) {
			$max = max( $max, (float) $it['valor'] );
		}
		ob_start();
		?>
		<figure class="gf gf--barras">
			<?php if ( $max <= 0 ) : ?>
				<p class="gf-vazio gf-vazio--solto"><?php echo esc_html( $a['vazio'] ?? 'Sem dados neste período.' ); ?></p>
			<?php else : ?>
				<ul class="gf-barras" aria-label="<?php echo esc_attr( $a['titulo'] ?? '' ); ?>">
					<?php foreach ( $itens as $it ) :
						$linhas = array( array( 's1', $it['rotulo'], $it['texto'] ) );
						if ( ! empty( $it['det'] ) ) {
							$linhas[] = array( '', '', $it['det'] );
						}
						?>
						<li class="gf-barra" tabindex="0" data-gf-titulo="<?php echo esc_attr( $a['titulo'] ?? '' ); ?>" data-gf-linhas="<?php echo esc_attr( wp_json_encode( $linhas ) ); ?>">
							<span class="gf-barra__r"><?php echo esc_html( $it['rotulo'] ); ?></span>
							<span class="gf-barra__v"><?php echo esc_html( $it['texto'] ); ?><?php if ( ! empty( $it['det'] ) ) : ?> <small><?php echo esc_html( $it['det'] ); ?></small><?php endif; ?></span>
							<span class="gf-barra__trilho"><i class="gf-c--s1" style="width:<?php echo esc_attr( (string) round( (float) $it['valor'] / $max * 100, 2 ) ); ?>%"></i></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</figure>
		<?php
		return (string) ob_get_clean();
	}

	/* Peças comuns */

	/** Legenda (só com 2+ séries): retângulo para barras, traço para linhas. */
	private static function legenda( array $series, string $forma ): string {
		if ( count( $series ) < 2 ) {
			return '';
		}
		$h = '<ul class="gf-legenda">';
		foreach ( $series as $s ) {
			$h .= '<li><i class="gf-chave gf-chave--' . esc_attr( $forma ) . ' gf-c--' . esc_attr( $s['cor'] ) . '"></i>' . esc_html( $s['nome'] ) . '</li>';
		}
		return $h . '</ul>';
	}

	private static function eixo_y( float $max, float $passo, callable $eixo ): string {
		$h = '<div class="gf-y" aria-hidden="true">';
		for ( $v = $max; $v >= -0.001; $v -= $passo ) {
			$h .= '<span>' . esc_html( $eixo( max( 0, $v ) ) ) . '</span>';
		}
		return $h . '</div>';
	}

	private static function grelha( float $max, float $passo ): string {
		$h = '<div class="gf-grelha" aria-hidden="true">';
		for ( $v = $max; $v >= -0.001; $v -= $passo ) {
			$h .= '<i></i>';
		}
		return $h . '</div>';
	}

	private static function eixo_x( array $rotulos, int $saltar ): string {
		$n = count( $rotulos );
		$h = '<div class="gf-x" aria-hidden="true">';
		foreach ( array_values( $rotulos ) as $i => $r ) {
			// Mostra 1 em cada N, contando a partir do fim: o último fica sempre.
			// Com todos visíveis, os alternados (is-alt) saem em ecrãs estreitos.
			$alt = 1 === $saltar && $n > 7 && 1 === ( $n - 1 - $i ) % 2;
			$h  .= '<span' . ( $alt ? ' class="is-alt"' : '' ) . '>' . ( 0 === ( $n - 1 - $i ) % $saltar ? esc_html( $r ) : '' ) . '</span>';
		}
		return $h . '</div>';
	}

	/** A tabela gémea do gráfico (acessível, sem depender do rato). */
	private static function tabela( string $titulo, array $longos, array $series, callable $fmt, ?array $totais = null, string $cab = 'Período' ): string {
		$h  = '<details class="gf-dados"><summary>Ver dados</summary><div class="gf-dados__rolo"><table>';
		$h .= '<caption>' . esc_html( $titulo ) . '</caption><thead><tr><th scope="col">' . esc_html( $cab ) . '</th>';
		foreach ( $series as $s ) {
			$h .= '<th scope="col">' . esc_html( $s['nome'] ) . '</th>';
		}
		$h .= $totais ? '<th scope="col">Total</th>' : '';
		$h .= '</tr></thead><tbody>';
		foreach ( array_values( $longos ) as $i => $l ) {
			$h .= '<tr><th scope="row">' . esc_html( $l ) . '</th>';
			foreach ( $series as $s ) {
				$h .= '<td>' . esc_html( $fmt( (float) ( $s['valores'][ $i ] ?? 0 ) ) ) . '</td>';
			}
			$h .= $totais ? '<td>' . esc_html( $fmt( (float) $totais[ $i ] ) ) . '</td>' : '';
			$h .= '</tr>';
		}
		return $h . '</tbody></table></div></details>';
	}
}

/**
 * Peças dos painéis (.db-*): período escolhido, métricas derivadas, filtro,
 * ícones, iniciais, números em moeda e estados vazios. Usadas pelos quatro
 * portais (médico, gestão, receção e direção).
 */
final class Clinica_Painel {

	/** Ícones de traço (24×24, 1.8px) partilhados pelos portais. */
	const ICONES = array(
		'painel'     => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
		'pacientes'  => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		'paciente'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
		'consultas'  => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1.5"/><path d="M9 12h6M9 16h4"/>',
		'agenda'     => '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/>',
		'financeiro' => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18v3.5"/><path d="M3 7.5v10A2.5 2.5 0 0 0 5.5 20H20a1 1 0 0 0 1-1v-3"/><path d="M21 11.5h-4.5a2 2 0 0 0 0 4H21z"/>',
		'sair'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
		'procurar'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'sino'       => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
		'feito'      => '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/><path d="m9 15 2 2 4-4"/>',
		'tendencia'  => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
		'relogio'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'coracao'    => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7z"/>',
		'seta'       => '<path d="M7 17 17 7M8 7h9v9"/>',
		'mais'       => '<circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>',
		'pontos'     => '<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',
		'retorno'    => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
		'medico'     => '<path d="M6 3v6a6 6 0 0 0 12 0V3"/><path d="M12 15v2a4 4 0 0 0 8 0v-2"/><circle cx="20" cy="13" r="2"/>',
		'telemovel'  => '<rect x="6" y="2.5" width="12" height="19" rx="2.5"/><path d="M11 18h2"/>',
		'dinheiro'   => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 9.5v5M18 9.5v5"/>',
		'cartao'     => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/>',
		'entrar'     => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
		'registo'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
		'mensagem'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'definicoes' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
		'fila'       => '<path d="M3 6h18M3 12h12M3 18h7"/><circle cx="19" cy="17" r="3"/>',
		'pendente'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/><path d="M12 3v1"/>',
		'especialidades' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
		'subir'      => '<path d="M12 19V5M5 12l7-7 7 7"/>',
		'grafico'    => '<path d="M3 3v18h18"/><rect x="7" y="11" width="3" height="6" rx="1"/><rect x="12" y="7" width="3" height="10" rx="1"/><rect x="17" y="13" width="3" height="4" rx="1"/>',
		'alvo'       => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
		'verificar'  => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
		'rejeitar'   => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>',
	);

	/** SVG de um ícone pelo nome (aria-hidden: o texto ao lado diz o que é). */
	public static function icone( string $nome ): string {
		return '<svg class="db-ic" viewBox="0 0 24 24" aria-hidden="true">' . ( self::ICONES[ $nome ] ?? '' ) . '</svg>';
	}

	/** Iniciais de um nome (sem "Dr."/"Dra."), para os avatares de texto. */
	public static function iniciais( string $nome ): string {
		$p = preg_split( '/\s+/', trim( (string) preg_replace( '/\b(Dra?|Dr\(a\))\.?\s*/i', '', $nome ) ) ) ?: array();
		$i = mb_substr( $p[0] ?? '', 0, 1 ) . ( count( $p ) > 1 ? mb_substr( (string) end( $p ), 0, 1 ) : '' );
		return mb_strtoupper( $i ?: '?' );
	}

	/** Variação percentual face a uma base (sem base, null: não se inventa). */
	public static function var_pct( $atual, $anterior ): ?int {
		return $anterior > 0 ? (int) round( ( $atual - $anterior ) / $anterior * 100 ) : null;
	}

	/** Período pedido em ?periodo= (90 dias por omissão). */
	public static function periodo(): string {
		$p = isset( $_GET['periodo'] ) ? sanitize_key( wp_unslash( $_GET['periodo'] ) ) : '90d';
		return isset( Clinica_Analitica::PERIODOS_MEDICO[ $p ] ) ? $p : '90d';
	}

	/**
	 * Dados do período com as métricas derivadas já calculadas.
	 *
	 * @param int $medico_id 0 = clínica inteira.
	 */
	public static function dados( int $medico_id, ?string $periodo = null ): array {
		$d    = Clinica_Analitica::medico( $medico_id, $periodo ?? self::periodo() );
		$at   = $d['atual'];
		$ant  = $d['anterior'];
		$taxa = static fn( $parte, $todo ) => $todo > 0 ? $parte / $todo * 100 : null;

		$d['escopo']          = $medico_id ? 'medico' : 'clinica';
		$d['comparencia']     = $taxa( $at['realizadas'], $at['realizadas'] + $at['faltas'] );
		$d['comparencia_ant'] = $taxa( $ant['realizadas'], $ant['realizadas'] + $ant['faltas'] );
		$d['cobranca']        = $taxa( $at['recebido'], $at['faturado'] );
		$d['valor_medio']     = $at['n_pagamentos'] ? $at['faturado'] / $at['n_pagamentos'] : null;
		$d['valor_medio_ant'] = $ant['n_pagamentos'] ? $ant['faturado'] / $ant['n_pagamentos'] : null;
		$d['por_unidade']     = array( 'dia' => 'por dia', 'semana' => 'por semana', 'mes' => 'por mês' )[ $d['unidade'] ];
		$d['saltar']          = array( 'dia' => 5, 'semana' => 2, 'mes' => 1 )[ $d['unidade'] ];
		$d['contra']          = 'os ' . $d['label'] . ' anteriores';
		$d['serie_faturado']  = array_map( static fn( $a, $b ) => $a + $b, $d['serie']['recebido'], $d['serie']['por_receber'] );
		return $d;
	}

	/** Filtro de período: uma linha de ligações que mantém a vista (#painel, #financeiro…). */
	public static function filtro( string $vista, string $periodo ): void {
		$base = remove_query_arg( array( 'periodo', 'cl', 'estado', 'pac', 'atender' ) );
		echo '<nav class="db-periodo" aria-label="Período">';
		foreach ( Clinica_Analitica::PERIODOS_MEDICO as $k => $p ) {
			printf(
				'<a href="%1$s"%2$s>%3$s</a>',
				esc_url( add_query_arg( 'periodo', $k, $base ) . '#' . $vista ),
				$k === $periodo ? ' aria-current="true"' : '',
				esc_html( $p['label'] )
			);
		}
		echo '</nav>';
	}

	/** Valor de cartão: número grande e a moeda pequena ao lado (HTML já escapado). */
	public static function moeda_kpi( ?float $v ): string {
		if ( null === $v ) {
			return '—';
		}
		return esc_html( number_format( $v, 0, ',', ' ' ) ) . '<small>' . esc_html( Clinica_Grafico::moeda_codigo() ) . '</small>';
	}

	/** Estado vazio de um cartão: ícone, frase principal e explicação. */
	public static function vazio( string $titulo, string $texto, string $icone = 'calendario' ): void {
		$svg = array(
			'calendario' => '<rect x="3" y="4.5" width="18" height="16" rx="2.5"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/>',
			'carteira'   => '<path d="M3 7.5A2.5 2.5 0 0 1 5.5 5H18v3.5"/><path d="M3 7.5v10A2.5 2.5 0 0 0 5.5 20H20a1 1 0 0 0 1-1v-3"/><path d="M21 11.5h-4.5a2 2 0 0 0 0 4H21z"/>',
			'pessoas'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
			'mensagem'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		);
		printf(
			'<div class="db-vazio"><span class="db-vazio__icone" aria-hidden="true"><svg viewBox="0 0 24 24">%1$s</svg></span><p><b>%2$s</b>%3$s</p></div>',
			$svg[ $icone ] ?? ( self::ICONES[ $icone ] ?? $svg['calendario'] ),
			esc_html( $titulo ),
			esc_html( $texto )
		);
	}
}
