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
    emit('openModal', 'growth')
  }
}

const chartData = computed(() => {
  const validData = Array.isArray(props.data) 
    ? props.data.filter(d => d && d.date && (d.equity !== undefined || d.balance !== undefined))
    : []
  
  // Calculate growth percentage from initial balance or first data point
  const baseBalance = props.initialBalance > 0 
    ? props.initialBalance 
    : (Number(validData[0]?.balance) || Number(validData[0]?.equity) || 1)
  
  const growthData = validData.map(d => {
    const currentBalance = Number(d.balance) || Number(d.equity) || 0
    return ((currentBalance - baseBalance) / baseBalance) * 100
  })
  
  return {
    labels: validData.map(d => d.date),
    datasets: [
      {
        label: 'Growth %',
        data: growthData,
        borderColor: '#06b6d4',
        backgroundColor: (context) => {
          const chart = context.chart
          const { ctx, chartArea } = chart
          if (!chartArea) return 'rgba(6, 182, 212, 0.1)'
          
          const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top)
          gradient.addColorStop(0, 'rgba(6, 182, 212, 0)')
          gradient.addColorStop(0.5, 'rgba(6, 182, 212, 0.15)')
          gradient.addColorStop(1, 'rgba(6, 182, 212, 0.4)')
          return gradient
        },
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#06b6d4',
        pointBorderColor: '#083344',
        pointBorderWidth: 2,
        pointHoverRadius: 8,
        pointHoverBackgroundColor: '#06b6d4',
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
      backgroundColor: '#083344',
      titleColor: '#cffafe',
      bodyColor: '#a5f3fc',
      borderColor: '#0e7490',
      borderWidth: 1,
      padding: 14,
      displayColors: false,
      callbacks: {
        title: (tooltipItems) => {
          return `📅 ${tooltipItems[0].label}`
        },
        label: (context) => {
          const value = context.parsed.y
          const sign = value >= 0 ? '+' : ''
          return `📊 Growth: ${sign}${value.toFixed(2)}%`
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
        color: 'rgba(6, 182, 212, 0.1)',
      },
      ticks: {
        color: '#64748b',
        callback: (value) => {
          const sign = value >= 0 ? '+' : ''
          return sign + value.toFixed(1) + '%'
        },
        font: {
          size: 11,
        },
      },
    },
  },
}

// Calculate current growth
const currentGrowth = computed(() => {
  if (!props.data || props.data.length === 0) return 0
  const baseBalance = props.initialBalance > 0 
    ? props.initialBalance 
    : (Number(props.data[0]?.balance) || Number(props.data[0]?.equity) || 1)
  const lastBalance = Number(props.data[props.data.length - 1]?.balance) || Number(props.data[props.data.length - 1]?.equity) || 0
  return ((lastBalance - baseBalance) / baseBalance) * 100
})
</script>

<template>
  <div class="card p-4 bg-gradient-to-br from-cyan-950/30 to-dark-900">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-sm font-medium text-dark-300">📊 Growth</h3>
        <p class="text-xs text-dark-500 mt-0.5">Performance percentage</p>
      </div>
      <div v-if="data.length > 0" class="text-right">
        <span :class="[
          'text-xl font-bold',
          currentGrowth >= 0 ? 'text-cyan-400' : 'text-red-400'
        ]">
          {{ currentGrowth >= 0 ? '+' : '' }}{{ currentGrowth.toFixed(2) }}%
        </span>
        <span class="block text-xs text-dark-500">
          Total Growth
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

