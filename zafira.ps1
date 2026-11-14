<#
.SYNOPSIS
    Launcher & Menu Perintah Cepat ZAFIRA (PowerShell)
.DESCRIPTION
    Jalankan tanpa argumen (.\zafira.ps1) untuk membuka menu interaktif,
    atau gunakan argumen singkat: start | users | google | build | clear | test | backup
#>

param(
    [Parameter(Position = 0)]
    [ValidateSet('', 'menu', 'start', 'users', 'google', 'admins', 'admin-pass', 'build', 'clear', 'test', 'backup')]
    [string]$Action = 'menu'
)

Set-Location -Path $PSScriptRoot

function Invoke-ZafiraAction([string]$Choice) {
    switch ($Choice) {
        'start' {
            Write-Host "`n[ZAFIRA] Menjalankan server lokal di http://127.0.0.1:8000 ..." -ForegroundColor Cyan
            Write-Host "Tekan Ctrl + C untuk menghentikan server.`n" -ForegroundColor DarkGray
            & php artisan serve --host=127.0.0.1 --port=8000
        }
        'users' {
            Write-Host "`n[ZAFIRA] Daftar semua pengguna & status sesi aktif:`n" -ForegroundColor Cyan
            & php artisan auth:users
        }
        'google' {
            Write-Host "`n[ZAFIRA] Daftar pengguna yang login/tertaut Google:`n" -ForegroundColor Cyan
            & php artisan auth:users --google
        }
        'admins' {
            Write-Host "`n[ZAFIRA] Daftar akun Admin Atelier:`n" -ForegroundColor Cyan
            & php artisan auth:admins
        }
        'admin-pass' {
            Write-Host "`n[ZAFIRA] Mengatur password Admin Atelier (input ketikan tidak ditampilkan di layar):`n" -ForegroundColor Cyan
            & php artisan admin:password
        }
        'build' {
            Write-Host "`n[ZAFIRA] Mem-build ulang aset CSS & React (Vite)..." -ForegroundColor Cyan
            & npm run build
        }
        'clear' {
            Write-Host "`n[ZAFIRA] Membersihkan cache konfigurasi & tampilan Laravel..." -ForegroundColor Cyan
            & php artisan config:clear
            & php artisan view:clear
            Write-Host "Selesai! Jangan lupa tekan Ctrl + Shift + R di browser." -ForegroundColor Green
        }
        'test' {
            Write-Host "`n[ZAFIRA] Menjalankan pengujian komponen React (Vitest)..." -ForegroundColor Cyan
            & npm run test:react
        }
        'backup' {
            Write-Host "`n[ZAFIRA] Membuat backup database SQLite & verifikasi otomatis..." -ForegroundColor Cyan
            & php artisan db:backup --verify
        }
    }
}

if ($Action -ne '' -and $Action -ne 'menu') {
    Invoke-ZafiraAction $Action
    exit $LASTEXITCODE
}

Write-Host ""
Write-Host "======================================================" -ForegroundColor Yellow
Write-Host "   ZAFIRA — LA MAISON DE LUMIERE (QUICK COMMANDS)     " -ForegroundColor Yellow
Write-Host "======================================================" -ForegroundColor Yellow
Write-Host "  [1] Jalankan Server Web     (http://127.0.0.1:8000)"
Write-Host "  [2] Cek Semua User Login    (php artisan auth:users)"
Write-Host "  [3] Cek Khusus Login Google (php artisan auth:users --google)"
Write-Host "  [4] Build Ulang UI/React    (npm run build)"
Write-Host "  [5] Bersihkan Cache .env/UI (config:clear + view:clear)"
Write-Host "  [6] Test Frontend React     (npm run test:react)"
Write-Host "  [7] Backup Database Aman    (php artisan db:backup --verify)"
Write-Host "  [8] Cek Daftar Akun Admin   (php artisan auth:admins)"
Write-Host "  [9] Set / Reset Sandi Admin (php artisan admin:password)"
Write-Host "  [0] Keluar"
Write-Host "------------------------------------------------------" -ForegroundColor DarkGray

$pick = Read-Host "Pilih nomor perintah [0-9]"
switch ($pick) {
    '1' { Invoke-ZafiraAction 'start' }
    '2' { Invoke-ZafiraAction 'users' }
    '3' { Invoke-ZafiraAction 'google' }
    '4' { Invoke-ZafiraAction 'build' }
    '5' { Invoke-ZafiraAction 'clear' }
    '6' { Invoke-ZafiraAction 'test' }
    '7' { Invoke-ZafiraAction 'backup' }
    '8' { Invoke-ZafiraAction 'admins' }
    '9' { Invoke-ZafiraAction 'admin-pass' }
    default { Write-Host "Keluar dari menu." -ForegroundColor DarkGray }
}

