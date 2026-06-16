<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultQuoteStrategy;

/**
 * Phase 2.2 — restores schema emulation for platforms that don't support
 * database schemas (SQLite, used in tests).
 *
 * Entities map to per-service PostgreSQL schemas (e.g. catalog.products). On
 * PostgreSQL that is emitted verbatim. On SQLite, DBAL's SchemaTool already
 * emulates DDL as "catalog__products", but ORM 3.x dropped the matching DML
 * emulation from DefaultQuoteStrategy::getTableName() — it returns
 * "catalog.products", which SQLite reads as database.table → "no such table".
 *
 * This strategy re-aligns DML with DDL: schema "." table → schema "__" table
 * whenever the platform lacks native schema support. On PostgreSQL it is a
 * no-op (supportsSchemas() === true), so production behaviour is unchanged.
 */
final class SchemaEmulatingQuoteStrategy extends DefaultQuoteStrategy
{
    /**
     * @param ClassMetadata<object> $class
     */
    public function getTableName(ClassMetadata $class, AbstractPlatform $platform): string
    {
        if (!empty($class->table['schema']) && !$platform->supportsSchemas()) {
            return $class->table['schema'].'__'.$class->table['name'];
        }

        return parent::getTableName($class, $platform);
    }
}
