const fs = require('fs');
const path = require('path');

const foldersToCopy = ['admin', 'doctor', 'patient', 'includes'];
const filesToCopy = [
  'index.php',
  'login.php',
  'logout.php',
  'register.php',
  'appointment.php',
  'cancel_appointment.php',
  'my_appointments.php',
  'hash.php',
  'password.php',
  'reset_all_passwords.php'
];

const apiDir = path.join(__dirname, 'api');

// Create api directory if it doesn't exist
if (!fs.existsSync(apiDir)) {
  fs.mkdirSync(apiDir);
}

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

// Copy folders
for (let folder of foldersToCopy) {
  const src = path.join(__dirname, folder);
  const dest = path.join(apiDir, folder);
  if (fs.existsSync(src)) {
    copyFolderRecursive(src, dest);
  }
}

// Copy files
for (let file of filesToCopy) {
  const src = path.join(__dirname, file);
  const dest = path.join(apiDir, file);
  if (fs.existsSync(src)) {
    fs.copyFileSync(src, dest);
  }
}

console.log('Build completed: PHP files copied to api/ directory successfully.');
