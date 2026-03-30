import * as vscode from 'vscode';
import { execFile } from 'child_process';
import { promisify } from 'util';
import * as path from 'path';
import { SessionTreeProvider } from './SessionTreeProvider';
import { MigrationPanel } from './MigrationPanel';

const execFileAsync = promisify(execFile);

function getCli(): string {
  const config = vscode.workspace.getConfiguration('movez');
  return config.get<string>('cliPath', 'movez');
}

function getServerUrl(): string {
  const config = vscode.workspace.getConfiguration('movez');
  return config.get<string>('serverUrl', '');
}

function getToken(): string {
  const config = vscode.workspace.getConfiguration('movez');
  return config.get<string>('token', '');
}

export function activate(context: vscode.ExtensionContext): void {
  const provider = new SessionTreeProvider(getCli());

  context.subscriptions.push(
    vscode.window.registerTreeDataProvider('movezSessions', provider),
  );

  // Refresh Sessions
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.refreshSessions', () => {
      provider.refresh();
    }),
  );

  // Export Sessions
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.exportSession', async () => {
      const workspaceFolder = vscode.workspace.workspaceFolders?.[0]?.uri.fsPath ?? '';
      const outputPath = path.join(workspaceFolder, `movez-export-${Date.now()}.json`);

      await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'MoveZ: Exporting sessions...' },
        async () => {
          try {
            await execFileAsync(getCli(), ['export', '--tool=auto', `--output=${outputPath}`], {
              timeout: 30_000,
            });
            vscode.window.showInformationMessage(`Sessions exported to: ${outputPath}`);
          } catch (err) {
            vscode.window.showErrorMessage(`Export failed: ${String(err)}`);
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
        filters: { 'Bundle files': ['json', 'enc.json', 'cbz'] },
      });

      if (!uri || uri.length === 0) {
        return;
      }

      const tools = ['cursor', 'windsurf', 'claude-code', 'codex', 'copilot-cli'];
      const targetTool = await vscode.window.showQuickPick(tools, {
        placeHolder: 'Select target AI tool',
      });

      if (!targetTool) {
        return;
      }

      const workspaceFolder = vscode.workspace.workspaceFolders?.[0]?.uri.fsPath ?? '';

      await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'MoveZ: Importing sessions...' },
        async () => {
          try {
            await execFileAsync(
              getCli(),
              ['import', `--input=${uri[0].fsPath}`, `--tool=${targetTool}`, `--project=${workspaceFolder}`],
              { timeout: 30_000 },
            );
            vscode.window.showInformationMessage(`Sessions imported into ${targetTool}`);
            provider.refresh();
          } catch (err) {
            vscode.window.showErrorMessage(`Import failed: ${String(err)}`);
          }
        },
      );
    }),
  );

  // Migration Wizard
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.openMigrationWizard', () => {
      MigrationPanel.createOrShow(context.extensionUri, getServerUrl());
    }),
  );

  // Sync Push
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.syncPush', async () => {
      const serverUrl = getServerUrl();
      const token     = getToken();

      if (!serverUrl) {
        vscode.window.showErrorMessage('Set movez.serverUrl in settings first.');
        return;
      }

      await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'MoveZ: Syncing push...' },
        async () => {
          try {
            await execFileAsync(
              getCli(),
              ['sync:push', `--server=${serverUrl}`, `--token=${token}`, '--tool=auto'],
              { timeout: 60_000 },
            );
            vscode.window.showInformationMessage('Sessions pushed to sync server');
          } catch (err) {
            vscode.window.showErrorMessage(`Sync push failed: ${String(err)}`);
          }
        },
      );
    }),
  );

  // Sync Pull
  context.subscriptions.push(
    vscode.commands.registerCommand('movez.syncPull', async () => {
      const serverUrl = getServerUrl();
      const token     = getToken();

      if (!serverUrl) {
        vscode.window.showErrorMessage('Set movez.serverUrl in settings first.');
        return;
      }

      const tools = ['cursor', 'windsurf', 'claude-code', 'codex', 'copilot-cli'];
      const targetTool = await vscode.window.showQuickPick(tools, {
        placeHolder: 'Import pulled sessions into which tool?',
      });

      if (!targetTool) {
        return;
      }

      const workspaceFolder = vscode.workspace.workspaceFolders?.[0]?.uri.fsPath ?? '';

      await vscode.window.withProgress(
        { location: vscode.ProgressLocation.Notification, title: 'MoveZ: Syncing pull...' },
        async () => {
          try {
            await execFileAsync(
              getCli(),
              [
                'sync:pull',
                `--server=${serverUrl}`,
                `--token=${token}`,
                `--tool=${targetTool}`,
                `--project=${workspaceFolder}`,
              ],
              { timeout: 60_000 },
            );
            vscode.window.showInformationMessage(`Sessions pulled into ${targetTool}`);
            provider.refresh();
          } catch (err) {
            vscode.window.showErrorMessage(`Sync pull failed: ${String(err)}`);
          }
        },
      );
    }),
  );
}

export function deactivate(): void {
  // Nothing to clean up
}
