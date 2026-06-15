<script setup lang="ts">
type TagType = 'info' | 'primary' | 'success' | 'warning' | 'danger'

defineProps<{
  text: string
  type?: TagType
}>()
</script>

<template>
  <el-tag :type="type || 'info'" size="small" round effect="light" disable-transitions>
    {{ text }}
  </el-tag>
</template>
