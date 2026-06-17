import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import { mockWithdrawConfig, mockWithdrawAccount, mockWithdrawRecords, mockBalanceAfterApply } from '@/mocks/withdraw'
import type { ApiRes, PageRes } from '@/types/api'
import type { WithdrawConfig, WithdrawAccount, WithdrawRecord, WithdrawApplyReq } from '@/types/withdraw'
import type { Balance } from '@/types/user'

export const getWithdrawConfig = async (): Promise<ApiRes<WithdrawConfig>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockWithdrawConfig }
  }
  return request.get('/withdraw/config')
}

export const getWithdrawAccount = async (): Promise<ApiRes<{ account: WithdrawAccount | null }>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { account: mockWithdrawAccount } }
  }
  return request.get('/withdraw/account')
}

export const saveWithdrawAccount = async (data: { type: string; realName: string; accountNo: string }): Promise<ApiRes<{ account: WithdrawAccount }>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { account: { ...mockWithdrawAccount, ...data, type: 'alipay' } } }
  }
  return request.post('/withdraw/account', data)
}

export const applyWithdraw = async (data: WithdrawApplyReq): Promise<ApiRes<{ record: WithdrawRecord; balance: Balance }>> => {
  if (useMock) {
    await delay(500)
    const record: WithdrawRecord = {
      id: Date.now(),
      amount: data.amount,
      status: 'pending',
      accountType: 'alipay',
      accountNo: mockWithdrawAccount.accountNo,
      realName: mockWithdrawAccount.realName,
      remark: data.remark || '',
      rejectReason: '',
      paidAt: null,
      createdAt: new Date().toISOString().replace('T', ' ').slice(0, 19),
    }
    return { code: 0, message: 'ok', data: { record, balance: mockBalanceAfterApply } }
  }
  return request.post('/withdraw/apply', data)
}

export const getWithdrawRecords = async (page = 1, pageSize = 20, status?: string): Promise<ApiRes<PageRes<WithdrawRecord>>> => {
  if (useMock) {
    await delay()
    let list = [...mockWithdrawRecords]
    if (status) list = list.filter((r) => r.status === status)
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  return request.get('/withdraw/records', { params: { page, pageSize, status } })
}
