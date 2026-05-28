# Casos de Uso — Plataforma de Gestão para Escritórios

## 1. Objetivo

Este documento descreve os casos de uso da plataforma de gestão para escritórios de advocacia, contabilidade, consultorias e serviços profissionais.

Os casos foram derivados do briefing de produto e do documento técnico do projeto. O objetivo é orientar planejamento de sprints, modelagem de domínio, desenho da API, definição de permissões, criação de telas e critérios de aceite.

## 2. Escopo geral

O sistema será uma plataforma SaaS, inicialmente exposta por API Laravel, consumida por aplicação web e aplicativo mobile. O produto centraliza clientes, documentos, tarefas, prazos, comunicação, financeiro, relatórios, equipe, portal do cliente e automações.

A ficha do cliente é o ponto central da experiência. Grande parte dos casos de uso nasce ou termina nela.

## 3. Atores

### 3.1 Administrador da organização

Usuário com controle total sobre a organização. Configura equipe, permissões, módulos, modelos, integrações e dados estruturais.

### 3.2 Gestor do escritório

Dono, sócio ou gerente responsável por acompanhar operação, financeiro, equipe, indicadores, riscos e pendências.

### 3.3 Profissional responsável

Advogado, contador, consultor ou especialista que acompanha clientes, serviços, documentos, tarefas, prazos e atendimentos.

### 3.4 Assistente administrativo

Usuário operacional que cadastra clientes, organiza documentos, cria tarefas, envia solicitações, acompanha pendências e registra atendimentos.

### 3.5 Usuário financeiro

Usuário responsável por contas a receber, contas a pagar, cobranças, pagamentos, inadimplência, categorias financeiras e relatórios financeiros.

### 3.6 Cliente externo

Cliente do escritório. Acessa portal ou aplicativo para enviar documentos, consultar solicitações, cobranças, comunicados e informações liberadas.

### 3.7 Usuário somente leitura

Usuário interno com acesso restrito para consulta de informações autorizadas, sem permissão de alteração.

### 3.8 Sistema

Ator automatizado responsável por disparar notificações, gerar rotinas recorrentes, processar webhooks, calcular status, executar jobs e registrar auditoria.

### 3.9 Provedor externo

Serviços de terceiros integrados, como WhatsApp Business Platform, provedor de e-mail, gateway de pagamento, assinatura digital, armazenamento de arquivos e ferramentas futuras.

## 4. Regras transversais

Estas regras se aplicam a todos os casos de uso:

- Toda ação deve respeitar a organização ativa do usuário.
- O cliente externo só pode acessar dados explicitamente vinculados a ele.
- Dados financeiros só podem ser acessados por usuários autorizados.
- Documentos privados devem ser acessados por mecanismo seguro e temporário.
- Ações sensíveis devem gerar auditoria.
- Listagens devem permitir paginação e filtros quando houver volume.
- Alterações devem validar permissões por papel, vínculo com cliente e sensibilidade do recurso.
- Automações devem ser rastreáveis e idempotentes.
- Mensagens para advocacia devem evitar promessa de resultado, captação indevida e linguagem promocional agressiva.
- Conteúdos gerados por inteligência artificial devem ser tratados como sugestão e revisados por humano quando sensíveis.

## 5. Mapa de módulos e casos de uso

| Módulo | Casos de uso |
| --- | --- |
| Organização e acesso | UC-001 a UC-010 |
| Painel e ficha do cliente | UC-011 a UC-014 |
| Clientes | UC-015 a UC-023 |
| CRM e onboarding | UC-024 a UC-031 |
| Serviços e contratos | UC-032 a UC-037 |
| Documentos | UC-038 a UC-050 |
| Tarefas, prazos e agenda | UC-051 a UC-064 |
| Comunicação | UC-065 a UC-074 |
| Portal do cliente | UC-075 a UC-083 |
| Financeiro | UC-084 a UC-099 |
| Relatórios | UC-100 a UC-108 |
| Equipe e permissões | UC-109 a UC-114 |
| Base de conhecimento | UC-115 a UC-118 |
| Automações | UC-119 a UC-126 |
| Verticais de advocacia | UC-127 a UC-134 |
| Verticais de contabilidade | UC-135 a UC-143 |
| Consultorias e BPO | UC-144 a UC-149 |
| Auditoria, LGPD e segurança | UC-150 a UC-158 |
| Inteligência assistida | UC-159 a UC-165 |

---

# 6. Casos de uso

## UC-001 — Criar organização

**Objetivo:** permitir que um novo escritório seja cadastrado como organização no sistema.

**Atores:** administrador da organização.

**Pré-condições:** o usuário não possui organização ativa ou está iniciando uma nova conta.

**Fluxo principal:**

1. Usuário informa dados básicos da organização.
2. Sistema valida dados obrigatórios.
3. Sistema cria a organização.
4. Sistema vincula o usuário criador como administrador.
5. Sistema define configurações iniciais padrão.
6. Sistema registra auditoria de criação.

**Exceções:** dados inválidos, documento já cadastrado, e-mail já vinculado a outra conta sem autorização.

**Resultado esperado:** organização criada e pronta para configuração inicial.

## UC-002 — Autenticar usuário

**Objetivo:** permitir acesso seguro à plataforma.

**Atores:** usuário interno, cliente externo.

**Pré-condições:** usuário possui credenciais válidas.

**Fluxo principal:**

1. Usuário informa e-mail e senha.
2. Sistema valida credenciais.
3. Sistema aplica rate limit e políticas de segurança.
4. Sistema cria sessão ou token de acesso.
5. Sistema apresenta organizações disponíveis, quando houver mais de uma.
6. Sistema registra evento de login.

**Exceções:** credenciais inválidas, conta bloqueada, usuário inativo, excesso de tentativas.

**Resultado esperado:** usuário autenticado com permissões compatíveis.

## UC-003 — Selecionar organização ativa

**Objetivo:** permitir que usuário vinculado a mais de uma organização escolha o contexto de trabalho.

**Atores:** usuário interno.

**Pré-condições:** usuário autenticado e vinculado a múltiplas organizações.

**Fluxo principal:**

1. Sistema lista organizações acessíveis.
2. Usuário seleciona uma organização.
3. Sistema valida vínculo ativo.
4. Sistema define organização ativa na sessão/token.
5. Sistema carrega permissões e configurações do contexto.

**Exceções:** vínculo inativo, organização suspensa, usuário sem permissão.

**Resultado esperado:** todas as operações seguintes ficam escopadas à organização ativa.

## UC-004 — Recuperar senha

**Objetivo:** permitir que usuário recupere acesso à conta.

**Atores:** usuário interno, cliente externo.

**Pré-condições:** e-mail cadastrado.

**Fluxo principal:**

1. Usuário solicita recuperação de senha.
2. Sistema gera token temporário.
3. Sistema envia instruções por e-mail.
4. Usuário informa nova senha.
5. Sistema valida token, força e confirmação da senha.
6. Sistema atualiza credenciais e revoga sessões antigas, quando configurado.

**Exceções:** token expirado, token inválido, e-mail inexistente, senha fraca.

**Resultado esperado:** senha alterada com segurança.

## UC-005 — Convidar usuário interno

**Objetivo:** permitir que administradores adicionem colaboradores à organização.

**Atores:** administrador da organização, gestor.

**Pré-condições:** ator possui permissão para gerenciar equipe.

**Fluxo principal:**

1. Ator informa e-mail, nome, papel e permissões iniciais.
2. Sistema valida se convite é permitido pelo plano.
3. Sistema cria convite com expiração.
4. Sistema envia convite por e-mail.
5. Sistema registra auditoria.

**Exceções:** e-mail inválido, usuário já vinculado, limite do plano atingido.

**Resultado esperado:** convite enviado e pendente de aceite.

## UC-006 — Aceitar convite

**Objetivo:** permitir que colaborador aceite participação em uma organização.

**Atores:** usuário convidado.

**Pré-condições:** convite válido e não expirado.

**Fluxo principal:**

1. Usuário acessa link de convite.
2. Sistema valida token.
3. Usuário cria conta ou autentica conta existente.
4. Sistema vincula usuário à organização.
5. Sistema aplica papel e permissões definidos no convite.
6. Sistema registra aceite.

**Exceções:** convite expirado, convite revogado, conta bloqueada.

**Resultado esperado:** usuário passa a integrar a equipe.

## UC-007 — Revogar acesso de usuário

**Objetivo:** remover ou suspender acesso de colaborador.

**Atores:** administrador da organização.

**Pré-condições:** usuário alvo pertence à organização.

**Fluxo principal:**

1. Administrador seleciona usuário.
2. Sistema exibe impactos de revogação.
3. Administrador confirma suspensão ou remoção.
4. Sistema revoga sessões e tokens vinculados.
5. Sistema preserva histórico das ações realizadas pelo usuário.
6. Sistema registra auditoria.

**Exceções:** tentativa de remover último administrador, falta de permissão.

**Resultado esperado:** usuário deixa de acessar a organização.

## UC-008 — Gerenciar dispositivos e sessões

**Objetivo:** permitir controle sobre sessões e tokens ativos.

**Atores:** usuário autenticado, administrador.

**Pré-condições:** usuário possui sessões ou tokens ativos.

**Fluxo principal:**

1. Usuário acessa lista de sessões/dispositivos.
2. Sistema exibe dispositivos, datas e localização aproximada quando disponível.
3. Usuário revoga uma sessão específica ou todas as demais.
4. Sistema invalida tokens correspondentes.
5. Sistema registra evento de segurança.

**Exceções:** sessão atual não pode ser revogada sem novo login, token inexistente.

**Resultado esperado:** sessões indesejadas são encerradas.

## UC-009 — Configurar organização

**Objetivo:** manter dados e preferências globais do escritório.

**Atores:** administrador da organização.

**Pré-condições:** organização ativa.

**Fluxo principal:**

1. Administrador edita dados cadastrais, contatos, endereço, fuso horário e preferências.
2. Sistema valida campos.
3. Sistema salva alterações.
4. Sistema aplica configurações nos módulos dependentes.
5. Sistema registra auditoria.

**Exceções:** dados inválidos, documento duplicado, restrição de plano.

**Resultado esperado:** organização configurada conforme operação real.

## UC-010 — Gerenciar plano e módulos contratados

**Objetivo:** controlar recursos disponíveis por plano comercial.

**Atores:** administrador da organização, sistema.

**Pré-condições:** organização cadastrada.

**Fluxo principal:**

1. Administrador visualiza plano atual.
2. Sistema apresenta limites e módulos ativos.
3. Administrador solicita alteração de plano ou módulo adicional.
4. Sistema valida disponibilidade e impacto.
5. Sistema atualiza permissões de uso.
6. Sistema registra histórico de alteração.

**Exceções:** pagamento pendente, módulo incompatível, limite excedido.

**Resultado esperado:** recursos do sistema refletem o plano contratado.

## UC-011 — Visualizar painel inicial

**Objetivo:** apresentar visão executiva e operacional do escritório.

**Atores:** gestor, profissional responsável, assistente, financeiro.

**Pré-condições:** usuário autenticado e organização ativa.

**Fluxo principal:**

1. Usuário acessa painel inicial.
2. Sistema carrega indicadores permitidos para o papel.
3. Sistema exibe tarefas, prazos, documentos, cobranças, mensagens e alertas.
4. Usuário filtra por período, responsável ou área.
5. Sistema atualiza indicadores.

**Exceções:** usuário sem acesso a dados financeiros não vê indicadores financeiros.

**Resultado esperado:** usuário identifica rapidamente prioridades do dia, semana e mês.

## UC-012 — Visualizar alertas críticos

**Objetivo:** destacar riscos operacionais, financeiros e documentais.

**Atores:** gestor, profissional responsável, assistente, financeiro.

**Pré-condições:** existem eventos críticos ou próximos do vencimento.

**Fluxo principal:**

1. Sistema calcula alertas conforme regras configuradas.
2. Usuário visualiza alertas no painel.
3. Usuário abre o alerta.
4. Sistema direciona para cliente, documento, tarefa, prazo ou cobrança vinculada.

**Exceções:** alerta sem permissão de visualização é ocultado.

**Resultado esperado:** pendências críticas recebem atenção prioritária.

## UC-013 — Visualizar ficha completa do cliente

**Objetivo:** centralizar a situação atual do cliente em uma única visão.

**Atores:** gestor, profissional responsável, assistente, financeiro, usuário somente leitura.

**Pré-condições:** cliente cadastrado e usuário autorizado.

**Fluxo principal:**

