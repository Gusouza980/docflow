# Sprint 03 — Documentos e Solicitações Documentais

## Objetivo

Implementar o núcleo documental do sistema: categorias, upload seguro, versões, validade, visibilidade, solicitações de documentos ao cliente, aprovação, recusa, filtros e auditoria de acesso.

## Referências

- `docs/briefing_app_gestao_escritorios.md`
- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Casos de uso: UC-038 a UC-050, UC-102, UC-150, UC-158.

## Escopo funcional

- Cadastrar categorias de documentos.
- Enviar documentos internos.
- Solicitar documentos ao cliente.
- Receber documentos enviados pelo cliente.
- Aprovar e recusar documentos recebidos.
- Substituir documentos mantendo versões.
- Controlar validade e vencimento.
- Visualizar e baixar documentos com segurança.
- Definir visibilidade e sensibilidade.
- Consultar documentos por filtros.
- Cancelar solicitações documentais.

## Tarefas técnicas

### Modelagem e migrations

- Criar tabela `document_categories`.
- Criar tabela `documents`.
- Criar tabela `document_versions`.
- Criar tabela `document_requests`.
- Criar tabela `document_request_items`.
- Criar tabela de eventos ou usar auditoria para visualização/download.
- Incluir `organization_id`, `client_id`, status, categoria, validade, sensibilidade e visibilidade.
- Adicionar índices por organização, cliente, categoria, status, validade e data de criação.
- Usar soft deletes quando aplicável.

### Armazenamento de arquivos

- Configurar disco privado para desenvolvimento.
- Preparar abstração para S3 ou serviço compatível em produção.
- Gerar nomes internos seguros.
- Preservar nome original apenas como metadado.
- Validar MIME type, extensão e tamanho.
- Calcular hash do arquivo.
- Impedir acesso público direto ao arquivo.
- Implementar geração de URL temporária ou stream seguro para visualização/download.

### Categorias e metadados

- Implementar CRUD de categorias documentais.
- Permitir validade padrão por categoria.
- Permitir marcação de categoria sensível.
- Impedir exclusão de categoria em uso sem tratamento explícito.

### Upload e versões

- Implementar upload interno de documento.
- Implementar criação da versão inicial.
- Implementar substituição com nova versão.
- Preservar histórico de versões.
- Marcar versão anterior como substituída quando aplicável.
- Registrar origem do arquivo: interno, portal, e-mail, WhatsApp ou importação.

### Solicitações documentais

- Implementar criação de solicitação com múltiplos itens.
- Permitir prazo, instruções e cliente vinculado.
- Permitir status por item: solicitado, recebido, em análise, aprovado, recusado, cancelado.
- Implementar envio de documento para item solicitado.
- Implementar aprovação e recusa com motivo obrigatório.
- Reabrir item quando documento for recusado.
- Cancelar solicitação e interromper pendências.

### Segurança e permissões

- Criar policies para documentos, versões e solicitações.
- Respeitar visibilidade: interna, cliente, restrita, sigilosa.
- Registrar visualização e download de documentos sensíveis.
- Garantir que cliente externo só veja documentos explicitamente liberados.
- Mascarar metadados sensíveis conforme permissão.

### Alertas e relatório documental básico

- Implementar consulta de documentos vencidos e próximos do vencimento.
- Preparar job ou query para alertas futuros de validade.
- Implementar relatório/listagem de documentos pendentes e vencidos.

### Testes

- Testar upload com arquivo válido.
- Testar rejeição por tamanho, MIME type e extensão inválida.
- Testar criação de versão e substituição.
- Testar solicitação documental com múltiplos itens.
- Testar aprovação e recusa com motivo.
- Testar controle de acesso por organização, cliente e visibilidade.
- Testar geração de URL temporária ou acesso seguro.
- Testar auditoria de visualização/download quando aplicável.

## Endpoints esperados

- `GET /api/v1/document-categories`
- `POST /api/v1/document-categories`
- `PATCH /api/v1/document-categories/{category}`
- `DELETE /api/v1/document-categories/{category}`
- `GET /api/v1/documents`
- `POST /api/v1/documents`
- `GET /api/v1/documents/{document}`
- `PATCH /api/v1/documents/{document}`
- `POST /api/v1/documents/{document}/versions`
- `GET /api/v1/documents/{document}/view`
- `GET /api/v1/documents/{document}/download`
- `POST /api/v1/document-requests`
- `GET /api/v1/document-requests`
- `GET /api/v1/document-requests/{documentRequest}`
- `POST /api/v1/document-request-items/{item}/upload`
- `PATCH /api/v1/document-request-items/{item}/approve`
- `PATCH /api/v1/document-request-items/{item}/reject`
- `PATCH /api/v1/document-requests/{documentRequest}/cancel`
- `GET /api/v1/clients/{client}/documents`

## Condições de aceite

- Documentos são armazenados em área privada e nunca expostos por caminho público.
- Todo documento possui metadados, categoria, cliente ou recurso vinculado, versão e auditoria mínima.
- Solicitações documentais mostram claramente o que está pendente, recebido, aprovado ou recusado.
- Recusa de documento exige motivo e reabre o item para novo envio.
- Substituição de documento preserva versões anteriores.
- Usuário sem permissão não consegue visualizar, baixar ou alterar documento.
- Cliente externo só acessa documentos e solicitações liberadas para ele.
- Documentos vencidos e próximos do vencimento podem ser consultados.
- Testes cobrem upload, permissões, status, versões e isolamento multi-tenant.

## Fora do escopo

- Portal completo do cliente, além do suporte necessário para envio documental.
- Varredura antivírus real, salvo se já houver ferramenta local definida.
- Integração real com WhatsApp/e-mail para notificar solicitações.
- Assinatura digital.

