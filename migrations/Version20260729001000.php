<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le délai de rappel et l’historique des changements de statut des rendez-vous.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE company_settings ADD confirmation_reminder_hours INT NOT NULL DEFAULT 24');
        $this->addSql('ALTER TABLE company_settings MODIFY confirmation_reminder_hours INT NOT NULL');
        $this->addSql("CREATE TABLE appointment_status_history (
            id INT AUTO_INCREMENT NOT NULL,
            rdv_id INT NOT NULL,
            changed_by_id INT DEFAULT NULL,
            old_status VARCHAR(20) NOT NULL,
            new_status VARCHAR(20) NOT NULL,
            changed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            note LONGTEXT DEFAULT NULL,
            INDEX IDX_191705864CCE3F86 (rdv_id),
            INDEX IDX_19170586828AD0A0 (changed_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE appointment_status_history ADD CONSTRAINT FK_191705864CCE3F86 FOREIGN KEY (rdv_id) REFERENCES rdv (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointment_status_history ADD CONSTRAINT FK_19170586828AD0A0 FOREIGN KEY (changed_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('DROP TABLE appointment_status_history');
        $this->addSql('ALTER TABLE company_settings DROP confirmation_reminder_hours');
    }
}
