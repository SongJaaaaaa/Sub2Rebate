<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AdminSideNav from '@/components/layout/AdminSideNav.vue'
import TopBar from '@/components/layout/TopBar.vue'

const drawerVisible = ref(false)
const route = useRoute()

watch(() => route.path, () => { drawerVisible.value = false })
</script>

<template>
  <div class="min-h-screen bg-[var(--sr-surface)] text-[var(--sr-primary)] md:flex">
    <AdminSideNav :visible="drawerVisible" @close="drawerVisible = false" />
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
