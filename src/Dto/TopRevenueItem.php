<?php declare(strict_types=1);

namespace NadeosData\Dto;

final class TopRevenueItem
{
    public function __construct(
        public readonly int    $rank,
        public readonly float  $revenue,
        public readonly string $company,
        public readonly string $contactPerson,
        public readonly string $phoneNumber,
        public readonly string $email,
        public readonly string $customerId,
        public readonly string $customerNumber = ''
    ) {}

    public function toArray(): array
    {
        return [
            'rank' => $this->rank,
            'revenue' => $this->revenue,
            'company' => $this->company,
            'contactPerson' => $this->contactPerson,
            'phoneNumber' => $this->phoneNumber,
            'email' => $this->email,
            'customerId' => $this->customerId,
            'customerNumber' => $this->customerNumber
        ];
    }

    public function getFormattedRevenue(): string
    {
        return number_format($this->revenue, 2, ',', '.') . ' €';
    }
}
