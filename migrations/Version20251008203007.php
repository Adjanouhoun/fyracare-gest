<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251008203007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_closure ADD closed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD balance INT NOT NULL, DROP from_at, DROP to_at, DROP opening_balance, DROP closing_balance, DROP counted_cash, DROP discrepancy, DROP code');
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643A4C3A3BB');
        $this->addSql('DROP INDEX IDX_965E643A4C3A3BB ON cash_movement');
        $this->addSql('ALTER TABLE cash_movement DROP payment_id, DROP updated_at, CHANGE type type VARCHAR(10) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE cash_closure ADD to_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD closing_balance INT NOT NULL, ADD counted_cash INT DEFAULT NULL, ADD discrepancy INT DEFAULT NULL, ADD code VARCHAR(32) NOT NULL, CHANGE closed_at from_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE balance opening_balance INT NOT NULL');
        $this->addSql('ALTER TABLE cash_movement ADD payment_id INT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE type type VARCHAR(3) NOT NULL');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643A4C3A3BB FOREIGN KEY (payment_id) REFERENCES payment (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_965E643A4C3A3BB ON cash_movement (payment_id)');
    }
}
