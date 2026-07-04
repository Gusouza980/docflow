# Sprint 11 — E-mail transacional e filas em produção

## Objetivo

Garantir que notificações críticas **saiam de verdade** em ambientes staging/produção, com fila configurada, provedor encapsulado e documentação de deploy.

## Referências

- `config/mail.php`, `.env.example`
- `app/Notifications/PortalClientAlertNotification.php`
- `app/Notifications/PortalResetPasswordNotification.php`
- Notificações Laravel existentes (equipe usa `InternalReminder`, não mail)

## Pré-requisitos

- Sail com Mailpit já no `compose.yaml`.
- Notificações portal implementadas (`ShouldQueue`).

## Escopo funcional

- Provedor de e-mail configurável (SMTP genérico + suporte a Resend/Postmark via SMTP ou driver dedicado).
- Fila `database` ou `redis` documentada para produção; `sync` apenas local opcional.
- Todos os e-mails transacionais do portal passam pela fila.
- Template de e-mail consistente (layout Markdown Laravel).
- Comando ou health check que valida configuração de mail + queue.
- Desenvolvimento continua simples (Mailpit + `QUEUE_CONNECTION=database`).

## Tarefas técnicas

### Contrato de provedor (preparação Sprint 08)

- [ ] Criar interface `App\Contracts\Mail\TransactionalMailer` ou usar Mail Laravel nativo com wrapper `SendTransactionalMail` — **preferir wrapper fino**, não over-engineer.
- [ ] Implementação `LaravelTransactionalMailer` (delega a `Mail` / `Notification`).
- [ ] Implementação `LogTransactionalMailer` para testes.

### Configuração

- [ ] Revisar `.env.example`:
  - `MAIL_MAILER=smtp`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`;
  - bloco comentado para Resend/Postmark (host, port, encryption);
  - `QUEUE_CONNECTION=database` + instrução `queue:work`.
- [ ] Documentar em comentário no `.env.example` (não criar README separado) variáveis obrigatórias produção.
- [ ] `config/mail.php`: `from` usando `MAIL_FROM_ADDRESS` / `APP_NAME`.

### Notificações

- [ ] Revisar `PortalClientAlertNotification` e `PortalResetPasswordNotification`:
  - assunto e corpo em português;
  - `action` URL absoluta correta;
  - fila default (`ShouldQueue`).
- [ ] Opcional: notificação de equipe por e-mail para eventos críticos (feature flag `ORGANIZATION_EMAIL_ALERTS=false` default) — **fora se atrasar sprint**.

### Jobs e worker

- [ ] Garantir migration `jobs` / `failed_jobs` aplicada.
- [ ] Adicionar ao scheduler ou documentar Supervisor/systemd para `queue:work --tries=3`.
- [ ] Comando `docflow:health` ou estender `/up`:
  - verifica conexão queue;
  - verifica mail config não é `log` em produção (`APP_ENV=production`).

### Testes

- [ ] Feature: reset senha portal enfileira notification (`Queue::fake` + assert pushed).
- [ ] Feature: `NotifyPortalClient` envia mail com `Notification::fake`.
- [ ] Feature: mail contém action URL do portal.
- [ ] Teste manual documentado: Mailpit recebe e-mail após `queue:work`.

### Deploy / Sail

- [ ] Opcional: serviço `queue` no `compose.yaml` (profile) rodando `queue:work`.
- [ ] Verificar `vendor/bin/sail artisan queue:listen` no fluxo dev.

## Endpoints / comandos

- `GET /up` — health estendido (opcional).
- `php artisan queue:work`
- `php artisan docflow:health` (se criado)

## Condições de aceite

- Em dev com Sail + Mailpit, fluxo completo: evento → job → e-mail visível no Mailpit.
- Em produção, `.env` com SMTP real não usa driver `log`.
- Falha de envio registra em `failed_jobs`.
- Testes automatizados cobrem enqueue e conteúdo básico.
- Nenhuma credencial commitada.

## Fora do escopo

- Templates marketing / campanhas.
- WhatsApp ou SMS.
- Fila Horizon (pode vir depois).

## Ordem sugerida de implementação

1. Revisar config + `.env.example`.
2. Polir notifications + testes com `Notification::fake`.
3. Health check mail/queue.
4. Serviço queue opcional no Sail.
5. Validar manualmente com Mailpit.
