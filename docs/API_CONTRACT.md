# Sub2Rebate API 契约

> 版本：v1
> 路由前缀：`/api/v1`
> 适用范围：Vue 用户端、Laravel API、必要的后台自定义接口。
> 维护状态：本文是前后端联调的唯一接口口径；新增、修改、删除接口时必须同步更新本文。

## 1. 基础约定

### 1.1 响应格式

所有接口统一返回 JSON：

```json
{
  "code": 0,
  "message": "ok",
  "data": {}
}
```

错误响应：

```json
{
  "code": 44001,
  "message": "余额不足",
  "data": null
}
```

分页响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [],
    "page": 1,
    "pageSize": 20,
    "total": 0
  }
}
```

### 1.2 HTTP 状态码

| 状态码 | 场景 |
|---:|---|
| 200 | 成功 |
| 201 | 创建成功 |
| 400 | 参数格式错误 |
| 401 | 未登录或 token 失效 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 422 | 业务规则不通过 |
| 429 | 请求过于频繁 |
| 500 | 服务端异常 |

### 1.3 认证方式

用户端复用 Sub2API 账号登录，由 Sub2Rebate 后端签发自己的访问 token。

请求头：

```text
Authorization: Bearer <token>
Accept: application/json
```

公开接口：

- `GET /api/v1/health`
- `POST /api/v1/auth/login`

其余用户端接口默认需要登录。

### 1.4 字段格式

| 类型 | 约定 |
|---|---|
| ID | 整数，字段名使用 `id` 或 `xxxId` |
| 金额 | 字符串，保留 2 位小数，例如 `"1280.00"` |
| 比例 | 字符串，保留最多 6 位小数，例如 `"0.150000"` |
| 时间 | 字符串，格式为 `YYYY-MM-DD HH:mm:ss`，时区 Asia/Shanghai |
| 布尔值 | JSON boolean |
| 枚举 | 使用小写英文字符串 |

金额规则：

- 后端返回金额必须是字符串。
- 前端只展示金额，不做关键金额计算。
- 前端提交金额也使用字符串，例如 `"100.00"`。
- 充值、返利、提现、余额调整必须由后端按配置计算，不能信任前端传入的派生金额。
- 充值事件需要保存原始来源金额、标准金额、币种/单位和当时使用的换算配置快照。
- 当前默认金额换算为 1 人民币 = 1 Sub2API 额度/刀，但必须通过后台配置维护。

### 1.5 安全与权限

- 普通用户只能访问自己的用户端资源。
- 管理后台优先使用 Sub2API `users.role = admin` 判断管理员身份，并允许本系统角色表做补充限制。
- 涉及充值补录、返利事件、提现审核、配置修改、余额调整的操作必须要求管理员权限。
- 后台敏感操作必须要求备注，并写入审计日志。
- `users.balance` / `users.total_recharged` 快照只能用于对账、异常提示或人工确认，不能直接自动触发返利。
- 返利事件必须有 `sourceType + sourceId` 幂等键。

### 1.6 分页参数

列表接口统一支持：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---:|---|
| page | number | 1 | 当前页 |
| pageSize | number | 20 | 每页条数 |

第一版 `pageSize` 最大值为 100。

### 1.7 接口文档维护规则

- `docs/API_CONTRACT.md` 是项目唯一接口文档。前端 Mock、前端 API 封装、后端路由、Feature Test 都以本文为准。
- 新增接口时，必须补充：请求方法、路径、认证要求、参数、请求示例、响应示例、错误场景。
- 修改接口时，必须同步修改对应字段、枚举、分页格式、错误码和示例。
- 删除接口时，必须从本文移除或标记为废弃，并说明替代接口。
- 涉及金额、权限、返利、提现、余额调整的接口，必须先补本文，再写代码和测试。
- 每次接口开发完成后，必须执行或核对 `php artisan route:list --path=api/v1`，确保路由与本文一致。
- 如果没有更新本文，本次接口开发不算完成。

### 1.8 当前已实现接口索引

当前后端 `/api/v1` 已实现 55 个路由。

| 模块 | 方法 | 路径 | 权限 |
|---|---|---|---|
| 基础 | GET | `/api/v1/health` | 公开 |
| 认证 | POST | `/api/v1/auth/login` | 公开 |
| 认证 | POST | `/api/v1/auth/logout` | 登录 |
| 认证 | GET | `/api/v1/auth/me` | 登录 |
| 账户 | GET | `/api/v1/account/profile` | 登录 |
| 账户 | PUT | `/api/v1/account/profile` | 登录 |
| 账户 | POST | `/api/v1/account/change-password` | 登录 |
| 配置 | GET | `/api/v1/config/items` | 登录 |
| 邀请 | GET | `/api/v1/invite/me` | 登录 |
| 邀请 | POST | `/api/v1/invite/bind` | 登录 |
| 邀请 | GET | `/api/v1/invite/tree` | 登录 |
| 邀请 | GET | `/api/v1/invite/records` | 登录 |
| 提现 | GET | `/api/v1/withdraw/config` | 登录 |
| 提现 | GET | `/api/v1/withdraw/account` | 登录 |
| 提现 | POST | `/api/v1/withdraw/account` | 登录 |
| 提现 | POST | `/api/v1/withdraw/apply` | 登录 |
| 提现 | GET | `/api/v1/withdraw/records` | 登录 |
| 仪表盘 | GET | `/api/v1/dashboard/summary` | 登录 |
| 仪表盘 | GET | `/api/v1/dashboard/rebate-trends` | 登录 |
| 仪表盘 | GET | `/api/v1/dashboard/recent-activities` | 登录 |
| 返利 | GET | `/api/v1/rebate/records` | 登录 |
| 推广 | GET | `/api/v1/promotion/summary` | 登录 |
| 推广 | GET | `/api/v1/promotion/conversions` | 登录 |
| 推广 | GET | `/api/v1/promotion/rebate-records` | 登录 |
| 后台 | GET | `/api/v1/admin/dashboard` | 管理员 |
| 后台 | GET | `/api/v1/admin/trends` | 管理员 |
| 后台 | GET | `/api/v1/admin/users` | 管理员 |
| 后台 | POST | `/api/v1/admin/users/{id}/ban` | 管理员 |
| 后台 | POST | `/api/v1/admin/users/{id}/unban` | 管理员 |
| 后台 | POST | `/api/v1/admin/users/{id}/role` | 管理员 |
| 后台 | GET | `/api/v1/admin/withdrawals` | 管理员 |
| 后台 | POST | `/api/v1/admin/withdrawals/{id}/approve` | 管理员 |
| 后台 | POST | `/api/v1/admin/withdrawals/{id}/reject` | 管理员 |
| 后台 | POST | `/api/v1/admin/withdrawals/{id}/paid` | 管理员 |
| 后台 | GET | `/api/v1/admin/rebate-config` | 管理员 |
| 后台 | PUT | `/api/v1/admin/rebate-config` | 管理员 |
| 后台 | POST | `/api/v1/admin/balance-adjust` | 管理员 |
| 后台 | POST | `/api/v1/admin/users/{id}/api-quota` | 管理员 |
| 后台 | GET | `/api/v1/admin/users/{id}/balance-records` | 管理员 |
| 后台 | GET | `/api/v1/admin/relationship-tree` | 管理员 |
| 后台 | GET | `/api/v1/admin/audit-logs` | 管理员 |
| 后台 | GET | `/api/v1/admin/user-rebate-overrides` | 管理员 |
| 后台 | GET | `/api/v1/admin/users/{id}/rebate-override` | 管理员 |
| 后台 | PUT | `/api/v1/admin/users/{id}/rebate-override` | 管理员 |

## 2. 状态枚举

### 2.1 用户角色

| 值 | 说明 |
|---|---|
| user | 普通用户 |
| admin | 管理员 |

### 2.2 返利类型

| 值 | 说明 |
|---|---|
| milestone | 里程碑奖励 |
| decay | 衰减系数多级返利 |
| manual | 管理员手工调整 |

### 2.3 返利记录状态

| 值 | 说明 |
|---|---|
| pending | 待确认 |
| confirmed | 已确认 |
| frozen | 冻结中 |
| available | 可提现 |
| canceled | 已取消 |

### 2.3A 用户返利资格

| 值 | 说明 |
|---|---|
| eligible | 可获得新返利 |
| disabled | 不可获得新返利，但不代表禁止登录 |

说明：账号登录状态仍由 `users.status` 控制；返利资格用于邀请树置灰和返利计算。

### 2.3B 失效节点返利处理模式

| 值 | 说明 |
|---|---|
| platform | 失效节点对应返利不发放，归平台保留 |
| exclude_recalculate | 排除失效节点后，对剩余有效上级按衰减系数重算 |

### 2.4 提现状态

| 值 | 说明 |
|---|---|
| pending | 待审核 |
| approved | 已审核 |
| paid | 已打款 |
| rejected | 已拒绝 |
| failed | 打款失败 |
| canceled | 已取消 |

### 2.5 支付状态

| 值 | 说明 |
|---|---|
| created | 已创建 |
| paid | 已支付 |
| failed | 支付失败 |
| closed | 已关闭 |
| refunded | 已退款 |

### 2.6 返利事件状态

| 值 | 说明 |
|---|---|
| pending | 待处理 |
| processing | 处理中 |
| processed | 已处理 |
| failed | 处理失败 |
| ignored | 已忽略 |

## 3. 错误码

当前后端已实现的基础错误码：

| 错误码 | 含义 |
|---:|---|
| 0 | 成功 |
| 40001 | 参数错误 |
| 40101 | 未登录 |
| 40102 | 登录失败 |
| 40301 | 无权限 |
| 40302 | 账号已被禁用 |
| 40401 | 资源不存在 |
| 42201 | 校验失败 |
| 50001 | 服务端异常 |

业务错误后续可以按模块扩展，但扩展前必须先补充到本节：

| 预留段 | 模块 |
|---:|---|
| 41000-41999 | 邀请 |
| 43000-43999 | 返利 |
| 44000-44999 | 提现/余额 |
| 45000-45999 | 充值/返利事件 |
| 47000-47999 | 风控 |

## 4. 通用对象

### 4.1 User

```json
{
  "id": 1001,
  "username": "demo",
  "nickname": "演示用户",
  "email": "demo@example.com",
  "avatar": "",
  "role": "user",
  "createdAt": "2026-06-13 12:00:00"
}
```

### 4.2 Balance

```json
{
  "availableAmount": "1280.00",
  "frozenAmount": "200.00",
  "totalAmount": "1480.00",
  "withdrawnAmount": "500.00"
}
```

### 4.3 RebateRecord

```json
{
  "id": 9001,
  "eventId": 8001,
  "payerUserId": 1008,
  "receiverUserId": 1001,
  "type": "decay",
  "level": 1,
  "sourceAmount": "100.00",
  "rebateAmount": "9.62",
  "status": "available",
  "remark": "多级返利",
  "createdAt": "2026-06-13 12:00:00"
}
```

## 5. 基础接口

### 5.1 健康检查

```text
GET /api/v1/health
```

响应：

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

## 6. 认证接口

### 6.1 登录

```text
POST /api/v1/auth/login
```

说明：

- 登录接口有频率限制，默认同一账号 + IP 每分钟最多 5 次。
- 访问 token 由 Sanctum 签发，默认有效期 7 天，可通过 `SANCTUM_EXPIRATION` 调整。
- 管理员封禁用户后，会撤销该用户已有 token；登录态接口也会拦截非 active 用户。

请求：

```json
{
  "account": "demo",
  "password": "123456"
}
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "token": "plain-text-token",
    "tokenType": "Bearer",
    "user": {
      "id": 1001,
      "username": "demo",
      "nickname": "演示用户",
      "email": "demo@example.com",
      "avatar": "",
      "role": "user",
      "createdAt": "2026-06-13 12:00:00"
    }
  }
}
```

### 6.2 退出登录

```text
POST /api/v1/auth/logout
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": null
}
```

### 6.3 当前用户

```text
GET /api/v1/auth/me
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "user": {
      "id": 1001,
      "username": "demo",
      "nickname": "演示用户",
      "email": "demo@example.com",
      "avatar": "",
      "role": "user",
      "createdAt": "2026-06-13 12:00:00"
    },
    "balance": {
      "availableAmount": "1280.00",
      "frozenAmount": "200.00",
      "totalAmount": "1480.00",
      "withdrawnAmount": "500.00"
    }
  }
}
```

## 7. 仪表盘接口

### 7.1 汇总数据

```text
GET /api/v1/dashboard/summary
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "availableAmount": "1280.00",
    "frozenAmount": "200.00",
    "totalAmount": "1480.00",
    "withdrawnAmount": "500.00",
    "totalRebateAmount": "15880.00",
    "pendingWithdrawCount": 2
  }
}
```

### 7.2 返利趋势

```text
GET /api/v1/dashboard/rebate-trends?range=30d
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| range | string | 7d | `7d`、`30d`；其他值按 `7d` 处理 |

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "range": "30d",
    "list": [
      {
        "date": "2026-06-13",
        "amount": "128.00"
      }
    ]
  }
}
```

### 7.3 最近动态

```text
GET /api/v1/dashboard/recent-activities
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 1,
        "module": "rebate",
        "action": "rebate.decay_granted",
        "remark": "多级返利",
        "createdAt": "2026-06-13 12:00:00"
      }
    ]
  }
}
```

## 8. 邀请接口

### 8.1 我的邀请信息

```text
GET /api/v1/invite/me
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "inviteCode": "",
    "inviteUrl": "",
    "sub2ApiAffCode": "SUB2AFF12",
    "sub2ApiInviteUrl": "https://api.sjiaa.cc.cd/register?aff=SUB2AFF12",
    "sub2ApiAffiliatePageUrl": "https://api.sjiaa.cc.cd/affiliate",
    "parent": {
      "id": 1000,
      "username": "parent",
      "nickname": "上级用户"
    },
    "depth": 2,
    "directInviteCount": 18,
    "teamInviteCount": 126
  }
}
```

说明：用户端只展示 `sub2ApiAffCode` / `sub2ApiInviteUrl` / `sub2ApiAffiliatePageUrl`。`inviteCode` / `inviteUrl` 保留为空字符串，用于兼容旧前端字段；本地多级层级由 Sub2API `user_affiliates.inviter_id` 同步到 `referral_paths` 后计算。

当前后端已实现该接口；`directInviteCount` 和 `teamInviteCount` 基于 `referral_paths` 统计。

### 8.2 绑定邀请码

```text
POST /api/v1/invite/bind
```

请求：

```json
{
  "inviteCode": "ABCD12"
}
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "bound": true,
    "parent": {
      "id": 1000,
      "username": "parent",
      "nickname": "上级用户"
    }
  }
}
```

当前后端已实现一次绑定、自邀请拦截、重复绑定拦截和基础循环关系拦截。管理员修正邀请关系归入后台阶段。

### 8.3 邀请树

```text
GET /api/v1/invite/tree?maxDepth=3
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---:|---|
| maxDepth | number | 3 | 返回树深度，仅控制展示层级 |

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "root": {
      "id": 1001,
      "username": "demo",
      "nickname": "演示用户",
      "level": 0,
      "children": [
        {
          "id": 1008,
          "username": "child",
          "nickname": "下级用户",
          "level": 1,
          "rebateStatus": "eligible",
          "nodeState": "active",
          "children": []
        }
      ]
    }
  }
}
```

当前后端已实现 `maxDepth` 裁剪，第一版用户端只能以当前登录用户为根节点查看自己的下级树。

待实现：节点需补充 `rebateStatus` 和 `nodeState`，用于管理端邀请树置灰；用户端可默认只按有效节点展示统计。

### 8.4 邀请记录

```text
GET /api/v1/invite/records?page=1&pageSize=20
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 1008,
        "username": "child",
        "nickname": "下级用户",
        "level": 1,
        "totalPaidAmount": "300.00",
        "totalRebateAmount": "30.00",
        "boundAt": "2026-06-13 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

