<?php
/**
 * inc/legal.php
 *
 * Páginas legais do site público (Termos, Privacidade, Cookies) e o aviso de
 * cookies. As três páginas criam-se sozinhas com o template page-legal.php;
 * o texto vive aqui e descreve o que o sistema faz de facto (dados recolhidos,
 * cookies, prestadores). Contactos e prazos vêm de Clinica_Homepage e da
 * configuração, para nunca ficarem desatualizados.
 *
 * @package Clinica
 */

declare( strict_types = 1 );
defined( 'ABSPATH' ) || exit;

final class Clinica_Legal {

	public const TEMPLATE = 'page-legal.php';

	/** Cookie que guarda a escolha do aviso: 'aceite' ou 'recusado'. */
	public const COOKIE = 'clinica_cookies';

	/** Data da última revisão dos textos (Y-m-d). */
	private const REVISTO = '2026-10-16';

	private const PAGINAS = array(
		'termos'      => 'Termos de utilização',
		'privacidade' => 'Política de privacidade',
		'cookies'     => 'Política de cookies',
	);

	public static function init(): void {
		add_action( 'after_setup_theme', array( self::class, 'instalar' ) );
	}

	/** Cria as páginas (uma vez) e garante o template. */
	public static function instalar(): void {
		if ( '1' === get_option( 'clinica_paginas_legais' ) ) {
			return;
		}
		foreach ( self::PAGINAS as $slug => $titulo ) {
			$pagina = get_page_by_path( $slug );
			$id     = $pagina ? $pagina->ID : wp_insert_post(
				array(
					'post_title'  => $titulo,
					'post_name'   => $slug,
					'post_status' => 'publish',
					'post_type'   => 'page',
				)
			);
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( (int) $id, '_wp_page_template', self::TEMPLATE );
			}
		}
		update_option( 'clinica_paginas_legais', '1' );
	}

	public static function url( string $slug ): string {
		return home_url( '/' . $slug . '/' );
	}

	/** Ligações do rodapé: slug => título curto. */
	public static function ligacoes(): array {
		return array(
			self::url( 'termos' )      => 'Termos',
			self::url( 'privacidade' ) => 'Privacidade',
			self::url( 'cookies' )     => 'Cookies',
		);
	}

	/** O aviso de cookies só aparece a quem ainda não escolheu. */
	public static function falta_escolha(): bool {
		$escolha = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return ! in_array( $escolha, array( 'aceite', 'recusado' ), true );
	}

	/** HTML de uma secção: o de post, mais o tabindex da tabela que desliza. */
	public static function html( string $html ): string {
		$permitido                    = wp_kses_allowed_html( 'post' );
		$permitido['div']['tabindex'] = true;
		return wp_kses( $html, $permitido );
	}

	public static function revisto(): string {
		return mb_strtolower( date_i18n( 'j \d\e F \d\e Y', (int) strtotime( self::REVISTO ) ) );
	}

	/**
	 * Documento de uma página: título, resumo e secções (id, título, HTML).
	 *
	 * @return array{titulo:string,resumo:string,seccoes:array<int,array{id:string,titulo:string,html:string}>}|null
	 */
	public static function documento( string $slug ): ?array {
		$c     = Clinica_Homepage::contacto();
		$dados = array(
			'email'    => esc_html( $c['email'] ),
			'mailto'   => esc_attr( 'mailto:' . $c['email'] ),
			'telefone' => esc_html( $c['telefone'] ),
			'morada'   => esc_html( implode( ', ', $c['morada'] ) ),
			'cancelar' => (string) max( 0, Clinica_Config::int( 'cancelamento_horas', 24 ) ),
			'termos'   => esc_url( self::url( 'termos' ) ),
			'priv'     => esc_url( self::url( 'privacidade' ) ),
			'cookies'  => esc_url( self::url( 'cookies' ) ),
		);

		$docs = array(
			'termos'      => self::termos( $dados ),
			'privacidade' => self::privacidade( $dados ),
			'cookies'     => self::cookies( $dados ),
		);
		return $docs[ $slug ] ?? null;
	}

	/** Substitui {chave} pelos dados (já escapados). */
	private static function preencher( string $html, array $dados ): string {
		return preg_replace_callback(
			'/\{(\w+)\}/',
			static fn( array $m ): string => $dados[ $m[1] ] ?? $m[0],
			$html
		);
	}

	private static function montar( string $titulo, string $resumo, array $seccoes, array $dados ): array {
		$saida = array();
		foreach ( $seccoes as $id => $s ) {
			$saida[] = array(
				'id'     => $id,
				'titulo' => $s[0],
				'html'   => self::preencher( $s[1], $dados ),
			);
		}
		return array( 'titulo' => $titulo, 'resumo' => $resumo, 'seccoes' => $saida );
	}

	private static function termos( array $d ): array {
		return self::montar(
			'Termos de utilização',
			'As regras para usar este site e marcar consultas na Clínica Vida.',
			array(
				'ambito'       => array(
					'Âmbito',
					'<p>Estes termos aplicam-se ao site da Clínica Vida e às marcações feitas através dele. Ao usar o site, aceita estas condições. O tratamento dos seus dados pessoais está descrito na <a href="{priv}">Política de privacidade</a>.</p>',
				),
				'saude'        => array(
					'Informação de saúde',
					'<p>A informação deste site é geral e não substitui uma consulta médica, um diagnóstico ou um tratamento.</p><p>Em caso de urgência, não marque pelo site: dirija-se ao serviço de urgência mais próximo ou ligue para a clínica.</p>',
				),
				'marcacoes'    => array(
					'Marcações',
					'<ul><li>Pode marcar sem conta (só com nome e telefone) ou com a sua conta.</li><li>A marcação fica pendente até a receção a confirmar, por telefone ou SMS. O horário só fica garantido depois dessa confirmação.</li><li>Os dados que indica têm de ser verdadeiros. Para evitar abusos, há um limite de marcações por confirmar por número de telefone e de pedidos por hora.</li><li>Os horários dos médicos podem mudar. Se for preciso alterar a sua consulta, a clínica contacta-o.</li></ul>',
				),
				'cancelar'     => array(
					'Alterar ou cancelar',
					'<p>Pode alterar ou cancelar uma consulta até {cancelar} horas antes, na sua conta ou pelo telefone {telefone}. Se não puder comparecer, avise: a vaga fica livre para outro paciente.</p>',
				),
				'pagamentos'   => array(
					'Pagamentos',
					'<p>O valor da consulta é informado na marcação ou na receção. Aceitamos M-Pesa, e-Mola, cartão e numerário. Cada pagamento tem recibo.</p><p>Os dados do cartão são lidos no terminal de pagamento e não ficam guardados no nosso sistema.</p>',
				),
				'conta'        => array(
					'A sua conta',
					'<p>A conta é pessoal. Guarde a palavra-passe em segurança e avise-nos se suspeitar de um acesso indevido. As áreas reservadas à equipa da clínica só podem ser usadas por quem tem autorização.</p>',
				),
				'uso'          => array(
					'Uso aceitável',
					'<p>Não é permitido usar o site para fins ilícitos, fazer marcações falsas, enviar mensagens abusivas ou tentar aceder a dados e áreas sem autorização.</p>',
				),
				'propriedade'  => array(
					'Propriedade intelectual',
					'<p>Os textos, a marca e o desenho do site pertencem à Clínica Vida. Algumas fotografias são de <a href="https://www.freepik.com" target="_blank" rel="noopener">Freepik</a> e são usadas ao abrigo da respetiva licença.</p>',
				),
				'lei'          => array(
					'Lei aplicável',
					'<p>Estes termos regem-se pela lei da República de Moçambique. Para qualquer litígio é competente o foro da cidade de Maputo.</p>',
				),
				'alteracoes'   => array(
					'Alterações e contacto',
					'<p>Podemos atualizar estes termos; a data da última revisão está no topo da página. Dúvidas: <a href="{mailto}">{email}</a> ou {telefone}.</p>',
				),
			),
			$d
		);
	}

	private static function privacidade( array $d ): array {
		return self::montar(
			'Política de privacidade',
			'Que dados pessoais recolhemos, para quê, com quem os partilhamos e como pode exercer os seus direitos.',
			array(
				'responsavel' => array(
					'Quem trata os seus dados',
					'<p>A Clínica Vida, {morada}. Contacto para assuntos de privacidade: <a href="{mailto}">{email}</a> ou {telefone}.</p>',
				),
				'dados'       => array(
					'Que dados recolhemos',
					'<ul><li><b>Marcação sem conta:</b> nome, telefone e a consulta escolhida (especialidade, médico, dia e hora).</li><li><b>Conta:</b> nome, email, telefone e palavra-passe, guardada cifrada e nunca em texto.</li><li><b>Mensagens:</b> o nome, o contacto e o texto que nos envia pelo formulário.</li><li><b>Na clínica:</b> os dados de identificação e de saúde registados pela equipa: notas da consulta, diagnósticos, prescrições, exames e documentos.</li><li><b>Pagamentos:</b> valor, método e, no M-Pesa, o número e a referência da transação.</li><li><b>Segurança:</b> o endereço IP, guardado cifrado e por pouco tempo, para travar abusos nas marcações e nas entradas, e o registo das ações da equipa.</li></ul>',
				),
				'finalidades' => array(
					'Para que os usamos',
					'<ul><li>Marcar e confirmar consultas e enviar lembretes por SMS (um dia e uma hora antes).</li><li>Prestar cuidados de saúde e manter o seu processo clínico.</li><li>Registar pagamentos e emitir recibos.</li><li>Responder às suas mensagens.</li><li>Proteger o site e os dados contra acessos indevidos.</li></ul><p>Não vendemos dados pessoais nem os usamos para publicidade.</p>',
				),
				'saude'       => array(
					'Dados de saúde e sigilo',
					'<p>O processo clínico só é visto pelos médicos que o acompanham, que estão sujeitos a sigilo profissional. A receção vê apenas os dados administrativos necessários para marcar, receber e cobrar.</p>',
				),
				'partilha'    => array(
					'Com quem partilhamos',
					'<ul><li>O prestador de SMS que envia confirmações e lembretes.</li><li>Os operadores de pagamento móvel (M-Pesa, e-Mola), quando escolhe esse método.</li><li>Um serviço de automação que envia avisos sobre as suas marcações, quando ativo.</li><li>O Google Fonts, que fornece os tipos de letra do site: o seu browser liga-se aos servidores da Google, que recebem o seu endereço IP.</li><li>As autoridades, quando a lei o exigir.</li></ul><p>Os prestadores só recebem o necessário para o serviço que fazem.</p>',
				),
				'prazos'      => array(
					'Durante quanto tempo',
					'<ul><li>Processo clínico: pelo prazo exigido pelas normas de saúde aplicáveis.</li><li>Marcações e pagamentos: o tempo necessário para as obrigações contabilísticas e fiscais.</li><li>Mensagens: enquanto forem precisas para lhe responder.</li><li>Endereço IP para travar abusos: até uma hora.</li></ul>',
				),
				'seguranca'   => array(
					'Segurança',
					'<p>Cada pessoa da equipa só acede ao que o seu papel exige, as palavras-passe são guardadas cifradas e as ações feitas no sistema ficam registadas.</p>',
				),
				'direitos'    => array(
					'Os seus direitos',
					'<p>Pode pedir acesso aos seus dados, a correção do que estiver errado, a eliminação do que a lei não obrigue a guardar (o processo clínico tem de ser conservado) e deixar de receber lembretes por SMS. Faça o pedido por <a href="{mailto}">{email}</a> ou na receção; podemos pedir que confirme a sua identidade.</p>',
				),
				'base'        => array(
					'Base legal',
					'<p>Tratamos os dados para cumprir a marcação e a prestação de cuidados que pede, para cumprir obrigações legais e, na segurança do site, no interesse legítimo da clínica, de acordo com a Constituição da República de Moçambique (artigo 71.º) e a Lei n.º 3/2017, de 9 de janeiro (Lei das Transações Eletrónicas).</p>',
				),
				'menores'     => array(
					'Menores',
					'<p>As marcações para menores são feitas pelos pais ou por quem tenha a sua guarda.</p>',
				),
				'alteracoes'  => array(
					'Alterações',
					'<p>Esta política pode ser atualizada; a data da última revisão está no topo da página. Sobre cookies, veja a <a href="{cookies}">Política de cookies</a>.</p>',
				),
			),
			$d
		);
	}

	private static function cookies( array $d ): array {
		$linhas = array(
			array( 'wordpress_logged_in_…', 'Mantém a sessão iniciada na sua conta.', 'Necessário', 'Sessão (14 dias com «Manter sessão»)' ),
			array( 'wordpress_sec_… ou wordpress_…', 'Protege o acesso às áreas reservadas.', 'Necessário', 'Sessão (14 dias com «Manter sessão»)' ),
			array( self::COOKIE, 'Guarda a escolha que fez neste aviso.', 'Necessário', '12 meses' ),
		);
		$tabela = '<div class="lg-tabela" role="region" aria-label="Cookies usados" tabindex="0"><table><thead><tr><th scope="col">Nome</th><th scope="col">Para que serve</th><th scope="col">Tipo</th><th scope="col">Duração</th></tr></thead><tbody>';
		foreach ( $linhas as $l ) {
			$tabela .= sprintf( '<tr><td data-rotulo="Nome"><code>%s</code></td><td data-rotulo="Para que serve">%s</td><td data-rotulo="Tipo">%s</td><td data-rotulo="Duração">%s</td></tr>', esc_html( $l[0] ), esc_html( $l[1] ), esc_html( $l[2] ), esc_html( $l[3] ) );
		}
		$tabela .= '</tbody></table></div>';

		return self::montar(
			'Política de cookies',
			'Que cookies o site usa e como pode escolher.',
			array(
				'oque'      => array(
					'O que são cookies',
					'<p>Cookies são pequenos ficheiros que o site guarda no seu browser para se lembrar de informações entre visitas, como a sessão iniciada.</p>',
				),
				'usamos'    => array(
					'Cookies que usamos',
					'<p>Usamos apenas cookies necessários ao funcionamento do site. Os de sessão só existem se entrar na sua conta.</p>' . $tabela . '<p>Nas áreas da equipa, o browser guarda também preferências de ecrã (por exemplo, o menu recolhido), sem dados pessoais.</p>',
				),
				'terceiros' => array(
					'Serviços de terceiros',
					'<p>Não usamos cookies de publicidade nem de estatística. Os tipos de letra vêm do Google Fonts, que não coloca cookies mas recebe o seu endereço IP, como explicado na <a href="{priv}">Política de privacidade</a>.</p>',
				),
				'escolher'  => array(
					'Como escolher',
					'<p>Na primeira visita mostramos um aviso para aceitar ou recusar. Se recusar, o site guarda só a sua escolha e continua a funcionar. Pode mudar de ideias em «Preferências de cookies», no rodapé, ou apagar os cookies nas definições do browser.</p>',
				),
				'contacto'  => array(
					'Contacto',
					'<p>Dúvidas sobre cookies: <a href="{mailto}">{email}</a>.</p>',
				),
			),
			$d
		);
	}
}

Clinica_Legal::init();
