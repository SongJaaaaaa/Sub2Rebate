import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios'
import { ElMessage } from 'element-plus'
import type { ApiRes } from '@/types/api'

const baseURL = import.meta.env.VITE_API_BASE_URL || '/api/v1'
let redirectingLogin = false

export const request = axios.create({
  baseURL,
  timeout: 15000,
  headers: { Accept: 'application/json' },
})

request.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = localStorage.getItem('sr_token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

request.interceptors.response.use(
  (res) => res.data,
  (err: AxiosError<ApiRes<unknown>>) => {
    const status = err.response?.status
    const msg = err.response?.data?.message || err.message || '请求失败'

    if (status === 401) {
      localStorage.removeItem('sr_token')
      sessionStorage.clear()
      // 使用 location 而非 router 避免循环依赖
      if (!redirectingLogin && window.location.pathname !== '/login') {
        redirectingLogin = true
        window.location.href = `/login?redirect=${encodeURIComponent(window.location.pathname)}`
      }
    } else {
      ElMessage.error(msg)
    }

    return Promise.reject(err)
  },
)
