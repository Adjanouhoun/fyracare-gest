<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la traçabilité des annulations de rendez-vous et de paiements.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql("ALTER TABLE payment ADD cancelled_by_id INT DEFAULT NULL, ADD cancelled_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD cancellation_reason LONGTEXT DEFAULT NULL");
        $this->addSql('CREATE INDEX IDX_6D28840D187B2D12 ON payment (cancelled_by_id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_PAYMENT_CANCELLED_BY FOREIGN KEY (cancelled_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql("ALTER TABLE rdv ADD cancelled_by_id INT DEFAULT NULL, ADD cancelled_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD cancellation_reason LONGTEXT DEFAULT NULL");
        $this->addSql('CREATE INDEX IDX_10C31F86187B2D12 ON rdv (cancelled_by_id)');
        $this->addSql('ALTER TABLE rdv ADD CONSTRAINT FK_RDV_CANCELLED_BY FOREIGN KEY (cancelled_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_PAYMENT_CANCELLED_BY');
        $this->addSql('DROP INDEX IDX_6D28840D187B2D12 ON payment');
        $this->addSql('ALTER TABLE payment DROP cancelled_by_id, DROP cancelled_at, DROP cancellation_reason');
        $this->addSql('ALTER TABLE rdv DROP FOREIGN KEY FK_RDV_CANCELLED_BY');
        $this->addSql('DROP INDEX IDX_10C31F86187B2D12 ON rdv');
        $this->addSql('ALTER TABLE rdv DROP cancelled_by_id, DROP cancelled_at, DROP cancellation_reason');
    }
}
