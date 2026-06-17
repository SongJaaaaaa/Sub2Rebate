# 深度测试审查报告

> 日期：2026-06-14
> 审查方式：静态代码审查 + 子代理逻辑验证（VM 环境不可用，无法执行 PHPUnit）
> 审查范围：邀请关系、数据同步、提现状态机、衰减返利计算、API 端点安全

---

## 一、审查概览

| 模块 | 状态 | 发现问题数 |
|------|------|-----------|
| 邀请关系 (InviteService) | ✅ 逻辑正确 | 2 个边缘问题 |
| 数据同步 (Sub2RebateAuthService) | ✅ 核心正确 | 1 个安全建议 |
| 提现状态机 (WithdrawService + AdminWithdrawService) | ✅ 状态机完整 | 1 个数据一致性 bug |
| 衰减返利 (DecayRebateService + Calculator) | ✅ 公式正确 | 1 个自定义费率精度问题 |
| API 端点安全 | ✅ 中间件完整 | 无高危漏洞 |

---

## 二、邀请关系模块

### 验证通过

- `syncFromSub2Api`：递归同步 inviter 链（最大 50 层），先创建/同步 parent 再建立 path
- `bind()`：拒绝自绑定、重复绑定、环形绑定（`pathHasUser` 检测）
- `ancestorIds()`：path `"1/2/3"` → 移除自身 → `[1,2]` → reverse → `[2,1]`，closest-first 顺序正确
- `ensurePath()`：`firstOrCreate` 幂等，重复调用不产生多条记录
- 生产验证与测试对齐：用户 2→3 的 `path="2/3"`、`depth=1` 与代码逻辑一致

### 发现问题

1. **ensurePath() 并发竞态（低风险）**
   - 两个请求同时到达时，`firstOrCreate` 在 MySQL/PostgreSQL 中可能因 unique 约束抛异常
   - 当前 `referral_paths.user_id` 有 unique 约束，异常会冒泡为 500
   - **建议**：改为 `insertOrIgnore` + `first()` 或捕获 UniqueConstraint 异常

2. **refreshChildren() 无深度限制（理论风险）**
   - 当修改某节点 path 时，递归更新所有子节点
   - 树深度 >1000 可能栈溢出
   - **建议**：改为循环 + 队列，或设置递归上限

---

## 三、数据同步模块

### 验证通过

- 登录流程：`validate()` → `password_verify()` → `syncLocalUser()` → `createToken()`
- 角色解析：`sub2_user_roles` 表优先 → Sub2API role 兜底 → 非法角色钳位为 `'user'`
- 字段同步：username/email/status/aff_code/inviter_id 每次登录覆盖更新
- 状态检查：`EnsureActiveUser` 中间件 + token 立即删除，封禁后最多 1 个请求窗口

### 发现问题

1. **sub2_user_roles 写入无接口鉴权（设计问题）**
   - 当前只有 admin API (`POST /admin/users/{id}/role`) 能修改
   - 只要 DB 层面有权限控制即安全
   - **状态**：已确认 admin 接口有 `EnsureAdmin` 中间件保护，无直接提权风险

---

## 四、提现状态机

### 状态转换矩阵

```
pending  → approved  (admin approve)
pending  → rejected  (admin reject)
approved → paid      (admin markPaid)
approved → rejected  (admin reject)
paid     → [终态]
rejected → [终态]
```

### 验证通过

- `apply()`：检查余额 → 冻结 → 创建 pending 记录（事务 + lockForUpdate）
- `approve()`：只接受 pending 状态
- `reject()`：接受 pending/approved，解冻金额返回 available
- `markPaid()`：只接受 approved，从 frozen 转 withdrawn
- 余额守恒：`available + frozen + withdrawn = constant`（正常流程）

### 发现问题

1. **reject 无余额记录时静默跳过解冻（数据一致性 bug）**
   - `AdminWithdrawService::reject()` 第 62 行：`if ($balance instanceof RebateBalance)` 为 false 时不报错
   - 提现记录标为 rejected 但冻结金额永远无法解冻
   - **风险**：极低（apply 时已创建 balance，正常流程不会触发）
   - **建议**：改为 reject 前必须存在 balance，否则返回错误

---

## 五、衰减返利计算

### 公式验证

