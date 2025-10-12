<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251010150948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement ADD updated_by_id INT DEFAULT NULL, ADD deleted_by_id INT DEFAULT NULL, ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643A896DBBDE FOREIGN KEY (updated_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643AC76F1F52 FOREIGN KEY (deleted_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_965E643A896DBBDE ON cash_movement (updated_by_id)');
        $this->addSql('CREATE INDEX IDX_965E643AC76F1F52 ON cash_movement (deleted_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643A896DBBDE');
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643AC76F1F52');
        $this->addSql('DROP INDEX IDX_965E643A896DBBDE ON cash_movement');
        $this->addSql('DROP INDEX IDX_965E643AC76F1F52 ON cash_movement');
        $this->addSql('ALTER TABLE cash_movement DROP updated_by_id, DROP deleted_by_id, DROP deleted_at');
    }
}
