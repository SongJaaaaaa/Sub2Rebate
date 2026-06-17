import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import { mockAdminUsers, mockAdminWithdrawals, mockRebateConfig, mockAdminDashboardStats, mockAdminTrends, mockFullRebateConfig, mockRelationshipTree } from '@/mocks/admin'
import type { ApiRes, PageRes } from '@/types/api'
import type { AdminUser, AdminWithdrawRecord, RebateConfig, AdminDashboardStats, AdminTrendItem, FullRebateConfig, RelationshipNode, AdminPaymentConfig } from '@/types/admin'

// ============ 数据看板 ============

export const getAdminDashboard = async (): Promise<ApiRes<AdminDashboardStats>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockAdminDashboardStats }
  }
  return request.get('/admin/dashboard')
}

export const getAdminTrends = async (range = '7d'): Promise<ApiRes<{ items: AdminTrendItem[] }>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { items: mockAdminTrends } }
  }
  return request.get('/admin/trends', { params: { range } })
}

// ============ 用户管理 ============

export const getAdminUsers = async (page = 1, pageSize = 20, keyword?: string): Promise<ApiRes<PageRes<AdminUser>>> => {
  if (useMock) {
    await delay()
    let list = [...mockAdminUsers]
    if (keyword) list = list.filter((u) => u.username.includes(keyword) || u.nickname.includes(keyword))
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  return request.get('/admin/users', { params: { page, pageSize, keyword } })
}

export const banUser = async (userId: number): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(300)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post(`/admin/users/${userId}/ban`)
}

export const unbanUser = async (userId: number): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(300)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post(`/admin/users/${userId}/unban`)
}

export const setUserRole = async (userId: number, role: string): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(300)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post(`/admin/users/${userId}/role`, { role })
}

// ============ 提现审核 ============

export const getAdminWithdrawals = async (page = 1, pageSize = 20, status?: string): Promise<ApiRes<PageRes<AdminWithdrawRecord>>> => {
  if (useMock) {
    await delay()
    let list = [...mockAdminWithdrawals]
    if (status) list = list.filter((r) => r.status === status)
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  return request.get('/admin/withdrawals', { params: { page, pageSize, status } })
}

export const approveWithdraw = async (id: number): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post(`/admin/withdrawals/${id}/approve`)
}

export const markPaid = async (id: number): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post(`/admin/withdrawals/${id}/paid`)
}

export const rejectWithdraw = async (id: number, reason: string): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post(`/admin/withdrawals/${id}/reject`, { reason })
}

// ============ 支付配置 ============

const mockPaymentConfig: AdminPaymentConfig = {
  enabled: true,
  channel: 'alipay',
  qrUrl: 'https://via.placeholder.com/320x320.png?text=Alipay+QR',
  displayName: '支付宝收款码',
  note: '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。',
  expireMinutes: 15,
  creditRate: '1',
}

export const getAdminPaymentConfig = async (): Promise<ApiRes<AdminPaymentConfig>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockPaymentConfig }
  }
  return request.get('/admin/payment-config')
}

export const saveAdminPaymentConfig = async (data: AdminPaymentConfig): Promise<ApiRes<AdminPaymentConfig>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data }
  }
  return request.put('/admin/payment-config', data)
}

// ============ 返利配置（完整版） ============

export const getFullRebateConfig = async (): Promise<ApiRes<FullRebateConfig>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockFullRebateConfig }
  }
  return request.get('/admin/rebate-config')
}

export const saveFullRebateConfig = async (config: FullRebateConfig): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(500)
    return { code: 0, message: 'ok', data: null }
  }
  return request.put('/admin/rebate-config', config)
}

// compat
export const getRebateConfig = async (): Promise<ApiRes<RebateConfig>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockRebateConfig }
  }
  return request.get('/admin/rebate-config')
}

export const saveRebateConfig = async (config: RebateConfig): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(500)
    return { code: 0, message: 'ok', data: null }
  }
  return request.put('/admin/rebate-config', config)
}

// ============ 推荐关系树 ============

export const getRelationshipTree = async (userId?: number): Promise<ApiRes<RelationshipNode>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: mockRelationshipTree }
  }
  const res = await request.get('/admin/relationship-tree', { params: { userId } }) as ApiRes<{ root?: RelationshipNode; list?: RelationshipNode[] }>
  const root = res.data.root || [...(res.data.list || [])].sort((a, b) => b.directReferrals - a.directReferrals)[0]
  return {
    ...res,
    data: root || null as unknown as RelationshipNode,
  }
}

// ============ 审计日志 ============

import { mockAuditLogs, mockBalanceAdjustRecords, mockUserRebateOverrides } from '@/mocks/adminExtra'
import type { AdminAuditLogRaw, ApiQuotaAdjustRes, ApiQuotaInfo, ApiQuotaRecord, ApiQuotaReq, AuditLogItem, AuditActionType, AuditStatus, BalanceAdjustReq, BalanceAdjustRecord, UserRebateOverride } from '@/types/admin'

const actionMap: Record<string, AuditActionType> = {
  'config.update': '配置更改',
  'balance.adjust': '手动余额调整',
  'user.ban': '用户冻结',
  'user.unban': '用户冻结',
  'withdraw.approve': '提现审批',
  'withdraw.reject': '提现审批',
  'withdraw.mark_paid': '提现审批',
  'user.set_role': '角色变更',
  'rebate.override.update': '返利层级调整',
}

