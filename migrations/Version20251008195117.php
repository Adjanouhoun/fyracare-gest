<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251008195117 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cash_closure (id INT AUTO_INCREMENT NOT NULL, from_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', to_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', total_in INT NOT NULL, total_out INT NOT NULL, opening_balance INT NOT NULL, closing_balance INT NOT NULL, counted_cash INT DEFAULT NULL, discrepancy INT DEFAULT NULL, code VARCHAR(32) NOT NULL, notes LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cash_movement (id INT AUTO_INCREMENT NOT NULL, closure_id INT DEFAULT NULL, type VARCHAR(3) NOT NULL, amount INT NOT NULL, source VARCHAR(20) NOT NULL, method VARCHAR(50) DEFAULT NULL, source_id INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_965E643AFA43E9DA (closure_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_965E643AFA43E9DA FOREIGN KEY (closure_id) REFERENCES cash_closure (id)');
        $this->addSql('DROP TABLE clash_closure');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE clash_closure (id INT AUTO_INCREMENT NOT NULL, day DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', total_cash INT NOT NULL, total_mobile INT NOT NULL, grand_total INT NOT NULL, meta LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:array)\', notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_965E643AFA43E9DA');
        $this->addSql('DROP TABLE cash_closure');
        $this->addSql('DROP TABLE cash_movement');
    }
}
