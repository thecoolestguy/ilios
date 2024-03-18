<?php

declare(strict_types=1);

namespace App\Traits;

interface DescribableNullableEntityInterface
{
    public function setDescription(?string $description): void;
    public function getDescription(): ?string;
}
