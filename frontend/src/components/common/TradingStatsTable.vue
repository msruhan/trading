<script setup>
import { computed } from 'vue'
import {
  ChartBarIcon,
  CurrencyDollarIcon,
  ClockIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
  ScaleIcon,
  FireIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  stats: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

// Format numbers with locale
const formatNumber = (value, decimals = 2) => {
  if (value === undefined || value === null) return '-'
  return Number(value).toLocaleString('en-US', { 
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals 
  })
}

const formatDuration = (minutes) => {
  if (!minutes || minutes <= 0) return '-'
  if (minutes < 60) return `${Math.round(minutes)}m`
  if (minutes < 1440) return `${(minutes / 60).toFixed(1)}h`
  return `${(minutes / 1440).toFixed(1)}d`
}

// Stats data grouped by category
const statsGroups = computed(() => [
  {
    title: 'Trading Activity',
    icon: ChartBarIcon,
    color: 'from-blue-500/20 to-blue-600/10',
    iconColor: 'text-blue-400',
    stats: [
      { label: 'Total Trades', value: props.stats?.total_trades || 0, format: 'number' },
      { label: 'Total Volume', value: props.stats?.total_volume || 0, format: 'volume' },
      { label: 'Total Lots', value: props.stats?.total_lots || 0, format: 'lots' },
      { label: 'Avg Trade Length', value: props.stats?.avg_trade_length || 0, format: 'duration' },
    ]
  },
  {
    title: 'Winning Stats',
    icon: ArrowTrendingUpIcon,
    color: 'from-green-500/20 to-green-600/10',
    iconColor: 'text-green-400',
    stats: [
      { label: 'Avg Win ($)', value: props.stats?.avg_win_dollar || 0, format: 'dollar' },
      { label: 'Avg Win (pips)', value: props.stats?.avg_win_pips || 0, format: 'pips' },
      { label: 'Best Trade', value: props.stats?.best_trade || 0, format: 'dollar' },
      { label: 'Win Streak', value: props.stats?.max_win_streak || 0, format: 'number' },
    ]
  },
  {
    title: 'Losing Stats',
    icon: ArrowTrendingDownIcon,
    color: 'from-red-500/20 to-red-600/10',
    iconColor: 'text-red-400',
    stats: [
      { label: 'Avg Loss ($)', value: props.stats?.avg_loss_dollar || 0, format: 'dollar' },
      { label: 'Avg Loss (pips)', value: props.stats?.avg_loss_pips || 0, format: 'pips' },
      { label: 'Worst Trade', value: props.stats?.worst_trade || 0, format: 'dollar' },
      { label: 'Loss Streak', value: props.stats?.max_loss_streak || 0, format: 'number' },
    ]
  },
  {
    title: 'Performance Metrics',
    icon: ScaleIcon,
    color: 'from-purple-500/20 to-purple-600/10',
    iconColor: 'text-purple-400',
    stats: [
      { label: 'Profit Factor', value: props.stats?.profit_factor || 0, format: 'ratio' },
      { label: 'Longs Won', value: props.stats?.longs_won_percent || 0, format: 'percent' },
      { label: 'Shorts Won', value: props.stats?.shorts_won_percent || 0, format: 'percent' },
      { label: 'Risk/Reward', value: props.stats?.risk_reward || 0, format: 'ratio' },
    ]
  },
])

// Format value based on type
const formatValue = (value, format) => {
  switch (format) {
    case 'dollar':
      const dollarVal = Number(value)
      const sign = dollarVal >= 0 ? '' : '-'
      return `${sign}$${formatNumber(Math.abs(dollarVal))}`
    case 'pips':
      return `${formatNumber(value, 1)} pips`
    case 'percent':
      return `${formatNumber(value, 1)}%`
    case 'ratio':
      return formatNumber(value, 2)
    case 'volume':
      return formatNumber(value, 0)
    case 'lots':
      return formatNumber(value, 2)
    case 'duration':
      return formatDuration(value)
    case 'number':
    default:
      return formatNumber(value, 0)
  }
}
</script>

<template>
  <div class="card overflow-hidden">
    <div class="p-4 border-b border-dark-800 bg-gradient-to-r from-dark-900 to-dark-850">
      <div class="flex items-center gap-2">
        <FireIcon class="w-5 h-5 text-orange-400" />
        <h3 class="text-sm font-semibold text-dark-200">Trading Statistics</h3>
      </div>
      <p class="text-xs text-dark-500 mt-1">Detailed performance metrics</p>
    </div>
    
    <div v-if="loading" class="p-8 flex justify-center">
      <div class="spinner" />
    </div>
    
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-0">
      <div 
        v-for="(group, groupIndex) in statsGroups" 
        :key="groupIndex"
        :class="[
          'p-4 border-b md:border-b-0 border-dark-800',
          groupIndex < statsGroups.length - 1 ? 'md:border-r' : '',
        ]"
      >
        <!-- Group Header -->
        <div :class="['flex items-center gap-2 mb-3 pb-2 border-b border-dark-800']">
          <div :class="['p-1.5 rounded-lg bg-gradient-to-br', group.color]">
            <component :is="group.icon" :class="['w-4 h-4', group.iconColor]" />
          </div>
          <span class="text-xs font-medium text-dark-400 uppercase tracking-wider">
            {{ group.title }}
          </span>
        </div>
        
        <!-- Stats List -->
        <div class="space-y-2.5">
          <div 
            v-for="(stat, statIndex) in group.stats" 
            :key="statIndex"
            class="flex items-center justify-between"
          >
            <span class="text-xs text-dark-500">{{ stat.label }}</span>
            <span :class="[
              'text-sm font-mono font-medium',
              stat.format === 'dollar' && stat.value < 0 ? 'text-red-400' : 
              stat.format === 'dollar' && stat.value > 0 ? 'text-green-400' : 
              'text-dark-200'
            ]">
              {{ formatValue(stat.value, stat.format) }}
            </span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Summary Footer -->
    <div class="p-4 bg-gradient-to-r from-dark-850 to-dark-900 border-t border-dark-800">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
        <div>
          <span class="block text-xs text-dark-500 mb-1">Win Rate</span>
          <span :class="[
            'text-lg font-bold',
            (stats?.winrate || 0) >= 50 ? 'text-green-400' : 'text-red-400'
          ]">
            {{ formatNumber(stats?.winrate || 0, 1) }}%
          </span>
        </div>
        <div>
          <span class="block text-xs text-dark-500 mb-1">Total Profit</span>
          <span :class="[
            'text-lg font-bold',
            (stats?.total_profit || 0) >= 0 ? 'text-green-400' : 'text-red-400'
          ]">
            {{ (stats?.total_profit || 0) >= 0 ? '+' : '' }}${{ formatNumber(stats?.total_profit || 0) }}
          </span>
        </div>
        <div>
          <span class="block text-xs text-dark-500 mb-1">Gross Profit</span>
          <span class="text-lg font-bold text-green-400">
            +${{ formatNumber(stats?.gross_profit || 0) }}
          </span>
        </div>
        <div>
          <span class="block text-xs text-dark-500 mb-1">Gross Loss</span>
          <span class="text-lg font-bold text-red-400">
            -${{ formatNumber(stats?.gross_loss || 0) }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

