import * as vscode from 'vscode';
import { execFile } from 'child_process';

export interface CliResult {
  stdout: string;
  stderr: string;
}

export interface CliOptions {
  timeoutMs?: number;
  /** Extra environment variables, e.g. MOVEZ_TOKEN (never pass secrets as arguments). */
  env?: Record<string, string>;
}

let output: vscode.OutputChannel | undefined;

export function outputChannel(): vscode.OutputChannel {
  output ??= vscode.window.createOutputChannel('MoveZ');
  return output;
}

/** First workspace folder, or undefined when no folder is open. */
export function workspaceFolder(): string | undefined {
  return vscode.workspace.workspaceFolders?.[0]?.uri.fsPath;
}

/**
 * Resolve how to launch the CLI. Settings are read on every call so changes
 * apply without reloading the window. A .phar path is run through PHP, which
 * is required on Windows where .phar files are not directly executable.
 */
function resolveCommand(args: string[]): { cmd: string; cmdArgs: string[] } {
  const config  = vscode.workspace.getConfiguration('movez');
  const cliPath = config.get<string>('cliPath', 'movez') || 'movez';
  const phpPath = config.get<string>('phpPath', 'php') || 'php';

  if (cliPath.toLowerCase().endsWith('.phar')) {
    return { cmd: phpPath, cmdArgs: ['-d', 'memory_limit=512M', cliPath, ...args] };
  }

  return { cmd: cliPath, cmdArgs: args };
}

/**
 * Run the movez CLI. Rejects with the CLI's own error output so callers can
 * show a meaningful message; everything is also logged to the MoveZ output channel.
 */
export function runCli(args: string[], options: CliOptions = {}): Promise<CliResult> {
  const { cmd, cmdArgs } = resolveCommand(args);
  const log = outputChannel();
  log.appendLine(`> ${cmd} ${cmdArgs.join(' ')}`);

  return new Promise((resolve, reject) => {
    execFile(
      cmd,
      cmdArgs,
      {
        cwd: workspaceFolder(),
        timeout: options.timeoutMs ?? 60_000,
        maxBuffer: 64 * 1024 * 1024,
        windowsHide: true,
        env: { ...process.env, ...options.env },
      },
      (error, stdout, stderr) => {
        if (stderr.trim()) {
          log.appendLine(stderr.trim());
        }

        if (error) {
          const detail = (stderr || stdout).trim() || error.message;
          log.appendLine(`✘ ${detail}`);
          reject(new Error(
            (error as NodeJS.ErrnoException).code === 'ENOENT'
              ? `Cannot find the MoveZ CLI at "${cmd}". Set "movez.cliPath" (and "movez.phpPath" for a .phar).`
              : detail,
          ));
          return;
        }

        if (stdout.trim()) {
          log.appendLine(stdout.trim());
        }
        resolve({ stdout, stderr });
      },
    );
  });
}

const TOKEN_KEY = 'movez.syncToken';

/** Sync token from SecretStorage, falling back to the deprecated plain-text setting. */
export async function getToken(secrets: vscode.SecretStorage): Promise<string> {
  const stored = await secrets.get(TOKEN_KEY);
  if (stored) {
    return stored;
  }

  return vscode.workspace.getConfiguration('movez').get<string>('token', '');
}

export async function setToken(secrets: vscode.SecretStorage, token: string): Promise<void> {
  if (token) {
    await secrets.store(TOKEN_KEY, token);
  } else {
    await secrets.delete(TOKEN_KEY);
  }
}

/** Tools the CLI can write to (Windsurf is not supported yet). */
export const WRITABLE_TOOLS = ['cursor', 'claude-code', 'codex', 'copilot-cli'];

/** Tools the CLI can read from. */
export const READABLE_TOOLS = ['cursor', 'claude-code', 'codex', 'copilot-cli', 'cline', 'continue'];
