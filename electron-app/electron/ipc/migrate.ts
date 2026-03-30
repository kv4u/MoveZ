import { ipcMain, BrowserWindow } from 'electron'
import { streamCli } from '../cli'

export function registerMigrateHandlers(): void {
  // Transfer: runs movez transfer and streams progress back
  ipcMain.handle('migrate:transfer', async (event, opts: {
    fromTool: string
    toTool: string
    project?: string
    fromPath?: string
    toPath?: string
  }) => {
    const args = ['transfer', `--from=${opts.fromTool}`, `--to=${opts.toTool}`]
    if (opts.project) args.push(`--project=${opts.project}`)
    if (opts.fromPath) args.push(`--from-path=${opts.fromPath}`)
    if (opts.toPath) args.push(`--to-path=${opts.toPath}`)

    return new Promise<{ success: boolean; output: string }>((resolve) => {
      const lines: string[] = []
      const win = BrowserWindow.fromWebContents(event.sender)

      streamCli(
        args,
        (line) => {
          lines.push(line)
          win?.webContents.send('migrate:progress', line)
        },
        (code) => {
          resolve({ success: code === 0, output: lines.join('\n') })
        }
      )
    })
  })
}
