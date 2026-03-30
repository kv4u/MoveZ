import { contextBridge, ipcRenderer } from 'electron'

export const bridge = {
  // Sessions
  listSessions:   (tool?: string) => ipcRenderer.invoke('sessions:list', tool),
  exportSessions: (opts: unknown) => ipcRenderer.invoke('sessions:export', opts),
  importSessions: (opts: unknown) => ipcRenderer.invoke('sessions:import', opts),

  // Migration
  transfer: (opts: unknown) => ipcRenderer.invoke('migrate:transfer', opts),
  onMigrateProgress: (cb: (line: string) => void) => {
    const handler = (_: Electron.IpcRendererEvent, line: string) => cb(line)
    ipcRenderer.on('migrate:progress', handler)
    return () => ipcRenderer.removeListener('migrate:progress', handler)
  },

  // Sync
  syncPush: (opts: unknown) => ipcRenderer.invoke('sync:push', opts),
  syncPull: (opts: unknown) => ipcRenderer.invoke('sync:pull', opts),
  onSyncLog: (cb: (line: string) => void) => {
    const handler = (_: Electron.IpcRendererEvent, line: string) => cb(line)
    ipcRenderer.on('sync:log', handler)
    return () => ipcRenderer.removeListener('sync:log', handler)
  },

  // Doctor
  runDoctor: () => ipcRenderer.invoke('doctor:run'),
  onDoctorLine: (cb: (line: string) => void) => {
    const handler = (_: Electron.IpcRendererEvent, line: string) => cb(line)
    ipcRenderer.on('doctor:line', handler)
    return () => ipcRenderer.removeListener('doctor:line', handler)
  },

  // Settings
  getSettings:  ()             => ipcRenderer.invoke('settings:get'),
  setSetting:   (key: string, value: unknown) => ipcRenderer.invoke('settings:set', key, value),
  saveSettings: (settings: unknown) => ipcRenderer.invoke('settings:setAll', settings),

  // Dialogs
  openFile:      (opts: unknown) => ipcRenderer.invoke('dialog:openFile', opts),
  openDirectory: (opts: unknown) => ipcRenderer.invoke('dialog:openDirectory', opts),
  saveFile:      (opts: unknown) => ipcRenderer.invoke('dialog:saveFile', opts),

  // Theme
  toggleTheme: () => ipcRenderer.invoke('theme:toggle'),
  getTheme:    () => ipcRenderer.invoke('theme:get')
}

contextBridge.exposeInMainWorld('bridge', bridge)