当前后端已实现邀请记录分页；`totalPaidAmount` 和 `totalRebateAmount` 在 B5/B6/B7 接入充值和返利流水前返回 `"0.00"`。

## 9. 推广与返利接口

### 9.1 推广汇总

```text
GET /api/v1/promotion/summary
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "inviteCode": "",
    "inviteUrl": "",
    "sub2ApiAffCode": "SUB2AFF12",
    "sub2ApiInviteUrl": "https://api.sjiaa.cc.cd/register?aff=SUB2AFF12",
    "sub2ApiAffiliatePageUrl": "https://api.sjiaa.cc.cd/affiliate",
    "directInviteCount": 18,
    "teamInviteCount": 126,
    "conversionCount": 48,
    "totalPaidUserCount": 48,
    "conversionRate": "0.380952",
    "totalRebateAmount": "15880.00"
  }
}
```

### 9.2 转化记录

```text
GET /api/v1/promotion/conversions?page=1&pageSize=20
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 1008,
        "username": "child",
        "level": 1,
        "payCount": 2,
        "totalPaidAmount": "100.00",
        "boundAt": "2026-06-13 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

### 9.3 返利记录

```text
GET /api/v1/rebate/records?page=1&pageSize=20&type=decay&status=available
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| type | string |  | `milestone`、`decay`、`manual` |

