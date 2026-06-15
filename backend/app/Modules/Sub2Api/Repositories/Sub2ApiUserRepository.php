<?php

namespace App\Modules\Sub2Api\Repositories;

use App\Modules\Sub2Api\DTO\Sub2ApiUserData;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

class Sub2ApiUserRepository
{
    private string $connection = 'sub2api';

    public function findByAccount(string $account): ?Sub2ApiUserData
    {
        $value = mb_strtolower(trim($account));
        if ($value === '') {
            return null;
        }

        $row = $this->baseQuery()
            ->where(function ($query) use ($value): void {
                $query
                    ->whereRaw('LOWER(u.email) = ?', [$value])
                    ->orWhereRaw('LOWER(u.username) = ?', [$value]);
            })
            ->first();

        return $row ? Sub2ApiUserData::fromRow($row) : null;
    }

    public function findById(int $id): ?Sub2ApiUserData
    {
        if ($id <= 0) {
            return null;
        }

        $row = $this->baseQuery()
            ->where('u.id', $id)
            ->first();

        return $row ? Sub2ApiUserData::fromRow($row) : null;
    }

    /**
     * @return array<int, string>
     */
    public function identityProviders(int $userId): array
    {
        return $this->db()
            ->table('auth_identities')
            ->where('user_id', $userId)
            ->distinct()
            ->orderBy('provider_type')
            ->pluck('provider_type')
            ->map(fn (mixed $item): string => (string) $item)
            ->values()
            ->all();
    }

    private function baseQuery(): mixed
    {
        return $this->db()
            ->table('users as u')
            ->leftJoin('user_affiliates as ua', 'ua.user_id', '=', 'u.id')
            ->select([
                'u.id',
                'u.email',
                'u.username',
                'u.password_hash',
                'u.role',
                'u.status',
                'u.balance',
                'u.total_recharged',
                'ua.aff_code',
                'ua.inviter_id',
                'u.created_at',
                'u.updated_at',
            ])
            ->whereNull('u.deleted_at');
    }

    private function db(): ConnectionInterface
    {
        return DB::connection($this->connection);
    }
}
