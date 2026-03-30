import * as vscode from 'vscode';

export class MigrationPanel {
  public static currentPanel: MigrationPanel | undefined;

  private static readonly viewType = 'movezMigration';

  private readonly _panel: vscode.WebviewPanel;
  private _disposables: vscode.Disposable[] = [];

  private constructor(
    panel: vscode.WebviewPanel,
    private readonly serverUrl: string,
  ) {
    this._panel = panel;
    this._panel.webview.html = this._getHtmlContent();

    this._panel.onDidDispose(() => this.dispose(), null, this._disposables);
  }

  public static createOrShow(extensionUri: vscode.Uri, serverUrl: string): void {
    const column = vscode.window.activeTextEditor
      ? vscode.window.activeTextEditor.viewColumn
      : undefined;

    if (MigrationPanel.currentPanel) {
      MigrationPanel.currentPanel._panel.reveal(column);
      return;
    }

    const panel = vscode.window.createWebviewPanel(
      MigrationPanel.viewType,
      'MoveZ Migration Wizard',
      column ?? vscode.ViewColumn.One,
      {
        enableScripts: true,
        retainContextWhenHidden: true,
      },
    );

    MigrationPanel.currentPanel = new MigrationPanel(panel, serverUrl);
  }

  public dispose(): void {
    MigrationPanel.currentPanel = undefined;

    this._panel.dispose();

    while (this._disposables.length) {
      const x = this._disposables.pop();
      x?.dispose();
    }
  }

  private _getHtmlContent(): string {
    const serverUrl = this.serverUrl;

    if (serverUrl) {
      // If a server URL is configured, show an iframe pointing to the dashboard
      return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MoveZ Migration Wizard</title>
  <style>
    body, html { margin: 0; padding: 0; width: 100%; height: 100vh; overflow: hidden; }
    iframe { width: 100%; height: 100%; border: none; }
  </style>
</head>
<body>
  <iframe src="${serverUrl}/migration/wizard" sandbox="allow-scripts allow-same-origin allow-forms"></iframe>
</body>
</html>`;
    }

    // Fallback: embedded wizard steps
    return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MoveZ Migration Wizard</title>
  <style>
    body { font-family: var(--vscode-font-family); color: var(--vscode-foreground); background: var(--vscode-editor-background); padding: 24px; }
    h1 { font-size: 1.4em; margin-bottom: 16px; }
    .step { display: none; }
    .step.active { display: block; }
    select, input { background: var(--vscode-input-background); color: var(--vscode-input-foreground); border: 1px solid var(--vscode-input-border); padding: 6px 10px; border-radius: 4px; width: 300px; margin-bottom: 12px; }
    button { background: var(--vscode-button-background); color: var(--vscode-button-foreground); border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-right: 8px; }
    button:hover { background: var(--vscode-button-hoverBackground); }
    .notice { padding: 12px; background: var(--vscode-editorInfo-background); border-radius: 4px; margin-bottom: 16px; }
  </style>
</head>
<body>
  <h1>⚡ MoveZ Migration Wizard</h1>
  <div class="notice">Configure a sync server URL in settings for the full web wizard experience.</div>

  <div id="step1" class="step active">
    <h2>Step 1: Source Tool</h2>
    <select id="fromTool">
      <option value="">Select source tool...</option>
      <option value="cursor">Cursor</option>
      <option value="windsurf">Windsurf</option>
      <option value="claude-code">Claude Code</option>
      <option value="codex">Codex</option>
      <option value="copilot-cli">Copilot CLI</option>
      <option value="cline">Cline</option>
      <option value="continue">Continue</option>
    </select>
    <br><button onclick="nextStep(2)">Next →</button>
  </div>

  <div id="step2" class="step">
    <h2>Step 2: Target Tool</h2>
    <select id="toTool">
      <option value="">Select target tool...</option>
      <option value="cursor">Cursor</option>
      <option value="windsurf">Windsurf</option>
      <option value="claude-code">Claude Code</option>
      <option value="codex">Codex</option>
      <option value="copilot-cli">Copilot CLI</option>
    </select>
    <br><button onclick="nextStep(1)">← Back</button>
    <button onclick="nextStep(3)">Next →</button>
  </div>

  <div id="step3" class="step">
    <h2>Step 3: Confirm</h2>
    <p>Transfer sessions from <strong id="confirmFrom"></strong> to <strong id="confirmTo"></strong>.</p>
    <button onclick="nextStep(2)">← Back</button>
    <button onclick="runTransfer()">✓ Transfer</button>
  </div>

  <div id="step4" class="step">
    <h2>✅ Done!</h2>
    <p id="resultMsg"></p>
    <button onclick="location.reload()">Start over</button>
  </div>

  <script>
    let currentStep = 1;
    function nextStep(n) {
      document.getElementById('step' + currentStep).classList.remove('active');
      currentStep = n;
      document.getElementById('step' + n).classList.add('active');
      if (n === 3) {
        document.getElementById('confirmFrom').textContent = document.getElementById('fromTool').value;
        document.getElementById('confirmTo').textContent = document.getElementById('toTool').value;
      }
    }
    function runTransfer() {
      const from = document.getElementById('fromTool').value;
      const to = document.getElementById('toTool').value;
      nextStep(4);
      document.getElementById('resultMsg').textContent =
        'Run: movez transfer --from=' + from + ' --to=' + to;
    }
  </script>
</body>
</html>`;
  }
}
