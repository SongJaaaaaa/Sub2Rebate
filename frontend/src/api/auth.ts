import { request } from '@/utils/request'
import { useMock, delay } from '@/mocks'
import { mockLoginRes, mockAdminLoginRes, mockMeRes, mockAdminMeRes } from '@/mocks/auth'
import type { ApiRes } from '@/types/api'
import type { LoginReq, LoginRes, MeRes } from '@/types/user'

// Mock 账号：
// 普通用户: u1 / 123
// 管理员:   admin / 123

export const login = async (data: LoginReq): Promise<ApiRes<LoginRes>> => {
  if (useMock) {
    await delay()
    if (data.account === 'admin' && data.password === '123') {
      return { code: 0, message: 'ok', data: mockAdminLoginRes }
    }
    if (['demo', 'u1'].includes(data.account) && data.password === '123') {
      return { code: 0, message: 'ok', data: mockLoginRes }
    }
    return { code: 401, message: '账号或密码错误', data: mockLoginRes }
  }
  return request.post('/auth/login', data)
}

export const logout = async (): Promise<ApiRes<null>> => {
  if (useMock) {
    await delay(100)
    return { code: 0, message: 'ok', data: null }
  }
  return request.post('/auth/logout')
}

export const getMe = async (): Promise<ApiRes<MeRes>> => {
  if (useMock) {
    await delay(200)
    // 根据 token 判断返回哪个用户
    const token = localStorage.getItem('sr_token')
    if (token === 'mock-token-sub2rebate-admin') {
      return { code: 0, message: 'ok', data: mockAdminMeRes }
    }
    return { code: 0, message: 'ok', data: mockMeRes }
  }
  return request.get('/auth/me')
}
