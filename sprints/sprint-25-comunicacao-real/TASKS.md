# Sprint 25 — Comunicação que sai de verdade

## Objetivo

O escritório **envia** mensagem (não só registra): lote com revisão e status por destinatário; automação `send_message_template` (e-mail + portal); atalho `wa.me` com o texto do modelo. Sem WhatsApp Business API.

## Valor que precisa ficar óbvio

1. Em **Envio em lote**, filtrar inadimplentes (ou docs a vencer), revisar quem entra/quem é pulado, confirmar.
2. Cada destinatário fica **Na fila / Enviado / Falhou / Pronto no WhatsApp**.
3. Canal e-mail **manda e-mail** com assunto e corpo do modelo (não só grava no hub).
4. Canal portal grava a conversa e avisa no e-mail do acesso (já existia; agora passa pelo mesmo pipeline).
5. Canal WhatsApp abre `wa.me` com o texto pronto — o sistema não fala com a API da Meta.
6. Automação **Cobrança vencida → e-mail ao cliente** usa um modelo existente, uma vez por cobrança+vencimento.

## Decisões travadas

| # | Decisão | Escolha | Por quê |
|---|---------|---------|---------|
| 1 | WhatsApp oficial | Fora — só `wa.me` | Horizonte 5; sem janela 24h / template Meta |
| 2 | E-mail “de verdade” | Notification com subject/body do modelo | Canal `email` hoje só registrava |
| 3 | Quando enviar no lote | Clique explícito após revisão | Mesmo espírito da flag da 23 e do Pix da 24 |
| 4 | Filtros do lote | Inadimplentes, docs a vencer (7 dias), seleção | Valor imediato após 23/24 |
| 5 | Teto do lote | 100 destinatários por envio | Evita timeout e e-mail em massa acidental |
| 6 | Consentimento | Obrigatório; sem consentimento = pulado | Já é regra do hub/portal |
| 7 | Destino e-mail | Contato primário → qualquer contato → acesso do portal | Não inventar campo novo no cliente |
| 8 | Destino WhatsApp | `whatsapp` do contato, senão `phone`; DDI 55 se 10–11 dígitos | Cadastro que já existe |
| 9 | Automação | Só e-mail e portal | Cron não clica em `wa.me` |
| 10 | Status novos | `queued`, `failed` (+ `registered` = WhatsApp à espera) | Fila / enviado / falhou do aceite |
| 11 | Envio avulso | Mesmo pipeline do lote | Hub e portal deixam de “só registrar” no e-mail |

## Referências

- [Horizonte 5](../horizonte-05-rotina-diaria/README.md)
- [Sprint 20](../sprint-20-automacoes-simples/TASKS.md) — leftover `send_message_template`
- [Sprint 24](../sprint-24-cliente-paga-cobranca/TASKS.md) — filtro de inadimplência
- `MessageTemplate`, `ClientMessage`, `CommunicationConsent`, `AutomationRunner`

## Escopo funcional

### Escritório — lote

- `/messages/batch`: filtro + modelo → **Revisar** → lista pronta / pulada (motivo) → **Enviar**.
- Cria `message_batches` + N `client_messages` com status por destinatário.
- Depois: `/messages/batches/{batch}` com status e, no WhatsApp, **Abrir WhatsApp**.

### Escritório — avulso

- Hub e `/portal` usam o mesmo envio: e-mail sai; portal notifica; WhatsApp fica `registered` com botão `wa.me`.
- Clicar **Abrir WhatsApp** marca `sent`.

### Automação

- Preset **Cobrança vencida → e-mail ao cliente** (template obrigatório).
- Action `send_message_template`; idempotência do runner (não reenvia no mesmo `due_at`).
- Sem consentimento ou sem e-mail: log ok, resultado `skipped` (não explode a regra).

## Tarefas técnicas

- [x] `queued` / `failed` em `ClientMessage`; `failure_reason`; `batch_id`.
- [x] Tabela `message_batches`.
- [x] `DeliverOutboundClientMessage` + job (e-mail/portal) + `WhatsAppLink`.
- [x] Notification com corpo do modelo (não o alerta genérico de 200 chars).
- [x] UI lote + hub `wa.me` + preset de automação.
- [x] Guia `/platform/guides` (portal + automações).
- [x] Testes: lote, isolamento, e-mail, wa.me, automação, pulados.

## Critérios de aceite

- [x] Lote para N clientes grava status por destinatário; revisão mostra quem será pulado e por quê.
- [x] E-mail do modelo chega ao endereço do contato (fila sync nos testes).
- [x] Sem consentimento ou sem destino: não cria mensagem “enviada”; motivo visível.
- [x] Automação cobrança vencida → e-mail usa template; segunda execução no mesmo `due_at` não duplica.
- [x] Clique em WhatsApp abre `https://wa.me/{digits}?text=` com o corpo renderizado.
- [x] Readonly e outra org: 403 / vazio.
- [x] WhatsApp Business API continua fora.

## Fora de escopo

- WhatsApp Business API, janela 24h, templates Meta.
- SMS / ligação.
- Retry automático de `failed` na UI.
- Editor rico / anexos no e-mail do modelo.
- Trocar o alerta genérico do portal em todos os outros fluxos.

## UX (não negociar na implementação)

- Revisão **antes** de enviar. Sem “enviar para todos” escondido.
- Motivo do pulo em português (“Sem consentimento”, “Sem e-mail no contato”).
- No WhatsApp, o botão primário é **Abrir WhatsApp**, não “Marcar como enviado”.
- Copy de erro acionável (“Cadastre o WhatsApp no contato do cliente”).
