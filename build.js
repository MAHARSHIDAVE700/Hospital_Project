const fs = require('fs');
const path = require('path');

// Helper to recursively copy directories
function copyFolderRecursive(src, dest) {
  if (!fs.existsSync(dest)) {
    fs.mkdirSync(dest, { recursive: true });
  }
  const entries = fs.readdirSync(src, { withFileTypes: true });
  for (let entry of entries) {
    const srcPath = path.join(src, entry.name);
    const destPath = path.join(dest, entry.name);
    if (entry.isDirectory()) {
      copyFolderRecursive(srcPath, destPath);
    } else {
      fs.copyFileSync(srcPath, destPath);
    }
  }
}

// Create public directory if it doesn't exist and copy assets
const publicDir = path.join(__dirname, 'public');
if (!fs.existsSync(publicDir)) {
  fs.mkdirSync(publicDir);
}

const assetsSrc = path.join(__dirname, 'assets');
const assetsDest = path.join(publicDir, 'assets');
if (fs.existsSync(assetsSrc)) {
  copyFolderRecursive(assetsSrc, assetsDest);
}

console.log('Build completed: assets copied to public/ successfully.');
