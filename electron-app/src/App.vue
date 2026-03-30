<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import Sidebar from '@/components/Sidebar.vue'
import { useSettingsStore } from '@/stores/settings'

const settingsStore = useSettingsStore()
onMounted(() => settingsStore.load())
</script>

<template>
  <div class="flex h-screen bg-slate-950 text-slate-100 overflow-hidden dark">
    <Sidebar />
    <main class="flex-1 overflow-auto">
      <RouterView />
    </main>
  </div>
</template>
