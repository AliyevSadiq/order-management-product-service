<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250301000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create products and categories tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');

        $this->addSql('
            CREATE TABLE categories (
                id UUID NOT NULL DEFAULT uuid_generate_v4(),
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                parent_id UUID DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX uniq_categories_slug ON categories (slug)');
        $this->addSql('CREATE INDEX idx_category_parent ON categories (parent_id)');

        $this->addSql('
            CREATE TABLE products (
                id UUID NOT NULL DEFAULT uuid_generate_v4(),
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                price NUMERIC(10, 2) NOT NULL,
                sku VARCHAR(100) NOT NULL,
                stock INTEGER NOT NULL DEFAULT 0,
                category_id UUID DEFAULT NULL,
                active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        ');

        $this->addSql('CREATE UNIQUE INDEX uniq_products_sku ON products (sku)');
        $this->addSql('CREATE INDEX idx_product_category ON products (category_id)');
        $this->addSql('CREATE INDEX idx_product_active ON products (active)');
        $this->addSql('CREATE INDEX idx_product_price ON products (price)');

        $this->addSql('COMMENT ON COLUMN categories.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN categories.parent_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN categories.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN products.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN products.category_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN products.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN products.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS products');
        $this->addSql('DROP TABLE IF EXISTS categories');
    }
}
