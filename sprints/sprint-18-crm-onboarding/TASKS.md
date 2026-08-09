# Sprint 18 — CRM (leads/funil) e onboarding

## Objetivo

Entregar CRM mínimo viável para o escritório: **leads**, etapas de funil, atividades/follow-ups, propostas simples e **conversão em cliente** com histórico preservado; mais **onboarding** inicial (checklist → tarefas/docs) ao converter ou ao marcar contrato aceito.

## Referências

- [Horizonte 3 — README](../horizonte-03-comercial-operacao/README.md)
- `docs/briefing_app_gestao_escritorios.md` — §§ 7.3, 7.4
- UC-024 a UC-031
- `TaskTemplate`, `DocumentRequest`, `Client` existentes

## Decisões de kickoff

- CRM disponível **apenas a partir do plano Profissional** (feature `crm` em Profissional e Escritório).

## Pré-requisitos

- Clientes, tarefas, solicitações de documento e permissões Spatie por org (Horizonte 1).
- Feature flags de plano já existem; CRM fica disponível a partir de Profissional **ou** liberado para todos no MVP (decidir no kickoff; default: **todos os planos**, limites depois).

## Escopo funcional

### Leads e funil

- CRUD de lead (nome, contato, origem, responsável, valor estimado, serviço de interesse, stage).
- Stages iniciais (seed): Novo contato → Primeiro atendimento → Diagnóstico → Proposta → Negociação → Aceito / Perdido / Sem resposta.
- Kanban ou lista + filtro por stage/responsável/origem (Inertia).
- Motivo de perda obrigatório ao mover para Perdido.

### Atividades e propostas

- `lead_activities`: ligação, reunião, WhatsApp, e-mail, nota (registro manual; sem WhatsApp API).
- `proposals`: valor, status (`draft`, `sent`, `accepted`, `rejected`), notas, datas.
- Aceitar proposta pode sugerir conversão (não obriga).

### Conversão

- Action `ConvertLeadToClient`: cria `Client` (+ contato), vincula `lead.client_id`, status lead = convertido.
- Histórico de atividades/propostas permanece no lead e aparece na ficha do cliente (aba Comercial).

### Onboarding

- Template de onboarding por org (checklist items → cria `Task`s e opcionalmente `DocumentRequest`).
- Disparo na conversão (flag) ou action manual “Iniciar onboarding”.
- Página/resumo: itens pendentes do onboarding do cliente.

## Tarefas técnicas

### Modelagem

- [x] Migration `leads`:
  ```text
  organization_id, client_id nullable
  name, email, phone, origin, stage
  owner_user_id nullable, estimated_value_cents nullable
  service_interest nullable, lost_reason nullable
  converted_at nullable, metadata json
  ```
- [x] Migration `lead_activities`: lead_id, type, body, happened_at, created_by_user_id.
- [x] Migration `proposals`: lead_id, title, amount_cents, status, sent_at, decided_at, notes.
- [x] Migration `onboarding_templates` + `onboarding_template_items` (org-scoped).
- [x] Migration `client_onboardings` (+ items status) **ou** reutilizar tasks com tag/source `onboarding`.
- [x] Models + factories; policies/permissions (`leads.view`, `leads.manage` ou reutilizar roles admin/manager/assistant).

### Actions

- [x] `CreateLead`, `UpdateLeadStage`, `RecordLeadActivity`.
- [x] `CreateProposal`, `AcceptProposal`, `RejectProposal`.
- [x] `ConvertLeadToClient`.
- [x] `StartClientOnboarding` (a partir de template).

### Controllers / rotas web (Inertia)

- [x] `LeadController` — index (kanban/lista), show, store, update, destroy.
- [x] `LeadActivityController`, `ProposalController`.
- [x] `LeadConversionController@store`.
- [x] `OnboardingTemplateController` (admin) + `ClientOnboardingController`.
- [x] Menu sidebar: “CRM” / “Leads”.

### Frontend

- [x] `Leads/Index.vue`, `Show.vue`, formulários.
- [x] Board por stage (drag opcional; MVP: botões “mover”).
- [x] Aba comercial em `Clients/Show` (leads convertidos / histórico).
- [x] UI onboarding pendente no cliente.

### Testes

- [x] Feature: criar lead e mover stage.
- [x] Feature: perdido exige `lost_reason`.
- [x] Feature: converter cria client e preserva activities.
- [x] Feature: onboarding cria N tasks a partir do template.
- [x] Feature: assistente sem permissão → 403 (se policy restritiva).
- [x] Feature: isolamento tenant (lead de outra org → 404).

## Endpoints (web MVP)

| Método | Rota | Quem |
|--------|------|------|
| GET/POST | `/leads` | equipe |
| GET/PATCH | `/leads/{lead}` | equipe |
| POST | `/leads/{lead}/activities` | equipe |
| POST | `/leads/{lead}/proposals` | equipe |
| POST | `/leads/{lead}/convert` | manager/admin |
| GET/POST | `/onboarding-templates` | admin |
| POST | `/clients/{client}/onboarding` | manager/admin |

## Condições de aceite

- Funil usable no dia a dia (criar → mover → follow-up → converter).
- Conversão não duplica cliente se `client_id` já vinculado.
- Onboarding gera trabalho visível em `/tasks`.
- Testes cobrem conversão e isolamento por org.

## Fora do escopo

- Scoring de lead / IA.
- Integração formulário site / Meta Ads.
- WhatsApp API.
- Comissão de vendedor.
- Pipeline multi-moeda.

## Ordem sugerida

1. Migrations + models + policies.
2. CRUD leads + stages.
3. Activities + proposals.
4. ConvertLeadToClient + UI ficha.
5. Onboarding templates + StartClientOnboarding.
6. Testes.
