<script setup>
import { computed } from 'vue'
import { Line } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

const props = defineProps({
  data: {
    type: Array,
    required: true,
  },
  initialBalance: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  accountId: {
    type: [Number, String],
    default: null,
  },
})

const emit = defineEmits(['openModal'])

const handleChartClick = () => {
  if (props.accountId) {
    emit('openModal', 'balance')
  }
}

const chartData = computed(() => {
  const validData = Array.isArray(props.data) 
    ? props.data.filter(d => d && d.date && (d.equity !== undefined || d.balance !== undefined))
    : []
  
  return {
    labels: validData.map(d => d.date),
    datasets: [
      {
        label: 'Balance',
        data: validData.map(d => Number(d.balance) || Number(d.equity) || 0),
        borderColor: '#8b5cf6',
        backgroundColor: (context) => {
          const chart = context.chart
          const { ctx, chartArea } = chart
          if (!chartArea) return 'rgba(139, 92, 246, 0.1)'
          
          const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top)
          gradient.addColorStop(0, 'rgba(139, 92, 246, 0)')
          gradient.addColorStop(0.5, 'rgba(139, 92, 246, 0.1)')
          gradient.addColorStop(1, 'rgba(139, 92, 246, 0.3)')
          return gradient
        },
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#8b5cf6',
        pointBorderColor: '#1e1b4b',
        pointBorderWidth: 2,
        pointHoverRadius: 8,
        pointHoverBackgroundColor: '#8b5cf6',
        pointHoverBorderColor: '#fff',
        pointHoverBorderWidth: 3,
        borderWidth: 3,
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
      backgroundColor: '#1e1b4b',
      titleColor: '#e0e7ff',
      bodyColor: '#c7d2fe',
      borderColor: '#4c1d95',
      borderWidth: 1,
      padding: 14,
      displayColors: false,
      callbacks: {
        title: (tooltipItems) => {
          return `📅 ${tooltipItems[0].label}`
        },
        label: (context) => {
          const value = context.parsed.y
          return `💰 Balance: $${value.toLocaleString('en-US', { minimumFractionDigits: 2 })}`
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
        font: {
          size: 11,
        },
      },
    },
    y: {
      display: true,
      grid: {
        color: 'rgba(139, 92, 246, 0.1)',
      },
      ticks: {
        color: '#64748b',
        callback: (value) => '$' + value.toLocaleString(),
        font: {
          size: 11,
        },
      },
    },
  },
}

// Calculate total change
const totalChange = computed(() => {
  if (!props.data || props.data.length < 2) return { value: 0, percent: 0 }
  const first = Number(props.data[0]?.balance) || Number(props.data[0]?.equity) || 0
  const last = Number(props.data[props.data.length - 1]?.balance) || Number(props.data[props.data.length - 1]?.equity) || 0
  const change = last - first
  const percent = first > 0 ? (change / first) * 100 : 0
  return { value: change, percent }
})
</script>

<template>
  <div class="card p-4 bg-gradient-to-br from-violet-950/30 to-dark-900">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-sm font-medium text-dark-300">💵 Balance</h3>
        <p class="text-xs text-dark-500 mt-0.5">Account balance over time</p>
      </div>
      <div v-if="data.length > 1" class="text-right">
        <span :class="[
          'text-lg font-bold',
          totalChange.value >= 0 ? 'text-green-400' : 'text-red-400'
        ]">
          {{ totalChange.value >= 0 ? '+' : '' }}${{ totalChange.value.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
        </span>
        <span :class="[
          'block text-xs',
          totalChange.percent >= 0 ? 'text-green-500' : 'text-red-500'
        ]">
          {{ totalChange.percent >= 0 ? '↑' : '↓' }} {{ Math.abs(totalChange.percent).toFixed(2) }}%
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
      <Line :data="chartData" :options="chartOptions" />
    </div>
  </div>
</template>

