export type RechargeChannel = 'alipay'
export type RechargeStatus = 'pending' | 'submitted' | 'approved' | 'rejected' | 'expired'

export interface RechargeConfig {
  enabled: boolean
  channel: RechargeChannel
  qrUrl: string
  displayName: string
  note: string
  expireMinutes: number
}

export interface RechargeOrder {
  id: number
  orderNo: string
  channel: RechargeChannel
  amount: string
  bonusAmount: string
  creditAmount: string
  status: RechargeStatus
  payerName: string
  payerAccount: string
  voucherImageUrl: string
  remark: string
  reviewRemark: string
  rebateEventId: number | null
  submittedAt: string
  reviewedAt: string
  paidAt: string
  expireAt: string
  createdAt: string
  qrUrl: string
  displayName: string
  note: string
}

export interface CreateRechargeOrderReq {
  amount: string | number
  remark?: string
}

export interface SubmitRechargeOrderReq {
  payerName: string
  payerAccount: string
  voucherImageUrl?: string
}

export interface AdminRechargeOrder extends RechargeOrder {
  userId: number
  username: string
  nickname: string
}