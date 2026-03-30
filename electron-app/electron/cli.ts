import { spawn } from 'child_process'
import { join } from 'path'
import { existsSync } from 'fs'
import { app } from 'electron'
import Store from 'electron-store'

const store = new Store<{ cliPath: string; phpPath: string }>()

export interface CliResult {
  stdout: string
  stderr: string
  code: number
}

/** Path to bundled PHP exe inside the installed app resources */
function bundledPhpPath(): string {
  const base = app.isPackaged
    ? process.resourcesPath
    : join(__dirname, '../../resources')
  return join(base, 'php', 'php.exe')
}

/** Path to bundled PHAR inside the installed app resources */
function bundledPharPath(): string {
  const base = app.isPackaged
    ? process.resourcesPath
    : join(__dirname, '../../resources')
  return join(base, 'movez.phar')
}

export function getCliPath(): string {
  const stored = store.get('cliPath') as string | undefined
  if (stored && stored !== 'movez') return stored
  // Auto-use bundled PHAR if present
  const phar = bundledPharPath()
  if (existsSync(phar)) return phar
  return 'movez'
}

function buildSpawnArgs(cliPath: string, args: string[]): { cmd: string; cmdArgs: string[] } {
  // .phar files must be run via php.exe
  if (cliPath.toLowerCase().endsWith('.phar')) {
    const storedPhp = store.get('phpPath') as string | undefined
    // Prefer stored, else bundled, else system php
    const phpBin = (storedPhp && storedPhp !== 'php')
      ? storedPhp
      : existsSync(bundledPhpPath()) ? bundledPhpPath() : 'php'
    return { cmd: phpBin, cmdArgs: ['-d', 'memory_limit=512M', cliPath, ...args] }
  }
  return { cmd: cliPath, cmdArgs: args }
}

export function runCli(args: string[]): Promise<CliResult> {
  return new Promise((resolve) => {
    const { cmd, cmdArgs } = buildSpawnArgs(getCliPath(), args)
    const chunks: Buffer[] = []
    const errChunks: Buffer[] = []

    const proc = spawn(cmd, cmdArgs, { windowsHide: true })

    proc.stdout.on('data', (d) => chunks.push(Buffer.from(d)))
    proc.stderr.on('data', (d) => errChunks.push(Buffer.from(d)))

    proc.on('close', (code) => {
      resolve({
        stdout: Buffer.concat(chunks).toString('utf8').trim(),
        stderr: Buffer.concat(errChunks).toString('utf8').trim(),
        code:   code ?? 1
      })
    })

    proc.on('error', (err) => {
      resolve({ stdout: '', stderr: err.message, code: 1 })
    })
  })
}

export function streamCli(
  args: string[],
  onData: (line: string) => void,
  onDone: (code: number) => void
): void {
  const { cmd, cmdArgs } = buildSpawnArgs(getCliPath(), args)
  const proc = spawn(cmd, cmdArgs, { windowsHide: true })

  proc.stdout.on('data', (d: Buffer) => {
    d.toString('utf8').split('\n').filter(Boolean).forEach(onData)
  })

  proc.stderr.on('data', (d: Buffer) => {
    d.toString('utf8').split('\n').filter(Boolean).forEach(onData)
  })

  proc.on('close', onDone)
  proc.on('error', () => onDone(1))
}
