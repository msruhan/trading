# Trading Journal & Portfolio Dashboard

A comprehensive web application for recording, displaying, and analyzing monthly/daily trading activities with real-time broker synchronization.

![Trading Journal](https://img.shields.io/badge/Vue.js-3.x-green) ![Laravel](https://img.shields.io/badge/Laravel-11.x-red) ![Tailwind](https://img.shields.io/badge/Tailwind-3.x-blue)

## 🚀 Features

- **Dashboard**: Real-time overview of balance, equity, P/L, and winrate
- **Auto Sync**: Connect MT4/MT5 accounts via EA Bridge (read-only investor password)
- **Manual Entry**: Add trades manually or upload CSV/HTML statements
- **Portfolio**: Multi-account management with aggregated statistics
- **Calendar & News**: Trading calendar with daily P/L and market news
- **Reports**: Export monthly reports in CSV/PDF format
- **Dark/Light Mode**: Beautiful UI with theme toggle
- **Real-time Updates**: WebSocket-powered live data updates

## 📁 Project Structure

```
trading/
├── frontend/          # Vue.js 3 + Tailwind + Vite
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── stores/
│   │   ├── services/
│   │   └── ...
│   └── package.json
├── backend/           # Laravel 11 (PHP 8.2+)
│   ├── app/
│   ├── database/
│   ├── routes/
│   └── composer.json
├── ea-bridge/         # MQL4/MQL5 Expert Advisor
├── docker/            # Docker configuration
└── README.md
```

## 🛠️ Installation

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+
- MySQL 8.0+
- Redis (for queues)

### Backend Setup

```bash
cd backend

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
# DB_DATABASE=trading_journal
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed demo data
php artisan db:seed

# Start development server
php artisan serve
```

### Frontend Setup

```bash
cd frontend

# Install dependencies
npm install

# Start development server
npm run dev
```

### Docker Setup (Recommended)

```bash
# Start all services
docker-compose up -d

# Run migrations
docker-compose exec backend php artisan migrate --seed
```

## 🔐 Security

- **Investor Password Only**: Only read-only investor passwords are accepted
- **AES-256 Encryption**: All credentials encrypted at rest
- **HTTPS Required**: All communications over TLS
- **HMAC Authentication**: EA Bridge uses signed tokens
- **Rate Limiting**: API endpoints are rate-limited
- **2FA Support**: Optional two-factor authentication

## 📡 API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | User authentication |
| GET | `/api/v1/dashboard` | Dashboard metrics |
| GET | `/api/v1/accounts` | List trading accounts |
| POST | `/api/v1/accounts` | Add new account |
| POST | `/api/v1/accounts/{id}/sync` | Trigger manual sync |
| POST | `/api/v1/incoming/trades` | EA Bridge webhook |
| GET | `/api/v1/trades` | List trades |
| POST | `/api/v1/trades/manual` | Add manual trade |
| POST | `/api/v1/upload/statement` | Upload MT4/MT5 statement |
| GET | `/api/v1/reports/monthly` | Export monthly report |

## 🤖 EA Bridge Setup

1. Copy `ea-bridge/TradingJournalBridge.mq4` to MT4 Experts folder
2. Configure the API URL and authentication token
3. Attach to any chart (runs independently)
4. The EA will automatically sync trades to your dashboard

## 📊 Database Schema

See `backend/database/migrations/` for complete schema or import `database/schema.sql` directly to phpMyAdmin.

## 🎨 UI Reference

Design inspired by [Hyperliquid](https://app.hyperliquid.xyz/) - minimal, modern, charts-focused with side navigation.

## 📝 License

MIT License - feel free to use for personal or commercial projects.

## 🤝 Contributing

Contributions are welcome! Please read our contributing guidelines first.

$env:Path = "C:\xampp2\php;" + ($env:Path -replace 'C:\\xampp\\php;?','') 