import { ipcMain } from 'electron'
import { runCli } from '../cli'

export function registerSessionHandlers(): void {
  // List sessions for a specific tool (or all tools)
  ipcMain.handle('sessions:list', async (_event, tool?: string) => {
    const args = ['list-sessions', '--json']
    if (tool) args.push(`--tool=${tool}`)

    const result = await runCli(args)
    if (result.code !== 0) {
      throw new Error(result.stderr || 'CLI error')
    }

    try {
      return JSON.parse(result.stdout)
    } catch {
      // Surface the raw output so the user can see what went wrong
      const preview = result.stdout.slice(0, 300) || result.stderr.slice(0, 300) || '(no output)'
      throw new Error(`Failed to parse session list. CLI output: ${preview}`)
    }
  })

  // Export sessions to a .cbz bundle
  ipcMain.handle('sessions:export', async (_event, opts: {
    tool: string
    outputPath: string
    encrypt: boolean
    project?: string
  }) => {
    const args = ['export', `--tool=${opts.tool}`, `--output=${opts.outputPath}`]
    if (opts.encrypt) args.push('--encrypt')
    if (opts.project) args.push(`--project=${opts.project}`)

    const result = await runCli(args)
    if (result.code !== 0) throw new Error(result.stderr || 'Export failed')
    return { success: true, output: result.stdout }
  })

  // Import sessions from a .cbz bundle
  ipcMain.handle('sessions:import', async (_event, opts: {
    inputPath: string
    tool: string
    encrypted: boolean
    fromPath?: string
    toPath?: string
    project?: string
  }) => {
    const args = ['import', `--input=${opts.inputPath}`, `--tool=${opts.tool}`]
    if (opts.encrypted) args.push('--encrypted')
    if (opts.fromPath) args.push(`--from-path=${opts.fromPath}`)
    if (opts.toPath) args.push(`--to-path=${opts.toPath}`)
    if (opts.project) args.push(`--project=${opts.project}`)

    const result = await runCli(args)
    if (result.code !== 0) throw new Error(result.stderr || 'Import failed')
    return { success: true, output: result.stdout }
  })
}
