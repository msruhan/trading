<template>
  <div class="landing-page min-h-screen bg-dark-950 text-white overflow-hidden">
    <!-- Animated Background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-1/2 -left-1/2 w-full h-full bg-gradient-radial from-emerald-500/10 via-transparent to-transparent animate-pulse-slow"></div>
      <div class="absolute -bottom-1/2 -right-1/2 w-full h-full bg-gradient-radial from-cyan-500/10 via-transparent to-transparent animate-pulse-slow delay-1000"></div>
      <!-- Grid Pattern -->
      <div class="absolute inset-0 bg-[linear-gradient(rgba(34,197,94,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(34,197,94,0.03)_1px,transparent_1px)] bg-[size:60px_60px]"></div>
    </div>

    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 glass border-b border-dark-800/50">
      <div class="max-w-7xl mx-auto px-6 py-4">
        <div class="flex items-center justify-between">
          <!-- Logo -->
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-400 flex items-center justify-center">
              <svg class="w-6 h-6 text-dark-950" fill="currentColor" viewBox="0 0 24 24">
                <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
              </svg>
            </div>
            <span class="text-xl font-bold tracking-tight">
              <span class="text-white">Trading</span>
              <span class="text-emerald-400">Journal</span>
            </span>
          </div>

          <!-- Nav Links -->
          <div class="hidden md:flex items-center gap-8">
            <a href="#features" class="text-dark-300 hover:text-white transition-colors">Features</a>
            <a href="#how-it-works" class="text-dark-300 hover:text-white transition-colors">How It Works</a>
            <a href="#pricing" class="text-dark-300 hover:text-white transition-colors">Pricing</a>
          </div>

          <!-- Auth Buttons -->
          <div class="flex items-center gap-3">
            <router-link to="/login" class="px-4 py-2 text-dark-300 hover:text-white transition-colors">
              Login
            </router-link>
            <router-link to="/register" class="px-5 py-2.5 bg-gradient-to-r from-emerald-500 to-cyan-500 text-dark-950 font-semibold rounded-xl hover:shadow-glow transition-all duration-300 hover:scale-105">
              Get Started Free
            </router-link>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center pt-20">
      <div class="max-w-7xl mx-auto px-6 py-20">
        <div class="text-center space-y-8">
          <!-- Badge -->
          <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-500/10 border border-emerald-500/20 animate-fade-in">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-sm text-emerald-400">Real-time sync with MT4/MT5</span>
          </div>

          <!-- Heading -->
          <h1 class="text-5xl md:text-7xl font-bold leading-tight animate-slide-up">
            <span class="block">Track Your</span>
            <span class="block mt-2">
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-cyan-400 to-emerald-400 animate-gradient">Trading Journey</span>
            </span>
          </h1>

          <!-- Subheading -->
          <p class="max-w-2xl mx-auto text-xl text-dark-400 animate-slide-up delay-100">
            Platform all-in-one untuk mencatat, menganalisis, dan mengembangkan performa trading Anda dengan sinkronisasi real-time dari broker MT4/MT5.
          </p>

          <!-- CTA Buttons -->
          <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4 animate-slide-up delay-200">
            <router-link to="/register" class="group px-8 py-4 bg-gradient-to-r from-emerald-500 to-cyan-500 text-dark-950 font-bold rounded-xl hover:shadow-glow transition-all duration-300 hover:scale-105 flex items-center gap-2">
              Mulai Gratis
              <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
              </svg>
            </router-link>
            <a href="#features" class="px-8 py-4 border border-dark-700 text-white font-semibold rounded-xl hover:bg-dark-800/50 transition-all duration-300 flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Lihat Demo
            </a>
          </div>

          <!-- Stats Preview -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-16 animate-slide-up delay-300">
            <div class="p-6 rounded-2xl bg-dark-900/50 border border-dark-800 backdrop-blur-sm">
              <div class="text-3xl font-bold text-emerald-400">10K+</div>
              <div class="text-sm text-dark-400 mt-1">Active Traders</div>
            </div>
            <div class="p-6 rounded-2xl bg-dark-900/50 border border-dark-800 backdrop-blur-sm">
              <div class="text-3xl font-bold text-cyan-400">$50M+</div>
              <div class="text-sm text-dark-400 mt-1">Trades Tracked</div>
            </div>
            <div class="p-6 rounded-2xl bg-dark-900/50 border border-dark-800 backdrop-blur-sm">
              <div class="text-3xl font-bold text-emerald-400">99.9%</div>
              <div class="text-sm text-dark-400 mt-1">Uptime</div>
            </div>
            <div class="p-6 rounded-2xl bg-dark-900/50 border border-dark-800 backdrop-blur-sm">
              <div class="text-3xl font-bold text-cyan-400">24/7</div>
              <div class="text-sm text-dark-400 mt-1">Auto Sync</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Scroll Indicator -->
      <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
        <svg class="w-6 h-6 text-dark-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
        </svg>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="relative py-32">
      <div class="max-w-7xl mx-auto px-6">
        <!-- Section Header -->
        <div class="text-center mb-20">
          <span class="text-emerald-400 font-semibold tracking-wider uppercase text-sm">Features</span>
          <h2 class="text-4xl md:text-5xl font-bold mt-4">
            Semua Yang Anda Butuhkan<br/>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">Dalam Satu Platform</span>
          </h2>
          <p class="max-w-2xl mx-auto text-dark-400 mt-6 text-lg">
            Dirancang khusus untuk trader forex dan crypto yang serius mengembangkan performa trading mereka.
          </p>
        </div>

        <!-- Features Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          <!-- Feature 1: Portfolio -->
          <div class="group p-8 rounded-2xl bg-dark-900/50 border border-dark-800 hover:border-emerald-500/50 transition-all duration-500 hover:-translate-y-2">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-emerald-500/20 to-emerald-500/5 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-7 h-7 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-3">Portfolio Dashboard</h3>
            <p class="text-dark-400 leading-relaxed">
              Pantau seluruh portofolio trading Anda dalam satu dashboard. Lihat total balance, equity, profit/loss, dan winrate secara real-time.
            </p>
          </div>

          <!-- Feature 2: Real-time Sync -->
          <div class="group p-8 rounded-2xl bg-dark-900/50 border border-dark-800 hover:border-cyan-500/50 transition-all duration-500 hover:-translate-y-2">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-cyan-500/20 to-cyan-500/5 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-7 h-7 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-3">Real-time Sync</h3>
            <p class="text-dark-400 leading-relaxed">
              Sinkronisasi otomatis dengan akun MT4/MT5 menggunakan investor password (read-only). Data trading Anda selalu update tanpa manual input.
            </p>
          </div>

          <!-- Feature 3: News Filter -->
          <div class="group p-8 rounded-2xl bg-dark-900/50 border border-dark-800 hover:border-amber-500/50 transition-all duration-500 hover:-translate-y-2">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-amber-500/20 to-amber-500/5 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-7 h-7 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-3">News Filter</h3>
            <p class="text-dark-400 leading-relaxed">
              Integrasi dengan ForexFactory untuk filter berita ekonomi berdasarkan impact level. Hindari trading saat high-impact news release.
            </p>
          </div>

          <!-- Feature 4: Trading Plan -->
          <div class="group p-8 rounded-2xl bg-dark-900/50 border border-dark-800 hover:border-violet-500/50 transition-all duration-500 hover:-translate-y-2">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-violet-500/20 to-violet-500/5 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-7 h-7 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-3">Trading Plan</h3>
            <p class="text-dark-400 leading-relaxed">
              Buat dan kelola trading plan Anda. Catat alasan entry, setup, dan evaluasi setiap trade untuk meningkatkan konsistensi.
            </p>
          </div>

          <!-- Feature 5: Monthly Recap -->
          <div class="group p-8 rounded-2xl bg-dark-900/50 border border-dark-800 hover:border-rose-500/50 transition-all duration-500 hover:-translate-y-2">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-rose-500/20 to-rose-500/5 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-7 h-7 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-3">Monthly Recap</h3>
            <p class="text-dark-400 leading-relaxed">
              Laporan rekapitulasi bulanan dengan statistik lengkap. Export ke PDF atau CSV untuk dokumentasi dan analisis mendalam.
            </p>
          </div>

          <!-- Feature 6: Analytics -->
          <div class="group p-8 rounded-2xl bg-dark-900/50 border border-dark-800 hover:border-blue-500/50 transition-all duration-500 hover:-translate-y-2">
            <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500/20 to-blue-500/5 flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
              <svg class="w-7 h-7 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <h3 class="text-xl font-bold mb-3">Advanced Analytics</h3>
            <p class="text-dark-400 leading-relaxed">
              Analisis performa dengan metrik seperti profit factor, average win/loss, risk-reward ratio, dan drawdown analysis.
            </p>
          </div>
        </div>

        <!-- Additional Features Row -->
        <div class="grid md:grid-cols-2 gap-6 mt-6">
          <!-- Multi-Account -->
          <div class="group p-8 rounded-2xl bg-gradient-to-br from-emerald-500/10 to-cyan-500/10 border border-emerald-500/20 hover:border-emerald-500/50 transition-all duration-500">
            <div class="flex items-start gap-6">
              <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-500 flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7 text-dark-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-bold mb-2">Multi-Account Management</h3>
                <p class="text-dark-400 leading-relaxed">
                  Kelola banyak akun trading dalam satu dashboard. Lihat aggregated statistics dari semua akun atau analisis per akun.
                </p>
              </div>
            </div>
          </div>

          <!-- Trading Calendar -->
          <div class="group p-8 rounded-2xl bg-gradient-to-br from-violet-500/10 to-rose-500/10 border border-violet-500/20 hover:border-violet-500/50 transition-all duration-500">
            <div class="flex items-start gap-6">
              <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-violet-500 to-rose-500 flex items-center justify-center flex-shrink-0">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
              <div>
                <h3 class="text-xl font-bold mb-2">Trading Calendar</h3>
                <p class="text-dark-400 leading-relaxed">
                  Visualisasi P/L harian dalam format kalender. Identifikasi pattern dan hari-hari terbaik untuk trading Anda.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- How It Works Section -->
    <section id="how-it-works" class="relative py-32 bg-dark-900/30">
      <div class="max-w-7xl mx-auto px-6">
        <div class="text-center mb-20">
          <span class="text-emerald-400 font-semibold tracking-wider uppercase text-sm">How It Works</span>
          <h2 class="text-4xl md:text-5xl font-bold mt-4">
            Mulai Dalam <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">3 Langkah</span>
          </h2>
        </div>

        <div class="grid md:grid-cols-3 gap-8">
          <!-- Step 1 -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-cyan-500 flex items-center justify-center text-dark-950 font-bold text-xl">
              1
            </div>
            <div class="p-8 pt-12 rounded-2xl bg-dark-900/50 border border-dark-800 h-full">
              <h3 class="text-xl font-bold mb-4">Buat Akun</h3>
              <p class="text-dark-400">
                Daftar gratis dan buat akun dalam hitungan detik. Tidak perlu kartu kredit.
              </p>
            </div>
          </div>

          <!-- Step 2 -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-cyan-500 flex items-center justify-center text-dark-950 font-bold text-xl">
              2
            </div>
            <div class="p-8 pt-12 rounded-2xl bg-dark-900/50 border border-dark-800 h-full">
              <h3 class="text-xl font-bold mb-4">Hubungkan Broker</h3>
              <p class="text-dark-400">
                Pasang EA Bridge di MT4/MT5 Anda dengan investor password. 100% aman dan read-only.
              </p>
            </div>
          </div>

          <!-- Step 3 -->
          <div class="relative">
            <div class="absolute -top-4 -left-4 w-12 h-12 rounded-full bg-gradient-to-br from-emerald-500 to-cyan-500 flex items-center justify-center text-dark-950 font-bold text-xl">
              3
            </div>
            <div class="p-8 pt-12 rounded-2xl bg-dark-900/50 border border-dark-800 h-full">
              <h3 class="text-xl font-bold mb-4">Mulai Tracking</h3>
              <p class="text-dark-400">
                Trade seperti biasa. Semua akan tercatat otomatis dan siap untuk dianalisis.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Security Section -->
    <section class="relative py-32">
      <div class="max-w-7xl mx-auto px-6">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
          <div>
            <span class="text-emerald-400 font-semibold tracking-wider uppercase text-sm">Security First</span>
            <h2 class="text-4xl md:text-5xl font-bold mt-4 mb-6">
              Keamanan Data<br/>
              <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">Prioritas Utama</span>
            </h2>
            <p class="text-dark-400 text-lg mb-8">
              Kami memahami bahwa data trading Anda adalah informasi sensitif. Itulah mengapa kami menerapkan standar keamanan tertinggi.
            </p>

            <div class="space-y-4">
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                  <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>
                <span class="text-dark-200">Investor Password Only (Read-Only Access)</span>
              </div>
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                  <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>
                <span class="text-dark-200">AES-256 Encryption untuk semua data</span>
              </div>
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                  <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>
                <span class="text-dark-200">HTTPS/TLS untuk semua komunikasi</span>
              </div>
              <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                  <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                  </svg>
                </div>
                <span class="text-dark-200">Two-Factor Authentication (2FA)</span>
              </div>
            </div>
          </div>

          <!-- Security Visual -->
          <div class="relative">
            <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/20 to-cyan-500/20 rounded-3xl blur-3xl"></div>
            <div class="relative p-8 rounded-3xl bg-dark-900/80 border border-dark-800">
              <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 to-cyan-500 flex items-center justify-center">
                  <svg class="w-6 h-6 text-dark-950" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                </div>
                <div>
                  <div class="font-bold text-lg">Secured Connection</div>
                  <div class="text-sm text-emerald-400">End-to-end encrypted</div>
                </div>
              </div>
              <div class="space-y-3">
                <div class="flex justify-between items-center p-3 rounded-lg bg-dark-800/50">
                  <span class="text-dark-400">Data Encryption</span>
                  <span class="text-emerald-400 font-mono text-sm">AES-256</span>
                </div>
                <div class="flex justify-between items-center p-3 rounded-lg bg-dark-800/50">
                  <span class="text-dark-400">API Security</span>
                  <span class="text-emerald-400 font-mono text-sm">HMAC-SHA256</span>
                </div>
                <div class="flex justify-between items-center p-3 rounded-lg bg-dark-800/50">
                  <span class="text-dark-400">Transport Layer</span>
                  <span class="text-emerald-400 font-mono text-sm">TLS 1.3</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="relative py-32 bg-dark-900/30">
      <div class="max-w-7xl mx-auto px-6">
        <div class="text-center mb-16">
          <span class="text-emerald-400 font-semibold tracking-wider uppercase text-sm">Pricing</span>
          <h2 class="text-4xl md:text-5xl font-bold mt-4">
            Pilih Paket <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">Terbaik</span>
          </h2>
          <p class="text-dark-400 mt-4 text-lg">Mulai gratis, upgrade kapanpun Anda siap</p>
        </div>

        <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
          <!-- Free Plan -->
          <div class="p-8 rounded-2xl bg-dark-900/50 border border-dark-800">
            <div class="text-dark-400 font-semibold mb-4">Starter</div>
            <div class="flex items-baseline gap-1 mb-6">
              <span class="text-4xl font-bold">Free</span>
            </div>
            <p class="text-dark-400 mb-8">Untuk trader yang baru memulai journey.</p>
            <ul class="space-y-3 mb-8">
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                1 Trading Account
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Basic Dashboard
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Manual Trade Entry
              </li>
              <li class="flex items-center gap-3 text-dark-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Auto Sync
              </li>
            </ul>
            <router-link to="/register" class="block w-full py-3 text-center border border-dark-700 rounded-xl font-semibold hover:bg-dark-800 transition-colors">
              Get Started
            </router-link>
          </div>

          <!-- Pro Plan -->
          <div class="relative p-8 rounded-2xl bg-gradient-to-b from-emerald-500/10 to-cyan-500/10 border-2 border-emerald-500/50">
            <div class="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 bg-gradient-to-r from-emerald-500 to-cyan-500 rounded-full text-dark-950 text-sm font-bold">
              POPULAR
            </div>
            <div class="text-emerald-400 font-semibold mb-4">Pro</div>
            <div class="flex items-baseline gap-1 mb-6">
              <span class="text-4xl font-bold">$9</span>
              <span class="text-dark-400">/month</span>
            </div>
            <p class="text-dark-400 mb-8">Untuk trader serius yang butuh fitur lengkap.</p>
            <ul class="space-y-3 mb-8">
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                5 Trading Accounts
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Real-time Auto Sync
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                News Filter
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Advanced Analytics
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                PDF/CSV Export
              </li>
            </ul>
            <router-link to="/register" class="block w-full py-3 text-center bg-gradient-to-r from-emerald-500 to-cyan-500 rounded-xl text-dark-950 font-bold hover:shadow-glow transition-all">
              Start Free Trial
            </router-link>
          </div>

          <!-- Enterprise Plan -->
          <div class="p-8 rounded-2xl bg-dark-900/50 border border-dark-800">
            <div class="text-dark-400 font-semibold mb-4">Enterprise</div>
            <div class="flex items-baseline gap-1 mb-6">
              <span class="text-4xl font-bold">Custom</span>
            </div>
            <p class="text-dark-400 mb-8">Untuk prop firms dan trading groups.</p>
            <ul class="space-y-3 mb-8">
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Unlimited Accounts
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Team Management
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Custom Integration
              </li>
              <li class="flex items-center gap-3 text-dark-300">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Priority Support
              </li>
            </ul>
            <a href="mailto:contact@tradingjournal.com" class="block w-full py-3 text-center border border-dark-700 rounded-xl font-semibold hover:bg-dark-800 transition-colors">
              Contact Sales
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="relative py-32">
      <div class="max-w-4xl mx-auto px-6 text-center">
        <div class="p-12 rounded-3xl bg-gradient-to-br from-emerald-500/10 via-cyan-500/10 to-violet-500/10 border border-emerald-500/20">
          <h2 class="text-4xl md:text-5xl font-bold mb-6">
            Siap Tingkatkan<br/>
            <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-cyan-400">Performa Trading?</span>
          </h2>
          <p class="text-dark-400 text-lg mb-8 max-w-2xl mx-auto">
            Bergabung dengan ribuan trader yang sudah menggunakan Trading Journal untuk menganalisis dan meningkatkan hasil trading mereka.
          </p>
          <router-link to="/register" class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-emerald-500 to-cyan-500 text-dark-950 font-bold rounded-xl hover:shadow-glow transition-all duration-300 hover:scale-105">
            Mulai Gratis Sekarang
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
          </router-link>
        </div>
      </div>
    </section>

    <!-- Footer -->
    <footer class="relative py-16 border-t border-dark-800">
      <div class="max-w-7xl mx-auto px-6">
        <div class="grid md:grid-cols-4 gap-12">
          <!-- Logo & Description -->
          <div class="md:col-span-2">
            <div class="flex items-center gap-3 mb-4">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-400 flex items-center justify-center">
                <svg class="w-6 h-6 text-dark-950" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/>
                </svg>
              </div>
              <span class="text-xl font-bold">
                <span class="text-white">Trading</span>
                <span class="text-emerald-400">Journal</span>
              </span>
            </div>
            <p class="text-dark-400 max-w-sm">
              Platform all-in-one untuk mencatat, menganalisis, dan mengembangkan performa trading Anda.
            </p>
          </div>

          <!-- Links -->
          <div>
            <h4 class="font-semibold mb-4">Product</h4>
            <ul class="space-y-2 text-dark-400">
              <li><a href="#features" class="hover:text-white transition-colors">Features</a></li>
              <li><a href="#pricing" class="hover:text-white transition-colors">Pricing</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Documentation</a></li>
              <li><a href="#" class="hover:text-white transition-colors">API</a></li>
            </ul>
          </div>

          <div>
            <h4 class="font-semibold mb-4">Company</h4>
            <ul class="space-y-2 text-dark-400">
              <li><a href="#" class="hover:text-white transition-colors">About</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Blog</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Privacy</a></li>
              <li><a href="#" class="hover:text-white transition-colors">Terms</a></li>
            </ul>
          </div>
        </div>

        <div class="mt-12 pt-8 border-t border-dark-800 flex flex-col md:flex-row justify-between items-center gap-4">
          <p class="text-dark-500 text-sm">© 2025 Trading Journal. All rights reserved.</p>
          <div class="flex items-center gap-4">
            <a href="#" class="text-dark-500 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
              </svg>
            </a>
            <a href="#" class="text-dark-500 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
              </svg>
            </a>
            <a href="#" class="text-dark-500 hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
              </svg>
            </a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup>
// Landing page - no additional logic needed
</script>

<style scoped>
/* Gradient animation */
@keyframes gradient {
  0%, 100% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
}

.animate-gradient {
  background-size: 200% auto;
  animation: gradient 4s ease infinite;
}

/* Radial gradient helper */
.bg-gradient-radial {
  background: radial-gradient(circle, var(--tw-gradient-stops));
}

/* Smooth scroll behavior */
html {
  scroll-behavior: smooth;
}

/* Delay utilities */
.delay-1000 {
  animation-delay: 1000ms;
}
</style>