1. Usuário abre a ficha do cliente.
2. Sistema apresenta dados cadastrais e responsáveis.
3. Sistema apresenta documentos, tarefas, prazos, serviços, contratos, comunicações, financeiro e alertas conforme permissão.
4. Usuário navega por abas ou seções.
5. Sistema mantém histórico e indicadores do cliente.

**Exceções:** dados sensíveis são ocultados conforme permissão.

**Resultado esperado:** usuário entende a situação do cliente sem consultar ferramentas externas.

## UC-014 — Visualizar linha do tempo do cliente

**Objetivo:** exibir histórico cronológico de eventos relevantes do cliente.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** cliente possui eventos registrados.

**Fluxo principal:**

1. Usuário acessa linha do tempo.
2. Sistema lista eventos em ordem cronológica.
3. Usuário filtra por tipo: documentos, tarefas, mensagens, financeiro, prazos ou alterações.
4. Sistema exibe detalhes do evento autorizado.

**Exceções:** eventos sigilosos são omitidos ou mascarados.

**Resultado esperado:** histórico do relacionamento fica rastreável e consultável.

## UC-015 — Cadastrar cliente pessoa física

**Objetivo:** registrar cliente individual.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** usuário possui permissão para cadastrar clientes.

**Fluxo principal:**

1. Usuário informa nome, CPF, contatos, endereço, responsável, status e observações.
2. Sistema valida campos obrigatórios e CPF.
3. Sistema verifica duplicidade dentro da organização.
4. Sistema cria cliente pessoa física.
5. Sistema registra auditoria.

**Exceções:** CPF inválido, cliente duplicado, ausência de responsável.

**Resultado esperado:** cliente pessoa física disponível na carteira do escritório.

## UC-016 — Cadastrar cliente pessoa jurídica

**Objetivo:** registrar empresa cliente.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** usuário possui permissão para cadastrar clientes.

**Fluxo principal:**

1. Usuário informa razão social, nome fantasia, CNPJ, regime tributário, CNAE, endereço e contatos.
2. Usuário define responsáveis internos e contatos do cliente.
3. Sistema valida campos e CNPJ.
4. Sistema verifica duplicidade.
5. Sistema cria cliente pessoa jurídica.
6. Sistema registra auditoria.

**Exceções:** CNPJ inválido, cliente duplicado, falta de responsável.

**Resultado esperado:** cliente PJ cadastrado com informações operacionais.

## UC-017 — Editar cadastro de cliente

**Objetivo:** manter dados cadastrais atualizados.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** cliente existente e usuário autorizado.

**Fluxo principal:**

1. Usuário abre cadastro do cliente.
2. Usuário altera dados permitidos.
3. Sistema valida alterações.
4. Sistema salva nova versão dos dados.
5. Sistema registra auditoria com campos alterados.

**Exceções:** usuário sem permissão para campo sensível, documento duplicado.

**Resultado esperado:** cadastro atualizado com rastreabilidade.

## UC-018 — Classificar cliente por status, prioridade e risco

**Objetivo:** permitir gestão ativa da carteira.

**Atores:** gestor, profissional responsável.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário acessa ficha do cliente.
2. Usuário altera status, prioridade, risco ou potencial de receita.
3. Sistema valida opções permitidas.
4. Sistema salva classificação.
5. Sistema atualiza painéis e relatórios.

**Exceções:** usuário sem permissão gerencial.

**Resultado esperado:** cliente passa a aparecer corretamente em filtros, alertas e indicadores.

## UC-019 — Adicionar contatos do cliente

**Objetivo:** registrar contatos financeiros, operacionais e alternativos.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário adiciona contato com nome, cargo, e-mail, telefone, WhatsApp e tipo.
2. Sistema valida dados.
3. Sistema salva contato vinculado ao cliente.
4. Sistema permite definir contato principal por finalidade.

**Exceções:** contato duplicado, dados inválidos.

**Resultado esperado:** equipe sabe com quem falar para cada assunto.

## UC-020 — Atribuir responsáveis internos ao cliente

**Objetivo:** evitar abandono de demandas e organizar carteira.

**Atores:** gestor, administrador.

**Pré-condições:** cliente e usuários internos cadastrados.

**Fluxo principal:**

1. Gestor seleciona cliente.
2. Gestor define responsável principal e responsáveis auxiliares.
3. Sistema valida vínculos ativos.
4. Sistema atualiza permissões e visibilidade.
5. Sistema registra alteração.

**Exceções:** usuário inativo, tentativa de deixar cliente sem responsável.

**Resultado esperado:** cliente possui responsáveis claros.

## UC-021 — Aplicar etiquetas e categorias a clientes

**Objetivo:** facilitar segmentação e filtros.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário cria ou seleciona etiquetas.
2. Sistema associa etiquetas ao cliente.
3. Usuário filtra carteira por etiquetas.
4. Sistema retorna clientes compatíveis.

**Exceções:** etiqueta duplicada, permissão insuficiente para criar etiqueta global.

**Resultado esperado:** carteira organizada por critérios úteis ao escritório.

## UC-022 — Consultar e filtrar clientes

**Objetivo:** localizar clientes rapidamente.

**Atores:** usuários internos autorizados.

**Pré-condições:** existem clientes cadastrados.

**Fluxo principal:**

1. Usuário acessa listagem de clientes.
2. Usuário busca por nome, documento, status, responsável, etiqueta ou tipo.
3. Sistema retorna resultados paginados.
4. Usuário abre ficha do cliente desejado.

**Exceções:** usuário só vê clientes permitidos.

**Resultado esperado:** clientes são localizados com rapidez e segurança.

## UC-023 — Inativar ou encerrar cliente

**Objetivo:** retirar cliente da operação ativa preservando histórico.

**Atores:** gestor, administrador.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário seleciona cliente.
2. Usuário informa motivo de inativação ou encerramento.
3. Sistema verifica pendências abertas.
4. Sistema solicita confirmação quando houver documentos, cobranças ou tarefas pendentes.
5. Sistema altera status e preserva histórico.
6. Sistema registra auditoria.

**Exceções:** cliente com obrigação ativa bloqueante, usuário sem permissão.

**Resultado esperado:** cliente deixa de aparecer como ativo, sem perda de histórico.

## UC-024 — Cadastrar lead

**Objetivo:** registrar oportunidade comercial.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** módulo CRM habilitado.

**Fluxo principal:**

1. Usuário informa nome, contato, origem, interesse e responsável.
2. Sistema valida dados.
3. Sistema cria lead na primeira etapa do funil.
4. Sistema agenda follow-up opcional.

**Exceções:** lead duplicado, dados insuficientes.

**Resultado esperado:** oportunidade comercial passa a ser acompanhada.

## UC-025 — Mover lead no funil comercial

**Objetivo:** acompanhar evolução de oportunidade.

**Atores:** gestor, profissional responsável.

**Pré-condições:** lead cadastrado.

**Fluxo principal:**

1. Usuário seleciona lead.
2. Usuário altera etapa do funil.
3. Sistema valida transição.
4. Sistema registra histórico de mudança.
5. Sistema atualiza indicadores comerciais.

**Exceções:** transição inválida, lead perdido sem motivo informado.

**Resultado esperado:** funil comercial reflete situação real da negociação.

## UC-026 — Registrar contato comercial

**Objetivo:** manter histórico de interações com lead.

**Atores:** profissional responsável, assistente.

**Pré-condições:** lead cadastrado.

**Fluxo principal:**

1. Usuário registra ligação, reunião, WhatsApp, e-mail ou observação.
2. Sistema vincula registro ao lead.
3. Usuário agenda próximo follow-up, se necessário.
4. Sistema salva histórico.

**Exceções:** usuário sem acesso ao lead.

**Resultado esperado:** negociação deixa de depender de memória individual.

## UC-027 — Criar proposta comercial

**Objetivo:** formalizar oferta de serviço ao lead ou cliente.

**Atores:** gestor, profissional responsável.

**Pré-condições:** lead ou cliente cadastrado.

**Fluxo principal:**

1. Usuário seleciona interessado.
2. Usuário informa serviço, escopo, valor, recorrência e validade.
3. Sistema gera proposta com status em elaboração.
4. Usuário revisa e marca como enviada.
5. Sistema registra histórico.

**Exceções:** dados comerciais incompletos, usuário sem permissão.

**Resultado esperado:** proposta controlada e rastreável.

## UC-028 — Registrar aceite ou recusa de proposta

**Objetivo:** concluir etapa comercial.

**Atores:** gestor, profissional responsável.

**Pré-condições:** proposta enviada.

**Fluxo principal:**

1. Usuário abre proposta.
2. Usuário marca como aceita ou recusada.
3. Se recusada, sistema exige motivo de perda.
4. Se aceita, sistema permite converter lead em cliente.
5. Sistema atualiza indicadores.

**Exceções:** proposta expirada, usuário sem permissão.

**Resultado esperado:** resultado comercial registrado corretamente.

## UC-029 — Converter lead em cliente

**Objetivo:** transformar oportunidade aceita em cliente ativo.

**Atores:** gestor, profissional responsável.

**Pré-condições:** lead qualificado ou proposta aceita.

**Fluxo principal:**

1. Usuário solicita conversão.
2. Sistema reaproveita dados do lead.
3. Usuário completa dados obrigatórios de cliente.
4. Sistema cria cliente e preserva histórico comercial.
5. Sistema permite iniciar onboarding.

**Exceções:** dados obrigatórios ausentes, cliente duplicado.

**Resultado esperado:** lead convertido sem perda de histórico.

## UC-030 — Executar onboarding de cliente

**Objetivo:** padronizar entrada de novos clientes.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** cliente ativo recém-criado ou contrato fechado.

**Fluxo principal:**

1. Usuário escolhe tipo de serviço ou modelo de onboarding.
2. Sistema cria checklist inicial.
3. Sistema cria tarefas iniciais.
4. Sistema solicita documentos obrigatórios.
5. Sistema agenda reunião de boas-vindas, se configurado.
6. Sistema cria cobrança inicial, se aplicável.

**Exceções:** modelo inexistente, documentos obrigatórios não configurados.

**Resultado esperado:** entrada do cliente segue processo padrão do escritório.

## UC-031 — Registrar expectativas e canais oficiais do cliente

**Objetivo:** alinhar comunicação e escopo operacional desde o início.

**Atores:** profissional responsável, gestor.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário registra expectativas, preferências e canais oficiais.
2. Sistema vincula informações à ficha do cliente.
3. Sistema orienta comunicações futuras conforme preferências.
4. Sistema registra histórico.

**Exceções:** canal sem consentimento, usuário sem permissão.

**Resultado esperado:** equipe conhece acordos iniciais e canais adequados.

## UC-032 — Cadastrar tipo de serviço

**Objetivo:** manter catálogo de serviços oferecidos.

**Atores:** administrador, gestor.

**Pré-condições:** organização ativa.

**Fluxo principal:**

1. Usuário informa nome, descrição, área, categoria e parâmetros padrão.
2. Sistema valida dados.
3. Sistema cria tipo de serviço.
4. Sistema disponibiliza serviço para contratos e onboarding.

**Exceções:** serviço duplicado, campos obrigatórios ausentes.

**Resultado esperado:** serviço disponível para contratação.

## UC-033 — Vincular serviço contratado ao cliente

**Objetivo:** registrar o que o cliente contratou.

**Atores:** gestor, profissional responsável.

**Pré-condições:** cliente e tipo de serviço cadastrados.

**Fluxo principal:**

1. Usuário seleciona cliente e tipo de serviço.
2. Usuário define responsável, valor, recorrência, início e status.
3. Sistema valida dados.
4. Sistema cria serviço contratado.
5. Sistema pode disparar onboarding ou tarefas padrão.

**Exceções:** cliente inativo, serviço indisponível.

**Resultado esperado:** serviço aparece na ficha e em relatórios.

## UC-034 — Criar contrato

**Objetivo:** formalizar relação comercial e operacional.

**Atores:** gestor, profissional responsável.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário informa escopo, valor, forma de pagamento, vigência, reajuste e cancelamento.
2. Sistema vincula contrato ao cliente e serviços.
3. Usuário anexa documento assinado ou minuta.
4. Sistema salva contrato e metadados.
5. Sistema registra auditoria.

**Exceções:** arquivo inválido, dados obrigatórios ausentes.

**Resultado esperado:** contrato fica disponível na ficha do cliente.

## UC-035 — Controlar renovação de contrato

**Objetivo:** evitar vencimento ou renovação sem acompanhamento.

**Atores:** gestor, profissional responsável, sistema.

**Pré-condições:** contrato possui data de término ou regra de renovação.

**Fluxo principal:**

