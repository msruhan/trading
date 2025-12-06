import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { dashboardAPI } from '@/services/api'

export const useDashboardStore = defineStore('dashboard', () => {
  // State
  const metrics = ref(null)
  const equityCurve = ref([])
  const dailyPnL = ref([])
  const todaysTrades = ref([])
  const statsByPair = ref([])
  const news = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const balance = computed(() => metrics.value?.balance ?? 0)
  const equity = computed(() => metrics.value?.equity ?? 0)
  const floatingPL = computed(() => metrics.value?.floating_pl ?? 0)
  const dailyProfit = computed(() => metrics.value?.daily_pl ?? 0)
  const monthlyProfit = computed(() => metrics.value?.monthly_pl ?? 0)
  const winrate = computed(() => metrics.value?.winrate ?? 0)
  const totalTrades = computed(() => metrics.value?.total_trades ?? 0)
  const openTrades = computed(() => metrics.value?.open_trades ?? 0)

  // Actions
  async function fetchDashboard(days = 30) {
    loading.value = true
    error.value = null
    
    try {
      const response = await dashboardAPI.getOverview({ days })
      const data = response.data
      
      metrics.value = data.metrics
      equityCurve.value = data.equity_curve
      dailyPnL.value = data.daily_pnl
      todaysTrades.value = data.todays_trades
      statsByPair.value = data.stats_by_pair
      news.value = data.news
      
      return data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch dashboard data'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchMetrics(days = 30) {
    try {
      const response = await dashboardAPI.getMetrics({ days })
      metrics.value = response.data
      return response.data
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch metrics'
      throw err
    }
  }

  async function fetchEquityCurve(days = 30) {
    try {
      const response = await dashboardAPI.getEquityCurve({ days })
      equityCurve.value = response.data
      return response.data
    } catch (err) {
      throw err
    }
  }

  async function fetchDailyPnL(days = 30) {
    try {
      const response = await dashboardAPI.getDailyPnL({ days })
      dailyPnL.value = response.data
      return response.data
    } catch (err) {
      throw err
    }
  }

  function reset() {
    metrics.value = null
    equityCurve.value = []
    dailyPnL.value = []
    todaysTrades.value = []
    statsByPair.value = []
    news.value = []
    error.value = null
  }

  return {
    // State
    metrics,
    equityCurve,
    dailyPnL,
    todaysTrades,
    statsByPair,
    news,
    loading,
    error,
    // Getters
    balance,
    equity,
    floatingPL,
    dailyProfit,
    monthlyProfit,
    winrate,
    totalTrades,
    openTrades,
    // Actions
    fetchDashboard,
    fetchMetrics,
    fetchEquityCurve,
    fetchDailyPnL,
    reset,
  }
})

