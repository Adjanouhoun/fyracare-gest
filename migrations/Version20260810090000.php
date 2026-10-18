<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260810090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la réouverture traçable des clôtures de caisse sans supprimer l’historique.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql("CREATE TABLE cash_closure_reopening (id INT AUTO_INCREMENT NOT NULL, closure_id INT NOT NULL, reopened_by_id INT DEFAULT NULL, previous_closed_by_id INT DEFAULT NULL, reclosed_by_id INT DEFAULT NULL, reopened_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', reason LONGTEXT NOT NULL, previous_closed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', previous_total_in INT NOT NULL, previous_total_out INT NOT NULL, previous_balance INT NOT NULL, previous_counted_balance INT NOT NULL, previous_variance INT NOT NULL, previous_notes LONGTEXT DEFAULT NULL, previous_late_reason LONGTEXT DEFAULT NULL, reclosed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_CASH_REOPEN_CLOSURE (closure_id), INDEX IDX_CASH_REOPEN_AUTHOR (reopened_by_id), INDEX IDX_CASH_REOPEN_PREVIOUS_AUTHOR (previous_closed_by_id), INDEX IDX_CASH_REOPEN_RECLOSED_AUTHOR (reclosed_by_id), CONSTRAINT FK_CASH_REOPEN_CLOSURE FOREIGN KEY (closure_id) REFERENCES cash_closure (id) ON DELETE CASCADE, CONSTRAINT FK_CASH_REOPEN_AUTHOR FOREIGN KEY (reopened_by_id) REFERENCES user (id) ON DELETE SET NULL, CONSTRAINT FK_CASH_REOPEN_PREVIOUS_AUTHOR FOREIGN KEY (previous_closed_by_id) REFERENCES user (id) ON DELETE SET NULL, CONSTRAINT FK_CASH_REOPEN_RECLOSED_AUTHOR FOREIGN KEY (reclosed_by_id) REFERENCES user (id) ON DELETE SET NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('DROP TABLE cash_closure_reopening');
    }
}
