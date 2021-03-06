<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1544694243MediaDefaultFolder extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1544694243;
    }

    public function update(Connection $connection): void
    {
        $connection->exec('
            CREATE TABLE `media_default_folder` (
              `id` BINARY(16) NOT NULL,
              `media_folder_id` BINARY(16),
              `associations` JSON NOT NULL,
              `entity` VARCHAR(255) NOT NULL,
              `created_at` DATETIME(3),
              `updated_at` DATETIME(3),
              PRIMARY KEY (`id`),
              CONSTRAINT `fk.media_default_folder.media_folder_id` FOREIGN KEY (`media_folder_id`) 
                REFERENCES `media_folder` (`id`) ON DELETE SET NULL,
              CONSTRAINT `json.associations` CHECK (JSON_VALID(`associations`)),
              UNIQUE KEY `uniq.entity` (`entity`),
              UNIQUE KEY `uniq.media_folder_id` (`media_folder_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // no destructive changes
    }
}
