<script setup>
import { computed } from 'vue'
import dayjs from 'dayjs'

const props = defineProps({
  trades: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  compact: {
    type: Boolean,
    default: false,
  },
})

const formatDate = (date) => {
  if (!date) return '-'
  return dayjs(date).format('MMM DD, HH:mm')
}

const formatPrice = (price, pair) => {
  if (!price && price !== 0) return '-'
  const decimals = pair?.includes('JPY') || pair?.includes('XAU') ? 2 : 5
  const numPrice = typeof price === 'string' ? parseFloat(price) : Number(price)
  if (isNaN(numPrice)) return '-'
  return numPrice.toFixed(decimals)
}

const formatProfit = (profit) => {
  if (profit === null || profit === undefined || profit === '') return '-'
  // Convert to number if it's a string
  const numProfit = typeof profit === 'string' ? parseFloat(profit) : Number(profit)
  if (isNaN(numProfit)) return '-'
  const prefix = numProfit >= 0 ? '+' : ''
  return `${prefix}$${numProfit.toFixed(2)}`
}

const profitClass = (profit) => {
  if (profit === null || profit === undefined || profit === '') return ''
  const numProfit = typeof profit === 'string' ? parseFloat(profit) : Number(profit)
  if (isNaN(numProfit)) return ''
  if (numProfit > 0) return 'profit'
  if (numProfit < 0) return 'loss'
  return ''
}

const typeClass = (type) => {
  if (type?.includes('buy')) return 'text-green-400'
  if (type?.includes('sell')) return 'text-red-400'
  return ''
}
</script>

<template>
  <div class="table-container">
    <!-- Loading state -->
    <div v-if="loading" class="p-8 text-center">
      <div class="spinner mx-auto" />
      <p class="mt-2 text-sm text-dark-500">Loading trades...</p>
    </div>

    <!-- Empty state -->
    <div v-else-if="trades.length === 0" class="p-8 text-center text-dark-500">
      <p>No trades found</p>
    </div>

    <!-- Table -->
    <table v-else class="table">
      <thead>
        <tr>
          <th>Pair</th>
          <th>Type</th>
          <th v-if="!compact">Lots</th>
          <th v-if="!compact">Entry</th>
          <th v-if="!compact">Exit</th>
          <th>Time</th>
          <th class="text-right">P/L</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(trade, index) in trades" :key="`trade-${trade.id || trade.ticket || index}`">
          <td>
            <span class="font-medium text-dark-100">{{ trade.pair }}</span>
          </td>
          <td>
            <span :class="['uppercase text-xs font-medium', typeClass(trade.type)]">
              {{ trade.type }}
            </span>
          </td>
          <td v-if="!compact" class="font-mono text-dark-300">
            {{ trade.lots }}
          </td>
          <td v-if="!compact" class="font-mono text-dark-300">
            {{ formatPrice(trade.open_price, trade.pair) }}
          </td>
          <td v-if="!compact" class="font-mono text-dark-300">
            {{ formatPrice(trade.close_price, trade.pair) }}
          </td>
          <td class="text-dark-400 text-sm">
            {{ formatDate(trade.close_time || trade.open_time) }}
          </td>
          <td class="text-right">
            <span :class="['font-mono font-medium', profitClass(trade.profit)]">
              {{ formatProfit(trade.profit) }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

