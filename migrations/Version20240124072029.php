<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240124072029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет таблицу table1';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE `table1` (`id` INT NOT NULL AUTO_INCREMENT, `table2_id` INT NOT NULL, `table3_id` INT NOT NULL, `source` VARCHAR(255) NOT NULL, `hash` VARCHAR(50) NOT NULL, `comment` TEXT NOT NULL, `ended_at` INT DEFAULT NULL, `created_at` INT NOT NULL, `updated_at` INT NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $this->addSql("ALTER TABLE `table1` ADD CONSTRAINT `table1_table2`
                       FOREIGN KEY (`table2_id`) REFERENCES `table2` (`id`) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE `table1` ADD CONSTRAINT `table1_table3`
                       FOREIGN KEY (`table3_id`) REFERENCES `table3` (`id`) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE `table1`");
    }
}
