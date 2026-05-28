# Sprint 08 — Automações, Integrações e Módulos Evolutivos

## Objetivo

Implementar a evolução modular do produto: CRM e onboarding completo, serviços e contratos, automações simples, integrações externas preparadas, base de conhecimento, módulos verticais de advocacia, contabilidade, consultorias/BPO, LGPD avançada e inteligência assistida em modo controlado.

## Referências

- `docs/briefing_app_gestao_escritorios.md`
- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Casos de uso: UC-024 a UC-037, UC-067, UC-069, UC-071, UC-074, UC-115 a UC-149, UC-152 a UC-157, UC-159 a UC-165.

## Escopo funcional

- CRM e onboarding completo.
- Serviços contratados e contratos.
- Comunicação avançada.
- Base de conhecimento.
- Automações simples.
- Módulo jurídico.
- Módulo contábil.
- Consultorias e BPO.
- LGPD avançada.
- Inteligência assistida.
- Preparação de integrações externas.

## Tarefas técnicas

### CRM e onboarding

- Criar entidades `leads`, `lead_activities`, `proposals` e etapas do funil.
- Implementar cadastro de lead.
- Implementar movimentação de lead no funil.
- Implementar registro de contato comercial.
- Implementar criação e resultado de proposta.
- Implementar conversão de lead em cliente preservando histórico.
- Implementar onboarding com checklist, tarefas, documentos, cobrança inicial e reunião.
- Registrar expectativas e canais oficiais do cliente.

### Serviços e contratos

- Criar `service_types`, `client_services` e `contracts`.
- Implementar catálogo de serviços.
- Vincular serviço contratado ao cliente.
- Criar contrato com escopo, valores, recorrência, vigência e anexos.
- Controlar renovação e vencimento de contrato.
- Registrar inclusões, exclusões e limites de escopo.
- Encerrar serviço contratado sem perder histórico.

### Comunicação avançada

- Implementar envio em lote com filtros e revisão.
- Implementar vínculo de mensagens a recursos.
- Implementar status de envio quando houver provedor integrado ou simulado.
- Implementar revisão de mensagem sensível.
- Criar interface de provedor para WhatsApp e e-mail, sem espalhar SDKs pelo domínio.

### Base de conhecimento

- Criar artigos, categorias e versões.
- Implementar publicação, rascunho e controle de visibilidade.
- Implementar busca de artigos.
- Vincular artigos a serviço, tarefa, checklist ou modelo.
- Registrar leitura quando necessário.

### Automações

- Criar estrutura `automation_rules` e `automation_logs`.
- Implementar gatilhos iniciais:
  - cliente criado;
  - serviço contratado;
  - documento pendente;
  - documento próximo do vencimento;
  - cobrança vencida;
  - tarefa vencida.
- Implementar ações iniciais:
  - criar tarefa;
  - solicitar documento;
  - enviar notificação;
  - enviar mensagem por modelo;
  - criar cobrança recorrente.
- Garantir idempotência e logs de execução.
- Permitir ativar, pausar e consultar execuções.

### Integrações externas

- Criar contratos/interfaces para provedores de pagamento, e-mail e WhatsApp.
- Implementar provedores fake para testes.
- Preparar webhook de pagamento idempotente.
- Preparar envio de e-mail transacional.
- Preparar envio via WhatsApp oficial, sem depender de WhatsApp Web.
- Registrar payloads externos com mascaramento adequado.

### Módulo jurídico

- Criar `legal_cases`, `legal_processes`, `legal_movements`, `legal_deadlines` quando necessário.
- Implementar cadastro de caso jurídico.
- Implementar cadastro de processo.
- Implementar movimentações.
- Implementar prazos jurídicos com revisão e alertas.
- Implementar audiências.
- Implementar honorários advocatícios vinculados a cliente, caso ou contrato.
- Implementar restrição de caso sigiloso.
- Validar mensagens jurídicas sensíveis.

### Módulo contábil

- Criar perfil contábil do cliente.
- Criar obrigações mensais.
- Criar competências mensais.
- Solicitar documentos mensais.
- Controlar certificado digital.
- Controlar procuração eletrônica.
- Criar funcionários de clientes.
- Controlar eventos trabalhistas.
- Gerar relatório mensal contábil.

### Consultorias e BPO

