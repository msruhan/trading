<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '@/stores/dashboard'
import StatCard from '@/components/dashboard/StatCard.vue'
import EquityChart from '@/components/charts/EquityChart.vue'
import DailyPnLChart from '@/components/charts/DailyPnLChart.vue'
import TradeTable from '@/components/common/TradeTable.vue'
import TodayAnalysisModal from '@/components/dashboard/TodayAnalysisModal.vue'
import api, { msiAPI, todayAnalysisAPI } from '@/services/api'
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
  LightBulbIcon,
  XMarkIcon,
  CheckCircleIcon,
  XCircleIcon,
  InformationCircleIcon,
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
// Get today's date in YYYY-MM-DD format, ensuring it uses the correct timezone
const getTodayDate = () => {
  const now = new Date()
  // Use local date to avoid timezone issues
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const selectedDate = ref(getTodayDate())

// Prediction 3: Technical Analysis
const technicalAnalysis = ref(null)
const loadingTechnical = ref(false)
const technicalError = ref(null)

// Prediction 4: Market Stability Index (MSI)
const msiData = ref(null)
const loadingMSI = ref(false)
const msiError = ref(null)

// Indicator info modal
const showIndicatorModal = ref(false)
const selectedIndicator = ref(null)

// Today Analysis
const todayAnalysis = ref(null)
const loadingTodayAnalysis = ref(false)
const todayAnalysisError = ref(null)
const showTodayAnalysis = ref(false)

const runTodayAnalysis = async () => {
  loadingTodayAnalysis.value = true
  todayAnalysisError.value = null
  showTodayAnalysis.value = true
  
  try {
    const response = await todayAnalysisAPI.getAnalysis()
    todayAnalysis.value = response.data.data
  } catch (error) {
    todayAnalysisError.value = error.response?.data?.message || 'Gagal melakukan analisis hari ini'
    console.error('Today Analysis error:', error)
  } finally {
    loadingTodayAnalysis.value = false
  }
}

const handleViewNews = () => {
  showTodayAnalysis.value = false
  router.push('/news')
}

const handleViewChart = () => {
  showTodayAnalysis.value = false
  router.push('/chart')
}

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

const getMSIStatusClass = (status) => {
  return status === 'DANGER' ? 'text-red-400' :
         status === 'CAUTION' ? 'text-yellow-400' :
         'text-green-400'
}

const getMSIStatusBg = (status) => {
  return status === 'DANGER' ? 'bg-red-500/10 border-red-500/30' :
         status === 'CAUTION' ? 'bg-yellow-500/10 border-yellow-500/30' :
         'bg-green-500/10 border-green-500/30'
}

const fetchPredictions = async () => {
  loadingPredictions.value = true
  try {
    // Always use today's date for predictions
    const todayDate = getTodayDate()
    selectedDate.value = todayDate // Update selectedDate to match
    
    const response = await api.get('/news/predictions', {
      params: { date: todayDate }
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

// Fetch MSI (Market Stability Index)
const fetchMSI = async () => {
  loadingMSI.value = true
  msiError.value = null
  
  try {
    const response = await msiAPI.getLive()
    msiData.value = response.data
  } catch (error) {
    console.error('Failed to fetch MSI:', error)
    msiError.value = error.response?.data?.message || error.message || 'Gagal mengambil Market Stability Index'
  } finally {
    loadingMSI.value = false
  }
}

// Get indicator information based on scoring rules
const getIndicatorInfo = (indicator, value, score) => {
  const info = {
    name: '',
    description: '',
    currentValue: value,
    currentScore: score,
    category: '',
    safeRanges: [],
    dangerRanges: [],
    interpretation: ''
  }

  switch (indicator) {
    case 'dxy':
      info.name = 'DXY (Dollar Index)'
      info.description = 'Indeks Dolar AS mengukur kekuatan USD terhadap sekumpulan mata uang utama'
      info.category = score >= 70 ? '🟢 Sideways/Stabil' : score >= 50 ? '🟡 Moderate Movement' : '🔴 Strong Trend'
      info.safeRanges = [
        { range: '95-105', description: 'DXY dalam range normal, dekat tengah (100)', score: 80 },
        { range: '90-110', description: 'DXY dalam range normal', score: 50 }
      ]
      info.dangerRanges = [
        { range: '< 90 atau > 110', description: 'DXY di luar range normal, menunjukkan trend kuat', score: 20 }
      ]
      info.interpretation = score >= 70 
        ? 'DXY bergerak sideways, kondisi stabil untuk EA Grid'
        : score >= 50
        ? 'DXY menunjukkan pergerakan moderat, perlu perhatian'
        : 'DXY trending kuat, berbahaya untuk EA Grid'
      break

    case 'vix':
      info.name = 'VIX (Volatility Index)'
      info.description = 'Indeks volatilitas pasar saham AS (CBOE Volatility Index)'
      info.category = score >= 70 ? '🟢 Low Volatility' : score >= 50 ? '🟡 Moderate Volatility' : '🔴 High Volatility'
      info.safeRanges = [
        { range: '< 15', description: 'VIX rendah, volatilitas rendah, kondisi stabil', score: 90 },
        { range: '15-20', description: 'VIX sedang, volatilitas moderat', score: 70 }
      ]
      info.dangerRanges = [
        { range: '20-25', description: 'VIX tinggi, volatilitas tinggi', score: 40 },
        { range: '> 25', description: 'VIX sangat tinggi, volatilitas ekstrem', score: 10 }
      ]
      info.interpretation = score >= 70
        ? 'VIX rendah, pasar stabil, aman untuk EA Grid'
        : score >= 50
        ? 'VIX sedang, perlu perhatian'
        : 'VIX tinggi, pasar volatile, berbahaya untuk EA Grid'
      break

    case 'gvz':
      info.name = 'GVZ (Gold Volatility Index)'
      info.description = 'Indeks volatilitas emas, mengukur ekspektasi volatilitas harga emas'
      info.category = score >= 70 ? '🟢 Low Volatility' : score >= 50 ? '🟡 Moderate Volatility' : '🔴 High Volatility'
      info.safeRanges = [
        { range: '< 12', description: 'GVZ rendah, emas sangat stabil, sangat aman untuk EA Grid', score: 90 },
        { range: '12-15', description: 'GVZ sedang, emas stabil', score: 70 }
      ]
      info.dangerRanges = [
        { range: '15-18', description: 'GVZ tinggi, volatilitas emas meningkat', score: 45 },
        { range: '>= 18', description: 'GVZ sangat tinggi, emas rawan trending kuat', score: 20 }
      ]
      info.interpretation = score >= 70
        ? 'GVZ rendah, emas stabil, aman untuk EA Grid'
        : score >= 50
        ? 'GVZ sedang, perlu perhatian'
        : 'GVZ tinggi, emas volatile, berbahaya untuk EA Grid'
      break

    case 'yield':
      info.name = 'US 10-Year Treasury Yield'
      info.description = 'Imbal hasil obligasi pemerintah AS 10 tahun, indikator sentimen pasar'
      info.category = score >= 60 ? '🟢 Stable' : score >= 50 ? '🟡 Moderate' : '🔴 Volatile'
      info.safeRanges = [
        { range: '2.5-4.5%', description: 'Yield dalam range normal, dekat tengah (3.5%)', score: 70 },
        { range: '2.0-5.0%', description: 'Yield dalam range normal', score: 50 }
      ]
      info.dangerRanges = [
        { range: '< 2.0% atau > 5.0%', description: 'Yield di luar range normal, menunjukkan instabilitas', score: 20 }
      ]
      info.interpretation = score >= 60
        ? 'Yield stabil, kondisi pasar stabil'
        : score >= 50
        ? 'Yield menunjukkan pergerakan moderat'
        : 'Yield volatile, kondisi pasar tidak stabil'
      break

    case 'spx':
      info.name = 'S&P500 Futures (ES)'
      info.description = 'Futures S&P500, indikator sentimen pasar saham global'
      info.category = score >= 70 ? '🟢 Sideways' : score >= 50 ? '🟡 Stable Trend' : '🔴 Volatile'
      info.safeRanges = [
        { range: 'Sideways (< 0.5% change)', description: 'ES bergerak sideways, kondisi stabil', score: 80 },
        { range: 'Uptrend stabil (0.5-2%)', description: 'ES uptrend stabil, kondisi baik', score: 60 }
      ]
      info.dangerRanges = [
        { range: 'Downtrend stabil (-0.5% to -2%)', description: 'ES downtrend, perlu perhatian', score: 40 },
        { range: 'Volatile (> 2% atau < -2%)', description: 'ES volatile, pergerakan besar', score: 20 }
      ]
      info.interpretation = score >= 70
        ? 'ES sideways, kondisi stabil untuk EA Grid'
        : score >= 50
        ? 'ES menunjukkan trend stabil'
        : 'ES volatile, berbahaya untuk EA Grid'
      break

    case 'correlation':
      info.name = 'Cross-Pair Correlation'
      info.description = 'Korelasi rata-rata antara pasangan mata uang (XAUUSD, EURUSD, GBPUSD, USDJPY)'
      info.category = score >= 70 ? '🟢 Low Correlation' : score >= 50 ? '🟡 Moderate Correlation' : '🔴 High Correlation'
      info.safeRanges = [
        { range: '|corr| < 0.3', description: 'Korelasi rendah, pasar tidak seragam (CHOPPY), aman untuk EA grid', score: 90 },
        { range: '0.3-0.6', description: 'Korelasi sedang', score: 60 }
      ]
      info.dangerRanges = [
        { range: '|corr| > 0.6', description: 'Korelasi tinggi, pasar bergerak searah (TRENDING), berbahaya untuk EA grid', score: 30 }
      ]
      info.interpretation = score >= 70
        ? 'Korelasi rendah, pasar choppy, aman untuk EA Grid'
        : score >= 50
        ? 'Korelasi sedang, perlu perhatian'
        : 'Korelasi tinggi, pasar trending, berbahaya untuk EA Grid'
      break
  }

  return info
}

// Open indicator info modal
const openIndicatorInfo = (indicator, value, score) => {
  selectedIndicator.value = getIndicatorInfo(indicator, value, score)
  showIndicatorModal.value = true
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
  // Ensure selectedDate is set to today
  selectedDate.value = getTodayDate()
  
  await dashboardStore.fetchDashboard(selectedDays.value)
  await fetchPredictions()
  await fetchTechnicalAnalysis()
  await fetchMSI()
})

const handleDaysChange = async (days) => {
  selectedDays.value = days
  await dashboardStore.fetchDashboard(days)
}

const refreshData = async () => {
  // Ensure selectedDate is set to today when refreshing
  selectedDate.value = getTodayDate()
  
  await Promise.all([
    dashboardStore.fetchDashboard(selectedDays.value),
    fetchPredictions(),
    fetchTechnicalAnalysis(),
    fetchMSI()
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

// Computed: Technical Confidence & Override Logic (same as News.vue)
const technicalConfidence = computed(() => {
  if (!technicalAnalysis.value?.predictions) return null
  
  const allModules = []
  let insufficientDataCount = 0
  let dangerWithHighConfidence = false
  const dangerModules = [] // Track which modules triggered DANGER
  
  // Collect all modules from all timeframes
  Object.values(technicalAnalysis.value.predictions).forEach((timeframe, tfIndex) => {
    const timeframeName = Object.keys(technicalAnalysis.value.predictions)[tfIndex]
    if (timeframe?.modules) {
      Object.values(timeframe.modules).forEach((module, modIndex) => {
        const moduleKey = Object.keys(timeframe.modules)[modIndex]
        allModules.push({
          ...module,
          timeframe: timeframeName,
          moduleKey: moduleKey
        })
        if (module.status === 'insufficient_data') {
          insufficientDataCount++
        }
        // Check if any DANGER module has confidence >= 70%
        if ((module.status === 'danger' || module.status === 'extreme_shock' || 
             module.status === 'strong_trend' || module.status === 'shock') && 
            (module.confidence ?? 0) >= 70) {
          dangerWithHighConfidence = true
          dangerModules.push({
            module: module.module || moduleKey,
            timeframe: timeframeName,
            status: module.status,
            confidence: module.confidence ?? 0,
            status_label: module.status_label
          })
        }
      })
    }
  })
  
  if (allModules.length === 0) return null
  
  // Calculate average confidence (excluding insufficient_data)
  const validModules = allModules.filter(m => m.status !== 'insufficient_data')
  const avgConfidence = validModules.length > 0
    ? Math.round(validModules.reduce((sum, m) => sum + (m.confidence ?? 0), 0) / validModules.length)
    : 0
  
  // Determine final status with override rules
  let finalStatus = technicalAnalysis.value.combined_overall?.status || 'safe'
  let overrideReason = null
  let eaRecommendation = null
  const riskScore = technicalAnalysis.value.combined_overall?.risk_score || 0
  
  // Rule 1: If any DANGER module with confidence >= 70% → override to DANGER
  if (dangerWithHighConfidence) {
    finalStatus = 'danger'
    const dangerModulesList = dangerModules.map(m => `${m.module} (${m.timeframe})`).join(', ')
    overrideReason = `Modul DANGER dengan confidence ≥ 70% terdeteksi: ${dangerModulesList}`
    eaRecommendation = `EA DANGER | Risk Score: ${riskScore}/100 | Kondisi market berisiko tinggi untuk EA Grid. Disarankan nonaktifkan EA atau gunakan mode konservatif.`
  }
  // Rule 2: If all SAFE and no Insufficient Data → SAFE
  else if (allModules.every(m => m.status === 'safe' || m.status === 'low_risk') && insufficientDataCount === 0) {
    finalStatus = 'safe'
    eaRecommendation = `EA SAFE | Risk Score: ${riskScore}/100 | Kondisi market relatif aman untuk EA Grid.`
  }
  // Rule 3: If mixed & many Insufficient Data → CAUTION
  else if (insufficientDataCount > allModules.length * 0.5) {
    finalStatus = 'caution'
    overrideReason = 'Keputusan teknikal kurang kuat, sebaiknya ikuti Prediksi 1 & 2.'
    eaRecommendation = `EA CAUTION | Risk Score: ${riskScore}/100 | Data teknikal kurang lengkap. Gunakan mode konservatif atau nonaktifkan EA.`
  }
  // Default: Generate recommendation based on final status
  else {
    // If status changed due to override, generate new recommendation
    if (finalStatus !== technicalAnalysis.value.combined_overall?.status) {
      if (finalStatus === 'danger') {
        eaRecommendation = `EA DANGER | Risk Score: ${riskScore}/100 | Kondisi market berisiko tinggi untuk EA Grid. Disarankan nonaktifkan EA atau gunakan mode konservatif.`
      } else if (finalStatus === 'caution') {
        eaRecommendation = `EA CAUTION | Risk Score: ${riskScore}/100 | Kondisi market perlu perhatian. Gunakan mode konservatif.`
      } else {
        eaRecommendation = `EA SAFE | Risk Score: ${riskScore}/100 | Kondisi market relatif aman untuk EA Grid.`
      }
    } else {
      // Use original recommendation if status not changed
      eaRecommendation = technicalAnalysis.value.combined_overall?.recommendation || ''
    }
  }
  
  return {
    confidence: avgConfidence,
    finalStatus,
    overrideReason,
    insufficientDataCount,
    totalModules: allModules.length,
    eaRecommendation,
    dangerModules: dangerModules,
    originalStatus: technicalAnalysis.value.combined_overall?.status || 'safe',
    originalRiskScore: riskScore
  }
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
<!-- 
        <button
          class="btn-primary flex items-center gap-2"
          @click="router.push('/trades/new')"
        >
          <PlusIcon class="w-4 h-4" />
          <span class="hidden sm:inline">Add Trade</span>
        </button> -->

        <button
          class="btn-primary flex items-center gap-2"
          @click="runTodayAnalysis"
          :disabled="loadingTodayAnalysis"
        >
          <!-- Shine effect on hover -->
          <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"></span>
          
          <!-- Content -->
          <span class="relative z-10 flex items-center gap-2">
            <svg v-if="loadingTodayAnalysis" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <LightBulbIcon v-else class="w-4 h-4" />
            <span class="hidden sm:inline font-semibold">{{ loadingTodayAnalysis ? 'Analyzing...' : 'Today Analysis' }}</span>
          </span>
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


    <!-- EA Predictions Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- PREDICTION 1: ForexFactory Impact Based -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2.5">
            <span class="text-xl">📊</span>
            <div>
              <h2 class="text-base font-semibold">News Impact (Forex Factory)</h2>
              <p class="text-xs text-dark-400">#1 Prediction</p>
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
              <h2 class="text-base font-semibold"> Historical Data</h2>
              <p class="text-xs text-dark-400">#2 Prediction</p>
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
              <h2 class="text-base font-semibold"> Technical Analysis (7 Indicators)</h2>
              <p class="text-xs text-dark-400">#3 Prediction</p>
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
          (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'danger' ? 'bg-red-500/10 border-red-500/30' :
          (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'caution' ? 'bg-yellow-500/10 border-yellow-500/30' :
          'bg-green-500/10 border-green-500/30'
        ]">
          <div class="flex items-center justify-between mb-2">
            <div class="flex-1">
              <span :class="[
                'text-lg font-bold',
                getPredictionClass(technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status)
              ]">
                {{ (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'danger' ? '🔴 DANGER' : 
                   (technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status) === 'caution' ? '🟡 CAUTION' : '🟢 SAFE' }}
              </span>
              <p v-if="technicalConfidence?.overrideReason" class="text-xs text-yellow-400 mt-0.5 font-medium">
                ⚠️ Override
              </p>
            </div>
            <div class="text-right">
              <div class="text-xl font-bold" :class="[
                getPredictionClass(technicalConfidence?.finalStatus || technicalAnalysis.combined_overall.status)
              ]">
                {{ technicalAnalysis.combined_overall.risk_score }}/100
              </div>
              <div class="text-xs text-dark-400">Risk</div>
              <div v-if="technicalConfidence" class="text-xs font-medium mt-0.5" :class="[
                technicalConfidence.confidence >= 70 ? 'text-green-400' :
                technicalConfidence.confidence >= 50 ? 'text-yellow-400' : 'text-gray-400'
              ]">
                {{ technicalConfidence.confidence }}% Conf
              </div>
            </div>
          </div>
          <p class="text-xs text-dark-300 mb-2.5 line-clamp-2">
            {{ technicalConfidence?.eaRecommendation || technicalAnalysis.combined_overall.recommendation }}
          </p>
          
          <!-- Override Warning (Compact) -->
          <div v-if="technicalConfidence?.overrideReason && technicalConfidence.finalStatus === 'danger'" class="mt-2 p-2 bg-red-500/10 border border-red-500/30 rounded text-xs">
            <p class="text-red-400 font-medium mb-1">⚠️ Status di-override menjadi DANGER</p>
            <p class="text-red-300/80 text-xs mb-1">
              Meskipun risk score rendah ({{ technicalConfidence.originalRiskScore }}/100) dan semua timeframe Safe, 
              terdeteksi modul berisiko tinggi dengan confidence ≥ 70%.
            </p>
            <details class="text-xs">
              <summary class="cursor-pointer text-red-400 hover:text-red-300 mb-1">
                Modul yang memicu override ({{ technicalConfidence.dangerModules?.length || 0 }})
              </summary>
              <div class="mt-1 space-y-1">
                <div 
                  v-for="(dangerMod, idx) in technicalConfidence.dangerModules" 
                  :key="idx"
                  class="p-1.5 bg-red-500/20 rounded border border-red-500/30"
                >
                  <div class="flex items-center justify-between">
                    <span class="text-red-300 font-medium">{{ dangerMod.module }}</span>
                    <span class="text-red-400 font-bold">{{ dangerMod.confidence }}%</span>
                  </div>
                  <span class="text-red-400/70 text-xs">{{ dangerMod.timeframe }} • {{ dangerMod.status_label }}</span>
                </div>
              </div>
            </details>
          </div>
          
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
          
          <!-- Technical Confidence Summary (Compact) -->
          <div v-if="technicalConfidence" class="mt-2.5 p-2 bg-dark-800/50 rounded border border-dark-700/50">
            <div class="flex items-center justify-between text-xs">
              <div>
                <span class="text-dark-400">Technical Confidence:</span>
                <span class="text-dark-300 ml-1">{{ technicalConfidence.totalModules }} modul</span>
                <span v-if="technicalConfidence.insufficientDataCount > 0" class="text-yellow-400 ml-1">
                  ({{ technicalConfidence.insufficientDataCount }} kurang data)
                </span>
              </div>
              <div class="text-right">
                <span class="font-bold" :class="[
                  technicalConfidence.confidence >= 70 ? 'text-green-400' :
                  technicalConfidence.confidence >= 50 ? 'text-yellow-400' : 'text-gray-400'
                ]">
                  {{ technicalConfidence.confidence }}%
                </span>
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

      <!-- PREDICTION 4: Market Stability Index (MSI) -->
      <div class="card p-5">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center gap-2.5">
            <span class="text-xl">🌍</span>
            <div>
              <h2 class="text-base font-semibold"> Market Stability Index (Yahoo Finance)</h2>
              <p class="text-xs text-dark-400">#4 Prediction</p>
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
              @click="fetchMSI()"
              :disabled="loadingMSI"
              class="flex items-center gap-1.5 px-2.5 py-1 text-xs bg-purple-600/20 text-purple-400 border border-purple-500/30 rounded-lg hover:bg-purple-600/30 transition-colors disabled:opacity-50"
            >
              <svg :class="['w-3.5 h-3.5', loadingMSI && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              {{ loadingMSI ? '...' : '🔄' }}
            </button>
          </div>
        </div>

        <!-- MSI Summary -->
        <div v-if="msiData" :class="[
          'p-3.5 rounded-lg mb-3 border',
          getMSIStatusBg(msiData.status)
        ]">
          <div class="flex items-center justify-between mb-2">
            <span :class="[
              'text-lg font-bold',
              getMSIStatusClass(msiData.status)
            ]">
              {{ msiData.status === 'DANGER' ? '🔴 UNSTABLE' : 
                 msiData.status === 'CAUTION' ? '🟡 CAUTION' : '🟢 STABLE' }}
            </span>
            <div class="text-right">
              <div class="text-xl font-bold" :class="[
                getMSIStatusClass(msiData.status)
              ]">
                {{ msiData.msi }}/100
              </div>
              <div class="text-xs text-dark-400">MSI Score</div>
            </div>
          </div>
          <p class="text-xs text-dark-300 mb-2.5 line-clamp-2">{{ msiData.message }}</p>
          
          <!-- Details Row -->
          <div class="flex flex-wrap gap-1.5 text-xs mb-2.5">
            <span 
              v-if="msiData.details.dxy !== null" 
              @click="openIndicatorInfo('dxy', msiData.details.dxy, msiData.details.dxyScore)"
              class="px-2 py-0.5 bg-dark-800 rounded cursor-pointer hover:bg-dark-700 transition-colors"
              title="Klik untuk informasi detail"
            >
              DXY: <span class="text-white font-medium">{{ msiData.details.dxy?.toFixed(2) || 'N/A' }}</span>
            </span>
            <span 
              v-if="msiData.details.vix !== null" 
              @click="openIndicatorInfo('vix', msiData.details.vix, msiData.details.vixScore)"
              class="px-2 py-0.5 bg-dark-800 rounded cursor-pointer hover:bg-dark-700 transition-colors"
              title="Klik untuk informasi detail"
            >
              VIX: <span class="text-white font-medium">{{ msiData.details.vix?.toFixed(2) || 'N/A' }}</span>
            </span>
            <span 
              v-if="msiData.details.gvz !== null" 
              @click="openIndicatorInfo('gvz', msiData.details.gvz, msiData.details.gvzScore)"
              class="px-2 py-0.5 bg-dark-800 rounded cursor-pointer hover:bg-dark-700 transition-colors"
              title="Klik untuk informasi detail"
            >
              GVZ: <span class="text-white font-medium">{{ msiData.details.gvz?.toFixed(2) || 'N/A' }}</span>
            </span>
            <span 
              v-if="msiData.details.us10y !== null" 
              @click="openIndicatorInfo('yield', msiData.details.us10y, msiData.details.yieldScore)"
              class="px-2 py-0.5 bg-dark-800 rounded cursor-pointer hover:bg-dark-700 transition-colors"
              title="Klik untuk informasi detail"
            >
              Yield: <span class="text-white font-medium">{{ msiData.details.us10y?.toFixed(2) || 'N/A' }}%</span>
            </span>
            <span 
              v-if="msiData.details.spx !== null" 
              @click="openIndicatorInfo('spx', msiData.details.spx, msiData.details.spxScore)"
              class="px-2 py-0.5 bg-dark-800 rounded cursor-pointer hover:bg-dark-700 transition-colors"
              title="Klik untuk informasi detail"
            >
              ES: <span class="text-white font-medium">{{ msiData.details.spx?.toFixed(2) || 'N/A' }}</span>
            </span>
            <span 
              v-if="msiData.details.correlation !== null" 
              @click="openIndicatorInfo('correlation', msiData.details.correlation, msiData.details.correlationScore)"
              class="px-2 py-0.5 bg-dark-800 rounded cursor-pointer hover:bg-dark-700 transition-colors"
              title="Klik untuk informasi detail"
            >
              Corr: <span class="text-white font-medium">{{ msiData.details.correlation?.toFixed(2) || 'N/A' }}</span>
            </span>
          </div>

          <!-- Score Breakdown -->
          <div class="grid grid-cols-2 md:grid-cols-3 gap-1.5 mt-2.5">
            <div 
              v-if="msiData.details.dxyScore !== null" 
              @click="openIndicatorInfo('dxy', msiData.details.dxy, msiData.details.dxyScore)"
              class="p-1.5 rounded border border-dark-700/50 text-xs cursor-pointer hover:border-primary-500/50 hover:bg-dark-800/50 transition-colors"
              title="Klik untuk informasi detail"
            >
              <div class="text-dark-400 text-xs mb-0.5">DXY Score</div>
              <div class="font-bold" :class="[
                msiData.details.dxyScore >= 70 ? 'text-green-400' :
                msiData.details.dxyScore >= 50 ? 'text-yellow-400' : 'text-red-400'
              ]">
                {{ msiData.details.dxyScore }}
              </div>
            </div>
            <div 
              v-if="msiData.details.vixScore !== null" 
              @click="openIndicatorInfo('vix', msiData.details.vix, msiData.details.vixScore)"
              class="p-1.5 rounded border border-dark-700/50 text-xs cursor-pointer hover:border-primary-500/50 hover:bg-dark-800/50 transition-colors"
              title="Klik untuk informasi detail"
            >
              <div class="text-dark-400 text-xs mb-0.5">VIX Score</div>
              <div class="font-bold" :class="[
                msiData.details.vixScore >= 70 ? 'text-green-400' :
                msiData.details.vixScore >= 50 ? 'text-yellow-400' : 'text-red-400'
              ]">
                {{ msiData.details.vixScore }}
              </div>
            </div>
            <div 
              v-if="msiData.details.gvzScore !== null" 
              @click="openIndicatorInfo('gvz', msiData.details.gvz, msiData.details.gvzScore)"
              class="p-1.5 rounded border border-dark-700/50 text-xs cursor-pointer hover:border-primary-500/50 hover:bg-dark-800/50 transition-colors"
              title="Klik untuk informasi detail"
            >
              <div class="text-dark-400 text-xs mb-0.5">GVZ Score</div>
              <div class="font-bold" :class="[
                msiData.details.gvzScore >= 70 ? 'text-green-400' :
                msiData.details.gvzScore >= 50 ? 'text-yellow-400' : 'text-red-400'
              ]">
                {{ msiData.details.gvzScore }}
              </div>
            </div>
            <div 
              v-if="msiData.details.yieldScore !== null" 
              @click="openIndicatorInfo('yield', msiData.details.us10y, msiData.details.yieldScore)"
              class="p-1.5 rounded border border-dark-700/50 text-xs cursor-pointer hover:border-primary-500/50 hover:bg-dark-800/50 transition-colors"
              title="Klik untuk informasi detail"
            >
              <div class="text-dark-400 text-xs mb-0.5">Yield Score</div>
              <div class="font-bold" :class="[
                msiData.details.yieldScore >= 70 ? 'text-green-400' :
                msiData.details.yieldScore >= 50 ? 'text-yellow-400' : 'text-red-400'
              ]">
                {{ msiData.details.yieldScore }}
              </div>
            </div>
            <div 
              v-if="msiData.details.spxScore !== null" 
              @click="openIndicatorInfo('spx', msiData.details.spx, msiData.details.spxScore)"
              class="p-1.5 rounded border border-dark-700/50 text-xs cursor-pointer hover:border-primary-500/50 hover:bg-dark-800/50 transition-colors"
              title="Klik untuk informasi detail"
            >
              <div class="text-dark-400 text-xs mb-0.5">ES Score</div>
              <div class="font-bold" :class="[
                msiData.details.spxScore >= 70 ? 'text-green-400' :
                msiData.details.spxScore >= 50 ? 'text-yellow-400' : 'text-red-400'
              ]">
                {{ msiData.details.spxScore }}
              </div>
            </div>
            <div 
              v-if="msiData.details.correlationScore !== null" 
              @click="openIndicatorInfo('correlation', msiData.details.correlation, msiData.details.correlationScore)"
              class="p-1.5 rounded border border-dark-700/50 text-xs cursor-pointer hover:border-primary-500/50 hover:bg-dark-800/50 transition-colors"
              title="Klik untuk informasi detail"
            >
              <div class="text-dark-400 text-xs mb-0.5">Corr Score</div>
              <div class="font-bold" :class="[
                msiData.details.correlationScore >= 70 ? 'text-green-400' :
                msiData.details.correlationScore >= 50 ? 'text-yellow-400' : 'text-red-400'
              ]">
                {{ msiData.details.correlationScore }}
              </div>
            </div>
          </div>

          <!-- Cross-Pair Correlations Detail (Compact) -->
          <div v-if="msiData.details.correlations" class="mt-3 p-3 bg-dark-800/50 rounded-lg border border-dark-700/50">
            <h4 class="text-xs font-semibold text-dark-300 mb-2">📊 Cross-Pair Correlation</h4>
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div v-if="msiData.details.correlations.xauusd_eurusd !== null" class="flex items-center justify-between">
                <span class="text-dark-400">XAU↔EUR:</span>
                <span class="font-bold" :class="[
                  Math.abs(msiData.details.correlations.xauusd_eurusd) < 0.3 ? 'text-green-400' :
                  Math.abs(msiData.details.correlations.xauusd_eurusd) < 0.6 ? 'text-yellow-400' : 'text-red-400'
                ]">
                  {{ msiData.details.correlations.xauusd_eurusd.toFixed(2) }}
                </span>
              </div>
              <div v-if="msiData.details.correlations.xauusd_gbpusd !== null" class="flex items-center justify-between">
                <span class="text-dark-400">XAU↔GBP:</span>
                <span class="font-bold" :class="[
                  Math.abs(msiData.details.correlations.xauusd_gbpusd) < 0.3 ? 'text-green-400' :
                  Math.abs(msiData.details.correlations.xauusd_gbpusd) < 0.6 ? 'text-yellow-400' : 'text-red-400'
                ]">
                  {{ msiData.details.correlations.xauusd_gbpusd.toFixed(2) }}
                </span>
              </div>
              <div v-if="msiData.details.correlations.xauusd_usdjpy !== null" class="flex items-center justify-between">
                <span class="text-dark-400">XAU↔JPY:</span>
                <span class="font-bold" :class="[
                  Math.abs(msiData.details.correlations.xauusd_usdjpy) < 0.3 ? 'text-green-400' :
                  Math.abs(msiData.details.correlations.xauusd_usdjpy) < 0.6 ? 'text-yellow-400' : 'text-red-400'
                ]">
                  {{ msiData.details.correlations.xauusd_usdjpy.toFixed(2) }}
                </span>
              </div>
              <div v-if="msiData.details.correlations.eurusd_gbpusd !== null" class="flex items-center justify-between">
                <span class="text-dark-400">EUR↔GBP:</span>
                <span class="font-bold" :class="[
                  Math.abs(msiData.details.correlations.eurusd_gbpusd) < 0.3 ? 'text-green-400' :
                  Math.abs(msiData.details.correlations.eurusd_gbpusd) < 0.6 ? 'text-yellow-400' : 'text-red-400'
                ]">
                  {{ msiData.details.correlations.eurusd_gbpusd.toFixed(2) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Updated At -->
          <div v-if="msiData.updatedAt" class="mt-2.5 text-xs text-dark-500">
            Updated: {{ new Date(msiData.updatedAt).toLocaleString() }}
          </div>
        </div>

        <!-- Error State -->
        <div v-if="msiError" class="p-2.5 bg-red-500/10 border border-red-500/30 rounded-lg mb-3">
          <p class="text-xs text-red-400">{{ msiError }}</p>
        </div>

        <!-- Loading State -->
        <div v-if="loadingMSI" class="flex items-center justify-center py-4">
          <div class="spinner mr-2"></div>
          <span class="text-xs text-dark-400">Loading MSI...</span>
        </div>

        <!-- Empty State -->
        <div v-else-if="!loadingMSI && !msiData && !msiError" class="text-center py-4 text-dark-400">
          <span class="text-2xl mb-1 block">🌍</span>
          <p class="text-xs">Klik "🔄" untuk analisis</p>
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

  <!-- Indicator Info Modal -->
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="showIndicatorModal && selectedIndicator"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="showIndicatorModal = false"
      >
        <div class="fixed inset-0 bg-dark-950/90 backdrop-blur-sm"></div>
        <div class="relative min-h-screen flex items-center justify-center p-4">
          <div class="relative w-full max-w-2xl card p-6 bg-dark-900" @click.stop>
            <div class="flex items-center justify-between mb-4">
              <h2 class="text-xl font-bold text-dark-100">{{ selectedIndicator.name }}</h2>
              <button
                @click="showIndicatorModal = false"
                class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-200 transition-colors"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <div class="space-y-4">
              <!-- Description -->
              <div>
                <p class="text-sm text-dark-300">{{ selectedIndicator.description }}</p>
              </div>

              <!-- Current Value & Score -->
              <div class="p-4 bg-dark-800/50 rounded-lg border border-dark-700/50">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <div class="text-xs text-dark-400 mb-1">Nilai Saat Ini</div>
                    <div class="text-2xl font-bold text-dark-100">
                      {{ typeof selectedIndicator.currentValue === 'number' 
                        ? selectedIndicator.currentValue.toFixed(2) 
                        : selectedIndicator.currentValue }}
                      {{ selectedIndicator.name.includes('Yield') ? '%' : '' }}
                    </div>
                  </div>
                  <div>
                    <div class="text-xs text-dark-400 mb-1">Score</div>
                    <div class="text-2xl font-bold" :class="[
                      selectedIndicator.currentScore >= 70 ? 'text-green-400' :
                      selectedIndicator.currentScore >= 50 ? 'text-yellow-400' : 'text-red-400'
                    ]">
                      {{ selectedIndicator.currentScore }}/100
                    </div>
                  </div>
                </div>
                <div class="mt-3 pt-3 border-t border-dark-700/50">
                  <div class="text-xs text-dark-400 mb-1">Kategori</div>
                  <div class="text-sm font-semibold text-dark-200">{{ selectedIndicator.category }}</div>
                </div>
              </div>

              <!-- Interpretation -->
              <div class="p-4 rounded-lg border" :class="[
                selectedIndicator.currentScore >= 70 ? 'bg-green-500/10 border-green-500/30' :
                selectedIndicator.currentScore >= 50 ? 'bg-yellow-500/10 border-yellow-500/30' :
                'bg-red-500/10 border-red-500/30'
              ]">
                <div class="text-sm font-semibold mb-2" :class="[
                  selectedIndicator.currentScore >= 70 ? 'text-green-400' :
                  selectedIndicator.currentScore >= 50 ? 'text-yellow-400' : 'text-red-400'
                ]">
                  Interpretasi
                </div>
                <p class="text-sm text-dark-200">{{ selectedIndicator.interpretation }}</p>
              </div>

              <!-- Safe Ranges -->
              <div>
                <h3 class="text-sm font-semibold text-green-400 mb-3">🟢 Nilai Aman</h3>
                <div class="space-y-2">
                  <div 
                    v-for="(range, idx) in selectedIndicator.safeRanges" 
                    :key="idx"
                    class="p-3 bg-green-500/5 border border-green-500/20 rounded-lg"
                  >
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-sm font-medium text-green-300">{{ range.range }}</span>
                      <span class="text-xs text-green-400">Score: {{ range.score }}</span>
                    </div>
                    <p class="text-xs text-dark-300">{{ range.description }}</p>
                  </div>
                </div>
              </div>

              <!-- Danger Ranges -->
              <div>
                <h3 class="text-sm font-semibold text-red-400 mb-3">🔴 Nilai Berbahaya</h3>
                <div class="space-y-2">
                  <div 
                    v-for="(range, idx) in selectedIndicator.dangerRanges" 
                    :key="idx"
                    class="p-3 bg-red-500/5 border border-red-500/20 rounded-lg"
                  >
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-sm font-medium text-red-300">{{ range.range }}</span>
                      <span class="text-xs text-red-400">Score: {{ range.score }}</span>
                    </div>
                    <p class="text-xs text-dark-300">{{ range.description }}</p>
                  </div>
                </div>
              </div>

              <!-- Close Button -->
              <div class="flex justify-end pt-4">
                <button
                  @click="showIndicatorModal = false"
                  class="px-4 py-2 bg-primary-500 text-white rounded-lg hover:bg-primary-600 transition-colors"
                >
                  Tutup
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Today Analysis Modal -->
  <TodayAnalysisModal
    :show="showTodayAnalysis"
    :loading="loadingTodayAnalysis"
    :error="todayAnalysisError"
    :analysis="todayAnalysis"
    @close="showTodayAnalysis = false"
    @view-news="handleViewNews"
    @view-chart="handleViewChart"
  />
</template>

<style scoped>
/* Modal transition */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .card,
.modal-leave-active .card {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-enter-from .card,
.modal-leave-to .card {
  transform: scale(0.95);
  opacity: 0;
}
</style>

