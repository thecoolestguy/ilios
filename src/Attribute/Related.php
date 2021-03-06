<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Related
{
    public function __construct(public ?string $value = null)
    {
    }
}
