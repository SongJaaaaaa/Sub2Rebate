import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import { mockInviteInfo, mockInviteTree, mockInviteRecords, mockPromotionSummary, mockConversions, mockRebateRecords } from '@/mocks/promotion'
import type { ApiRes, PageRes } from '@/types/api'
import type { InviteInfo, InviteTreeNode, InviteRecord, PromotionSummary, ConversionRecord, RebateRecord } from '@/types/rebate'

export const getInviteInfo = async (): Promise<ApiRes<InviteInfo>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockInviteInfo }
  }
  return request.get('/invite/me')
}

export const bindInviteCode = async (inviteCode: string): Promise<ApiRes<{ bound: boolean }>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { bound: true } }
  }
  return request.post('/invite/bind', { inviteCode })
}

export const getInviteTree = async (maxDepth = 3): Promise<ApiRes<{ root: InviteTreeNode }>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { root: mockInviteTree } }
  }
  return request.get('/invite/tree', { params: { maxDepth } })
}

export const getInviteRecords = async (page = 1, pageSize = 10): Promise<ApiRes<PageRes<InviteRecord>>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { list: mockInviteRecords, page, pageSize, total: mockInviteRecords.length } }
  }
  return request.get('/invite/records', { params: { page, pageSize } })
}

export const getPromotionSummary = async (): Promise<ApiRes<PromotionSummary>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockPromotionSummary }
  }
  return request.get('/promotion/summary')
}

export const getConversions = async (page = 1, pageSize = 10): Promise<ApiRes<PageRes<ConversionRecord>>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { list: mockConversions, page, pageSize, total: mockConversions.length } }
  }
  return request.get('/promotion/conversions', { params: { page, pageSize } })
}

export const getRebateRecords = async (page = 1, pageSize = 10): Promise<ApiRes<PageRes<RebateRecord>>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: { list: mockRebateRecords, page, pageSize, total: mockRebateRecords.length } }
  }
  return request.get('/promotion/rebate-records', { params: { page, pageSize } })
}
