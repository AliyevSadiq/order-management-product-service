<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250318000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create stock_reservations table for saga stock management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_reservations (
            id UUID NOT NULL,
            items JSON NOT NULL,
            status VARCHAR(32) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('COMMENT ON COLUMN stock_reservations.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN stock_reservations.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN stock_reservations.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE INDEX idx_reservation_status ON stock_reservations (status)');
        $this->addSql('CREATE INDEX idx_reservation_expires ON stock_reservations (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stock_reservations');
    }
}
