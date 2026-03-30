export interface SessionSummary {
  id: string
  title: string
  project: string
  source_tool: string
  created_at: string
  last_active_at: string
  turn_count: number
}

export interface TurnDTO {
  role: 'user' | 'assistant'
  content: string
  timestamp: string
  files_referenced: string[]
  file_diffs: { file: string; diff: string }[]
  reasoning_trace: string | null
  tool_calls: unknown[]
}

export interface SessionDetail extends SessionSummary {
  turns: TurnDTO[]
}

export interface Settings {
  cliPath: string
  serverUrl: string
  token: string
  darkMode: boolean
}

export interface TransferResult {
  success: boolean
  output: string
}

export interface Bridge {
  listSessions:      (tool?: string) => Promise<SessionSummary[]>
  exportSessions:    (opts: ExportOpts) => Promise<{ success: boolean; output: string }>
  importSessions:    (opts: ImportOpts) => Promise<{ success: boolean; output: string }>
  transfer:          (opts: TransferOpts) => Promise<TransferResult>
  onMigrateProgress: (cb: (line: string) => void) => () => void
  syncPush:          (opts: SyncOpts) => Promise<TransferResult>
  syncPull:          (opts: SyncPullOpts) => Promise<TransferResult>
  onSyncLog:         (cb: (line: string) => void) => () => void
  runDoctor:         () => Promise<{ success: boolean; lines: string[] }>
  onDoctorLine:      (cb: (line: string) => void) => () => void
  getSettings:       () => Promise<Settings>
  setSetting:        (key: string, value: unknown) => Promise<Settings>
  saveSettings:      (settings: Partial<Settings>) => Promise<Settings>
  openFile:          (opts: { title?: string; filters?: FileFilter[] }) => Promise<string | null>
  openDirectory:     (opts: { title?: string }) => Promise<string | null>
  saveFile:          (opts: { title?: string; defaultPath?: string; filters?: FileFilter[] }) => Promise<string | null>
  toggleTheme:       () => Promise<boolean>
  getTheme:          () => Promise<boolean>
}

export interface ExportOpts {
  tool: string
  outputPath: string
  encrypt: boolean
  project?: string
}

export interface ImportOpts {
  inputPath: string
  tool: string
  encrypted: boolean
  fromPath?: string
  toPath?: string
  project?: string
}

export interface TransferOpts {
  fromTool: string
  toTool: string
  project?: string
  fromPath?: string
  toPath?: string
}

export interface SyncOpts {
  token: string
  server: string
  tool?: string
  project?: string
}

export interface SyncPullOpts extends SyncOpts {
  tool: string
  fromPath?: string
  toPath?: string
}

export interface FileFilter {
  name: string
  extensions: string[]
}

declare global {
  interface Window {
    bridge: Bridge
  }
}
