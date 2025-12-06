<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useDashboardStore } from '@/stores/dashboard'
import StatCard from '@/components/dashboard/StatCard.vue'
import EquityChart from '@/components/charts/EquityChart.vue'
import DailyPnLChart from '@/components/charts/DailyPnLChart.vue'
import TradeTable from '@/components/common/TradeTable.vue'
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

onMounted(async () => {
  await dashboardStore.fetchDashboard(selectedDays.value)
})

const handleDaysChange = async (days) => {
  selectedDays.value = days
  await dashboardStore.fetchDashboard(days)
}

const refreshData = async () => {
  await dashboardStore.fetchDashboard(selectedDays.value)
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

