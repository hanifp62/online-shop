// bundle-barrel-imports / bundle-defer-third-party analog:
// axios dihapus — app.js hanya memakai fetch(). File ini dipertahankan
// sebagai no-op agar `import './bootstrap'` tetap valid tanpa menambah bundle.
window.axios = window.axios ?? null;
