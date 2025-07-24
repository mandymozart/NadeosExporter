<?php declare(strict_types=1);

namespace NadeosData\Dto;

use Shopware\Core\Framework\Struct\Collection;
use NadeosData\Dto\Commission;

class CommissionCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Commission::class;
    }
}