<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\CalendarEventController;
use App\Http\Controllers\Api\V1\ClientContactController;
use App\Http\Controllers\Api\V1\ClientCommunicationController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\ClientTagController;
use App\Http\Controllers\Api\V1\CommunicationConsentController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeadlineController;
use App\Http\Controllers\Api\V1\DocumentCategoryController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\DocumentRequestController;
use App\Http\Controllers\Api\V1\DocumentRequestItemController;
use App\Http\Controllers\Api\V1\MessageTemplateController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\OrganizationInvitationController;
use App\Http\Controllers\Api\V1\OrganizationMemberController;
use App\Http\Controllers\Api\V1\PortalApiController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ReportFilterController;
use App\Http\Controllers\Api\V1\ReportScheduleController;
use App\Http\Controllers\Api\V1\TaskChecklistItemController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskTemplateController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Docflow API",
 *         version="1.0.0",
 *         description="API para gestao SaaS de escritorios, autenticacao, organizacoes, membros e convites."
 *     ),
 *     @OA\Server(
 *         url="/api",
 *         description="Servidor da API"
 *     ),
 *     @OA\Tag(name="Auth", description="Autenticacao e recuperacao de senha"),
 *     @OA\Tag(name="Organizations", description="Organizacoes SaaS"),
 *     @OA\Tag(name="Organization Members", description="Membros da organizacao ativa"),
 *     @OA\Tag(name="Organization Invitations", description="Convites para membros da organizacao"),
 *     @OA\Components(
 *         @OA\SecurityScheme(
 *             securityScheme="sanctum",
 *             type="http",
 *             scheme="bearer",
 *             bearerFormat="Sanctum"
 *         ),
 *         @OA\Parameter(
 *             parameter="ActiveOrganization",
 *             name="X-Organization-Id",
 *             in="header",
 *             required=true,
 *             description="ID da organizacao ativa para rotas escopadas por tenant.",
 *             @OA\Schema(type="integer", example=1)
 *         ),
 *         @OA\Schema(
 *             schema="User",
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Gustavo Silva"),
 *             @OA\Property(property="email", type="string", format="email", example="gustavo@example.com")
 *         ),
 *         @OA\Schema(
 *             schema="Organization",
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Docflow Office"),
 *             @OA\Property(property="document", type="string", nullable=true, example="12345678901234"),
 *             @OA\Property(property="email", type="string", nullable=true, format="email", example="office@example.com"),
 *             @OA\Property(property="phone", type="string", nullable=true, example="+55 11 99999-9999"),
 *             @OA\Property(property="timezone", type="string", example="America/Sao_Paulo"),
 *             @OA\Property(property="status", type="string", example="active"),
 *             @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 *         ),
 *         @OA\Schema(
 *             schema="OrganizationMember",
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="organization_id", type="integer", example=1),
 *             @OA\Property(property="user", ref="#/components/schemas/User"),
 *             @OA\Property(property="role", type="string", example="admin"),
 *             @OA\Property(property="status", type="string", example="active"),
 *             @OA\Property(property="joined_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="suspended_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 *         ),
 *         @OA\Schema(
 *             schema="OrganizationInvitation",
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="organization_id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", nullable=true, example="Maria Souza"),
 *             @OA\Property(property="email", type="string", format="email", example="maria@example.com"),
 *             @OA\Property(property="role", type="string", example="assistant"),
 *             @OA\Property(property="status", type="string", example="pending"),
 *             @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="cancelled_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="created_at", type="string", format="date-time", nullable=true)
 *         ),
 *         @OA\Schema(
 *             schema="ValidationError",
 *             type="object",
 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
 *             @OA\Property(property="errors", type="object")
 *         ),
 *         @OA\Schema(
 *             schema="Error",
 *             type="object",
 *             @OA\Property(property="message", type="string", example="Forbidden.")
 *         )
 *     )
 * )
 *
 * @OA\Post(
 *     path="/v1/auth/login",
 *     tags={"Auth"},
 *     summary="Autenticar usuario",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email","password"},
 *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *             @OA\Property(property="password", type="string", format="password", example="password"),
 *             @OA\Property(property="device_name", type="string", nullable=true, example="iPhone")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Token emitido com sucesso.",
 *         @OA\JsonContent(
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="token", type="string"),
 *                 @OA\Property(property="token_type", type="string", example="Bearer"),
 *                 @OA\Property(property="user", ref="#/components/schemas/User")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=422, description="Credenciais invalidas.", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
 *     @OA\Response(response=429, description="Muitas tentativas.")
 * )
 *
 * @OA\Post(
 *     path="/v1/auth/forgot-password",
 *     tags={"Auth"},
 *     summary="Solicitar recuperacao de senha",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email"},
 *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Solicitacao processada."),
 *     @OA\Response(response=422, description="Dados invalidos.", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
 *     @OA\Response(response=429, description="Muitas tentativas.")
 * )
 *
 * @OA\Post(
 *     path="/v1/auth/reset-password",
 *     tags={"Auth"},
 *     summary="Redefinir senha",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"token","email","password","password_confirmation"},
 *             @OA\Property(property="token", type="string"),
 *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *             @OA\Property(property="password", type="string", format="password", example="new-password"),
 *             @OA\Property(property="password_confirmation", type="string", format="password", example="new-password")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Senha redefinida."),
 *     @OA\Response(response=422, description="Token ou dados invalidos.", @OA\JsonContent(ref="#/components/schemas/ValidationError")),
 *     @OA\Response(response=429, description="Muitas tentativas.")
 * )
 *
 * @OA\Get(
 *     path="/v1/auth/me",
 *     tags={"Auth"},
 *     summary="Consultar usuario autenticado",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=200, description="Usuario autenticado.", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/User"))),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Post(
 *     path="/v1/auth/logout",
 *     tags={"Auth"},
 *     summary="Encerrar token atual",
 *     security={{"sanctum":{}}},
 *     @OA\Response(response=204, description="Logout realizado."),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Get(
 *     path="/v1/organizations",
 *     tags={"Organizations"},
 *     summary="Listar organizacoes acessiveis",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=15)),
 *     @OA\Response(response=200, description="Lista paginada de organizacoes."),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Post(
 *     path="/v1/organizations",
 *     tags={"Organizations"},
 *     summary="Criar organizacao",
 *     security={{"sanctum":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name"},
 *             @OA\Property(property="name", type="string", example="Docflow Office"),
 *             @OA\Property(property="document", type="string", nullable=true, example="12345678901234"),
 *             @OA\Property(property="email", type="string", nullable=true, format="email", example="office@example.com"),
 *             @OA\Property(property="phone", type="string", nullable=true, example="+55 11 99999-9999"),
 *             @OA\Property(property="timezone", type="string", nullable=true, example="America/Sao_Paulo")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Organizacao criada.", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Organization"))),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=422, description="Dados invalidos.", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Patch(
 *     path="/v1/organizations/{organization}",
 *     tags={"Organizations"},
 *     summary="Atualizar organizacao",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(name="organization", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Docflow Office"),
 *             @OA\Property(property="document", type="string", nullable=true, example="12345678901234"),
 *             @OA\Property(property="email", type="string", nullable=true, format="email", example="office@example.com"),
 *             @OA\Property(property="phone", type="string", nullable=true, example="+55 11 99999-9999"),
 *             @OA\Property(property="timezone", type="string", nullable=true, example="America/Sao_Paulo")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Organizacao atualizada.", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Organization"))),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Sem permissao.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=422, description="Dados invalidos.", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Post(
 *     path="/v1/organizations/{organization}/switch",
 *     tags={"Organizations"},
 *     summary="Selecionar organizacao ativa",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(name="organization", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Organizacao selecionada.", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/Organization"))),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Sem acesso a organizacao.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Get(
 *     path="/v1/organization-members",
 *     tags={"Organization Members"},
 *     summary="Listar membros da organizacao ativa",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(ref="#/components/parameters/ActiveOrganization"),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=15)),
 *     @OA\Response(response=200, description="Lista paginada de membros."),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Sem acesso a organizacao.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=422, description="Organizacao ativa ausente.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Patch(
 *     path="/v1/organization-members/{organizationMember}/suspend",
 *     tags={"Organization Members"},
 *     summary="Suspender membro da organizacao",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(ref="#/components/parameters/ActiveOrganization"),
 *     @OA\Parameter(name="organizationMember", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Membro suspenso.", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/OrganizationMember"))),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Sem permissao.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=422, description="Nao foi possivel suspender o membro.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Patch(
 *     path="/v1/organization-members/{organizationMember}/reactivate",
 *     tags={"Organization Members"},
 *     summary="Reativar membro da organizacao",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(ref="#/components/parameters/ActiveOrganization"),
 *     @OA\Parameter(name="organizationMember", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=200, description="Membro reativado.", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/OrganizationMember"))),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Sem permissao.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Get(
 *     path="/v1/organization-invitations",
 *     tags={"Organization Invitations"},
 *     summary="Listar convites da organizacao ativa",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(ref="#/components/parameters/ActiveOrganization"),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=15)),
 *     @OA\Response(response=200, description="Lista paginada de convites."),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Sem acesso a organizacao.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Post(
 *     path="/v1/organization-invitations",
 *     tags={"Organization Invitations"},
 *     summary="Convidar membro para organizacao ativa",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(ref="#/components/parameters/ActiveOrganization"),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"email","role"},
 *             @OA\Property(property="name", type="string", nullable=true, example="Maria Souza"),
 *             @OA\Property(property="email", type="string", format="email", example="maria@example.com"),
 *             @OA\Property(property="role", type="string", enum={"admin","manager","professional","assistant","finance","readonly"}, example="assistant")
 *         )
 *     ),
 *     @OA\Response(response=201, description="Convite criado.", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/OrganizationInvitation"))),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Sem permissao.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=422, description="Dados invalidos.", @OA\JsonContent(ref="#/components/schemas/ValidationError"))
 * )
 *
 * @OA\Post(
 *     path="/v1/organization-invitations/{token}/accept",
 *     tags={"Organization Invitations"},
 *     summary="Aceitar convite de organizacao",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
 *     @OA\Response(response=200, description="Convite aceito.", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/OrganizationMember"))),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Convite pertence a outro e-mail.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=422, description="Convite invalido ou expirado.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 *
 * @OA\Delete(
 *     path="/v1/organization-invitations/{organizationInvitation}",
 *     tags={"Organization Invitations"},
 *     summary="Cancelar convite pendente",
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(ref="#/components/parameters/ActiveOrganization"),
 *     @OA\Parameter(name="organizationInvitation", in="path", required=true, @OA\Schema(type="integer", example=1)),
 *     @OA\Response(response=204, description="Convite cancelado."),
 *     @OA\Response(response=401, description="Nao autenticado.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=403, description="Sem permissao.", @OA\JsonContent(ref="#/components/schemas/Error")),
 *     @OA\Response(response=422, description="Convite nao pode ser cancelado.", @OA\JsonContent(ref="#/components/schemas/Error"))
 * )
 */
Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:login');
    Route::post('/auth/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:login');

    Route::get('/portal/me', [PortalApiController::class, 'me']);
    Route::get('/portal/dashboard', [PortalApiController::class, 'dashboard']);
    Route::get('/portal/document-requests', [PortalApiController::class, 'documentRequests']);
    Route::get('/portal/receivables', [PortalApiController::class, 'receivables']);
    Route::get('/portal/tickets', [PortalApiController::class, 'tickets']);
    Route::post('/portal/tickets', [PortalApiController::class, 'storeTicket']);
    Route::get('/portal/announcements', [PortalApiController::class, 'announcements']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthenticatedSessionController::class, 'show']);
        Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);

        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::post('/organizations', [OrganizationController::class, 'store']);
        Route::patch('/organizations/{organization}', [OrganizationController::class, 'update']);
        Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switch']);

        Route::post('/organization-invitations/{token}/accept', [OrganizationInvitationController::class, 'accept']);

        Route::middleware('active.organization')->group(function (): void {
            Route::get('/dashboard', DashboardController::class);

            Route::get('/organization-members', [OrganizationMemberController::class, 'index']);
            Route::patch('/organization-members/{organizationMember}/suspend', [OrganizationMemberController::class, 'suspend']);
            Route::patch('/organization-members/{organizationMember}/reactivate', [OrganizationMemberController::class, 'reactivate']);

            Route::get('/organization-invitations', [OrganizationInvitationController::class, 'index']);
            Route::post('/organization-invitations', [OrganizationInvitationController::class, 'store']);
            Route::delete('/organization-invitations/{organizationInvitation}', [OrganizationInvitationController::class, 'destroy']);

            Route::get('/clients', [ClientController::class, 'index']);
            Route::post('/clients', [ClientController::class, 'store']);
            Route::get('/clients/{client}', [ClientController::class, 'show']);
            Route::patch('/clients/{client}', [ClientController::class, 'update']);
            Route::patch('/clients/{client}/status', [ClientController::class, 'updateStatus']);
            Route::get('/clients/{client}/timeline', [ClientController::class, 'timeline']);
            Route::post('/clients/{client}/contacts', [ClientContactController::class, 'store']);
            Route::post('/clients/{client}/tags/{tag}', [ClientTagController::class, 'attach']);
            Route::delete('/clients/{client}/tags/{tag}', [ClientTagController::class, 'detach']);
            Route::post('/clients/{client}/responsibles', [ClientController::class, 'addResponsible']);
            Route::delete('/clients/{client}/responsibles/{member}', [ClientController::class, 'removeResponsible']);
            Route::patch('/clients/{client}/access', [ClientController::class, 'updateAccess']);
            Route::get('/clients/{client}/documents', [DocumentController::class, 'clientDocuments']);

            Route::patch('/client-contacts/{contact}', [ClientContactController::class, 'update']);
            Route::delete('/client-contacts/{contact}', [ClientContactController::class, 'destroy']);

            Route::post('/client-tags', [ClientTagController::class, 'store']);

            Route::get('/document-categories', [DocumentCategoryController::class, 'index']);
            Route::post('/document-categories', [DocumentCategoryController::class, 'store']);
            Route::patch('/document-categories/{category}', [DocumentCategoryController::class, 'update']);
            Route::delete('/document-categories/{category}', [DocumentCategoryController::class, 'destroy']);

            Route::get('/documents', [DocumentController::class, 'index']);
            Route::post('/documents', [DocumentController::class, 'store']);
            Route::get('/documents/{document}', [DocumentController::class, 'show']);
            Route::patch('/documents/{document}', [DocumentController::class, 'update']);
            Route::post('/documents/{document}/versions', [DocumentController::class, 'storeVersion']);
            Route::get('/documents/{document}/view', [DocumentController::class, 'view']);
            Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

            Route::get('/document-requests', [DocumentRequestController::class, 'index']);
            Route::post('/document-requests', [DocumentRequestController::class, 'store']);
            Route::get('/document-requests/{documentRequest}', [DocumentRequestController::class, 'show']);
            Route::patch('/document-requests/{documentRequest}/cancel', [DocumentRequestController::class, 'cancel']);
            Route::post('/document-request-items/{item}/upload', [DocumentRequestItemController::class, 'upload']);
            Route::patch('/document-request-items/{item}/approve', [DocumentRequestItemController::class, 'approve']);
            Route::patch('/document-request-items/{item}/reject', [DocumentRequestItemController::class, 'reject']);

            Route::get('/tasks', [TaskController::class, 'index']);
            Route::post('/tasks', [TaskController::class, 'store']);
            Route::get('/tasks/{task}', [TaskController::class, 'show']);
            Route::patch('/tasks/{task}', [TaskController::class, 'update']);
            Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
            Route::patch('/tasks/{task}/assign', [TaskController::class, 'assign']);
            Route::patch('/tasks/{task}/complete', [TaskController::class, 'complete']);
            Route::post('/tasks/{task}/checklist-items', [TaskChecklistItemController::class, 'store']);
            Route::patch('/task-checklist-items/{item}', [TaskChecklistItemController::class, 'update']);
            Route::delete('/task-checklist-items/{item}', [TaskChecklistItemController::class, 'destroy']);

            Route::get('/task-templates', [TaskTemplateController::class, 'index']);
            Route::post('/task-templates', [TaskTemplateController::class, 'store']);
            Route::post('/task-templates/{template}/create-tasks', [TaskTemplateController::class, 'createTasks']);

            Route::get('/deadlines', [DeadlineController::class, 'index']);
            Route::post('/deadlines', [DeadlineController::class, 'store']);
            Route::patch('/deadlines/{deadline}', [DeadlineController::class, 'update']);
            Route::patch('/deadlines/{deadline}/complete', [DeadlineController::class, 'complete']);
            Route::patch('/deadlines/{deadline}/request-review', [DeadlineController::class, 'requestReview']);
            Route::patch('/deadlines/{deadline}/approve-review', [DeadlineController::class, 'approveReview']);

            Route::get('/calendar-events', [CalendarEventController::class, 'index']);
            Route::post('/calendar-events', [CalendarEventController::class, 'store']);
            Route::patch('/calendar-events/{event}', [CalendarEventController::class, 'update']);
            Route::post('/calendar-events/{event}/notes', [CalendarEventController::class, 'notes']);

            Route::get('/message-templates', [MessageTemplateController::class, 'index']);
            Route::post('/message-templates', [MessageTemplateController::class, 'store']);
            Route::patch('/message-templates/{template}', [MessageTemplateController::class, 'update']);
            Route::delete('/message-templates/{template}', [MessageTemplateController::class, 'destroy']);
            Route::post('/messages', [ClientCommunicationController::class, 'store']);
            Route::get('/clients/{client}/messages', [ClientCommunicationController::class, 'index']);
            Route::get('/communication-consents', [CommunicationConsentController::class, 'index']);
            Route::post('/communication-consents', [CommunicationConsentController::class, 'store']);
            Route::patch('/communication-consents/{consent}/revoke', [CommunicationConsentController::class, 'revoke']);
            Route::get('/tickets', [TicketController::class, 'index']);
            Route::post('/tickets', [TicketController::class, 'store']);
            Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
            Route::patch('/tickets/{ticket}', [TicketController::class, 'update']);
            Route::post('/tickets/{ticket}/messages', [TicketController::class, 'storeMessage']);

            Route::get('/reports/overview', [ReportController::class, 'overview']);
            Route::get('/reports/productivity', [ReportController::class, 'productivity']);
            Route::get('/reports/documents', [ReportController::class, 'documents']);
            Route::get('/reports/finance', [ReportController::class, 'finance']);
            Route::get('/reports/clients/{client}/monthly', [ReportController::class, 'monthly']);
            Route::post('/reports/clients/{client}/monthly', [ReportController::class, 'generateMonthly']);
            Route::patch('/reports/{report}/release-to-client', [ReportController::class, 'release']);
            Route::post('/reports/export', [ReportController::class, 'export']);
            Route::get('/report-filters', [ReportFilterController::class, 'index']);
            Route::post('/report-filters', [ReportFilterController::class, 'store']);
            Route::patch('/report-filters/{filter}', [ReportFilterController::class, 'update']);
            Route::delete('/report-filters/{filter}', [ReportFilterController::class, 'destroy']);
            Route::get('/report-schedules', [ReportScheduleController::class, 'index']);
            Route::post('/report-schedules', [ReportScheduleController::class, 'store']);
        });
    });
});
