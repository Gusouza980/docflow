# Sprint 05 — Financeiro Básico

## Objetivo

Implementar controle financeiro essencial do escritório: contas a receber, pagamentos, pagamentos parciais, cancelamento, renegociação, contas a pagar, despesas por cliente, categorias financeiras, inadimplência e fluxo de caixa inicial.

## Referências

- `docs/briefing_app_gestao_escritorios.md`
- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Casos de uso: UC-084 a UC-099, UC-103.

## Escopo funcional

- Criar conta a receber.
- Criar cobrança recorrente.
- Registrar pagamento.
- Registrar pagamento parcial.
- Cancelar cobrança.
- Renegociar cobrança.
- Registrar conta a pagar.
- Registrar pagamento de despesa.
- Controlar despesas por cliente.
- Acompanhar inadimplência.
- Enviar lembrete de cobrança em modo interno/manual.
- Consultar fluxo de caixa.
- Analisar rentabilidade básica por cliente.
- Categorizar receitas e despesas.

## Tarefas técnicas

### Modelagem e migrations

- Criar tabela `financial_categories`.
- Criar tabela `receivables`.
- Criar tabela `receivable_recurrences`.
- Criar tabela `payments`.
- Criar tabela `payables`.
- Criar tabela `payable_payments`, se necessário, ou unificar pagamentos com tipo.
- Criar tabela de renegociações financeiras, se necessário.
- Incluir `organization_id`, `client_id`, `service_id` opcional, status, vencimento, competência e categoria.
- Definir padrão monetário único: centavos inteiros ou decimal com precisão fixa.
- Adicionar índices por organização, cliente, status, vencimento, pagamento, categoria e competência.

### Categorias financeiras

- Implementar CRUD de categorias.
- Diferenciar receita, despesa e categorias mistas, se necessário.
- Impedir remoção de categoria em uso sem substituição.

### Contas a receber

- Implementar criação de cobrança avulsa.
- Implementar edição limitada enquanto cobrança não estiver paga.
- Implementar status: aberta, paga, vencida, cancelada, renegociada, parcialmente paga.
- Implementar cálculo de vencida por data e status.
- Implementar cancelamento com motivo obrigatório.
- Implementar histórico financeiro na ficha do cliente.

### Pagamentos

- Implementar registro manual de pagamento.
- Implementar pagamento parcial.
- Impedir pagamento duplicado.
- Validar valor maior que zero e compatível com saldo.
- Atualizar status da cobrança conforme saldo.
- Registrar auditoria para toda baixa, cancelamento e alteração.

### Recorrência e renegociação

- Implementar configuração de cobrança recorrente.
- Implementar geração de cobranças por rotina interna ou action idempotente.
- Evitar duplicidade por cliente, recorrência e período.
- Implementar renegociação criando novas cobranças e marcando original como renegociada.

### Contas a pagar e despesas

- Implementar criação de conta a pagar.
- Implementar baixa de despesa.
- Permitir vínculo de despesa a cliente.
- Marcar despesa como reembolsável ou interna.
- Preparar geração futura de reembolso a partir de despesa reembolsável.

### Indicadores financeiros

- Implementar consulta de inadimplência.
- Implementar fluxo de caixa por período.
- Implementar visão financeira do cliente.
- Implementar rentabilidade básica por cliente com receitas e despesas vinculadas.
- Garantir que competência, vencimento e recebimento sejam conceitos separados.

### Permissões e segurança

- Criar policies financeiras.
- Bloquear acesso financeiro para assistente ou usuário sem permissão.
- Garantir que usuário sem acesso financeiro não veja valores na ficha do cliente.
- Registrar auditoria detalhada de operações financeiras.

### Testes

- Testar criação de cobrança.
- Testar pagamento total e parcial.
- Testar cancelamento com motivo.
- Testar bloqueio de alteração de cobrança paga.
- Testar renegociação.
- Testar geração recorrente idempotente.
- Testar contas a pagar e baixa.
- Testar permissões financeiras.
- Testar isolamento multi-tenant.

## Endpoints esperados

- `GET /api/v1/finance/receivables`
- `POST /api/v1/finance/receivables`
- `GET /api/v1/finance/receivables/{receivable}`
- `PATCH /api/v1/finance/receivables/{receivable}`
- `PATCH /api/v1/finance/receivables/{receivable}/cancel`
- `POST /api/v1/finance/receivables/{receivable}/payments`
- `POST /api/v1/finance/receivables/{receivable}/renegotiate`
- `GET /api/v1/finance/recurrences`
- `POST /api/v1/finance/recurrences`
- `POST /api/v1/finance/recurrences/{recurrence}/generate`
- `GET /api/v1/finance/payables`
- `POST /api/v1/finance/payables`
- `PATCH /api/v1/finance/payables/{payable}`
- `POST /api/v1/finance/payables/{payable}/payments`
- `GET /api/v1/finance/categories`
- `POST /api/v1/finance/categories`
- `GET /api/v1/finance/cash-flow`
- `GET /api/v1/finance/overdue`
- `GET /api/v1/clients/{client}/finance`

## Condições de aceite

- Cobranças possuem valor, vencimento, cliente, categoria e status.
- Pagamento total marca cobrança como paga.
- Pagamento parcial mantém saldo em aberto.
- Cobrança vencida aparece em inadimplência.
- Cobrança paga não pode ser alterada de forma destrutiva.
- Cancelamento e renegociação exigem motivo e geram auditoria.
- Recorrência gera cobranças sem duplicidade por período.
- Despesas podem ser vinculadas ao escritório ou a clientes.
- Usuário sem permissão financeira não acessa endpoints nem valores financeiros.
- Fluxo de caixa e ficha financeira do cliente retornam dados consistentes.
- Testes cobrem operações financeiras críticas e isolamento por organização.

## Fora do escopo

- Integração real com gateway de pagamento.
- Emissão de boleto, Pix ou cartão.
- Webhooks de pagamento.
- Nota fiscal.
- Honorários advocatícios avançados.
- BPO financeiro de clientes.

