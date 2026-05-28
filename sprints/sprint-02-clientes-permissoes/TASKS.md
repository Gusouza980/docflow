# Sprint 02 — Clientes, Ficha e Permissões Operacionais

## Objetivo

Implementar a base operacional do produto: cadastro de clientes PF/PJ, contatos, responsáveis, etiquetas, status, prioridade, risco, ficha completa inicial, linha do tempo e permissões de acesso a clientes.

## Referências

- `docs/briefing_app_gestao_escritorios.md`
- `docs/documento_tecnico_app_gestao_escritorios.md`
- `docs/casos_de_uso_app_gestao_escritorios.md`
- Casos de uso: UC-011 a UC-023, UC-109 a UC-113, UC-158.

## Escopo funcional

- Visualizar painel inicial em versão básica.
- Visualizar ficha completa do cliente.
- Visualizar linha do tempo inicial do cliente.
- Cadastrar, editar, consultar e inativar cliente PF/PJ.
- Adicionar contatos do cliente.
- Atribuir responsáveis internos.
- Aplicar etiquetas e categorias.
- Controlar acesso de usuários a clientes.
- Visualizar carga de trabalho inicial por responsável.

## Tarefas técnicas

### Modelagem e migrations

- Criar tabela `clients`.
- Criar tabela `client_individual_profiles`.
- Criar tabela `client_company_profiles`.
- Criar tabela `client_contacts`.
- Criar tabela `client_tags`.
- Criar tabela pivô entre clientes e tags.
- Criar tabela de responsáveis internos do cliente, se necessário.
- Criar tabela de restrições de acesso a cliente, se necessário.
- Incluir `organization_id` em todas as tabelas de negócio.
- Adicionar índices para busca por organização, nome, documento, status, responsável, prioridade e risco.
- Usar soft deletes em clientes e contatos.

### Cadastros de clientes

- Implementar criação de cliente pessoa física.
- Implementar criação de cliente pessoa jurídica.
- Validar CPF/CNPJ em camada de request ou regra dedicada.
- Impedir duplicidade de documento dentro da mesma organização.
- Exigir ao menos um responsável interno para cliente ativo.
- Implementar edição com auditoria de alterações relevantes.
- Implementar inativação e encerramento com motivo.

### Contatos, etiquetas e classificação

- Implementar CRUD de contatos do cliente.
- Permitir contato principal por finalidade: geral, financeiro, operacional.
- Implementar criação e associação de etiquetas.
- Implementar alteração de status, prioridade, risco e potencial de receita.
- Garantir que classificações alimentem filtros e painel.

### Ficha do cliente

- Implementar endpoint de detalhe consolidado do cliente.
- Exibir dados cadastrais, contatos, responsáveis, etiquetas, status e alertas básicos.
- Preparar estrutura para futuras abas: documentos, tarefas, financeiro, mensagens e contratos.
- Implementar linha do tempo inicial com eventos de criação, edição, mudança de status e responsáveis.

### Painel inicial básico

- Implementar endpoint de dashboard básico.
- Exibir contagem de clientes ativos, inativos, em onboarding, inadimplentes, alto risco e sem responsável.
- Exibir lista de clientes com pendências estruturais, como ausência de contato principal.
- Respeitar permissões do usuário.

### Permissões operacionais

- Implementar papéis iniciais: administrador, gestor, profissional responsável, assistente, financeiro e somente leitura.
- Criar policies para clientes, contatos e etiquetas.
- Garantir que usuário só veja clientes permitidos.
- Implementar restrição explícita de acesso a cliente.
- Mascarar campos sensíveis quando o papel permitir visão parcial.

### Testes

- Testar criação de cliente PF e PJ.
- Testar validação e duplicidade de CPF/CNPJ por organização.
- Testar obrigação de responsável interno.
- Testar filtros de clientes.
- Testar que usuário de uma organização não vê clientes de outra.
- Testar restrição explícita de acesso ao cliente.
- Testar alteração de status, prioridade e risco.
- Testar auditoria de criação, edição e encerramento.

## Endpoints esperados

- `GET /api/v1/dashboard`
- `GET /api/v1/clients`
- `POST /api/v1/clients`
- `GET /api/v1/clients/{client}`
- `PATCH /api/v1/clients/{client}`
- `PATCH /api/v1/clients/{client}/status`
- `POST /api/v1/clients/{client}/contacts`
- `PATCH /api/v1/client-contacts/{contact}`
- `DELETE /api/v1/client-contacts/{contact}`
- `GET /api/v1/clients/{client}/timeline`
- `POST /api/v1/client-tags`
- `POST /api/v1/clients/{client}/tags`
- `DELETE /api/v1/clients/{client}/tags/{tag}`
- `POST /api/v1/clients/{client}/responsibles`
- `DELETE /api/v1/clients/{client}/responsibles/{member}`
- `PATCH /api/v1/clients/{client}/access`

## Condições de aceite

- O sistema permite cadastrar clientes PF e PJ com validações adequadas.
- Nenhum cliente ativo pode ficar sem responsável interno.
- A listagem de clientes possui busca, filtros e paginação.
- A ficha do cliente mostra dados principais, contatos, responsáveis, tags e status operacional.
- A linha do tempo exibe eventos iniciais do cliente.
- Usuários só visualizam clientes permitidos pela organização e regras de acesso.
- Campos sensíveis são ocultados ou mascarados quando o usuário não possui permissão completa.
- Mudanças importantes geram auditoria.
- Testes cobrem fluxos principais, isolamento multi-tenant e falhas de autorização.

## Fora do escopo

- Upload e solicitação de documentos.
- Tarefas, prazos e agenda.
- Financeiro completo.
- Portal do cliente.
- CRM completo e onboarding automatizado.

