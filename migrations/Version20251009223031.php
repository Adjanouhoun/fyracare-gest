<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251009223031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE expense_category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, color VARCHAR(7) DEFAULT \'#999999\' NOT NULL, UNIQUE INDEX UNIQ_C02DDB385E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cash_movement ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643A12469DE2 FOREIGN KEY (category_id) REFERENCES expense_category (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_965E643A12469DE2 ON cash_movement (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643A12469DE2');
        $this->addSql('DROP TABLE expense_category');
        $this->addSql('DROP INDEX IDX_965E643A12469DE2 ON cash_movement');
        $this->addSql('ALTER TABLE cash_movement DROP category_id');
    }
}
