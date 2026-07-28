<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace la suppression des utilisateurs par une désactivation traçable.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql("ALTER TABLE user ADD deactivated_by_id INT DEFAULT NULL, ADD is_active TINYINT(1) NOT NULL DEFAULT 1, ADD deactivated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE user MODIFY is_active TINYINT(1) NOT NULL');
        $this->addSql('CREATE INDEX IDX_8D93D649BDC76BAA ON user (deactivated_by_id)');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649BDC76BAA FOREIGN KEY (deactivated_by_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Cette migration est exclusivement prévue pour MySQL.',
        );

        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649BDC76BAA');
        $this->addSql('DROP INDEX IDX_8D93D649BDC76BAA ON user');
        $this->addSql('ALTER TABLE user DROP deactivated_by_id, DROP is_active, DROP deactivated_at');
    }
}
