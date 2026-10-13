import * as path from 'path';
import { runTests } from '@vscode/test-electron';

/**
 * Downloads a throwaway copy of VS Code into .vscode-test/ and runs the
 * integration suite inside it, with the extension loaded from this folder.
 * The user's own VS Code install, settings and extensions are not touched.
 */
async function main(): Promise<void> {
  const root = path.resolve(__dirname, '../../');

  await runTests({
    extensionDevelopmentPath: root,
    extensionTestsPath: path.resolve(__dirname, './suite/index'),
    launchArgs: [
      path.join(root, 'test-fixtures', 'workspace'),
      '--disable-extensions',
      '--disable-workspace-trust',
      '--skip-welcome',
    ],
    extensionTestsEnv: {
      MOVEZ_TEST_PHP: process.env.MOVEZ_TEST_PHP ?? 'php',
    },
  });
}

main().catch((err) => {
  console.error('Integration tests failed:', err);
  process.exit(1);
});