1. Sistema identifica contrato próximo do vencimento.
2. Sistema gera alerta para responsável.
3. Usuário revisa contrato.
4. Usuário renova, encerra ou marca para negociação.
5. Sistema atualiza status e histórico.

**Exceções:** contrato sem responsável, usuário sem permissão.

**Resultado esperado:** contratos são acompanhados antes do vencimento.

## UC-036 — Registrar limites de escopo do serviço

**Objetivo:** reduzir conflitos sobre o que está incluso.

**Atores:** gestor, profissional responsável.

**Pré-condições:** serviço contratado existente.

**Fluxo principal:**

1. Usuário registra itens inclusos, não inclusos e limites.
2. Sistema vincula escopo ao serviço ou contrato.
3. Sistema exibe escopo na ficha do cliente.
4. Sistema permite consulta durante chamados e cobranças.

**Exceções:** usuário sem permissão.

**Resultado esperado:** equipe possui referência clara de escopo contratado.

## UC-037 — Encerrar serviço contratado

**Objetivo:** finalizar serviço sem remover histórico.

**Atores:** gestor, profissional responsável.

**Pré-condições:** serviço ativo.

**Fluxo principal:**

1. Usuário solicita encerramento.
2. Sistema exibe tarefas, documentos e cobranças vinculadas.
3. Usuário informa data e motivo.
4. Sistema altera status do serviço.
5. Sistema preserva histórico.

**Exceções:** pendências bloqueantes, usuário sem permissão.

**Resultado esperado:** serviço deixa de gerar rotinas ativas.

## UC-038 — Cadastrar categoria de documento

**Objetivo:** padronizar organização documental.

**Atores:** administrador, gestor.

**Pré-condições:** organização ativa.

**Fluxo principal:**

1. Usuário cria categoria com nome, descrição, validade padrão e sensibilidade.
2. Sistema valida duplicidade.
3. Sistema salva categoria.
4. Sistema disponibiliza categoria para upload e solicitações.

**Exceções:** categoria duplicada.

**Resultado esperado:** documentos passam a ser classificados de forma consistente.

## UC-039 — Enviar documento interno

**Objetivo:** permitir que equipe anexe documento ao cliente, serviço, contrato ou tarefa.

**Atores:** profissional responsável, assistente, gestor.

**Pré-condições:** usuário autorizado e recurso vinculado existente.

**Fluxo principal:**

1. Usuário seleciona arquivo.
2. Usuário informa categoria, descrição, validade e visibilidade.
3. Sistema valida tipo, tamanho e segurança do arquivo.
4. Sistema armazena arquivo em área privada.
5. Sistema cria registro documental e versão inicial.
6. Sistema registra auditoria.

**Exceções:** arquivo inválido, limite excedido, falha no armazenamento.

**Resultado esperado:** documento fica disponível conforme permissões.

## UC-040 — Solicitar documentos ao cliente

**Objetivo:** organizar pedidos documentais ao cliente.

**Atores:** profissional responsável, assistente, gestor.

**Pré-condições:** cliente cadastrado e canal de comunicação disponível.

**Fluxo principal:**

1. Usuário cria solicitação documental.
2. Usuário adiciona itens necessários, prazo e instruções.
3. Sistema gera solicitação com status pendente.
4. Sistema envia aviso ao cliente por portal, e-mail ou WhatsApp.
5. Sistema registra solicitação na ficha do cliente.

**Exceções:** cliente sem contato válido, canal sem consentimento, documentos sem descrição.

**Resultado esperado:** cliente recebe pedido claro e rastreável.

## UC-041 — Enviar documento pelo cliente

**Objetivo:** permitir envio organizado de documentos solicitados.

**Atores:** cliente externo.

**Pré-condições:** solicitação ativa e cliente autenticado ou com link seguro.

**Fluxo principal:**

1. Cliente acessa solicitação.
2. Cliente seleciona item pendente.
3. Cliente envia arquivo.
4. Sistema valida arquivo.
5. Sistema registra envio como recebido.
6. Sistema notifica responsável interno.

**Exceções:** link expirado, arquivo inválido, solicitação encerrada.

**Resultado esperado:** documento é recebido sem se perder em conversas.

## UC-042 — Aprovar documento recebido

**Objetivo:** confirmar que documento atende ao solicitado.

**Atores:** profissional responsável, assistente.

**Pré-condições:** documento recebido e aguardando conferência.

**Fluxo principal:**

1. Usuário abre documento recebido.
2. Usuário confere conteúdo.
3. Usuário marca como aprovado.
4. Sistema atualiza status.
5. Sistema notifica cliente, se configurado.
6. Sistema registra auditoria.

**Exceções:** usuário sem permissão, documento inacessível.

**Resultado esperado:** pendência documental é concluída.

## UC-043 — Recusar documento recebido

**Objetivo:** solicitar correção ou reenvio de documento inadequado.

**Atores:** profissional responsável, assistente.

**Pré-condições:** documento recebido.

**Fluxo principal:**

1. Usuário abre documento.
2. Usuário informa motivo da recusa.
3. Sistema altera status para recusado.
4. Sistema reabre item da solicitação.
5. Sistema notifica cliente com instrução.
6. Sistema registra auditoria.

**Exceções:** motivo ausente, solicitação encerrada.

**Resultado esperado:** cliente sabe o que precisa corrigir.

## UC-044 — Substituir documento e manter versões

**Objetivo:** preservar histórico documental.

**Atores:** profissional responsável, assistente, cliente externo quando permitido.

**Pré-condições:** documento existente.

**Fluxo principal:**

1. Usuário solicita substituição.
2. Sistema recebe novo arquivo.
3. Sistema cria nova versão.
4. Sistema marca versão anterior como substituída.
5. Sistema mantém histórico acessível conforme permissão.

**Exceções:** arquivo inválido, usuário sem permissão.

**Resultado esperado:** documento atualizado sem perda da versão anterior.

## UC-045 — Controlar validade de documento

**Objetivo:** acompanhar vencimento de documentos sensíveis.

**Atores:** sistema, profissional responsável, assistente.

**Pré-condições:** documento possui data de validade.

**Fluxo principal:**

1. Sistema monitora datas de validade.
2. Sistema identifica documentos próximos do vencimento.
3. Sistema gera alerta para responsável.
4. Usuário solicita renovação ou atualiza documento.
5. Sistema atualiza status.

**Exceções:** documento sem responsável, cliente inativo.

**Resultado esperado:** documentos vencidos ou a vencer são controlados.

## UC-046 — Visualizar documento

**Objetivo:** permitir acesso seguro a documento.

**Atores:** usuários autorizados, cliente externo autorizado.

**Pré-condições:** documento existe e ator tem permissão.

**Fluxo principal:**

1. Usuário solicita visualização.
2. Sistema valida permissão e contexto.
3. Sistema gera acesso temporário ou stream seguro.
4. Sistema registra visualização.

**Exceções:** permissão negada, documento removido, URL expirada.

**Resultado esperado:** documento é acessado com segurança e rastreabilidade.

## UC-047 — Baixar documento

**Objetivo:** permitir download autorizado.

**Atores:** usuários autorizados, cliente externo autorizado.

**Pré-condições:** permissão de download concedida.

**Fluxo principal:**

1. Usuário solicita download.
2. Sistema valida permissão.
3. Sistema gera URL temporária.
4. Usuário baixa arquivo.
5. Sistema registra auditoria.

**Exceções:** download bloqueado por sensibilidade, URL expirada.

**Resultado esperado:** download ocorre sem expor armazenamento privado.

## UC-048 — Definir visibilidade de documento

**Objetivo:** controlar quem pode ver documento.

**Atores:** gestor, profissional responsável.

**Pré-condições:** documento cadastrado.

**Fluxo principal:**

1. Usuário abre configurações do documento.
2. Usuário define visibilidade: interna, cliente, restrita ou sigilosa.
3. Sistema valida permissão.
4. Sistema aplica regras de acesso.
5. Sistema registra alteração.

**Exceções:** usuário sem permissão para reduzir/aumentar visibilidade.

**Resultado esperado:** documento respeita sigilo adequado.

## UC-049 — Consultar documentos por filtros

**Objetivo:** encontrar documentos rapidamente.

**Atores:** usuários autorizados.

**Pré-condições:** documentos cadastrados.

**Fluxo principal:**

1. Usuário acessa listagem documental.
2. Usuário filtra por cliente, categoria, validade, status, responsável ou texto.
3. Sistema retorna resultados permitidos.
4. Usuário abre documento.

**Exceções:** resultados ocultados por permissão.

**Resultado esperado:** documentos são encontrados sem depender de pastas externas.

## UC-050 — Cancelar solicitação documental

**Objetivo:** encerrar pedido que não é mais necessário.

**Atores:** profissional responsável, assistente, gestor.

**Pré-condições:** solicitação documental aberta.

**Fluxo principal:**

1. Usuário seleciona solicitação.
2. Usuário informa motivo do cancelamento.
3. Sistema altera status para cancelada.
4. Sistema interrompe lembretes automáticos.
5. Sistema registra histórico.

**Exceções:** solicitação já concluída, usuário sem permissão.

**Resultado esperado:** solicitação deixa de gerar pendências.

## UC-051 — Criar tarefa

**Objetivo:** registrar trabalho operacional a executar.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** usuário possui permissão para criar tarefas.

**Fluxo principal:**

1. Usuário informa título, descrição, responsável, prazo, prioridade e vínculos.
2. Sistema valida dados obrigatórios.
3. Sistema cria tarefa com status inicial.
4. Sistema notifica responsável.
5. Sistema registra histórico.

**Exceções:** tarefa sem prazo, responsável inválido.

**Resultado esperado:** demanda passa a ser acompanhada.

## UC-052 — Atribuir ou reatribuir tarefa

**Objetivo:** distribuir trabalho entre colaboradores.

**Atores:** gestor, profissional responsável.

**Pré-condições:** tarefa existente.

**Fluxo principal:**

1. Usuário seleciona tarefa.
2. Usuário altera responsável.
3. Sistema valida se novo responsável tem acesso ao cliente/recurso.
4. Sistema atualiza tarefa.
5. Sistema notifica envolvidos.

**Exceções:** responsável sem acesso, tarefa encerrada.

**Resultado esperado:** tarefa fica sob responsabilidade correta.

## UC-053 — Atualizar status de tarefa

**Objetivo:** refletir andamento real do trabalho.

**Atores:** responsável da tarefa, gestor.

**Pré-condições:** tarefa aberta.

**Fluxo principal:**

1. Usuário abre tarefa.
2. Usuário altera status.
3. Sistema valida transição.
4. Sistema registra data, usuário e comentário opcional.
5. Sistema atualiza painéis.

**Exceções:** tentativa de concluir sem checklist obrigatório, status inválido.

**Resultado esperado:** equipe acompanha andamento da demanda.

## UC-054 — Concluir tarefa

**Objetivo:** finalizar trabalho executado.

**Atores:** responsável da tarefa, gestor.

**Pré-condições:** tarefa em andamento ou revisão.

**Fluxo principal:**

1. Usuário marca tarefa como concluída.
2. Sistema verifica checklist e pendências obrigatórias.
3. Usuário informa observação final, se necessário.
4. Sistema registra conclusão.
5. Sistema atualiza indicadores.

**Exceções:** checklist incompleto, usuário sem permissão.

**Resultado esperado:** tarefa é encerrada com histórico.

## UC-055 — Criar checklist de tarefa

**Objetivo:** padronizar execução de atividades.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** tarefa existente.

**Fluxo principal:**

1. Usuário adiciona itens de checklist.
2. Sistema salva itens em ordem.
3. Responsável marca itens concluídos durante execução.
4. Sistema calcula progresso.

**Exceções:** item vazio, tarefa encerrada.

**Resultado esperado:** tarefa possui etapas claras de execução.

## UC-056 — Criar modelo de tarefa

**Objetivo:** reutilizar atividades recorrentes.

**Atores:** gestor, administrador.

**Pré-condições:** organização ativa.

**Fluxo principal:**

1. Usuário cria modelo com título, prazo relativo, checklist, prioridade e responsável padrão.
2. Sistema valida dados.
3. Sistema salva modelo.
4. Modelo fica disponível para onboarding, serviços e automações.

**Exceções:** modelo duplicado, prazo relativo inválido.

**Resultado esperado:** escritório reduz criação manual repetitiva.

## UC-057 — Criar tarefas a partir de modelo

**Objetivo:** gerar conjunto de tarefas padronizadas.

**Atores:** gestor, profissional responsável, sistema.

**Pré-condições:** modelo de tarefa existente.

