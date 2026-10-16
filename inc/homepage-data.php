<?php
/**
 * inc/homepage-data.php
 *
 * Fachada de dados da homepage. Catálogos, equipa e estatísticas são derivados
 * da base operacional; os templates em /pages/home recebem sempre o mesmo
 * formato, sem conhecer a origem de cada campo.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

final class Clinica_Homepage {

	/**
	 * Devolve o SVG de um ícone (traço) a partir do seu conteúdo interno.
	 * Centraliza os atributos comuns -> os dados só guardam os <path>.
	 */
	private static function icone( string $interior, int $tamanho = 28 ): string {
		return sprintf(
			'<svg width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">%2$s</svg>',
			$tamanho,
			$interior
		);
	}

	/** Iniciais a partir de um nome ("Dra. Ana Silva" -> "AS"). */
	public static function iniciais( string $nome ): string {
		$limpo  = trim( preg_replace( '/\b(Dr|Dra|Prof)\.?/i', '', $nome ) );
		$partes = preg_split( '/\s+/', $limpo, -1, PREG_SPLIT_NO_EMPTY );
		$ini    = '';
		foreach ( array( reset( $partes ), end( $partes ) ) as $p ) {
			$ini .= mb_substr( (string) $p, 0, 1 );
		}
		return mb_strtoupper( $ini );
	}

	/** Números de destaque do hero. */
	public static function estatisticas(): array {
		return array(
			array( 'numero' => '15+',    'rotulo' => 'Anos de experiência' ),
			array( 'numero' => '40+',    'rotulo' => 'Médicos especialistas' ),
			array( 'numero' => '25 mil', 'rotulo' => 'Pacientes por ano' ),
			array( 'numero' => '98%',    'rotulo' => 'Satisfação' ),
		);
	}

	/**
	 * Desenho anatómico em linha (48×48) para os cartões de especialidade.
	 * Traço mais fino e canvas maior que o self::icone() genérico, para o desenho
	 * ganhar detalhe (estilo "Department Category" das clínicas de referência).
	 */
	private static function anat( string $interior ): string {
		return sprintf(
			'<svg width="48" height="48" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">%s</svg>',
			$interior
		);
	}

	/** Especialidades apresentadas na grelha (nome + desenho anatómico). */
	public static function especialidades(): array {
		return array(
			array( 'nome' => 'Cardiologia',  'icone' => self::anat( '<path d="M24 40.5C12.8 32.8 6 26.3 6 18.2 6 12.7 10.2 8.8 15 8.8c3.6 0 7 2.1 9 5.6 2-3.5 5.4-5.6 9-5.6 4.8 0 9 3.9 9 9.4 0 8.1-6.8 14.6-18 22.3Z"></path><path d="M11 24h6.2l2.6-5 4.4 10 2.8-5H37"></path>' ) ),
			array( 'nome' => 'Dermatologia', 'icone' => self::anat( '<rect x="7.5" y="8" width="21" height="21" rx="6"></rect><circle cx="14.5" cy="15" r="1"></circle><circle cx="21.5" cy="18.5" r="1"></circle><circle cx="15.5" cy="22.5" r="1"></circle><circle cx="28.5" cy="27" r="7"></circle><path d="M33.5 32 39 37.5"></path>' ) ),
			array( 'nome' => 'Ortopedia',    'icone' => self::anat( '<path d="M18.5 29.5 29.5 18.5"></path><path d="M18.5 29.5c-.8-.8-2.1-.8-2.9 0-.5.5-.7 1.3-.5 2-.7-.2-1.5 0-2 .5-.8.8-.8 2.1 0 2.9.8.8 2.1.8 2.9 0 .5-.5.7-1.3.5-2 .7.2 1.5 0 2-.5.8-.8.8-2.1 0-2.9Z"></path><path d="M29.5 18.5c.8.8 2.1.8 2.9 0 .5-.5.7-1.3.5-2 .7.2 1.5 0 2-.5.8-.8.8-2.1 0-2.9-.8-.8-2.1-.8-2.9 0-.5.5-.7 1.3-.5 2-.7-.2-1.5 0-2 .5-.8.8-.8 2.1 0 2.9Z"></path>' ) ),
			array( 'nome' => 'Pediatria',    'icone' => self::anat( '<circle cx="24" cy="26.5" r="10.5"></circle><circle cx="14.5" cy="16" r="4.3"></circle><circle cx="33.5" cy="16" r="4.3"></circle><circle cx="20.3" cy="24.5" r="1.2"></circle><circle cx="27.7" cy="24.5" r="1.2"></circle><circle cx="24" cy="28" r="2"></circle><path d="M21.4 31.2c1.6 1.2 3.6 1.2 5.2 0"></path>' ) ),
			array( 'nome' => 'Neurologia',   'icone' => self::anat( '<path d="M24 12c-1.7-1.9-4.4-2.3-6.6-.9-1 .6-1.6 1.6-1.9 2.6-2.4.3-4.3 2.4-4.3 4.9 0 .5.1 1 .2 1.4-1.7.9-2.8 2.6-2.8 4.6 0 1.6.7 3 1.9 4-.2.5-.3 1.1-.3 1.7 0 2.7 2.2 4.9 4.9 4.9.6 0 1.3-.1 1.8-.4 1 1.5 2.7 2.5 4.7 2.5"></path><path d="M24 12c1.7-1.9 4.4-2.3 6.6-.9 1 .6 1.6 1.6 1.9 2.6 2.4.3 4.3 2.4 4.3 4.9 0 .5-.1 1-.2 1.4 1.7.9 2.8 2.6 2.8 4.6 0 1.6-.7 3-1.9 4 .2.5.3 1.1.3 1.7 0 2.7-2.2 4.9-4.9 4.9-.6 0-1.3-.1-1.8-.4-1 1.5-2.7 2.5-4.7 2.5"></path><path d="M24 12.5v25"></path><path d="M18.5 19.5c1.3.7 1.9 1.9 1.9 3.3M29.5 19.5c-1.3.7-1.9 1.9-1.9 3.3M18 28c1.5.4 2.4 1.4 2.7 2.8M30 28c-1.5.4-2.4 1.4-2.7 2.8"></path>' ) ),
			array( 'nome' => 'Oftalmologia', 'icone' => self::anat( '<path d="M5 24s7.6-11 19-11 19 11 19 11-7.6 11-19 11S5 24 5 24Z"></path><circle cx="24" cy="24" r="6.2"></circle><circle cx="24" cy="24" r="2"></circle>' ) ),
		);
	}

	/** Serviços de saúde (secção teal), cada um com o seu ícone. */
	public static function servicos(): array {
		return array(
			array( 'titulo' => 'Consultas de especialidade', 'texto' => 'Escolha entre as áreas e os profissionais ativos, com disponibilidade ligada diretamente à agenda clínica.', 'icone' => self::icone( '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>' ) ),
			array( 'titulo' => 'Marcação organizada', 'texto' => 'Especialidade, médico, data e horário ficam reunidos num percurso simples, sem duplicar informação.', 'icone' => self::icone( '<rect x="3" y="5" width="18" height="16" rx="2"></rect><path d="M16 3v4M8 3v4M3 10h18"></path><path d="m9 16 2 2 4-4"></path>' ) ),
			array( 'titulo' => 'Acompanhamento clínico', 'texto' => 'Consultas, notas e próximos passos permanecem associados ao processo do paciente para dar continuidade ao cuidado.', 'icone' => self::icone( '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6M8 13h8M8 17h6"></path>' ) ),
			array( 'titulo' => 'Acesso protegido', 'texto' => 'Pacientes e profissionais entram em áreas próprias, com permissões separadas para cada responsabilidade.', 'icone' => self::icone( '<rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>' ) ),
		);
	}

	/**
	 * Equipa médica completa (página Médicos) — retrato + perfil detalhado.
	 * 'formacao' e 'idiomas' são listas. Os retratos vivem em
	 * assets/img/medicos/equipa/ (3:4, fundo creme como na referência).
	 */
	public static function equipa(): array {
		$img = CLINICA_URI . '/assets/img/medicos/equipa/';
		return array(
			array(
				'nome'          => 'Dra. Ana Silva',
				'especialidade' => 'Cardiologia',
				'cargo'         => 'Diretora Clínica',
				'foto'          => $img . 'ana-silva.webp',
				'descricao'     => 'Dedica-se à cardiologia de intervenção e à prevenção do risco cardiovascular, com uma prática centrada na relação de confiança com o doente.',
				'experiencia'   => '20 anos',
				'formacao'      => array( 'Faculdade de Medicina, Universidade Eduardo Mondlane' ),
				'idiomas'       => array( 'Português', 'Inglês', 'Francês' ),
				'focos'         => array( 'Cardiologia de intervenção', 'Ecocardiografia', 'Hipertensão' ),
			),
			array(
				'nome'          => 'Dr. Miguel Costa',
				'especialidade' => 'Ortopedia',
				'cargo'         => 'Ortopedista Sénior',
				'foto'          => $img . 'miguel-costa.webp',
				'descricao'     => 'Especialista em medicina desportiva e cirurgia do joelho, com foco na recuperação funcional e no regresso seguro à atividade.',
				'experiencia'   => '15 anos',
				'formacao'      => array( 'ISCTEM', 'Fellowship em Cirurgia do Joelho (Alemanha)' ),
				'idiomas'       => array( 'Português', 'Inglês' ),
				'focos'         => array( 'Cirurgia do joelho', 'Medicina desportiva', 'Traumatologia' ),
			),
			array(
				'nome'          => 'Dra. Beatriz Rocha',
				'especialidade' => 'Pediatria',
				'cargo'         => 'Pediatra',
				'foto'          => $img . 'beatriz-rocha.webp',
				'descricao'     => 'Acompanha o desenvolvimento infantil do nascimento à adolescência, com uma abordagem tranquila e próxima das famílias.',
				'experiencia'   => '12 anos',
				'formacao'      => array( 'Universidade Católica de Moçambique', 'Hospital Central de Maputo' ),
				'idiomas'       => array( 'Português', 'Espanhol' ),
				'focos'         => array( 'Desenvolvimento infantil', 'Vacinação', 'Nutrição pediátrica' ),
			),
			array(
				'nome'          => 'Dr. João Ferreira',
				'especialidade' => 'Neurologia',
				'cargo'         => 'Neurologista',
				'foto'          => $img . 'joao-ferreira.webp',
				'descricao'     => 'Investigador e clínico em doenças neurodegenerativas, dedicado ao diagnóstico precoce e a planos de tratamento personalizados.',
				'experiencia'   => '18 anos',
				'formacao'      => array( 'Atlanta University', 'Doutoramento em Neurociências' ),
				'idiomas'       => array( 'Português', 'Inglês', 'Alemão' ),
				'focos'         => array( 'Doenças neurodegenerativas', 'Cefaleias', 'Epilepsia' ),
			),
			array(
				'nome'          => 'Dr. Roberto Dias',
				'especialidade' => 'Medicina Interna',
				'cargo'         => 'Internista',
				'foto'          => $img . 'roberto-dias.webp',
				'descricao'     => 'Coordena o acompanhamento de doentes crónicos e complexos, articulando as várias especialidades num plano de cuidados único.',
				'experiencia'   => '22 anos',
				'formacao'      => array( 'San Diego University, Califórnia', 'Especialização em Medicina Interna' ),
				'idiomas'       => array( 'Português', 'Inglês' ),
				'focos'         => array( 'Doença crónica', 'Check-up completo', 'Diabetes' ),
			),
			array(
				'nome'          => 'Dr. Carlos Nunes',
				'especialidade' => 'Medicina Geral e Familiar',
				'cargo'         => 'Médico de Família',
				'foto'          => $img . 'carlos-nunes.webp',
				'descricao'     => 'O primeiro ponto de contacto para toda a família, do rastreio de rotina ao encaminhamento para o especialista indicado.',
				'experiencia'   => '10 anos',
				'formacao'      => array( 'Universidade Pedagógica, Maputo' ),
				'idiomas'       => array( 'Português', 'Inglês' ),
				'focos'         => array( 'Saúde familiar', 'Rastreios', 'Gestão de crónicos' ),
			),
		);
	}

	/** Testemunhos de pacientes. */
	public static function testemunhos(): array {
		return array(
			array( 'nome' => 'Amélia Tembe',   'detalhe' => 'Paciente de Cardiologia',      'texto' => 'Fui atendida no próprio dia e a Dra. Ana Silva explicou-me tudo com uma calma e clareza que nunca tinha encontrado. Recomendo sem hesitar.' ),
			array( 'nome' => 'Jorge Cossa',    'detalhe' => 'Paciente de Ortopedia',        'texto' => 'Depois de meses com dores no joelho, em duas consultas tinha diagnóstico, plano de tratamento e fisioterapia marcada. Profissionalismo total.' ),
			array( 'nome' => 'Lúcia Mabjaia',  'detalhe' => 'Mãe de paciente de Pediatria', 'texto' => 'A equipa de pediatria é extraordinária com as crianças. O meu filho até gosta de ir às consultas, e isso diz tudo.' ),
		);
	}

	/**
	 * Perguntas frequentes da homepage. Cada resposta descreve o que o sistema
	 * faz de facto (marcação, SMS em inc/lembretes.php, edição em /conta/,
	 * métodos manuais da receção em Clinica_Pagamentos). O M-Pesa fica de fora
	 * enquanto o gateway estiver inativo.
	 */
	public static function faq(): array {
		$tel = implode( ' ou ', self::telefones() );
		$c   = self::contacto();
		return array(
			array( 'p' => 'Como marco uma consulta?',            'r' => 'Online, em poucos minutos: escolha a especialidade, o médico e o horário em «Marcar consulta». Se preferir, ligue para ' . $tel . '.' ),
			array( 'p' => 'Posso escolher o médico?',             'r' => 'Sim. Na marcação escolhe primeiro a especialidade e depois o médico que prefere, de entre os que têm horário disponível.' ),
			array( 'p' => 'Vou receber algum lembrete?',          'r' => 'Sim. Enviamos um SMS um dia antes e outro uma hora antes da consulta, para o número que indicar.' ),
			array( 'p' => 'Posso alterar ou cancelar a consulta?', 'r' => 'Pode. Em «A minha conta» encontra as suas consultas e pode editá-las ou cancelá-las.' ),
			array( 'p' => 'Qual é o horário de atendimento?',     'r' => $c['horario'] . ', em ' . $c['morada'][0] . ', ' . $c['morada'][1] . '.' ),
			array( 'p' => 'Que formas de pagamento aceitam?',     'r' => 'Na receção aceitamos numerário, cartão e e-Mola.' ),
		);
	}

	/**
	 * Perguntas frequentes da página Médicos (mesma regra: só o que o sistema
	 * faz — a escolha de médico e os horários livres vêm do /marcar/).
	 */
	public static function faq_medicos(): array {
		$tel = implode( ' ou ', self::telefones() );
		$faq = self::faq();
		return array(
			$faq[1], // Posso escolher o médico?
			array( 'p' => 'Como sei que médico é o indicado para mim?', 'r' => 'Abra o perfil de cada médico para ver a formação, as línguas que fala e as áreas a que se dedica. Se continuar com dúvidas, ligue para ' . $tel . ' e a receção ajuda a escolher.' ),
			array( 'p' => 'E se o médico que quero não tiver horário?', 'r' => 'Na marcação aparecem só os horários livres. Escolha outra data ou outro médico da mesma especialidade, ou ligue-nos e procuramos uma alternativa consigo.' ),
			array( 'p' => 'Posso voltar a ser visto pelo mesmo médico?', 'r' => 'Sim. Numa nova marcação escolha a mesma especialidade e o mesmo médico. As suas consultas anteriores ficam guardadas em «A minha conta».' ),
			$faq[3], // Posso alterar ou cancelar a consulta?
		);
	}

	/**
	 * Perguntas frequentes da página Especialidades. "Marcação por contacto"
	 * é o que a vitrine mostra quando a especialidade não tem médicos na BD.
	 */
	public static function faq_especialidades(): array {
		$tel = implode( ' ou ', self::telefones() );
		$faq = self::faq();
		return array(
			array( 'p' => 'Não sei que especialidade escolher. E agora?', 'r' => 'Ligue para ' . $tel . ' ou escreva-nos pela página de contacto: a receção ouve o que sente e indica-lhe a especialidade certa.' ),
			array( 'p' => 'O que quer dizer «Marcação por contacto»?', 'r' => 'Essa especialidade ainda não tem horários para marcar online. Fale connosco por telefone ou pela página de contacto e marcamos consigo.' ),
			$faq[1], // Posso escolher o médico?
			$faq[2], // Vou receber algum lembrete?
			$faq[3], // Posso alterar ou cancelar a consulta?
		);
	}

	/**
	 * Fonte ÚNICA dos dados de contacto da clínica.
	 * Tanto a secção de contacto como o rodapé leem daqui -> muda-se num só sítio.
	 */
	public static function contacto(): array {
		return array(
			'morada'   => array( 'Av. Julius Nyerere 780', 'Sommerschield, Maputo' ),
			'telefone' => '+258 849596862 / +258 879596862',
			'email'    => 'geral@clinicavida.co.mz',
			'horario'  => 'Seg a Sáb, 08h00 às 20h00',
		);
	}

	/**
	 * Telefones individuais. O campo 'telefone' junta-os com "/", e tirar só os
	 * não-dígitos dava um link tel: com os DOIS números colados (não ligava).
	 */
	public static function telefones(): array {
		return array_values( array_filter( array_map( 'trim', explode( '/', self::contacto()['telefone'] ) ) ) );
	}

	/** "+258 849596862" → "tel:+258849596862". */
	public static function tel_href( string $numero ): string {
		return 'tel:' . preg_replace( '/[^+\d]/', '', $numero );
	}

	/** Blocos de contacto (com ícone) para a secção — derivados de contacto(). */
	public static function contactos(): array {
		$c = self::contacto();
		return array(
			array( 'rotulo' => 'Telefone', 'valor' => $c['telefone'], 'icone' => self::icone( '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"></path>', 19 ) ),
			array( 'rotulo' => 'Email',    'valor' => $c['email'],    'icone' => self::icone( '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline>', 19 ) ),
			array( 'rotulo' => 'Horário',  'valor' => $c['horario'],  'icone' => self::icone( '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>', 19 ) ),
		);
	}

	/**
	 * Redes sociais do rodapé, pela ordem WhatsApp, Facebook, Instagram, TikTok. Os
	 * endereços vêm das Definições (Gestão); sem endereço, a rede não aparece.
	 * O WhatsApp aceita número ou link e, vazio, usa o primeiro telefone.
	 * 'rede' vira a classe --<rede> (cor da marca no CSS).
	 */
	public static function redes_sociais(): array {
		$whatsapp = trim( (string) Clinica_Config::get( 'rede_whatsapp', '' ) );
		if ( '' === $whatsapp ) {
			$whatsapp = self::telefones()[0] ?? '';
		}
		$redes = array(
			array( 'rede' => 'whatsapp', 'label' => 'WhatsApp', 'url' => self::whatsapp_url( $whatsapp ),
				'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>' ),
			array( 'rede' => 'facebook', 'label' => 'Facebook', 'url' => esc_url_raw( (string) Clinica_Config::get( 'rede_facebook', '' ) ),
				'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>' ),
			array( 'rede' => 'instagram', 'label' => 'Instagram', 'url' => esc_url_raw( (string) Clinica_Config::get( 'rede_instagram', '' ) ),
				'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>' ),
			array( 'rede' => 'tiktok', 'label' => 'TikTok', 'url' => esc_url_raw( (string) Clinica_Config::get( 'rede_tiktok', '' ) ),
				'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>' ),
		);
		return array_values( array_filter( $redes, static fn( array $r ): bool => '' !== $r['url'] ) );
	}

	/** "+258 84 959 6862" ou um link → https://wa.me/258849596862. */
	private static function whatsapp_url( string $valor ): string {
		if ( preg_match( '#^https?://#i', $valor ) ) {
			return esc_url_raw( $valor );
		}
		$digitos = (string) preg_replace( '/\D/', '', $valor );
		if ( 9 === strlen( $digitos ) ) {
			$digitos = '258' . $digitos;
		}
		return '' === $digitos ? '' : 'https://wa.me/' . $digitos;
	}
}
