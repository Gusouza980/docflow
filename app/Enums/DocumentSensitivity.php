<?php

namespace App\Enums;

enum DocumentSensitivity: string
{
    case Normal = 'normal';
    case Sensitive = 'sensitive';
    case Confidential = 'confidential';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Sensitive => 'Sensível',
            self::Confidential => 'Confidencial',
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