const actionFilterMap: Record<string, string> = {
  配置更改: 'config.update',
  手动余额调整: 'balance.adjust',
  用户冻结: 'user.ban',
  提现审批: 'withdraw.approve',
  角色变更: 'user.set_role',
  返利层级调整: 'rebate.override.update',
}

const initials = (text: string) => text
  .split(/[\s_-]+/)
  .filter(Boolean)
  .slice(0, 2)
  .map((s) => s[0]?.toUpperCase())
  .join('') || 'SYS'

const valueText = (val: unknown) => {
  if (val === null || val === undefined) return '-'
  if (typeof val === 'object') return JSON.stringify(val)
  return String(val)
}

const buildChanges = (before: Record<string, unknown> | null, after: Record<string, unknown> | null) => {
  if (!before || !after) return []
  return Object.keys(after)
    .filter((field) => before[field] !== after[field])
    .slice(0, 6)
    .map((field) => ({
      field,
      fieldLabel: field,
      oldValue: valueText(before[field]),
      newValue: valueText(after[field]),
    }))
}

const mapAuditLog = (log: AdminAuditLogRaw): AuditLogItem => {
  const operator = log.actorUserId ? `管理员 #${log.actorUserId}` : '系统'
  const status: AuditStatus = log.action.includes('reject') ? '失败' : '成功'
  return {
    id: String(log.id),
    datetime: log.createdAt || '',
    operator,
    operatorAvatar: initials(operator),
    actionType: actionMap[log.action] || '配置更改',
    status,
    target: log.targetUserId ? `用户 #${log.targetUserId}` : (log.subjectId ? `对象 #${log.subjectId}` : log.module),
    ip: '-',
    device: 'Sub2Rebate',
    transactionId: `AUD-${log.id}`,
    changes: buildChanges(log.beforeValues, log.afterValues),
    remark: log.remark || log.action,
    events: [
      { text: log.action, time: log.createdAt || '', status: 'done' },
    ],
  }
}

export const getAuditLogs = async (page = 1, pageSize = 10, actionType?: string): Promise<ApiRes<PageRes<AuditLogItem>>> => {
  if (useMock) {
    await delay()
    let list = [...mockAuditLogs]
    if (actionType) list = list.filter((l) => l.actionType === actionType)
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  const action = actionType ? actionFilterMap[actionType] || actionType : undefined
  const res = await request.get('/admin/audit-logs', { params: { page, pageSize, actionType: action } }) as ApiRes<PageRes<AdminAuditLogRaw>>
  return {
    ...res,
    data: {
      ...res.data,
      list: res.data.list.map(mapAuditLog),
    },
  }
}

// ============ 手动余额调整 ============

export const getBalanceAdjustRecords = async (userId: number): Promise<ApiRes<BalanceAdjustRecord[]>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockBalanceAdjustRecords }
  }
  const res = await request.get(`/admin/users/${userId}/balance-records`) as ApiRes<PageRes<BalanceAdjustRecord>>
  return { ...res, data: res.data.list }
}

export const adjustBalance = async (data: BalanceAdjustReq): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(500)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post('/admin/balance-adjust', data)
}

export const adjustApiQuota = async (userId: number, data: ApiQuotaReq): Promise<ApiRes<ApiQuotaAdjustRes>> => {
  if (useMock) {
    await delay(500)
    return {
      code: 0,
      message: 'ok',
      data: {
        userId,
        type: data.type,
        amount: String(data.amount),
        reason: data.reason,
        remark: data.remark,
        rebateEventId: null,
        sub2api: {},
      },
    }
  }
  return request.post(`/admin/users/${userId}/api-quota`, data)
}

export const getApiQuota = async (userId: number): Promise<ApiRes<ApiQuotaInfo>> => {
  if (useMock) {
    await delay()
    const user = mockAdminUsers.find((u) => u.id === userId)
    return {
      code: 0,
      message: 'ok',
      data: {
        userId,
        nickname: user?.nickname || user?.username || `用户${userId}`,
        username: user?.username || `user${userId}`,
        apiBalance: '0.00',
        totalUsed: '0.00',
        totalCharged: '0.00',
        sub2ApiAffCode: user?.sub2ApiAffCode || '',
        sub2ApiInviterId: user?.sub2ApiInviterId || null,
        updatedAt: '',
      },
    }
  }
  return request.get(`/admin/users/${userId}/api-quota`)
}

export const getApiQuotaRecords = async (userId: number): Promise<ApiRes<ApiQuotaRecord[]>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: [] }
  }
  const res = await request.get(`/admin/users/${userId}/api-quota-records`) as ApiRes<PageRes<ApiQuotaRecord>>
  return { ...res, data: res.data.list }
}

// ============ 用户个性化返利设置 ============

export const getUserRebateOverrides = async (): Promise<ApiRes<UserRebateOverride[]>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockUserRebateOverrides }
  }
  const res = await request.get('/admin/user-rebate-overrides') as ApiRes<PageRes<UserRebateOverride>>
  return { ...res, data: res.data.list }
}

export const getUserRebateOverride = async (userId: number): Promise<ApiRes<UserRebateOverride | null>> => {
  if (useMock) {
    await delay()
    const found = mockUserRebateOverrides.find((o) => o.userId === userId) || null
    return { code: 0, message: 'ok', data: found }
  }
  return request.get(`/admin/users/${userId}/rebate-override`)
}

export const saveUserRebateOverride = async (data: UserRebateOverride): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: null }
  }
  return request.put(`/admin/users/${data.userId}/rebate-override`, data)
}