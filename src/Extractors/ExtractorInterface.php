<?php declare(strict_types=1);

namespace NadeosData\Extractors;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;

interface ExtractorInterface
{
    public function extract(Entity $entity): array;
}
