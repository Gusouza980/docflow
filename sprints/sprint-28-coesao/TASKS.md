# Sprint 28 — Coesão

Fecha os loops que já existiam, concentra a rotina em Meu dia e na ficha, e liga tarefa, chamado, documento e cobrança.

## Onda 1

- [x] API de cliente dispara `client.created` e a API de mensagem usa o mesmo envio da web
- [x] Quitar ou cancelar o último título vencido devolve o cliente para ativo
- [x] Ativar contrato rascunho gera a recorrência quando a flag está ligada
- [x] Automação de solicitação documental exige itens
- [x] Proposta aceita pode criar contrato rascunho
- [x] Card de chamados abre o portal; cobrança do Meu dia abre o título
- [x] Menu esconde Financeiro e Auditoria sem permissão
- [x] `finance_advanced` e `reports_scheduling` bloqueiam as ações avançadas quando há planos
- [x] Webhook do tenant sem cobrança permanece pendente e retenta
- [x] Portal encerra a sessão se o plano perder a feature
- [x] `automations:dispatch-due` grava `SchedulerRunLog`
- [x] Reenvio do mesmo lote no mesmo dia é idempotente

## Onda 2

- [x] Login do escritório cai em Meu dia
- [x] Meu dia inclui prazos atribuídos
- [x] Ficha tem abas Tarefas, Prazos e Financeiro
- [x] Menu agrupado e navegação inferior no mobile
- [x] Busca do topo encontra clientes e tarefas
- [x] Chamado e mensagem do portal abrem a ficha em `?tab=tickets`

## Onda 3

- [x] Tarefa pode apontar para contrato, solicitação e chamado
- [x] Chamado pode apontar para cobrança, com ação no financeiro
- [x] Documento enviado herda o serviço da solicitação
- [x] Onboarding cria a solicitação documental com itens
- [x] Mensagem pode referenciar tarefa, documento ou cobrança
- [x] Automação de vencido não reenvia o modelo se o lembrete do dia já saiu
- [x] `RunAutomationRule` passa a ser despachado pelo runner