说明：当前后端已实现 `type` 筛选；`status` 参数文档预留，后端暂未使用。

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 9001,
        "eventId": 8001,
        "payerUserId": 1008,
        "receiverUserId": 1001,
        "type": "decay",
        "level": 1,
        "sourceAmount": "100.00",
        "rebateAmount": "9.62",
        "status": "available",
        "remark": "多级返利",
        "createdAt": "2026-06-13 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

## 10. 提现接口

### 10.1 提现配置

```text
GET /api/v1/withdraw/config
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "minAmount": "50.00",
    "reviewMode": "manual",
    "dailyLimit": 1,
    "freezeDays": 0,
    "tips": [
      "提现最低金额为 50.00 元",
      "每日最多提现 1 次",
      "第一版采用人工审核和人工打款"
    ]
  }
}
```

### 10.2 提现账号

```text
GET /api/v1/withdraw/account
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "account": {
      "id": 1,
      "type": "alipay",
      "realName": "张三",
      "accountNo": "demo@example.com",
      "createdAt": "2026-06-13 12:00:00",
      "updatedAt": "2026-06-13 12:00:00"
    }
  }
}
```

未绑定时：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "account": null
  }
}
```

### 10.3 保存提现账号

```text
POST /api/v1/withdraw/account
```

请求：

```json
{
  "type": "alipay",
  "realName": "张三",
  "accountNo": "demo@example.com"
}
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "account": {
      "id": 1,
      "type": "alipay",
      "realName": "张三",
      "accountNo": "demo@example.com",
      "createdAt": "2026-06-13 12:00:00",
      "updatedAt": "2026-06-13 12:00:00"
    }
  }
}
```

### 10.4 申请提现

```text
POST /api/v1/withdraw/apply
```

请求：

```json
{
  "amount": "100.00",
  "remark": "提现到支付宝"
}
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "record": {
      "id": 7001,
      "amount": "100.00",
      "status": "pending",
      "accountType": "alipay",
      "accountNo": "demo@example.com",
      "realName": "张三",
      "remark": "提现到支付宝",
      "createdAt": "2026-06-13 12:00:00"
    },
    "balance": {
      "availableAmount": "1180.00",
      "frozenAmount": "300.00",
      "totalAmount": "1480.00",
      "withdrawnAmount": "500.00"
    }
  }
}
```

### 10.5 提现记录

```text
GET /api/v1/withdraw/records?page=1&pageSize=20&status=pending
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| status | string |  | 提现状态 |

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 7001,
        "amount": "100.00",
        "status": "pending",
        "accountType": "alipay",
        "accountNo": "demo@example.com",
        "realName": "张三",
        "remark": "提现到支付宝",
        "rejectReason": "",
        "paidAt": null,
        "createdAt": "2026-06-13 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

