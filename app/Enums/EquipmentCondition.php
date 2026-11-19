<?php

namespace App\Enums;

enum EquipmentCondition: string
{
    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Poor => 'Poor',
        };
    }
}
