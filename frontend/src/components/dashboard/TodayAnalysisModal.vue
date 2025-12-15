<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-dark-950/80 backdrop-blur-sm"
        @click.self="$emit('close')"
      >
        <div class="bg-dark-900 border border-dark-800 rounded-2xl shadow-2xl max-w-5xl w-full max-h-[90vh] overflow-y-auto">
          <!-- Header -->
          <div class="sticky top-0 bg-dark-900 border-b border-dark-800 p-6 flex items-center justify-between z-10 backdrop-blur-sm">
            <div class="flex items-center gap-3">
              <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-primary-500 to-accent-500 flex items-center justify-center shadow-lg">
                <LightBulbIcon class="w-7 h-7 text-white" />
              </div>
              <div>
                <h2 class="text-2xl font-bold text-dark-100">Today Analysis</h2>
                <p class="text-sm text-dark-400">AI-Powered EA Safety Recommendation System</p>
              </div>
            </div>
            <button
              @click="$emit('close')"
              class="p-2 rounded-lg hover:bg-dark-800 text-dark-400 hover:text-dark-100 transition-colors"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>

          <!-- Content -->
          <div class="p-6 space-y-6">
            <!-- Loading State -->
            <div v-if="loading" class="text-center py-16">
              <div class="inline-block relative mb-6">
                <div class="w-20 h-20 border-4 border-primary-500/30 border-t-primary-500 rounded-full animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                  <LightBulbIcon class="w-8 h-8 text-primary-500 animate-pulse" />
                </div>
              </div>
              <p class="text-lg text-dark-200 font-medium mb-2">Analyzing market conditions...</p>
              <p class="text-sm text-dark-400 mb-4">Processing 4 prediction models</p>
              <div class="flex justify-center gap-2">
                <div class="w-3 h-3 bg-primary-500 rounded-full animate-bounce" style="animation-delay: 0s"></div>
                <div class="w-3 h-3 bg-primary-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                <div class="w-3 h-3 bg-primary-500 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
              </div>
              <div class="mt-6 space-y-2">
                <div class="flex items-center justify-center gap-2 text-sm text-dark-400">
                  <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                  <span>Analyzing Forex Factory News Impact...</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-sm text-dark-400">
                  <div class="w-2 h-2 bg-blue-500 rounded-full animate-pulse" style="animation-delay: 0.3s"></div>
                  <span>Processing Historical Data...</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-sm text-dark-400">
                  <div class="w-2 h-2 bg-purple-500 rounded-full animate-pulse" style="animation-delay: 0.6s"></div>
                  <span>Calculating Technical Analysis...</span>
                </div>
                <div class="flex items-center justify-center gap-2 text-sm text-dark-400">
                  <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse" style="animation-delay: 0.9s"></div>
                  <span>Evaluating Market Stability Index...</span>
                </div>
              </div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="bg-red-500/10 border-2 border-red-500/30 rounded-xl p-6">
              <div class="flex items-center gap-3">
                <XCircleIcon class="w-6 h-6 text-red-400" />
                <div>
                  <h3 class="text-lg font-semibold text-red-400 mb-1">Error</h3>
                  <p class="text-red-300">{{ error }}</p>
                </div>
              </div>
            </div>

            <!-- Analysis Results -->
            <div v-else-if="analysis" class="space-y-6">
              <!-- Final Score & Recommendation - Hero Section -->
              <div :class="['rounded-2xl p-8 border-2 relative overflow-hidden', getStatusBg(analysis.final_score.status)]">
                <!-- Background Pattern -->
                <div class="absolute inset-0 opacity-5">
                  <div class="absolute inset-0" :style="{ backgroundImage: 'radial-gradient(circle at 2px 2px, currentColor 1px, transparent 0)', backgroundSize: '24px 24px' }"></div>
                </div>
                
                <div class="relative z-10">
                  <div class="flex items-start justify-between mb-6">
                    <div class="flex-1">
                      <p class="text-sm font-medium text-dark-400 mb-2 uppercase tracking-wide">EA Safety Score</p>
                      <div class="flex items-baseline gap-3 mb-4">
                        <p :class="['text-6xl font-bold', getStatusColor(analysis.final_score.status)]">
                          {{ analysis.final_score.score }}
                        </p>
                        <p class="text-2xl text-dark-500">/ 100</p>
                      </div>
                      
                      <!-- Score Breakdown -->
                      <div class="grid grid-cols-4 gap-2 mt-4">
                        <div
                          v-for="(value, key) in analysis.final_score.breakdown"
                          :key="key"
                          class="bg-dark-800/50 rounded-lg p-2 text-center"
                        >
                          <p class="text-xs text-dark-400 mb-1">{{ key.replace('prediction_', 'P').toUpperCase() }}</p>
                          <p class="text-sm font-semibold text-dark-200">{{ value }}</p>
                        </div>
                      </div>
                    </div>
                    
                    <div class="ml-4">
                      <component :is="getStatusIcon(analysis.final_score.status)" 
                        :class="['w-16 h-16', getStatusColor(analysis.final_score.status)]" 
                      />
                    </div>
                  </div>
                  
                  <!-- Recommendation Card -->
                  <div class="mt-6 p-6 bg-dark-800/70 rounded-xl border border-dark-700/50 backdrop-blur-sm">
                    <div class="flex items-center gap-4 mb-4">
                      <div :class="[
                        'w-14 h-14 rounded-xl flex items-center justify-center',
                        analysis.recommendation.action === 'ON' ? 'bg-green-500/20' : 'bg-red-500/20'
                      ]">
                        <component :is="analysis.recommendation.action === 'ON' ? CheckCircleIcon : XCircleIcon"
                          :class="['w-8 h-8', analysis.recommendation.action === 'ON' ? 'text-green-400' : 'text-red-400']"
                        />
                      </div>
                      <div class="flex-1">
                        <p class="text-xs text-dark-400 mb-1 uppercase tracking-wide">Recommendation</p>
                        <h3 :class="[
                          'text-2xl font-bold',
                          analysis.recommendation.action === 'ON' ? 'text-green-400' : 'text-red-400'
                        ]">
                          EA {{ analysis.recommendation.action }}
                        </h3>
                        <p class="text-sm text-dark-300 mt-1">{{ analysis.recommendation.message }}</p>
                      </div>
                      <div class="text-right">
                        <p class="text-xs text-dark-400 mb-1">Confidence</p>
                        <p class="text-2xl font-bold text-primary-400">{{ analysis.recommendation.confidence }}%</p>
                      </div>
                    </div>
                    
                    <!-- Reasons -->
                    <div class="mt-4 pt-4 border-t border-dark-700/50">
                      <p class="text-xs font-semibold text-dark-400 mb-3 uppercase tracking-wide">Key Factors</p>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        <div
                          v-for="(reason, idx) in analysis.recommendation.reasons"
                          :key="idx"
                          class="flex items-start gap-2 p-2 bg-dark-900/50 rounded-lg"
                        >
                          <div class="w-1.5 h-1.5 rounded-full bg-primary-400 mt-1.5 flex-shrink-0"></div>
                          <p class="text-sm text-dark-300">{{ reason }}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Safe Hours -->
              <div class="bg-dark-800 rounded-xl p-6 border border-dark-700">
                <div class="flex items-center gap-3 mb-4">
                  <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
                    <CalendarDaysIcon class="w-5 h-5 text-green-400" />
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold text-dark-100">Safe Hours for EA Today</h3>
                    <p class="text-xs text-dark-400">Recommended time windows for EA Grid operation</p>
                  </div>
                </div>
                
                <div v-if="analysis.safe_hours && analysis.safe_hours.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  <div
                    v-for="(window, idx) in analysis.safe_hours"
                    :key="idx"
                    class="bg-green-500/10 border-2 border-green-500/30 rounded-xl p-4 hover:bg-green-500/15 transition-colors"
                  >
                    <div class="flex items-center justify-between mb-2">
                      <span class="text-xs font-medium text-green-400 uppercase tracking-wide">Window {{ idx + 1 }}</span>
                      <CheckCircleIcon class="w-4 h-4 text-green-400" />
                    </div>
                    <p class="text-xl font-bold text-green-400 mb-1">{{ window.start }} - {{ window.end }}</p>
                    <p class="text-xs text-dark-400">{{ window.duration }} safe</p>
                  </div>
                </div>
                <div v-else class="bg-red-500/10 border-2 border-red-500/30 rounded-xl p-6 text-center">
                  <XCircleIcon class="w-8 h-8 text-red-400 mx-auto mb-2" />
                  <p class="text-red-400 font-medium">No safe windows detected</p>
                  <p class="text-sm text-red-300 mt-1">EA should remain OFF today</p>
                </div>
              </div>

              <!-- Predictions Breakdown -->
              <div>
                <div class="flex items-center gap-3 mb-4">
                  <div class="w-10 h-10 rounded-lg bg-primary-500/20 flex items-center justify-center">
                    <ChartBarIcon class="w-5 h-5 text-primary-400" />
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold text-dark-100">Prediction Breakdown</h3>
                    <p class="text-xs text-dark-400">Detailed analysis from 4 prediction models</p>
                  </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div
                    v-for="(pred, key) in analysis.predictions"
                    :key="key"
                    :class="[
                      'rounded-xl p-5 border-2 transition-all hover:scale-[1.02]',
                      pred.status === 'safe' || pred.status === 'SAFE' ? 'bg-green-500/5 border-green-500/20' :
                      pred.status === 'danger' || pred.status === 'DANGER' ? 'bg-red-500/5 border-red-500/20' :
                      'bg-yellow-500/5 border-yellow-500/20'
                    ]"
                  >
                    <div class="flex items-start justify-between mb-3">
                      <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                          <span class="text-xs font-bold text-primary-400">
                            {{ key.replace('prediction_', '#').toUpperCase() }}
                          </span>
                          <span :class="['px-2 py-1 rounded text-xs font-semibold', 
                            pred.status === 'safe' || pred.status === 'SAFE' ? 'bg-green-500/20 text-green-400' :
                            pred.status === 'danger' || pred.status === 'DANGER' ? 'bg-red-500/20 text-red-400' :
                            'bg-yellow-500/20 text-yellow-400'
                          ]">
                            {{ pred.status.toUpperCase() }}
                          </span>
                        </div>
                        <h4 class="font-semibold text-dark-100 text-sm mb-1">{{ pred.name }}</h4>
                      </div>
                      <component :is="getStatusIcon(pred.status)" 
                        :class="['w-6 h-6 flex-shrink-0', getStatusColor(pred.status)]" 
                      />
                    </div>
                    
                    <div class="flex items-baseline gap-2 mb-3">
                      <p :class="['text-3xl font-bold', getStatusColor(pred.status)]">
                        {{ pred.score }}
                      </p>
                      <p class="text-lg text-dark-500">/ 100</p>
                    </div>
                    
                    <div class="flex items-center justify-between text-xs">
                      <span class="text-dark-400">Weight:</span>
                      <span class="font-semibold text-dark-300">{{ (pred.weight * 100).toFixed(0) }}%</span>
                    </div>
                    
                    <!-- Additional info if available -->
                    <div v-if="pred.high_impact_count !== undefined" class="mt-3 pt-3 border-t border-dark-700/50">
                      <div class="flex items-center justify-between text-xs">
                        <span class="text-dark-400">High Impact:</span>
                        <span class="font-semibold text-red-400">{{ pred.high_impact_count }}</span>
                      </div>
                    </div>
                    <div v-if="pred.total_trades !== undefined" class="mt-2 flex items-center justify-between text-xs">
                      <span class="text-dark-400">Historical Trades:</span>
                      <span class="font-semibold text-dark-300">{{ pred.total_trades }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Summary -->
              <div class="bg-gradient-to-br from-dark-800 to-dark-800/50 rounded-xl p-6 border border-dark-700">
                <div class="flex items-center gap-3 mb-4">
                  <div class="w-10 h-10 rounded-lg bg-primary-500/20 flex items-center justify-center">
                    <InformationCircleIcon class="w-5 h-5 text-primary-400" />
                  </div>
                  <h3 class="text-lg font-semibold text-dark-100">Analysis Summary</h3>
                </div>
                <p class="text-dark-200 leading-relaxed">{{ analysis.summary }}</p>
                <div v-if="analysis.generated_at" class="mt-4 pt-4 border-t border-dark-700/50">
                  <p class="text-xs text-dark-400">
                    Generated at: <span class="text-dark-300">{{ new Date(analysis.generated_at).toLocaleString() }}</span>
                  </p>
                </div>
              </div>

              <!-- Action Buttons -->
              <div class="flex gap-3 pt-2">
                <button
                  @click="$emit('view-news')"
                  class="flex-1 btn-primary flex items-center justify-center gap-2 py-3 rounded-xl font-medium transition-all hover:scale-105"
                >
                  <InformationCircleIcon class="w-5 h-5" />
                  View Details (News)
                </button>
                <button
                  @click="$emit('view-chart')"
                  class="flex-1 btn-secondary flex items-center justify-center gap-2 py-3 rounded-xl font-medium transition-all hover:scale-105"
                >
                  <ChartBarIcon class="w-5 h-5" />
                  View Details (Chart)
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import {
  LightBulbIcon,
  XMarkIcon,
  CheckCircleIcon,
  XCircleIcon,
  InformationCircleIcon,
  CalendarDaysIcon,
  ChartBarIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: Boolean,
  loading: Boolean,
  error: String,
  analysis: Object,
})

const emit = defineEmits(['close', 'view-news', 'view-chart'])

const getStatusIcon = (status) => {
  return status === 'safe' || status === 'SAFE' ? CheckCircleIcon :
         status === 'danger' || status === 'DANGER' ? XCircleIcon :
         InformationCircleIcon
}

const getStatusColor = (status) => {
  return status === 'safe' || status === 'SAFE' ? 'text-green-400' :
         status === 'danger' || status === 'DANGER' ? 'text-red-400' :
         'text-yellow-400'
}

const getStatusBg = (status) => {
  return status === 'safe' || status === 'SAFE' ? 'bg-green-500/10 border-green-500/30' :
         status === 'danger' || status === 'DANGER' ? 'bg-red-500/10 border-red-500/30' :
         'bg-yellow-500/10 border-yellow-500/30'
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-active .bg-dark-900,
.modal-leave-active .bg-dark-900 {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-enter-from .bg-dark-900,
.modal-leave-to .bg-dark-900 {
  transform: scale(0.95);
  opacity: 0;
}
</style>
