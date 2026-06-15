# Sub2Rebate 后端

Laravel 12 + Sanctum + Filament 3 后端骨架。

## 当前内容

- Laravel 12 基础启动文件。
- PostgreSQL、Redis、Sub2API 只读库的环境变量示例。
- `GET /api/v1/health` 健康检查接口，返回统一响应格式。
- `App\Support\ApiResponse` 统一 API 响应工具。
- 健康检查 Feature Test。
- B1 模块基础结构：`app/Modules/*`、模块 ServiceProvider、基础错误码。
- B2 认证基础：Sub2API 用户读取、bcrypt 登录校验、Sanctum token 签发。
- B2 用户资料：聚合 Sub2API `users` / `user_affiliates.aff_code` 和本地 `referral_paths`，本地 `users` 同步保存 Sub2API affiliate 快照。
- B2 Feature Test：登录同步、token 签发、错误密码、禁用用户、未登录、账户资料邀请链接聚合。

## 已接入接口

```text
GET  /api/v1/health
POST /api/v1/auth/login
POST /api/v1/auth/logout
GET  /api/v1/auth/me
GET  /api/v1/account/profile
```

`account/profile` 会返回 Sub2Rebate 自己的邀请码/邀请链接，也会返回 Sub2API 原生 `aff_code` 和邀请链接。

## 本地启动

```bash
export PATH="/Users/macbook/Desktop/分销/.tools/conda/php/bin:$PATH"
composer install
cp .env.example .env
php artisan key:generate
php artisan serve
```

运行测试：

```bash
php artisan test
```

本地/测试环境执行 seeder 后会生成演示账号，方便快速验证：

```text
管理员：admin / 123
普通用户：u1 / 123
```

健康检查：

```bash
curl http://127.0.0.1:8000/api/v1/health
```

预期响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "status": "ok",
    "version": "v1"
  }
}
```

## 说明

本项目已在 `.tools/conda/php/bin` 准备本地 PHP 8.5.7 和 Composer 2.10.1；`composer.json` 通过 `config.platform.php = 8.2.0` 固定依赖解析平台，避免锁到 PHP 8.4+ 专属依赖。

当前完整 `composer install` 已可完成，Laravel 运行时依赖、PHPUnit dev 依赖、路由加载和 Feature Test 都可用。为避免格式化工具下载失败阻塞 B1/B2 验证，当前暂未接入 `laravel/pint`。

注意：本地工具链当前是 PHP 8.5；依赖按 `config.platform.php = 8.2.0` 锁定，部署/CI 建议使用 PHP 8.2/8.3/8.4。
