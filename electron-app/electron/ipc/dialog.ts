import { ipcMain, dialog, BrowserWindow } from 'electron'

export function registerDialogHandlers(): void {
  ipcMain.handle('dialog:openFile', async (event, opts: {
    title?: string
    filters?: { name: string; extensions: string[] }[]
  }) => {
    const win = BrowserWindow.fromWebContents(event.sender)
    const result = await dialog.showOpenDialog(win!, {
      title:       opts.title ?? 'Open File',
      filters:     opts.filters ?? [{ name: 'All Files', extensions: ['*'] }],
      properties:  ['openFile']
    })
    return result.canceled ? null : result.filePaths[0]
  })

  ipcMain.handle('dialog:openDirectory', async (event, opts: { title?: string }) => {
    const win = BrowserWindow.fromWebContents(event.sender)
    const result = await dialog.showOpenDialog(win!, {
      title:      opts.title ?? 'Select Directory',
      properties: ['openDirectory']
    })
    return result.canceled ? null : result.filePaths[0]
  })

  ipcMain.handle('dialog:saveFile', async (event, opts: {
    title?: string
    defaultPath?: string
    filters?: { name: string; extensions: string[] }[]
  }) => {
    const win = BrowserWindow.fromWebContents(event.sender)
    const result = await dialog.showSaveDialog(win!, {
      title:       opts.title ?? 'Save File',
      defaultPath: opts.defaultPath,
      filters:     opts.filters ?? [{ name: 'MoveZ Bundle', extensions: ['cbz'] }]
    })
    return result.canceled ? null : result.filePath
  })
}