**Fluxo principal:**

1. Ator seleciona modelo e contexto.
2. Sistema calcula responsáveis e prazos.
3. Sistema cria tarefas vinculadas.
4. Sistema notifica responsáveis.
5. Sistema registra origem do modelo.

**Exceções:** modelo inativo, responsável padrão indisponível.

**Resultado esperado:** rotina padronizada é criada com poucos cliques.

## UC-058 — Criar prazo importante

**Objetivo:** registrar compromisso temporal crítico.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** usuário autorizado.

**Fluxo principal:**

1. Usuário informa título, tipo, data, horário, cliente, responsável e urgência.
2. Sistema valida campos.
3. Sistema cria prazo.
4. Sistema agenda alertas.
5. Sistema registra histórico.

**Exceções:** data inválida, ausência de responsável.

**Resultado esperado:** prazo passa a ser monitorado.

## UC-059 — Confirmar cumprimento de prazo

**Objetivo:** registrar que prazo foi atendido.

**Atores:** responsável pelo prazo, gestor.

**Pré-condições:** prazo aberto.

**Fluxo principal:**

1. Usuário abre prazo.
2. Usuário marca como cumprido.
3. Sistema solicita confirmação ou evidência, quando configurado.
4. Sistema registra cumprimento.
5. Sistema remove alerta pendente.

**Exceções:** prazo exige revisão, usuário sem permissão.

**Resultado esperado:** prazo fica registrado como cumprido.

## UC-060 — Revisar prazo antes de conclusão

**Objetivo:** permitir controle de qualidade em prazos críticos.

**Atores:** profissional responsável, gestor.

**Pré-condições:** prazo configurado para revisão.

**Fluxo principal:**

1. Responsável solicita revisão.
2. Revisor recebe notificação.
3. Revisor aprova ou solicita ajustes.
4. Sistema registra decisão.
5. Prazo pode ser concluído após aprovação.

**Exceções:** revisor indisponível, prazo vencido.

**Resultado esperado:** prazos críticos possuem validação adicional.

## UC-061 — Visualizar agenda

**Objetivo:** centralizar compromissos, reuniões, tarefas e prazos.

**Atores:** usuários internos autorizados.

**Pré-condições:** existem eventos ou prazos cadastrados.

**Fluxo principal:**

1. Usuário acessa agenda.
2. Sistema exibe visão diária, semanal ou mensal.
3. Usuário filtra por responsável, cliente ou tipo.
4. Sistema exibe detalhes autorizados.

**Exceções:** eventos sigilosos ocultos.

**Resultado esperado:** agenda operacional fica unificada.

## UC-062 — Agendar reunião

**Objetivo:** registrar compromisso com cliente ou equipe.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** cliente ou participantes definidos.

**Fluxo principal:**

1. Usuário informa título, data, local/link, participantes e pauta.
2. Sistema cria evento.
3. Sistema notifica participantes.
4. Sistema permite confirmação de presença.

**Exceções:** conflito de agenda, participante sem contato.

**Resultado esperado:** reunião fica registrada e vinculada ao cliente quando aplicável.

## UC-063 — Registrar ata ou resumo de reunião

**Objetivo:** transformar reunião em histórico e próximos passos.

**Atores:** profissional responsável, assistente.

**Pré-condições:** reunião realizada ou em andamento.

**Fluxo principal:**

1. Usuário abre evento.
2. Usuário registra resumo, decisões e pendências.
3. Sistema permite criar tarefas derivadas.
4. Sistema vincula resumo à ficha do cliente.

**Exceções:** usuário sem acesso ao cliente.

**Resultado esperado:** reunião gera histórico operacional.

## UC-064 — Receber lembretes de tarefa, prazo e agenda

**Objetivo:** reduzir esquecimentos.

**Atores:** sistema, usuários internos.

**Pré-condições:** tarefas, prazos ou eventos com lembretes configurados.

**Fluxo principal:**

1. Sistema identifica lembrete a disparar.
2. Sistema valida destinatários.
3. Sistema envia notificação.
4. Sistema registra envio.

**Exceções:** canal indisponível, usuário inativo.

**Resultado esperado:** responsáveis são avisados no momento certo.

## UC-065 — Criar modelo de mensagem

**Objetivo:** padronizar comunicação com clientes.

**Atores:** administrador, gestor.

**Pré-condições:** organização ativa.

**Fluxo principal:**

1. Usuário informa nome, canal, finalidade, conteúdo e variáveis.
2. Sistema valida conteúdo e variáveis.
3. Sistema salva modelo.
4. Modelo fica disponível para envio manual ou automações.

**Exceções:** variável inválida, modelo com linguagem bloqueada por política.

**Resultado esperado:** equipe usa comunicação consistente.

## UC-066 — Enviar mensagem individual ao cliente

**Objetivo:** registrar comunicação relevante com cliente.

**Atores:** profissional responsável, assistente, gestor.

**Pré-condições:** cliente possui canal válido e consentimento quando necessário.

**Fluxo principal:**

1. Usuário seleciona cliente e canal.
2. Usuário escreve mensagem ou escolhe modelo.
3. Sistema valida permissão e consentimento.
4. Sistema envia ou registra mensagem.
5. Sistema vincula comunicação ao histórico do cliente.

**Exceções:** canal indisponível, cliente sem consentimento, falha do provedor.

**Resultado esperado:** mensagem enviada e rastreada.

## UC-067 — Enviar mensagem em lote

**Objetivo:** comunicar grupos de clientes sobre assuntos comuns.

**Atores:** gestor, administrador.

**Pré-condições:** usuários e clientes autorizados para comunicação em lote.

**Fluxo principal:**

1. Usuário seleciona público por filtros.
2. Usuário escolhe canal e modelo.
3. Sistema valida consentimentos e restrições.
4. Usuário revisa lista final.
5. Sistema envia mensagens em fila.
6. Sistema registra resultados.

**Exceções:** limite de envio excedido, clientes sem consentimento, conteúdo não permitido.

**Resultado esperado:** comunicação em lote ocorre com controle e registro.

## UC-068 — Registrar comunicação recebida

**Objetivo:** centralizar mensagens vindas de clientes.

**Atores:** sistema, assistente, profissional responsável.

**Pré-condições:** canal integrado ou registro manual permitido.

**Fluxo principal:**

1. Sistema recebe mensagem externa ou usuário registra manualmente.
2. Sistema identifica cliente relacionado.
3. Sistema salva mensagem no histórico.
4. Sistema notifica responsável.
5. Sistema permite converter mensagem em tarefa, chamado ou documento.

**Exceções:** cliente não identificado, anexo inválido.

**Resultado esperado:** comunicação não fica perdida em canais externos.

## UC-069 — Vincular mensagem a recurso

**Objetivo:** conectar comunicação a tarefa, documento, cobrança, serviço ou chamado.

**Atores:** usuários internos autorizados.

**Pré-condições:** mensagem e recurso existem.

**Fluxo principal:**

1. Usuário abre mensagem.
2. Usuário escolhe recurso de vínculo.
3. Sistema valida permissões.
4. Sistema cria associação.
5. Sistema exibe mensagem também no contexto do recurso.

**Exceções:** recurso de outra organização, permissão insuficiente.

**Resultado esperado:** histórico fica contextualizado.

## UC-070 — Controlar consentimento de comunicação

**Objetivo:** respeitar preferências e bases legais de contato.

**Atores:** gestor, assistente, cliente externo.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário registra ou atualiza preferências de comunicação.
2. Sistema salva canais permitidos e restrições.
3. Sistema bloqueia envios incompatíveis.
4. Sistema registra histórico de consentimento.

**Exceções:** tentativa de envio sem consentimento exigido.

**Resultado esperado:** comunicação respeita preferências e LGPD.

## UC-071 — Acompanhar status de envio

**Objetivo:** dar visibilidade sobre entrega de mensagens.

**Atores:** usuários internos autorizados.

**Pré-condições:** mensagem enviada por canal integrado.

**Fluxo principal:**

1. Sistema recebe eventos do provedor.
2. Sistema atualiza status: enviado, entregue, lido, falhou.
3. Usuário visualiza status no histórico.
4. Sistema permite reenvio quando apropriado.

**Exceções:** provedor não suporta leitura, webhook inválido.

**Resultado esperado:** equipe sabe se a comunicação chegou ao cliente.

## UC-072 — Gerenciar notificações internas

**Objetivo:** organizar avisos dentro do sistema.

**Atores:** usuários internos.

**Pré-condições:** usuário autenticado.

**Fluxo principal:**

1. Sistema gera notificação por evento.
2. Usuário visualiza lista de notificações.
3. Usuário marca como lida ou acessa recurso relacionado.
4. Sistema atualiza status.

**Exceções:** recurso relacionado sem permissão.

**Resultado esperado:** usuários acompanham eventos relevantes.

## UC-073 — Criar chamado a partir de mensagem

**Objetivo:** transformar solicitação informal em demanda controlada.

**Atores:** assistente, profissional responsável.

**Pré-condições:** mensagem recebida ou registrada.

**Fluxo principal:**

1. Usuário seleciona mensagem.
2. Usuário cria chamado com assunto, prioridade, responsável e prazo.
3. Sistema vincula mensagem original.
4. Sistema registra chamado na ficha do cliente.

**Exceções:** mensagem sem cliente identificado.

**Resultado esperado:** solicitação passa a ter responsável e status.

## UC-074 — Revisar mensagem sensível antes de envio

**Objetivo:** reduzir risco ético e regulatório.

**Atores:** profissional responsável, gestor.

**Pré-condições:** mensagem classificada como sensível ou regra de revisão ativa.

**Fluxo principal:**

1. Usuário cria mensagem.
2. Sistema identifica necessidade de revisão.
3. Revisor aprova, edita ou rejeita.
4. Sistema envia apenas após aprovação.
5. Sistema registra decisão.

**Exceções:** revisor não disponível, mensagem urgente sem permissão de exceção.

**Resultado esperado:** comunicações sensíveis passam por controle adequado.

## UC-075 — Acessar portal do cliente

**Objetivo:** permitir que cliente consulte informações liberadas.

**Atores:** cliente externo.

**Pré-condições:** cliente possui acesso ativo.

**Fluxo principal:**

1. Cliente autentica no portal.
2. Sistema valida vínculo com cliente.
3. Sistema exibe painel simples com documentos, solicitações, cobranças, comunicados e chamados permitidos.

**Exceções:** acesso revogado, cliente inativo.

**Resultado esperado:** cliente acompanha pendências sem depender apenas do WhatsApp.

## UC-076 — Visualizar documentos solicitados no portal

**Objetivo:** mostrar ao cliente o que precisa enviar.

**Atores:** cliente externo.

**Pré-condições:** existem solicitações documentais abertas.

**Fluxo principal:**

1. Cliente acessa área de documentos.
2. Sistema lista solicitações, itens, prazos e status.
3. Cliente abre instruções de cada item.
4. Cliente envia documento ou consulta motivo de recusa.

**Exceções:** solicitação cancelada ou expirada.

**Resultado esperado:** cliente entende claramente o que falta.

## UC-077 — Consultar cobranças no portal

**Objetivo:** permitir acompanhamento financeiro pelo cliente.

**Atores:** cliente externo.

**Pré-condições:** cobrança visível ao cliente.

**Fluxo principal:**

1. Cliente acessa área financeira.
2. Sistema lista cobranças abertas, vencidas e pagas.
3. Cliente abre detalhe da cobrança.
4. Sistema exibe boleto, Pix, recibo ou instruções, conforme integração.

**Exceções:** cobrança interna não visível, pagamento indisponível.

**Resultado esperado:** cliente consulta e paga cobranças com clareza.

## UC-078 — Abrir solicitação pelo portal

**Objetivo:** permitir que cliente registre demanda estruturada.

**Atores:** cliente externo.

**Pré-condições:** portal ativo para o cliente.

**Fluxo principal:**

1. Cliente escolhe tipo de solicitação.
2. Cliente informa assunto, descrição e anexos.
3. Sistema valida dados.
4. Sistema cria chamado.
5. Sistema notifica equipe responsável.

**Exceções:** anexos inválidos, tipo de solicitação indisponível.

**Resultado esperado:** demanda entra no fluxo de atendimento.

## UC-079 — Acompanhar status de solicitação pelo portal

**Objetivo:** dar transparência ao cliente.

**Atores:** cliente externo.

**Pré-condições:** chamado ou solicitação existente.

**Fluxo principal:**

1. Cliente acessa lista de solicitações.
2. Sistema exibe status, responsável público, mensagens e prazos visíveis.
3. Cliente envia complemento, quando permitido.
4. Sistema notifica equipe.

