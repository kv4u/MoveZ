// Fills download links and sizes from the latest GitHub release, and points the
// main button at the right installer for the visitor's OS. Every link already
// works without JavaScript (it falls back to the releases page).
(() => {
  const REPO = 'kv4u/MoveZ';

  const MATCHERS = {
    win: (n) => /^MoveZ-Setup-.*\.exe$/.test(n),
    'mac-arm64': (n) => /-mac-arm64\.dmg$/.test(n),
    'mac-x64': (n) => /-mac-x64\.dmg$/.test(n),
    linux: (n) => /\.AppImage$/.test(n),
    phar: (n) => n === 'movez.phar',
    vsix: (n) => n === 'movez-vscode.vsix',
  };

  const LABELS = {
    win: 'Download for Windows',
    'mac-arm64': 'Download for macOS',
    'mac-x64': 'Download for macOS (Intel)',
    linux: 'Download for Linux',
  };

  function detectPlatform() {
    const ua = navigator.userAgent;
    const platform = (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || '';
    if (/Win/i.test(platform) || /Windows/i.test(ua)) return 'win';
    if (/Mac/i.test(platform) || /Mac OS X/i.test(ua)) {
      // Browsers don't reliably expose Apple Silicon vs Intel; Apple Silicon is the default today
      return 'mac-arm64';
    }
    if (/Linux/i.test(platform) && !/Android/i.test(ua)) return 'linux';
    return null;
  }

  const formatSize = (bytes) => (bytes >= 1e6 ? `${Math.round(bytes / 1e6)} MB` : `${Math.max(1, Math.round(bytes / 1e3))} KB`);

  function apply(release) {
    document.querySelectorAll('[data-version]').forEach((el) => { el.textContent = release.tag_name; });

    const assets = {};
    for (const [key, match] of Object.entries(MATCHERS)) {
      const asset = release.assets.find((a) => match(a.name));
      if (asset) assets[key] = asset;
    }

    document.querySelectorAll('[data-asset]').forEach((card) => {
      const asset = assets[card.dataset.asset];
      if (!asset) return;
      card.href = asset.browser_download_url;
      const size = card.querySelector('[data-size]');
      if (size) size.textContent = `${asset.name} · ${formatSize(asset.size)}`;
    });

    const platform = detectPlatform();
    const primary = document.querySelector('[data-primary-download]');
    if (platform && assets[platform] && primary) {
      primary.href = assets[platform].browser_download_url;
      primary.querySelector('[data-primary-label]').textContent = LABELS[platform];
      document.querySelector(`[data-asset="${platform}"]`)?.classList.add('recommended');
      const note = document.querySelector('[data-primary-note]');
      if (note) note.textContent = `${release.tag_name} · ${formatSize(assets[platform].size)} · Other platforms, CLI and VS Code extension below`;
    }
  }

  fetch(`https://api.github.com/repos/${REPO}/releases/latest`, { headers: { Accept: 'application/vnd.github+json' } })
    .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`GitHub API ${r.status}`))))
    .then(apply)
    .catch(() => { /* keep the static fallback links */ });
})();
