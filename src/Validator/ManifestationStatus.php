<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class ManifestationStatus extends Constraint
{
    public string $message = 'Invalid status1 value.';
}
