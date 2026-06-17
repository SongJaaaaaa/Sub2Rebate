import type { User } from '@/types/user'
import type { WithdrawRecord } from '@/types/withdraw'

// 管理端 — 用户管理
export interface AdminUser extends User {
  status: 'active' | 'banned'
  parentNickname: string | null
  directInviteCount: number
  totalRebateAmount: string
  totalPaidAmount: string
  sub2ApiAffCode?: string
  sub2ApiInviterId?: number | null
}

// 管理端 — 提现审核
export interface AdminWithdrawRecord extends WithdrawRecord {
  userId: number
  username: string
  nickname: string
}

export interface AdminPaymentConfig {
  enabled: boolean
  channel: 'alipay'
  qrUrl: string
  displayName: string
  note: string
  expireMinutes: number
  creditRate: string
}

// 管理端 — 返利配置（完整）
export interface MilestoneConfig {
  threshold: string
  reward: string
  maxTimes: number
}

export interface MultiLevelConfig {
  enabled: boolean
  totalPoolRate: string
  decayCoefficient: string
  maxDepth: number
}

export interface WithdrawLimitConfig {
  minAmount: string
  cooldownHours: number
}

export interface RiskControlConfig {
  blacklistEnabled: boolean
  autoFreezeThreshold: number
}

export interface FullRebateConfig {
  milestone: MilestoneConfig
  multiLevel: MultiLevelConfig
  withdrawLimit: WithdrawLimitConfig
  riskControl: RiskControlConfig
  lastModifiedBy: string
  lastModifiedAt: string
}

// 管理端 — 推荐关系树
export interface RelationshipNode {
  id: number
  username: string
  nickname: string
  avatar: string
  level: string
  totalRecharge: string
  directReferrals: number
  status: 'active' | 'banned' | 'warning'
  children?: RelationshipNode[]
}

// 管理端 — 数据看板
export interface AdminDashboardStats {
  totalUsers: number
  todayNewUsers: number
  totalRebateAmount: string
  totalWithdrawAmount: string
  pendingWithdrawCount: number
  pendingWithdrawAmount: string
  todayRebateAmount: string
  monthRebateAmount: string
}

export interface AdminTrendItem {
  date: string
  newUsers: number
  rebateAmount: string
  withdrawAmount: string
}

// compat: 旧的简单类型保留
export interface RebateLevelRule {
  level: number
  rate: string
  enabled: boolean
}

export interface MilestoneRule {
  id: number
  name: string
  threshold: string
  reward: string
  enabled: boolean
}

export interface RebateConfig {
  maxLevel: number
  decayRate: string
  levelRules: RebateLevelRule[]
  milestoneRules: MilestoneRule[]
}

// ============ 审计日志 ============

export type AuditActionType = '配置更改' | '手动余额调整' | '用户冻结' | '提现审批' | '角色变更' | '返利层级调整'
export type AuditStatus = '成功' | '失败'

export interface AuditLogItem {
  id: string
  datetime: string
  operator: string
  operatorAvatar: string
  actionType: AuditActionType
  status: AuditStatus
  target: string
  ip: string
  device: string
  transactionId: string
  changes?: AuditChange[]
  remark: string
  reviewer?: string
  reviewStatus?: '已通过复核' | '待复核'
  events?: AuditEvent[]
}

export interface AdminAuditLogRaw {
  id: number
  actorUserId: number | null
  targetUserId: number | null
  module: string
  action: string
  subjectType: string | null
  subjectId: number | null
  beforeValues: Record<string, unknown> | null
  afterValues: Record<string, unknown> | null
  remark: string
  createdAt: string
}

export interface AuditChange {
  field: string
  fieldLabel: string
  oldValue: string
  newValue: string
}

export interface AuditEvent {
  text: string
  time: string
  status: 'done' | 'pending' | 'planned'
}

// ============ 手动余额调整 ============

export type BalanceAdjustType = 'add' | 'subtract'
export type BalanceAdjustReason = '手动补偿' | '违规扣除' | '活动奖励' | '系统修正' | '其他'
export type ApiQuotaReason = '充值' | '手动补偿' | '违规扣除' | '系统修正' | '其他'

export interface BalanceAdjustRecord {
  id: number
  type: BalanceAdjustType
  amount: string
  reason: BalanceAdjustReason
  remark: string
  operator: string
  createdAt: string
  tag: string
  tagColor: 'warning' | 'danger' | 'success' | 'info'
}

export interface BalanceAdjustReq {
  userId: number
  type: BalanceAdjustType
  amount: string | number
  reason: BalanceAdjustReason
  remark: string
  adminPassword: string
}

export interface ApiQuotaReq {
  type: BalanceAdjustType
  amount: string | number
  reason: ApiQuotaReason
  remark: string
  adminPassword?: string
}

export interface ApiQuotaAdjustRes {
  userId: number
  type: BalanceAdjustType
  amount: string
  reason: ApiQuotaReason
  remark: string
  rebateEventId: number | null
  sub2api: Record<string, unknown>
}

export interface ApiQuotaInfo {
  userId: number
  nickname: string
  username: string
  apiBalance: string
  totalUsed: string
  totalCharged: string
  sub2ApiAffCode: string
  sub2ApiInviterId: number | null
  updatedAt: string
}

export interface ApiQuotaRecord {
  id: number
  type: BalanceAdjustType
  amount: string
  reason: ApiQuotaReason
  remark: string
  operator: string
  createdAt: string
  rebateEventId: number | null
}

// ============ 用户个性化返利设置 ============

export interface UserRebateOverride {
  userId: number
  username: string
  nickname: string
  customRates: { level: number; rate: string }[]
  enabled: boolean
  updatedAt: string
}

export type { AdminRechargeOrder } from '@/types/recharge'