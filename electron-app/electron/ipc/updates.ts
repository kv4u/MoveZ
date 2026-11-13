import { app, ipcMain, net, shell } from 'electron'

const REPO         = 'kv4u/MoveZ'
const RELEASES_URL = `https://github.com/${REPO}/releases/latest`

export interface UpdateInfo {
  current:   string
  latest:    string | null
  available: boolean
}

// True when version a (e.g. "1.0.9") is newer than b
export function isNewer(a: string, b: string): boolean {
  const pa = a.split('.').map((n) => parseInt(n, 10) || 0)
  const pb = b.split('.').map((n) => parseInt(n, 10) || 0)
  for (let i = 0; i < Math.max(pa.length, pb.length); i++) {
    const d = (pa[i] ?? 0) - (pb[i] ?? 0)
    if (d !== 0) return d > 0
  }
  return false
}

export function registerUpdateHandlers(): void {
  // Asks GitHub for the latest release. Only the public releases API is
  // contacted, and only when the "Check for updates" setting is on.
  ipcMain.handle('updates:check', async (): Promise<UpdateInfo> => {
    const current = app.getVersion()
    try {
      const res = await net.fetch(`https://api.github.com/repos/${REPO}/releases/latest`, {
        headers: { Accept: 'application/vnd.github+json', 'User-Agent': 'MoveZ-Desktop' }
      })
      if (!res.ok) return { current, latest: null, available: false }

      const release = (await res.json()) as { tag_name?: string }
      const latest  = (release.tag_name ?? '').replace(/^v/, '')

      // Development builds carry a placeholder version, so never nag there
      return { current, latest: latest || null, available: app.isPackaged && latest !== '' && isNewer(latest, current) }
    } catch {
      return { current, latest: null, available: false }
    }
  })

  ipcMain.handle('updates:open', () => shell.openExternal(RELEASES_URL))
}
