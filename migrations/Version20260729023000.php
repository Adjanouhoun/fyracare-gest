<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729023000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sécurise la clôture journalière avec date unique, solde compté, écart et responsable.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql("ALTER TABLE cash_closure ADD closed_by_id INT DEFAULT NULL, ADD closure_date DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', ADD counted_balance INT NOT NULL DEFAULT 0, ADD variance INT NOT NULL DEFAULT 0");
        $this->addSql('UPDATE cash_closure SET closure_date = DATE(closed_at), counted_balance = balance, variance = 0');
        $this->addSql("ALTER TABLE cash_closure MODIFY closure_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)', MODIFY counted_balance INT NOT NULL, MODIFY variance INT NOT NULL");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_16EB9F8D61F2ACE6 ON cash_closure (closure_date)');
        $this->addSql('CREATE INDEX IDX_16EB9F8DE1FA7797 ON cash_closure (closed_by_id)');
        $this->addSql('ALTER TABLE cash_closure ADD CONSTRAINT FK_16EB9F8DE1FA7797 FOREIGN KEY (closed_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE cash_closure DROP FOREIGN KEY FK_16EB9F8DE1FA7797');
        $this->addSql('DROP INDEX IDX_16EB9F8DE1FA7797 ON cash_closure');
        $this->addSql('DROP INDEX UNIQ_16EB9F8D61F2ACE6 ON cash_closure');
        $this->addSql('ALTER TABLE cash_closure DROP closed_by_id, DROP closure_date, DROP counted_balance, DROP variance');
    }
}