## 11. 账户接口

### 11.1 账户资料

```text
GET /api/v1/account/profile
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "user": {
      "id": 1001,
      "username": "demo",
      "nickname": "演示用户",
      "email": "demo@example.com",
      "avatar": "",
      "role": "user",
      "createdAt": "2026-06-13 12:00:00"
    },
    "invite": {
      "inviteCode": "",
      "inviteUrl": "",
      "sub2ApiAffCode": "SUB2AFF12",
      "sub2ApiInviteUrl": "https://api.sjiaa.cc.cd/register?aff=SUB2AFF12",
      "sub2ApiAffiliatePageUrl": "https://api.sjiaa.cc.cd/affiliate",
      "parentNickname": "上级用户",
      "depth": 2
    },
    "balance": {
      "availableAmount": "1280.00",
      "frozenAmount": "200.00",
      "totalAmount": "1480.00",
      "withdrawnAmount": "500.00"
    }
  }
}
```

### 11.2 更新账户资料

```text
PUT /api/v1/account/profile
```

说明：

- `email` 必须在本地 `users` 表唯一。

请求：

```json
{
  "nickname": "新昵称",
  "email": "new@example.com"
}
```

响应：

```json
{
  "code": 0,
  "message": "资料更新成功",
  "data": null
}
```

