import * as vscode from 'vscode';
import { runCli, workspaceFolder } from './cli';
import type { SessionDTO } from './types';

export class SessionTreeItem extends vscode.TreeItem {
  constructor(public readonly session: SessionDTO) {
    super(session.title || session.id, vscode.TreeItemCollapsibleState.None);

    const turns = session.turn_count ?? session.turns?.length ?? 0;

    this.description = `${session.source_tool} · ${turns} turn(s)`;
    this.tooltip = new vscode.MarkdownString(
      `**${session.title}**\n\n` +
      `Tool: ${session.source_tool}\n\n` +
      (session.project ? `Project: ${session.project}\n\n` : '') +
      `Last active: ${new Date(session.last_active_at).toLocaleString()}\n\n` +
      `Turns: ${turns}`,
    );
    this.contextValue = 'movezSession';
    this.iconPath = new vscode.ThemeIcon('comment-discussion');
  }
}

class MessageItem extends vscode.TreeItem {
  constructor(message: string, icon: string) {
    super(message, vscode.TreeItemCollapsibleState.None);
    this.iconPath = new vscode.ThemeIcon(icon);
  }
}

export class SessionTreeProvider implements vscode.TreeDataProvider<vscode.TreeItem> {
  private readonly _onDidChangeTreeData = new vscode.EventEmitter<vscode.TreeItem | undefined | void>();
  readonly onDidChangeTreeData = this._onDidChangeTreeData.event;

  refresh(): void {
    this._onDidChangeTreeData.fire();
  }

  getTreeItem(element: vscode.TreeItem): vscode.TreeItem {
    return element;
  }

  async getChildren(element?: vscode.TreeItem): Promise<vscode.TreeItem[]> {
    if (element) {
      return [];
    }

    // Show sessions for the open project; all sessions when no folder is open
    const project = workspaceFolder();
    const args    = ['list-sessions', '--json', ...(project ? [`--project=${project}`] : [])];

    try {
      const { stdout } = await runCli(args, { timeoutMs: 30_000 });
      const data       = JSON.parse(stdout.trim() || '[]');
      const sessions   = Array.isArray(data) ? (data as SessionDTO[]) : [];

      if (sessions.length === 0) {
        return [new MessageItem(project ? 'No sessions for this project' : 'No sessions found', 'info')];
      }

      return sessions
        .sort((a, b) => b.last_active_at.localeCompare(a.last_active_at))
        .map((s) => new SessionTreeItem(s));
    } catch (err) {
      const message = err instanceof Error ? err.message : String(err);
      return [new MessageItem(`MoveZ CLI error: ${message.split('\n')[0]}`, 'warning')];
    }
  }
}
