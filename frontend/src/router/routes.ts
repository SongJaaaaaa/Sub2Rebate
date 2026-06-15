import type { RouteRecordRaw } from 'vue-router'
import UserLayout from '@/layouts/UserLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import AdminLayout from '@/layouts/AdminLayout.vue'

export const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    component: AuthLayout,
    children: [{ path: '', name: 'login', component: () => import('@/views/auth/LoginView.vue') }],
  },
  {
    path: '/',
    component: UserLayout,
    redirect: '/dashboard',
    children: [
      { path: 'dashboard', name: 'dashboard', component: () => import('@/views/dashboard/DashboardView.vue'), meta: { title: '仪表盘' } },
      { path: 'promotion', name: 'promotion', component: () => import('@/views/promotion/PromotionView.vue'), meta: { title: '推广中心' } },
      { path: 'my-team', name: 'my-team', component: () => import('@/views/promotion/MyRelationshipView.vue'), meta: { title: '我的团队' } },
      { path: 'recharge', name: 'recharge', component: () => import('@/views/recharge/RechargeView.vue'), meta: { title: '额度充值' } },
      { path: 'withdraw', name: 'withdraw', component: () => import('@/views/withdraw/WithdrawView.vue'), meta: { title: '提现管理' } },
      { path: 'account', name: 'account', component: () => import('@/views/account/AccountView.vue'), meta: { title: '账户设置' } },
    ],
  },
  {
    path: '/admin',
    component: AdminLayout,
    redirect: '/admin/dashboard',
    meta: { requiresAdmin: true },
    children: [
      { path: 'dashboard', name: 'admin-dashboard', component: () => import('@/views/admin/AdminDashboardView.vue'), meta: { title: '数据看板', requiresAdmin: true } },
      { path: 'relationships', name: 'admin-relationships', component: () => import('@/views/admin/AdminRelationshipView.vue'), meta: { title: '推荐关系', requiresAdmin: true } },
      { path: 'users', name: 'admin-users', component: () => import('@/views/admin/AdminUsersView.vue'), meta: { title: '用户管理', requiresAdmin: true } },
      { path: 'user-rebate', name: 'admin-user-rebate', component: () => import('@/views/admin/AdminUserRebateView.vue'), meta: { title: '用户返利设置', requiresAdmin: true } },
      { path: 'api-quota', name: 'admin-api-quota', component: () => import('@/views/admin/AdminApiQuotaView.vue'), meta: { title: 'API额度管理', requiresAdmin: true } },
      { path: 'withdrawals', name: 'admin-withdrawals', component: () => import('@/views/admin/AdminWithdrawalsView.vue'), meta: { title: '提现审核', requiresAdmin: true } },
      { path: 'rebate-config', name: 'admin-rebate-config', component: () => import('@/views/admin/AdminRebateConfigView.vue'), meta: { title: '返利配置', requiresAdmin: true } },
      { path: 'audit-log', name: 'admin-audit-log', component: () => import('@/views/admin/AdminAuditLogView.vue'), meta: { title: '审计日志', requiresAdmin: true } },
    ],
  },
]
