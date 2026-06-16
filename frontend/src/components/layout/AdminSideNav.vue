<script setup lang="ts">
import { DataAnalysis, Setting, User, Wallet, Share, Coin, Document, CreditCard } from '@element-plus/icons-vue'

defineProps<{
  visible?: boolean
}>()

defineEmits<{
  close: []
}>()

const items = [
  { path: '/admin/dashboard', text: '数据看板', icon: DataAnalysis },
  { path: '/admin/relationships', text: '推荐关系', icon: Share },
  { path: '/admin/users', text: '用户管理', icon: User },
  { path: '/admin/user-rebate', text: '返利层级', icon: Setting },
  { path: '/admin/api-quota', text: 'API额度', icon: Coin },
  { path: '/admin/withdrawals', text: '提现审核', icon: Wallet },
  { path: '/admin/recharge-orders', text: '充值审核', icon: CreditCard },
  { path: '/admin/payment-config', text: '支付配置', icon: Setting },
  { path: '/admin/rebate-config', text: '返利配置', icon: Setting },
  { path: '/admin/audit-log', text: '审计日志', icon: Document },
]
</script>

<template>
  <!-- Desktop sidebar -->
  <aside class="hidden h-screen w-64 shrink-0 border-r border-[var(--sr-border)] bg-white px-4 py-6 md:flex md:flex-col md:sticky md:top-0">
    <div class="mb-8 px-2">
      <div class="text-2xl font-bold text-[var(--sr-primary)]">Sub2Rebate</div>
      <div class="mt-1 text-xs font-semibold uppercase tracking-widest text-[var(--sr-danger)]">管理后台</div>
    </div>

    <nav class="flex-1 space-y-1">
      <RouterLink
        v-for="item in items"
        :key="item.path"
        :to="item.path"
        class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold text-[var(--sr-muted)] transition hover:bg-[var(--sr-surface-low)] hover:text-[var(--sr-primary)]"
        active-class="!bg-[var(--sr-surface-low)] !text-[var(--sr-secondary)] border-r-4 border-[var(--sr-secondary)]"
      >
        <el-icon :size="18"><component :is="item.icon" /></el-icon>
        <span>{{ item.text }}</span>
      </RouterLink>
    </nav>
  </aside>

  <!-- Mobile overlay -->
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="visible" class="sr-overlay md:hidden" @click="$emit('close')" />
    </Transition>
  </Teleport>

  <!-- Mobile drawer -->
  <Teleport to="body">
    <Transition name="slide-left">
      <aside
        v-if="visible"
        class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-[var(--sr-border)] bg-white px-4 py-6 md:hidden"
      >
        <div class="mb-8 px-2">
          <div class="text-2xl font-bold text-[var(--sr-primary)]">Sub2Rebate</div>
          <div class="mt-1 text-xs font-semibold uppercase tracking-widest text-[var(--sr-danger)]">管理后台</div>
        </div>

        <nav class="flex-1 space-y-1">
          <RouterLink
            v-for="item in items"
            :key="item.path"
            :to="item.path"
            class="flex items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold text-[var(--sr-muted)] transition hover:bg-[var(--sr-surface-low)] hover:text-[var(--sr-primary)]"
            active-class="!bg-[var(--sr-surface-low)] !text-[var(--sr-secondary)] border-r-4 border-[var(--sr-secondary)]"
            @click="$emit('close')"
          >
            <el-icon :size="18"><component :is="item.icon" /></el-icon>
            <span>{{ item.text }}</span>
          </RouterLink>
        </nav>
      </aside>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 200ms ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.slide-left-enter-active,
.slide-left-leave-active {
  transition: transform 250ms cubic-bezier(0.4, 0, 0.2, 1);
}
.slide-left-enter-from,
.slide-left-leave-to {
  transform: translateX(-100%);
}
</style>
