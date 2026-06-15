# Sub2Rebate 前端

Vue 3 用户前端，基于 Vite + TypeScript + Element Plus + Tailwind CSS。

## 开发命令

```bash
npm install
npm run dev
npm run build
npm run typecheck
```

需要 Node 18+。

## 联调开发（默认模式）

前端默认连接真实后端 API。开发时需要同时启动后端：

```bash
# 终端 1：启动后端
cd backend && php artisan serve

# 终端 2：启动前端
cd frontend && npm run dev
```

Vite dev server 会将 `/api` 请求代理到 `http://127.0.0.1:8000`，无需额外 CORS 配置。

## 环境变量

复制 `.env.example` 后按需调整：

```bash
VITE_API_BASE_URL=/api/v1
VITE_USE_MOCK=false
```

- `VITE_USE_MOCK=false`（默认）：走真实 API，需要后端运行。
- `VITE_USE_MOCK=true`：启用 Mock 模式，不依赖后端即可完成所有页面开发。

## 目录说明

- `src/api/`：按模块封装 API，内置 Mock 开关（`useMock` 为 true 时走本地模拟数据）。
- `src/components/common/`：通用组件（AppCard、MetricCard、PageHeader、StatusTag、EmptyState）。
- `src/components/layout/`：布局组件（SideNav 含移动端抽屉、TopBar）。
- `src/layouts/`：页面布局（AuthLayout、UserLayout）。
- `src/mocks/`：Mock 数据，与 `docs/API_CONTRACT.md` 保持一致。
- `src/router/`：路由和登录守卫。
- `src/stores/`：Pinia 状态管理。
- `src/styles/`：设计 Token、Element Plus 主题覆盖、全局样式。
- `src/types/`：TypeScript 类型定义，与 API 契约对齐。
- `src/utils/`：请求封装、金额格式化、状态映射。
- `src/views/`：页面组件。

## 已完成任务

- F0：Vite + Vue 3 + TypeScript 骨架。
- F1：设计 Token 完善、响应式布局（移动端抽屉导航）、通用组件。
- F2：API 层重写 + Mock 开关 + 全面 Mock 数据覆盖。
- F3：登录页（表单校验、loading、错误提示）、auth store 接入 API、路由守卫（fetchMe + 401 重定向）。
- F4：前端从 Mock 切换到真实 API，Vite proxy 代理后端。

## Mock 模式使用

设置 `VITE_USE_MOCK=true`，所有 API 调用返回与 `docs/API_CONTRACT.md` 一致的 Mock 数据。所有页面可跑通完整交互流程。

Mock 账号：
- 普通用户: u1 / 123
- 管理员: admin / 123
