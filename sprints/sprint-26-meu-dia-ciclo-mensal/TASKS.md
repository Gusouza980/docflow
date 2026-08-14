# Sprint 26 — Fila “Meu dia” + ciclo mensal leve

## Objetivo

O membro abre **uma** fila do dia e age em tarefa, documento e cobrança (e chamado, se houver) sem caçar módulos. Serviços recorrentes pedem o pacote documental do mês **uma vez**, no dia 1.

## Valor que precisa ficar óbvio

1. Sidebar **Meu dia** — lista do membro, ordenada por atraso.
2. Cada item leva direto à ação (tarefa, solicitação, financeiro, chamado).
3. No tipo de serviço, o escritório marca **quais docs pedir todo mês**.
4. Dia 1 (comando diário): cria a solicitação do mês; segunda execução no mesmo mês não duplica.

## Decisões travadas

| # | Decisão | Escolha | Por quê |
|---|---------|---------|---------|
| 1 | Onde mora a fila | `/my-day`, não misturar no dashboard gerencial | Dashboard continua org-wide; Meu dia é do membro |
| 2 | Tipos na fila | Tarefa, item documental, cobrança vencida, chamado | Aceite: ≥ 3 tipos; chamado já tem `assigned_to` |
| 3 | Escopo do membro | Tarefas/chamados atribuídos; docs/cobranças dos clientes que ele pode ver | Reusa `ClientPolicy` / query de clientes |
| 4 | Pacote documental | JSON no `ServiceType` (`monthly_document_items`) | Sem tabela extra; checklist curto |
| 5 | Anti-duplicação | `document_requests.client_service_id` + `billing_period` (1º do mês) | Mesmo espírito da recorrência financeira |
| 6 | Quando gera | Comando diário; só cria se `billing_period` do mês ainda não existe | “Todo dia 1” sem cron frágil só no dia 1 |
| 7 | Fiscal/jurídico | Fora | Horizonte 6 |

## Referências

- [Horizonte 5](../horizonte-05-rotina-diaria/README.md)
- `ReportMetrics::alerts()`, `TaskController::taskQuery()`, `GenerateReceivableRecurrences`
- `DocumentRequest` + `DocumentRequestItem` (criação manual já existe)

## Escopo funcional

### Meu dia

- `/my-day`: seções Tarefas, Documentos, Cobranças, Chamados (esconde vazias).
- Item: cliente, título, vencimento, status, link.
- Atrasado em evidência. Vazio: “Nada para hoje.”

### Pacote mensal

- Em `/service-types`: lista “Pedir todo mês” (títulos).
- Cliente com serviço **ativo** e pacote não vazio entra no comando.
- Gera `DocumentRequest` com itens; título claro (“Documentos de agosto — Contabilidade”).
- Já existe pedido daquele serviço no mês: skip.

## Tarefas técnicas

- [x] `/my-day` + `BuildsMyDayPayload` (tarefa, doc, cobrança, chamado).
- [x] `monthly_document_items` no `ServiceType` + `client_service_id`/`billing_period` na solicitação.
- [x] Comando `documents:generate-monthly-packages` (diário, idempotente).
- [x] UI tipos de serviço: “Pedir todo mês”.
- [x] Guia `/platform/guides` (operação, serviços, dashboard).
- [x] Testes: fila, isolamento, pacote, skip.

## Critérios de aceite

- [x] Membro abre Meu dia e age em ≥ 3 tipos (tarefa, doc, cobrança) sem caçar módulos.
- [x] Atrasado em evidência; vazio: “Nada para hoje.”
- [x] Tipo de serviço guarda a lista “pedir todo mês”.
- [x] Comando gera o pacote do mês com itens; segunda execução no mesmo mês não duplica.
- [x] Serviço pausado / tipo inativo / pacote vazio não gera.
- [x] Outra org e cliente restrito não aparecem na fila.

## Fora de escopo

- Módulo fiscal/contábil, jurídico, app mobile.
- Reescrever o dashboard.
- Atribuir cobrança a um membro (continua via cliente).
- Editor rico de checklist por cliente (o pacote é do tipo de serviço).
