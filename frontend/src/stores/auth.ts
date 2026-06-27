import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { login as apiLogin, logout as apiLogout, getMe } from '@/api/auth'
import { resetRouterAuth } from '@/router'
import type { User, Balance, LoginReq, Sub2ApiBalance } from '@/types/user'

const TOKEN_KEY = 'sr_token'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(TOKEN_KEY) || '')
  const user = ref<User | null>(null)
  const balance = ref<Balance | null>(null)
  const sub2ApiBalance = ref<Sub2ApiBalance | null>(null)
  const loading = ref(false)

  const isLogin = computed(() => Boolean(token.value))

  const setToken = (val: string) => {
    token.value = val
    if (val) {
      localStorage.setItem(TOKEN_KEY, val)
    } else {
      localStorage.removeItem(TOKEN_KEY)
    }
  }

  const clearSession = () => {
    setToken('')
    user.value = null
    balance.value = null
    sub2ApiBalance.value = null
    sessionStorage.clear()
    resetRouterAuth()
  }

  const login = async (form: LoginReq) => {
    loading.value = true
    try {
      clearSession()
      const res = await apiLogin(form)
      if (res.code === 0) {
        setToken(res.data.token)
        user.value = res.data.user
      } else {
        throw new Error(res.message)
      }
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    try {
      await apiLogout()
    } finally {
      clearSession()
    }
  }

  const fetchMe = async () => {
    if (!token.value) return
    loading.value = true
    try {
      const res = await getMe()
      if (res.code === 0) {
        user.value = res.data.user
        balance.value = res.data.balance
        sub2ApiBalance.value = res.data.sub2ApiBalance || null
      } else {
        // token 无效
        clearSession()
      }
    } catch {
      clearSession()
    } finally {
      loading.value = false
    }
  }

  return { token, user, balance, sub2ApiBalance, loading, isLogin, login, logout, fetchMe, clearSession }
})
