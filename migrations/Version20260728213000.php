<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Lie les mouvements de caisse aux paiements et garantit l’unicité des reçus.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE cash_movement ADD payment_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_965E643A4C3A3BB ON cash_movement (payment_id)');
        $this->addSql('ALTER TABLE cash_movement ADD CONSTRAINT FK_CASH_MOVEMENT_PAYMENT FOREIGN KEY (payment_id) REFERENCES payment (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6D28840DB0ADB74C ON payment (receipt_number)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('DROP INDEX UNIQ_6D28840DB0ADB74C ON payment');
        $this->addSql('ALTER TABLE cash_movement DROP FOREIGN KEY FK_CASH_MOVEMENT_PAYMENT');
        $this->addSql('DROP INDEX IDX_965E643A4C3A3BB ON cash_movement');
        $this->addSql('ALTER TABLE cash_movement DROP payment_id');
    }
}
