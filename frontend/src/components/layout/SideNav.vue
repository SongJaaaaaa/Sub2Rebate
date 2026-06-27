<script setup lang="ts">
import { House, Promotion, Setting, Wallet, Connection, CreditCard, List } from '@element-plus/icons-vue'

defineProps<{
  visible?: boolean
}>()

defineEmits<{
  close: []
}>()

const items = [
  { path: '/dashboard', text: '仪表盘', icon: House },
  { path: '/promotion', text: '推广中心', icon: Promotion },
  { path: '/my-team', text: '我的团队', icon: Connection },
  { path: '/recharge', text: '额度充值', icon: CreditCard },
  { path: '/rebate/records', text: '返利明细', icon: List },
  { path: '/withdraw', text: '提现管理', icon: Wallet },
  { path: '/account', text: '账户设置', icon: Setting },
]
</script>

<template>
  <!-- Desktop sidebar -->
  <aside class="hidden h-screen w-64 shrink-0 border-r border-[var(--sr-border)] bg-white px-4 py-6 md:flex md:flex-col md:sticky md:top-0">
    <div class="mb-8 px-2">
      <div class="text-2xl font-bold text-[var(--sr-primary)]">Sub2Rebate</div>
      <div class="mt-1 text-xs font-semibold uppercase tracking-widest text-[var(--sr-muted)]">返利推广中心</div>
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

    <RouterLink to="/withdraw" class="block rounded-lg bg-[var(--sr-primary)] px-4 py-3 text-center text-sm font-bold text-white transition hover:opacity-90">
      申请提现
    </RouterLink>
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
          <div class="mt-1 text-xs font-semibold uppercase tracking-widest text-[var(--sr-muted)]">返利推广中心</div>
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

        <RouterLink
          to="/withdraw"
          class="block rounded-lg bg-[var(--sr-primary)] px-4 py-3 text-center text-sm font-bold text-white transition hover:opacity-90"
          @click="$emit('close')"
        >
          申请提现
        </RouterLink>
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
