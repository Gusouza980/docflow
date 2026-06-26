<?php

namespace App\Enums;

enum DocumentVisibility: string
{
    case Internal = 'internal';
    case Client = 'client';
    case Restricted = 'restricted';
    case Confidential = 'confidential';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Interno',
            self::Client => 'Cliente',
            self::Restricted => 'Restrito',
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
