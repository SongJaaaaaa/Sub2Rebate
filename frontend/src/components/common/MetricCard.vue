<script setup lang="ts">
import AppCard from './AppCard.vue'

defineProps<{
  label: string
  value: string
  hint?: string
  hintType?: 'success' | 'danger' | 'muted'
}>()
</script>

<template>
  <AppCard class="transition-shadow hover:shadow-card-hover">
    <div class="text-xs font-semibold uppercase tracking-wide text-[var(--sr-muted)]">{{ label }}</div>
    <div class="mt-3 text-3xl font-bold leading-tight">{{ value }}</div>
    <div
      v-if="hint"
      class="mt-3 text-xs font-semibold"
      :class="{
        'text-[var(--sr-success)]': !hintType || hintType === 'success',
        'text-[var(--sr-danger)]': hintType === 'danger',
        'text-[var(--sr-muted)]': hintType === 'muted',
      }"
    >
      {{ hint }}
    </div>
  </AppCard>
</template>
