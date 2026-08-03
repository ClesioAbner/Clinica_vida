<?php
/**
 * inc/homepage-data.php
 *
 * Modelo de conteúdo da homepage — a "camada de dados" da página pública.
 * Os templates em /pages/home só percorrem estes arrays; não têm texto fixo.
 * Assim o conteúdo (estatísticas, especialidades, equipa, testemunhos, contactos)
 * edita-se num só sítio, sem mexer no HTML.
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

	/** Especialidades apresentadas na grelha (nome + ícone). */
	public static function especialidades(): array {
		return array(
			array( 'nome' => 'Cardiologia',   'icone' => self::icone( '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>' ) ),
			array( 'nome' => 'Dermatologia',  'icone' => self::icone( '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="4"></circle>' ) ),
			array( 'nome' => 'Ortopedia',     'icone' => self::icone( '<path d="M12 2v4"></path><path d="M12 18v4"></path><circle cx="12" cy="8" r="3"></circle><circle cx="12" cy="16" r="3"></circle>' ) ),
			array( 'nome' => 'Pediatria',     'icone' => self::icone( '<path d="M9 12h6"></path><path d="M12 9v6"></path><circle cx="12" cy="12" r="10"></circle>' ) ),
			array( 'nome' => 'Neurologia',    'icone' => self::icone( '<path d="M12 2a7 7 0 0 1 7 7c0 2.5-1.5 4-2.5 5.5S15 18 15 20h-6c0-2-.5-4-1.5-5.5S5 11.5 5 9a7 7 0 0 1 7-7z"></path><path d="M9 22h6"></path>' ) ),
			array( 'nome' => 'Oftalmologia',  'icone' => self::icone( '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>' ) ),
		);
	}

	/** Serviços de saúde (secção teal), cada um com o seu ícone. */
	public static function servicos(): array {
		return array(
			array( 'titulo' => 'Serviço de Urgência',       'texto' => 'Atendimento imediato 24 horas para situações críticas, acidentes e emergências, com equipa preparada para trauma.', 'icone' => self::icone( '<path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>' ) ),
			array( 'titulo' => 'Radiologia e Imagiologia',  'texto' => 'Diagnóstico por imagem avançado raio-X, TAC, ressonância e ecografia  para diagnósticos precisos e rápidos.',   'icone' => self::icone( '<rect x="3" y="3" width="18" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line>' ) ),
			array( 'titulo' => 'Análises Clínicas',         'texto' => 'Laboratório completo para análises de sangue, urina e outras amostras, com resultados fiáveis e atempados.',        'icone' => self::icone( '<path d="M9 3h6"></path><path d="M10 3v6l-5 9a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-9V3"></path>' ) ),
			array( 'titulo' => 'Farmácia',                  'texto' => 'Farmácia interna com medicação prescrita e produtos de saúde, para acesso cómodo e rápido a tratamentos.',           'icone' => self::icone( '<path d="M10.5 20.5 3.5 13.5a5 5 0 0 1 7-7l7 7a5 5 0 0 1-7 7z"></path><line x1="8.5" y1="8.5" x2="15.5" y2="15.5"></line>' ) ),
		);
	}

	/**
	 * Equipa médica completa (página Médicos) — retrato + perfil detalhado.
	 * As fotos vivem em assets/img; troca o ficheiro para mudar o retrato.
	 */
	public static function equipa(): array {
		$img = CLINICA_URI . '/assets/img/';
		return array(
			array(
				'nome'          => 'Dra. Ana Silva',
				'especialidade' => 'Cardiologia',
				'cargo'         => 'Diretora Clínica',
				'foto'          => $img . 'Medica1.png',
				'descricao'     => 'Dedica-se à cardiologia de intervenção e à prevenção do risco cardiovascular, com uma prática centrada na relação de confiança com o doente.',
				'experiencia'   => '20 anos',
				'formacao'      => 'Faculdade de Medicina  Universidade Eduardo Mondlane',
				'idiomas'       => 'Português  Inglês  Francês',
				'focos'         => array( 'Cardiologia de intervenção', 'Ecocardiografia', 'Hipertensão' ),
			),
			array(
				'nome'          => 'Dr. Miguel Costa',
				'especialidade' => 'Ortopedia',
				'cargo'         => 'Ortopedista Sénior',
				'foto'          => $img . 'medico4.png',
				'descricao'     => 'Especialista em medicina desportiva e cirurgia do joelho, com foco na recuperação funcional e no regresso seguro à atividade.',
				'experiencia'   => '15 anos',
				'formacao'      => ' ISCTEM ,  Fellowship em Cirurgia do Joelho (Alemanha)',
				'idiomas'       => 'Português  Inglês',
				'focos'         => array( 'Cirurgia do joelho', 'Medicina desportiva', 'Traumatologia' ),
			),
			array(
				'nome'          => 'Dra. Beatriz Rocha',
				'especialidade' => 'Pediatria',
				'cargo'         => 'Pediatra',
				'foto'          => $img . 'medico5.png',
				'descricao'     => 'Acompanha o desenvolvimento infantil do nascimento à adolescência, com uma abordagem tranquila e próxima das famílias.',
				'experiencia'   => '12 anos',
				'formacao'      => 'Universidade Católica de Moçambique,  Hospital Central de Maputo',
				'idiomas'       => 'Português  Espanhol',
				'focos'         => array( 'Desenvolvimento infantil', 'Vacinação', 'Nutrição pediátrica' ),
			),
			array(
				'nome'          => 'Dr. João Ferreira',
				'especialidade' => 'Neurologia',
				'cargo'         => 'Neurologista',
				'foto'          => $img . 'medico6.png',
				'descricao'     => 'Investigador e clínico em doenças neurodegenerativas, dedicado ao diagnóstico precoce e a planos de tratamento personalizados.',
				'experiencia'   => '18 anos',
				'formacao'      => ' Atlanta university,  Doutoramento em Neurociências',
				'idiomas'       => 'Português  Inglês  Alemão',
				'focos'         => array( 'Doenças neurodegenerativas', 'Cefaleias', 'Epilepsia' ),
			),
			array(
				'nome'          => 'Dr. Roberto Dias',
				'especialidade' => 'Medicina Interna',
				'cargo'         => 'Internista',
				'foto'          => $img . 'medico7.png',
				'descricao'     => 'Coordena o acompanhamento de doentes crónicos e complexos, articulando as várias especialidades num plano de cuidados único.',
				'experiencia'   => '22 anos',
				'formacao'      => ' San Diego University, California,  Especialização em Medicina Interna',
				'idiomas'       => 'Português  Inglês',
				'focos'         => array( 'Doença crónica', 'Check-up completo', 'Diabetes' ),
			),
			array(
				'nome'          => 'Dr. Carlos Nunes',
				'especialidade' => 'Medicina Geral e Familiar',
				'cargo'         => 'Médico de Família',
				'foto'          => $img . 'medico2.png',
				'descricao'     => 'O primeiro ponto de contacto para toda a família  do rastreio de rotina ao encaminhamento para o especialista indicado.',
				'experiencia'   => '10 anos',
				'formacao'      => 'Universidade Pedagógica, Maputo',
				'idiomas'       => 'Português  Inglês',
				'focos'         => array( 'Saúde familiar', 'Rastreios', 'Gestão de crónicos' ),
			),
		);
	}

	/** Testemunhos de pacientes. */
	public static function testemunhos(): array {
		return array(
			array( 'nome' => 'Amélia Tembe',   'detalhe' => 'Paciente de Cardiologia',      'texto' => 'Fui atendida no próprio dia e a Dra. Ana Silva explicou-me tudo com uma calma e clareza que nunca tinha encontrado. Recomendo sem hesitar.' ),
			array( 'nome' => 'Jorge Cossa',    'detalhe' => 'Paciente de Ortopedia',        'texto' => 'Depois de meses com dores no joelho, em duas consultas tinha diagnóstico, plano de tratamento e fisioterapia marcada. Profissionalismo total.' ),
			array( 'nome' => 'Lúcia Mabjaia',  'detalhe' => 'Mãe de paciente de Pediatria', 'texto' => 'A equipa de pediatria é extraordinária com as crianças. O meu filho até gosta de ir às consultas  isso diz tudo.' ),
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
			'horario'  => 'Seg-Sáb, 08h00-20h00',
		);
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
	 * Redes sociais do rodapé.
	 * 'rede' vira a classe --<rede> (define a cor da marca no CSS).
	 * Os SVG são marcas preenchidas (fill=currentColor).
	 */
	public static function redes_sociais(): array {
		return array(
			array( 'rede' => 'facebook',  'url' => '#', 'label' => 'Facebook',
				'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>' ),
			array( 'rede' => 'instagram', 'url' => '#', 'label' => 'Instagram',
				'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>' ),
			array( 'rede' => 'whatsapp',  'url' => '#', 'label' => 'WhatsApp',
				'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>' ),
			array( 'rede' => 'linkedin',  'url' => '#', 'label' => 'LinkedIn',
				'svg' => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.848 3.37-1.848 3.601 0 4.267 2.37 4.267 5.455v6.284zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>' ),
		);
	}
}
