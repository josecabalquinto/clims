<?php

namespace App\Enums;

enum BorrowStatus: string
{
    case Borrowed = 'borrowed';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Borrowed => 'Borrowed',
            self::PartiallyReturned => 'Partially returned',
            self::Returned => 'Returned',
        };
    }
}
