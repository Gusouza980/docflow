<?php

namespace Database\Seeders\Support;

use App\Automations\AutomationPresets;
use App\Enums\CalendarEventType;
use App\Enums\ClientPriority;
use App\Enums\DocumentSensitivity;
use App\Enums\DocumentVisibility;
use App\Enums\TaskPriority;
use App\Models\AuditLog;
use App\Models\AutomationRule;
use App\Models\CalendarEvent;
use App\Models\CalendarEventParticipant;
use App\Models\Client;
use App\Models\ClientCompanyProfile;
use App\Models\ClientContact;
use App\Models\ClientIndividualProfile;
use App\Models\ClientPortalAccess;
use App\Models\ClientService;
use App\Models\ClientTag;
use App\Models\Contract;
use App\Models\Deadline;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentVersion;
use App\Models\FinancialCategory;
use App\Models\InternalReminder;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\Payable;
use App\Models\Plan;
use App\Models\Proposal;
use App\Models\Receivable;
use App\Models\ReportSchedule;
use App\Models\SavedReportFilter;
use App\Models\ServiceType;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoWorkspaceBuilder
{
    /**
     * @param  array{
     *     key: string,
     *     document: string,
     *     name: string,
     *     email: string,
     *     phone: string,
     *     plan_slug: string,
     *     org_status: string,
     *     subscription_status: string,
     *     trial_ends_at?: CarbonInterface|null,
     *     past_due_at?: CarbonInterface|null,
     *     canceled_at?: CarbonInterface|null,
     *     members: list<array{role: string, name: string, email: string}>,
     *     seed_core?: bool,
     *     seed_invitation?: bool,
     *     invite_token?: string,
     *     seed_portal?: bool,
     *     portal_email?: string,
     *     seed_finance?: bool,
     *     seed_crm?: bool,
     *     seed_services?: bool,
     *     seed_tickets?: bool,
     *     seed_automations?: bool,
     *     seed_reports?: bool,
     *     seed_audit?: bool,
     *     invoice_status?: string|null,
     * }  $definition
     */
    public function seed(array $definition): Organization
    {
        return DB::transaction(function () use ($definition): Organization {
            $organization = $this->organization($definition);
            $subscription = $this->subscription($organization, $definition);
            $this->invoice($organization, $subscription, $definition['invoice_status'] ?? null);

            setPermissionsTeamId($organization->id);

            $members = $this->members($organization, $definition['members']);

            if (! ($definition['seed_core'] ?? false)) {
                return $organization;
            }

            $admin = $members[OrganizationMember::ROLE_ADMIN]->user;
            $professional = $members[OrganizationMember::ROLE_PROFESSIONAL] ?? $members[OrganizationMember::ROLE_ADMIN];
            $manager = $members[OrganizationMember::ROLE_MANAGER] ?? $members[OrganizationMember::ROLE_ADMIN];
            $assistant = $members[OrganizationMember::ROLE_ASSISTANT] ?? $professional;

            $tags = $this->clientTags($organization);
            $categories = $this->documentCategories($organization);
            $templates = $this->taskTemplates($organization);

            $individualClient = $this->individualClient($organization, $professional);
            $companyClient = $this->companyClient($organization, $manager);

            $individualClient->tags()->syncWithoutDetaching([
                $tags['VIP']->id,
                $tags['Imposto de Renda']->id,
            ]);
            $companyClient->tags()->syncWithoutDetaching([
                $tags['Recorrente']->id,
                $tags['Jurídico']->id,
            ]);

            $documents = $this->documents($organization, $admin, $individualClient, $companyClient, $categories);
            $this->documentRequests($organization, $admin, $individualClient, $companyClient, $categories);
            $this->tasks($organization, $admin, $professional, $assistant, $individualClient, $companyClient, $templates);
            $this->deadlines($organization, $admin, $professional, $manager, $individualClient, $companyClient);
            $this->calendarEvents($organization, $admin, $professional, $manager, $assistant, $individualClient, $companyClient);

            if ($definition['seed_invitation'] ?? false) {
                $this->invitation($organization, $admin, $definition['invite_token'] ?? 'demo-invite-token');
            }

            if ($definition['seed_portal'] ?? false) {
                $this->portal(
                    $organization,
                    $admin,
                    $companyClient,
                    $definition['portal_email'] ?? 'portal@novaclinica.example.com',
                );
            }

            if ($definition['seed_finance'] ?? false) {
                $this->finance($organization, $admin, $companyClient);
            }

            if ($definition['seed_crm'] ?? false) {
                $this->crm($organization, $admin);
            }

            if ($definition['seed_services'] ?? false) {
                $this->services($organization, $manager, $companyClient, $definition['key']);
            }

            if ($definition['seed_tickets'] ?? false) {
                $this->tickets($organization, $admin, $professional, $companyClient);
            }

            if ($definition['seed_automations'] ?? false) {
                $this->automations($organization, $templates['Onboarding de cliente']);
            }

            if ($definition['seed_reports'] ?? false) {
                $this->reports($organization, $admin);
            }

            if ($definition['seed_audit'] ?? false) {
                $this->audit($organization, $admin, $documents[0]);
            }

            return $organization;
        });
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function organization(array $definition): Organization
    {
        $planId = Plan::query()->where('slug', $definition['plan_slug'])->value('id');

        return Organization::query()->updateOrCreate(
            ['document' => $definition['document']],
            [
                'name' => $definition['name'],
                'email' => $definition['email'],
                'phone' => $definition['phone'],
                'timezone' => 'America/Sao_Paulo',
                'status' => $definition['org_status'],
                'plan_id' => $planId,
                'settings' => [
                    'currency' => 'BRL',
                    'locale' => 'pt_BR',
                    'document_retention_years' => 5,
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function subscription(Organization $organization, array $definition): Subscription
    {
        $status = $definition['subscription_status'];

        $periodStart = now();
        $periodEnd = now()->addMonth();

        if ($status === Subscription::STATUS_TRIALING) {
            $periodEnd = $definition['trial_ends_at'] ?? now()->addDays(14);
        }

        if ($status === Subscription::STATUS_PAST_DUE) {
            $periodStart = now()->subMonth();
            $periodEnd = now()->subDays(2);
        }

        if ($status === Subscription::STATUS_CANCELED) {
            $periodStart = now()->subMonths(2);
            $periodEnd = now()->subDays(2);
        }

        return Subscription::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'plan_id' => $organization->plan_id,
                'status' => $status,
                'billing_provider' => Subscription::BILLING_PROVIDER_MANUAL,
                'trial_ends_at' => $definition['trial_ends_at'] ?? null,
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'past_due_at' => $definition['past_due_at'] ?? null,
                'canceled_at' => $definition['canceled_at'] ?? null,
                'cancel_at_period_end' => false,
            ],
        );
    }

    private function invoice(Organization $organization, Subscription $subscription, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        $plan = Plan::query()->find($organization->plan_id);
        $periodStart = $subscription->current_period_start ?? now();
        $periodEnd = $subscription->current_period_end ?? now()->addMonth();

        $invoice = SubscriptionInvoice::query()->firstOrNew([
            'subscription_id' => $subscription->id,
        ]);

        $invoice->fill([
            'organization_id' => $organization->id,
            'amount_cents' => $plan?->price_cents ?? 0,
            'currency' => 'BRL',
            'status' => $status,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_at' => $status === SubscriptionInvoice::STATUS_OPEN
                ? now()->subDays(2)
                : $periodEnd,
            'paid_at' => $status === SubscriptionInvoice::STATUS_PAID ? now()->subDays(3) : null,
            'payment_method' => $status === SubscriptionInvoice::STATUS_PAID ? 'manual' : null,
        ]);

        $invoice->save();
    }

    /**
     * @param  list<array{role: string, name: string, email: string}>  $memberDefinitions
     * @return array<string, OrganizationMember>
     */
    private function members(Organization $organization, array $memberDefinitions): array
    {
        $members = [];

        foreach ($memberDefinitions as $memberDefinition) {
            $user = $this->user($memberDefinition['name'], $memberDefinition['email']);
            $member = $this->member($organization, $user, $memberDefinition['role']);

            $roleModel = Role::findOrCreate($memberDefinition['role'], 'web');
            $roleModel->syncPermissions(PermissionSeeder::rolePermissions()[$memberDefinition['role']]);
            $member->user->assignRole($roleModel);
            $member->user->unsetRelation('roles')->unsetRelation('permissions');

            $members[$memberDefinition['role']] = $member;
        }

        return $members;
    }

    private function user(string $name, string $email): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
            ],
        );

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function member(Organization $organization, User $user, string $role): OrganizationMember
    {
        return OrganizationMember::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'status' => OrganizationMember::STATUS_ACTIVE,
                'joined_at' => now()->subDays(30),
                'suspended_at' => null,
            ],
        );
    }

    /**
     * @return array<string, ClientTag>
     */
    private function clientTags(Organization $organization): array
    {
        $tags = [
            'VIP' => '#0f766e',
            'Recorrente' => '#2563eb',
            'Imposto de Renda' => '#9333ea',
            'Jurídico' => '#b45309',
        ];

        return collect($tags)
            ->mapWithKeys(fn (string $color, string $name) => [
                $name => ClientTag::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'name' => $name,
                    ],
                    ['color' => $color],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, DocumentCategory>
     */
    private function documentCategories(Organization $organization): array
    {
        $categories = [
            'Contrato Social' => ['Documento societário principal.', 3650, DocumentSensitivity::Confidential],
            'Procuração' => ['Autorização para representação do cliente.', 365, DocumentSensitivity::Sensitive],
            'Comprovante de Endereço' => ['Comprovante residencial ou comercial atualizado.', 180, DocumentSensitivity::Normal],
            'Documento Fiscal' => ['Notas, guias e comprovantes fiscais.', 1825, DocumentSensitivity::Sensitive],
            'Documento Pessoal' => ['RG, CNH, CPF ou documentos pessoais equivalentes.', 3650, DocumentSensitivity::Confidential],
        ];

        return collect($categories)
            ->mapWithKeys(fn (array $data, string $name) => [
                $name => DocumentCategory::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'name' => $name,
                    ],
                    [
                        'description' => $data[0],
                        'validity_days' => $data[1],
                        'sensitivity' => $data[2],
                        'is_active' => true,
                    ],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, TaskTemplate>
     */
    private function taskTemplates(Organization $organization): array
    {
        $templates = [
            'Onboarding de cliente' => [
                'description' => 'Fluxo padrão para ativação de um novo cliente.',
                'priority' => TaskPriority::High,
                'items' => [
                    ['Coletar documentação inicial', 1, TaskPriority::High, ['Conferir identidade', 'Validar comprovante de endereço']],
                    ['Cadastrar dados financeiros', 3, TaskPriority::Normal, ['Definir dia de vencimento', 'Registrar contato financeiro']],
                    ['Revisar contrato de prestação', 5, TaskPriority::High, ['Enviar minuta', 'Registrar aceite']],
                ],
            ],
            'Fechamento mensal' => [
                'description' => 'Rotina operacional e financeira recorrente.',
                'priority' => TaskPriority::Normal,
                'items' => [
                    ['Solicitar documentos fiscais', 2, TaskPriority::Normal, ['Notas emitidas', 'Extratos bancários']],
                    ['Conferir pendências financeiras', 4, TaskPriority::High, ['Mensalidade', 'Reembolsos']],
                ],
            ],
        ];

        return collect($templates)
            ->mapWithKeys(function (array $data, string $name) use ($organization) {
                $template = TaskTemplate::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'name' => $name,
                    ],
                    [
                        'description' => $data['description'],
                        'priority' => $data['priority'],
                        'is_active' => true,
                    ],
                );

                foreach ($data['items'] as $item) {
                    TaskTemplateItem::query()->updateOrCreate(
                        [
                            'organization_id' => $organization->id,
                            'task_template_id' => $template->id,
                            'title' => $item[0],
                        ],
                        [
                            'description' => null,
                            'due_in_days' => $item[1],
                            'priority' => $item[2],
                            'checklist_items' => $item[3],
                        ],
                    );
                }

                return [$name => $template];
            })
            ->all();
    }

    private function individualClient(Organization $organization, OrganizationMember $responsible): Client
    {
        $client = Client::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'document_number' => '12345678901',
            ],
            [
                'primary_responsible_member_id' => $responsible->id,
                'type' => Client::TYPE_INDIVIDUAL,
                'display_name' => 'Ana Paula Martins',
                'status' => Client::STATUS_ACTIVE,
                'priority' => ClientPriority::High,
                'risk_level' => Client::RISK_LOW,
                'potential_revenue_cents' => 450000,
                'origin' => 'indicação',
                'access_policy' => Client::ACCESS_ALL_MEMBERS,
                'internal_notes' => 'Cliente pessoa física com acompanhamento tributário anual.',
                'entered_at' => now()->subMonths(4)->toDateString(),
            ],
        );

        ClientIndividualProfile::query()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'full_name' => 'Ana Paula Martins',
                'rg' => '334455667',
                'birth_date' => '1986-08-21',
                'marital_status' => 'married',
                'profession' => 'Médica',
            ],
        );

        ClientContact::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'email' => 'ana.martins@example.com',
            ],
            [
                'name' => 'Ana Paula Martins',
                'role' => 'Titular',
                'phone' => '(11) 98888-1001',
                'whatsapp' => '(11) 98888-1001',
                'type' => ClientContact::TYPE_GENERAL,
                'is_primary' => true,
                'notes' => 'Prefere contato pelo WhatsApp no período da tarde.',
            ],
        );

        $client->responsibles()->syncWithoutDetaching([$responsible->id => ['is_primary' => true]]);

        return $client;
    }

    private function companyClient(Organization $organization, OrganizationMember $responsible): Client
    {
        $client = Client::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'document_number' => '98765432000110',
            ],
            [
                'primary_responsible_member_id' => $responsible->id,
                'type' => Client::TYPE_COMPANY,
                'display_name' => 'Nova Clínica Integrada LTDA',
                'status' => Client::STATUS_ACTIVE,
                'priority' => ClientPriority::Normal,
                'risk_level' => Client::RISK_MEDIUM,
                'potential_revenue_cents' => 1250000,
                'origin' => 'site',
                'access_policy' => Client::ACCESS_RESTRICTED,
                'internal_notes' => 'Contrato recorrente com obrigações financeiras e documentais mensais.',
                'entered_at' => now()->subMonths(2)->toDateString(),
            ],
        );

        ClientCompanyProfile::query()->updateOrCreate(
            ['client_id' => $client->id],
            [
                'legal_name' => 'Nova Clínica Integrada LTDA',
                'trade_name' => 'Nova Clínica',
                'state_registration' => 'ISENTO',
                'municipal_registration' => '44556677',
                'tax_regime' => 'lucro_presumido',
                'main_cnae' => '8630-5/03',
            ],
        );

        ClientContact::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'email' => 'financeiro@novaclinica.example.com',
            ],
            [
                'name' => 'Patrícia Nogueira',
                'role' => 'Coordenadora financeira',
                'phone' => '(11) 3777-2200',
                'whatsapp' => '(11) 97777-2200',
                'type' => ClientContact::TYPE_FINANCIAL,
                'is_primary' => true,
                'notes' => 'Centraliza documentos fiscais e comprovantes.',
            ],
        );

        $client->responsibles()->syncWithoutDetaching([$responsible->id => ['is_primary' => true]]);
        $client->accessMembers()->syncWithoutDetaching([$responsible->id]);

        return $client;
    }

    /**
     * @param  array<string, DocumentCategory>  $categories
     * @return list<Document>
     */
    private function documents(
        Organization $organization,
        User $admin,
        Client $individualClient,
        Client $companyClient,
        array $categories,
    ): array {
        $documents = [
            [
                'client' => $individualClient,
                'category' => $categories['Documento Pessoal'],
                'title' => 'CNH - Ana Paula Martins',
                'sensitivity' => DocumentSensitivity::Confidential,
                'visibility' => DocumentVisibility::Restricted,
                'expires_at' => now()->addYears(4)->toDateString(),
            ],
            [
                'client' => $companyClient,
                'category' => $categories['Contrato Social'],
                'title' => 'Contrato Social - Nova Clínica',
                'sensitivity' => DocumentSensitivity::Confidential,
                'visibility' => DocumentVisibility::Internal,
                'expires_at' => null,
            ],
            [
                'client' => $companyClient,
                'category' => $categories['Documento Fiscal'],
                'title' => 'Guia DAS - Abril',
                'sensitivity' => DocumentSensitivity::Sensitive,
                'visibility' => DocumentVisibility::Client,
                'expires_at' => now()->addYears(5)->toDateString(),
            ],
        ];

        $created = [];

        foreach ($documents as $index => $data) {
            $document = Document::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'client_id' => $data['client']->id,
                    'title' => $data['title'],
                ],
                [
                    'document_category_id' => $data['category']->id,
                    'created_by_user_id' => $admin->id,
                    'description' => 'Documento criado para dados demonstrativos do ambiente web.',
                    'status' => Document::STATUS_APPROVED,
                    'visibility' => $data['visibility'],
                    'sensitivity' => $data['sensitivity'],
                    'expires_at' => $data['expires_at'],
                    'approved_at' => now()->subDays(3),
                    'rejected_at' => null,
                    'rejection_reason' => null,
                ],
            );

            DocumentVersion::query()->updateOrCreate(
                [
                    'document_id' => $document->id,
                    'version_number' => 1,
                ],
                [
                    'organization_id' => $organization->id,
                    'uploaded_by_user_id' => $admin->id,
                    'source' => DocumentVersion::SOURCE_INTERNAL,
                    'disk' => 'local',
                    'path' => "demo/documents/{$document->id}/v1.pdf",
                    'original_name' => Str::slug($data['title']).'.pdf',
                    'stored_name' => "demo-document-{$index}.pdf",
                    'mime_type' => 'application/pdf',
                    'size' => 1024 * (20 + $index),
                    'hash' => hash('sha256', "{$organization->id}:{$document->id}:1"),
                    'replaced_at' => null,
                ],
            );

            $created[] = $document;
        }

        return $created;
    }

    /**
     * @param  array<string, DocumentCategory>  $categories
     */
    private function documentRequests(
        Organization $organization,
        User $admin,
        Client $individualClient,
        Client $companyClient,
        array $categories,
    ): void {
        $requests = [
            [
                'client' => $individualClient,
                'title' => 'Atualização cadastral anual',
                'due_at' => now()->addDays(10)->toDateString(),
                'items' => [
                    [$categories['Comprovante de Endereço'], 'Comprovante de endereço atualizado'],
                    [$categories['Documento Pessoal'], 'Documento pessoal atualizado'],
                ],
            ],
            [
                'client' => $companyClient,
                'title' => 'Documentos fiscais do mês',
                'due_at' => now()->addDays(5)->toDateString(),
                'items' => [
                    [$categories['Documento Fiscal'], 'Notas fiscais emitidas no mês'],
                    [$categories['Procuração'], 'Procuração para representação fiscal'],
                ],
            ],
        ];

        foreach ($requests as $requestData) {
            $documentRequest = DocumentRequest::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'client_id' => $requestData['client']->id,
                    'title' => $requestData['title'],
                ],
                [
                    'requested_by_user_id' => $admin->id,
                    'instructions' => 'Enviar documentos em PDF legível pelo portal ou atendimento.',
                    'due_at' => $requestData['due_at'],
                    'status' => DocumentRequest::STATUS_PENDING,
                    'completed_at' => null,
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                ],
            );

            foreach ($requestData['items'] as $item) {
                DocumentRequestItem::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'document_request_id' => $documentRequest->id,
                        'title' => $item[1],
                    ],
                    [
                        'document_category_id' => $item[0]->id,
                        'document_id' => null,
                        'instructions' => 'Anexar arquivo atualizado e sem cortes.',
                        'due_at' => $requestData['due_at'],
                        'status' => DocumentRequestItem::STATUS_REQUESTED,
                        'received_at' => null,
                        'approved_at' => null,
                        'rejected_at' => null,
                        'rejection_reason' => null,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, TaskTemplate>  $templates
     */
    private function tasks(
        Organization $organization,
        User $admin,
        OrganizationMember $professional,
        OrganizationMember $assistant,
        Client $individualClient,
        Client $companyClient,
        array $templates,
    ): void {
        $tasks = [
            [
                'client' => $individualClient,
                'member' => $professional,
                'template' => $templates['Onboarding de cliente'],
                'title' => 'Revisar documentação pessoal da Ana',
                'priority' => TaskPriority::High,
                'due_at' => now()->addDays(2)->toDateString(),
                'checklist' => ['Conferir CNH', 'Validar comprovante', 'Registrar observações'],
            ],
            [
                'client' => $companyClient,
                'member' => $assistant,
                'template' => $templates['Fechamento mensal'],
                'title' => 'Solicitar notas fiscais da Nova Clínica',
                'priority' => TaskPriority::Normal,
                'due_at' => now()->addDays(4)->toDateString(),
                'checklist' => ['Enviar solicitação', 'Conferir anexos recebidos'],
            ],
        ];

        foreach ($tasks as $taskData) {
            $task = Task::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'client_id' => $taskData['client']->id,
                    'title' => $taskData['title'],
                ],
                [
                    'assigned_to_member_id' => $taskData['member']->id,
                    'created_by_user_id' => $admin->id,
                    'task_template_id' => $taskData['template']->id,
                    'description' => 'Tarefa operacional criada para acompanhamento no painel web.',
                    'status' => Task::STATUS_PENDING,
                    'priority' => $taskData['priority'],
                    'due_at' => $taskData['due_at'],
                    'started_at' => null,
                    'completed_at' => null,
                    'completion_notes' => null,
                ],
            );

            foreach ($taskData['checklist'] as $index => $title) {
                TaskChecklistItem::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'task_id' => $task->id,
                        'title' => $title,
                    ],
                    [
                        'is_required' => $index === 0,
                        'is_completed' => false,
                        'completed_at' => null,
                    ],
                );
            }

            InternalReminder::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'user_id' => $taskData['member']->user_id,
                    'remindable_type' => Task::class,
                    'remindable_id' => $task->id,
                    'type' => 'task_due',
                ],
                [
                    'remind_at' => now()->addDay(),
                    'sent_at' => null,
                ],
            );
        }
    }

    private function deadlines(
        Organization $organization,
        User $admin,
        OrganizationMember $professional,
        OrganizationMember $manager,
        Client $individualClient,
        Client $companyClient,
    ): void {
        $deadlines = [
            [
                'client' => $individualClient,
                'member' => $professional,
                'title' => 'Entrega da declaração anual',
                'type' => 'tax',
                'urgency' => TaskPriority::High,
                'due_at' => now()->addDays(12)->toDateString(),
                'requires_review' => true,
            ],
            [
                'client' => $companyClient,
                'member' => $manager,
                'title' => 'Renovação da procuração fiscal',
                'type' => 'legal',
                'urgency' => TaskPriority::Normal,
                'due_at' => now()->addDays(20)->toDateString(),
                'requires_review' => false,
            ],
        ];

        foreach ($deadlines as $deadlineData) {
            Deadline::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'client_id' => $deadlineData['client']->id,
                    'title' => $deadlineData['title'],
                ],
                [
                    'assigned_to_member_id' => $deadlineData['member']->id,
                    'created_by_user_id' => $admin->id,
                    'description' => 'Prazo demonstrativo para acompanhamento operacional.',
                    'type' => $deadlineData['type'],
                    'urgency' => $deadlineData['urgency'],
                    'status' => Deadline::STATUS_PENDING,
                    'due_at' => $deadlineData['due_at'],
                    'requires_review' => $deadlineData['requires_review'],
                    'review_requested_at' => null,
                    'review_approved_at' => null,
                    'review_notes' => null,
                    'completed_at' => null,
                    'completion_notes' => null,
                ],
            );
        }
    }

    private function calendarEvents(
        Organization $organization,
        User $admin,
        OrganizationMember $professional,
        OrganizationMember $manager,
        OrganizationMember $assistant,
        Client $individualClient,
        Client $companyClient,
    ): void {
        $events = [
            [
                'client' => $individualClient,
                'title' => 'Reunião de alinhamento tributário',
                'type' => CalendarEventType::Meeting,
                'starts_at' => now()->addDays(3)->setTime(10, 0),
                'ends_at' => now()->addDays(3)->setTime(11, 0),
                'participants' => [$professional, $manager],
            ],
            [
                'client' => $companyClient,
                'title' => 'Audiência administrativa',
                'type' => CalendarEventType::Hearing,
                'starts_at' => now()->addDays(8)->setTime(14, 0),
                'ends_at' => now()->addDays(8)->setTime(15, 30),
                'participants' => [$manager, $assistant],
            ],
        ];

        foreach ($events as $eventData) {
            $event = CalendarEvent::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'client_id' => $eventData['client']->id,
                    'title' => $eventData['title'],
                ],
                [
                    'created_by_user_id' => $admin->id,
                    'description' => 'Evento demonstrativo para agenda web.',
                    'type' => $eventData['type'],
                    'status' => CalendarEvent::STATUS_CONFIRMED,
                    'starts_at' => $eventData['starts_at'],
                    'ends_at' => $eventData['ends_at'],
                    'location' => 'Videoconferência',
                    'notes' => null,
                    'notes_recorded_at' => null,
                ],
            );

            foreach ($eventData['participants'] as $participant) {
                CalendarEventParticipant::query()->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'calendar_event_id' => $event->id,
                        'organization_member_id' => $participant->id,
                    ],
                    [
                        'external_name' => null,
                        'external_email' => null,
                        'status' => 'accepted',
                    ],
                );
            }
        }
    }

    private function invitation(Organization $organization, User $admin, string $token): void
    {
        OrganizationInvitation::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'email' => 'novo.membro@docflow.local',
            ],
            [
                'invited_by_user_id' => $admin->id,
                'accepted_by_user_id' => null,
                'name' => 'Novo Membro',
                'role' => OrganizationMember::ROLE_ASSISTANT,
                'token' => $token,
                'status' => OrganizationInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
                'cancelled_at' => null,
            ],
        );
    }

    private function portal(Organization $organization, User $admin, Client $client, string $email): void
    {
        $access = ClientPortalAccess::query()->firstOrNew([
            'organization_id' => $organization->id,
            'email' => $email,
        ]);

        if (! $access->exists) {
            $access->token_hash = ClientPortalAccess::makeToken()['hash'];
        }

        $access->fill([
            'client_id' => $client->id,
            'created_by_user_id' => $admin->id,
            'name' => 'Patrícia Nogueira',
            'password' => 'password',
            'password_set_at' => now(),
            'onboarding_completed_at' => now(),
            'status' => ClientPortalAccess::STATUS_ACTIVE,
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        $access->save();
    }

    private function finance(Organization $organization, User $admin, Client $client): void
    {
        $income = FinancialCategory::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Honorários',
                'type' => FinancialCategory::TYPE_INCOME,
            ],
            [
                'color' => '#0f766e',
                'is_active' => true,
            ],
        );

        $expense = FinancialCategory::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Infraestrutura',
                'type' => FinancialCategory::TYPE_EXPENSE,
            ],
            [
                'color' => '#b45309',
                'is_active' => true,
            ],
        );

        Receivable::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'description' => 'Mensalidade contábil - mês vigente',
            ],
            [
                'financial_category_id' => $income->id,
                'created_by_user_id' => $admin->id,
                'amount_cents' => 250000,
                'paid_amount_cents' => 0,
                'status' => Receivable::STATUS_OPEN,
                'due_at' => now()->addDays(8)->toDateString(),
                'competence_date' => now()->startOfMonth()->toDateString(),
            ],
        );

        Receivable::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'description' => 'Honorários extra - declaração anual',
            ],
            [
                'financial_category_id' => $income->id,
                'created_by_user_id' => $admin->id,
                'amount_cents' => 180000,
                'paid_amount_cents' => 180000,
                'status' => Receivable::STATUS_PAID,
                'due_at' => now()->subDays(12)->toDateString(),
                'competence_date' => now()->subMonth()->startOfMonth()->toDateString(),
                'paid_at' => now()->subDays(10)->toDateString(),
            ],
        );

        Payable::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'description' => 'Assinatura de software fiscal',
            ],
            [
                'client_id' => null,
                'financial_category_id' => $expense->id,
                'created_by_user_id' => $admin->id,
                'vendor_name' => 'SoftFiscal LTDA',
                'amount_cents' => 8900,
                'paid_amount_cents' => 0,
                'status' => Payable::STATUS_OPEN,
                'due_at' => now()->addDays(15)->toDateString(),
                'competence_date' => now()->startOfMonth()->toDateString(),
                'is_reimbursable' => false,
            ],
        );
    }

    private function crm(Organization $organization, User $admin): void
    {
        $newLead = Lead::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'email' => 'contato@lead-novo.example.com',
            ],
            [
                'owner_user_id' => $admin->id,
                'name' => 'Clínica Horizonte',
                'phone' => '11987654321',
                'origin' => Lead::ORIGIN_WEBSITE,
                'stage' => Lead::STAGE_NEW,
                'estimated_value_cents' => 360000,
                'service_interest' => 'Contabilidade mensal',
            ],
        );

        LeadActivity::query()->updateOrCreate(
            [
                'lead_id' => $newLead->id,
                'type' => LeadActivity::TYPE_NOTE,
                'body' => 'Chegou pelo formulário do site.',
            ],
            [
                'created_by_user_id' => $admin->id,
                'happened_at' => now()->subDays(2),
            ],
        );

        $proposalLead = Lead::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'email' => 'proposta@lead-proposta.example.com',
            ],
            [
                'owner_user_id' => $admin->id,
                'name' => 'Studio Aurora',
                'phone' => '11912345678',
                'origin' => Lead::ORIGIN_REFERRAL,
                'stage' => Lead::STAGE_PROPOSAL,
                'estimated_value_cents' => 720000,
                'service_interest' => 'BPO financeiro',
            ],
        );

        LeadActivity::query()->updateOrCreate(
            [
                'lead_id' => $proposalLead->id,
                'type' => LeadActivity::TYPE_MEETING,
                'body' => 'Reunião de diagnóstico concluída.',
            ],
            [
                'created_by_user_id' => $admin->id,
                'happened_at' => now()->subDays(5),
            ],
        );

        Proposal::query()->updateOrCreate(
            [
                'lead_id' => $proposalLead->id,
                'title' => 'Pacote BPO financeiro mensal',
            ],
            [
                'amount_cents' => 720000,
                'status' => Proposal::STATUS_SENT,
                'sent_at' => now()->subDays(3),
                'notes' => 'Proposta demonstrativa para o funil comercial.',
            ],
        );
    }

    private function services(
        Organization $organization,
        OrganizationMember $assignee,
        Client $client,
        string $key,
    ): void {
        $serviceType = ServiceType::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Contabilidade mensal',
            ],
            [
                'description' => 'Escrituração, guias e obrigações acessórias.',
                'is_active' => true,
                'default_amount_cents' => 250000,
                'default_billing_interval' => ServiceType::BILLING_MONTH,
            ],
        );

        $clientService = ClientService::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'client_id' => $client->id,
                'service_type_id' => $serviceType->id,
            ],
            [
                'status' => ClientService::STATUS_ACTIVE,
                'starts_at' => now()->subMonths(2)->toDateString(),
                'ends_at' => null,
                'assigned_to_member_id' => $assignee->id,
                'notes' => 'Serviço recorrente demonstrativo.',
            ],
        );

        $contract = Contract::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'code' => 'CTR-'.strtoupper($key).'-001',
            ],
            [
                'client_id' => $client->id,
                'status' => Contract::STATUS_ACTIVE,
                'amount_cents' => 250000,
                'billing_interval' => Contract::BILLING_MONTH,
                'starts_at' => now()->subMonths(2)->toDateString(),
                'ends_at' => now()->addMonths(10)->toDateString(),
                'auto_renew' => true,
                'scope_included' => 'Contabilidade mensal e obrigações acessórias.',
                'scope_excluded' => 'Auditoria independente.',
            ],
        );

        $contract->clientServices()->syncWithoutDetaching([$clientService->id]);
    }

    private function tickets(
        Organization $organization,
        User $admin,
        OrganizationMember $assignee,
        Client $client,
    ): void {
        $open = Ticket::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'title' => 'Dúvida sobre guia DAS',
            ],
            [
                'client_id' => $client->id,
                'opened_by_user_id' => $admin->id,
                'assigned_to_member_id' => $assignee->id,
                'description' => 'Cliente pediu confirmação do vencimento da guia.',
                'status' => Ticket::STATUS_IN_PROGRESS,
                'priority' => Ticket::PRIORITY_HIGH,
                'visible_to_client' => true,
                'due_at' => now()->addDays(2)->toDateString(),
            ],
        );

        TicketMessage::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'ticket_id' => $open->id,
                'body' => 'Vou conferir o vencimento e retorno ainda hoje.',
            ],
            [
                'user_id' => $admin->id,
                'sender_type' => TicketMessage::SENDER_INTERNAL,
                'visible_to_client' => true,
            ],
        );

        Ticket::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'title' => 'Envio de procuração resolvido',
            ],
            [
                'client_id' => $client->id,
                'opened_by_user_id' => $admin->id,
                'assigned_to_member_id' => $assignee->id,
                'description' => 'Procuração recebida e arquivada.',
                'status' => Ticket::STATUS_RESOLVED,
                'priority' => Ticket::PRIORITY_NORMAL,
                'visible_to_client' => true,
                'resolved_at' => now()->subDay(),
            ],
        );
    }

    private function automations(Organization $organization, TaskTemplate $onboardingTemplate): void
    {
        $preset = AutomationPresets::get('client_created_tasks');
        $preset['actions'][0]['params']['task_template_id'] = $onboardingTemplate->id;

        AutomationRule::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'preset_key' => 'client_created_tasks',
            ],
            [
                'name' => $preset['name'],
                'trigger' => $preset['trigger'],
                'conditions' => $preset['conditions'],
                'actions' => $preset['actions'],
                'is_active' => true,
            ],
        );
    }

    private function reports(Organization $organization, User $admin): void
    {
        SavedReportFilter::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Visão mensal compartilhada',
            ],
            [
                'user_id' => $admin->id,
                'report_type' => 'overview',
                'filters' => ['period' => 'month'],
                'is_shared' => true,
            ],
        );

        ReportSchedule::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Relatório mensal automático',
            ],
            [
                'created_by_user_id' => $admin->id,
                'client_id' => null,
                'report_type' => 'overview',
                'frequency' => 'monthly',
                'filters' => ['period' => 'month'],
                'is_active' => true,
                'next_run_at' => now()->addMonth()->startOfMonth()->toDateString(),
            ],
        );
    }

    private function audit(Organization $organization, User $admin, Document $document): void
    {
        AuditLog::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'action' => 'document.approved',
                'auditable_type' => Document::class,
                'auditable_id' => $document->id,
            ],
            [
                'user_id' => $admin->id,
                'metadata' => ['source' => 'demo-seeder'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'DemoWorkspaceSeeder',
            ],
        );
    }
}
