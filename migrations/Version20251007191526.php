<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251007191526 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE client CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE rdv CHANGE notes notes LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD fonction VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP fonction');
        $this->addSql('ALTER TABLE client CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE rdv CHANGE notes notes LONGTEXT NOT NULL');
    }
}
