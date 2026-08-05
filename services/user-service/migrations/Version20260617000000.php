<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * User Service initial schema (Phase 3). Owns the identity data; UUID PK,
 * password nullable for OAuth-only accounts, google_id/github_id for OAuth.
 */
final class Version20260617000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User Service: create users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, full_name VARCHAR(255) DEFAULT NULL, google_id VARCHAR(255) DEFAULT NULL, github_id VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('COMMENT ON COLUMN users.id IS \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