**Exceções:** solicitação interna não visível ao cliente.

**Resultado esperado:** cliente acompanha andamento sem perguntar repetidamente.

## UC-080 — Confirmar reunião pelo portal

**Objetivo:** facilitar confirmação de compromissos.

**Atores:** cliente externo.

**Pré-condições:** reunião com confirmação habilitada.

**Fluxo principal:**

1. Cliente acessa convite ou portal.
2. Sistema exibe dados da reunião.
3. Cliente confirma, recusa ou solicita remarcação.
4. Sistema atualiza evento e notifica equipe.

**Exceções:** reunião cancelada, prazo de confirmação expirado.

**Resultado esperado:** equipe recebe confirmação formal.

## UC-081 — Consultar comunicados no portal

**Objetivo:** disponibilizar avisos importantes ao cliente.

**Atores:** cliente externo.

**Pré-condições:** comunicado publicado para o cliente.

**Fluxo principal:**

1. Cliente acessa área de comunicados.
2. Sistema lista comunicados.
3. Cliente abre comunicado.
4. Sistema registra leitura quando aplicável.

**Exceções:** comunicado expirado ou removido.

**Resultado esperado:** cliente acessa informações relevantes.

## UC-082 — Atualizar dados cadastrais pelo portal

**Objetivo:** permitir que cliente mantenha dados atualizados.

**Atores:** cliente externo.

**Pré-condições:** edição habilitada.

**Fluxo principal:**

1. Cliente edita dados permitidos.
2. Sistema valida alterações.
3. Sistema salva como atualização direta ou solicitação de revisão.
4. Sistema notifica equipe quando houver revisão.

**Exceções:** campo bloqueado, dados inválidos.

**Resultado esperado:** cadastro fica mais atualizado com controle interno.

## UC-083 — Baixar relatório liberado ao cliente

**Objetivo:** entregar valor e transparência ao cliente.

**Atores:** cliente externo.

**Pré-condições:** relatório publicado e liberado.

**Fluxo principal:**

1. Cliente acessa relatórios.
2. Sistema lista relatórios disponíveis.
3. Cliente visualiza ou baixa relatório.
4. Sistema registra acesso.

**Exceções:** relatório expirado, permissão revogada.

**Resultado esperado:** cliente acessa entregáveis do escritório.

## UC-084 — Criar conta a receber

**Objetivo:** registrar cobrança futura ou imediata.

**Atores:** financeiro, gestor.

**Pré-condições:** cliente cadastrado e usuário autorizado.

**Fluxo principal:**

1. Usuário informa cliente, descrição, valor, vencimento, categoria e serviço vinculado.
2. Sistema valida dados financeiros.
3. Sistema cria cobrança com status aberto.
4. Sistema atualiza ficha financeira do cliente.
5. Sistema registra auditoria.

**Exceções:** valor inválido, cliente inativo, usuário sem permissão.

**Resultado esperado:** cobrança passa a compor contas a receber.

## UC-085 — Criar cobrança recorrente

**Objetivo:** automatizar mensalidades, honorários e serviços recorrentes.

**Atores:** financeiro, gestor.

**Pré-condições:** cliente ou contrato com recorrência.

**Fluxo principal:**

1. Usuário informa valor, periodicidade, início, fim e regra de vencimento.
2. Sistema valida recorrência.
3. Sistema cria configuração recorrente.
4. Sistema gera cobranças conforme agenda.
5. Sistema evita duplicidade por período.

**Exceções:** recorrência inválida, contrato encerrado.

**Resultado esperado:** cobranças recorrentes são geradas sem trabalho manual repetitivo.

## UC-086 — Registrar pagamento

**Objetivo:** baixar cobrança recebida.

**Atores:** financeiro, gestor, sistema via provedor.

**Pré-condições:** cobrança aberta ou parcialmente paga.

**Fluxo principal:**

1. Ator informa data, valor, método e comprovante, ou sistema recebe webhook.
2. Sistema valida valor e status.
3. Sistema registra pagamento.
4. Sistema atualiza status da cobrança.
5. Sistema registra auditoria.

**Exceções:** pagamento duplicado, valor divergente, webhook inválido.

**Resultado esperado:** recebimento fica refletido no financeiro.

## UC-087 — Registrar pagamento parcial

**Objetivo:** controlar recebimento incompleto.

**Atores:** financeiro, gestor.

**Pré-condições:** cobrança aberta.

**Fluxo principal:**

1. Usuário informa valor pago.
2. Sistema compara com valor devido.
3. Sistema registra pagamento parcial.
4. Sistema mantém saldo em aberto.
5. Sistema atualiza status para parcialmente pago.

**Exceções:** valor maior que saldo sem regra de crédito, usuário sem permissão.

**Resultado esperado:** saldo restante continua controlado.

## UC-088 — Cancelar cobrança

**Objetivo:** remover cobrança indevida sem apagar histórico.

**Atores:** financeiro, gestor.

**Pré-condições:** cobrança não liquidada ou regra permite cancelamento.

**Fluxo principal:**

1. Usuário seleciona cobrança.
2. Usuário informa motivo.
3. Sistema valida status.
4. Sistema cancela cobrança.
5. Sistema registra auditoria.

**Exceções:** cobrança paga, cobrança em disputa, usuário sem permissão.

**Resultado esperado:** cobrança deixa de ser exigível e permanece no histórico.

## UC-089 — Renegociar cobrança

**Objetivo:** registrar acordo financeiro.

**Atores:** financeiro, gestor.

**Pré-condições:** cobrança em aberto ou vencida.

**Fluxo principal:**

1. Usuário seleciona cobrança.
2. Usuário informa nova condição, vencimentos ou parcelas.
3. Sistema marca cobrança original como renegociada.
4. Sistema cria novas cobranças conforme acordo.
5. Sistema registra histórico.

**Exceções:** cobrança sem permissão de renegociação.

**Resultado esperado:** acordo financeiro fica formalizado e rastreável.

## UC-090 — Registrar conta a pagar

**Objetivo:** controlar despesas do escritório ou vinculadas a clientes.

**Atores:** financeiro, gestor.

**Pré-condições:** usuário autorizado.

**Fluxo principal:**

1. Usuário informa descrição, categoria, valor, vencimento, fornecedor e cliente vinculado, se houver.
2. Sistema valida dados.
3. Sistema cria conta a pagar.
4. Sistema atualiza previsão de caixa.

**Exceções:** valor inválido, categoria ausente.

**Resultado esperado:** despesa passa a ser acompanhada.

## UC-091 — Registrar pagamento de despesa

**Objetivo:** baixar conta a pagar.

**Atores:** financeiro, gestor.

**Pré-condições:** conta a pagar aberta.

**Fluxo principal:**

1. Usuário informa pagamento da despesa.
2. Sistema valida valor e data.
3. Sistema atualiza status.
4. Sistema registra comprovante, se anexado.
5. Sistema registra auditoria.

**Exceções:** despesa já paga, valor inválido.

**Resultado esperado:** despesa paga reflete no fluxo de caixa.

## UC-092 — Controlar despesas por cliente

**Objetivo:** medir custo e rentabilidade por cliente/caso.

**Atores:** financeiro, gestor, profissional responsável.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário lança despesa vinculada ao cliente.
2. Sistema classifica como reembolsável ou interna.
3. Sistema atualiza financeiro do cliente.
4. Sistema permite gerar cobrança de reembolso, quando aplicável.

**Exceções:** usuário sem acesso financeiro.

**Resultado esperado:** rentabilidade do cliente considera despesas relacionadas.

## UC-093 — Gerar cobrança por provedor de pagamento

**Objetivo:** emitir boleto, Pix ou link de pagamento.

**Atores:** financeiro, sistema, provedor externo.

**Pré-condições:** integração de pagamento configurada.

**Fluxo principal:**

1. Usuário solicita emissão.
2. Sistema envia dados ao provedor.
3. Provedor retorna identificador e dados de pagamento.
4. Sistema salva informações.
5. Sistema disponibiliza cobrança ao cliente.

**Exceções:** falha do provedor, dados fiscais incompletos.

**Resultado esperado:** cobrança pode ser paga por canal integrado.

## UC-094 — Processar webhook de pagamento

**Objetivo:** atualizar financeiro automaticamente.

**Atores:** sistema, provedor externo.

**Pré-condições:** provedor configurado e webhook recebido.

**Fluxo principal:**

1. Sistema recebe webhook.
2. Sistema valida assinatura ou segredo.
3. Sistema verifica idempotência.
4. Sistema processa evento em job.
5. Sistema atualiza cobrança ou pagamento.
6. Sistema registra payload e resultado.

**Exceções:** assinatura inválida, evento duplicado, cobrança não encontrada.

**Resultado esperado:** status financeiro fica sincronizado com provedor.

## UC-095 — Acompanhar inadimplência

**Objetivo:** visualizar clientes e cobranças em atraso.

**Atores:** financeiro, gestor.

**Pré-condições:** existem cobranças vencidas.

**Fluxo principal:**

1. Usuário acessa relatório ou painel de inadimplência.
2. Sistema lista cobranças vencidas por cliente, valor e tempo de atraso.
3. Usuário filtra por responsável, período ou cliente.
4. Usuário envia lembrete ou registra ação de cobrança.

**Exceções:** usuário sem permissão financeira.

**Resultado esperado:** inadimplência é controlada ativamente.

## UC-096 — Enviar lembrete de cobrança

**Objetivo:** reduzir cobrança manual.

**Atores:** financeiro, sistema.

**Pré-condições:** cobrança aberta, a vencer ou vencida.

**Fluxo principal:**

1. Usuário ou automação seleciona cobrança.
2. Sistema escolhe modelo adequado.
3. Sistema valida canal e consentimento.
4. Sistema envia lembrete.
5. Sistema registra comunicação na ficha do cliente.

**Exceções:** cliente sem canal válido, limite de tentativas excedido.

**Resultado esperado:** cliente é avisado de forma profissional.

## UC-097 — Consultar fluxo de caixa

**Objetivo:** visualizar previsão de entradas e saídas.

**Atores:** financeiro, gestor.

**Pré-condições:** contas a receber ou pagar cadastradas.

**Fluxo principal:**

1. Usuário informa período.
2. Sistema calcula entradas previstas, recebidas, despesas e saldo estimado.
3. Sistema exibe visão por dia, semana ou mês.
4. Usuário exporta ou detalha dados.

**Exceções:** usuário sem permissão.

**Resultado esperado:** escritório tem previsibilidade financeira.

## UC-098 — Analisar rentabilidade por cliente

**Objetivo:** identificar clientes mais e menos rentáveis.

**Atores:** gestor, financeiro.

**Pré-condições:** receitas e despesas vinculadas a clientes.

**Fluxo principal:**

1. Usuário seleciona período.
2. Sistema calcula receitas, despesas e estimativa de margem por cliente.
3. Sistema ordena clientes por rentabilidade.
4. Usuário acessa detalhe.

**Exceções:** dados insuficientes, permissão negada.

**Resultado esperado:** gestor toma decisões com base em rentabilidade.

## UC-099 — Categorizar receitas e despesas

**Objetivo:** padronizar relatórios financeiros.

**Atores:** financeiro, administrador.

**Pré-condições:** organização ativa.

**Fluxo principal:**

1. Usuário cria categorias e centros de custo.
2. Sistema valida duplicidade.
3. Usuário aplica categorias em lançamentos.
4. Sistema consolida relatórios por categoria.

**Exceções:** categoria em uso não pode ser removida sem substituição.

**Resultado esperado:** financeiro fica classificável e analisável.

## UC-100 — Gerar relatório de visão geral do escritório

**Objetivo:** consolidar indicadores executivos.

**Atores:** gestor.

**Pré-condições:** dados operacionais existentes.

**Fluxo principal:**

1. Gestor seleciona período.
2. Sistema consolida indicadores de clientes, tarefas, documentos, financeiro e equipe.
3. Sistema apresenta gráficos e tabelas.
4. Gestor detalha indicador específico.

**Exceções:** dados financeiros ocultados se usuário não autorizado.

**Resultado esperado:** gestor entende situação geral do escritório.

## UC-101 — Gerar relatório de produtividade

**Objetivo:** acompanhar execução da equipe sem foco em vigilância excessiva.

**Atores:** gestor.

**Pré-condições:** tarefas e chamados registrados.

**Fluxo principal:**

1. Gestor seleciona período e equipe.
2. Sistema calcula tarefas concluídas, atrasadas, tempo médio e carga atual.
3. Sistema exibe indicadores por colaborador.
4. Gestor identifica gargalos.

