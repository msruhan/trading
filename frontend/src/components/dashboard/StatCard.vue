<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  prefix: {
    type: String,
    default: '',
  },
  suffix: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'default', // default, profit, loss, neutral
  },
  change: {
    type: Number,
    default: null,
  },
  icon: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const valueClass = computed(() => {
  if (props.type === 'profit' || (props.type === 'auto' && Number(props.value) > 0)) {
    return 'text-green-400'
  }
  if (props.type === 'loss' || (props.type === 'auto' && Number(props.value) < 0)) {
    return 'text-red-400'
  }
  return 'text-dark-100'
})

const formattedValue = computed(() => {
  if (typeof props.value === 'number') {
    return props.value.toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  }
  return props.value
})
</script>

<template>
  <div class="stat-card group hover:border-dark-700 transition-colors">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <span class="stat-label">{{ label }}</span>
      <component 
        v-if="icon" 
        :is="icon" 
        class="w-5 h-5 text-dark-500 group-hover:text-dark-400 transition-colors" 
      />
    </div>

    <!-- Value -->
    <div v-if="loading" class="h-8 bg-dark-800 rounded animate-pulse" />
    <div v-else class="flex items-baseline gap-1">
      <span v-if="prefix" class="text-sm text-dark-400">{{ prefix }}</span>
      <span :class="['stat-value', valueClass]">
        {{ formattedValue }}
      </span>
      <span v-if="suffix" class="text-sm text-dark-400">{{ suffix }}</span>
    </div>

    <!-- Change indicator -->
    <div v-if="change !== null" class="flex items-center gap-1 text-xs">
      <span :class="change >= 0 ? 'text-green-400' : 'text-red-400'">
        {{ change >= 0 ? '+' : '' }}{{ change.toFixed(2) }}%
      </span>
      <span class="text-dark-500">vs yesterday</span>
    </div>
  </div>
</template>

