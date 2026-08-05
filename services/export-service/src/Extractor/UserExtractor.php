<?php

declare(strict_types=1);

namespace App\Extractor;

use App\Client\UserClient;

/**
 * Extracts users for export from the User Service over HTTP. The API returns
 * every user unfiltered, so email/role filtering is applied locally.
 */
final class UserExtractor implements ExportExtractorInterface
{
    public function __construct(private readonly UserClient $userClient)
    {
    }

    public function extract(array $filters): array
    {
        $emailNeedle = isset($filters['email']) ? (string) $filters['email'] : '';
        $roleNeedle = isset($filters['role']) ? (string) $filters['role'] : '';

        $rows = [];
        foreach ($this->userClient->fetchAll() as $user) {
            $roles = $user['roles'] ?? [];

            if ('' !== $emailNeedle && false === stripos((string) ($user['email'] ?? ''), $emailNeedle)) {
                continue;
            }
            if ('' !== $roleNeedle && !$this->rolesMatch($roles, $roleNeedle)) {
                continue;
            }

            $rows[] = [
                'id' => (string) ($user['id'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'full_name' => (string) ($user['fullName'] ?? ''),
                'roles' => implode(', ', $roles),
            ];
        }

        return $rows;
    }

    /**
     * @param list<string> $roles
     */
    private function rolesMatch(array $roles, string $needle): bool
    {
        foreach ($roles as $role) {
            if (false !== stripos($role, $needle)) {
                return true;
            }
        }

        return false;
    }
}
