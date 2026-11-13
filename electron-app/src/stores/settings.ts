import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Settings } from '@/types/bridge'

export const useSettingsStore = defineStore('settings', () => {
  const settings = ref<Settings>({
    cliPath:   'movez',
    phpPath:   'php',
    serverUrl: '',
    token:     ''
  })

  async function load() {
    settings.value = await window.bridge.getSettings()
  }

  async function save(partial: Partial<Settings>) {
    settings.value = await window.bridge.saveSettings(partial)
  }

  return { settings, load, save }
})