### 11.3 修改密码

```text
POST /api/v1/account/change-password
```

说明：

- 当前登录密码由 Sub2API 统一管理，本接口不会修改本地 `users.password`。
- 后端返回业务错误，提示用户前往 Sub2API 修改密码，避免本地密码和 Sub2API 密码不一致。

响应：

```json
{
  "code": 40001,
  "message": "登录密码由 Sub2API 统一管理，请前往 Sub2API 修改密码",
  "data": null
}
```

## 12. 配置接口

### 12.1 配置项列表

```text
GET /api/v1/config/items
```

说明：

- 需要登录。
- 第一版用于读取默认配置和 Tips。
- 后台修改配置后也通过同一配置表读取。
- 后台配置修改使用 `PUT /api/v1/admin/rebate-config` 或 Filament Resource，并写入审计日志。

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "items": [
      {
        "key": "withdraw.daily_limit",
        "group": "withdraw",
        "name": "每日提现次数",
        "type": "int",
        "value": 1,
        "tips": "每日提现次数上限",
        "sort": 120
      }
    ],
    "values": {
      "milestone": {
        "amount": "100",
        "reward_amount": "15",
        "max_times": 2,
        "only_direct": true
      },
      "rebate": {
        "pool_ratio": "0.15",
        "mode": "decay",
        "decay_factor": "0.4",
        "normalize": true,
        "inactive_node_mode": "platform"
      },
      "payment": {
        "cny_to_credit_rate": "1"
      },
      "withdraw": {
        "min_amount": "50",
        "review_mode": "manual",
        "daily_limit": 1,
        "freeze_days": 0
      },
      "risk": {
        "blacklist_enabled": true,
        "duplicate_check": true
      }
    }
  }
}
```

## 13. 后台接口口径

第一版同时提供 Filament Resource 和 Vue 管理端 Admin API。Admin API 统一使用 `/api/v1/admin` 前缀，并要求 `auth:sanctum` + `admin` 中间件。

后台登录和权限：

- 后台用户仍复用 Sub2API 用户池。
- 优先使用 Sub2API `users.role = admin` 判断管理员。
- `sub2_user_roles` 可作为本系统补充权限和限制。
- 普通用户不能访问 Filament 后台或任何管理 API。

后台资源必须覆盖：

| 资源 | 说明 |
|---|---|
| users | 用户与角色标记 |
| referral_paths | 邀请关系和邀请树 |
| rebate_events | 返利事件 |
| user_rebate_progress | 里程碑进度 |
| rebate_records | 返利流水 |
| rebate_balances | 返利余额 |
| withdraw_accounts | 提现账号 |
| withdraw_records | 提现审核 |
| config_items | 配置中心 |
| risk_flags | 风控名单 |
| audit_logs | 审计日志 |
| sub2api_upstream_accounts | Sub2API 上游模型账号/渠道账号监控 |

后台敏感操作统一要求：

- 必须登录管理员账号。
- 必须填写操作备注。
- 必须写入审计日志。
- 金额类操作必须使用事务、幂等键和字符串金额。
- 不允许仅根据 Sub2API `users.balance` 差值自动发放返利。

### 13.1 后台仪表盘

```text
GET /api/v1/admin/dashboard
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "totalUsers": 128,
    "todayNewUsers": 3,
    "totalRebateAmount": "15880.00",
    "pendingWithdrawCount": 2,
    "rebateBalanceAmount": "1680.00",
    "pendingEventCount": 4
  }
}
```

### 13.2 后台返利趋势

```text
GET /api/v1/admin/trends?range=30d
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| range | string | 7d | `7d`、`30d`；其他值按 `7d` 处理 |

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "range": "30d",
    "list": [
      {
        "date": "2026-06-13",
        "amount": "128.00"
      }
    ]
  }
}
```

### 13.3 用户列表

```text
GET /api/v1/admin/users?page=1&pageSize=20&keyword=demo
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| page | number | 1 | 当前页 |
| pageSize | number | 20 | 每页条数，最大 100 |
| keyword | string |  | 支持用户名、邮箱、用户 ID 搜索 |

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 1001,
        "username": "demo",
        "email": "demo@example.com",
        "role": "user",
        "status": "active",
        "sub2ApiAffCode": "SUB2AFF12",
        "sub2ApiInviterId": null,
        "createdAt": "2026-06-13 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

