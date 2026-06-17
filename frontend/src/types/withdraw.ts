export type WithdrawStatus = 'pending' | 'approved' | 'paid' | 'rejected' | 'failed' | 'canceled'

export interface WithdrawConfig {
  minAmount: string
  reviewMode: 'manual' | 'auto'
  dailyLimit: number | null
  freezeDays: number
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
  amount: string
  status: WithdrawStatus
  accountType: string
  accountNo: string
  realName: string
  remark: string
  rejectReason: string
  paidAt: string | null
  createdAt: string
}

export interface WithdrawApplyReq {
  amount: string
  remark?: string
}
