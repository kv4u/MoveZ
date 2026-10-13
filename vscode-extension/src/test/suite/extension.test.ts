import * as assert from 'assert';
import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import * as vscode from 'vscode';
import type { MovezApi } from '../../extension';

const FAKE_CLI = path.resolve(__dirname, '../../../test-fixtures/fake-movez.phar');
const COMMANDS = [
  'movez.exportSession',
  'movez.importSession',
  'movez.openMigrationWizard',
  'movez.syncPush',
  'movez.syncPull',
  'movez.setToken',
  'movez.refreshSessions',
];

async function activate(): Promise<MovezApi> {
  const ext = vscode.extensions.getExtension<MovezApi>('movez.movez');
  assert.ok(ext, 'extension movez.movez is installed');
  return ext.activate();
}

async function configure(cliPath: string): Promise<void> {
  const config = vscode.workspace.getConfiguration('movez');
  await config.update('cliPath', cliPath, vscode.ConfigurationTarget.Global);
  await config.update('phpPath', process.env.MOVEZ_TEST_PHP ?? 'php', vscode.ConfigurationTarget.Global);
}

suite('MoveZ extension', () => {
  let logFile: string;

  suiteSetup(() => {
    logFile = path.join(fs.mkdtempSync(path.join(os.tmpdir(), 'movez-ext-')), 'calls.jsonl');
    // Inherited by the CLI processes the extension spawns
    process.env.MOVEZ_FAKE_LOG = logFile;
  });

  test('activates and registers every command', async () => {
    const api = await activate();
    assert.ok(api.sessionTree, 'activate() returns the session tree');

    const registered = await vscode.commands.getCommands(true);
    for (const command of COMMANDS) {
      assert.ok(registered.includes(command), `${command} is registered`);
    }
  });

  test('Sessions view lists sessions from the CLI', async () => {
    const api = await activate();
    await configure(FAKE_CLI);

    const items = await api.sessionTree.getChildren();
    const labels = items.map((i) => i.label);

    assert.deepStrictEqual(labels, ['Refactor <info>auth</info>', 'Add a health check'], 'newest first, text verbatim');
    assert.strictEqual(items[0].description, 'cursor · 12 turn(s)');
    assert.strictEqual(items[1].description, 'claude-code · 4 turn(s)');
  });

  test('CLI is called through PHP with the workspace project and --no-ansi', async () => {
    const calls = fs.readFileSync(logFile, 'utf8').trim().split('\n').map((l) => JSON.parse(l).argv as string[]);
    const list = calls.find((argv) => argv[0] === 'list-sessions');
    assert.ok(list, 'list-sessions was invoked');

    const workspace = vscode.workspace.workspaceFolders?.[0]?.uri.fsPath;
    assert.ok(workspace, 'a workspace folder is open');
    assert.ok(list.includes('--json'));
    assert.ok(list.includes(`--project=${workspace}`), `scoped to the open folder: ${list.join(' ')}`);
    assert.ok(list.includes('--no-ansi'), 'colour codes disabled');
  });

  test('a missing CLI shows a clear message instead of an empty view', async () => {
    const api = await activate();
    await configure(path.join(os.tmpdir(), 'does-not-exist', 'movez.phar'));

    const items = await api.sessionTree.getChildren();
    assert.strictEqual(items.length, 1);
    assert.match(String(items[0].label), /^MoveZ CLI error:/);
  });

  suiteTeardown(async () => {
    const config = vscode.workspace.getConfiguration('movez');
    await config.update('cliPath', undefined, vscode.ConfigurationTarget.Global);
    await config.update('phpPath', undefined, vscode.ConfigurationTarget.Global);
  });
});
