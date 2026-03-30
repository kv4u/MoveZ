import { ipcMain } from 'electron'
import Store from 'electron-store'

interface Settings {
  cliPath:   string
  phpPath:   string
  serverUrl: string
  token:     string
  darkMode:  boolean
}

const store = new Store<Settings>({
  defaults: {
    cliPath:   'movez',
    phpPath:   'php',
    serverUrl: '',
    token:     '',
    darkMode:  true
  }
})

export function registerSettingsHandlers(): void {
  ipcMain.handle('settings:get', () => store.store)

  ipcMain.handle('settings:set', (_event, key: keyof Settings, value: unknown) => {
    store.set(key, value)
    return store.store
  })

  ipcMain.handle('settings:setAll', (_event, settings: Partial<Settings>) => {
    Object.entries(settings).forEach(([k, v]) => store.set(k as keyof Settings, v))
    return store.store
  })
}
