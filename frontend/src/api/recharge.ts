import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import type { ApiRes, PageRes } from '@/types/api'
import type { RechargeConfig, RechargeOrder, AdminRechargeOrder, CreateRechargeOrderReq, SubmitRechargeOrderReq, EpayReturnReq } from '@/types/recharge'

const calcBonus = (amount: number) => {
  if (amount >= 1000) return 120
  if (amount >= 500) return 50
  if (amount >= 200) return 15
  if (amount >= 100) return 5
  return 0
}

const mockConfig: RechargeConfig = {
  enabled: true,
  mode: 'manual_qr',
  channel: 'alipay',
  qrUrl: 'https://via.placeholder.com/320x320.png?text=Alipay+QR',
  displayName: '支付宝收款码',
  note: '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。',
  expireMinutes: 15,
}

const mockOrder = (amount: string | number): RechargeOrder => {
  const val = Number(amount)
  const bonus = calcBonus(val)

  return {
    id: Date.now(),
    orderNo: `RC${Date.now()}`,
    channel: 'alipay',
    outTradeNo: '',
    providerTradeNo: '',
    subject: `API充值-${val.toFixed(2)}元`,
    amount: val.toFixed(2),
    bonusAmount: bonus.toFixed(2),
    creditAmount: (val + bonus).toFixed(2),
    paidAmount: '',
    sub2BalanceBefore: '',
    sub2BalanceAfter: '',
    status: 'pending',
    tradeStatus: '',
    creditStatus: 'pending',
    payerName: '',
    payerAccount: '',
    voucherImageUrl: '',
    remark: '',
    reviewRemark: '',
    creditFailMsg: '',
    rebateEventId: null,
    submittedAt: '',
    reviewedAt: '',
    paidAt: '',
    creditedAt: '',
    expireAt: new Date(Date.now() + 15 * 60 * 1000).toISOString().replace('T', ' ').slice(0, 19),
    createdAt: new Date().toISOString().replace('T', ' ').slice(0, 19),
    payUrl: '',
    qrUrl: mockConfig.qrUrl,
    displayName: mockConfig.displayName,
    note: mockConfig.note,
  }
}

export const getRechargeConfig = async (): Promise<ApiRes<RechargeConfig>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockConfig }
  }
  return request.get('/recharge/config')
}

export const createRechargeOrder = async (data: CreateRechargeOrderReq): Promise<ApiRes<RechargeOrder>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: mockOrder(data.amount) }
  }
  return request.post('/recharge/orders', data)
}

export const submitRechargeOrder = async (id: number, data: SubmitRechargeOrderReq): Promise<ApiRes<RechargeOrder>> => {
  if (useMock) {
    await delay(400)
    const order = mockOrder(500)
    order.id = id
    order.status = 'submitted'
    order.payerName = data.payerName
    order.payerAccount = data.payerAccount
    order.voucherImageUrl = data.voucherImageUrl || ''
    order.submittedAt = new Date().toISOString().replace('T', ' ').slice(0, 19)
    return { code: 0, message: 'ok', data: order }
  }
  return request.post(`/recharge/orders/${id}/submit`, data)
}

export const getRechargeOrders = async (
  page = 1,
  pageSize = 20,
  status?: string,
  startDate?: string,
  endDate?: string,
): Promise<ApiRes<PageRes<RechargeOrder>>> => {
  if (useMock) {
    await delay()
    let list = [mockOrder(100), { ...mockOrder(500), id: 2, status: 'submitted' as const }]
    if (status) list = list.filter((item) => item.status === status)
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  return request.get('/recharge/orders', { params: { page, pageSize, status, startDate, endDate } })
}

export const getRechargeOrder = async (id: number): Promise<ApiRes<RechargeOrder>> => {
  if (useMock) {
    await delay()
    const order = mockOrder(100)
    order.id = id
    return { code: 0, message: 'ok', data: order }
  }
  return request.get(`/recharge/orders/${id}`)
}

export const syncEpayReturn = async (data: EpayReturnReq): Promise<ApiRes<RechargeOrder>> => {
  if (useMock) {
    await delay(400)
    const order = mockOrder(data.money)
    order.orderNo = data.out_trade_no
    order.outTradeNo = data.out_trade_no
    order.providerTradeNo = data.trade_no
    order.channel = 'epay'
    order.status = 'approved'
    order.tradeStatus = data.trade_status
    order.creditStatus = 'success'
    order.paidAmount = data.money
    order.sub2BalanceBefore = '100.00'
    order.sub2BalanceAfter = (100 + Number(data.money || 0)).toFixed(2)
    return { code: 0, message: 'ok', data: order }
  }
  return request.post('/recharge/epay/return', data)
}

export const getAdminRechargeOrders = async (page = 1, pageSize = 20, status?: string): Promise<ApiRes<PageRes<AdminRechargeOrder>>> => {
  if (useMock) {
    await delay()
    const list: AdminRechargeOrder[] = [{ ...mockOrder(500), id: 1, status: 'submitted', userId: 1001, username: 'user1', nickname: '用户1' }]
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  return request.get('/admin/recharge-orders', { params: { page, pageSize, status } })
}

export const approveRechargeOrder = async (id: number, remark: string): Promise<ApiRes<AdminRechargeOrder>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: { ...mockOrder(500), id, status: 'approved', userId: 1001, username: 'user1', nickname: '用户1', reviewRemark: remark, paidAt: new Date().toISOString().replace('T', ' ').slice(0, 19) } }
  }
  return request.post(`/admin/recharge-orders/${id}/approve`, { remark })
}

export const rejectRechargeOrder = async (id: number, remark: string): Promise<ApiRes<AdminRechargeOrder>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: { ...mockOrder(500), id, status: 'rejected', userId: 1001, username: 'user1', nickname: '用户1', reviewRemark: remark } }
  }
  return request.post(`/admin/recharge-orders/${id}/reject`, { remark })
}

export const retryRechargeCredit = async (id: number): Promise<ApiRes<AdminRechargeOrder>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: { ...mockOrder(500), id, status: 'approved', userId: 1001, username: 'user1', nickname: '用户1' } }
  }
  return request.post(`/admin/recharge-orders/${id}/retry-credit`)
}
