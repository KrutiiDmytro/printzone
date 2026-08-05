<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 decomposition — decouple carts from the User domain.
 *
 * Replaces the Doctrine FK carts.user_id → users with a plain scalar reference.
 * The user_id column and its UNIQUE constraint (one cart per user) are kept;
 * only the cross-domain foreign key is dropped.
 */
final class Version20260612160002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1: decouple carts from users (drop FK, keep unique user_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE carts DROP CONSTRAINT fk_4e004aaca76ed395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE carts ADD CONSTRAINT fk_4e004aaca76ed395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