**Exceções:** dados insuficientes.

**Resultado esperado:** distribuição de trabalho pode ser ajustada.

## UC-102 — Gerar relatório de documentos pendentes e vencidos

**Objetivo:** controlar risco documental.

**Atores:** gestor, profissional responsável, assistente.

**Pré-condições:** documentos e solicitações cadastrados.

**Fluxo principal:**

1. Usuário seleciona filtros.
2. Sistema lista documentos pendentes, vencidos e próximos do vencimento.
3. Usuário acessa cliente ou dispara solicitação de renovação.

**Exceções:** documentos ocultos por permissão.

**Resultado esperado:** pendências documentais são tratadas de forma ativa.

## UC-103 — Gerar relatório financeiro

**Objetivo:** acompanhar faturamento, recebimentos, despesas e inadimplência.

**Atores:** financeiro, gestor.

**Pré-condições:** dados financeiros cadastrados.

**Fluxo principal:**

1. Usuário seleciona período e filtros.
2. Sistema consolida receitas, despesas, inadimplência e previsão.
3. Usuário detalha por cliente, serviço ou categoria.
4. Sistema permite exportação, se autorizado.

**Exceções:** usuário sem permissão financeira.

**Resultado esperado:** financeiro do escritório fica visível e analisável.

## UC-104 — Gerar relatório comercial

**Objetivo:** medir origem e conversão de oportunidades.

**Atores:** gestor.

**Pré-condições:** CRM em uso.

**Fluxo principal:**

1. Gestor seleciona período.
2. Sistema calcula leads, propostas, conversão, ticket médio e motivos de perda.
3. Sistema agrupa por origem e responsável.

**Exceções:** módulo CRM desabilitado.

**Resultado esperado:** escritório entende canais comerciais mais efetivos.

## UC-105 — Gerar relatório mensal para cliente

**Objetivo:** demonstrar valor entregue em serviços recorrentes.

**Atores:** profissional responsável, gestor.

**Pré-condições:** cliente possui atividades no período.

**Fluxo principal:**

1. Usuário seleciona cliente e período.
2. Sistema reúne tarefas, documentos, obrigações, comunicações e entregas.
3. Usuário revisa conteúdo.
4. Sistema gera relatório.
5. Usuário libera ao cliente ou envia por canal configurado.

**Exceções:** dados sensíveis não liberados, relatório sem revisão obrigatória.

**Resultado esperado:** cliente recebe resumo profissional das atividades.

## UC-106 — Exportar relatório

**Objetivo:** permitir uso externo controlado dos dados.

**Atores:** usuários autorizados.

**Pré-condições:** relatório gerado e permissão de exportação.

**Fluxo principal:**

1. Usuário solicita exportação.
2. Sistema valida permissão.
3. Sistema gera arquivo em formato permitido.
4. Sistema disponibiliza download seguro.
5. Sistema registra auditoria.

**Exceções:** relatório grande processado em fila, permissão negada.

**Resultado esperado:** dados são exportados com controle.

## UC-107 — Salvar filtros de relatório

**Objetivo:** agilizar consultas recorrentes.

**Atores:** gestor, financeiro, profissional responsável.

**Pré-condições:** relatório com filtros disponíveis.

**Fluxo principal:**

1. Usuário configura filtros.
2. Usuário salva visão.
3. Sistema armazena filtros por usuário ou organização.
4. Usuário reutiliza visão posteriormente.

**Exceções:** nome duplicado, filtros inválidos.

**Resultado esperado:** consultas frequentes ficam acessíveis rapidamente.

## UC-108 — Agendar envio de relatório

**Objetivo:** automatizar entrega periódica de relatórios.

**Atores:** gestor, sistema.

**Pré-condições:** relatório e destinatários configurados.

**Fluxo principal:**

1. Usuário define relatório, periodicidade e destinatários.
2. Sistema valida permissões e canais.
3. Sistema agenda geração.
4. Na data definida, sistema gera e envia relatório.
5. Sistema registra envio.

**Exceções:** destinatário sem permissão, falha de geração.

**Resultado esperado:** relatórios recorrentes são entregues sem trabalho manual.

## UC-109 — Cadastrar colaborador

**Objetivo:** manter equipe da organização estruturada.

**Atores:** administrador, gestor.

**Pré-condições:** usuário convidado ou criado.

**Fluxo principal:**

1. Usuário informa dados do colaborador, função e departamento.
2. Sistema vincula usuário à organização.
3. Sistema define papel e permissões.
4. Sistema disponibiliza colaborador para atribuições.

**Exceções:** limite de usuários do plano, e-mail duplicado.

**Resultado esperado:** colaborador pode receber clientes, tarefas e agenda.

## UC-110 — Definir papéis e permissões

**Objetivo:** controlar acesso por perfil.

**Atores:** administrador.

**Pré-condições:** organização ativa.

**Fluxo principal:**

1. Administrador seleciona papel ou usuário.
2. Sistema exibe permissões disponíveis.
3. Administrador concede ou remove permissões.
4. Sistema valida regras mínimas de segurança.
5. Sistema registra auditoria.

**Exceções:** remoção de permissão crítica do último administrador.

**Resultado esperado:** acessos refletem responsabilidades reais.

## UC-111 — Restringir acesso a cliente

**Objetivo:** limitar visualização de clientes específicos.

**Atores:** administrador, gestor.

**Pré-condições:** cliente e usuários cadastrados.

**Fluxo principal:**

1. Usuário abre configurações de acesso do cliente.
2. Usuário define quem pode acessar.
3. Sistema valida responsáveis mínimos.
4. Sistema aplica restrições nas consultas.
5. Sistema registra alteração.

**Exceções:** cliente sem responsável, usuário sem permissão.

**Resultado esperado:** clientes sensíveis ficam protegidos.

## UC-112 — Restringir caso ou documento sigiloso

**Objetivo:** proteger informações sensíveis além do acesso geral ao cliente.

**Atores:** gestor, profissional responsável.

**Pré-condições:** recurso sensível cadastrado.

**Fluxo principal:**

1. Usuário marca recurso como restrito.
2. Usuário define grupo ou usuários autorizados.
3. Sistema aplica controle de acesso específico.
4. Sistema registra alteração.

**Exceções:** usuário tentando restringir recurso sem permissão de gestão.

**Resultado esperado:** sigilo é preservado mesmo dentro da equipe.

## UC-113 — Visualizar carga de trabalho da equipe

**Objetivo:** apoiar distribuição de demandas.

**Atores:** gestor.

**Pré-condições:** tarefas atribuídas a colaboradores.

**Fluxo principal:**

1. Gestor acessa visão de equipe.
2. Sistema mostra tarefas abertas, atrasadas, prazos e agenda por colaborador.
3. Gestor filtra por período e área.
4. Gestor reatribui demandas, se necessário.

**Exceções:** dados de área restrita ocultados.

**Resultado esperado:** gestor identifica sobrecarga e gargalos.

## UC-114 — Consultar agenda individual

**Objetivo:** visualizar compromissos de colaborador.

**Atores:** gestor, colaborador.

**Pré-condições:** colaborador possui eventos ou tarefas.

**Fluxo principal:**

1. Usuário seleciona colaborador.
2. Sistema exibe agenda permitida.
3. Usuário acessa detalhes autorizados.

**Exceções:** eventos privados ou sigilosos ocultados.

**Resultado esperado:** agenda da equipe pode ser coordenada.

## UC-115 — Criar artigo da base de conhecimento

**Objetivo:** documentar procedimentos internos.

**Atores:** gestor, administrador.

**Pré-condições:** módulo habilitado.

**Fluxo principal:**

1. Usuário cria artigo com título, categoria, conteúdo e visibilidade.
2. Sistema salva versão inicial.
3. Sistema disponibiliza artigo para usuários autorizados.

**Exceções:** conteúdo vazio, categoria inválida.

**Resultado esperado:** procedimento interno fica documentado.

## UC-116 — Atualizar artigo e preservar versão

**Objetivo:** manter histórico de procedimentos.

**Atores:** gestor, administrador.

**Pré-condições:** artigo existente.

**Fluxo principal:**

1. Usuário edita artigo.
2. Sistema cria nova versão.
3. Sistema registra autor e data.
4. Sistema publica ou mantém rascunho.

**Exceções:** artigo bloqueado, usuário sem permissão.

**Resultado esperado:** conhecimento interno evolui com rastreabilidade.

## UC-117 — Consultar procedimento interno

**Objetivo:** ajudar equipe a seguir padrões.

**Atores:** usuários internos autorizados.

**Pré-condições:** artigos publicados.

**Fluxo principal:**

1. Usuário busca por termo, categoria ou serviço.
2. Sistema lista artigos autorizados.
3. Usuário abre artigo.
4. Sistema registra leitura, se configurado.

**Exceções:** artigo restrito.

**Resultado esperado:** equipe encontra orientação sem depender de treinamento informal.

## UC-118 — Vincular artigo a serviço, tarefa ou checklist

**Objetivo:** colocar conhecimento no contexto da execução.

**Atores:** gestor, administrador.

**Pré-condições:** artigo e recurso existentes.

**Fluxo principal:**

1. Usuário seleciona artigo.
2. Usuário vincula a serviço, tarefa, modelo ou checklist.
3. Sistema exibe referência durante execução.

**Exceções:** recurso indisponível, permissão negada.

**Resultado esperado:** procedimentos aparecem onde são necessários.

## UC-119 — Configurar automação simples

**Objetivo:** reduzir trabalho manual repetitivo.

**Atores:** administrador, gestor.

**Pré-condições:** automações habilitadas.

**Fluxo principal:**

1. Usuário escolhe gatilho.
2. Usuário define condições e ação.
3. Sistema valida configuração.
4. Sistema ativa automação.
5. Sistema registra regra criada.

**Exceções:** regra inválida, ação sem permissão, conflito com outra regra.

**Resultado esperado:** sistema executa ação automaticamente quando gatilho ocorrer.

## UC-120 — Criar tarefas automaticamente no onboarding

**Objetivo:** padronizar entrada de clientes.

**Atores:** sistema.

**Pré-condições:** cliente novo e modelo configurado.

**Fluxo principal:**

1. Sistema identifica evento de cliente criado ou serviço contratado.
2. Sistema seleciona modelo aplicável.
3. Sistema cria tarefas.
4. Sistema notifica responsáveis.
5. Sistema registra execução da automação.

**Exceções:** modelo ausente, responsável inválido.

**Resultado esperado:** onboarding começa automaticamente.

## UC-121 — Solicitar documentos automaticamente

**Objetivo:** reduzir esquecimento em serviços padronizados.

**Atores:** sistema.

**Pré-condições:** serviço contratado possui documentos obrigatórios.

**Fluxo principal:**

1. Sistema identifica serviço contratado.
2. Sistema cria solicitação documental.
3. Sistema envia aviso ao cliente.
4. Sistema registra execução.

**Exceções:** cliente sem canal válido, documento obrigatório não configurado.

**Resultado esperado:** cliente recebe solicitação no início do serviço.

## UC-122 — Enviar lembrete automático de documento pendente

**Objetivo:** diminuir cobrança manual de documentos.

**Atores:** sistema.

**Pré-condições:** solicitação documental pendente e regra ativa.

**Fluxo principal:**

1. Sistema identifica pendência próxima do prazo ou vencida.
2. Sistema valida limite de lembretes.
3. Sistema envia mensagem ao cliente.
4. Sistema registra comunicação.

**Exceções:** cliente sem consentimento, solicitação cancelada.

**Resultado esperado:** cliente é lembrado sem ação manual da equipe.

## UC-123 — Criar cobranças recorrentes automaticamente

**Objetivo:** gerar receitas recorrentes conforme contrato.

**Atores:** sistema.

**Pré-condições:** recorrência ativa.

**Fluxo principal:**

1. Sistema executa rotina agendada.
2. Sistema identifica cobranças a gerar.
3. Sistema verifica idempotência por período.
4. Sistema cria cobranças.
5. Sistema registra resultado.

**Exceções:** contrato encerrado, cobrança já existente.

**Resultado esperado:** cobranças recorrentes são criadas corretamente.

## UC-124 — Notificar vencimento de documento

**Objetivo:** prevenir documentos vencidos.

**Atores:** sistema.

**Pré-condições:** documento com validade e regra de alerta.

**Fluxo principal:**

1. Sistema identifica documento próximo do vencimento.
2. Sistema notifica responsável interno.
3. Sistema pode criar tarefa ou solicitação de renovação.
4. Sistema registra alerta.

**Exceções:** documento substituído, cliente encerrado.

