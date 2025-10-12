<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251010004101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_C02DDB385E237E06 ON expense_category');
        $this->addSql('ALTER TABLE expense_category ADD description VARCHAR(255) DEFAULT NULL, ADD active TINYINT(1) NOT NULL, DROP color, CHANGE name name VARCHAR(100) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense_category ADD color VARCHAR(7) DEFAULT \'#999999\' NOT NULL, DROP description, DROP active, CHANGE name name VARCHAR(120) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C02DDB385E237E06 ON expense_category (name)');
    }
}
