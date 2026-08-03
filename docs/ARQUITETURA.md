# Clínica Vida — Arquitetura & Plano do Sistema

> Sistema de gestão clínica (Maputo). Base: WordPress usado **como aplicação**
> (tabelas próprias `clinica_*`, papéis próprios, camadas separadas).
> Objetivo: milhares de utilizadores, regras de negócio configuráveis, auditável.

---

## 1. Decisão de arquitetura

WordPress **é adequado** para esta escala (milhares de pacientes) desde que seja
tratado como aplicação, não como blog:

- **Dados** em tabelas próprias `clinica_*` (não em `wp_posts`).
- **Autenticação e papéis** via utilizadores/roles do WordPress (maduro e seguro).
- **Camadas separadas** (ver §2). Regras de negócio **nunca** nos templates.

> Se um dia crescer para nível hospitalar (várias unidades, integrações HL7,
> alta concorrência), a camada de Serviços/Repositórios aqui definida migra para
> uma app dedicada (Laravel/Node) sem reescrever a lógica de negócio.

---

## 2. Camadas (fluxo de um pedido)

```
Template (page-*.php)              → apresentação, zero SQL
   │  chama
Controlador (inc/controllers)      → valida input, nonce, orquestra, PRG
   │  chama
Serviço (inc/services)             → REGRAS DE NEGÓCIO (agenda, pagamento, processo)
   │  chama
Repositório (inc/repo)             → SQL preparado, transações
   │
Base de dados (clinica_*)
```

Serviços novos a criar:
- `Agenda_Service` — gera slots livres, valida limites, previne sobreposição.
- `Consulta_Service` — máquina de estados + histórico.
- `Pagamento_Service` — abstração de gateway (M-Pesa hoje, outros depois).
- `Processo_Service` — abre/atualiza o processo clínico.
- `Auditoria_Service` — regista todas as operações sensíveis.
- `Config_Service` — lê regras da BD (com cache), nunca do código.

---

## 3. Papéis e permissões (WordPress custom roles)

| Papel | Slug | Resumo |
|---|---|---|
| Paciente | `clinica_paciente` | Marca/reagenda/cancela, paga (M-Pesa), vê o **seu** histórico/exames/receitas/recibos, edita perfil. |
| Secretária | `clinica_secretaria` | Cadastra pacientes, marca/reagenda/cancela, confirma presença, confirma pagamentos, emite recibos, gere agenda dos médicos. |
| Administração | `clinica_gestao` | Gere médicos/funcionários/especialidades/pagamentos, relatórios, configura regras da clínica. |
| Administrador | `clinica_admin` | Utilizadores, roles, permissões, configs gerais, logs, backup, auditoria — controlo total. |
| Médico | `clinica_medico` | Vê a sua agenda, atende, diagnóstico, receita, pedido de exames, notas no processo. |

Cada papel = conjunto de *capabilities* (`clinica_marcar`, `clinica_ver_processo`,
`clinica_gerir_medicos`, …). Um paciente **nunca** acede a dados de outro.

---

## 4. Módulos

Autenticação · Utilizadores · Roles/Permissões · Pacientes · Médicos ·
Especialidades · Agenda · Consultas · Processo Clínico · Diagnósticos ·
Receitas · Exames · Pagamentos (M-Pesa) · Recibos · Relatórios ·
Configurações · Notificações · Auditoria.

---

## 5. Modelo de dados (tabelas `clinica_*`)

**Existentes:** `especialidades`, `medicos`, `pacientes`, `consultas`, `horarios`, `mensagens`.

**Núcleo / pessoas**
- `unidades` (id, nome, morada, ativo)
- `processos` (id, **numero** `PROC-000001` UNIQUE, paciente_id UNIQUE, aberto_em, estado, alergias, antecedentes, grupo_sanguineo)
- `funcionarios` (user_id, papel, unidade_id) — secretárias/gestão
- `pacientes` (+ processo_id)
- `medicos` (+ limite_dia, limite_hora, duracao_min, unidade_id)

**Agenda**
- `horarios` (medico_id, unidade_id, dia_semana 0-6, hora_inicio, hora_fim, intervalo_inicio, intervalo_fim)
- `bloqueios` (medico_id, data_inicio, data_fim, motivo)  — férias/folgas
- `feriados` (data UNIQUE, descricao)

**Consultas**
- `consultas` (id, **processo_id**, paciente_id, medico_id, unidade_id, inicio, fim, duracao_min, estado, valor, pago TINYINT, observacoes, criado_por, criado_em)
- `consulta_estados` (id, consulta_id, de_estado, para_estado, user_id, motivo, criado_em) — histórico completo

**Clínico (append-only)**
- `diagnosticos` (id, consulta_id, processo_id, medico_id, cid, descricao, criado_em)
- `receitas` (id, consulta_id, processo_id, medico_id, criado_em) + `receita_itens` (receita_id, medicamento, dose, posologia)
- `exames` (id, consulta_id, processo_id, tipo, estado, resultado_anexo_id, criado_em)
- `documentos` (id, processo_id, tipo, anexo_id, criado_em)
- `notas` (id, processo_id, consulta_id, autor_id, texto, criado_em) — imutável; correções via adenda

