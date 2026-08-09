# Sprint 21 — Dashboard de resultado e valor

## Objetivo

Redesenhar o dashboard do escritório para liderar com **resultado** (recebido, MRR, pipeline, valor em risco), rebaixando a visão operacional de pendências a um segundo plano — sem inventar ROI artificial do Docflow.

## Referências

- [Horizonte 4 — README](../horizonte-04-prova-de-valor/README.md)
- Sprint 09 (dashboard/alertas), 18 (CRM), 19 (contratos)
- `ReportMetrics`, `BuildsDashboardPayload`, `Dashboard/Index.vue`

## Escopo funcional

### Hero — Resultado do período

- Com financeiro: recebido (+ delta vs período anterior), em aberto, vencido, saldo líquido (recebido − payables pagos).
- Sem financeiro: tarefas concluídas, docs aprovados no período, clientes ativos (sem centavos).

### Contratos

- MRR estimado (mensal + anual/12; único fora do MRR).
- Valor em risco 30d (soma `amount_cents` a vencer).
- Escopo por `clientQuery`; oculto para readonly.

### Comercial (feature `crm` + `canViewCrm`)

- Pipeline aberto (`estimated_value_cents` de stages abertos).
- Ganho no período (leads convertidos + propostas aceitas).

### UI

1. Hero  
2. Faixa contratos / comercial  
3. Alertas  
4. Operação (KPIs atuais compactos)  
5. Empty state com CTA de valor  

## Tarefas técnicas

- [x] `ReportMetrics::valueSummary`, `contractsRevenueSummary`, `commercialSummary` + período anterior.
- [x] `BuildsDashboardPayload` expõe `value`, `contracts_revenue`, `commercial`, `can_access_crm`.
- [x] Redesign `Dashboard/Index.vue`.
- [x] Testes em `DashboardManagementTest`.
- [x] Docs ACTIVE + horizonte 4.

## Critérios de aceite

- [x] Gestor com financeiro vê recebido + delta e valor em risco no primeiro viewport.
- [x] Profissional+ vê bloco comercial; Essencial não.
- [x] Role sem financeiro não vê centavos de cobrança.
- [x] Alertas operacionais permanecem clicáveis.

## Fora de escopo

- ROI Docflow (automações × horas)
- BI / gráficos pesados
- Reescrita completa de `/reports`
