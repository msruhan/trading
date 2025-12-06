<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { reportsAPI } from '@/services/api'
import { useAccountsStore } from '@/stores/accounts'
import TradeTable from '@/components/common/TradeTable.vue'
import {
  DocumentArrowDownIcon,
  CalendarIcon,
  FunnelIcon,
} from '@heroicons/vue/24/outline'

const accountsStore = useAccountsStore()

const loading = ref(false)
const reportData = ref(null)

const filters = reactive({
  account_id: '',
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
})

const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 5 }, (_, i) => currentYear - i)
})

onMounted(async () => {
  await accountsStore.fetchAccounts()
  await fetchReport()
})

const fetchReport = async () => {
  loading.value = true
  try {
    const response = await reportsAPI.getMonthly(filters)
    reportData.value = response.data
  } catch (error) {
    console.error('Failed to fetch report:', error)
  } finally {
    loading.value = false
  }
}

const handleFilterChange = () => {
  fetchReport()
}

const exportCSV = async () => {
  try {
    const response = await reportsAPI.exportCSV(filters)
    const blob = new Blob([response.data], { type: 'text/csv' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `trading-report-${filters.year}-${filters.month}.csv`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Failed to export CSV:', error)
  }
}

const exportPDF = async () => {
  try {
    const response = await reportsAPI.exportPDF(filters)
    const blob = new Blob([response.data], { type: 'application/pdf' })
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `trading-report-${filters.year}-${filters.month}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Failed to export PDF:', error)
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-dark-100">Reports</h1>
        <p class="text-dark-400">Monthly trading performance reports</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          class="btn-secondary flex items-center gap-2"
          @click="exportCSV"
        >
          <DocumentArrowDownIcon class="w-4 h-4" />
          Export CSV
        </button>
        <button
          class="btn-primary flex items-center gap-2"
          @click="exportPDF"
        >
          <DocumentArrowDownIcon class="w-4 h-4" />
          Export PDF
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="card p-4">
      <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
          <CalendarIcon class="w-5 h-5 text-dark-500" />
          <select v-model="filters.month" class="input w-auto" @change="handleFilterChange">
            <option v-for="(month, index) in months" :key="index" :value="index + 1">
              {{ month }}
            </option>
          </select>
          <select v-model="filters.year" class="input w-auto" @change="handleFilterChange">
            <option v-for="year in years" :key="year" :value="year">
              {{ year }}
            </option>
          </select>
        </div>
        <div class="flex items-center gap-2">
          <FunnelIcon class="w-5 h-5 text-dark-500" />
          <select v-model="filters.account_id" class="input w-auto" @change="handleFilterChange">
            <option value="">All Accounts</option>
            <option v-for="account in accountsStore.accounts" :key="account.id" :value="account.id">
              {{ account.name || account.broker_name }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="spinner" />
    </div>

    <!-- Report content -->
    <template v-else-if="reportData">
      <!-- Stats summary -->
      <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <div class="stat-card">
          <span class="stat-label">Total Trades</span>
          <span class="stat-value text-dark-100">{{ reportData.stats.total_trades }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Win Rate</span>
          <span class="stat-value text-dark-100">{{ reportData.stats.winrate }}%</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Total P/L</span>
          <span :class="['stat-value', reportData.stats.total_profit >= 0 ? 'profit' : 'loss']">
            ${{ reportData.stats.total_profit.toLocaleString() }}
          </span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Profit Factor</span>
          <span class="stat-value text-dark-100">{{ reportData.stats.profit_factor }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Avg Win</span>
          <span class="stat-value profit">${{ reportData.stats.average_win.toLocaleString() }}</span>
        </div>
        <div class="stat-card">
          <span class="stat-label">Avg Loss</span>
          <span class="stat-value loss">-${{ Math.abs(reportData.stats.average_loss).toLocaleString() }}</span>
        </div>
      </div>

      <!-- Daily breakdown -->
      <div class="card p-4">
        <h3 class="text-sm font-medium text-dark-300 mb-4">Daily Performance</h3>
        <div class="overflow-x-auto">
          <div class="flex gap-1 min-w-max">
            <div
              v-for="(data, date) in reportData.daily_data"
              :key="date"
              class="flex flex-col items-center w-12"
            >
              <div
                :class="[
                  'w-8 h-8 rounded-md flex items-center justify-center text-xs font-medium',
                  data.profit > 0 ? 'bg-green-500/20 text-green-400' :
                  data.profit < 0 ? 'bg-red-500/20 text-red-400' :
                  'bg-dark-800 text-dark-500'
                ]"
                :title="`${date}: $${data.profit} (${data.trades} trades)`"
              >
                {{ data.trades }}
              </div>
              <span class="text-xs text-dark-500 mt-1">{{ date.slice(-2) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Performance by pair -->
      <div class="card p-4">
        <h3 class="text-sm font-medium text-dark-300 mb-4">Performance by Pair</h3>
        <div class="space-y-2">
          <div
            v-for="(data, pair) in reportData.pair_data"
            :key="pair"
            class="flex items-center justify-between py-2 border-b border-dark-800 last:border-0"
          >
            <div class="flex items-center gap-4">
              <span class="font-medium text-dark-100 w-20">{{ pair }}</span>
              <span class="text-sm text-dark-500">{{ data.trades }} trades</span>
              <span class="text-sm text-dark-500">{{ data.winrate }}% win</span>
            </div>
            <span :class="['font-mono font-medium', data.profit >= 0 ? 'profit' : 'loss']">
              {{ data.profit >= 0 ? '+' : '' }}${{ data.profit.toFixed(2) }}
            </span>
          </div>
        </div>
      </div>

      <!-- Trades list -->
      <div class="card overflow-hidden">
        <div class="p-4 border-b border-dark-800">
          <h3 class="text-sm font-medium text-dark-300">All Trades</h3>
        </div>
        <TradeTable :trades="reportData.trades" />
      </div>
    </template>

    <!-- No data -->
    <div v-else class="card p-12 text-center">
      <p class="text-dark-500">No data available for the selected period</p>
    </div>
  </div>
</template>

