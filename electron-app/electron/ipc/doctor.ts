import { ipcMain, BrowserWindow } from 'electron'
import { streamCli } from '../cli'

export function registerDoctorHandlers(): void {
  ipcMain.handle('doctor:run', async (event) => {
    return new Promise<{ success: boolean; lines: string[] }>((resolve) => {
      const lines: string[] = []
      const win = BrowserWindow.fromWebContents(event.sender)

      streamCli(
        ['doctor'],
        (line) => {
          lines.push(line)
          win?.webContents.send('doctor:line', line)
        },
        (code) => resolve({ success: code === 0, lines })
      )
    })
  })
}
