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

// The renderer may only change known keys, with the right types. cliPath and
// phpPath are executed by the main process, so they must be plain strings.
const VALIDATORS: { [K in keyof Settings]: (v: unknown) => v is Settings[K] } = {
  cliPath:   (v): v is string => typeof v === 'string' && v.length < 1024 && !/[\r\n]/.test(v),
  phpPath:   (v): v is string => typeof v === 'string' && v.length < 1024 && !/[\r\n]/.test(v),
  serverUrl: (v): v is string => typeof v === 'string' && (v === '' || /^https?:\/\/[^\s]+$/i.test(v)),
  token:     (v): v is string => typeof v === 'string' && v.length < 512,
  darkMode:  (v): v is boolean => typeof v === 'boolean'
}

function applySetting(key: string, value: unknown): void {
  if (!Object.prototype.hasOwnProperty.call(VALIDATORS, key)) {
    throw new Error(`Unknown setting: ${key}`)
  }

  const k = key as keyof Settings
  if (!VALIDATORS[k](value)) {
    throw new Error(`Invalid value for setting ${key}`)
  }

  store.set(k, value as Settings[typeof k])
}

export function registerSettingsHandlers(): void {
  ipcMain.handle('settings:get', () => store.store)

  ipcMain.handle('settings:set', (_event, key: string, value: unknown) => {
    applySetting(key, value)
    return store.store
  })

  ipcMain.handle('settings:setAll', (_event, settings: Record<string, unknown>) => {
    Object.entries(settings ?? {}).forEach(([k, v]) => applySetting(k, v))
    return store.store
  })
}
