<?php

namespace App\Enums;

enum BorrowerType: string
{
    case Student = 'student';
    case Teacher = 'teacher';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
