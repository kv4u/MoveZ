import * as vscode from 'vscode';
import * as path from 'path';
import { SessionTreeProvider } from './SessionTreeProvider';
import { MigrationPanel } from './MigrationPanel';
import { READABLE_TOOLS, WRITABLE_TOOLS, getToken, outputChannel, runCli, setToken, workspaceFolder } from './cli';

function getServerUrl(): string {
  return vscode.workspace.getConfiguration('movez').get<string>('serverUrl', '').replace(/\/+$/, '');
}

function errorMessage(err: unknown): string {
  return err instanceof Error ? err.message : String(err);
}

function showError(prefix: string, err: unknown): void {
  void vscode.window
    .showErrorMessage(`${prefix}: ${errorMessage(err)}`, 'Show Log')
    .then((choice) => choice && outputChannel().show());
}

export function activate(context: vscode.ExtensionContext): void {
  const provider = new SessionTreeProvider();

  context.subscriptions.push(
    outputChannel(),
    vscode.window.registerTreeDataProvider('movezSessions', provider),
    vscode.workspace.onDidChangeConfiguration((e) => {
      if (e.affectsConfiguration('movez.cliPath') || e.affectsConfiguration('movez.phpPath')) {
        provider.refresh();
      }
    }),
  );

  // Refresh Sessions
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.refreshSessions', () => provider.refresh()),
  );

  // Export Sessions
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.exportSession', async () => {
      const tool = await vscode.window.showQuickPick(['auto', ...READABLE_TOOLS], {
        placeHolder: 'Export sessions from which tool?',
      });
      if (!tool) {
        return;
      }

      const project = workspaceFolder();
      const target  = await vscode.window.showSaveDialog({
        defaultUri: vscode.Uri.file(path.join(project ?? '', `movez-export-${Date.now()}.cbz`)),
        filters: { 'MoveZ bundle': ['cbz'] },
      });
      if (!target) {
        return;
      }

      const encrypt = await vscode.window.showQuickPick(['No', 'Yes'], {
        placeHolder: 'Encrypt the bundle with your MoveZ key (~/.movez/key)?',
      });
      if (!encrypt) {
        return;
      }

      await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'MoveZ: Exporting sessions...' },
        async () => {
          try {
            const args = ['export', `--tool=${tool}`, `--output=${target.fsPath}`];
            if (project) {
              args.push(`--project=${project}`);
            }
            if (encrypt === 'Yes') {
              args.push('--encrypt');
            }

            const { stdout } = await runCli(args, { timeoutMs: 300_000 });
            vscode.window.showInformationMessage(stdout.trim().split('\n')[0] || `Exported to ${target.fsPath}`);
          } catch (err) {
            showError('Export failed', err);
          }
        },
      );
    }),
  );

  // Import Sessions
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.importSession', async () => {
      const uri = await vscode.window.showOpenDialog({
        canSelectMany: false,
        filters: { 'MoveZ bundles': ['cbz', 'json'] },
      });
      if (!uri || uri.length === 0) {
        return;
      }

      const targetTool = await vscode.window.showQuickPick(WRITABLE_TOOLS, {
        placeHolder: 'Import into which AI tool?',
      });
      if (!targetTool) {
        return;
      }

      const project = workspaceFolder();

      await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'MoveZ: Importing sessions...' },
        async () => {
          try {
            // Encrypted .cbz bundles and *.enc.json files are detected by the CLI
            const args = ['import', `--input=${uri[0].fsPath}`, `--tool=${targetTool}`];
            if (project) {
              args.push(`--project=${project}`);
            }

            await runCli(args, { timeoutMs: 300_000 });
            vscode.window.showInformationMessage(`Sessions imported into ${targetTool}`);
            provider.refresh();
          } catch (err) {
            showError('Import failed', err);
          }
        },
      );
    }),
  );

  // Migration Wizard
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.openMigrationWizard', () => {
      MigrationPanel.createOrShow(() => provider.refresh());
    }),
  );

  // Store the sync token in VS Code's SecretStorage (not settings.json)
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.setToken', async () => {
      const token = await vscode.window.showInputBox({
        prompt: 'MoveZ sync API token (stored in your OS keychain via VS Code SecretStorage)',
        password: true,
        ignoreFocusOut: true,
      });
      if (token === undefined) {
        return;
      }

      await setToken(context.secrets, token.trim());
      vscode.window.showInformationMessage(token.trim() ? 'MoveZ sync token saved.' : 'MoveZ sync token cleared.');
    }),
  );

  async function syncCredentials(): Promise<{ server: string; token: string } | undefined> {
    const server = getServerUrl();
    if (!server) {
      vscode.window.showErrorMessage('Set "movez.serverUrl" in settings first.');
      return undefined;
    }

    const token = await getToken(context.secrets);
    if (!token) {
      const choice = await vscode.window.showErrorMessage('No sync token configured.', 'Set Token');
      if (choice) {
        await vscode.commands.executeCommand('movez.setToken');
      }
      return undefined;
    }

    return { server, token };
  }

  // Sync Push
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.syncPush', async () => {
      const creds = await syncCredentials();
      if (!creds) {
        return;
      }

      const tool = await vscode.window.showQuickPick(['auto', ...READABLE_TOOLS], {
        placeHolder: 'Push sessions from which tool?',
      });
      if (!tool) {
        return;
      }

      const project = workspaceFolder();

      await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'MoveZ: Pushing sessions...' },
        async () => {
          try {
            const args = ['sync:push', `--server=${creds.server}`, `--tool=${tool}`];
            if (project) {
              args.push(`--project=${project}`);
            }

            // Token goes through the environment so it never appears in process lists
            const { stdout } = await runCli(args, { timeoutMs: 300_000, env: { MOVEZ_TOKEN: creds.token } });
            vscode.window.showInformationMessage(stdout.trim().split('\n')[0] || 'Sessions pushed');
          } catch (err) {
            showError('Sync push failed', err);
          }
        },
      );
    }),
  );

  // Sync Pull
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.syncPull', async () => {
      const creds = await syncCredentials();
      if (!creds) {
        return;
      }

      const targetTool = await vscode.window.showQuickPick(WRITABLE_TOOLS, {
        placeHolder: 'Import pulled sessions into which tool?',
      });
      if (!targetTool) {
        return;
      }

      const project = workspaceFolder();

      await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'MoveZ: Pulling sessions...' },
        async () => {
          try {
            const args = ['sync:pull', `--server=${creds.server}`, `--tool=${targetTool}`];
            if (project) {
              args.push(`--project=${project}`);
            }

            const { stdout } = await runCli(args, { timeoutMs: 300_000, env: { MOVEZ_TOKEN: creds.token } });
            vscode.window.showInformationMessage(stdout.trim().split('\n')[0] || `Sessions pulled into ${targetTool}`);
            provider.refresh();
          } catch (err) {
            showError('Sync pull failed', err);
          }
        },
      );
    }),
  );
}

export function deactivate(): void {
  // Nothing to clean up
}
