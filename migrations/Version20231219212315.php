<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231219212315 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создаёт таблицу тултипов (подсказки сайта)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE `table1` (`id` INT NOT NULL AUTO_INCREMENT, `title` VARCHAR(100) NOT NULL DEFAULT '', `text` TEXT NOT NULL, `path` VARCHAR(150) NOT NULL DEFAULT '', `url` VARCHAR(255) DEFAULT NULL, `mode` VARCHAR(50) DEFAULT NULL, `created_at` INT NOT NULL, `updated_at` INT NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE `table1`");
    }
}
