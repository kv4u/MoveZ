<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import Sidebar from '@/components/Sidebar.vue'
import UpdateBanner from '@/components/UpdateBanner.vue'
import { useSettingsStore } from '@/stores/settings'

const settingsStore = useSettingsStore()
onMounted(() => settingsStore.load())
</script>

<template>
  <div class="flex h-screen bg-slate-950 text-slate-100 overflow-hidden dark">
    <Sidebar />
    <div class="flex-1 flex flex-col min-w-0">
      <UpdateBanner />
      <main class="flex-1 overflow-auto">
        <RouterView />
      </main>
    </div>
  </div>
</template>
