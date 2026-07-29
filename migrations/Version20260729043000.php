<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729043000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve directement sur chaque paiement l’utilisateur ayant réalisé l’encaissement.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE payment ADD received_by_id INT DEFAULT NULL');
        $this->addSql('UPDATE payment p INNER JOIN cash_movement m ON m.payment_id = p.id SET p.received_by_id = m.created_by_id WHERE p.received_by_id IS NULL AND m.created_by_id IS NOT NULL');
        $this->addSql('CREATE INDEX IDX_6D28840D4418C7B7 ON payment (received_by_id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D4418C7B7 FOREIGN KEY (received_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D4418C7B7');
        $this->addSql('DROP INDEX IDX_6D28840D4418C7B7 ON payment');
        $this->addSql('ALTER TABLE payment DROP received_by_id');
    }
}
