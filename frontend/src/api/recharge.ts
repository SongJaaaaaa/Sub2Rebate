import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import type { ApiRes, PageRes } from '@/types/api'
import type { RechargeConfig, RechargeOrder, AdminRechargeOrder, CreateRechargeOrderReq, SubmitRechargeOrderReq, EpayPayResult } from '@/types/recharge'

const calcBonus = (amount: number) => {
  if (amount >= 1000) return 120
  if (amount >= 500) return 50
  if (amount >= 200) return 15
  if (amount >= 100) return 5
  return 0
}

const mockConfig: RechargeConfig = {
  enabled: true,
  channel: 'alipay',
  qrUrl: 'https://via.placeholder.com/320x320.png?text=Alipay+QR',
  displayName: '支付宝收款码',
  note: '付款时请备注订单号，支付后点击“我已完成支付”等待审核到账。',
  expireMinutes: 15,
  epayEnabled: true,
}

const mockOrder = (amount: string | number): RechargeOrder => {
  const val = Number(amount)
  const bonus = calcBonus(val)

  return {
    id: Date.now(),
    orderNo: `RC${Date.now()}`,
    channel: 'alipay',
    amount: val.toFixed(2),
    bonusAmount: bonus.toFixed(2),
    creditAmount: (val + bonus).toFixed(2),
    status: 'pending',
    payerName: '',
    payerAccount: '',
    voucherImageUrl: '',
    remark: '',
    reviewRemark: '',
    rebateEventId: null,
    payMethod: '',
    epayTradeNo: '',
    sub2BalanceBefore: null,
    sub2BalanceAfter: null,
    submittedAt: '',
    reviewedAt: '',
    paidAt: '',
    expireAt: new Date(Date.now() + 15 * 60 * 1000).toISOString().replace('T', ' ').slice(0, 19),
    createdAt: new Date().toISOString().replace('T', ' ').slice(0, 19),
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

export const createEpayOrder = async (data: CreateRechargeOrderReq): Promise<ApiRes<EpayPayResult>> => {
  if (useMock) {
    await delay(400)
    const order = mockOrder(data.amount)
    order.channel = 'epay'
    return {
      code: 0,
      message: 'ok',
      data: {
        order,
        payType: 'qrcode',
        payInfo: 'https://qr.alipay.com/mock-epay-' + order.orderNo,
      },
    }
  }
  return request.post('/recharge/epay/pay', data)
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

export const getRechargeOrders = async (page = 1, pageSize = 20, status?: string): Promise<ApiRes<PageRes<RechargeOrder>>> => {
  if (useMock) {
    await delay()
    const list = [mockOrder(100), { ...mockOrder(500), id: 2, status: 'submitted' as const }]
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  return request.get('/recharge/orders', { params: { page, pageSize, status } })
}

export const getAdminRechargeOrders = async (page = 1, pageSize = 20, status?: string, keyword?: string, channel?: string): Promise<ApiRes<PageRes<AdminRechargeOrder>>> => {
  if (useMock) {
    await delay()
    const list: AdminRechargeOrder[] = [{ ...mockOrder(500), id: 1, status: 'submitted', userId: 1001, username: 'user1', nickname: '用户1' }]
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  return request.get('/admin/recharge-orders', { params: { page, pageSize, status, keyword, channel } })
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