**Pagamentos**
- `pagamentos` (id, consulta_id, paciente_id, valor, metodo 'mpesa', estado, referencia_externa, criado_em, atualizado_em)
- `recibos` (id, pagamento_id, numero UNIQUE, emitido_em)

**Sistema**
- `config` (chave UNIQUE, valor, tipo) — **todas** as regras configuráveis
- `auditoria` (id, user_id, acao, entidade, entidade_id, ip, detalhes JSON, criado_em)
- `notificacoes` (id, destino_user_id, canal, assunto, corpo, estado, criado_em)
- `mensagens` (contacto público — já existe)

**Índices críticos** (para escala): `consultas(medico_id, inicio)`,
`consultas(paciente_id)`, `consultas(processo_id)`, `consultas(estado)`,
`pagamentos(consulta_id)`, `auditoria(criado_em)`. **UNIQUE** `consultas(medico_id, inicio)`
para impedir dupla marcação ao nível da BD.

---

## 6. Regras de negócio (todas configuráveis em `config`)

**Agenda — proibido:**
horários duplicados · datas passadas · fora do expediente · durante o intervalo ·
em bloqueios/folgas · em feriados · paciente com duas consultas à mesma hora.

**Limites por médico (config):** ex. `max 5/hora` **e/ou** `max 40/dia`.
Ao atingir → bloquear novos agendamentos e mostrar indisponibilidade. **Nunca ultrapassar.**

**Estados da consulta:**
`agendada → confirmada → chegou → em_atendimento → finalizada`
com saídas `cancelada` e `faltou`. Cada transição → registo em `consulta_estados`.

**Fluxo:** Agendamento → validação de regras → pagamento M-Pesa → confirmação →
chegada → atendimento → diagnóstico → receita → exames → finalização →
**atualização automática do Processo Clínico**.

---

## 7. Concorrência e escala (o ponto senior)

Milhares de utilizadores ⇒ marcações **simultâneas**. Para nunca furar os limites:

1. **Transação + bloqueio**: a marcação corre em `START TRANSACTION`; conta as
   consultas do médico no dia/hora com `SELECT … FOR UPDATE`; se dentro do limite
   e o slot livre, insere; senão, aborta. Garante os limites sob concorrência.
2. **UNIQUE `(medico_id, inicio)`**: a BD recusa dupla marcação mesmo em corrida.
3. **Paginação** em todas as listas (nunca carregar tudo).
4. **Config em cache** (transient) — lida uma vez por pedido.
5. **Tabelas clínicas e de auditoria append-only** (só inserir; nunca UPDATE/DELETE).

---

## 8. Pagamentos (M-Pesa, extensível)

Interface `Gateway_Pagamento { iniciar(); confirmar_callback(); estado(); }`.
Implementação `Mpesa_Gateway` hoje. Estados: **pendente · pago · cancelado · falhou · reembolsado**.
Adicionar outro meio no futuro = nova classe, **sem tocar** no resto.

---

## 9. Roadmap por fases

1. **Fundação** — papéis/capabilities + dashboard por papel + `config` (regras em BD)
   + CRUD de médicos/especialidades/unidades (ligar à BD real).
2. **Agenda real** — `horarios`/`bloqueios`/`feriados` + `Agenda_Service` (slots livres)
   + limites por médico + anti-sobreposição transacional.
3. **Operação** — Secretária (cadastrar/marcar por terceiros, check-in, recibos)
   + Inbox de mensagens + Notificações (email/SMS).
4. **Pagamentos** — `Pagamento_Service` + M-Pesa + recibos.
5. **Clínico** — Processo + diagnósticos + receitas + exames + documentos.
6. **Gestão** — relatórios + auditoria completa + backup.

---

## 10-B. Estado da implementação

**Fase 1 (Fundação) — CONCLUÍDA:**
- RBAC com **separação de deveres** (`inc/roles.php`): 5 papéis; o **Admin/TI não tem
  acesso clínico** (`clinica_ver_processo`/`atender`/… removidas) — só o Médico é clínico.
- **Menor privilégio**: o painel do médico mostra **só os seus** pacientes
  (`consultas_do_medico`); receção/gestão veem a agenda global.
- Configurações em BD (`inc/config.php`), Processo Clínico (`inc/processo.php`),
  painel de Gestão com CRUD real de especialidades/médicos (`inc/gestao.php`),
  encaminhamento por papel após login.

Rever papéis a cada 3–6 meses (boa prática RBAC).

## 10. Auditoria (transversal)

Toda operação sensível → `auditoria`: **utilizador, ação, entidade, data/hora, IP,
detalhes (JSON antes/depois)**. Ex.: "criou paciente", "cancelou consulta",
"alterou pagamento", "editou prontuário".
