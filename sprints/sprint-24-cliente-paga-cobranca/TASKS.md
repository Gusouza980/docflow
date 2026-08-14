# Sprint 24 — Cliente paga a cobrança do escritório

> Planejada. Implementar após a Sprint 23.

## Objetivo

O cliente do **escritório** paga `Receivable` via Pix/boleto no portal. Gateway **por organização**, separado do Asaas do billing SaaS.

## Decisão de kickoff

Asaas por org (reusa `AsaasClient`) se o sandbox estiver ok; senão MVP “QR/link + registrar pagamento” e gateway completo na sprint seguinte.

## Aceite (rascunho)

- Portal mostra “Pagar agora” em cobrança aberta.
- Webhook marca `paid` uma única vez.
- Credenciais do tenant não vazam para o billing SaaS.

## Fora

- NF-e. Cartão recorrente avançado. Split de honorários.
