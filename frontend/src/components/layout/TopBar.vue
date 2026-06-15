<script setup lang="ts">
import { Bell, Fold, SwitchButton } from '@element-plus/icons-vue'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

defineEmits<{
  toggleMenu: []
}>()

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const title = computed(() => String(route.meta.title || '仪表盘'))

const onLogout = async () => {
  await auth.logout()
  router.replace('/login')
}
</script>

<template>
  <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-[var(--sr-border)] bg-white/90 px-4 backdrop-blur md:px-8">
    <div class="flex items-center gap-3">
      <el-button class="md:!hidden" :icon="Fold" circle size="small" @click="$emit('toggleMenu')" />
      <div class="text-lg font-bold md:hidden">Sub2Rebate</div>
      <h1 class="hidden text-base font-semibold text-[var(--sr-primary)] md:block">{{ title }}</h1>
    </div>

    <div class="flex items-center gap-3">
      <el-badge :value="0" :hidden="true">
        <el-button :icon="Bell" circle size="small" />
      </el-badge>
      <div class="hidden border-l border-[var(--sr-border)] pl-4 text-right sm:block">
        <div class="text-sm font-semibold">{{ auth.user?.nickname || '未登录' }}</div>
        <div class="text-xs text-[var(--sr-muted)]">{{ auth.user?.role === 'admin' ? '管理员' : '推广员' }}</div>
      </div>
      <el-button :icon="SwitchButton" size="small" @click="onLogout">退出</el-button>
    </div>
  </header>
</template>
