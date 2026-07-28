<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fige le tarif de la prestation au niveau de chaque rendez-vous.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE rdv ADD price_at_booking INT DEFAULT NULL');
        $this->addSql(
            "UPDATE rdv r
             INNER JOIN prestation pr ON pr.id = r.prestation_id
             LEFT JOIN (
                 SELECT rdv_id, SUM(amount) AS paid_total
                 FROM payment
                 WHERE cancelled_at IS NULL
                 GROUP BY rdv_id
             ) totals ON totals.rdv_id = r.id
             SET r.price_at_booking = CASE
                 WHEN r.payment_status = 'PAYE'
                     AND totals.paid_total BETWEEN (pr.prix / 2) AND (pr.prix * 2)
                     THEN totals.paid_total
                 ELSE pr.prix
             END",
        );
        $this->addSql('ALTER TABLE rdv MODIFY price_at_booking INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE rdv DROP price_at_booking');
    }
}
