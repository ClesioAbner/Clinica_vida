# Clínica Vida

Sistema de gestão da Clínica Vida (Sommerschield, Maputo): o site público onde os pacientes marcam consultas e os portais onde a equipa trabalha — receção, médicos, gestão e direção. É um tema WordPress usado como aplicação, com tabelas, papéis e regras próprias.

## O que faz

**Site público**

- Páginas de especialidades, médicos, sobre e contacto.
- Marcação de consulta com conta ou sem conta (só nome e telefone), com as horas livres reais de cada médico. A receção confirma por telefone ou SMS.
- Termos, Política de privacidade e Política de cookies, com aviso de cookies.

**Paciente** (`/conta/`): consultas marcadas, alterar ou cancelar dentro do prazo, respostas do médico e mensagens.

**Receção** (`/recepcao/`)

- Painel do dia, pedidos do site para confirmar, agenda e fila de atendimento (check-in).
- Nova consulta ao balcão em três passos, incluindo pessoas sem conta.
- Caixa: cobrança em M-Pesa, e-Mola, cartão ou numerário, com cálculo do troco e recibo.
- Ficha administrativa e histórico dos pacientes, mensagens do formulário de contacto.

**Médico** (`/portal-medico/`)

- Painel com os pacientes do dia, agenda da semana e consultas.
- Atendimento com nota SOAP e diagnóstico CID-10 (não fecha sem diagnóstico), prescrições, pedidos de exame e documentos anexados ao processo.
- Vê só os seus pacientes e não vê faturação.

**Gestão** (`/gestao/`)

- Painel da clínica, equipa médica, especialidades, horários e folgas, agenda geral e finanças.
- Definições das regras sem tocar em código: horário, duração das consultas, limites, valor, redes sociais.
- Registo de auditoria e propostas para a Direção.

**Direção** (`/direcao/`): indicadores de atividade e receita, rentabilidade por especialidade e por médico, relatórios exportáveis em PDF, CSV e Excel, metas e aprovação das propostas da Gestão.

**Integrações**

- Lembretes por SMS um dia e uma hora antes da consulta. Sem credenciais, as mensagens ficam só registadas.
- API REST interna (`clinica/v1`) para consultas, pacientes, médicos, especialidades e pagamentos.
- Webhooks de saída para automações (por exemplo, n8n), desligados enquanto não houver URL.
- Cobrança M-Pesa automática (push C2B): a interface do gateway está pronta mas desligada; hoje os pagamentos são registados na receção.

## Tecnologia

- WordPress 6.6+ (tema clássico) e PHP 8.1+ com `strict_types`.
- MySQL com tabelas próprias `clinica_*` para consultas, pacientes, médicos, horários, pagamentos, processo clínico e auditoria.
- Papéis próprios: paciente, médico, secretária, administração e direção.
- CSS e JavaScript sem framework; animações com [Motion](https://motion.dev) 11.18.2, alojado no tema.
- Gráficos desenhados no servidor (`inc/graficos.php`), sem bibliotecas no browser.
- PDF com [Dompdf](https://github.com/dompdf/dompdf) (`lib/`) e Excel `.xlsx` gerado sem dependências.

## Instalação

1. Copie a pasta do tema para `wp-content/themes/` e ative-o em **Aspeto → Temas**. As tabelas e os papéis são criados no primeiro carregamento do site.
2. Crie as páginas abaixo e escolha o modelo indicado. Direção, Termos, Privacidade e Cookies criam-se sozinhas.

   | Página           | Endereço           | Modelo                     |
   | ---------------- | ------------------ | -------------------------- |
   | Especialidades   | `/especialidades/` | Clínica — Especialidades   |
   | Médicos          | `/medicos/`        | Clínica — Médicos          |
   | Sobre nós        | `/sobre/`          | Clínica — Sobre Nós        |
   | Contacto         | `/contacto/`       | Clínica — Contacto         |
   | Marcar consulta  | `/marcar/`         | Clínica — Marcar Consulta  |
   | Entrar           | `/entrar/`         | Clínica — Entrar           |
   | Minha conta      | `/conta/`          | Clínica — Minha Conta      |
   | Portal do médico | `/portal-medico/`  | Clínica — Painel do Médico |
   | Receção          | `/recepcao/`       | Clínica — Receção          |
   | Gestão           | `/gestao/`         | Clínica — Gestão           |
   | Agenda           | `/agenda/`         | Clínica — Gestão de Agenda |

   A página inicial usa `front-page.php` automaticamente.

3. Ponha os segredos no `wp-config.php`, fora do controlo de versões:

   ```php
   define( 'CLINICA_API_KEY', '…' );          // API REST (cabeçalho X-API-Key)
   define( 'CLINICA_SMS_URL', '…' );          // fornecedor de SMS
   define( 'CLINICA_SMS_CHAVE', '…' );
   define( 'CLINICA_SMS_SEGREDO', '…' );
   define( 'CLINICA_N8N_WEBHOOK_URL', '…' );  // webhooks de saída (opcional)
   define( 'CLINICA_WEBHOOK_SECRET', '…' );
   ```

4. Ajuste as regras da clínica em **Gestão → Definições**.

## Estrutura

```
functions.php        carrega os módulos por camadas
inc/                 domínio, dados, segurança, integrações e controladores
pages/               partes dos templates (site público e partials)
page-*.php           um template por página ou portal
assets/css/          estilos (portais/ tem a pele de cada portal)
assets/js/           comportamento; vendor/ tem o Motion
assets/img/          imagens em WebP
lib/                 Dompdf
docs/ARQUITETURA.md  decisões de arquitetura, camadas e plano
```

Os templates só apresentam. As regras vivem em `inc/` e os pedidos seguem o padrão POST → redirecionamento → GET, com nonce em todos os formulários.

## Desenvolvimento

```bash
npm install
npm run format        # Prettier em JS, CSS, JSON e Markdown
npm run format:check
```

O PHP segue o estilo do WordPress e fica fora do Prettier. Antes de enviar alterações, verifique a sintaxe com `php -l` nos ficheiros PHP alterados.

## Segurança e privacidade

- Acesso por papel: cada pessoa só vê o que a sua função exige. O processo clínico é só para os médicos do paciente.
- Palavras-passe cifradas e limite de marcações por IP (guardado cifrado e temporário).
- Limite de tentativas de entrada pronto em `inc/login-security.php`; está desligado (`RATE_LIMIT_ATIVO`) e deve ser ligado em produção.
- Ações da equipa registadas na auditoria.
- Segredos só no `wp-config.php`.
- Cookies apenas necessários.

## Autor

Desenvolvido por **Eclesio Abner Pembelane**.

## Licença

GPL-2.0-or-later.
