<script setup lang="ts">
defineProps<{
  title?: string
  description?: string
}>()
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <svg xmlns="http://www.w3.org/2000/svg" class="mb-4 h-16 w-16 text-[var(--sr-border)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
      <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
    </svg>
    <div v-if="title" class="text-sm font-semibold text-[var(--sr-primary)]">{{ title }}</div>
    <div class="mt-1 text-xs text-[var(--sr-muted)]">{{ description || '暂无数据' }}</div>
    <div class="mt-4">
      <slot />
    </div>
  </div>
</template>
