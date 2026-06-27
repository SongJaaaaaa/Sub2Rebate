export type WithdrawStatus = 'pending' | 'approved' | 'paid' | 'rejected' | 'failed' | 'canceled'
export type WithdrawType = 'alipay' | 'api_quota'

export interface WithdrawConfig {
  minAmount: string
  reviewMode: 'manual' | 'auto'
  dailyLimit: number | null
  freezeDays: number
  toApiQuotaEnabled: boolean
  toApiQuotaRate: string
  tips: string[]
}

export interface WithdrawAccount {
  id: number
  type: 'alipay'
  realName: string
  accountNo: string
  createdAt: string
  updatedAt: string
}

export interface WithdrawRecord {
  id: number
  type: WithdrawType
  amount: string
  status: WithdrawStatus
  accountType: string
  accountNo: string
  realName: string
  sub2ApiBalanceBefore: string | null
  sub2ApiBalanceAfter: string | null
  remark: string
  rejectReason: string
  payoutTradeNo: string
  payoutError: string
  payoutTime: string | null
  paidAt: string | null
  createdAt: string
}

export interface WithdrawApplyReq {
  amount: string
  remark?: string
}
