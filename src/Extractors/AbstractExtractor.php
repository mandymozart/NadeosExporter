<?php declare(strict_types=1);

namespace NadeosData\Extractors;

use NadeosData\Extractors\ExtractorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use UnexpectedValueException;

abstract class AbstractExtractor implements ExtractorInterface
{
    abstract protected function extractEntity(Entity $entity): array;
    abstract protected function isValidEntity(Entity $entity): bool;

    public function extract(Entity $entity): array
    {
        if (false === $this->isValidEntity($entity)) {
            throw new UnexpectedValueException(sprintf(
                "Entity of type %s cant be extract",
                gettype($entity)
            ));
        }

        return $this->extractEntity($entity);
    }
}