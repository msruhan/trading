<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '@/stores/dashboard'
import StatCard from '@/components/dashboard/StatCard.vue'
import EquityChart from '@/components/charts/EquityChart.vue'
import DailyPnLChart from '@/components/charts/DailyPnLChart.vue'
import TradeTable from '@/components/common/TradeTable.vue'
import api from '@/services/api'
import {
  CurrencyDollarIcon,
  ChartBarIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  CalendarDaysIcon,
  PercentBadgeIcon,
  ArrowPathIcon,
  PlusIcon,
  DocumentArrowDownIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const dashboardStore = useDashboardStore()

const selectedDays = ref(30)

// Prediction states
const loadingPredictions = ref(false)
const forexfactoryPredictions = ref([])
const forexfactoryDayPrediction = ref(null)
const forexfactorySafeWindows = ref([])
const historicalPredictions = ref([])
const historicalDayPrediction = ref(null)
const historicalSafeWindows = ref([])
const selectedDate = ref(new Date().toISOString().split('T')[0])

// Prediction 3: Technical Analysis
const technicalAnalysis = ref(null)
const loadingTechnical = ref(false)
const technicalError = ref(null)

// Helper functions
const getCurrencyClass = (currency) => {
  const colors = {
    'USD': 'bg-blue-500/20 text-blue-400',
    'EUR': 'bg-purple-500/20 text-purple-400',
    'GBP': 'bg-red-500/20 text-red-400',
    'JPY': 'bg-pink-500/20 text-pink-400',
    'AUD': 'bg-green-500/20 text-green-400',
    'CAD': 'bg-yellow-500/20 text-yellow-400',
    'CHF': 'bg-orange-500/20 text-orange-400',
    'NZD': 'bg-cyan-500/20 text-cyan-400',
  }
  return colors[currency] || 'bg-dark-700 text-dark-300'
}

const getImpactClass = (impact) => {
  return impact === 'high' ? 'bg-red-500/20 text-red-400' :
         impact === 'medium' ? 'bg-yellow-500/20 text-yellow-400' :
         'bg-green-500/20 text-green-400'
}

const getPredictionClass = (status) => {
  return status === 'danger' ? 'text-red-400' :
         status === 'caution' ? 'text-yellow-400' :
         'text-green-400'
}

const fetchPredictions = async () => {
  loadingPredictions.value = true
  try {
    const response = await api.get('/news/predictions', {
      params: { date: selectedDate.value }
    })
    
    // Prediction 1: ForexFactory Impact Based
    if (response.data.prediction_1_forexfactory) {
      forexfactoryPredictions.value = response.data.prediction_1_forexfactory.predictions || []
      forexfactoryDayPrediction.value = response.data.prediction_1_forexfactory.day_prediction || null
      forexfactorySafeWindows.value = response.data.prediction_1_forexfactory.safe_windows || []
    }
    
    // Prediction 2: Historical Marking Based
    if (response.data.prediction_2_historical) {
      historicalPredictions.value = response.data.prediction_2_historical.predictions || []
      historicalDayPrediction.value = response.data.prediction_2_historical.day_prediction || null
      historicalSafeWindows.value = response.data.prediction_2_historical.safe_windows || []
    }
  } catch (error) {
    console.error('Failed to fetch predictions:', error)
  } finally {
    loadingPredictions.value = false
  }
}

const fetchTechnicalAnalysis = async (ohlcData = null) => {
  loadingTechnical.value = true
  technicalError.value = null
  
  try {
    console.log('Fetching technical analysis from dashboard...')
    
    // If no OHLC data provided, generate sample data
    if (!ohlcData) {
      ohlcData = getOHLCFromTradingView()
    }
    
    // Validate OHLC data structure
    const hasM15 = ohlcData && (ohlcData['15m'] || ohlcData.M15) && (ohlcData['15m']?.length > 0 || ohlcData.M15?.length > 0)
    const hasH1 = ohlcData && ohlcData.H1 && ohlcData.H1.length > 0
    const hasH4 = ohlcData && ohlcData.H4 && ohlcData.H4.length > 0
    
    if (!hasM15 || !hasH1 || !hasH4) {
      console.warn('OHLC data incomplete, using sample data')
      ohlcData = getOHLCFromTradingView() // Force sample data
    }
    
    console.log('Sending OHLC data to backend:', ohlcData)
    const response = await api.post('/news/technical-analysis', {
      ohlc: ohlcData,
      symbol: 'XAUUSD',
    })
    
    console.log('Technical analysis response:', response.data)
    if (response.data && response.data.prediction_3_technical) {
      technicalAnalysis.value = response.data.prediction_3_technical
    } else {
      technicalError.value = 'Format response tidak valid dari server'
    }
  } catch (error) {
    console.error('Failed to fetch technical analysis:', error)
    technicalError.value = error.response?.data?.message || error.message || 'Gagal mengambil analisis teknikal'
  } finally {
    loadingTechnical.value = false
  }
}

// Generate sample OHLC data for testing
const getOHLCFromTradingView = () => {
  const basePrice = 2650.0 // XAUUSD base price
  const now = Date.now()
  const oneHour = 60 * 60 * 1000
  const oneMinute = 60 * 1000
  
  // Generate M15 data (last 50 periods)
  const m15Data = []
  for (let i = 50; i >= 0; i--) {
    const time = now - (i * oneMinute * 15)
    const open = basePrice + (Math.random() - 0.5) * 8
    const high = open + Math.random() * 3
    const low = open - Math.random() * 3
    const close = open + (Math.random() - 0.5) * 5
    m15Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  // Generate H1 data (last 50 hours)
  const h1Data = []
  for (let i = 50; i >= 0; i--) {
    const time = now - (i * oneHour)
    const open = basePrice + (Math.random() - 0.5) * 10
    const high = open + Math.random() * 5
    const low = open - Math.random() * 5
    const close = open + (Math.random() - 0.5) * 8
    h1Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  // Generate H4 data (last 30 periods)
  const h4Data = []
  for (let i = 30; i >= 0; i--) {
    const time = now - (i * oneHour * 4)
    const open = basePrice + (Math.random() - 0.5) * 15
    const high = open + Math.random() * 8
    const low = open - Math.random() * 8
    const close = open + (Math.random() - 0.5) * 12
    h4Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  return {
    '1m': [],
    '5m': [],
    '15m': m15Data,
    'H1': h1Data,
    'H4': h4Data,
  }
}

onMounted(async () => {
  await dashboardStore.fetchDashboard(selectedDays.value)
  await fetchPredictions()
  await fetchTechnicalAnalysis()
})

const handleDaysChange = async (days) => {
  selectedDays.value = days
  await dashboardStore.fetchDashboard(days)
}

const refreshData = async () => {
  await Promise.all([
    dashboardStore.fetchDashboard(selectedDays.value),
    fetchPredictions(),
    fetchTechnicalAnalysis()
  ])
}

const floatingPLType = computed(() => {
  const pl = dashboardStore.floatingPL
  if (pl > 0) return 'profit'
  if (pl < 0) return 'loss'
  return 'neutral'
})

const dailyPLType = computed(() => {
  const pl = dashboardStore.dailyProfit
  if (pl > 0) return 'profit'
  if (pl < 0) return 'loss'
  return 'neutral'
})

const monthlyPLType = computed(() => {
  const pl = dashboardStore.monthlyProfit
  if (pl > 0) return 'profit'
  if (pl < 0) return 'loss'
  return 'neutral'
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header with actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-100">Dashboard</h1>
        <p class="text-dark-400">Your trading performance at a glance</p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Time range selector -->
        <div class="flex items-center rounded-lg bg-dark-800 p-1">
          <button
            v-for="days in [7, 14, 30, 90]"
            :key="days"
            :class="[
              'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
              selectedDays === days
                ? 'bg-primary-500/20 text-primary-400'
                : 'text-dark-400 hover:text-dark-300'
            ]"
            @click="handleDaysChange(days)"
          >
            {{ days }}D
          </button>
        </div>

        <!-- Quick actions -->
        <button
          class="btn-secondary flex items-center gap-2"
          @click="refreshData"
        >
          <ArrowPathIcon class="w-4 h-4" />
          <span class="hidden sm:inline">Sync</span>
        </button>

        <button
          class="btn-primary flex items-center gap-2"
          @click="router.push('/trades/new')"
        >
          <PlusIcon class="w-4 h-4" />
          <span class="hidden sm:inline">Add Trade</span>
        </button>
      </div>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
      <StatCard
        label="Balance"
        :value="dashboardStore.balance"
        prefix="$"
        :icon="CurrencyDollarIcon"
        :loading="dashboardStore.loading"
      />
      <StatCard
        label="Equity"
        :value="dashboardStore.equity"
        prefix="$"
        :icon="ChartBarIcon"
        :loading="dashboardStore.loading"
      />
      <StatCard
        label="Floating P/L"
        :value="dashboardStore.floatingPL"
        prefix="$"
        :type="floatingPLType"
        :icon="ArrowTrendingUpIcon"
        :loading="dashboardStore.loading"
      />
      <StatCard
        label="Daily P/L"
        :value="dashboardStore.dailyProfit"
        prefix="$"
        :type="dailyPLType"
        :icon="CalendarDaysIcon"
        :loading="dashboardStore.loading"
      />
      <StatCard
        label="Monthly P/L"
        :value="dashboardStore.monthlyProfit"
        prefix="$"
        :type="monthlyPLType"
        :icon="ArrowTrendingDownIcon"
        :loading="dashboardStore.loading"
      />
      <StatCard
        label="Win Rate"
        :value="dashboardStore.winrate"
        suffix="%"
        :icon="PercentBadgeIcon"
        :loading="dashboardStore.loading"
      />
    </div>

    <!-- EA Predictions Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <!-- PREDICTION 1: ForexFactory Impact Based -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2.5">
            <span class="text-xl">📊</span>
            <div>
              <h2 class="text-base font-semibold">#1 Prediction</h2>
              <p class="text-xs text-dark-400">ForexFactory Impact</p>
            </div>
          </div>
          <div class="flex items-center gap-1.5">
            <router-link 
              to="/news"
              class="text-xs text-primary-400 hover:text-primary-300"
            >
              Detail →
            </router-link>
            <button 
              @click="fetchPredictions"
              :disabled="loadingPredictions"
              class="flex items-center gap-1.5 px-2.5 py-1 text-xs bg-blue-600/20 text-blue-400 border border-blue-500/30 rounded-lg hover:bg-blue-600/30 transition-colors disabled:opacity-50"
            >
              <svg :class="['w-3.5 h-3.5', loadingPredictions && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              {{ loadingPredictions ? '...' : '↻' }}
            </button>
          </div>
        </div>

        <!-- Day Prediction Summary -->
        <div v-if="forexfactoryDayPrediction" :class="[
          'p-3.5 rounded-lg mb-3 border',
          forexfactoryDayPrediction.status === 'danger' ? 'bg-red-500/10 border-red-500/30' :
          forexfactoryDayPrediction.status === 'caution' ? 'bg-yellow-500/10 border-yellow-500/30' :
          'bg-green-500/10 border-green-500/30'
        ]">
          <div class="flex items-center justify-between mb-2">
            <span :class="[
              'text-lg font-bold',
              getPredictionClass(forexfactoryDayPrediction.status)
            ]">
              {{ forexfactoryDayPrediction.status === 'danger' ? '🔴 DANGER' : forexfactoryDayPrediction.status === 'caution' ? '🟡 CAUTION' : '🟢 SAFE' }}
            </span>
            <span class="text-xs text-dark-500">{{ selectedDate }}</span>
          </div>
          <p class="text-xs text-dark-300 mb-2.5 line-clamp-2">{{ forexfactoryDayPrediction.message }}</p>
          
          <!-- Stats Row -->
          <div class="flex flex-wrap gap-1.5 text-xs">
            <span class="px-2 py-0.5 bg-dark-800 rounded">
              Events: <span class="text-white font-medium">{{ forexfactoryDayPrediction.total_events }}</span>
            </span>
            <span class="px-2 py-0.5 bg-red-500/10 text-red-400 rounded">
              🔴 {{ forexfactoryDayPrediction.danger_count }}
            </span>
            <span class="px-2 py-0.5 bg-yellow-500/10 text-yellow-400 rounded">
              🟡 {{ forexfactoryDayPrediction.caution_count }}
            </span>
            <span class="px-2 py-0.5 bg-orange-500/10 text-orange-400 rounded">
              🔥 {{ forexfactoryDayPrediction.high_impact_count }}
            </span>
          </div>

          <!-- Schedule Recommendations (Compact) -->
          <div v-if="forexfactoryDayPrediction.schedule_recommendation?.length > 0" class="mt-2.5">
            <div class="space-y-1">
              <div 
                v-for="(rec, index) in forexfactoryDayPrediction.schedule_recommendation.slice(0, 1)" 
                :key="index"
                :class="[
                  'p-1.5 rounded border text-xs',
                  rec.type === 'safe' ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30'
                ]"
              >
                <span :class="[
                  'font-mono font-bold text-xs',
                  rec.type === 'safe' ? 'text-green-400' : 'text-red-400'
                ]">{{ rec.time_range }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Safe Windows -->
        <div v-if="forexfactorySafeWindows.length > 0 && !loadingPredictions" class="p-2.5 bg-green-500/5 border border-green-500/20 rounded-lg">
          <h4 class="text-xs font-semibold text-green-400 mb-1">🟢 Window Aman:</h4>
          <div class="flex flex-wrap gap-1">
            <span 
              v-for="(window, index) in forexfactorySafeWindows.slice(0, 2)" 
              :key="index"
              class="px-1.5 py-0.5 bg-green-500/20 text-green-300 rounded font-mono text-xs"
            >
              {{ window.start }}-{{ window.end }}
            </span>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!loadingPredictions && !forexfactoryDayPrediction" class="text-center py-4 text-dark-400">
          <span class="text-2xl mb-1 block">📊</span>
          <p class="text-xs">Tidak ada prediksi</p>
        </div>

        <!-- Loading State -->
        <div v-if="loadingPredictions" class="flex items-center justify-center py-4">
          <div class="spinner mr-2"></div>
          <span class="text-xs text-dark-400">Loading...</span>
        </div>
      </div>

      <!-- PREDICTION 2: Historical Marking Based -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2.5">
            <span class="text-xl">📈</span>
            <div>
              <h2 class="text-base font-semibold">#2 Prediction</h2>
              <p class="text-xs text-dark-400">Historical Data</p>
            </div>
          </div>
          <router-link 
            to="/news"
            class="text-xs text-primary-400 hover:text-primary-300"
          >
            Detail →
          </router-link>
        </div>

        <!-- Day Prediction Summary -->
        <div v-if="historicalDayPrediction" :class="[
          'p-3.5 rounded-lg mb-3 border',
          historicalDayPrediction.status === 'danger' ? 'bg-red-500/10 border-red-500/30' :
          historicalDayPrediction.status === 'caution' ? 'bg-yellow-500/10 border-yellow-500/30' :
          'bg-green-500/10 border-green-500/30'
        ]">
          <div class="flex items-center justify-between mb-2">
            <span :class="[
              'text-lg font-bold',
              getPredictionClass(historicalDayPrediction.status)
            ]">
              {{ historicalDayPrediction.status === 'danger' ? '🔴 DANGER' : historicalDayPrediction.status === 'caution' ? '🟡 CAUTION' : '🟢 SAFE' }}
            </span>
            <span class="text-xs text-dark-500">{{ selectedDate }}</span>
          </div>
          <p class="text-xs text-dark-300 mb-2.5 line-clamp-2">{{ historicalDayPrediction.message }}</p>
          
          <!-- Stats Row -->
          <div class="flex flex-wrap gap-1.5 text-xs">
            <span class="px-2 py-0.5 bg-dark-800 rounded">
              Events: <span class="text-white font-medium">{{ historicalDayPrediction.total_events }}</span>
            </span>
            <span class="px-2 py-0.5 bg-red-500/10 text-red-400 rounded">
              🔴 {{ historicalDayPrediction.danger_count }}
            </span>
            <span class="px-2 py-0.5 bg-yellow-500/10 text-yellow-400 rounded">
              🟡 {{ historicalDayPrediction.caution_count }}
            </span>
            <span v-if="historicalDayPrediction.avg_confidence" class="px-2 py-0.5 bg-purple-500/10 text-purple-400 rounded">
              📊 {{ historicalDayPrediction.avg_confidence }}%
            </span>
          </div>

          <!-- Schedule Recommendations (Compact) -->
          <div v-if="historicalDayPrediction.schedule_recommendation?.length > 0" class="mt-2.5">
            <div class="space-y-1">
              <div 
                v-for="(rec, index) in historicalDayPrediction.schedule_recommendation.slice(0, 1)" 
                :key="index"
                :class="[
                  'p-1.5 rounded border text-xs',
                  rec.type === 'safe' ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30'
                ]"
              >
                <span :class="[
                  'font-mono font-bold text-xs',
                  rec.type === 'safe' ? 'text-green-400' : 'text-red-400'
                ]">{{ rec.time_range }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Safe Windows -->
        <div v-if="historicalSafeWindows.length > 0 && !loadingPredictions" class="p-2.5 bg-green-500/5 border border-green-500/20 rounded-lg">
          <h4 class="text-xs font-semibold text-green-400 mb-1">🟢 Window Aman:</h4>
          <div class="flex flex-wrap gap-1">
            <span 
              v-for="(window, index) in historicalSafeWindows.slice(0, 2)" 
              :key="index"
              class="px-1.5 py-0.5 bg-green-500/20 text-green-300 rounded font-mono text-xs"
            >
              {{ window.start }}-{{ window.end }}
            </span>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="!loadingPredictions && !historicalDayPrediction" class="text-center py-4 text-dark-400">
          <span class="text-2xl mb-1 block">📈</span>
          <p class="text-xs">Tidak ada prediksi</p>
        </div>

        <!-- Loading State -->
        <div v-if="loadingPredictions" class="flex items-center justify-center py-4">
          <div class="spinner mr-2"></div>
          <span class="text-xs text-dark-400">Loading...</span>
        </div>
      </div>

      <!-- PREDICTION 3: Technical Analysis Based -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2.5">
            <span class="text-xl">📊</span>
            <div>
              <h2 class="text-base font-semibold">#3 Prediction</h2>
              <p class="text-xs text-dark-400">Technical Analysis</p>
            </div>
          </div>
          <div class="flex items-center gap-1.5">
            <router-link 
              to="/news"
              class="text-xs text-primary-400 hover:text-primary-300"
            >
              Detail →
            </router-link>
            <button 
              @click="fetchTechnicalAnalysis()"
              :disabled="loadingTechnical"
              class="flex items-center gap-1.5 px-2.5 py-1 text-xs bg-orange-600/20 text-orange-400 border border-orange-500/30 rounded-lg hover:bg-orange-600/30 transition-colors disabled:opacity-50"
            >
              <svg :class="['w-3.5 h-3.5', loadingTechnical && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              {{ loadingTechnical ? '...' : '⚡' }}
            </button>
          </div>
        </div>

        <!-- Combined Overall Summary -->
        <div v-if="technicalAnalysis?.combined_overall" :class="[
          'p-3.5 rounded-lg mb-3 border',
          technicalAnalysis.combined_overall.status === 'danger' ? 'bg-red-500/10 border-red-500/30' :
          technicalAnalysis.combined_overall.status === 'caution' ? 'bg-yellow-500/10 border-yellow-500/30' :
          'bg-green-500/10 border-green-500/30'
        ]">
          <div class="flex items-center justify-between mb-2">
            <span :class="[
              'text-lg font-bold',
              getPredictionClass(technicalAnalysis.combined_overall.status)
            ]">
              {{ technicalAnalysis.combined_overall.status === 'danger' ? '🔴 DANGER' : 
                 technicalAnalysis.combined_overall.status === 'caution' ? '🟡 CAUTION' : '🟢 SAFE' }}
            </span>
            <div class="text-right">
              <div class="text-xl font-bold" :class="[
                getPredictionClass(technicalAnalysis.combined_overall.status)
              ]">
                {{ technicalAnalysis.combined_overall.risk_score }}/100
              </div>
              <div class="text-xs text-dark-400">Risk</div>
            </div>
          </div>
          <p class="text-xs text-dark-300 mb-2.5 line-clamp-2">{{ technicalAnalysis.combined_overall.recommendation }}</p>
          
          <!-- Stats Row -->
          <div class="flex flex-wrap gap-1.5 text-xs mb-2.5">
            <span class="px-2 py-0.5 bg-dark-800 rounded">
              TF: <span class="text-white font-medium">3</span>
            </span>
            <span class="px-2 py-0.5 bg-red-500/10 text-red-400 rounded">
              🔴 {{ technicalAnalysis.combined_overall.timeframe_summary?.danger || 0 }}
            </span>
            <span class="px-2 py-0.5 bg-yellow-500/10 text-yellow-400 rounded">
              🟡 {{ technicalAnalysis.combined_overall.timeframe_summary?.caution || 0 }}
            </span>
            <span class="px-2 py-0.5 bg-green-500/10 text-green-400 rounded">
              🟢 {{ technicalAnalysis.combined_overall.timeframe_summary?.safe || 0 }}
            </span>
          </div>

          <!-- Timeframe Breakdown -->
          <div v-if="technicalAnalysis?.predictions" class="grid grid-cols-3 gap-1.5 mt-2.5">
            <div v-if="technicalAnalysis.predictions.M15" :class="[
              'p-1.5 rounded border text-xs text-center',
              technicalAnalysis.predictions.M15.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
              technicalAnalysis.predictions.M15.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
              'bg-green-500/5 border-green-500/20'
            ]">
              <div class="font-bold text-xs mb-0.5">M15</div>
              <div :class="[
                'text-xs font-bold',
                getPredictionClass(technicalAnalysis.predictions.M15.overall.status)
              ]">
                {{ technicalAnalysis.predictions.M15.overall.risk_score }}
              </div>
            </div>
            <div v-if="technicalAnalysis.predictions.H1" :class="[
              'p-1.5 rounded border text-xs text-center',
              technicalAnalysis.predictions.H1.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
              technicalAnalysis.predictions.H1.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
              'bg-green-500/5 border-green-500/20'
            ]">
              <div class="font-bold text-xs mb-0.5">H1</div>
              <div :class="[
                'text-xs font-bold',
                getPredictionClass(technicalAnalysis.predictions.H1.overall.status)
              ]">
                {{ technicalAnalysis.predictions.H1.overall.risk_score }}
              </div>
            </div>
            <div v-if="technicalAnalysis.predictions.H4" :class="[
              'p-1.5 rounded border text-xs text-center',
              technicalAnalysis.predictions.H4.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
              technicalAnalysis.predictions.H4.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
              'bg-green-500/5 border-green-500/20'
            ]">
              <div class="font-bold text-xs mb-0.5">H4</div>
              <div :class="[
                'text-xs font-bold',
                getPredictionClass(technicalAnalysis.predictions.H4.overall.status)
              ]">
                {{ technicalAnalysis.predictions.H4.overall.risk_score }}
              </div>
            </div>
          </div>
        </div>

        <!-- Error State -->
        <div v-if="technicalError" class="p-2.5 bg-red-500/10 border border-red-500/30 rounded-lg mb-3">
          <p class="text-xs text-red-400">{{ technicalError }}</p>
        </div>

        <!-- Loading State -->
        <div v-if="loadingTechnical" class="flex items-center justify-center py-4">
          <div class="spinner mr-2"></div>
          <span class="text-xs text-dark-400">Analyzing...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="!loadingTechnical && !technicalAnalysis?.combined_overall && !technicalError" class="text-center py-4 text-dark-400">
          <span class="text-2xl mb-1 block">📊</span>
          <p class="text-xs">Klik "⚡" untuk analisis</p>
        </div>
      </div>
    </div>

    <!-- Charts row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <EquityChart
        :data="dashboardStore.equityCurve"
        :loading="dashboardStore.loading"
      />
      <DailyPnLChart
        :data="dashboardStore.dailyPnL"
        :loading="dashboardStore.loading"
      />
    </div>

    <!-- Bottom row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Today's trades -->
      <div class="lg:col-span-2 card p-4">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-medium text-dark-300">Recent Trades</h3>
          <router-link to="/trades" class="text-xs text-primary-400 hover:text-primary-300">
            View all →
          </router-link>
        </div>
        <TradeTable
          :trades="dashboardStore.todaysTrades"
          :loading="dashboardStore.loading"
          compact
        />
      </div>

      <!-- Stats by pair -->
      <div class="card p-4">
        <h3 class="text-sm font-medium text-dark-300 mb-4">Performance by Pair</h3>
        <div class="space-y-3">
          <div
            v-for="stat in dashboardStore.statsByPair"
            :key="stat.pair"
            class="flex items-center justify-between py-2 border-b border-dark-800 last:border-0"
          >
            <div>
              <span class="font-medium text-dark-100">{{ stat.pair }}</span>
              <span class="ml-2 text-xs text-dark-500">{{ stat.trades }} trades</span>
            </div>
            <div class="text-right">
              <span :class="[
                'font-mono font-medium',
                stat.profit >= 0 ? 'profit' : 'loss'
              ]">
                {{ stat.profit >= 0 ? '+' : '' }}${{ stat.profit.toFixed(2) }}
              </span>
              <span class="ml-2 text-xs text-dark-500">{{ stat.winrate }}%</span>
            </div>
          </div>
          <div v-if="dashboardStore.statsByPair.length === 0" class="text-center text-dark-500 py-4">
            No trading data
          </div>
        </div>
      </div>
    </div>

    <!-- News widget -->
    <div class="card p-4" v-if="dashboardStore.news.length > 0">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-medium text-dark-300">Today's News</h3>
        <router-link to="/calendar" class="text-xs text-primary-400 hover:text-primary-300">
          View calendar →
        </router-link>
      </div>
      <div class="space-y-3">
        <div
          v-for="item in dashboardStore.news"
          :key="item.id"
          class="flex items-start gap-3 py-2 border-b border-dark-800 last:border-0"
        >
          <span :class="[
            'px-2 py-0.5 text-xs font-medium rounded',
            item.impact === 'high' ? 'bg-red-500/20 text-red-400' :
            item.impact === 'medium' ? 'bg-yellow-500/20 text-yellow-400' :
            'bg-green-500/20 text-green-400'
          ]">
            {{ item.currency }}
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-sm text-dark-200 truncate">{{ item.title }}</p>
            <p v-if="item.time" class="text-xs text-dark-500">{{ item.time }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

