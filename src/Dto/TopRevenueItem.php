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
        public readonly string $customerNumber = '',
        public readonly string $groupName = '',
        public readonly string $groupFirstname = '',
        public readonly string $groupLastname = ''
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
            'customerNumber' => $this->customerNumber,
            'groupName' => $this->groupName,
            'groupFirstname' => $this->groupFirstname,
            'groupLastname' => $this->groupLastname
        ];
    }

    public function getFormattedRevenue(): string
    {
        return number_format($this->revenue, 2, ',', '.') . ' €';
    }
}
