<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251006215202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE clash_closure (id INT AUTO_INCREMENT NOT NULL, day DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', total_cash INT NOT NULL, total_mobile INT NOT NULL, grand_total INT NOT NULL, meta LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', notes LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE client (id INT AUTO_INCREMENT NOT NULL, nometprenom VARCHAR(255) NOT NULL, telephone VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, notes LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment (id INT AUTO_INCREMENT NOT NULL, rdv_id INT NOT NULL, amount INT NOT NULL, methode VARCHAR(255) NOT NULL, paid_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', receipt_number VARCHAR(255) DEFAULT NULL, is_deposit TINYINT(1) NOT NULL, notes LONGTEXT NOT NULL, INDEX IDX_6D28840D4CCE3F86 (rdv_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE prestation (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(255) NOT NULL, duree_min INT NOT NULL, prix INT NOT NULL, is_active TINYINT(1) NOT NULL, description LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rdv (id INT AUTO_INCREMENT NOT NULL, client_id INT NOT NULL, prestation_id INT NOT NULL, start_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(255) NOT NULL, notes LONGTEXT NOT NULL, INDEX IDX_10C31F8619EB6921 (client_id), INDEX IDX_10C31F869E45C554 (prestation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, fullname VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D4CCE3F86 FOREIGN KEY (rdv_id) REFERENCES rdv (id)');
        $this->addSql('ALTER TABLE rdv ADD CONSTRAINT FK_10C31F8619EB6921 FOREIGN KEY (client_id) REFERENCES client (id)');
        $this->addSql('ALTER TABLE rdv ADD CONSTRAINT FK_10C31F869E45C554 FOREIGN KEY (prestation_id) REFERENCES prestation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D4CCE3F86');
        $this->addSql('ALTER TABLE rdv DROP FOREIGN KEY FK_10C31F8619EB6921');
        $this->addSql('ALTER TABLE rdv DROP FOREIGN KEY FK_10C31F869E45C554');
        $this->addSql('DROP TABLE clash_closure');
        $this->addSql('DROP TABLE client');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE prestation');
        $this->addSql('DROP TABLE rdv');
        $this->addSql('DROP TABLE user');
    }
}
