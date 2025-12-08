<script setup>
import { computed } from 'vue'
import { Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from 'chart.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
)

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  accountId: {
    type: [Number, String],
    default: null,
  },
  initialBalance: {
    type: Number,
    default: 0,
  },
})

const emit = defineEmits(['openModal'])

const handleChartClick = () => {
  if (props.accountId) {
    emit('openModal', 'profit')
  }
}

const chartData = computed(() => {
  const validData = Array.isArray(props.data) 
    ? props.data.filter(d => d && d.date)
    : []
  
  // Calculate daily profit (difference from previous day)
  const profits = validData.map((d, index) => {
    if (index === 0) {
      // First day profit is the balance/equity value itself minus initial (or just use as-is)
      return Number(d.equity) || Number(d.balance) || 0
    }
    const prevDay = validData[index - 1]
    const currentBalance = Number(d.balance) || Number(d.equity) || 0
    const prevBalance = Number(prevDay.balance) || Number(prevDay.equity) || 0
    return currentBalance - prevBalance
  })
  
  return {
    labels: validData.map(d => d.date),
    datasets: [
      {
        label: 'Daily Profit',
        data: profits,
        backgroundColor: profits.map(p => p >= 0 ? 'rgba(34, 197, 94, 0.8)' : 'rgba(239, 68, 68, 0.8)'),
        borderColor: profits.map(p => p >= 0 ? '#22c55e' : '#ef4444'),
        borderWidth: 1,
        borderRadius: 6,
        barThickness: 40,
      },
    ],
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      backgroundColor: '#1e293b',
      titleColor: '#f1f5f9',
      bodyColor: '#cbd5e1',
      borderColor: '#334155',
      borderWidth: 1,
      padding: 12,
      displayColors: false,
      callbacks: {
        label: (context) => {
          const value = context.parsed.y
          const sign = value >= 0 ? '+' : ''
          return `${sign}$${value.toLocaleString('en-US', { minimumFractionDigits: 2 })}`
        },
      },
    },
  },
  scales: {
    x: {
      display: true,
      grid: {
        display: false,
      },
      ticks: {
        color: '#64748b',
        maxRotation: 0,
        autoSkip: true,
        maxTicksLimit: 7,
      },
    },
    y: {
      display: true,
      grid: {
        color: 'rgba(51, 65, 85, 0.5)',
      },
      ticks: {
        color: '#64748b',
        callback: (value) => {
          const sign = value >= 0 ? '+' : ''
          return sign + '$' + value.toLocaleString()
        },
      },
    },
  },
}
</script>

<template>
  <div class="card p-4">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-sm font-medium text-dark-300">📈 Daily Profit</h3>
      <div class="flex items-center gap-4 text-xs">
        <span class="flex items-center gap-1">
          <span class="w-3 h-3 rounded bg-green-500"></span>
          <span class="text-dark-400">Profit</span>
        </span>
        <span class="flex items-center gap-1">
          <span class="w-3 h-3 rounded bg-red-500"></span>
          <span class="text-dark-400">Loss</span>
        </span>
      </div>
    </div>
    
    <div v-if="loading" class="h-56 flex items-center justify-center">
      <div class="spinner" />
    </div>
    
    <div v-else-if="data.length === 0" class="h-56 flex items-center justify-center text-dark-500">
      No data available
    </div>
    
    <div v-else class="h-56 cursor-pointer" @click="handleChartClick">
      <Bar :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

