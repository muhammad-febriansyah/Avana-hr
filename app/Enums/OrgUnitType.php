<?php

namespace App\Enums;

/**
 * Organisation hierarchy levels (Company → Division → Department → Unit).
 */
enum OrgUnitType: string
{
    case Company = 'company';
    case Division = 'division';
    case Department = 'department';
    case Unit = 'unit';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Perusahaan',
            self::Division => 'Divisi',
            self::Department => 'Departemen',
            self::Unit => 'Unit',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
