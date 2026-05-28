<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\CalendarEventParticipant;
use App\Models\Client;
use App\Models\ClientCompanyProfile;
use App\Models\ClientContact;
use App\Models\ClientIndividualProfile;
use App\Models\ClientTag;
use App\Models\Deadline;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentRequest;
use App\Models\DocumentRequestItem;
use App\Models\DocumentVersion;
use App\Models\InternalReminder;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoWorkspaceSeeder extends Seeder
{
    /**
     * Seed a consistent workspace for local web development.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $organization = Organization::query()->updateOrCreate(
                ['document' => '12345678000190'],
                [
                    'name' => 'DocFlow Consultoria Integrada',
                    'email' => 'contato@docflow.local',
                    'phone' => '(11) 4002-8922',
                    'timezone' => 'America/Sao_Paulo',
                    'status' => Organization::STATUS_ACTIVE,
                    'settings' => [
                        'currency' => 'BRL',
                        'locale' => 'pt_BR',
                        'document_retention_years' => 5,
                    ],
                ],
            );

            setPermissionsTeamId($organization->id);

            $admin = $this->user('Admin DocFlow', 'admin@docflow.local');
            $manager = $this->user('Marina Gestora', 'gestora@docflow.local');
            $professional = $this->user('Rafael Consultor', 'consultor@docflow.local');
            $assistant = $this->user('Bianca Assistente', 'assistente@docflow.local');
            $finance = $this->user('Caio Financeiro', 'financeiro@docflow.local');
            $readonly = $this->user('Leticia Leitura', 'leitura@docflow.local');

            $members = [
                OrganizationMember::ROLE_ADMIN => $this->member($organization, $admin, OrganizationMember::ROLE_ADMIN),
                OrganizationMember::ROLE_MANAGER => $this->member($organization, $manager, OrganizationMember::ROLE_MANAGER),
                OrganizationMember::ROLE_PROFESSIONAL => $this->member($organization, $professional, OrganizationMember::ROLE_PROFESSIONAL),
                OrganizationMember::ROLE_ASSISTANT => $this->member($organization, $assistant, OrganizationMember::ROLE_ASSISTANT),
                OrganizationMember::ROLE_FINANCE => $this->member($organization, $finance, OrganizationMember::ROLE_FINANCE),
                OrganizationMember::ROLE_READONLY => $this->member($organization, $readonly, OrganizationMember::ROLE_READONLY),
            ];

            foreach ($members as $role => $member) {
                $roleModel = Role::findOrCreate($role, 'web');
                $roleModel->syncPermissions(PermissionSeeder::rolePermissions()[$role]);
                $member->user->assignRole($roleModel);
                $member->user->unsetRelation('roles')->unsetRelation('permissions');
            }

            $tags = $this->clientTags($organization);
            $categories = $this->documentCategories($organization);
            $templates = $this->taskTemplates($organization);

            $individualClient = $this->individualClient($organization, $members[OrganizationMember::ROLE_PROFESSIONAL]);
            $companyClient = $this->companyClient($organization, $members[OrganizationMember::ROLE_MANAGER]);

            $individualClient->tags()->syncWithoutDetaching([
                $tags['VIP']->id,
                $tags['Imposto de Renda']->id,
            ]);
            $companyClient->tags()->syncWithoutDetaching([
                $tags['Recorrente']->id,
                $tags['Jurídico']->id,
            ]);

            $this->documents($organization, $admin, $individualClient, $companyClient, $categories);
            $this->documentRequests($organization, $admin, $individualClient, $companyClient, $categories);
            $this->tasks($organization, $admin, $members, $individualClient, $companyClient, $templates);
            $this->deadlines($organization, $admin, $members, $individualClient, $companyClient);
            $this->calendarEvents($organization, $admin, $members, $individualClient, $companyClient);
            $this->invitation($organization, $admin);
        });
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
            'Contrato Social' => ['Documento societário principal.', 3650, DocumentCategory::SENSITIVITY_CONFIDENTIAL],
            'Procuração' => ['Autorização para representação do cliente.', 365, DocumentCategory::SENSITIVITY_SENSITIVE],
            'Comprovante de Endereço' => ['Comprovante residencial ou comercial atualizado.', 180, DocumentCategory::SENSITIVITY_NORMAL],
            'Documento Fiscal' => ['Notas, guias e comprovantes fiscais.', 1825, DocumentCategory::SENSITIVITY_SENSITIVE],
            'Documento Pessoal' => ['RG, CNH, CPF ou documentos pessoais equivalentes.', 3650, DocumentCategory::SENSITIVITY_CONFIDENTIAL],
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
                'priority' => Task::PRIORITY_HIGH,
                'items' => [
                    ['Coletar documentação inicial', 1, Task::PRIORITY_HIGH, ['Conferir identidade', 'Validar comprovante de endereço']],
                    ['Cadastrar dados financeiros', 3, Task::PRIORITY_NORMAL, ['Definir dia de vencimento', 'Registrar contato financeiro']],
                    ['Revisar contrato de prestação', 5, Task::PRIORITY_HIGH, ['Enviar minuta', 'Registrar aceite']],
                ],
            ],
            'Fechamento mensal' => [
                'description' => 'Rotina operacional e financeira recorrente.',
                'priority' => Task::PRIORITY_NORMAL,
                'items' => [
                    ['Solicitar documentos fiscais', 2, Task::PRIORITY_NORMAL, ['Notas emitidas', 'Extratos bancários']],
                    ['Conferir pendências financeiras', 4, Task::PRIORITY_HIGH, ['Mensalidade', 'Reembolsos']],
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
                'priority' => Client::PRIORITY_HIGH,
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
                'priority' => Client::PRIORITY_NORMAL,
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
     */
    private function documents(
        Organization $organization,
        User $admin,
        Client $individualClient,
        Client $companyClient,
        array $categories,
    ): void {
        $documents = [
            [
                'client' => $individualClient,
                'category' => $categories['Documento Pessoal'],
                'title' => 'CNH - Ana Paula Martins',
                'sensitivity' => Document::SENSITIVITY_CONFIDENTIAL,
                'visibility' => Document::VISIBILITY_RESTRICTED,
                'expires_at' => now()->addYears(4)->toDateString(),
            ],
            [
                'client' => $companyClient,
                'category' => $categories['Contrato Social'],
                'title' => 'Contrato Social - Nova Clínica',
                'sensitivity' => Document::SENSITIVITY_CONFIDENTIAL,
                'visibility' => Document::VISIBILITY_INTERNAL,
                'expires_at' => null,
            ],
            [
                'client' => $companyClient,
                'category' => $categories['Documento Fiscal'],
                'title' => 'Guia DAS - Abril',
                'sensitivity' => Document::SENSITIVITY_SENSITIVE,
                'visibility' => Document::VISIBILITY_CLIENT,
                'expires_at' => now()->addYears(5)->toDateString(),
            ],
        ];

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
        }
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
     * @param  array<string, OrganizationMember>  $members
     * @param  array<string, TaskTemplate>  $templates
     */
    private function tasks(
        Organization $organization,
        User $admin,
        array $members,
        Client $individualClient,
        Client $companyClient,
        array $templates,
    ): void {
        $tasks = [
            [
                'client' => $individualClient,
                'member' => $members[OrganizationMember::ROLE_PROFESSIONAL],
                'template' => $templates['Onboarding de cliente'],
                'title' => 'Revisar documentação pessoal da Ana',
                'priority' => Task::PRIORITY_HIGH,
                'due_at' => now()->addDays(2)->toDateString(),
                'checklist' => ['Conferir CNH', 'Validar comprovante', 'Registrar observações'],
            ],
            [
                'client' => $companyClient,
                'member' => $members[OrganizationMember::ROLE_ASSISTANT],
                'template' => $templates['Fechamento mensal'],
                'title' => 'Solicitar notas fiscais da Nova Clínica',
                'priority' => Task::PRIORITY_NORMAL,
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

    /**
     * @param  array<string, OrganizationMember>  $members
     */
    private function deadlines(
        Organization $organization,
        User $admin,
        array $members,
        Client $individualClient,
        Client $companyClient,
    ): void {
        $deadlines = [
            [
                'client' => $individualClient,
                'member' => $members[OrganizationMember::ROLE_PROFESSIONAL],
                'title' => 'Entrega da declaração anual',
                'type' => 'tax',
                'urgency' => Deadline::URGENCY_HIGH,
                'due_at' => now()->addDays(12)->toDateString(),
                'requires_review' => true,
            ],
            [
                'client' => $companyClient,
                'member' => $members[OrganizationMember::ROLE_MANAGER],
                'title' => 'Renovação da procuração fiscal',
                'type' => 'legal',
                'urgency' => Deadline::URGENCY_NORMAL,
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

    /**
     * @param  array<string, OrganizationMember>  $members
     */
    private function calendarEvents(
        Organization $organization,
        User $admin,
        array $members,
        Client $individualClient,
        Client $companyClient,
    ): void {
        $events = [
            [
                'client' => $individualClient,
                'title' => 'Reunião de alinhamento tributário',
                'type' => CalendarEvent::TYPE_MEETING,
                'starts_at' => now()->addDays(3)->setTime(10, 0),
                'ends_at' => now()->addDays(3)->setTime(11, 0),
                'participants' => [
                    $members[OrganizationMember::ROLE_PROFESSIONAL],
                    $members[OrganizationMember::ROLE_MANAGER],
                ],
            ],
            [
                'client' => $companyClient,
                'title' => 'Audiência administrativa',
                'type' => CalendarEvent::TYPE_HEARING,
                'starts_at' => now()->addDays(8)->setTime(14, 0),
                'ends_at' => now()->addDays(8)->setTime(15, 30),
                'participants' => [
                    $members[OrganizationMember::ROLE_MANAGER],
                    $members[OrganizationMember::ROLE_ASSISTANT],
                ],
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

    private function invitation(Organization $organization, User $admin): void
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
                'token' => 'demo-invite-token',
                'status' => OrganizationInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
                'cancelled_at' => null,
            ],
        );
    }
}
