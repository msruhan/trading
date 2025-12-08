<script setup>
import { computed } from 'vue'

const props = defineProps({
  accountInfo: {
    type: Object,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

// Format numbers
const formatPercent = (value, showSign = false) => {
  if (value === undefined || value === null) return '0.00%'
  const num = Number(value)
  const sign = showSign && num > 0 ? '+' : ''
  return `${sign}${num.toFixed(2)}%`
}

const formatDollar = (value) => {
  if (value === undefined || value === null) return '$0.00'
  const num = Number(value)
  return `$${num.toLocaleString('en-US', { minimumFractionDigits: 2 })}`
}

// Get class based on value
const getValueClass = (value) => {
  if (value === undefined || value === null) return 'text-dark-200'
  const num = Number(value)
  if (num > 0) return 'text-green-400'
  if (num < 0) return 'text-red-400'
  return 'text-dark-200'
}
</script>

<template>
  <div class="card overflow-hidden h-full">
    <!-- Header Tabs -->
    <div class="flex items-center border-b border-dark-700 bg-dark-850">
      <button class="px-4 py-2.5 text-sm font-medium text-dark-100 border-b-2 border-primary-400 bg-dark-800/50">
        Info
      </button>
      <button class="px-4 py-2.5 text-sm font-medium text-dark-500 hover:text-dark-300 border-b-2 border-transparent">
        Stats
      </button>
      <button class="px-4 py-2.5 text-sm font-medium text-dark-500 hover:text-dark-300 border-b-2 border-transparent">
        General
      </button>
    </div>
    
    <!-- Content -->
    <div v-if="loading" class="p-8 flex justify-center">
      <div class="spinner" />
    </div>
    
    <div v-else class="text-sm">
      <!-- Gain Section -->
      <div class="border-b border-dark-800/50">
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400 font-medium">Gain:</span>
          <span :class="getValueClass(accountInfo?.gain)">
            {{ formatPercent(accountInfo?.gain, true) }}
          </span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Abs. Gain:</span>
          <span :class="getValueClass(accountInfo?.abs_gain)">
            {{ formatPercent(accountInfo?.abs_gain, true) }}
          </span>
        </div>
      </div>
      
      <!-- Daily/Monthly/Drawdown Section -->
      <div class="border-b border-dark-800/50">
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Daily:</span>
          <span class="text-dark-200">{{ formatPercent(accountInfo?.daily_gain) }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Monthly:</span>
          <span class="text-dark-200">{{ formatPercent(accountInfo?.monthly_gain) }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Drawdown:</span>
          <span class="text-dark-200">{{ formatPercent(accountInfo?.drawdown) }}</span>
        </div>
      </div>
      
      <!-- Balance Section -->
      <div class="border-b border-dark-800/50">
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400 font-medium">Balance:</span>
          <span class="text-dark-100 font-semibold">{{ formatDollar(accountInfo?.balance) }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Equity:</span>
          <span class="text-dark-100">
            <span class="text-dark-500 text-xs mr-1">({{ formatPercent(accountInfo?.equity_percent) }})</span>
            <span class="font-semibold">{{ formatDollar(accountInfo?.equity) }}</span>
          </span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Highest:</span>
          <span class="text-dark-200">
            <span class="text-dark-500 text-xs mr-1" v-if="accountInfo?.highest_date">({{ accountInfo?.highest_date }})</span>
            {{ formatDollar(accountInfo?.highest_balance) }}
          </span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Profit:</span>
          <span :class="getValueClass(accountInfo?.total_profit)">{{ formatDollar(accountInfo?.total_profit) }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Interest:</span>
          <span class="text-dark-200">{{ formatDollar(accountInfo?.interest || 0) }}</span>
        </div>
      </div>
      
      <!-- Deposits/Withdrawals Section -->
      <div class="border-b border-dark-800/50">
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Deposits:</span>
          <span class="text-dark-200">{{ formatDollar(accountInfo?.deposits) }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Withdrawals:</span>
          <span class="text-dark-200">{{ formatDollar(accountInfo?.withdrawals) }}</span>
        </div>
      </div>
      
      <!-- Updated Section -->
      <div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Updated:</span>
          <span class="text-dark-400">{{ accountInfo?.last_updated || '-' }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-2 hover:bg-dark-800/30">
          <span class="text-dark-400">Tracking:</span>
          <span class="text-dark-400">{{ accountInfo?.tracking || '0' }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
