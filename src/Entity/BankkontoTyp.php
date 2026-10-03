<?php

namespace App\Entity;

enum BankkontoTyp: string
{
    public function getDisplayName(): string
    {
        return match ($this) {
            self::HAUSGELD => 'Hausgeld',
            self::RUECKLAGE => 'Rücklage',
        };
    }
    case HAUSGELD = 'hausgeld';
    case RUECKLAGE = 'ruecklage';
}