### 13.4 封禁用户

```text
POST /api/v1/admin/users/{id}/ban
```

请求：

```json
{
  "remark": "风控封禁"
}
```

响应：返回 13.3 中的单个用户对象，`status` 为 `banned`。

### 13.5 解封用户

```text
POST /api/v1/admin/users/{id}/unban
```

请求：

```json
{
  "remark": "解除限制"
}
```

响应：返回 13.3 中的单个用户对象，`status` 为 `active`。

### 13.6 设置用户角色

```text
POST /api/v1/admin/users/{id}/role
```

请求：

```json
{
  "role": "admin",
  "remark": "设置管理员"
}
```

响应：返回 13.3 中的单个用户对象。

### 13.7 提现列表

```text
GET /api/v1/admin/withdrawals?page=1&pageSize=20&status=pending
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| status | string |  | 提现状态 |

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 7001,
        "userId": 1001,
        "amount": "100.00",
        "status": "pending",
        "accountType": "alipay",
        "accountNo": "demo@example.com",
        "realName": "张三",
        "remark": "提现到支付宝",
        "rejectReason": "",
        "reviewedBy": null,
        "reviewedAt": null,
        "paidAt": null,
        "createdAt": "2026-06-13 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

### 13.8 提现审核通过

```text
POST /api/v1/admin/withdrawals/{id}/approve
```

请求：

```json
{
  "remark": "资料无误"
}
```

响应：返回 13.7 中的单个提现记录对象。

### 13.9 拒绝提现

```text
POST /api/v1/admin/withdrawals/{id}/reject
```

请求：

```json
{
  "remark": "账号信息不完整"
}
```

响应：返回 13.7 中的单个提现记录对象。

### 13.10 标记已打款

```text
POST /api/v1/admin/withdrawals/{id}/paid
```

请求：

```json
{
  "remark": "支付宝已转账"
}
```

响应：返回 13.7 中的单个提现记录对象。

### 13.11 读取返利配置

```text
GET /api/v1/admin/rebate-config
```

响应：同 `GET /api/v1/config/items`。

### 13.12 更新返利配置

```text
PUT /api/v1/admin/rebate-config
```

说明：

- 只更新已存在的配置 key。
- 关键金额、比例和次数配置会做基础范围校验，例如返利池比例必须在 `0` 到 `1` 之间，衰减系数必须大于 `0` 且不超过 `1`。
- 更新会写入审计日志，审计日志默认记录 IP 和 User-Agent。

请求支持两种形式：

```json
{
  "items": [
    {
      "key": "withdraw.min_amount",
      "value": "50"
    }
  ]
}
```

或：

```json
{
  "values": {
    "withdraw": {
      "min_amount": "50"
    }
  }
}
```

响应：同 `GET /api/v1/config/items`。

### 13.13 余额调整

```text
POST /api/v1/admin/balance-adjust
```

请求：

```json
{
  "userId": 1001,
  "amount": "100.00",
  "type": "add",
  "reason": "手动补偿",
  "remark": "返利金额调整",
  "adminPassword": "******"
}
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| userId | number |  | 用户 ID；兼容 `user_id` |
| amount | string |  | 调整金额，必须大于 0 |
| type | string | add | `add` 增加；`sub` 或 `minus` 扣减 |
| reason | string | 手动补偿 | 原因类型 |
| remark | string | 返利金额调整 | 操作备注 |
| adminPassword | string |  | 当前管理员 Sub2API 登录密码，用于敏感操作二次校验 |

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "userId": 1001,
    "availableAmount": "1380.00",
    "frozenAmount": "200.00",
    "totalAmount": "1580.00",
    "withdrawnAmount": "500.00"
  }
}
```

### 13.14 Sub2API API 额度调整

```text
POST /api/v1/admin/users/{id}/api-quota
```

请求：

```json
{
  "amount": "100.00",
  "type": "add",
  "reason": "充值",
  "remark": "余额充值",
  "adminPassword": "******"
}
```

说明：

- 该接口调整的是 Sub2API `users.balance`，不是 Sub2Rebate 返利余额。
- 后端通过 Sub2API Admin API `POST /api/v1/admin/users/{id}/balance` 调用上游，并携带 `x-api-key` 与 `Idempotency-Key`。
- `type = add` 会同步创建本地 `payment_records` / `rebate_events`，作为返利处理入口。
- `adminPassword` 只用于服务端校验当前管理员密码，不写入审计日志。

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "userId": 1001,
    "type": "add",
    "amount": "100.00",
    "reason": "充值",
    "remark": "余额充值",
    "rebateEventId": 12
  }
}
```

