# Sprint 24 — Cliente paga a cobrança do escritório

## Objetivo

O cliente do **escritório** paga uma `Receivable` aberta no portal (Pix ou boleto) sem o dono baixar à mão. O dinheiro cai na **conta Asaas do tenant**, não na do Docflow.

## Valor que precisa ficar óbvio

1. Admin conecta o Asaas uma vez (chave + token de webhook).
2. Financeiro clica **Gerar Pix** na cobrança (ação explícita — sem surpresa no Asaas).
3. Cliente abre o portal e vê **Pagar agora**: QR + copiar código Pix (ou boleto).
4. Webhook marca `paid` **uma vez**. O escritório vê a cobrança paga sem “Baixar”.
5. Sem Asaas conectado, o portal continua com as instruções de texto (não quebra o que já existe).

## Decisões travadas

| # | Decisão | Escolha | Por quê |
|---|---------|---------|---------|
| 1 | Gateway | Asaas **por organização**, separado do SaaS | Horizonte 5; reusa `AsaasClient` HTTP |
| 2 | Quando criar cobrança no Asaas | Clique explícito no financeiro | Evita cobrança fantasma; mesmo espírito da flag da Sprint 23 |
| 3 | Meio padrão | **Pix** (`billingType=PIX`) | Menor fricção no Brasil; boleto opcional no mesmo botão (select) |
| 4 | Primeira fatura | Não emite no cadastro da recorrência | Scheduler já gera `Receivable`; esta sprint só cobra o que já existe |
| 5 | Baixa manual | Continua existindo | Fallback se o cliente pagou fora / Asaas caiu |
| 6 | Pagamento parcial online | Fora | Pix/boleto quita o saldo em aberto da cobrança |
| 7 | Credenciais | Tabela própria, `encrypted` cast, só admin | Nunca `config('docflow.billing.asaas_*')` |
| 8 | Webhook | `POST /webhooks/tenant/asaas/{organization}` | Não reutilizar `/webhooks/billing/asaas` |

## Referências

- [Horizonte 5](../horizonte-05-rotina-diaria/README.md)
- [Sprint 17](../sprint-17-asaas-billing-gateway/TASKS.md) — Asaas **SaaS** (não misturar)
- [Sprint 23](../sprint-23-contrato-gera-cobranca/TASKS.md) — origem da recorrência
- `AsaasClient`, `Receivable`, `Payment`, `ClientPortal/Finance.vue`

## Escopo funcional

### Escritório — conectar

- Admin em `/organizations`: card **Cobrança no portal (Asaas)**.
- Campos: API key, token do webhook (o mesmo configurado no painel Asaas).
- Mostra URL para colar no Asaas e máscara da chave (`••••` + 4 últimos).
- Financeiro/gestor **não** edita credencial; vê só “conectado / não conectado”.

### Escritório — gerar cobrança

- Em `/finance`, cobrança `open`/`partial`: **Gerar Pix** (ou boleto).
- Exige Asaas conectado + CPF/CNPJ do cliente.
- Idempotente: se já existe charge pendente, reusa (não duplica no Asaas).
- Depois: badge “Aguardando pagamento” + **Ver Pix** (QR, copiar, link).

### Portal

- Cobrança aberta com charge: CTA primário **Pagar agora**.
- Modal: valor, vencimento, QR Pix, **Copiar código Pix**, link do boleto se houver.
- Paga: some o CTA; status **Paga**.
- Sem charge: texto de `payment_instructions` (comportamento atual).

### Webhook

- Eventos: `PAYMENT_RECEIVED`, `PAYMENT_CONFIRMED`.
- Cria `Payment` (`method=asaas`, `received_by_user_id` nulo) e quita a receivable.
- Idempotência por `(organization_id, event_id)`.
- Secret inválido → 401, zero side effects.
- Evento SaaS neste endpoint → ignorado (não toca `SubscriptionInvoice`).

## Tarefas técnicas

- [x] `organization_payment_gateways` (api_key/webhook_token encrypted TEXT).
- [x] `receivable_charges` (1:1 com receivable; IDs e payload Pix/boleto).
- [x] `tenant_receivable_webhook_events`.
- [x] `clients.asaas_customer_id`; `payments.received_by_user_id` nullable.
- [x] `AsaasClient::forTenant()` — **proibido** cair na API key SaaS.
- [x] `TenantAsaasPaymentGateway`: customer + `POST /v3/payments` + Pix QR.
- [x] Actions: salvar gateway, criar charge, marcar pago.
- [x] Rotas office + webhook CSRF-exempt só no path tenant.
- [x] UI Organizations + Finance + Portal.
- [x] Guia `/platform/guides` (financeiro + portal).
- [x] Testes HTTP fake, isolamento SaaS, portal, papéis.

## Critérios de aceite

- [x] Admin salva chave Asaas; ela não aparece em log nem no JSON do Inertia.
- [x] Financeiro gera Pix; portal mostra QR + copiar; `payment_url` preenchida.
- [x] Sem CPF/CNPJ do cliente: erro claro, sem chamada Asaas incompleta.
- [x] Webhook válido marca `paid` uma vez; replay do mesmo `event_id` não duplica `Payment`.
- [x] Webhook com token SaaS (ou token de outra org) não baixa a cobrança.
- [x] Assistente não gera charge; cliente portal não POST de pagamento.
- [x] Sem gateway: portal inalterado (instruções de texto); botão Gerar Pix oculto/desabilitado com copy.
- [x] Driver/manual billing SaaS e `/webhooks/billing/asaas` sem regressão.

## Fora de escopo

- NF-e / cartão recorrente / split / estorno na UI.
- Emitir charge ao criar contrato ou recorrência.
- Upload de comprovante / “já paguei”.
- WhatsApp de cobrança (Sprint 25).
- Trocar Asaas por outro provedor.

## UX (não negociar na implementação)

- Um clique no financeiro para o cliente conseguir pagar. Sem tela extra de “fatura”.
- No portal, o botão primário é **Pagar agora**, não “Ver cobrança”.
- Copiar Pix é mais importante que o link do Asaas.
- Copy de erro em português, acionável (“Cadastre o CPF/CNPJ do cliente”).
