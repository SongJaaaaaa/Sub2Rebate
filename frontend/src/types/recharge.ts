export type RechargeChannel = 'alipay' | 'epay'
export type RechargeMode = 'manual_qr' | 'epay'
export type RechargeStatus = 'pending' | 'submitted' | 'paid' | 'approved' | 'failed' | 'rejected' | 'expired'
export type CreditStatus = 'pending' | 'success' | 'failed'

export interface EpayConfig {
  enabled: boolean
  pid: string
  key?: string
  hasKey?: boolean
  gatewayUrl: string
  notifyUrl: string
  returnUrl: string
  displayName: string
  sitename: string
  type: string
}

export interface RechargeConfig {
  enabled: boolean
  mode: RechargeMode
  channel: RechargeChannel
  qrUrl: string
  displayName: string
  note: string
  expireMinutes: number
  epay?: EpayConfig
}

export interface RechargeOrder {
  id: number
  orderNo: string
  channel: RechargeChannel
  outTradeNo: string
  providerTradeNo: string
  subject: string
  amount: string
  bonusAmount: string
  creditAmount: string
  paidAmount: string
  sub2BalanceBefore: string
  sub2BalanceAfter: string
  status: RechargeStatus
  tradeStatus: string
  creditStatus: CreditStatus
  payerName: string
  payerAccount: string
  voucherImageUrl: string
  remark: string
  reviewRemark: string
  creditFailMsg: string
  rebateEventId: number | null
  submittedAt: string
  reviewedAt: string
  paidAt: string
  creditedAt: string
  expireAt: string
  createdAt: string
  payUrl: string
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

export interface EpayReturnReq {
  pid: string
  trade_no: string
  out_trade_no: string
  type: string
  name: string
  money: string
  trade_status: string
  sign: string
  sign_type?: string
}

export interface AdminRechargeOrder extends RechargeOrder {
  userId: number
  username: string
  nickname: string
}
