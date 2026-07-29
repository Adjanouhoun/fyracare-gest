<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Détecte les clôtures de caisse oubliées et conserve le motif des clôtures tardives.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql("ALTER TABLE company_settings ADD cash_closure_enforcement_start_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");
        $this->addSql('UPDATE company_settings SET cash_closure_enforcement_start_date = CURRENT_DATE WHERE cash_closure_enforcement_start_date IS NULL');
        $this->addSql("ALTER TABLE company_settings MODIFY cash_closure_enforcement_start_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)'");
        $this->addSql('ALTER TABLE cash_closure ADD late_reason LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE company_settings DROP cash_closure_enforcement_start_date');
        $this->addSql('ALTER TABLE cash_closure DROP late_reason');
    }
}
