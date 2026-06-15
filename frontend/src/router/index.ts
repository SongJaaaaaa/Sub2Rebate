import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

let initialized = false

export const resetRouterAuth = () => {
  initialized = false
}

router.beforeEach(async (to) => {
  const token = localStorage.getItem('sr_token')

  // 未登录 -> 跳转登录页
  if (!token && to.name !== 'login') {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  // 已登录 -> 不能再访问登录页
  if (token && to.name === 'login') {
    return { name: 'dashboard' }
  }

  // 首次进入且已有 token，尝试获取用户信息
  if (token && !initialized) {
    initialized = true
    const { useAuthStore } = await import('@/stores/auth')
    const auth = useAuthStore()
    await auth.fetchMe()
    // fetchMe 失败会清除 token
    if (!auth.isLogin && to.name !== 'login') {
      return { name: 'login' }
    }
  }

  // 管理端权限守卫
  if (to.meta.requiresAdmin) {
    const { useAuthStore } = await import('@/stores/auth')
    const auth = useAuthStore()
    if (auth.user?.role !== 'admin') {
      return { name: 'dashboard' }
    }
  }

  return true
})

export default router
