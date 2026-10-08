import * as vscode from 'vscode';
import { randomBytes } from 'crypto';
import { READABLE_TOOLS, WRITABLE_TOOLS, runCli, workspaceFolder } from './cli';

interface TransferRequest {
  type: 'transfer';
  from: string;
  to: string;
  project: string;
  fromPath: string;
  toPath: string;
}

/**
 * In-editor migration wizard. Runs `movez transfer` locally — session files
 * live on this machine, so no server round-trip is involved.
 */
export class MigrationPanel {
  public static currentPanel: MigrationPanel | undefined;

  private static readonly viewType = 'movezMigration';

  private readonly _panel: vscode.WebviewPanel;
  private _disposables: vscode.Disposable[] = [];

  private constructor(panel: vscode.WebviewPanel, private readonly onTransferred: () => void) {
    this._panel = panel;
    this._panel.webview.html = this._getHtmlContent();

    this._panel.onDidDispose(() => this.dispose(), null, this._disposables);
    this._panel.webview.onDidReceiveMessage(
      (msg: TransferRequest) => this.handleMessage(msg),
      null,
      this._disposables,
    );
  }

  public static createOrShow(onTransferred: () => void): void {
    const column = vscode.window.activeTextEditor?.viewColumn;

    if (MigrationPanel.currentPanel) {
      MigrationPanel.currentPanel._panel.reveal(column);
      return;
    }

    const panel = vscode.window.createWebviewPanel(
      MigrationPanel.viewType,
      'MoveZ Migration Wizard',
      column ?? vscode.ViewColumn.One,
      { enableScripts: true, retainContextWhenHidden: true, localResourceRoots: [] },
    );

    MigrationPanel.currentPanel = new MigrationPanel(panel, onTransferred);
  }

  public dispose(): void {
    MigrationPanel.currentPanel = undefined;

    this._panel.dispose();

    while (this._disposables.length) {
      this._disposables.pop()?.dispose();
    }
  }

  private async handleMessage(msg: TransferRequest): Promise<void> {
    if (msg?.type !== 'transfer' || !READABLE_TOOLS.includes(msg.from) || !WRITABLE_TOOLS.includes(msg.to)) {
      return;
    }

    const args = ['transfer', `--from=${msg.from}`, `--to=${msg.to}`];
    if (msg.project) {
      args.push(`--project=${msg.project}`);
    }
    if (msg.fromPath && msg.toPath) {
      args.push(`--from-path=${msg.fromPath}`, `--to-path=${msg.toPath}`);
    }

    try {
      const { stdout } = await runCli(args, { timeoutMs: 300_000 });
      await this._panel.webview.postMessage({ type: 'done', ok: true, message: stdout.trim() || 'Transfer complete.' });
      this.onTransferred();
    } catch (err) {
      await this._panel.webview.postMessage({
        type: 'done',
        ok: false,
        message: err instanceof Error ? err.message : String(err),
      });
    }
  }

  private _getHtmlContent(): string {
    const nonce   = randomBytes(16).toString('base64');
    const csp     = `default-src 'none'; style-src 'nonce-${nonce}'; script-src 'nonce-${nonce}';`;
    const options = (tools: string[]) => tools.map((t) => `<option value="${t}">${t}</option>`).join('');
    const project = escapeHtml(workspaceFolder() ?? '');

    return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Content-Security-Policy" content="${csp}">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MoveZ Migration Wizard</title>
  <style nonce="${nonce}">
    body { font-family: var(--vscode-font-family); color: var(--vscode-foreground); background: var(--vscode-editor-background); padding: 24px; max-width: 560px; }
    h1 { font-size: 1.4em; margin-bottom: 16px; }
    label { display: block; font-size: 0.9em; margin: 12px 0 4px; }
    select, input { background: var(--vscode-input-background); color: var(--vscode-input-foreground); border: 1px solid var(--vscode-input-border, transparent); padding: 6px 10px; border-radius: 4px; width: 100%; box-sizing: border-box; }
    .row { display: flex; gap: 12px; } .row > div { flex: 1; }
    button { margin-top: 20px; background: var(--vscode-button-background); color: var(--vscode-button-foreground); border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
    button:disabled { opacity: 0.5; cursor: default; }
    .hint { opacity: 0.75; font-size: 0.85em; }
    #result { margin-top: 16px; padding: 10px 12px; border-radius: 4px; white-space: pre-wrap; display: none; }
    #result.ok { display: block; background: var(--vscode-editorInfo-background, rgba(0,128,0,.15)); }
    #result.err { display: block; background: var(--vscode-inputValidation-errorBackground); }
  </style>
</head>
<body>
  <h1>⚡ MoveZ Migration Wizard</h1>
  <p class="hint">Copies sessions from one AI tool into another on this machine.</p>

  <label for="fromTool">Source tool</label>
  <select id="fromTool"><option value="">Select source tool…</option>${options(READABLE_TOOLS)}</select>

  <label for="toTool">Target tool</label>
  <select id="toTool"><option value="">Select target tool…</option>${options(WRITABLE_TOOLS)}</select>

  <label for="project">Project (sessions are filtered by this folder)</label>
  <input id="project" value="${project}" placeholder="Leave empty to transfer all projects">

  <div class="row">
    <div><label for="fromPath">Remap from path (optional)</label><input id="fromPath" placeholder="/old/machine/projects"></div>
    <div><label for="toPath">Remap to path (optional)</label><input id="toPath" placeholder="/new/machine/projects"></div>
  </div>

  <button id="run" disabled>Transfer sessions</button>
  <div id="result"></div>

  <script nonce="${nonce}">
    const vscode = acquireVsCodeApi();
    const $ = (id) => document.getElementById(id);
    const run = $('run');

    function validate() {
      run.disabled = !$('fromTool').value || !$('toTool').value || $('fromTool').value === $('toTool').value;
    }
    $('fromTool').addEventListener('change', validate);
    $('toTool').addEventListener('change', validate);

    run.addEventListener('click', () => {
      run.disabled = true;
      run.textContent = 'Transferring…';
      $('result').className = '';
      vscode.postMessage({
        type: 'transfer',
        from: $('fromTool').value,
        to: $('toTool').value,
        project: $('project').value.trim(),
        fromPath: $('fromPath').value.trim(),
        toPath: $('toPath').value.trim(),
      });
    });

    window.addEventListener('message', (event) => {
      const msg = event.data;
      if (msg.type !== 'done') return;
      run.textContent = 'Transfer sessions';
      validate();
      $('result').textContent = (msg.ok ? '✅ ' : '❌ ') + msg.message;
      $('result').className = msg.ok ? 'ok' : 'err';
    });
  </script>
</body>
</html>`;
  }
}

function escapeHtml(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
