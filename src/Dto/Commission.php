<?php declare(strict_types=1);

namespace NadeosData\Dto;

use NadeosData\Services\CommissionService;
use DateTime;   // todo: make immutable

final class Commission
{
    public readonly string $groupNameHashed;

    public readonly float $commissionNetTotal;
    public readonly float $commissionGrossTotal;
    public readonly int   $orderYear;
    public readonly int   $orderMonth;

    private array $items = [];

    public function __construct(
        private readonly CommissionService $service,
        public readonly string      $orderNumber,
        public readonly DateTime    $orderDate,
        public readonly float       $orderAmountNetTotal,
        public readonly float       $commissionPercentage,
        public readonly string      $commissionType,
        public readonly string      $groupName,
        public readonly string      $salutation,
        public readonly string      $email,
        public readonly string      $firstname,
        public readonly string      $lastname,
        public readonly string      $street,
        public readonly string      $cityZip,
        public readonly string      $urlPdf
    ) {
        $this->commissionNetTotal   = $this->getCommissionNetTotal();
        $this->commissionGrossTotal = round($this->commissionNetTotal * 1.2, 2);
        $this->orderYear            = $this->getOrderYear();
        $this->orderMonth           = $this->getOrderMonth();

        $this->groupNameHashed = $groupName;
    }

    public function addItem(CommissionItem $item) {
        $this->items[] = $item;
    }

    public function getItems(): array {
        return $this->items;
    }

    public function getOrders() {
        return $this->service->getOrders(
            $this->orderDate,
            $this->groupName
        );
    }

    private function getOrderYear(): int
    {
        return (int) $this->orderDate->format('Y');
    }

    private function getOrderMonth(): int
    {
        return (int) $this->orderDate->format('m');
    }

    private function getCommissionNetTotal(): float
    {
        return round(
            $this->orderAmountNetTotal * ( $this->commissionPercentage / 100 ),
            2
        );
    }


    const COMMISSION_TYPE_MAP = [
        'default'  => 'Standard',
        'internal' => 'Mitarbeiter'
    ];

    public function getCommissionTypeName(): string
    {
        return self::COMMISSION_TYPE_MAP[
            $this->commissionType ?? 'default'
        ];
    }
}