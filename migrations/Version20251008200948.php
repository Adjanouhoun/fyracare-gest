<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251008200948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement ADD payment_id INT DEFAULT NULL, ADD source VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643A4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id)');
        $this->addSql('CREATE INDEX IDX_965E643A4C3A3BB ON cash_movement (payment_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643A4C3A3BB');
        $this->addSql('DROP INDEX IDX_965E643A4C3A3BB ON cash_movement');
        $this->addSql('ALTER TABLE cash_movement DROP payment_id, DROP source');
    }
}
