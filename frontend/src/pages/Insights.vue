<template>
  <div class="min-h-screen text-dark-100">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-dark-100 mb-2">Trading Insights</h1>
      <p class="text-dark-400">Analisis mendalam tentang performa trading Anda</p>
    </div>

      <!-- Period Selector -->
      <div class="mb-6 flex items-center gap-4">
        <label class="text-sm text-dark-300">Periode Analisis:</label>
        <select
          v-model="selectedDays"
          @change="loadInsights"
          class="px-4 py-2 bg-dark-800 border border-dark-700 rounded-lg text-dark-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
        >
          <option :value="30">30 Hari</option>
          <option :value="60">60 Hari</option>
          <option :value="90">90 Hari</option>
          <option :value="180">180 Hari</option>
        </select>
        <button
          @click="loadInsights"
          :disabled="loading"
          class="px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white rounded-lg disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <ArrowPathIcon :class="['w-4 h-4', loading && 'animate-spin']" />
          Refresh
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary-500"></div>
        <p class="mt-4 text-dark-400">Memuat insights...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6">
        <p class="text-red-400">{{ error }}</p>
      </div>

      <!-- Insights Content -->
      <div v-else-if="insights" class="space-y-6">
        <!-- 1. Hari Terbaik Trading dalam Seminggu -->
        <div class="bg-dark-900 border border-dark-800 rounded-xl p-6">
          <h2 class="text-xl font-semibold text-dark-100 mb-4 flex items-center gap-2">
            <CalendarDaysIcon class="w-6 h-6 text-primary-500" />
            Hari Terbaik Trading dalam Seminggu
          </h2>
          
          <div v-if="insights.best_day_of_week?.best_day" class="mb-4">
            <div class="flex items-center gap-4">
              <div class="flex-1 bg-dark-800 rounded-lg p-4">
                <p class="text-sm text-dark-400 mb-1">Hari Terbaik</p>
                <p class="text-2xl font-bold text-green-400">{{ insights.best_day_of_week.best_day.day_name }}</p>
                <p class="text-sm text-dark-300 mt-1">
                  Profit: <span class="text-green-400">{{ formatCurrency(insights.best_day_of_week.best_day.total_profit) }}</span>
                  | Winrate: <span class="text-green-400">{{ insights.best_day_of_week.best_day.winrate }}%</span>
                </p>
              </div>
              <div v-if="insights.best_day_of_week.worst_day" class="flex-1 bg-dark-800 rounded-lg p-4">
                <p class="text-sm text-dark-400 mb-1">Hari Terburuk</p>
                <p class="text-2xl font-bold text-red-400">{{ insights.best_day_of_week.worst_day.day_name }}</p>
                <p class="text-sm text-dark-300 mt-1">
                  Profit: <span class="text-red-400">{{ formatCurrency(insights.best_day_of_week.worst_day.total_profit) }}</span>
                  | Winrate: <span class="text-red-400">{{ insights.best_day_of_week.worst_day.winrate }}%</span>
                </p>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-7 gap-2">
            <div
              v-for="day in insights.best_day_of_week?.days || []"
              :key="day.day_number"
              class="bg-dark-800 rounded-lg p-3 text-center"
            >
              <p class="text-xs text-dark-400 mb-1">{{ day.day_name.substring(0, 3) }}</p>
              <p :class="['text-lg font-bold', day.total_profit >= 0 ? 'text-green-400' : 'text-red-400']">
                {{ formatCurrency(day.total_profit) }}
              </p>
              <p class="text-xs text-dark-300 mt-1">{{ day.total_trades }} trades</p>
            </div>
          </div>
        </div>

        <!-- 2. Jam Paling Profit / Jam Paling Berbahaya -->
        <div class="bg-dark-900 border border-dark-800 rounded-xl p-6">
          <h2 class="text-xl font-semibold text-dark-100 mb-4 flex items-center gap-2">
            <ClockIcon class="w-6 h-6 text-primary-500" />
            Jam Paling Profit / Jam Paling Berbahaya
          </h2>
          
          <div v-if="insights.best_hours?.best_hour" class="mb-4 grid grid-cols-2 gap-4">
            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
              <p class="text-sm text-dark-400 mb-1">Jam Terbaik</p>
              <p class="text-2xl font-bold text-green-400">{{ formatHour(insights.best_hours.best_hour.hour) }}</p>
              <p class="text-sm text-dark-300 mt-1">
                Profit: <span class="text-green-400">{{ formatCurrency(insights.best_hours.best_hour.total_profit) }}</span>
                | Winrate: <span class="text-green-400">{{ insights.best_hours.best_hour.winrate }}%</span>
              </p>
            </div>
            <div v-if="insights.best_hours.worst_hour" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
              <p class="text-sm text-dark-400 mb-1">Jam Terburuk</p>
              <p class="text-2xl font-bold text-red-400">{{ formatHour(insights.best_hours.worst_hour.hour) }}</p>
              <p class="text-sm text-dark-300 mt-1">
                Profit: <span class="text-red-400">{{ formatCurrency(insights.best_hours.worst_hour.total_profit) }}</span>
                | Winrate: <span class="text-red-400">{{ insights.best_hours.worst_hour.winrate }}%</span>
              </p>
            </div>
          </div>

          <div class="grid grid-cols-12 gap-1 mt-4">
            <div
              v-for="hour in insights.best_hours?.hours || []"
              :key="hour.hour"
              :class="[
                'bg-dark-800 rounded p-2 text-center',
                hour.total_profit >= 0 ? 'border border-green-500/30' : 'border border-red-500/30'
              ]"
            >
              <p class="text-xs text-dark-400">{{ formatHour(hour.hour) }}</p>
              <p :class="['text-sm font-bold', hour.total_profit >= 0 ? 'text-green-400' : 'text-red-400']">
                {{ formatCurrency(hour.total_profit) }}
              </p>
            </div>
          </div>
        </div>

        <!-- 3. Pair Paling Stabil -->
        <div class="bg-dark-900 border border-dark-800 rounded-xl p-6">
          <h2 class="text-xl font-semibold text-dark-100 mb-4 flex items-center gap-2">
            <ChartBarIcon class="w-6 h-6 text-primary-500" />
            Pair Paling Stabil
          </h2>
          
          <div v-if="insights.most_stable_pairs?.most_stable" class="mb-4">
            <div class="bg-dark-800 rounded-lg p-4">
              <p class="text-sm text-dark-400 mb-1">Pair Paling Stabil</p>
              <p class="text-2xl font-bold text-primary-400">{{ insights.most_stable_pairs.most_stable.pair }}</p>
              <p class="text-sm text-dark-300 mt-1">
                Stability Score: <span class="text-primary-400">{{ insights.most_stable_pairs.most_stable.stability_score }}%</span>
                | Winrate: <span class="text-green-400">{{ insights.most_stable_pairs.most_stable.winrate }}%</span>
                | Avg Profit: <span class="text-green-400">{{ formatCurrency(insights.most_stable_pairs.most_stable.avg_profit) }}</span>
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
            <div
              v-for="pair in insights.most_stable_pairs?.pairs || []"
              :key="pair.pair"
              class="bg-dark-800 rounded-lg p-4"
            >
              <div class="flex items-center justify-between mb-2">
                <p class="font-semibold text-dark-100">{{ pair.pair }}</p>
                <span class="text-xs px-2 py-1 rounded bg-primary-500/20 text-primary-400">
                  {{ pair.stability_score }}%
                </span>
              </div>
              <div class="space-y-1 text-sm">
                <p class="text-dark-400">
                  Total Trades: <span class="text-dark-100">{{ pair.total_trades }}</span>
                </p>
                <p class="text-dark-400">
                  Winrate: <span class="text-green-400">{{ pair.winrate }}%</span>
                </p>
                <p class="text-dark-400">
                  Avg Profit: <span :class="pair.avg_profit >= 0 ? 'text-green-400' : 'text-red-400'">
                    {{ formatCurrency(pair.avg_profit) }}
                  </span>
                </p>
                <p class="text-dark-400">
                  StdDev: <span class="text-dark-300">{{ formatCurrency(pair.stddev_profit) }}</span>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- 4. Win Rate Berdasarkan Jam -->
        <div class="bg-dark-900 border border-dark-800 rounded-xl p-6">
          <h2 class="text-xl font-semibold text-dark-100 mb-4 flex items-center gap-2">
            <PercentBadgeIcon class="w-6 h-6 text-primary-500" />
            Win Rate Berdasarkan Jam
          </h2>
          
          <div v-if="insights.winrate_by_hour?.best_hour" class="mb-4 grid grid-cols-2 gap-4">
            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
              <p class="text-sm text-dark-400 mb-1">Jam dengan Winrate Tertinggi</p>
              <p class="text-2xl font-bold text-green-400">{{ formatHour(insights.winrate_by_hour.best_hour.hour) }}</p>
              <p class="text-sm text-dark-300 mt-1">
                Winrate: <span class="text-green-400">{{ insights.winrate_by_hour.best_hour.winrate }}%</span>
                | Trades: <span class="text-dark-300">{{ insights.winrate_by_hour.best_hour.total_trades }}</span>
              </p>
            </div>
            <div v-if="insights.winrate_by_hour.worst_hour" class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
              <p class="text-sm text-dark-400 mb-1">Jam dengan Winrate Terendah</p>
              <p class="text-2xl font-bold text-red-400">{{ formatHour(insights.winrate_by_hour.worst_hour.hour) }}</p>
              <p class="text-sm text-dark-300 mt-1">
                Winrate: <span class="text-red-400">{{ insights.winrate_by_hour.worst_hour.winrate }}%</span>
                | Trades: <span class="text-dark-300">{{ insights.winrate_by_hour.worst_hour.total_trades }}</span>
              </p>
            </div>
          </div>

          <div class="grid grid-cols-12 gap-1 mt-4">
            <div
              v-for="hour in insights.winrate_by_hour?.hours || []"
              :key="hour.hour"
              class="bg-dark-800 rounded p-2 text-center"
            >
              <p class="text-xs text-dark-400">{{ formatHour(hour.hour) }}</p>
              <p :class="['text-sm font-bold', hour.winrate >= 50 ? 'text-green-400' : 'text-red-400']">
                {{ hour.winrate }}%
              </p>
              <p class="text-xs text-dark-500">{{ hour.total_trades }}t</p>
            </div>
          </div>
        </div>

        <!-- 5. Pattern Pembalikan Paling Sering -->
        <div class="bg-dark-900 border border-dark-800 rounded-xl p-6">
          <h2 class="text-xl font-semibold text-dark-100 mb-4 flex items-center gap-2">
            <ArrowsRightLeftIcon class="w-6 h-6 text-primary-500" />
            Pattern Pembalikan Paling Sering
          </h2>
          
          <div v-if="insights.reversal_patterns?.most_common" class="mb-4">
            <div class="bg-dark-800 rounded-lg p-4">
              <p class="text-sm text-dark-400 mb-1">Pattern Paling Sering</p>
              <p class="text-2xl font-bold text-primary-400">{{ insights.reversal_patterns.most_common.pattern }}</p>
              <p class="text-sm text-dark-300 mt-1">
                Frekuensi: <span class="text-primary-400">{{ insights.reversal_patterns.most_common.count }}x</span>
                | Persentase: <span class="text-primary-400">{{ insights.reversal_patterns.most_common.frequency }}%</span>
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <div
              v-for="pattern in insights.reversal_patterns?.patterns || []"
              :key="pattern.pattern"
              class="bg-dark-800 rounded-lg p-4"
            >
              <p class="font-semibold text-dark-100 mb-2">{{ pattern.pattern }}</p>
              <p class="text-2xl font-bold text-primary-400 mb-1">{{ pattern.count }}</p>
              <p class="text-sm text-dark-400">Frekuensi: {{ pattern.frequency }}%</p>
            </div>
          </div>
        </div>

        <!-- 6. Impact News yang Paling Sering Merugikan EA -->
        <div class="bg-dark-900 border border-dark-800 rounded-xl p-6">
          <h2 class="text-xl font-semibold text-dark-100 mb-4 flex items-center gap-2">
            <NewspaperIcon class="w-6 h-6 text-primary-500" />
            Impact News yang Paling Sering Merugikan EA
          </h2>
          
          <div v-if="insights.news_impact?.most_dangerous_currency" class="mb-4">
            <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4">
              <p class="text-sm text-dark-400 mb-1">Currency Paling Berbahaya</p>
              <p class="text-2xl font-bold text-red-400">{{ insights.news_impact.most_dangerous_currency.currency }}</p>
              <p class="text-sm text-dark-300 mt-1">
                Total Event: <span class="text-red-400">{{ insights.news_impact.most_dangerous_currency.count }}x</span>
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div
              v-for="impact in insights.news_impact?.by_impact || []"
              :key="`${impact.impact}-${impact.currency}`"
              class="bg-dark-800 rounded-lg p-4"
            >
              <div class="flex items-center justify-between mb-2">
                <span :class="getImpactClass(impact.impact)" class="px-2 py-1 rounded text-xs font-medium">
                  {{ impact.impact.toUpperCase() }}
                </span>
                <span class="text-xs text-dark-400">{{ impact.currency }}</span>
              </div>
              <p class="text-2xl font-bold text-red-400 mb-1">{{ impact.total_count }}</p>
              <p class="text-sm text-dark-400">Persentase: {{ impact.percentage }}%</p>
              <p class="text-xs text-dark-500 mt-1">Disable EA: {{ impact.disable_ea_count }}x</p>
            </div>
          </div>
        </div>

        <!-- 7. Grafik Profit Berdasarkan Sesi (Tokyo/London/NY) -->
        <div class="bg-dark-900 border border-dark-800 rounded-xl p-6">
          <h2 class="text-xl font-semibold text-dark-100 mb-4 flex items-center gap-2">
            <GlobeAltIcon class="w-6 h-6 text-primary-500" />
            Profit Berdasarkan Sesi Trading
          </h2>
          
          <div v-if="insights.profit_by_session?.best_session" class="mb-4">
            <div class="bg-dark-800 rounded-lg p-4">
              <p class="text-sm text-dark-400 mb-1">Sesi Terbaik</p>
              <p class="text-2xl font-bold text-green-400">{{ insights.profit_by_session.best_session.session_name }}</p>
              <p class="text-sm text-dark-300 mt-1">
                Profit: <span class="text-green-400">{{ formatCurrency(insights.profit_by_session.best_session.total_profit) }}</span>
                | Winrate: <span class="text-green-400">{{ insights.profit_by_session.best_session.winrate }}%</span>
                | Trades: <span class="text-dark-300">{{ insights.profit_by_session.best_session.total_trades }}</span>
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
            <div
              v-for="session in insights.profit_by_session?.sessions || []"
              :key="session.session"
              :class="[
                'rounded-lg p-4',
                session.total_profit >= 0 ? 'bg-green-500/10 border border-green-500/30' : 'bg-red-500/10 border border-red-500/30'
              ]"
            >
              <p class="text-sm text-dark-400 mb-1">{{ session.session_name }}</p>
              <p :class="['text-2xl font-bold mb-1', session.total_profit >= 0 ? 'text-green-400' : 'text-red-400']">
                {{ formatCurrency(session.total_profit) }}
              </p>
              <div class="space-y-1 text-sm">
                <p class="text-dark-400">
                  Trades: <span class="text-dark-100">{{ session.total_trades }}</span>
                </p>
                <p class="text-dark-400">
                  Winrate: <span :class="session.winrate >= 50 ? 'text-green-400' : 'text-red-400'">
                    {{ session.winrate }}%
                  </span>
                </p>
                <p class="text-dark-400">
                  Avg Profit: <span :class="session.avg_profit >= 0 ? 'text-green-400' : 'text-red-400'">
                    {{ formatCurrency(session.avg_profit) }}
                  </span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { insightsAPI } from '@/services/api'
import {
  CalendarDaysIcon,
  ClockIcon,
  ChartBarIcon,
  PercentBadgeIcon,
  ArrowsRightLeftIcon,
  NewspaperIcon,
  GlobeAltIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const loading = ref(false)
const error = ref(null)
const insights = ref(null)
const selectedDays = ref(90)

const loadInsights = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await insightsAPI.getAll({ days: selectedDays.value })
    insights.value = response.data.data
  } catch (err) {
    error.value = err.response?.data?.message || 'Gagal memuat insights'
    console.error('Error loading insights:', err)
  } finally {
    loading.value = false
  }
}

const formatCurrency = (value) => {
  if (value === null || value === undefined) return '$0.00'
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(value)
}

const formatHour = (hour) => {
  return `${hour.toString().padStart(2, '0')}:00`
}

const getImpactClass = (impact) => {
  return impact === 'high' ? 'bg-red-500/20 text-red-400' :
         impact === 'medium' ? 'bg-yellow-500/20 text-yellow-400' :
         'bg-green-500/20 text-green-400'
}

onMounted(() => {
  loadInsights()
})
</script>

