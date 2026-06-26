<?php

namespace Tests\Unit;

use App\Enums\CalendarEventType;
use App\Enums\ClientPriority;
use App\Enums\DocumentSensitivity;
use App\Enums\DocumentVisibility;
use App\Enums\TaskPriority;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentEnumTest extends TestCase
{
    #[DataProvider('visibilityLabelsProvider')]
    public function test_document_visibility_has_portuguese_label(DocumentVisibility $visibility, string $label): void
    {
        $this->assertSame($label, $visibility->label());
    }

    #[DataProvider('sensitivityLabelsProvider')]
    public function test_document_sensitivity_has_portuguese_label(DocumentSensitivity $sensitivity, string $label): void
    {
        $this->assertSame($label, $sensitivity->label());
    }

    #[DataProvider('taskPriorityLabelsProvider')]
    public function test_task_priority_has_portuguese_label(TaskPriority $priority, string $label): void
    {
        $this->assertSame($label, $priority->label());
    }

    #[DataProvider('clientPriorityLabelsProvider')]
    public function test_client_priority_has_portuguese_label(ClientPriority $priority, string $label): void
    {
        $this->assertSame($label, $priority->label());
    }

    #[DataProvider('calendarEventTypeLabelsProvider')]
    public function test_calendar_event_type_has_portuguese_label(CalendarEventType $type, string $label): void
    {
        $this->assertSame($label, $type->label());
    }

    public function test_enum_options_include_value_and_label(): void
    {
        $this->assertSame(
            [
                ['value' => 'internal', 'label' => 'Interno'],
                ['value' => 'client', 'label' => 'Cliente'],
                ['value' => 'restricted', 'label' => 'Restrito'],
                ['value' => 'confidential', 'label' => 'Confidencial'],
            ],
            DocumentVisibility::options(),
        );

        $this->assertSame(
            [
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'sensitive', 'label' => 'Sensível'],
                ['value' => 'confidential', 'label' => 'Confidencial'],
            ],
            DocumentSensitivity::options(),
        );

        $this->assertSame(
            [
                ['value' => 'low', 'label' => 'Baixa'],
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'high', 'label' => 'Alta'],
                ['value' => 'critical', 'label' => 'Crítica'],
            ],
            TaskPriority::options(),
        );

        $this->assertSame(
            [
                ['value' => 'low', 'label' => 'Baixa'],
                ['value' => 'normal', 'label' => 'Normal'],
                ['value' => 'high', 'label' => 'Alta'],
            ],
            ClientPriority::options(),
        );

        $this->assertSame(
            [
                ['value' => 'internal', 'label' => 'Interno'],
                ['value' => 'meeting', 'label' => 'Reunião'],
                ['value' => 'deadline', 'label' => 'Prazo'],
                ['value' => 'hearing', 'label' => 'Audiência'],
            ],
            CalendarEventType::options(),
        );
    }

    /**
     * @return array<string, array{0: DocumentVisibility, 1: string}>
     */
    public static function visibilityLabelsProvider(): array
    {
        return [
            'internal' => [DocumentVisibility::Internal, 'Interno'],
            'client' => [DocumentVisibility::Client, 'Cliente'],
            'restricted' => [DocumentVisibility::Restricted, 'Restrito'],
            'confidential' => [DocumentVisibility::Confidential, 'Confidencial'],
        ];
    }

    /**
     * @return array<string, array{0: DocumentSensitivity, 1: string}>
     */
    public static function sensitivityLabelsProvider(): array
    {
        return [
            'normal' => [DocumentSensitivity::Normal, 'Normal'],
            'sensitive' => [DocumentSensitivity::Sensitive, 'Sensível'],
            'confidential' => [DocumentSensitivity::Confidential, 'Confidencial'],
        ];
    }

    /**
     * @return array<string, array{0: TaskPriority, 1: string}>
     */
    public static function taskPriorityLabelsProvider(): array
    {
        return [
            'low' => [TaskPriority::Low, 'Baixa'],
            'normal' => [TaskPriority::Normal, 'Normal'],
            'high' => [TaskPriority::High, 'Alta'],
            'critical' => [TaskPriority::Critical, 'Crítica'],
        ];
    }

    /**
     * @return array<string, array{0: ClientPriority, 1: string}>
     */
    public static function clientPriorityLabelsProvider(): array
    {
        return [
            'low' => [ClientPriority::Low, 'Baixa'],
            'normal' => [ClientPriority::Normal, 'Normal'],
            'high' => [ClientPriority::High, 'Alta'],
        ];
    }

    /**
     * @return array<string, array{0: CalendarEventType, 1: string}>
     */
    public static function calendarEventTypeLabelsProvider(): array
    {
        return [
            'internal' => [CalendarEventType::Internal, 'Interno'],
            'meeting' => [CalendarEventType::Meeting, 'Reunião'],
            'deadline' => [CalendarEventType::Deadline, 'Prazo'],
            'hearing' => [CalendarEventType::Hearing, 'Audiência'],
        ];
    }
}
