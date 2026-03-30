import * as vscode from 'vscode';
import { execFile } from 'child_process';
import { promisify } from 'util';
import type { SessionDTO } from './types';

const execFileAsync = promisify(execFile);

export class SessionTreeItem extends vscode.TreeItem {
  constructor(
    public readonly session: SessionDTO,
    collapsibleState: vscode.TreeItemCollapsibleState,
  ) {
    super(session.title || session.id, collapsibleState);

    this.description = `${session.source_tool} · ${session.turns.length} turn(s)`;
    this.tooltip = new vscode.MarkdownString(
      `**${session.title}**\n\n` +
      `Tool: ${session.source_tool}\n\n` +
      `Last active: ${new Date(session.last_active_at).toLocaleString()}\n\n` +
      `Turns: ${session.turns.length}`,
    );
    this.contextValue = 'movezSession';
    this.iconPath = new vscode.ThemeIcon('comment-discussion');
  }
}

export class SessionTreeProvider implements vscode.TreeDataProvider<SessionTreeItem> {
  private _onDidChangeTreeData: vscode.EventEmitter<SessionTreeItem | undefined | null | void> =
    new vscode.EventEmitter<SessionTreeItem | undefined | null | void>();
  readonly onDidChangeTreeData: vscode.Event<SessionTreeItem | undefined | null | void> =
    this._onDidChangeTreeData.event;

  private sessions: SessionDTO[] = [];

  constructor(private readonly cliPath: string) {}

  refresh(): void {
    this._onDidChangeTreeData.fire();
  }

  getTreeItem(element: SessionTreeItem): vscode.TreeItem {
    return element;
  }

  async getChildren(_element?: SessionTreeItem): Promise<SessionTreeItem[]> {
    if (_element) {
      return [];
    }

    await this.loadSessions();

    return this.sessions.map(
      (s) => new SessionTreeItem(s, vscode.TreeItemCollapsibleState.None),
    );
  }

  private async loadSessions(): Promise<void> {
    try {
      const { stdout } = await execFileAsync(this.cliPath, ['list-sessions', '--json'], {
        timeout: 10_000,
      });

      const data = JSON.parse(stdout.trim());
      this.sessions = Array.isArray(data) ? data : [];
    } catch {
      this.sessions = [];
    }
  }
}