**Resultado esperado:** equipe age antes do vencimento.

## UC-125 — Notificar gestor sobre cliente de risco

**Objetivo:** alertar sobre clientes com pendências críticas.

**Atores:** sistema, gestor.

**Pré-condições:** regras de risco configuradas.

**Fluxo principal:**

1. Sistema calcula sinais de risco: inadimplência, muitas pendências, tarefas atrasadas ou ausência de contato.
2. Sistema gera alerta.
3. Gestor acessa detalhes.
4. Gestor define ação corretiva.

**Exceções:** dados insuficientes, alerta duplicado.

**Resultado esperado:** riscos são tratados antes de virar cancelamento ou problema operacional.

## UC-126 — Consultar logs de automação

**Objetivo:** dar transparência sobre execuções automáticas.

**Atores:** administrador, gestor.

**Pré-condições:** automações executadas.

**Fluxo principal:**

1. Usuário acessa histórico de automações.
2. Sistema lista regra, gatilho, ação, resultado e erros.
3. Usuário filtra por status ou período.
4. Usuário reprocessa ou corrige configuração, se permitido.

**Exceções:** log expirado por retenção.

**Resultado esperado:** automações são auditáveis.

## UC-127 — Cadastrar caso jurídico

**Objetivo:** organizar demandas jurídicas do cliente.

**Atores:** advogado, gestor, assistente autorizado.

**Pré-condições:** cliente cadastrado e módulo jurídico habilitado.

**Fluxo principal:**

1. Usuário informa área do direito, tipo de demanda, partes, responsável e status.
2. Sistema vincula caso ao cliente.
3. Sistema permite anexar documentos, prazos e honorários.
4. Sistema registra criação.

**Exceções:** caso sigiloso sem responsáveis definidos.

**Resultado esperado:** caso jurídico passa a ser acompanhado.

## UC-128 — Cadastrar processo judicial

**Objetivo:** registrar dados processuais.

**Atores:** advogado, assistente autorizado.

**Pré-condições:** caso jurídico cadastrado.

**Fluxo principal:**

1. Usuário informa número do processo, tribunal, vara, comarca, partes e fase.
2. Sistema valida formato quando aplicável.
3. Sistema vincula processo ao caso e cliente.
4. Sistema registra histórico.

**Exceções:** processo duplicado no cliente, usuário sem permissão.

**Resultado esperado:** processo fica disponível para acompanhamento.

## UC-129 — Registrar movimentação jurídica

**Objetivo:** manter histórico do caso/processo.

**Atores:** advogado, assistente autorizado.

**Pré-condições:** caso ou processo cadastrado.

**Fluxo principal:**

1. Usuário registra movimentação, data, descrição e anexos.
2. Sistema salva histórico.
3. Sistema permite gerar tarefa ou prazo associado.
4. Sistema notifica responsáveis, se configurado.

**Exceções:** caso encerrado, acesso restrito.

**Resultado esperado:** evolução jurídica fica documentada.

## UC-130 — Gerenciar prazo jurídico

**Objetivo:** controlar prazos jurídicos de alta prioridade.

**Atores:** advogado, gestor.

**Pré-condições:** caso ou processo cadastrado.

**Fluxo principal:**

1. Usuário cria prazo jurídico com data, responsável, urgência e revisão.
2. Sistema agenda alertas antecipados.
3. Responsável executa atividade.
4. Revisor aprova, quando necessário.
5. Sistema registra cumprimento.

**Exceções:** prazo vencido, revisão pendente.

**Resultado esperado:** prazos jurídicos são controlados com rigor.

## UC-131 — Controlar audiência

**Objetivo:** acompanhar audiências e compromissos jurídicos.

**Atores:** advogado, assistente, gestor.

**Pré-condições:** caso jurídico cadastrado.

**Fluxo principal:**

1. Usuário agenda audiência com data, local/link e responsáveis.
2. Sistema vincula ao caso e cliente.
3. Sistema envia lembretes.
4. Após audiência, usuário registra resultado.

**Exceções:** conflito de agenda, dados incompletos.

**Resultado esperado:** audiência fica integrada à agenda e ao histórico do caso.

## UC-132 — Controlar honorários advocatícios

**Objetivo:** registrar regras financeiras específicas de advocacia.

**Atores:** advogado, financeiro, gestor.

**Pré-condições:** cliente ou caso cadastrado.

**Fluxo principal:**

1. Usuário informa tipo de honorário: inicial, mensal, parcelado, êxito, misto ou ato.
2. Sistema vincula honorário ao contrato, cliente ou caso.
3. Sistema gera cobranças previstas.
4. Sistema acompanha pagamentos e repasses.

**Exceções:** usuário sem permissão financeira, contrato ausente.

**Resultado esperado:** honorários são controlados de forma adequada.

## UC-133 — Restringir caso jurídico sigiloso

**Objetivo:** proteger informações jurídicas sensíveis.

**Atores:** gestor, advogado responsável.

**Pré-condições:** caso jurídico cadastrado.

**Fluxo principal:**

1. Usuário marca caso como sigiloso.
2. Usuário define usuários autorizados.
3. Sistema restringe documentos, prazos, mensagens e relatórios relacionados.
4. Sistema registra alteração.

**Exceções:** tentativa de remover acesso do responsável principal.

**Resultado esperado:** sigilo profissional é reforçado.

## UC-134 — Validar mensagem jurídica sensível

**Objetivo:** evitar comunicação inadequada na advocacia.

**Atores:** advogado, gestor.

**Pré-condições:** comunicação classificada como jurídica sensível.

**Fluxo principal:**

1. Usuário cria mensagem sobre caso jurídico.
2. Sistema exige revisão ou confirmação.
3. Revisor aprova ou ajusta.
4. Sistema envia e registra.

**Exceções:** conteúdo bloqueado por política interna.

**Resultado esperado:** comunicação jurídica mantém padrão ético.

## UC-135 — Cadastrar perfil contábil do cliente

**Objetivo:** registrar características operacionais do cliente contábil.

**Atores:** contador, assistente, gestor.

**Pré-condições:** cliente PJ cadastrado e módulo contábil habilitado.

**Fluxo principal:**

1. Usuário informa regime tributário, CNAE, funcionários, emissão de nota, certificado e procuração.
2. Sistema valida campos.
3. Sistema salva perfil contábil.
4. Sistema usa perfil para obrigações e checklists.

**Exceções:** cliente não PJ, dados incompletos.

**Resultado esperado:** rotina contábil é adaptada ao perfil do cliente.

## UC-136 — Configurar obrigações mensais

**Objetivo:** padronizar rotina recorrente da contabilidade.

**Atores:** gestor, contador.

**Pré-condições:** perfil contábil ou tipo de serviço configurado.

**Fluxo principal:**

1. Usuário define obrigações por regime, departamento ou cliente.
2. Sistema salva checklist recorrente.
3. Sistema agenda criação mensal.
4. Sistema disponibiliza acompanhamento por competência.

**Exceções:** obrigação duplicada, regra inválida.

**Resultado esperado:** obrigações mensais ficam planejadas.

## UC-137 — Criar competência mensal do cliente contábil

**Objetivo:** controlar entregas por mês.

**Atores:** sistema, contador, assistente.

**Pré-condições:** cliente contábil ativo.

**Fluxo principal:**

1. Sistema identifica início de nova competência.
2. Sistema cria checklist mensal.
3. Sistema gera solicitações documentais.
4. Sistema atribui responsáveis por departamento.
5. Sistema registra competência.

**Exceções:** cliente inativo, obrigação sem responsável.

**Resultado esperado:** mês contábil começa organizado.

## UC-138 — Solicitar documentos mensais

**Objetivo:** pedir documentos recorrentes ao cliente contábil.

**Atores:** sistema, contador, assistente.

**Pré-condições:** competência mensal aberta.

**Fluxo principal:**

1. Sistema ou usuário seleciona lista de documentos.
2. Sistema envia solicitação ao cliente.
3. Cliente envia documentos pelo portal.
4. Equipe confere e aprova.
5. Sistema atualiza status da competência.

**Exceções:** cliente sem canal, documento recusado.

**Resultado esperado:** documentos mensais são recebidos e acompanhados.

## UC-139 — Controlar certificado digital

**Objetivo:** evitar vencimento de certificado.

**Atores:** contador, assistente, sistema.

**Pré-condições:** cliente possui certificado cadastrado.

**Fluxo principal:**

1. Sistema monitora validade.
2. Sistema alerta responsável antes do vencimento.
3. Usuário solicita renovação ao cliente.
4. Usuário atualiza certificado renovado.

**Exceções:** certificado inexistente, cliente inativo.

**Resultado esperado:** vencimentos são tratados preventivamente.

## UC-140 — Controlar procuração eletrônica

**Objetivo:** acompanhar validade e renovação de procurações.

**Atores:** contador, assistente, sistema.

**Pré-condições:** cliente possui procuração cadastrada.

**Fluxo principal:**

1. Sistema monitora validade da procuração.
2. Sistema alerta responsável.
3. Usuário solicita renovação ou registra nova procuração.
4. Sistema atualiza documento e status.

**Exceções:** documento recusado, prazo vencido.

**Resultado esperado:** acesso operacional do escritório permanece válido.

## UC-141 — Gerenciar funcionário de cliente contábil

**Objetivo:** apoiar rotinas de departamento pessoal.

**Atores:** contador, assistente de DP.

**Pré-condições:** cliente contábil ativo.

**Fluxo principal:**

1. Usuário cadastra funcionário do cliente.
2. Usuário registra vínculo, cargo, datas e documentos.
3. Sistema permite acompanhar eventos trabalhistas.

**Exceções:** dados obrigatórios ausentes, acesso restrito.

**Resultado esperado:** informações de DP ficam organizadas.

## UC-142 — Controlar eventos trabalhistas

**Objetivo:** acompanhar admissões, demissões, férias, afastamentos e exames.

**Atores:** assistente de DP, contador.

**Pré-condições:** funcionário de cliente cadastrado.

**Fluxo principal:**

1. Usuário cria evento trabalhista.
2. Sistema define prazos e documentos necessários.
3. Sistema gera tarefas ou solicitações.
4. Sistema acompanha conclusão.

**Exceções:** evento com prazo vencido, documento ausente.

**Resultado esperado:** departamento pessoal trabalha com controle de prazos e documentos.

## UC-143 — Gerar relatório mensal contábil ao cliente

**Objetivo:** demonstrar obrigações cumpridas e pendências.

**Atores:** contador, gestor.

**Pré-condições:** competência mensal com atividades registradas.

**Fluxo principal:**

1. Usuário seleciona cliente e competência.
2. Sistema reúne obrigações, documentos, guias, relatórios e pendências.
3. Usuário revisa relatório.
4. Sistema libera ao cliente.

**Exceções:** relatório incompleto, dados não liberados.

**Resultado esperado:** cliente percebe valor do trabalho contábil.

## UC-144 — Cadastrar diagnóstico de consultoria

**Objetivo:** registrar análise inicial em consultorias.

**Atores:** consultor, gestor.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário cria diagnóstico com áreas avaliadas, problemas e recomendações.
2. Sistema vincula ao cliente.
3. Sistema permite criar plano de ação.

**Exceções:** diagnóstico incompleto.

**Resultado esperado:** consultoria começa com registro estruturado.

## UC-145 — Criar plano de ação

**Objetivo:** organizar execução de consultoria ou projeto.

**Atores:** consultor, gestor.

**Pré-condições:** cliente ou diagnóstico cadastrado.

**Fluxo principal:**

1. Usuário cria etapas, responsáveis, prazos e indicadores.
2. Sistema gera tarefas.
3. Sistema acompanha progresso.
4. Sistema exibe evolução ao cliente quando liberado.

**Exceções:** responsável inválido, prazo ausente.

**Resultado esperado:** projeto consultivo fica operacionalizado.

## UC-146 — Registrar visita técnica ou consultiva

**Objetivo:** documentar atendimentos presenciais ou remotos.

**Atores:** consultor, assistente.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário agenda visita.
2. Após execução, registra observações, evidências e pendências.
3. Sistema gera tarefas e relatório, se necessário.

**Exceções:** anexos inválidos, cliente sem acesso.

**Resultado esperado:** visitas geram histórico e ações.

## UC-147 — Registrar não conformidades

**Objetivo:** controlar problemas encontrados em consultorias.

**Atores:** consultor.

**Pré-condições:** cliente e visita/projeto cadastrados.

**Fluxo principal:**

1. Usuário registra não conformidade, gravidade, evidência e prazo.
2. Sistema cria ação corretiva.
3. Sistema acompanha status.
4. Sistema inclui item em relatório.