### 13.14 用户余额调整记录

```text
GET /api/v1/admin/users/{id}/balance-records?page=1&pageSize=20
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 1,
        "action": "balance.adjust",
        "beforeValues": {},
        "afterValues": {
          "delta": "100.000000"
        },
        "remark": "活动补发",
        "createdAt": "2026-06-13 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

### 13.15 后台推荐关系树

```text
GET /api/v1/admin/relationship-tree?userId=1001&maxDepth=6
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| userId | number |  | 指定根用户；为空时返回全部根节点列表；兼容 `user_id` |
| maxDepth | number | 6 | 最大 10 |

指定 `userId` 时响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "root": {
      "id": 1001,
      "username": "demo",
      "nickname": "demo",
      "level": 0,
      "children": []
    }
  }
}
```

未指定 `userId` 时响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": []
  }
}
```

### 13.16 审计日志列表

```text
GET /api/v1/admin/audit-logs?page=1&pageSize=20&actionType=balance.adjust
```

参数：

| 参数 | 类型 | 默认值 | 说明 |
|---|---|---|---|
| actionType | string |  | 按操作类型筛选；兼容 `action` |

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "id": 1,
        "actorUserId": 1000,
        "targetUserId": 1001,
        "module": "balance",
        "action": "balance.adjust",
        "subjectType": "App\\Modules\\Rebate\\Models\\RebateBalance",
        "subjectId": 1,
        "beforeValues": {},
        "afterValues": {},
        "remark": "活动补发",
        "createdAt": "2026-06-13 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

### 13.17 用户个性化返利配置列表

```text
GET /api/v1/admin/user-rebate-overrides
```

说明：返回已保存的用户个性化返利配置，配置存储在 `config_items` 的 `rebate.user_override.{userId}` key 下。

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "list": [
      {
        "userId": 1001,
        "username": "demo",
        "enabled": true,
        "customRates": [
          {
            "level": 1,
            "rate": "0.10"
          }
        ],
        "updatedAt": "2026-06-14 12:00:00"
      }
    ],
    "page": 1,
    "pageSize": 20,
    "total": 1
  }
}
```

### 13.18 读取用户个性化返利配置

```text
GET /api/v1/admin/users/{id}/rebate-override
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "userId": 1001,
    "enabled": false,
    "items": []
  }
}
```

### 13.19 更新用户个性化返利配置

```text
PUT /api/v1/admin/users/{id}/rebate-override
```

请求：

```json
{
  "enabled": true,
  "items": [
    {
      "level": 1,
      "ratio": "0.100000"
    }
  ],
  "remark": "单独配置一级返利"
}
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "userId": 1001,
    "enabled": true,
    "items": [
      {
        "level": 1,
        "ratio": "0.100000"
      }
    ]
  }
}
```

## 14. 联调规则

- 前端 mock 数据必须与本文件字段保持一致。
- 后端新增或修改接口前，先更新本文件。
- 后端错误响应必须使用统一格式，不能直接返回框架默认 HTML。
- 涉及金额的接口必须返回字符串金额。
- 列表接口必须使用统一分页格式。
- 涉及金额和权限的接口必须先补契约，再写实现和测试。

## 10A. 充值接口

### 10A.1 充值配置

```text
GET /api/v1/recharge/config
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "enabled": true,
    "channel": "alipay",
    "qrUrl": "https://example.com/alipay-qr.png",
    "displayName": "张三-支付宝收款码",
    "note": "付款时请备注订单号，支付后等待管理员审核到账。",
    "expireMinutes": 15
  }
}
```

### 10A.2 创建充值订单

```text
POST /api/v1/recharge/orders
```

请求：

```json
{
  "amount": "100.00"
}
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "id": 1,
    "orderNo": "RC202606161530001234",
    "channel": "alipay",
    "amount": "100.00",
    "bonusAmount": "5.00",
    "creditAmount": "105.00",
    "status": "pending",
    "qrUrl": "https://example.com/alipay-qr.png",
    "displayName": "张三-支付宝收款码",
    "note": "付款时请备注订单号，支付后等待管理员审核到账。",
    "expireAt": "2026-06-16 15:45:00"
  }
}
```

### 10A.3 提交付款信息

```text
POST /api/v1/recharge/orders/{id}/submit
```

请求：

```json
{
  "payerName": "张三",
  "payerAccount": "alipay-demo"
}
```

响应：返回同订单结构，`status = submitted`。

### 10A.4 充值订单列表

```text
GET /api/v1/recharge/orders?page=1&pageSize=20&status=submitted
```

### 10A.5 后台充值审核列表

```text
GET /api/v1/admin/recharge-orders?page=1&pageSize=20&status=submitted
```

### 10A.6 后台确认到账

```text
POST /api/v1/admin/recharge-orders/{id}/approve
```

请求：

```json
{
  "remark": "已核对支付宝收款"
}
```

说明：确认到账后会自动调用 Sub2API 增加 API 额度，并创建返利事件。

### 10A.7 后台拒绝充值订单

```text
POST /api/v1/admin/recharge-orders/{id}/reject
```

请求：

```json
{
  "remark": "付款信息不匹配"
}
```
### 10A.8 后台读取支付配置

```text
GET /api/v1/admin/payment-config
```

认证：管理员。

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "enabled": true,
    "channel": "alipay",
    "qrUrl": "https://example.com/alipay-qr.png",
    "displayName": "张三-支付宝收款码",
    "note": "付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。",
    "expireMinutes": 15,
    "creditRate": "1"
  }
}
```

