<?php

namespace App\Enums;

enum CalendarEventType: string
{
    case Internal = 'internal';
    case Meeting = 'meeting';
    case Deadline = 'deadline';
    case Hearing = 'hearing';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interno',
            self::Meeting => 'Reunião',
            self::Deadline => 'Prazo',
            self::Hearing => 'Audiência',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
