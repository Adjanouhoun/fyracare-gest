<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729013000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le motif d’annulation aux mouvements manuels de caisse.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE cash_movement ADD deletion_reason LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE cash_movement DROP deletion_reason');
    }
}
