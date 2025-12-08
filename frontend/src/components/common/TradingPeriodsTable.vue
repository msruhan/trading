<script setup>
import { computed } from 'vue'
import {
  CalendarIcon,
  ArrowTrendingUpIcon,
  ArrowTrendingDownIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  periods: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

// Format numbers
const formatPercent = (value, showSign = true) => {
  if (value === undefined || value === null || value === '-') return '-'
  const num = Number(value)
  const sign = showSign && num > 0 ? '+' : ''
  return `${sign}${num.toFixed(2)}%`
}

const formatDollar = (value, showSign = true) => {
  if (value === undefined || value === null || value === '-') return '-'
  const num = Number(value)
  const sign = showSign && num > 0 ? '+' : ''
  return `${sign}$${Math.abs(num).toLocaleString('en-US', { minimumFractionDigits: 2 })}`
}

const formatPips = (value, showSign = true) => {
  if (value === undefined || value === null || value === '-') return '-'
  const num = Number(value)
  const sign = showSign && num > 0 ? '+' : ''
  return `${sign}${num.toLocaleString('en-US', { minimumFractionDigits: 1 })}`
}

const formatNumber = (value) => {
  if (value === undefined || value === null || value === '-') return '-'
  return Number(value).toLocaleString('en-US')
}

const formatLots = (value) => {
  if (value === undefined || value === null || value === '-') return '-'
  return Number(value).toFixed(2)
}

// Get class based on value
const getValueClass = (value) => {
  if (value === undefined || value === null || value === '-') return 'text-dark-500'
  const num = Number(value)
  if (num > 0) return 'text-green-400'
  if (num < 0) return 'text-red-400'
  return 'text-dark-300'
}

// Period rows
const periodRows = computed(() => [
  { key: 'today', label: 'Today', icon: '📅' },
  { key: 'this_week', label: 'This Week', icon: '📆' },
  { key: 'this_month', label: 'This Month', icon: '🗓️' },
  { key: 'this_year', label: 'This Year', icon: '📊' },
])

// Column definitions
const columns = [
  { key: 'gain', label: 'Gain', format: 'percent', diff: true },
  { key: 'profit', label: 'Profit', format: 'dollar', diff: true },
  { key: 'pips', label: 'Pips', format: 'pips', diff: true },
  { key: 'winrate', label: 'Win%', format: 'percent', diff: true },
  { key: 'trades', label: 'Trades', format: 'number', diff: true },
  { key: 'lots', label: 'Lots', format: 'lots', diff: true },
]

// Get formatted value
const getValue = (period, column) => {
  const data = props.periods?.[period.key]
  if (!data) return { value: '-', diff: '-' }
  
  const value = data[column.key]
  const diffValue = data[`${column.key}_diff`]
  
  let formattedValue = '-'
  let formattedDiff = '-'
  
  switch (column.format) {
    case 'percent':
      formattedValue = formatPercent(value)
      formattedDiff = diffValue !== undefined ? `(${formatPercent(diffValue)})` : ''
      break
    case 'dollar':
      formattedValue = formatDollar(value)
      formattedDiff = diffValue !== undefined ? `(${formatDollar(diffValue)})` : ''
      break
    case 'pips':
      formattedValue = formatPips(value)
      formattedDiff = diffValue !== undefined ? `(${formatPips(diffValue)})` : ''
      break
    case 'number':
      formattedValue = formatNumber(value)
      formattedDiff = diffValue !== undefined ? `(+${formatNumber(diffValue)})` : ''
      break
    case 'lots':
      formattedValue = formatLots(value)
      formattedDiff = diffValue !== undefined ? `(+${formatLots(diffValue)})` : ''
      break
  }
  
  return { 
    value: formattedValue, 
    diff: formattedDiff,
    valueClass: getValueClass(value),
    diffClass: getValueClass(diffValue),
  }
}
</script>

<template>
  <div class="card overflow-hidden">
    <!-- Header Tabs -->
    <div class="flex items-center border-b border-dark-800 bg-dark-900/50">
      <button class="px-4 py-3 text-sm font-medium text-primary-400 border-b-2 border-primary-400">
        Trading
      </button>
      <button class="px-4 py-3 text-sm font-medium text-dark-500 hover:text-dark-300">
        Periods
      </button>
      <button class="px-4 py-3 text-sm font-medium text-dark-500 hover:text-dark-300">
        Goals
      </button>
      <button class="px-4 py-3 text-sm font-medium text-dark-500 hover:text-dark-300">
        Browser
      </button>
      <div class="flex-1"></div>
      <button class="p-3 text-dark-500 hover:text-dark-300">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
      </button>
    </div>
    
    <!-- Table -->
    <div v-if="loading" class="p-8 flex justify-center">
      <div class="spinner" />
    </div>
    
    <div v-else class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="border-b border-dark-800 bg-dark-900/30">
            <th class="px-4 py-3 text-left">
              <span class="inline-flex items-center gap-1.5 text-xs font-medium text-dark-500 uppercase">
                <span class="w-4 h-4 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400">i</span>
              </span>
            </th>
            <th v-for="col in columns" :key="col.key" class="px-4 py-3 text-center">
              <span class="text-xs font-medium text-dark-400">
                {{ col.label }} <span class="text-dark-600">(Difference)</span>
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="period in periodRows" 
            :key="period.key"
            class="border-b border-dark-800/50 hover:bg-dark-800/30 transition-colors"
          >
            <td class="px-4 py-3">
              <span class="text-sm font-medium text-dark-300">
                {{ period.label }}
              </span>
            </td>
            <td v-for="col in columns" :key="col.key" class="px-4 py-3 text-center">
              <div class="flex flex-col items-center">
                <span :class="['text-sm font-medium', getValue(period, col).valueClass]">
                  {{ getValue(period, col).value }}
                </span>
                <span v-if="getValue(period, col).diff && getValue(period, col).diff !== '-'" 
                      :class="['text-xs', getValue(period, col).diffClass]">
                  {{ getValue(period, col).diff }}
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

