<?php
/**
 * Bootstrap do tema Clinica.
 * Define constantes de caminho/versao e carrega os modulos em /inc.
 *
 * @package Clinica
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

// Constantes usadas por inc/enqueue.php e restantes modulos.
define( 'CLINICA_DIR', get_template_directory() );
define( 'CLINICA_URI', get_template_directory_uri() );


// Versao lida do cabecalho do style.css (fallback fixo se nao existir).
$clinica_theme = wp_get_theme();
define( 'CLINICA_VERSION', $clinica_theme->get( 'Version' ) ?: '0.1.0' );

/**
 * Módulos carregados por camadas (ordem importa: base -> dados -> segurança
 * -> domínio/controladores -> apresentação).
 */
$clinica_modulos = array(
	// 1) Base / tema
	'inc/setup.php',
	'inc/input.php',            // helper de validação/sanitização (usado por todos)

	// 2) Fundação (Fase 1): RBAC, configurações em BD e processo clínico
	'inc/roles.php',
	'inc/config.php',
	'inc/auditoria.php',
	'inc/processo.php',
	'inc/pdf.php',                  // documentos clínicos em PDF gerado no servidor
	'inc/clinico.php',              // ato clínico: nota SOAP + diagnóstico (CID-10)
	'inc/agenda.php',

	// 3) Segurança
	'inc/security.php',
	'inc/login-security.php',

	// 3.5) Integrações externas (base partilhada -> canais concretos).
	// Carregam antes do domínio: os pagamentos dependem do gateway M-Pesa.
	'inc/integracoes.php',          // credenciais + telefone + resposta + cliente HTTP
	'inc/sms.php',                  // contratos + serviço
	'inc/sms-log.php',              // histórico e estados de entrega
	'inc/sms-api.php',              // canais (API privada / modo de registo)
	'inc/sms-mensagens.php',        // textos da clínica
	'inc/sms-callback.php',         // endpoint de entrega (REST)

	// 4) Domínio / dados / controladores
	'functions-clinic-snippet.php', // repo + ui (PRG) + CSS do portal
	'inc/especialidades.php',
	'inc/auth.php',                 // login + registo (RBAC)
	'inc/conta.php',                // área do utente
	'inc/gestao.php',               // painel de administração/gestão
	'inc/pagamentos.php',           // financeiro: contrato + estados
	'inc/analitica.php',            // camada analítica (tático/executivo) — §4: cache por cadência
	'inc/direcao.php',              // nível executivo: metas + aprovações estratégicas
	'inc/xlsx.php',                 // gerador de Excel real (.xlsx sem bibliotecas)
	'inc/relatorios.php',           // relatórios executivos + exportação PDF/CSV
	// 'inc/mpesa.php' NÃO é carregado: é a experiência standalone do Clesio
	// (corre-se no browser). Assim, editá-lo/parti-lo nunca derruba o site.
	// O ficheiro inc/mpesa-gateway.php está reservado a testes manuais e não
	// pode ser incluído no bootstrap: executa um pedido externo ao ser carregado.
	// Clinica_Pagamentos usa o gateway inativo seguro quando a classe não existe.
	'inc/rest-api.php',             // API REST clinica/v1 (n8n, Thunder Client)
	'inc/webhooks.php',             // webhooks de saída (eventos -> n8n -> WhatsApp)
	'inc/contacto-site.php',        // formulário de contacto (módulo WordPress)
	'inc/recepcao.php',             // portal da secretária/receção
	'inc/marcacao-rapida.php',      // marcação sem conta no site (nome + telefone)
	'inc/legal.php',                // termos, privacidade, cookies + aviso de cookies
	'inc/lembretes.php',            // cron do aviso 1h antes (usa repo + sms)

	// 5) Apresentação
	'inc/homepage-data.php',
	'inc/graficos.php',             // gráficos dos portais (.gf-*), desenhados no servidor
	'inc/loader.php',               // ecrã de carregamento (batimento) em todas as páginas
	'inc/enqueue.php',
);
foreach ( $clinica_modulos as $modulo ) {
	require_once CLINICA_DIR . '/' . $modulo;
}

// Esconde a barra de administracao do WordPress no frontend.
add_filter( 'show_admin_bar', '__return_false' );
