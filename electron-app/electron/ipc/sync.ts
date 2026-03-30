import { ipcMain, BrowserWindow } from 'electron'
import { streamCli } from '../cli'

export function registerSyncHandlers(): void {
  ipcMain.handle('sync:push', async (event, opts: {
    token: string
    server: string
    tool?: string
    project?: string
  }) => {
    const args = ['sync:push', `--token=${opts.token}`, `--server=${opts.server}`]
    if (opts.tool) args.push(`--tool=${opts.tool}`)
    if (opts.project) args.push(`--project=${opts.project}`)

    return new Promise<{ success: boolean; output: string }>((resolve) => {
      const lines: string[] = []
      const win = BrowserWindow.fromWebContents(event.sender)

      streamCli(
        args,
        (line) => {
          lines.push(line)
          win?.webContents.send('sync:log', line)
        },
        (code) => resolve({ success: code === 0, output: lines.join('\n') })
      )
    })
  })

  ipcMain.handle('sync:pull', async (event, opts: {
    token: string
    server: string
    tool: string
    project?: string
    fromPath?: string
    toPath?: string
  }) => {
    const args = ['sync:pull', `--token=${opts.token}`, `--server=${opts.server}`, `--tool=${opts.tool}`]
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
          win?.webContents.send('sync:log', line)
        },
        (code) => resolve({ success: code === 0, output: lines.join('\n') })
      )
    })
  })
}
