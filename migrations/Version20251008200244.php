<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251008200244 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643AFA43E9DA');
        $this->addSql('DROP INDEX IDX_965E643AFA43E9DA ON cash_movement');
        $this->addSql('ALTER TABLE cash_movement ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP closure_id, DROP source, DROP method, DROP source_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_movement ADD closure_id INT DEFAULT NULL, ADD source VARCHAR(20) NOT NULL, ADD method VARCHAR(50) DEFAULT NULL, ADD source_id INT DEFAULT NULL, DROP updated_at');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643AFA43E9DA FOREIGN KEY (closure_id) REFERENCES cash_closure (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_965E643AFA43E9DA ON cash_movement (closure_id)');
    }
}
