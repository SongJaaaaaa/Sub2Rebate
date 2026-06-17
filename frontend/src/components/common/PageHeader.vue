<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="mb-6">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold md:text-3xl">{{ title }}</h1>
        <p v-if="description" class="mt-2 text-sm text-[var(--sr-muted)]">{{ description }}</p>
      </div>
      <div class="shrink-0">
        <slot name="actions" />
      </div>
    </div>
    <slot name="extra" />
  </div>
</template>
