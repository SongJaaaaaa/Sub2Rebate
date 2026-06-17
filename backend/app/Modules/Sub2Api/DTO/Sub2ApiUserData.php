<?php

namespace App\Modules\Sub2Api\DTO;

use Carbon\CarbonImmutable;
use stdClass;

class Sub2ApiUserData
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly string $role,
        public readonly string $status,
        public readonly string $balance,
        public readonly string $totalRecharged,
        public readonly ?string $affCode,
        public readonly ?int $inviterId,
        public readonly ?CarbonImmutable $createdAt,
        public readonly ?CarbonImmutable $updatedAt,
    ) {
    }

    public static function fromRow(stdClass $row): self
    {
        return new self(
            id: (int) $row->id,
            email: (string) ($row->email ?? ''),
            username: (string) ($row->username ?? ''),
            passwordHash: (string) ($row->password_hash ?? ''),
            role: (string) ($row->role ?? 'user'),
            status: (string) ($row->status ?? 'active'),
            balance: (string) ($row->balance ?? '0'),
            totalRecharged: (string) ($row->total_recharged ?? '0'),
            affCode: $row->aff_code !== null ? (string) $row->aff_code : null,
            inviterId: $row->inviter_id !== null ? (int) $row->inviter_id : null,
            createdAt: self::parseTime($row->created_at ?? null),
            updatedAt: self::parseTime($row->updated_at ?? null),
        );
    }

    private static function parseTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value)->timezone('Asia/Shanghai');
    }
}
