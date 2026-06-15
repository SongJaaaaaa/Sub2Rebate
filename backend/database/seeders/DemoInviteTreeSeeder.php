<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoInviteTreeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $users = [
            ['id' => 1001, 'username' => 'u1', 'role' => 'user', 'parent' => null],
            ['id' => 1002, 'username' => 'u2', 'role' => 'user', 'parent' => 1001],
            ['id' => 1003, 'username' => 'u3', 'role' => 'user', 'parent' => 1001],
            ['id' => 1004, 'username' => 'u4', 'role' => 'user', 'parent' => 1001],
            ['id' => 1005, 'username' => 'u5', 'role' => 'user', 'parent' => 1002],
            ['id' => 1006, 'username' => 'u6', 'role' => 'user', 'parent' => 1002],
            ['id' => 1007, 'username' => 'u7', 'role' => 'user', 'parent' => 1003],
            ['id' => 1008, 'username' => 'u8', 'role' => 'user', 'parent' => 1003],
            ['id' => 1009, 'username' => 'u9', 'role' => 'user', 'parent' => 1005],
            ['id' => 1010, 'username' => 'u10', 'role' => 'user', 'parent' => 1005],
            ['id' => 1011, 'username' => 'u11', 'role' => 'user', 'parent' => 1009],
            ['id' => 1012, 'username' => 'u12', 'role' => 'user', 'parent' => 1006],
            ['id' => 1013, 'username' => 'u13', 'role' => 'user', 'parent' => 1007],
        ];

        $admin = User::query()->updateOrCreate(
            ['id' => 9001],
            [
                'username' => 'admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
                'status' => 'active',
            ]
        );
        $admin->forceFill(['password' => Hash::make('123')])->save();

        foreach ($users as $item) {
            $user = User::query()->updateOrCreate(
                ['id' => $item['id']],
                [
                    'username' => $item['username'],
                    'email' => $item['username'].'@example.com',
                    'role' => $item['role'],
                    'status' => 'active',
                    'sub2api_aff_code' => 'AFF'.$item['id'],
                    'sub2api_inviter_id' => $item['parent'],
                ]
            );

            $user->forceFill(['password' => Hash::make('123')])->save();
        }

        $paths = [];
        foreach ($users as $item) {
            $path = $item['parent'] === null
                ? (string) $item['id']
                : $paths[$item['parent']]['path'].'/'.$item['id'];

            $paths[$item['id']] = [
                'parent_user_id' => $item['parent'],
                'path' => $path,
                'depth' => substr_count($path, '/'),
            ];

            DB::table('referral_paths')->updateOrInsert(
                ['user_id' => $item['id']],
                [
                    'parent_user_id' => $item['parent'],
                    'invite_code' => 'AFF'.$item['id'],
                    'path' => $path,
                    'depth' => $paths[$item['id']]['depth'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach ($users as $item) {
            if ($item['role'] !== 'user') {
                continue;
            }

            DB::table('payment_records')->updateOrInsert(
                ['source_type' => 'demo_seed', 'source_id' => 'demo-'.$item['id']],
                [
                    'user_id' => $item['id'],
                    'status' => 'paid',
                    'source_amount' => '100.000000',
                    'source_currency' => 'CNY',
                    'standard_amount' => number_format(100 + ($item['id'] - 1000) * 25, 6, '.', ''),
                    'standard_currency' => 'CNY',
                    'credit_amount' => '100.000000',
                    'config_snapshot' => json_encode(['demo' => true]),
                    'operator_user_id' => 9001,
                    'remark' => '演示测试充值',
                    'paid_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
