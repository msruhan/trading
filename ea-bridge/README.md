# Trading Journal Bridge - EA Setup Guide

## 📋 Overview
EA ini mengirimkan data trading (balance, equity, trades) dari MetaTrader 4 ke Trading Journal Dashboard melalui API.

## 🔧 Setup Instructions

### 1. Dapatkan Token dan Account ID

1. **Login ke Dashboard** → Buka halaman **Accounts**
2. **Klik "Add Account"** atau pilih account yang sudah ada
3. **Catat informasi berikut:**
   - **Account ID**: Nomor ID account (contoh: `1`, `2`, dll)
   - **API Token**: Token yang ditampilkan saat membuat account atau regenerate token
   - **API URL**: URL ngrok (contoh: `https://ghislaine-boundless-billye.ngrok-free.dev/api/v1/incoming/trades`)

### 2. Konfigurasi EA di MT4

1. **Buka MetaEditor** (F4 di MT4)
2. **Buka file** `TradingJournalBridge.mq4`
3. **Update konfigurasi berikut:**

```mql4
input string   API_URL = "https://ghislaine-boundless-billye.ngrok-free.dev/api/v1/incoming/trades";
input string   API_TOKEN = "PASTE_TOKEN_DARI_DASHBOARD_DISINI";
input int      ACCOUNT_ID = 1;  // Ganti dengan Account ID dari dashboard
```

4. **Compile** (F7)
5. **Restart MT4**

### 3. Tambahkan URL ke MT4 Allowed URLs

1. Di MT4: **Tools** → **Options** → **Expert Advisors**
2. Centang **"Allow WebRequest for listed URL"**
3. Klik **Add** dan masukkan:
   ```
   https://ghislaine-boundless-billye.ngrok-free.dev
   ```
4. Klik **OK** dan **Restart MT4**

### 4. Attach EA ke Chart

1. **Drag EA** `TradingJournalBridge` ke chart mana saja
2. **Pastikan**:
   - ✅ API_URL sudah benar
   - ✅ API_TOKEN sudah diisi
   - ✅ ACCOUNT_ID sudah sesuai dengan account di dashboard
   - ✅ "Allow DLL imports" dicentang (jika muncul)
3. **Cek tab Experts** untuk melihat log

## ⚠️ Troubleshooting

### Error 5200: Invalid URL or connection refused
- ✅ Pastikan ngrok masih berjalan
- ✅ Pastikan URL sudah ditambahkan ke MT4 Allowed URLs
- ✅ Restart MT4 setelah menambahkan URL
- ✅ Cek apakah ngrok URL sudah berubah (restart ngrok = URL baru)

### Error 5203: Connection failed
- ✅ Pastikan ngrok masih berjalan
- ✅ Cek URL ngrok di terminal (bisa berubah setiap restart)
- ✅ Update API_URL di EA dengan URL ngrok yang baru

### Error 401: Unauthorized
- ✅ Pastikan API_TOKEN sudah benar (copy-paste dari dashboard)
- ✅ Pastikan ACCOUNT_ID sesuai dengan account di dashboard
- ✅ Jika token hilang, regenerate token di dashboard dan update EA

### Error 500: Internal Server Error
- ✅ Cek log Laravel: `backend/storage/logs/laravel.log`
- ✅ Pastikan database sudah di-migrate
- ✅ Pastikan server Laravel masih berjalan

## 🔄 Update Token atau URL

### Jika Token Hilang:
1. Buka **Account Detail** di dashboard
2. Klik **"Regenerate Token"**
3. **Copy token baru** dan update di EA
4. **Recompile EA** (F7)
5. **Restart MT4**

### Jika Ngrok URL Berubah:
1. **Cek URL ngrok** di terminal atau browser (http://127.0.0.1:4040)
2. **Update API_URL** di EA dengan URL baru
3. **Update Allowed URLs** di MT4 dengan URL baru
4. **Recompile EA** dan **Restart MT4**

## 📊 Data yang Dikirim

EA mengirimkan:
- ✅ **Balance**: Saldo akun
- ✅ **Equity**: Equity akun
- ✅ **Margin**: Margin yang digunakan
- ✅ **Free Margin**: Margin bebas
- ✅ **Margin Level**: Level margin
- ✅ **Trades**: Semua open trades dan history trades (jika SYNC_HISTORY = true)

## ⚙️ EA Settings

```mql4
SYNC_INTERVAL_SECONDS = 60    // Interval sync (detik)
SYNC_HISTORY = true           // Sync historical trades
HISTORY_DAYS = 30             // Jumlah hari history yang di-sync
```

## 🔗 Links

- **Dashboard**: http://localhost:5173 (atau URL frontend Anda)
- **Ngrok Dashboard**: http://127.0.0.1:4040 (untuk cek URL ngrok)
- **API Health Check**: `GET /api/v1/incoming/health`

## 📝 Notes

- Token hanya ditampilkan **sekali** saat account dibuat atau regenerate
- **Simpan token dengan aman** - jika hilang, harus regenerate
- Ngrok URL bisa berubah setiap restart - **update EA jika berubah**
- Pastikan **ngrok tetap berjalan** saat menggunakan EA
- EA akan sync otomatis setiap `SYNC_INTERVAL_SECONDS` detik
