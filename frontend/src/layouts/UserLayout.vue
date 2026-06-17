<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { watch } from 'vue'
import SideNav from '@/components/layout/SideNav.vue'
import TopBar from '@/components/layout/TopBar.vue'

const drawerVisible = ref(false)
const route = useRoute()

// 路由变化时关闭抽屉
watch(() => route.path, () => { drawerVisible.value = false })
</script>

<template>
  <div class="min-h-screen bg-[var(--sr-surface)] text-[var(--sr-primary)] md:flex">
    <SideNav :visible="drawerVisible" @close="drawerVisible = false" />
    <div class="min-w-0 flex-1">
      <TopBar @toggle-menu="drawerVisible = !drawerVisible" />
      <main class="mx-auto w-full max-w-content px-4 py-6 md:px-8">
        <RouterView v-slot="{ Component }">
          <Transition name="fade-slide" mode="out-in">
            <component :is="Component" />
          </Transition>
        </RouterView>
      </main>
    </div>
  </div>
</template>
