<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240228221328 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавляет связи между table1 и table2 + table3';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `table1`
            ADD CONSTRAINT `table1_table2` 
                FOREIGN KEY (`table2_id`) 
                    REFERENCES `table2` (`id`) ON DELETE CASCADE,
            ADD CONSTRAINT `table1_table3` 
                FOREIGN KEY (`table3_id`) 
                    REFERENCES `table3` (`id`) ON DELETE CASCADE
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `table1`
            DROP FOREIGN KEY `table1_table2`,
            DROP FOREIGN KEY `table1_table3`
        ');
    }
}
