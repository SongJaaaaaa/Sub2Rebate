export type RechargeChannel = 'alipay' | 'epay'
export type RechargeStatus = 'pending' | 'submitted' | 'approved' | 'rejected' | 'expired' | 'paid'

export interface RechargeConfig {
  enabled: boolean
  channel: RechargeChannel
  qrUrl: string
  displayName: string
  note: string
  expireMinutes: number
  epayEnabled: boolean
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

export interface EpayPayResult {
  order: RechargeOrder
  payType: string // qrcode / jump / urlscheme
  payInfo: string // 二维码内容或跳转 URL
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