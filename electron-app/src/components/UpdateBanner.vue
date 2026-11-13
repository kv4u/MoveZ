<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useSettingsStore } from '@/stores/settings'
import type { UpdateInfo } from '@/types/bridge'
import Icon from './Icon.vue'

const settingsStore = useSettingsStore()
const update        = ref<UpdateInfo | null>(null)
const dismissed     = ref(false)

onMounted(async () => {
  await settingsStore.load()
  if (!settingsStore.settings.checkUpdates) return
  update.value = await window.bridge.checkForUpdates()
})

function download() {
  window.bridge.openReleasePage()
}
</script>

<template>
  <div
    v-if="update?.available && !dismissed"
    class="flex items-center gap-3 px-5 py-2.5 border-b border-slate-800 bg-slate-900 text-sm"
    role="status"
  >
    <span class="w-2 h-2 rounded-full bg-brand-400 shrink-0" />
    <span class="text-slate-200">
      MoveZ {{ update.latest }} is available
      <span class="text-slate-500">— you have {{ update.current }}</span>
    </span>
    <button
      class="ml-auto px-3.5 py-1 rounded-full bg-brand-600 hover:bg-brand-500 text-ink text-xs font-semibold transition-colors"
      @click="download"
    >
      Download
    </button>
    <button
      class="p-1 rounded-full text-slate-500 hover:text-slate-200 hover:bg-slate-800 transition-colors"
      aria-label="Dismiss"
      @click="dismissed = true"
    >
      <Icon name="x" :size="14" />
    </button>
  </div>
</template>
