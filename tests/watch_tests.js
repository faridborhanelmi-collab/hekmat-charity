// tests/watch_tests.js
// Automated Watcher: Automatically runs tests whenever any PHP file is saved

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('\x1b[1;36m============================================================\x1b[0m');
console.log('\x1b[1;36m     👀  HEKMAT CHARITY LIVE AUTOMATED REGRESSION WATCHER   \x1b[0m');
console.log('\x1b[1;36m============================================================\x1b[0m');
console.log('Watching for file changes across project (Press Ctrl+C to stop)...\n');

let isRunning = false;
let debounceTimer = null;

function runTests(triggeredBy) {
  if (isRunning) return;
  isRunning = true;

  console.log(`\n\x1b[33m⚡ File changed: ${triggeredBy}\x1b[0m`);
  console.log('\x1b[34m▶ Running automated regression tests...\x1b[0m');

  const start = Date.now();
  try {
    const output = execSync('php tests/run_unit_tests.php', { encoding: 'utf8' });
    console.log(output);
    const duration = ((Date.now() - start) / 1000).toFixed(2);
    console.log(`\x1b[32m✔ Automated checks completed in ${duration}s. All safe!\x1b[0m\n`);
  } catch (err) {
    console.error(err.stdout || err.stderr || err.message);
    console.error('\x1b[1;31m❌ REGRESSION DETECTED! Revert or fix the recent changes.\x1b[0m\n');
  } finally {
    isRunning = false;
  }
}

// Initial run
runTests('Initial startup');

// Watch root and important directories
const watchDirs = ['.', 'admin', 'includes'];
watchDirs.forEach(dir => {
  const fullPath = path.resolve(__dirname, '..', dir);
  if (fs.existsSync(fullPath)) {
    fs.watch(fullPath, { recursive: false }, (eventType, filename) => {
      if (!filename || !filename.endsWith('.php')) return;
      if (filename.includes('test_hekmat.db')) return;

      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        runTests(`${dir}/${filename}`);
      }, 300);
    });
  }
});