- Criar diagnóstico de consultoria.
- Criar plano de ação.
- Registrar visita técnica ou consultiva.
- Registrar não conformidades.
- Controlar contas de cliente em BPO financeiro separadas do financeiro do escritório.
- Gerar relatório de evolução de consultoria.

### LGPD avançada

- Implementar registro e revogação avançada de consentimentos, se ainda não completo.
- Implementar exportação de dados do cliente.
- Implementar solicitação de anonimização ou exclusão.
- Implementar política de retenção documental.
- Implementar bloqueio de usuário por risco de segurança.
- Garantir auditoria de operações de privacidade.

### Inteligência assistida

- Criar camada isolada para provedor de IA.
- Implementar recursos em modo assistido e revisável:
  - resumo de histórico do cliente;
  - resumo de conversa longa;
  - sugestão de resposta;
  - sugestão de próximos passos;
  - identificação de cliente em risco;
  - rascunho de relatório mensal;
  - perguntas gerenciais.
- Permitir desativar IA por organização.
- Evitar envio de dados desnecessários ao provedor.
- Registrar quando conteúdo foi gerado ou sugerido por IA.

### Testes

- Testar conversão de lead em cliente.
- Testar onboarding criando tarefas, documentos e cobrança.
- Testar contrato e renovação.
- Testar automações sem duplicidade.
- Testar logs de automação.
- Testar provedores fake de e-mail, WhatsApp e pagamento.
- Testar webhook idempotente.
- Testar sigilo jurídico.
- Testar competência mensal contábil.
- Testar exportação/anonimização com bloqueios legais.
- Testar IA desabilitada por organização.

## Endpoints esperados

Como esta sprint agrupa módulos evolutivos, os endpoints devem ser definidos por módulo. Exemplos mínimos:

- `GET /api/v1/leads`
- `POST /api/v1/leads`
- `PATCH /api/v1/leads/{lead}/stage`
- `POST /api/v1/leads/{lead}/convert`
- `POST /api/v1/proposals`
- `PATCH /api/v1/proposals/{proposal}/accept`
- `PATCH /api/v1/proposals/{proposal}/reject`
- `GET /api/v1/service-types`
- `POST /api/v1/service-types`
- `POST /api/v1/clients/{client}/services`
- `POST /api/v1/contracts`
- `PATCH /api/v1/contracts/{contract}/renew`
- `GET /api/v1/automation-rules`
- `POST /api/v1/automation-rules`
- `PATCH /api/v1/automation-rules/{rule}/pause`
- `GET /api/v1/automation-logs`
- `GET /api/v1/knowledge-base/articles`
- `POST /api/v1/knowledge-base/articles`
- `POST /api/v1/legal-cases`
- `POST /api/v1/accounting/obligations`
- `POST /api/v1/consulting/action-plans`
- `POST /api/v1/privacy/export-requests`
- `POST /api/v1/privacy/anonymization-requests`
- `POST /api/v1/ai/client-summary`

## Condições de aceite

- Lead pode ser convertido em cliente sem perda de histórico.
- Onboarding consegue criar checklist, tarefas, solicitações documentais, cobrança e reunião conforme configuração.
- Serviços e contratos ficam visíveis na ficha do cliente.
- Contratos com vencimento geram alerta ou consulta de renovação.
- Automações possuem gatilho, condição, ação, status e log.
- Automações são idempotentes e não criam duplicidades óbvias.
- Integrações externas são encapsuladas por interfaces e testadas com provedores fake.
- Webhooks preparados validam assinatura/segredo e idempotência.
- Casos jurídicos sigilosos restringem documentos, prazos e mensagens relacionados.
- Competências contábeis geram obrigações e solicitações mensais.
- Dados de BPO financeiro do cliente não se misturam ao financeiro do escritório.
- Solicitações LGPD respeitam bloqueios legais, fiscais, contratuais e jurídicos.
- Recursos de IA são opcionais, auditáveis e exigem revisão humana quando sensíveis.
- Testes cobrem fluxos críticos de cada módulo habilitado.

## Fora do escopo

- Microsserviços separados.
- Treinamento de modelo próprio de IA.
- Integrações oficiais completas com todos os provedores possíveis.
- BI avançado ou data warehouse.
- Customização visual avançada de fluxos por cliente enterprise.

