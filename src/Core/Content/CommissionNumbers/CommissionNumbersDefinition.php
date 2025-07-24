<?php declare(strict_types=1);

namespace NadeosData\Core\Content\CommissionNumbers;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;

class CommissionNumbersDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'commission_numbers';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return CommissionNumbersEntity::class;
    }

    public function getCollectionClass(): string
    {
        return CommissionNumbersCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new IdField('ida', 'ida'))->addFlags(new Required()),
            (new IntField('year', 'year', 2000))->addFlags(new Required()),
            (new IntField('month', 'month', 1, 12))->addFlags(new Required()),
            (new StringField('group', 'group'))->addFlags(new Required()),
        ]);
    }
}
