import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import { mockAccountProfile } from '@/mocks/auth'
import type { ApiRes } from '@/types/api'
import type { AccountProfile } from '@/types/user'

export const getProfile = async (): Promise<ApiRes<AccountProfile>> => {
  if (useMock) {
    await delay()
    return { code: 0, message: 'ok', data: mockAccountProfile }
  }
  return request.get('/account/profile')
}

export interface ChangePasswordReq {
  oldPassword: string
  newPassword: string
}

export const changePassword = async (data: ChangePasswordReq): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(500)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post('/account/change-password', data)
}

export interface UpdateProfileReq {
  nickname?: string
  email?: string
}

export const updateProfile = async (data: UpdateProfileReq): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(400)
    return { code: 0, message: 'ok', data: null }
  }
  return request.put('/account/profile', data)
}