### 10A.9 后台保存支付配置

```text
PUT /api/v1/admin/payment-config
```

认证：管理员。

请求：

```json
{
  "enabled": true,
  "qrUrl": "https://example.com/alipay-qr.png",
  "displayName": "张三-支付宝收款码",
  "note": "付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。",
  "expireMinutes": 15,
  "creditRate": "1"
}
```

说明：

- `enabled = true` 时，`qrUrl` 必填。
- `qrUrl` 可以是公网图片 URL，也可以是后台上传本地图片后生成的 `data:image/...` 地址。
- `expireMinutes` 范围是 1 到 1440。
- `creditRate` 必须大于 0。
- 保存后，用户侧 `GET /api/v1/recharge/config` 和创建充值订单返回的二维码会读取同一份配置。

## 10B. 支付宝官方回调充值接口（升级方案）

> 这一组接口只在 `payment.mode = alipay_precreate` 时启用。
> 纯个人支付宝静态收款码仍走 10A 的人工审核版。

### 10B.1 创建支付宝充值订单

```text
POST /api/v1/recharge/orders
```

请求：

```json
{
  "amount": "100.00",
  "channel": "alipay"
}
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "id": 1,
    "orderNo": "RC202606161530001234",
    "outTradeNo": "ALI202606161530001234",
    "channel": "alipay",
    "payMode": "alipay_precreate",
    "amount": "100.00",
    "bonusAmount": "5.00",
    "creditAmount": "105.00",
    "status": "pending",
    "tradeStatus": "WAIT_BUYER_PAY",
    "creditStatus": "pending",
    "qrContent": "https://qr.alipay.com/baxxxxxxxxxxxx",
    "expireAt": "2026-06-16 15:45:00"
  }
}
```

说明：后端创建本地订单后，调用支付宝 `alipay.trade.precreate`，返回订单二维码原始内容 `qrContent`。

### 10B.2 查询充值订单详情

```text
GET /api/v1/recharge/orders/{id}
```

响应：

```json
{
  "code": 0,
  "message": "ok",
  "data": {
    "id": 1,
    "orderNo": "RC202606161530001234",
    "outTradeNo": "ALI202606161530001234",
    "channel": "alipay",
    "payMode": "alipay_precreate",
    "amount": "100.00",
    "bonusAmount": "5.00",
    "creditAmount": "105.00",
    "status": "approved",
    "tradeStatus": "TRADE_SUCCESS",
    "creditStatus": "success",
    "paidAmount": "100.00",
    "buyerLogonId": "z***@example.com",
    "paidAt": "2026-06-16 15:32:10",
    "expireAt": "2026-06-16 15:45:00"
  }
}
```

### 10B.3 支付宝异步回调

```text
POST /api/v1/payments/alipay/notify
```

认证：公开接口，仅支付宝服务器调用。

请求格式：

```text
Content-Type: application/x-www-form-urlencoded
```

关键字段：

| 字段 | 说明 |
|---|---|
| `out_trade_no` | 商户订单号 |
| `trade_no` | 支付宝交易号 |
| `trade_status` | 交易状态 |
| `total_amount` | 支付金额 |
| `buyer_logon_id` | 买家账号脱敏值 |
| `notify_time` | 通知时间 |
| `notify_id` | 通知 ID |
| `sign` | 签名 |
| `sign_type` | 签名类型 |

成功响应：

```text
success
```

失败响应：

```text
fail
```

说明：

- 后端必须先验签，再更新订单。
- 验签通过但金额不一致时，必须记录回调日志并标记异常，不直接发放额度。
- 回调处理成功后，由后端自动完成 Sub2API 加额度和返利事件创建。

### 10B.4 后台手动查单 / 补单

```text
POST /api/v1/admin/recharge-orders/{id}/sync-pay
```

请求：

```json
{
  "remark": "支付宝回调丢失，手动查单"
}
```

说明：

- 管理员主动向支付宝查询订单状态。
- 查到 `TRADE_SUCCESS` 但本地未入账时，走同一套自动入账逻辑。
- 这个接口主要用于补单，不给普通用户开放。

### 10B.5 后台关闭未支付订单

```text
POST /api/v1/admin/recharge-orders/{id}/close
```

请求：

```json
{
  "remark": "订单超时关闭"
}
```

说明：关闭后本地订单状态更新为 `closed`，必要时同步调用支付宝关单接口。