```
每级分配 = pool × (decay^(level-1) / Σ decay^(i-1))
```

- 示例：pool=100, decay=0.4, 3 级
  - Level 1: 100 × (1.0/1.56) = 64.102564
  - Level 2: 100 × (0.4/1.56) = 25.641026
  - Level 3: 100 × (0.16/1.56) = 10.256410
  - **总和 = 100.0** ✅

### 验证通过

- 余数补偿：四舍六入后差额补给第 1 级接收者，保证 pool 不丢失
- `normalAmount` 里程碑公式正确：`max(0, after-threshold) - max(0, before-threshold)` 只提取跨越阈值部分
- 幂等性：`RebateRecord::firstOrCreate` + `wasRecentlyCreated` 判断，重复处理不重复发放
- 0 祖先/1 祖先/多祖先：均正确处理

### 发现问题

1. **自定义费率模式无余数补偿（精度 bug）**
   - `customItems()` 对每个 rate 独立计算 `normalAmount × rate`，各自四舍六入
   - 多个自定义费率之和可能超出 normalAmount（微量，约 0.000001~0.000002 级别）
   - **风险**：极低，仅在多个自定义费率用户触发
   - **建议**：加入与标准衰减相同的 `$diff` 补偿逻辑

---

## 六、API 端点安全

### 中间件覆盖

| 路由组 | 中间件 | 验证 |
|--------|--------|------|
| `POST auth/login` | `throttle:login` | ✅ 限流 |
| 用户路由 | `auth:sanctum` + `active.user` | ✅ 认证 + 活跃 |
| 管理路由 | `auth:sanctum` + `active.user` + `admin` | ✅ 三重保护 |

### 验证通过

- 非管理员访问 `/admin/*` → 403 (code=40301)
- 被封禁用户访问任何路由 → token 被删 + 403
- 空账号/密码 → 400
- 错误密码 → 401
- 被禁用账号登录 → 403

---

## 七、测试文件修正记录

在审查过程中发现并修复了以下测试代码问题：

| 文件 | 问题 | 修正 |
|------|------|------|
| DeepWithdrawFlowTest | `$result['record']->id` 应为 `$result['record']['id']` | ✅ 已修复 4 处 |
| DeepWithdrawFlowTest | `assertStringContainsString('最低', ...)` 不匹配实际消息 | ✅ 改为 `'低于'` |
| DeepApiEndpointTest | `assertUnauthorized()` 用于空字段（实际返回 400） | ✅ 改为 `assertStatus(400)` |
| DeepInviteFlowTest | `assertStringContainsString('已绑定', ...)` 不够精确 | ✅ 改为 `'已绑定邀请关系'` |

---

## 八、与生产验证的对齐确认

你的生产验证结果（用户 2 邀请用户 3）：

| 验证点 | 生产实测 | 代码逻辑 | 一致性 |
|--------|----------|----------|--------|
| `user_affiliates.inviter_id = 2` | ✅ | Sub2API 原生写入 | ✅ |
| `referral_paths.parent_user_id = 2` | ✅ | `syncFromSub2Api` 读取 inviter_id 建立 | ✅ |
| `referral_paths.path = "2/3"` | ✅ | `parentPath + "/" + userId` | ✅ |
| `referral_paths.depth = 1` | ✅ | `parentDepth + 1`，root depth=0 | ✅ |
| 邀请树显示用户 3 | ✅ | `treeNode` 查 parent_user_id | ✅ |
| 邀请记录 total=1 | ✅ | `records` 查 parent_user_id 分页 | ✅ |

---

## 九、总结与建议

**系统整体状态：稳健**。核心业务逻辑（邀请、返利、提现）经过静态审查无高危缺陷，状态机转换完整，余额守恒机制到位。

**优先修复建议（按重要性排序）：**

1. ⚠️ `ensurePath` 并发竞态 → 加 try-catch UniqueConstraint
2. ⚠️ 自定义费率余数补偿 → 移植标准衰减的 `$diff` 逻辑
3. ℹ️ reject 时 balance 不存在的防御性检查

**下一步**：VM 环境恢复后执行完整 PHPUnit 套件（含新增的 4 个 Deep*Test 文件），确认 141+ 个既有测试 + 新增深度测试全部绿色。
