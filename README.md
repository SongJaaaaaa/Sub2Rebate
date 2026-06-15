# Sub2Rebate 分销返利系统

## 项目概述

Sub2Rebate 是 Sub2API 的独立分销返利系统，采用无限级金字塔 + 衰减系数算法实现多级分发。

## 技术栈

- **后端**: Laravel 12 + Filament 3
- **前端**: Vue 3 + Vite + Element Plus + Pinia
- **数据库**: PostgreSQL
- **缓存/队列**: Redis
- **部署**: Docker + Nginx

## 文档

详细文档见 `docs/` 目录。

## 开发

```bash
# 后端
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate

# 前端
cd frontend
npm install
npm run dev
```

## License

Private
