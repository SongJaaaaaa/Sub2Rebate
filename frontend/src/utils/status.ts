import type { WithdrawStatus } from '@/types/withdraw'
import type { RechargeStatus } from '@/types/recharge'
import type { RebateStatus, RebateType } from '@/types/rebate'

type TagType = 'info' | 'primary' | 'success' | 'warning' | 'danger'

interface StatusDisplay {
  text: string
  type: TagType
}

const withdrawMap: Record<WithdrawStatus, StatusDisplay> = {
  pending: { text: '待审核', type: 'warning' },
  approved: { text: '已通过', type: 'primary' },
  paid: { text: '已打款', type: 'success' },
  rejected: { text: '已拒绝', type: 'danger' },
  failed: { text: '失败', type: 'danger' },
  canceled: { text: '已取消', type: 'info' },
}

const rechargeStatusMap: Record<RechargeStatus, StatusDisplay> = {
  pending: { text: '待支付', type: 'warning' },
  submitted: { text: '待审核', type: 'primary' },
  approved: { text: '已到账', type: 'success' },
  paid: { text: '已到账', type: 'success' },
  rejected: { text: '已拒绝', type: 'danger' },
  expired: { text: '已过期', type: 'info' },
}

const rebateStatusMap: Record<RebateStatus, StatusDisplay> = {
  pending: { text: '待确认', type: 'warning' },
  confirmed: { text: '已确认', type: 'primary' },
  frozen: { text: '冻结中', type: 'info' },
  available: { text: '可提现', type: 'success' },
  canceled: { text: '已取消', type: 'danger' },
}

const rebateTypeMap: Record<RebateType, string> = {
  milestone: '里程碑奖励',
  decay: '多级返利',
  manual: '手工调整',
}

export const getWithdrawStatus = (status: WithdrawStatus): StatusDisplay =>
  withdrawMap[status] || { text: status, type: 'info' }

export const getRebateStatusDisplay = (status: RebateStatus): StatusDisplay =>
  rebateStatusMap[status] || { text: status, type: 'info' }

export const getRebateTypeText = (type: RebateType): string =>
  rebateTypeMap[type] || type

export const getRechargeStatus = (status: RechargeStatus): StatusDisplay =>
  rechargeStatusMap[status] || { text: status, type: 'info' }
