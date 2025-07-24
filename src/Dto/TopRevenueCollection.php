<?php declare(strict_types=1);

namespace NadeosData\Dto;

final class TopRevenueCollection
{
    /** @var TopRevenueItem[] */
    private array $items = [];

    public function add(TopRevenueItem $item): void
    {
        $this->items[] = $item;
    }

    /** @return TopRevenueItem[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getTotalRevenue(): float
    {
        return array_sum(array_map(fn(TopRevenueItem $item) => $item->revenue, $this->items));
    }

    public function toArray(): array
    {
        return array_map(fn(TopRevenueItem $item) => $item->toArray(), $this->items);
    }
}
