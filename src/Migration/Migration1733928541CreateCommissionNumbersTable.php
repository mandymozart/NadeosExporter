<?php declare(strict_types=1);

namespace NadeosData\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1733928541CreateCommissionNumbersTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1733928541;
    }

    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `commission_numbers` (
    `id` BINARY(16) NOT NULL,
    `ida` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `year` SMALLINT UNSIGNED NOT NULL,
    `month` SMALLINT UNSIGNED NOT NULL,
    `group` VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3),
    PRIMARY KEY (`ida`),
    UNIQUE KEY `idx_id` (`id`)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;
SQL;

    // PRIMARY KEY (`id`)
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
