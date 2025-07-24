<?php declare(strict_types=1);

namespace NadeosData\Core\Content\CommissionNumbers;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(CommissionNumbersEntity $entity)
 * @method void set(string $key, CommissionNumbersEntity $entity)
 * @method CommissionNumbersEntity[] getIterator()
 * @method CommissionNumbersEntity[] getElements()
 * @method CommissionNumbersEntity|null get(string $key)
 * @method CommissionNumbersEntity|null first()
 * @method CommissionNumbersEntity|null last()
 */
class CommissionNumbersCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return CommissionNumbersEntity::class;
    }
}
