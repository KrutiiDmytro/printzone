<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 decomposition — decouple orders from the User domain.
 *
 * Replaces the Doctrine FK orders.user_id → users with a plain scalar reference
 * and adds a user_email snapshot captured at order time. The user_id column is
 * kept (now without a FK constraint).
 */
final class Version20260612160003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 1: decouple orders from users (snapshot user_email, drop FK)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE orders ADD user_email VARCHAR(180) DEFAULT '' NOT NULL");
        // Backfill the snapshot for existing rows from current user data.
        $this->addSql('UPDATE orders o SET user_email = u.email FROM users u WHERE o.user_id = u.id');
        $this->addSql('ALTER TABLE orders ALTER user_email DROP DEFAULT');
        // Drop the cross-domain FK to users (order→user coupling). Keep the user_id column.
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT fk_e52ffdeea76ed395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT fk_e52ffdeea76ed395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE orders DROP user_email');
    }
}
