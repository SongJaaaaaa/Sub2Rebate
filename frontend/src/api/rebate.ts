import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import { mockRebateRecords } from '@/mocks/promotion'
import type { ApiRes, PageRes } from '@/types/api'
import type { RebateRecord, RebateType, RebateStatus } from '@/types/rebate'

export interface RebateRecordQuery {
  page?: number
  pageSize?: number
  type?: RebateType
  status?: RebateStatus
  startDate?: string
  endDate?: string
}

export const getRebateRecords = async (query: RebateRecordQuery = {}): Promise<ApiRes<PageRes<RebateRecord>>> => {
  const { page = 1, pageSize = 20, type, status, startDate, endDate } = query
  if (useMock) {
    await delay()
    let list = [...mockRebateRecords]
    if (type) list = list.filter((r) => r.type === type)
    if (status) list = list.filter((r) => r.status === status)
    return { code: 0, message: 'ok', data: { list, page, pageSize, total: list.length } }
  }
  return request.get('/rebate/records', { params: { page, pageSize, type, status, startDate, endDate } })
}
