import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Settings } from '@/types/bridge'

export const useSettingsStore = defineStore('settings', () => {
  const settings = ref<Settings>({
    cliPath:   'movez',
    serverUrl: '',
    token:     '',
    darkMode:  true
  })

  async function load() {
    settings.value = await window.bridge.getSettings()
    applyTheme(settings.value.darkMode)
  }

  async function save(partial: Partial<Settings>) {
    settings.value = await window.bridge.saveSettings(partial)
    if ('darkMode' in partial) applyTheme(partial.darkMode!)
  }

  function applyTheme(dark: boolean) {
    document.documentElement.classList.toggle('dark', dark)
  }

  return { settings, load, save }
})