**Exceções:** evidência obrigatória ausente.

**Resultado esperado:** problemas são tratados de forma rastreável.

## UC-148 — Controlar contas de cliente em BPO financeiro

**Objetivo:** apoiar operação de BPO financeiro quando aplicável.

**Atores:** usuário financeiro, consultor BPO.

**Pré-condições:** cliente com serviço de BPO ativo.

**Fluxo principal:**

1. Usuário registra contas a pagar ou receber do cliente.
2. Sistema classifica como financeiro do cliente, não do escritório.
3. Sistema permite acompanhamento e relatórios específicos.

**Exceções:** usuário sem permissão para BPO.

**Resultado esperado:** operação financeira do cliente fica separada da financeira do escritório.

## UC-149 — Gerar relatório de evolução de consultoria

**Objetivo:** apresentar progresso do trabalho consultivo.

**Atores:** consultor, gestor.

**Pré-condições:** plano de ação ou projeto com atividades.

**Fluxo principal:**

1. Usuário seleciona período e cliente.
2. Sistema reúne ações concluídas, pendências, indicadores e evidências.
3. Usuário revisa relatório.
4. Sistema libera ao cliente.

**Exceções:** dados incompletos.

**Resultado esperado:** cliente visualiza evolução do projeto.

## UC-150 — Registrar auditoria de ação sensível

**Objetivo:** manter rastreabilidade de operações críticas.

**Atores:** sistema.

**Pré-condições:** ação sensível executada.

**Fluxo principal:**

1. Sistema identifica ação auditável.
2. Sistema registra organização, usuário, recurso, ação, data, IP e alterações.
3. Sistema armazena log de forma consultável.

**Exceções:** falha de registro deve ser tratada como evento crítico para ações de alto risco.

**Resultado esperado:** ação sensível pode ser rastreada.

## UC-151 — Consultar trilha de auditoria

**Objetivo:** permitir investigação e controle interno.

**Atores:** administrador, gestor autorizado.

**Pré-condições:** logs de auditoria existentes.

**Fluxo principal:**

1. Usuário acessa auditoria.
2. Usuário filtra por usuário, recurso, ação, cliente ou período.
3. Sistema exibe registros autorizados.
4. Usuário exporta, se permitido.

**Exceções:** log fora da retenção, permissão negada.

**Resultado esperado:** histórico de ações fica disponível para controle.

## UC-152 — Registrar consentimento LGPD

**Objetivo:** manter histórico de consentimentos e preferências.

**Atores:** gestor, assistente, cliente externo, sistema.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Ator informa consentimento, finalidade, canal e data.
2. Sistema registra versão do termo ou origem.
3. Sistema aplica consentimento nas comunicações.

**Exceções:** consentimento revogado, finalidade incompatível.

**Resultado esperado:** tratamentos baseados em consentimento ficam rastreáveis.

## UC-153 — Revogar consentimento

**Objetivo:** permitir respeito à preferência do titular.

**Atores:** cliente externo, gestor, assistente.

**Pré-condições:** consentimento registrado.

**Fluxo principal:**

1. Ator solicita revogação.
2. Sistema registra data e origem.
3. Sistema bloqueia usos dependentes daquele consentimento.
4. Sistema preserva histórico.

**Exceções:** comunicação obrigatória por contrato ou obrigação legal pode seguir base diferente.

**Resultado esperado:** preferências do cliente são respeitadas.

## UC-154 — Solicitar exportação de dados do cliente

**Objetivo:** atender demanda de portabilidade ou auditoria interna.

**Atores:** administrador, gestor autorizado.

**Pré-condições:** cliente cadastrado.

**Fluxo principal:**

1. Usuário solicita exportação.
2. Sistema valida permissão e escopo.
3. Sistema gera pacote de dados em job.
4. Sistema disponibiliza download seguro.
5. Sistema registra auditoria.

**Exceções:** dados bloqueados por obrigação legal, pacote grande.

**Resultado esperado:** dados são exportados com controle.

## UC-155 — Solicitar anonimização ou exclusão

**Objetivo:** tratar pedidos de privacidade.

**Atores:** administrador, gestor autorizado.

**Pré-condições:** cliente ou titular identificado.

**Fluxo principal:**

1. Usuário registra solicitação.
2. Sistema avalia bloqueios: contratos, financeiro, obrigações legais ou jurídicas.
3. Sistema apresenta dados elegíveis.
4. Usuário confirma anonimização ou exclusão permitida.
5. Sistema executa e registra auditoria.

**Exceções:** retenção obrigatória, dados vinculados a processos ou financeiro.

**Resultado esperado:** solicitação é tratada sem violar obrigações legais.

## UC-156 — Aplicar retenção documental

**Objetivo:** controlar ciclo de vida de documentos.

**Atores:** sistema, administrador.

**Pré-condições:** política de retenção configurada.

**Fluxo principal:**

1. Sistema identifica documentos com retenção expirada.
2. Sistema gera lista para revisão ou executa ação configurada.
3. Administrador aprova descarte, anonimização ou manutenção.
4. Sistema registra decisão.

**Exceções:** documento bloqueado por caso, contrato ou obrigação.

**Resultado esperado:** documentos são mantidos pelo tempo adequado.

## UC-157 — Bloquear usuário por risco de segurança

**Objetivo:** proteger a organização contra acesso indevido.

**Atores:** sistema, administrador.

**Pré-condições:** evento de segurança detectado.

**Fluxo principal:**

1. Sistema identifica tentativas suspeitas ou administrador solicita bloqueio.
2. Sistema bloqueia acesso ou exige redefinição de senha.
3. Sistema revoga sessões.
4. Sistema registra evento.
5. Administrador revisa desbloqueio.

**Exceções:** falso positivo, administrador sem permissão.

**Resultado esperado:** risco de acesso indevido é reduzido.

## UC-158 — Mascarar dados sensíveis por permissão

**Objetivo:** limitar exposição de dados sensíveis.

**Atores:** sistema.

**Pré-condições:** usuário acessa dado sensível com permissão parcial.

**Fluxo principal:**

1. Sistema identifica sensibilidade do campo.
2. Sistema verifica permissão do usuário.
3. Sistema retorna dado completo, mascarado ou oculto.
4. Sistema registra visualização quando necessário.

**Exceções:** dado indispensável para operação autorizada.

**Resultado esperado:** usuários veem apenas o necessário.

## UC-159 — Gerar resumo do histórico do cliente

**Objetivo:** facilitar compreensão rápida da situação do cliente.

**Atores:** profissional responsável, gestor, sistema de IA.

**Pré-condições:** módulo de IA habilitado e dados suficientes.

**Fluxo principal:**

1. Usuário solicita resumo.
2. Sistema reúne eventos permitidos.
3. Sistema envia dados mínimos necessários ao provedor ou motor de IA.
4. Sistema gera rascunho de resumo.
5. Usuário revisa e utiliza.

**Exceções:** IA desabilitada, dados sensíveis bloqueados.

**Resultado esperado:** usuário ganha contexto sem ler todo o histórico.

## UC-160 — Resumir conversa longa

**Objetivo:** extrair pontos principais de comunicação extensa.

**Atores:** profissional responsável, assistente, sistema de IA.

**Pré-condições:** conversa registrada e usuário autorizado.

**Fluxo principal:**

1. Usuário seleciona conversa.
2. Sistema gera resumo com solicitações, decisões e pendências.
3. Usuário revisa.
4. Usuário pode criar tarefa ou chamado a partir do resumo.

**Exceções:** conversa sigilosa sem permissão, IA indisponível.

**Resultado esperado:** comunicação extensa vira informação operacional.

## UC-161 — Sugerir resposta ao cliente

**Objetivo:** ajudar equipe a responder com clareza e padrão.

**Atores:** profissional responsável, assistente, sistema de IA.

**Pré-condições:** mensagem recebida e módulo habilitado.

**Fluxo principal:**

1. Usuário solicita sugestão.
2. Sistema considera contexto permitido e modelo de linguagem.
3. Sistema gera rascunho.
4. Usuário revisa, edita e envia.
5. Sistema registra mensagem final enviada.

**Exceções:** conteúdo jurídico/contábil sensível exige revisão profissional.

**Resultado esperado:** resposta é acelerada, mas permanece sob controle humano.

## UC-162 — Sugerir próximos passos de uma demanda

**Objetivo:** apoiar organização de trabalho.

**Atores:** profissional responsável, gestor, sistema de IA.

**Pré-condições:** demanda, chamado ou caso cadastrado.

**Fluxo principal:**

1. Usuário solicita sugestão.
2. Sistema analisa histórico autorizado.
3. Sistema sugere tarefas, documentos ou prazos.
4. Usuário aceita, edita ou ignora sugestões.

**Exceções:** sugestão não aplicável, dados insuficientes.

**Resultado esperado:** usuário recebe apoio para planejar execução.

## UC-163 — Identificar cliente com risco de cancelamento

**Objetivo:** antecipar problemas de relacionamento.

**Atores:** gestor, sistema.

**Pré-condições:** dados históricos suficientes.

**Fluxo principal:**

1. Sistema avalia sinais como inadimplência, muitas demandas, atrasos e baixa interação.
2. Sistema calcula risco ou gera alerta.
3. Gestor visualiza motivos.
4. Gestor registra ação de retenção.

**Exceções:** cálculo indisponível, dados insuficientes.

**Resultado esperado:** gestor age antes da perda do cliente.

## UC-164 — Gerar rascunho de relatório mensal

**Objetivo:** acelerar produção de relatórios ao cliente.

**Atores:** profissional responsável, gestor, sistema de IA.

**Pré-condições:** atividades registradas no período.

**Fluxo principal:**

1. Usuário solicita rascunho.
2. Sistema reúne dados liberáveis.
3. IA sugere texto e estrutura.
4. Usuário revisa e aprova.
5. Sistema gera versão final.

**Exceções:** relatório sem dados, IA desabilitada.

**Resultado esperado:** relatório é produzido mais rápido sem perder revisão humana.

## UC-165 — Consultar dados gerenciais por pergunta

**Objetivo:** permitir perguntas gerenciais em linguagem natural.

**Atores:** gestor, sistema de IA.

**Pré-condições:** módulo habilitado e usuário com permissão.

**Fluxo principal:**

1. Gestor pergunta sobre indicadores ou dados operacionais.
2. Sistema identifica intenção e escopo permitido.
3. Sistema consulta dados estruturados.
4. Sistema responde com números e explicação.
5. Sistema oferece link para relatório detalhado.

**Exceções:** pergunta fora do escopo, dados financeiros sem permissão, ambiguidade.

**Resultado esperado:** gestor obtém respostas rápidas sem montar filtros complexos.

---

# 7. Priorização recomendada dos casos de uso

## 7.1 MVP

Priorizar:

- UC-001 a UC-009: base de organização e acesso.
- UC-011 a UC-023: painel, ficha e clientes.
- UC-038 a UC-050: documentos e solicitações.
- UC-051 a UC-064: tarefas, prazos e agenda.
- UC-065, UC-066, UC-068, UC-070, UC-072: comunicação básica.
- UC-075 a UC-079: portal simples do cliente.
- UC-084 a UC-089, UC-095 a UC-099: financeiro básico.
- UC-100, UC-102, UC-103, UC-105: relatórios essenciais.
- UC-109 a UC-113: equipe e permissões.
- UC-150, UC-151, UC-158: auditoria e segurança inicial.

## 7.2 Segunda etapa

Priorizar:

- CRM e onboarding completo: UC-024 a UC-031.
- Contratos e serviços: UC-032 a UC-037.
- Comunicação avançada: UC-067, UC-069, UC-071, UC-073, UC-074.
- Relatórios avançados: UC-101, UC-104, UC-106 a UC-108.
- Base de conhecimento: UC-115 a UC-118.
- Automações simples: UC-119 a UC-126.

## 7.3 Terceira etapa

Priorizar:

- Módulo jurídico: UC-127 a UC-134.
- Módulo contábil: UC-135 a UC-143.
- Consultorias e BPO: UC-144 a UC-149.
- LGPD avançada: UC-152 a UC-157.
- Inteligência assistida: UC-159 a UC-165.

# 8. Observações finais

Este documento descreve o escopo funcional amplo do produto. A implementação deve ser incremental, começando pelos casos de uso que sustentam a promessa central: centralizar cliente, documentos, tarefas, prazos, comunicação e financeiro.

Cada sprint deve transformar os casos priorizados em histórias menores, com critérios de aceite, contratos de API, regras de permissão, testes e impactos de auditoria.

