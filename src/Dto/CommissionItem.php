<?php declare(strict_types=1);

namespace NadeosData\Dto;

use DateTime;

final class CommissionItem
{
    public function __construct(
        public readonly float      $salesNet,
        public readonly float      $commissionNet,
        public readonly DateTime   $commissionPeriod
    ) {}
}