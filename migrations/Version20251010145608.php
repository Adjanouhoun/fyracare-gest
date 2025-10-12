<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251010145608 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643AB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_965E643AB03A8386 ON cash_movement (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643AB03A8386');
        $this->addSql('DROP INDEX IDX_965E643AB03A8386 ON cash_movement');
        $this->addSql('ALTER TABLE cash_movement DROP created_by_id');
    }
}
