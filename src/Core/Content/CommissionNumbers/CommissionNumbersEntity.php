<?php declare(strict_types=1);

namespace NadeosData\Core\Content\CommissionNumbers;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CommissionNumbersEntity extends Entity
{
    use EntityIdTrait;

    protected int $ida;

    protected int $year;

    protected int $month;

    protected string $group;

    public function setIda(int $ida): void
    {
        $this->ida = $ida;
    }

    public function getIda(): int
    {
        return $this->ida;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): void
    {
        $this->year = $year;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function setMonth(int $month): void
    {
        $this->month = $month;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function setGroup(string $group): void
    {
        $this->group = $group;
    }
}
