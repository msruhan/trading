<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold">Economic Calendar</h1>
        <p class="text-dark-400 mt-1">ForexFactory news with EA safety tracking</p>
      </div>
      
      <div class="flex items-center gap-3">
        <!-- EA Status Indicator -->
        <div :class="[
          'flex items-center gap-2 px-4 py-2 rounded-xl border',
          eaRecommendation?.status === 'danger' ? 'bg-red-500/10 border-red-500/30 text-red-400' :
          eaRecommendation?.status === 'caution' ? 'bg-yellow-500/10 border-yellow-500/30 text-yellow-400' :
          'bg-green-500/10 border-green-500/30 text-green-400'
        ]">
          <span class="relative flex h-3 w-3">
            <span :class="[
              'animate-ping absolute inline-flex h-full w-full rounded-full opacity-75',
              eaRecommendation?.status === 'danger' ? 'bg-red-400' :
              eaRecommendation?.status === 'caution' ? 'bg-yellow-400' : 'bg-green-400'
            ]"></span>
            <span :class="[
              'relative inline-flex rounded-full h-3 w-3',
              eaRecommendation?.status === 'danger' ? 'bg-red-500' :
              eaRecommendation?.status === 'caution' ? 'bg-yellow-500' : 'bg-green-500'
            ]"></span>
          </span>
          <span class="font-medium">EA: {{ eaRecommendation?.status?.toUpperCase() || 'CHECKING' }}</span>
        </div>

        <!-- Sync Buttons -->
        <div class="flex items-center gap-2">
          <button 
            @click="syncNews" 
            :disabled="syncing"
            class="flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-xl hover:bg-primary-500 transition-colors disabled:opacity-50"
          >
            <svg :class="['w-5 h-5', syncing && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            {{ syncing ? 'Syncing...' : 'Sync Today' }}
          </button>

          <button
            @click="syncWeek"
            :disabled="syncing || loading"
            class="flex items-center gap-2 px-3 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-500 transition-colors text-sm disabled:opacity-50"
          >
            <svg :class="['w-4 h-4', syncing && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Sync Week
          </button>

          <button
            @click="resyncNews"
            :disabled="syncing || loading"
            class="flex items-center gap-2 px-3 py-2 border border-red-500/60 text-red-300 rounded-xl hover:bg-red-500/10 transition-colors text-sm disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v6h6M20 20v-6h-6M5 19a9 9 0 0014-7 9 9 0 00-9-9c-2.39 0-4.58.94-6.18 2.47"/>
            </svg>
            Resync Day
          </button>

          <button
            @click="openCreateModal"
            :disabled="syncing || loading"
            class="flex items-center gap-2 px-3 py-2 border border-dark-600 text-dark-100 rounded-xl hover:bg-dark-800 transition-colors text-sm disabled:opacity-50"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add News
          </button>
        </div>
      </div>
    </div>

    <!-- Date Selector & Filters -->
    <div class="card p-4">
      <div class="flex flex-wrap items-center gap-4">
        <!-- Date Picker -->
        <div class="flex items-center gap-2">
          <button @click="prevDay" class="p-2 hover:bg-dark-800 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <input 
            type="date" 
            v-model="selectedDate"
            class="input bg-dark-800 border-dark-700"
          />
          <button @click="nextDay" class="p-2 hover:bg-dark-800 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
          <button @click="goToToday" class="px-3 py-1.5 text-sm bg-dark-800 hover:bg-dark-700 rounded-lg transition-colors">
            Today
          </button>
        </div>

        <!-- Impact Filter -->
        <div class="flex items-center gap-2">
          <span class="text-sm text-dark-400">Impact:</span>
          <div class="flex gap-1">
            <button 
              v-for="impact in ['all', 'high', 'medium', 'low']" 
              :key="impact"
              @click="filterImpact = impact"
              :class="[
                'px-3 py-1.5 text-sm rounded-lg transition-colors',
                filterImpact === impact 
                  ? impact === 'high' ? 'bg-red-500/20 text-red-400' :
                    impact === 'medium' ? 'bg-yellow-500/20 text-yellow-400' :
                    impact === 'low' ? 'bg-green-500/20 text-green-400' :
                    'bg-primary-500/20 text-primary-400'
                  : 'bg-dark-800 text-dark-400 hover:bg-dark-700'
              ]"
            >
              {{ impact === 'all' ? 'All' : impact.charAt(0).toUpperCase() + impact.slice(1) }}
            </button>
          </div>
        </div>

        <!-- Currency Filter -->
        <div class="flex items-center gap-2">
          <span class="text-sm text-dark-400">Currency:</span>
          <select v-model="filterCurrency" class="input bg-dark-800 border-dark-700 py-1.5 text-sm">
            <option value="">All</option>
            <option v-for="cur in currencies" :key="cur" :value="cur">{{ cur }}</option>
          </select>
        </div>

        <!-- EA Status Filter -->
        <div class="flex items-center gap-2">
          <span class="text-sm text-dark-400">EA Status:</span>
          <select v-model="filterEaStatus" class="input bg-dark-800 border-dark-700 py-1.5 text-sm">
            <option value="">All</option>
            <option value="empty">Empty (Not Set)</option>
            <option value="safe">Safe</option>
            <option value="caution">Caution</option>
            <option value="danger">Danger</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
      <div class="stat-card">
        <div class="stat-label">Total Events</div>
        <div class="stat-value">{{ stats.total || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">High Impact</div>
        <div class="stat-value text-red-400">{{ stats.high_impact || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Danger</div>
        <div class="stat-value text-red-400">{{ stats.danger || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Caution</div>
        <div class="stat-value text-yellow-400">{{ stats.caution || 0 }}</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Safe</div>
        <div class="stat-value text-green-400">{{ stats.safe || 0 }}</div>
      </div>
    </div>

    <!-- 🔮 PREDICTION 1: ForexFactory Impact Based -->
    <div class="card p-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <span class="text-2xl">📊</span>
          <div>
            <h2 class="text-lg font-semibold">Prediksi 1: Berdasarkan ForexFactory Impact</h2>
            <p class="text-sm text-dark-400">Prediksi berdasarkan actual impact (high/medium/low) dari ForexFactory</p>
          </div>
        </div>
        <button 
          @click="fetchPredictions"
          :disabled="loadingPredictions"
          class="flex items-center gap-2 px-3 py-1.5 text-sm bg-blue-600/20 text-blue-400 border border-blue-500/30 rounded-lg hover:bg-blue-600/30 transition-colors disabled:opacity-50"
        >
          <svg :class="['w-4 h-4', loadingPredictions && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          {{ loadingPredictions ? 'Loading...' : 'Refresh' }}
        </button>
      </div>

      <!-- Day Prediction Summary -->
      <div v-if="forexfactoryDayPrediction" :class="[
        'p-4 rounded-xl mb-4 border',
        forexfactoryDayPrediction.status === 'danger' ? 'bg-red-500/10 border-red-500/30' :
        forexfactoryDayPrediction.status === 'caution' ? 'bg-yellow-500/10 border-yellow-500/30' :
        'bg-green-500/10 border-green-500/30'
      ]">
        <div class="flex items-center gap-3 mb-2">
          <span :class="[
            'text-2xl font-bold',
            forexfactoryDayPrediction.status === 'danger' ? 'text-red-400' :
            forexfactoryDayPrediction.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
          ]">
            {{ forexfactoryDayPrediction.status === 'danger' ? '🔴 DANGER' : forexfactoryDayPrediction.status === 'caution' ? '🟡 CAUTION' : '🟢 SAFE' }}
          </span>
          <span class="text-sm text-dark-400">{{ selectedDate }}</span>
        </div>
        <p class="text-dark-300 mb-3">{{ forexfactoryDayPrediction.message }}</p>
        
        <!-- Stats Row -->
        <div class="flex flex-wrap gap-3 text-sm mb-4">
          <span class="px-2 py-1 bg-dark-800 rounded">
            📅 Events: <span class="text-white font-medium">{{ forexfactoryDayPrediction.total_events }}</span>
          </span>
          <span class="px-2 py-1 bg-red-500/10 text-red-400 rounded">
            🔴 Danger: {{ forexfactoryDayPrediction.danger_count }}
          </span>
          <span class="px-2 py-1 bg-yellow-500/10 text-yellow-400 rounded">
            🟡 Caution: {{ forexfactoryDayPrediction.caution_count }}
          </span>
          <span class="px-2 py-1 bg-orange-500/10 text-orange-400 rounded">
            🔥 High Impact: {{ forexfactoryDayPrediction.high_impact_count }}
          </span>
        </div>

        <!-- Schedule Recommendations -->
        <div v-if="forexfactoryDayPrediction.schedule_recommendation?.length > 0">
          <h4 class="text-sm font-semibold text-dark-300 mb-2">⏰ Jadwal EA (Berdasarkan Impact):</h4>
          <div class="space-y-2">
            <div 
              v-for="(rec, index) in forexfactoryDayPrediction.schedule_recommendation" 
              :key="index"
              :class="[
                'p-3 rounded-lg border',
                rec.type === 'safe' ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30'
              ]"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-lg">{{ rec.icon }}</span>
                  <span :class="[
                    'font-mono font-bold',
                    rec.type === 'safe' ? 'text-green-400' : 'text-red-400'
                  ]">{{ rec.time_range }}</span>
                </div>
                <span v-if="rec.duration_minutes" class="text-xs text-dark-400">
                  {{ Math.floor(rec.duration_minutes / 60) }}j {{ rec.duration_minutes % 60 }}m
                </span>
              </div>
              <p :class="[
                'text-sm mt-1',
                rec.type === 'safe' ? 'text-green-300/80' : 'text-red-300/80'
              ]">
                {{ rec.message }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Safe Windows Summary -->
      <div v-if="forexfactorySafeWindows.length > 0 && !loadingPredictions" class="mb-4 p-4 bg-green-500/5 border border-green-500/20 rounded-xl">
        <h4 class="text-sm font-semibold text-green-400 mb-2">🟢 Window EA Aman (Impact Based):</h4>
        <div class="flex flex-wrap gap-2">
          <span 
            v-for="(window, index) in forexfactorySafeWindows" 
            :key="index"
            class="px-3 py-1.5 bg-green-500/20 text-green-300 rounded-lg font-mono text-sm"
          >
            {{ window.start }} - {{ window.end }}
          </span>
        </div>
      </div>

      <!-- Individual Predictions (Collapsible) -->
      <details v-if="forexfactoryPredictions.length > 0" class="group">
        <summary class="cursor-pointer text-sm font-semibold text-dark-300 mb-2 flex items-center gap-2 hover:text-white">
          <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
          📊 Detail Prediksi ({{ forexfactoryPredictions.length }} events)
        </summary>
        <div class="mt-3 max-h-64 overflow-y-auto space-y-2 pr-2">
          <div 
            v-for="pred in forexfactoryPredictions" 
            :key="pred.id"
            :class="[
              'p-3 rounded-lg border transition-colors',
              pred.predicted_status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
              pred.predicted_status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
              'bg-green-500/5 border-green-500/20'
            ]"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="font-medium text-sm">{{ pred.title }}</span>
              <div class="flex items-center gap-2">
                <span class="text-xs text-dark-400">{{ pred.time }}</span>
                <span :class="getCurrencyClass(pred.currency)" class="px-1.5 py-0.5 rounded text-xs">
                  {{ pred.currency }}
                </span>
                <span :class="getImpactClass(pred.impact)" class="px-2 py-0.5 rounded text-xs">
                  {{ pred.impact }}
                </span>
              </div>
            </div>
            
            <!-- Prediction Status & Confidence -->
            <div class="flex items-center gap-3 mb-2">
              <span :class="[
                'px-2 py-0.5 rounded text-xs font-medium',
                pred.predicted_status === 'danger' ? 'bg-red-500/30 text-red-300' :
                pred.predicted_status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                'bg-green-500/30 text-green-300'
              ]">
                {{ pred.predicted_status?.toUpperCase() || '—' }}
              </span>
              <div class="flex items-center gap-1">
                <div class="w-12 h-1.5 bg-dark-700 rounded-full overflow-hidden">
                  <div 
                    :class="[
                      'h-full rounded-full',
                      pred.confidence >= 70 ? 'bg-green-500' :
                      pred.confidence >= 50 ? 'bg-yellow-500' : 'bg-gray-500'
                    ]"
                    :style="{ width: pred.confidence + '%' }"
                  ></div>
                </div>
                <span class="text-xs text-dark-500">{{ pred.confidence }}%</span>
              </div>
            </div>
            
            <!-- Reasons / Alasan Prediksi -->
            <div v-if="pred.reasons && pred.reasons.length > 0" class="mb-2">
              <details class="text-xs">
                <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                  📋 Alasan Prediksi ({{ pred.reasons.length }} faktor)
                </summary>
                <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-dark-700">
                  <div 
                    v-for="(reason, idx) in pred.reasons" 
                    :key="idx"
                    class="p-2 bg-dark-800/50 rounded"
                  >
                    <div class="flex items-center justify-between mb-1">
                      <span class="font-medium text-dark-300">{{ reason.factor }}</span>
                      <span class="text-dark-500">+{{ reason.weight }}%</span>
                    </div>
                    <div class="text-dark-400 text-xs mb-1">
                      <span class="font-medium">{{ reason.value }}</span>
                    </div>
                    <div class="text-dark-500 text-xs italic">
                      {{ reason.explanation }}
                    </div>
                  </div>
                </div>
              </details>
            </div>
            
            <!-- Recommendation -->
            <p class="text-xs text-dark-400 mt-1">{{ pred.recommendation }}</p>
            
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span :class="[
                  'px-2 py-0.5 rounded text-xs font-medium',
                  pred.predicted_status === 'danger' ? 'bg-red-500/30 text-red-300' :
                  pred.predicted_status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                  'bg-green-500/30 text-green-300'
                ]">
                  {{ pred.predicted_status?.toUpperCase() }}
                </span>
                <span class="text-xs text-dark-500">Confidence: {{ pred.confidence }}%</span>
              </div>
            </div>
            
            <!-- Reasons / Alasan Prediksi (untuk Prediksi 1) -->
            <div v-if="pred.reasons && pred.reasons.length > 0" class="mb-2 mt-2">
              <details class="text-xs">
                <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                  📋 Alasan Prediksi ({{ pred.reasons.length }} faktor)
                </summary>
                <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-blue-500/30">
                  <div 
                    v-for="(reason, idx) in pred.reasons" 
                    :key="idx"
                    class="p-2 bg-blue-500/5 rounded"
                  >
                    <div class="flex items-center justify-between mb-1">
                      <span class="font-medium text-blue-300">{{ reason.factor }}</span>
                      <span class="text-blue-400">+{{ reason.weight }}%</span>
                    </div>
                    <div class="text-dark-400 text-xs mb-1">
                      <span class="font-medium">{{ reason.value }}</span>
                    </div>
                    <div class="text-dark-500 text-xs italic">
                      {{ reason.explanation }}
                    </div>
                  </div>
                </div>
              </details>
            </div>
            
            <p class="text-xs text-dark-400 mt-1">{{ pred.recommendation }}</p>
          </div>
        </div>
      </details>

      <!-- Empty State -->
      <div v-else-if="!loadingPredictions" class="text-center py-8 text-dark-400">
        <span class="text-4xl mb-2 block">📊</span>
        <p>Tidak ada prediksi untuk tanggal ini.</p>
      </div>
    </div>

    <!-- 🔮 PREDICTION 2: Historical Marking Based -->
    <div class="card p-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <span class="text-2xl">📈</span>
          <div>
            <h2 class="text-lg font-semibold">Prediksi 2: Berdasarkan Data Historis</h2>
            <p class="text-sm text-dark-400">Prediksi berdasarkan news yang sudah Anda tandai (safe/caution/danger)</p>
          </div>
        </div>
      </div>

      <!-- Day Prediction Summary -->
      <div v-if="historicalDayPrediction" :class="[
        'p-4 rounded-xl mb-4 border',
        historicalDayPrediction.status === 'danger' ? 'bg-red-500/10 border-red-500/30' :
        historicalDayPrediction.status === 'caution' ? 'bg-yellow-500/10 border-yellow-500/30' :
        'bg-green-500/10 border-green-500/30'
      ]">
        <div class="flex items-center gap-3 mb-2">
          <span :class="[
            'text-2xl font-bold',
            historicalDayPrediction.status === 'danger' ? 'text-red-400' :
            historicalDayPrediction.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
          ]">
            {{ historicalDayPrediction.status === 'danger' ? '🔴 DANGER' : historicalDayPrediction.status === 'caution' ? '🟡 CAUTION' : '🟢 SAFE' }}
          </span>
          <span class="text-sm text-dark-400">{{ selectedDate }}</span>
        </div>
        <p class="text-dark-300 mb-3">{{ historicalDayPrediction.message }}</p>
        
        <!-- Stats Row -->
        <div class="flex flex-wrap gap-3 text-sm mb-4">
          <span class="px-2 py-1 bg-dark-800 rounded">
            📅 Events: <span class="text-white font-medium">{{ historicalDayPrediction.total_events }}</span>
          </span>
          <span class="px-2 py-1 bg-red-500/10 text-red-400 rounded">
            🔴 Danger: {{ historicalDayPrediction.danger_count }}
          </span>
          <span class="px-2 py-1 bg-yellow-500/10 text-yellow-400 rounded">
            🟡 Caution: {{ historicalDayPrediction.caution_count }}
          </span>
          <span v-if="historicalDayPrediction.marked_danger_count > 0" class="px-2 py-1 bg-red-500/20 text-red-300 rounded">
            ⚠️ Marked: {{ historicalDayPrediction.marked_danger_count }}
          </span>
          <span class="px-2 py-1 bg-purple-500/10 text-purple-400 rounded">
            📊 Confidence: {{ historicalDayPrediction.avg_confidence }}%
          </span>
        </div>

        <!-- Schedule Recommendations -->
        <div v-if="historicalDayPrediction.schedule_recommendation?.length > 0">
          <h4 class="text-sm font-semibold text-dark-300 mb-2">⏰ Jadwal EA (Berdasarkan History):</h4>
          <div class="space-y-2">
            <div 
              v-for="(rec, index) in historicalDayPrediction.schedule_recommendation" 
              :key="index"
              :class="[
                'p-3 rounded-lg border',
                rec.type === 'safe' ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30'
              ]"
            >
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="text-lg">{{ rec.icon }}</span>
                  <span :class="[
                    'font-mono font-bold',
                    rec.type === 'safe' ? 'text-green-400' : 'text-red-400'
                  ]">{{ rec.time_range }}</span>
                </div>
                <span v-if="rec.duration_minutes" class="text-xs text-dark-400">
                  {{ Math.floor(rec.duration_minutes / 60) }}j {{ rec.duration_minutes % 60 }}m
                </span>
              </div>
              <p :class="[
                'text-sm mt-1',
                rec.type === 'safe' ? 'text-green-300/80' : 'text-red-300/80'
              ]">
                {{ rec.message }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Safe Windows Summary -->
      <div v-if="historicalSafeWindows.length > 0 && !loadingPredictions" class="mb-4 p-4 bg-green-500/5 border border-green-500/20 rounded-xl">
        <h4 class="text-sm font-semibold text-green-400 mb-2">🟢 Window EA Aman (History Based):</h4>
        <div class="flex flex-wrap gap-2">
          <span 
            v-for="(window, index) in historicalSafeWindows" 
            :key="index"
            class="px-3 py-1.5 bg-green-500/20 text-green-300 rounded-lg font-mono text-sm"
          >
            {{ window.start }} - {{ window.end }}
          </span>
        </div>
      </div>

      <!-- Danger Windows Summary -->
      <div v-if="historicalDangerWindows.length > 0 && !loadingPredictions" class="mb-4 p-4 bg-red-500/5 border border-red-500/20 rounded-xl">
        <h4 class="text-sm font-semibold text-red-400 mb-2">🔴 Disable EA saat (History Based):</h4>
        <div class="space-y-2">
          <div 
            v-for="(window, index) in historicalDangerWindows" 
            :key="index"
            class="flex items-center justify-between text-sm"
          >
            <div class="flex items-center gap-2">
              <span class="text-dark-300">{{ window.title }}</span>
              <span v-if="window.reason" class="text-xs text-dark-500">({{ window.reason }})</span>
            </div>
            <span class="font-mono text-red-400">{{ window.time }} (±{{ window.before }}m)</span>
          </div>
        </div>
      </div>

      <!-- Individual Predictions (Collapsible) -->
      <details v-if="historicalPredictions.length > 0" class="group">
        <summary class="cursor-pointer text-sm font-semibold text-dark-300 mb-2 flex items-center gap-2 hover:text-white">
          <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
          📊 Detail Prediksi ({{ historicalPredictions.length }} events)
        </summary>
        <div class="mt-3 max-h-64 overflow-y-auto space-y-2 pr-2">
          <div 
            v-for="pred in historicalPredictions" 
            :key="pred.id"
            :class="[
              'p-3 rounded-lg border transition-colors',
              pred.predicted_status === 'danger' || pred.current_status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
              pred.predicted_status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
              'bg-green-500/5 border-green-500/20'
            ]"
          >
            <div class="flex items-center justify-between mb-1">
              <span class="font-medium text-sm">{{ pred.title }}</span>
              <div class="flex items-center gap-2">
                <span class="text-xs text-dark-400">{{ pred.time }}</span>
                <span :class="getCurrencyClass(pred.currency)" class="px-1.5 py-0.5 rounded text-xs">
                  {{ pred.currency }}
                </span>
              </div>
            </div>
            
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <!-- Current Status (if marked) -->
                <span v-if="pred.current_status && pred.current_status !== 'unknown'" :class="[
                  'px-2 py-0.5 rounded text-xs font-medium',
                  pred.current_status === 'danger' ? 'bg-red-500/30 text-red-300' :
                  pred.current_status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                  'bg-green-500/30 text-green-300'
                ]">
                  Marked: {{ pred.current_status?.toUpperCase() }}
                </span>
                
                <!-- Prediction Badge (if not marked) - Hanya tampilkan jika ada data historis -->
                <span v-else-if="pred.has_historical_data && pred.predicted_status" :class="[
                  'px-2 py-0.5 rounded text-xs font-medium',
                  pred.predicted_status === 'danger' ? 'bg-red-500/30 text-red-300' :
                  pred.predicted_status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                  'bg-green-500/30 text-green-300'
                ]">
                  Pred: {{ pred.predicted_status?.toUpperCase() || '—' }}
                </span>
                <!-- No prediction badge if no historical data -->
                <span v-else class="px-2 py-0.5 rounded text-xs font-medium bg-dark-700 text-dark-400">
                  Belum ada data
                </span>
                
                <!-- Confidence -->
                <div class="flex items-center gap-1">
                  <div class="w-12 h-1.5 bg-dark-700 rounded-full overflow-hidden">
                    <div 
                      :class="[
                        'h-full rounded-full',
                        pred.confidence >= 70 ? 'bg-green-500' :
                        pred.confidence >= 50 ? 'bg-yellow-500' : 'bg-gray-500'
                      ]"
                      :style="{ width: pred.confidence + '%' }"
                    ></div>
                  </div>
                  <span class="text-xs text-dark-500">{{ pred.confidence }}%</span>
                </div>
                
                <!-- Historical Data - Total Occurrences -->
                <span v-if="pred.historical_data?.total_occurrences > 0" class="text-xs text-dark-500">
                  ({{ pred.historical_data.total_occurrences }}x muncul)
                </span>
                <span v-else-if="!pred.has_historical_data" class="text-xs text-dark-500 italic">
                  (Belum ada data historis)
                </span>
              </div>
            </div>
            
            <!-- Algorithm Factors / Alasan Algoritma (untuk Prediksi 2) -->
            <div v-if="pred.algorithm_factors && pred.algorithm_factors.length > 0" class="mb-2 mt-2">
              <details class="text-xs">
                <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                  🧠 Algoritma Penguatan ({{ pred.algorithm_factors.length }} faktor)
                </summary>
                <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-purple-500/30">
                  <div 
                    v-for="(factor, idx) in pred.algorithm_factors" 
                    :key="idx"
                    class="p-2 bg-purple-500/5 rounded"
                  >
                    <div class="flex items-center justify-between mb-1">
                      <span class="font-medium text-purple-300">{{ factor.factor }}</span>
                      <span v-if="factor.weight > 0" class="text-purple-400">+{{ factor.weight }}%</span>
                    </div>
                    <div class="text-dark-400 text-xs mb-1">
                      <span class="font-medium">{{ factor.value }}</span>
                    </div>
                    <div class="text-dark-500 text-xs italic">
                      {{ factor.explanation }}
                    </div>
                  </div>
                </div>
              </details>
            </div>
            
            <p class="text-xs text-dark-400 mt-1">{{ pred.recommendation }}</p>
          </div>
        </div>
      </details>

      <!-- Empty State -->
      <div v-else-if="!loadingPredictions" class="text-center py-8 text-dark-400">
        <span class="text-4xl mb-2 block">📈</span>
        <p>Tidak ada prediksi historis untuk tanggal ini.</p>
        <p class="text-sm mt-1">Tandai news dengan Safe/Caution/Danger untuk melatih prediksi ini.</p>
      </div>
    </div>

    <!-- 📊 PREDICTION 3: Technical Analysis Based -->
    <div class="card p-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <span class="text-2xl">📊</span>
          <div>
            <h2 class="text-lg font-semibold">Prediksi 3: Berdasarkan Analisis Teknikal</h2>
            <p class="text-sm text-dark-400">7 modul analisis teknikal multi-timeframe</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button 
            @click="fetchTechnicalAnalysis()"
            :disabled="loadingTechnical"
            class="flex items-center gap-2 px-3 py-1.5 text-sm bg-orange-600/20 text-orange-400 border border-orange-500/30 rounded-lg hover:bg-orange-600/30 transition-colors disabled:opacity-50"
          >
            <svg :class="['w-4 h-4', loadingTechnical && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            {{ loadingTechnical ? 'Analyzing...' : 'Analyze Technical' }}
          </button>
          <span class="text-xs text-dark-500">(Sample data)</span>
        </div>
      </div>

      <!-- Error State -->
      <div v-if="technicalError" class="p-4 bg-red-500/10 border border-red-500/30 rounded-xl mb-4">
        <p class="text-sm text-red-400">{{ technicalError }}</p>
        <p class="text-xs text-red-400/70 mt-1">Pastikan TradingView chart terhubung dan mengirim data OHLC.</p>
      </div>

      <!-- Loading State -->
      <div v-else-if="loadingTechnical" class="text-center py-8 text-dark-400">
        <svg class="w-8 h-8 animate-spin mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <p>Menganalisis data teknikal...</p>
      </div>

      <!-- Day Prediction Summary (Card Besar) -->
      <div v-else-if="technicalAnalysis?.combined_overall" :class="[
        'p-6 rounded-xl mb-6 border-2',
        technicalAnalysis.combined_overall.status === 'danger' ? 'bg-red-500/10 border-red-500/40' :
        technicalAnalysis.combined_overall.status === 'caution' ? 'bg-yellow-500/10 border-yellow-500/40' :
        'bg-green-500/10 border-green-500/40'
      ]">
        <div class="flex items-center gap-4 mb-4">
          <span :class="[
            'text-4xl font-bold',
            technicalAnalysis.combined_overall.status === 'danger' ? 'text-red-400' :
            technicalAnalysis.combined_overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
          ]">
            {{ technicalAnalysis.combined_overall.status === 'danger' ? '🔴 DANGER' : 
               technicalAnalysis.combined_overall.status === 'caution' ? '🟡 CAUTION' : '🟢 SAFE' }}
          </span>
          <div class="flex-1">
            <h3 class="text-xl font-bold mb-1">Kesimpulan Analisis Teknikal</h3>
            <p class="text-sm text-dark-400">Berdasarkan analisis M15, H1, dan H4 dengan 7 modul</p>
          </div>
          <div class="text-right">
            <div class="text-3xl font-bold" :class="[
              technicalAnalysis.combined_overall.status === 'danger' ? 'text-red-400' :
              technicalAnalysis.combined_overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
            ]">
              {{ technicalAnalysis.combined_overall.risk_score }}/100
            </div>
            <div class="text-xs text-dark-400">Risk Score</div>
            <div class="text-sm font-medium mt-1" :class="[
              technicalAnalysis.combined_overall.status === 'danger' ? 'text-red-400' :
              technicalAnalysis.combined_overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
            ]">
              {{ technicalAnalysis.combined_overall.confidence }}% Confidence
            </div>
          </div>
        </div>
        
        <p class="text-lg text-dark-200 mb-4 font-medium">{{ technicalAnalysis.combined_overall.recommendation }}</p>
        
        <!-- Stats Row -->
        <div class="flex flex-wrap gap-3 text-sm mb-4">
          <span class="px-3 py-1.5 bg-dark-800 rounded-lg">
            📊 Timeframes: <span class="text-white font-medium">3 (M15, H1, H4)</span>
          </span>
          <span class="px-3 py-1.5 bg-red-500/20 text-red-400 rounded-lg border border-red-500/30">
            🔴 Danger: {{ technicalAnalysis.combined_overall.timeframe_summary?.danger || 0 }}
          </span>
          <span class="px-3 py-1.5 bg-yellow-500/20 text-yellow-400 rounded-lg border border-yellow-500/30">
            🟡 Caution: {{ technicalAnalysis.combined_overall.timeframe_summary?.caution || 0 }}
          </span>
          <span class="px-3 py-1.5 bg-green-500/20 text-green-400 rounded-lg border border-green-500/30">
            🟢 Safe: {{ technicalAnalysis.combined_overall.timeframe_summary?.safe || 0 }}
          </span>
        </div>

        <!-- Timeframe Breakdown -->
        <div v-if="technicalAnalysis?.predictions" class="grid md:grid-cols-3 gap-3 mt-4">
          <!-- M15 Summary -->
          <div v-if="technicalAnalysis.predictions.M15" :class="[
            'p-3 rounded-lg border',
            technicalAnalysis.predictions.M15.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
            technicalAnalysis.predictions.M15.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
            'bg-green-500/5 border-green-500/20'
          ]">
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold text-sm">M15</span>
              <span :class="[
                'px-2 py-0.5 rounded text-xs font-medium',
                technicalAnalysis.predictions.M15.overall.status === 'danger' ? 'bg-red-500/30 text-red-300' :
                technicalAnalysis.predictions.M15.overall.status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                'bg-green-500/30 text-green-300'
              ]">
                {{ technicalAnalysis.predictions.M15.overall.status_label }}
              </span>
            </div>
            <div class="text-xs text-dark-400">
              Risk: <span class="font-bold" :class="[
                technicalAnalysis.predictions.M15.overall.status === 'danger' ? 'text-red-400' :
                technicalAnalysis.predictions.M15.overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
              ]">{{ technicalAnalysis.predictions.M15.overall.risk_score }}/100</span>
            </div>
          </div>

          <!-- H1 Summary -->
          <div v-if="technicalAnalysis.predictions.H1" :class="[
            'p-3 rounded-lg border',
            technicalAnalysis.predictions.H1.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
            technicalAnalysis.predictions.H1.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
            'bg-green-500/5 border-green-500/20'
          ]">
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold text-sm">H1</span>
              <span :class="[
                'px-2 py-0.5 rounded text-xs font-medium',
                technicalAnalysis.predictions.H1.overall.status === 'danger' ? 'bg-red-500/30 text-red-300' :
                technicalAnalysis.predictions.H1.overall.status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                'bg-green-500/30 text-green-300'
              ]">
                {{ technicalAnalysis.predictions.H1.overall.status_label }}
              </span>
            </div>
            <div class="text-xs text-dark-400">
              Risk: <span class="font-bold" :class="[
                technicalAnalysis.predictions.H1.overall.status === 'danger' ? 'text-red-400' :
                technicalAnalysis.predictions.H1.overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
              ]">{{ technicalAnalysis.predictions.H1.overall.risk_score }}/100</span>
            </div>
          </div>

          <!-- H4 Summary -->
          <div v-if="technicalAnalysis.predictions.H4" :class="[
            'p-3 rounded-lg border',
            technicalAnalysis.predictions.H4.overall.status === 'danger' ? 'bg-red-500/5 border-red-500/20' :
            technicalAnalysis.predictions.H4.overall.status === 'caution' ? 'bg-yellow-500/5 border-yellow-500/20' :
            'bg-green-500/5 border-green-500/20'
          ]">
            <div class="flex items-center justify-between mb-2">
              <span class="font-bold text-sm">H4</span>
              <span :class="[
                'px-2 py-0.5 rounded text-xs font-medium',
                technicalAnalysis.predictions.H4.overall.status === 'danger' ? 'bg-red-500/30 text-red-300' :
                technicalAnalysis.predictions.H4.overall.status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                'bg-green-500/30 text-green-300'
              ]">
                {{ technicalAnalysis.predictions.H4.overall.status_label }}
              </span>
            </div>
            <div class="text-xs text-dark-400">
              Risk: <span class="font-bold" :class="[
                technicalAnalysis.predictions.H4.overall.status === 'danger' ? 'text-red-400' :
                technicalAnalysis.predictions.H4.overall.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
              ]">{{ technicalAnalysis.predictions.H4.overall.risk_score }}/100</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Module Details per Timeframe -->
      <div v-if="technicalAnalysis?.predictions" class="space-y-4">
        <!-- M15 Modules -->
        <details v-if="technicalAnalysis.predictions.M15?.modules" class="group">
          <summary class="cursor-pointer text-sm font-semibold text-dark-300 mb-4 flex items-center gap-2 hover:text-white">
            <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            📊 Detail Modul M15 (7 modul)
          </summary>
          <div class="mt-4 space-y-3">
            <div 
              v-for="(module, key) in technicalAnalysis.predictions.M15.modules" 
              :key="key"
              :class="[
                'p-4 rounded-lg border',
                module.status === 'danger' || module.status === 'extreme_shock' || module.status === 'strong_trend' || module.status === 'shock' ? 'bg-red-500/5 border-red-500/20' :
                module.status === 'caution' || module.status === 'moderately_trending' || module.status === 'high_risk' ? 'bg-yellow-500/5 border-yellow-500/20' :
                module.status === 'insufficient_data' ? 'bg-gray-500/5 border-gray-500/20' :
                'bg-green-500/5 border-green-500/20'
              ]"
            >
              <div class="flex items-center justify-between mb-2">
                <div>
                  <h4 class="font-medium text-sm">{{ module.module }}</h4>
                  <p class="text-xs text-dark-400 mt-0.5">{{ module.status_label }}</p>
                </div>
                <div class="text-right">
                  <div class="text-sm font-bold" :class="[
                    module.status === 'danger' || module.status === 'extreme_shock' ? 'text-red-400' :
                    module.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
                  ]">
                    {{ module.confidence }}%
                  </div>
                </div>
              </div>
              
              <!-- Reasons -->
              <div v-if="module.reasons && module.reasons.length > 0" class="mt-2">
                <details class="text-xs">
                  <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                    📋 Alasan ({{ module.reasons.length }})
                  </summary>
                  <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-orange-500/30">
                    <div 
                      v-for="(reason, idx) in module.reasons" 
                      :key="idx"
                      class="p-2 bg-orange-500/5 rounded"
                    >
                      <div class="flex items-center justify-between mb-1">
                        <span class="font-medium text-orange-300">{{ reason.factor }}</span>
                        <span v-if="reason.weight > 0" class="text-orange-400">+{{ reason.weight }}%</span>
                      </div>
                      <div class="text-dark-400 text-xs mb-1">
                        <span class="font-medium">{{ reason.value }}</span>
                      </div>
                      <div class="text-dark-500 text-xs italic">
                        {{ reason.explanation }}
                      </div>
                    </div>
                  </div>
                </details>
              </div>
              
              <p class="text-xs text-dark-400 mt-2">{{ module.recommendation }}</p>
            </div>
          </div>
        </details>

        <!-- H1 Modules -->
        <details v-if="technicalAnalysis.predictions.H1?.modules" class="group">
          <summary class="cursor-pointer text-sm font-semibold text-dark-300 mb-4 flex items-center gap-2 hover:text-white">
            <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            📊 Detail Modul H1 (7 modul)
          </summary>
          <div class="mt-4 space-y-3">
            <div 
              v-for="(module, key) in technicalAnalysis.predictions.H1.modules" 
              :key="key"
              :class="[
                'p-4 rounded-lg border',
                module.status === 'danger' || module.status === 'extreme_shock' || module.status === 'strong_trend' || module.status === 'shock' ? 'bg-red-500/5 border-red-500/20' :
                module.status === 'caution' || module.status === 'moderately_trending' || module.status === 'high_risk' ? 'bg-yellow-500/5 border-yellow-500/20' :
                module.status === 'insufficient_data' ? 'bg-gray-500/5 border-gray-500/20' :
                'bg-green-500/5 border-green-500/20'
              ]"
            >
              <div class="flex items-center justify-between mb-2">
                <div>
                  <h4 class="font-medium text-sm">{{ module.module }}</h4>
                  <p class="text-xs text-dark-400 mt-0.5">{{ module.status_label }}</p>
                </div>
                <div class="text-right">
                  <div class="text-sm font-bold" :class="[
                    module.status === 'danger' || module.status === 'extreme_shock' ? 'text-red-400' :
                    module.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
                  ]">
                    {{ module.confidence }}%
                  </div>
                </div>
              </div>
              
              <!-- Reasons -->
              <div v-if="module.reasons && module.reasons.length > 0" class="mt-2">
                <details class="text-xs">
                  <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                    📋 Alasan ({{ module.reasons.length }})
                  </summary>
                  <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-orange-500/30">
                    <div 
                      v-for="(reason, idx) in module.reasons" 
                      :key="idx"
                      class="p-2 bg-orange-500/5 rounded"
                    >
                      <div class="flex items-center justify-between mb-1">
                        <span class="font-medium text-orange-300">{{ reason.factor }}</span>
                        <span v-if="reason.weight > 0" class="text-orange-400">+{{ reason.weight }}%</span>
                      </div>
                      <div class="text-dark-400 text-xs mb-1">
                        <span class="font-medium">{{ reason.value }}</span>
                      </div>
                      <div class="text-dark-500 text-xs italic">
                        {{ reason.explanation }}
                      </div>
                    </div>
                  </div>
                </details>
              </div>
              
              <p class="text-xs text-dark-400 mt-2">{{ module.recommendation }}</p>
            </div>
          </div>
        </details>

        <!-- H4 Modules -->
        <details v-if="technicalAnalysis.predictions.H4?.modules" class="group">
          <summary class="cursor-pointer text-sm font-semibold text-dark-300 mb-4 flex items-center gap-2 hover:text-white">
            <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            📊 Detail Modul H4 (7 modul)
          </summary>
          <div class="mt-4 space-y-3">
            <div 
              v-for="(module, key) in technicalAnalysis.predictions.H4.modules" 
              :key="key"
              :class="[
                'p-4 rounded-lg border',
                module.status === 'danger' || module.status === 'extreme_shock' || module.status === 'strong_trend' || module.status === 'shock' ? 'bg-red-500/5 border-red-500/20' :
                module.status === 'caution' || module.status === 'moderately_trending' || module.status === 'high_risk' ? 'bg-yellow-500/5 border-yellow-500/20' :
                module.status === 'insufficient_data' ? 'bg-gray-500/5 border-gray-500/20' :
                'bg-green-500/5 border-green-500/20'
              ]"
            >
              <div class="flex items-center justify-between mb-2">
                <div>
                  <h4 class="font-medium text-sm">{{ module.module }}</h4>
                  <p class="text-xs text-dark-400 mt-0.5">{{ module.status_label }}</p>
                </div>
                <div class="text-right">
                  <div class="text-sm font-bold" :class="[
                    module.status === 'danger' || module.status === 'extreme_shock' ? 'text-red-400' :
                    module.status === 'caution' ? 'text-yellow-400' : 'text-green-400'
                  ]">
                    {{ module.confidence }}%
                  </div>
                </div>
              </div>
              
              <!-- Reasons -->
              <div v-if="module.reasons && module.reasons.length > 0" class="mt-2">
                <details class="text-xs">
                  <summary class="cursor-pointer text-dark-400 hover:text-dark-300 mb-1">
                    📋 Alasan ({{ module.reasons.length }})
                  </summary>
                  <div class="mt-2 space-y-1.5 pl-2 border-l-2 border-orange-500/30">
                    <div 
                      v-for="(reason, idx) in module.reasons" 
                      :key="idx"
                      class="p-2 bg-orange-500/5 rounded"
                    >
                      <div class="flex items-center justify-between mb-1">
                        <span class="font-medium text-orange-300">{{ reason.factor }}</span>
                        <span v-if="reason.weight > 0" class="text-orange-400">+{{ reason.weight }}%</span>
                      </div>
                      <div class="text-dark-400 text-xs mb-1">
                        <span class="font-medium">{{ reason.value }}</span>
                      </div>
                      <div class="text-dark-500 text-xs italic">
                        {{ reason.explanation }}
                      </div>
                    </div>
                  </div>
                </details>
              </div>
              
              <p class="text-xs text-dark-400 mt-2">{{ module.recommendation }}</p>
            </div>
          </div>
        </details>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loadingTechnical && !technicalError" class="text-center py-8 text-dark-400">
        <span class="text-4xl mb-2 block">📊</span>
        <p>Klik "Analyze Technical" untuk memulai analisis.</p>
        <p class="text-sm mt-1">Pastikan TradingView chart terhubung dan mengirim data OHLC.</p>
      </div>
    </div>

    <!-- 📈 Learning Statistics (Collapsible) -->
    <div class="card p-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <span class="text-2xl">📈</span>
          <div>
            <h2 class="text-lg font-semibold">Learning Statistics</h2>
            <p class="text-sm text-dark-400">Pola historis dari news yang sudah ditandai</p>
          </div>
        </div>
        <button 
          @click="fetchPredictionStats"
          :disabled="loadingStats"
          class="flex items-center gap-2 px-3 py-1.5 text-sm bg-blue-600/20 text-blue-400 border border-blue-500/30 rounded-lg hover:bg-blue-600/30 transition-colors disabled:opacity-50"
        >
          <svg :class="['w-4 h-4', loadingStats && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
          {{ loadingStats ? 'Loading...' : 'Load Stats' }}
        </button>
      </div>

      <details class="group">
        <summary class="cursor-pointer text-sm font-semibold text-dark-300 hover:text-white mb-4 flex items-center gap-2">
          <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
          Tampilkan Detail Statistik
        </summary>

        <div class="mt-4">
          <!-- Loading State -->
          <div v-if="loadingStats" class="flex items-center justify-center py-8">
            <div class="spinner mr-2"></div>
            <span class="text-dark-400">Memuat statistik...</span>
          </div>

          <!-- Error State -->
          <div v-else-if="predictionStatsError" class="p-4 bg-red-500/10 border border-red-500/30 rounded-xl">
            <p class="text-sm text-red-400">Error: {{ predictionStatsError }}</p>
            <button @click="fetchPredictionStats" class="mt-2 text-xs text-red-300 hover:text-red-200">
              Coba lagi
            </button>
          </div>

          <!-- Stats Content -->
          <div v-else-if="predictionStats">
            <!-- Summary Stats -->
            <div class="grid grid-cols-3 gap-4 mb-6">
              <div class="p-4 bg-dark-800/50 rounded-xl border border-dark-700">
                <div class="text-xs text-dark-400 mb-1">Total Marked</div>
                <div class="text-2xl font-bold text-white">{{ predictionStats.total_marked || 0 }}</div>
                <div class="text-xs text-dark-500 mt-1">dalam {{ predictionStats.period_days }} hari</div>
              </div>
              <div class="p-4 bg-dark-800/50 rounded-xl border border-dark-700">
                <div class="text-xs text-dark-400 mb-1">Unique Events</div>
                <div class="text-2xl font-bold text-blue-400">{{ predictionStats.unique_events || 0 }}</div>
                <div class="text-xs text-dark-500 mt-1">event berbeda</div>
              </div>
              <div class="p-4 bg-dark-800/50 rounded-xl border border-dark-700">
                <div class="text-xs text-dark-400 mb-1">Learning Data</div>
                <div class="text-2xl font-bold text-purple-400">{{ predictionStats.events?.length || 0 }}</div>
                <div class="text-xs text-dark-500 mt-1">dengan statistik</div>
              </div>
            </div>

            <!-- High Impact Events Stats -->
            <div v-if="predictionStats?.high_impact_stats && Object.keys(predictionStats.high_impact_stats).length > 0" class="mb-6">
              <h3 class="text-sm font-semibold text-orange-400 mb-3">🔥 Analisis High-Impact Events</h3>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div 
                  v-for="(stat, type) in predictionStats.high_impact_stats" 
                  :key="type"
                  class="p-3 bg-dark-800/50 rounded-lg border border-dark-700"
                >
                  <div class="flex items-center justify-between mb-2">
                    <span class="font-medium text-sm">{{ type }}</span>
                    <span class="text-xs text-dark-400">{{ stat.total }} events</span>
                  </div>
                  <div class="space-y-1">
                    <div class="flex items-center justify-between text-xs">
                      <span class="text-red-400">Danger</span>
                      <span>{{ stat.danger_pct }}%</span>
                    </div>
                    <div class="w-full h-1 bg-dark-700 rounded-full overflow-hidden">
                      <div class="h-full bg-red-500 rounded-full" :style="{ width: stat.danger_pct + '%' }"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                      <span class="text-yellow-400">Caution</span>
                      <span>{{ stat.caution_pct }}%</span>
                    </div>
                    <div class="w-full h-1 bg-dark-700 rounded-full overflow-hidden">
                      <div class="h-full bg-yellow-500 rounded-full" :style="{ width: stat.caution_pct + '%' }"></div>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                      <span class="text-green-400">Safe</span>
                      <span>{{ stat.safe_pct }}%</span>
                    </div>
                    <div class="w-full h-1 bg-dark-700 rounded-full overflow-hidden">
                      <div class="h-full bg-green-500 rounded-full" :style="{ width: stat.safe_pct + '%' }"></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Learning Summary -->
            <div v-if="predictionStats?.learning_summary" class="grid md:grid-cols-2 gap-4">
              <div class="p-4 bg-red-500/5 border border-red-500/20 rounded-xl">
                <h4 class="text-sm font-semibold text-red-400 mb-2">🔴 News Paling Berbahaya</h4>
                <div v-if="predictionStats.learning_summary.most_dangerous?.length > 0" class="space-y-2">
                  <div 
                    v-for="(event, index) in predictionStats.learning_summary.most_dangerous.slice(0, 5)" 
                    :key="index"
                    class="flex items-center justify-between text-sm"
                  >
                    <div class="flex items-center gap-2">
                      <span :class="getCurrencyClass(event.currency)" class="px-1.5 py-0.5 rounded text-xs">
                        {{ event.currency }}
                      </span>
                      <span class="text-dark-300 truncate max-w-48">{{ event.title }}</span>
                    </div>
                    <span class="text-red-400 font-mono">{{ event.danger_pct }}%</span>
                  </div>
                </div>
                <p v-else class="text-sm text-dark-500">Belum ada data.</p>
              </div>
              <div class="p-4 bg-green-500/5 border border-green-500/20 rounded-xl">
                <h4 class="text-sm font-semibold text-green-400 mb-2">🟢 News Paling Aman</h4>
                <div v-if="predictionStats.learning_summary.most_safe?.length > 0" class="space-y-2">
                  <div 
                    v-for="(event, index) in predictionStats.learning_summary.most_safe.slice(0, 5)" 
                    :key="index"
                    class="flex items-center justify-between text-sm"
                  >
                    <div class="flex items-center gap-2">
                      <span :class="getCurrencyClass(event.currency)" class="px-1.5 py-0.5 rounded text-xs">
                        {{ event.currency }}
                      </span>
                      <span class="text-dark-300 truncate max-w-48">{{ event.title }}</span>
                    </div>
                    <span class="text-green-400 font-mono">{{ event.safe_pct }}%</span>
                  </div>
                </div>
                <p v-else class="text-sm text-dark-500">Belum ada data.</p>
              </div>
            </div>

            <!-- Advice -->
            <div v-if="predictionStats?.learning_summary?.advice" class="mt-4 p-4 bg-blue-500/5 border border-blue-500/20 rounded-xl">
              <p class="text-sm text-blue-400">💡 {{ predictionStats.learning_summary.advice }}</p>
            </div>
          </div>

          <!-- Empty State -->
          <div v-else-if="!loadingStats" class="text-center py-8 text-dark-400">
            <span class="text-4xl mb-2 block">📊</span>
            <p class="text-sm">Klik "Load Stats" untuk melihat statistik pembelajaran.</p>
            <p class="text-xs mt-1 text-dark-500">Tandai lebih banyak news untuk meningkatkan akurasi prediksi.</p>
          </div>
        </div>
      </details>
    </div>

    <!-- News Table -->
    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-dark-800/50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider w-12">
                <input 
                  type="checkbox" 
                  @change="toggleSelectAll"
                  :checked="selectedNews.length === filteredNews.length && filteredNews.length > 0"
                  class="rounded border-dark-600 bg-dark-800 text-primary-500 focus:ring-primary-500"
                />
              </th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Date</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Time</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Currency</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Impact</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Event</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Actual</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Forecast</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Previous</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">EA Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-dark-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-dark-800">
            <tr v-if="loading">
              <td colspan="10" class="px-4 py-12 text-center">
                <div class="flex items-center justify-center gap-3">
                  <div class="spinner"></div>
                  <span class="text-dark-400">Loading news...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="filteredNews.length === 0">
              <td colspan="10" class="px-4 py-12 text-center text-dark-400">
                <div class="flex flex-col items-center gap-2">
                  <svg class="w-12 h-12 text-dark-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                  </svg>
                  <span>No news found for this date</span>
                  <button @click="syncNews" class="mt-2 text-primary-400 hover:text-primary-300">
                    Click to sync news
                  </button>
                </div>
              </td>
            </tr>
            <tr 
              v-for="news in filteredNews" 
              :key="news.id"
              :class="[
                'hover:bg-dark-800/30 transition-colors',
                news.should_disable_ea && 'bg-red-500/5'
              ]"
            >
              <td class="px-4 py-3">
                <input 
                  type="checkbox" 
                  :value="news.id"
                  v-model="selectedNews"
                  class="rounded border-dark-600 bg-dark-800 text-primary-500 focus:ring-primary-500"
                />
              </td>
              <td class="px-4 py-3 font-mono text-xs text-dark-300">
                {{ formatDate(news) }}
              </td>
              <td class="px-4 py-3 font-mono text-sm">
                {{ news.formatted_time || 'All Day' }}
              </td>
              <td class="px-4 py-3">
                <span :class="getCurrencyClass(news.currency)" class="px-2 py-1 rounded text-sm font-semibold">
                  {{ news.currency }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span :class="getImpactClass(news.impact)" class="px-2 py-1 rounded text-xs font-medium flex items-center gap-1 w-fit">
                  <span :class="getImpactDotClass(news.impact)" class="w-2 h-2 rounded-full"></span>
                  {{ news.impact }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="font-medium">{{ news.title }}</div>
                <div v-if="news.user_notes" class="text-xs text-dark-400 mt-1">
                  📝 {{ news.user_notes }}
                </div>
              </td>
              <td class="px-4 py-3 font-mono text-sm" :class="getValueClass(news.actual, news.forecast)">
                {{ news.actual || '-' }}
              </td>
              <td class="px-4 py-3 font-mono text-sm text-dark-400">
                {{ news.forecast || '-' }}
              </td>
              <td class="px-4 py-3 font-mono text-sm text-dark-400">
                {{ news.previous || '-' }}
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <button 
                    @click="cycleEaStatus(news)"
                    :class="[
                      'px-2 py-1 rounded text-xs font-medium border transition-colors',
                      news.ea_status_color
                    ]"
                    :title="news.ea_status ? `EA Status: ${news.ea_status.toUpperCase()}` : 'Klik untuk menandai EA Status'"
                  >
                    {{ news.ea_status ? news.ea_status.toUpperCase() : '—' }}
                  </button>
                  <div v-if="news.should_disable_ea" class="text-xs text-red-400" title="EA will be disabled">
                    ⚠️
                  </div>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <button 
                    @click="openEditModal(news)"
                    class="p-1.5 hover:bg-dark-700 rounded-lg transition-colors text-dark-400 hover:text-white"
                    title="Edit EA Settings"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <button 
                    @click="toggleDisableEa(news)"
                    :class="[
                      'p-1.5 rounded-lg transition-colors',
                      news.should_disable_ea ? 'bg-red-500/20 text-red-400' : 'hover:bg-dark-700 text-dark-400 hover:text-white'
                    ]"
                    :title="news.should_disable_ea ? 'EA Disabled' : 'Click to disable EA'"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                  </button>
                  <button 
                    @click="deleteNews(news)"
                    class="p-1.5 hover:bg-red-600/20 rounded-lg transition-colors text-red-400"
                    title="Delete event"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 7h12M10 11v6m4-6v6M9 7l1-2h4l1 2M5 7h14l-1 12H6L5 7z"/>
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 🔥 High-Impact Events Reference Card -->
    <div class="card p-6">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
          <span class="text-2xl">📚</span>
          <div>
            <h2 class="text-lg font-semibold">Referensi High-Impact News</h2>
            <p class="text-sm text-dark-400">Catatan: Impact bisa bervariasi, gunakan sebagai pertimbangan saja</p>
          </div>
        </div>
      </div>

      <!-- Notable Events Today -->
      <div v-if="notableEvents.length > 0" class="mb-6">
        <h3 class="text-sm font-semibold text-orange-400 mb-3">🔥 Notable Events Hari Ini ({{ selectedDate }})</h3>
        <div class="space-y-3">
          <div 
            v-for="event in notableEvents" 
            :key="event.id"
            class="p-4 bg-dark-800/50 border border-dark-700 rounded-xl"
          >
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2">
                <span class="px-2 py-1 text-xs font-bold bg-orange-500/20 text-orange-400 rounded">
                  {{ event.type }}
                </span>
                <span class="font-medium">{{ event.title }}</span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm text-dark-400">{{ event.time }}</span>
                <span :class="getCurrencyClass(event.currency)" class="px-2 py-0.5 rounded text-xs">
                  {{ event.currency }}
                </span>
                <span :class="getImpactClass(event.actual_impact)" class="px-2 py-0.5 rounded text-xs">
                  {{ event.actual_impact }}
                </span>
              </div>
            </div>
            
            <p class="text-sm text-dark-400 mb-2">{{ event.note }}</p>
            
            <!-- Historical Stats if available -->
            <div v-if="event.historical_stats" class="flex items-center gap-4 text-xs">
              <span class="text-dark-500">Berdasarkan {{ event.historical_stats.total }} history:</span>
              <span class="text-red-400">Danger {{ event.historical_stats.danger_pct }}%</span>
              <span class="text-yellow-400">Caution {{ event.historical_stats.caution_pct }}%</span>
              <span class="text-green-400">Safe {{ event.historical_stats.safe_pct }}%</span>
            </div>
            
            <!-- Current EA Status -->
            <div v-if="event.ea_status" class="mt-2">
              <span class="text-xs text-dark-500">Status saat ini: </span>
              <span :class="[
                'px-2 py-0.5 rounded text-xs font-medium',
                event.ea_status === 'danger' ? 'bg-red-500/30 text-red-300' :
                event.ea_status === 'caution' ? 'bg-yellow-500/30 text-yellow-300' :
                'bg-green-500/30 text-green-300'
              ]">{{ event.ea_status?.toUpperCase() }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Reference Guide -->
      <details class="group">
        <summary class="cursor-pointer flex items-center gap-2 text-sm font-semibold text-dark-300 hover:text-white">
          <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
          📖 Panduan High-Impact Events
        </summary>
        
        <div class="mt-4 grid md:grid-cols-2 gap-4">
          <div v-for="(info, index) in referenceInfo" :key="index" class="p-4 bg-dark-800/30 rounded-xl border border-dark-700/50">
            <div class="flex items-center gap-2 mb-2">
              <span class="px-2 py-1 text-xs font-bold bg-orange-500/20 text-orange-400 rounded">{{ info.type }}</span>
              <span class="font-medium text-sm">{{ info.name }}</span>
            </div>
            <p class="text-xs text-dark-400 mb-2">{{ info.description }}</p>
            <div class="flex items-center justify-between text-xs">
              <span class="text-dark-500">Typical Impact: 
                <span :class="info.typical_impact === 'High' ? 'text-red-400' : info.typical_impact === 'Medium' ? 'text-yellow-400' : 'text-dark-300'">
                  {{ info.typical_impact }}
                </span>
              </span>
            </div>
            <p class="text-xs text-blue-400 mt-1">💡 {{ info.advice }}</p>
          </div>
        </div>
      </details>
      
      <!-- Disclaimer -->
      <div class="mt-4 p-3 bg-yellow-500/5 border border-yellow-500/20 rounded-lg">
        <p class="text-xs text-yellow-400/80">
          ⚠️ <strong>Catatan Penting:</strong> Impact news bisa bervariasi tergantung rilis (contoh: Final GDP biasanya low impact, Advance GDP biasanya high impact). 
          Selalu cek actual impact dari ForexFactory dan sesuaikan dengan pengalaman trading Anda.
        </p>
      </div>
    </div>

    <!-- Bulk Actions -->
    <div v-if="selectedNews.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50">
      <div class="flex items-center gap-3 px-6 py-3 bg-dark-800 border border-dark-700 rounded-xl shadow-xl">
        <span class="text-sm text-dark-300">{{ selectedNews.length }} selected</span>
        <div class="w-px h-6 bg-dark-700"></div>
        <button @click="bulkMark(null)" class="px-3 py-1.5 text-sm bg-dark-700 text-dark-300 rounded-lg hover:bg-dark-600 transition-colors border border-dark-600">
          Mark Empty
        </button>
        <button @click="bulkMark('safe')" class="px-3 py-1.5 text-sm bg-green-500/20 text-green-400 rounded-lg hover:bg-green-500/30 transition-colors">
          Mark Safe
        </button>
        <button @click="bulkMark('caution')" class="px-3 py-1.5 text-sm bg-yellow-500/20 text-yellow-400 rounded-lg hover:bg-yellow-500/30 transition-colors">
          Mark Caution
        </button>
        <button @click="bulkMark('danger')" class="px-3 py-1.5 text-sm bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 transition-colors">
          Mark Danger
        </button>
        <button @click="selectedNews = []" class="p-1.5 hover:bg-dark-700 rounded-lg transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>

    <!-- Edit Modal -->
    <Teleport to="body">
      <div v-if="editingNews" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60" @click="editingNews = null"></div>
        <div class="relative w-full max-w-lg bg-dark-900 border border-dark-800 rounded-2xl shadow-xl">
          <div class="p-6">
            <h3 class="text-lg font-semibold mb-4">Edit EA Settings</h3>
            
            <div class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm text-dark-400 mb-2">Date</label>
                  <input
                    type="date"
                    v-model="editForm.date"
                    class="input"
                  />
                </div>
                <div>
                  <label class="block text-sm text-dark-400 mb-2">Time</label>
                  <input
                    type="time"
                    v-model="editForm.time"
                    class="input"
                  />
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm text-dark-400 mb-2">Currency</label>
                  <select v-model="editForm.currency" class="input">
                    <option value="">Select currency</option>
                    <option v-for="cur in currencies" :key="cur" :value="cur">{{ cur }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm text-dark-400 mb-2">Impact</label>
                  <select v-model="editForm.impact" class="input">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                  </select>
                </div>
              </div>

              <div>
                <label class="block text-sm text-dark-400 mb-2">Event Title</label>
                <input
                  type="text"
                  v-model="editForm.title"
                  class="input"
                  placeholder="Event title"
                />
              </div>

              <div>
                <label class="block text-sm text-dark-400 mb-2">EA Status</label>
                <div class="grid grid-cols-4 gap-2">
                  <button 
                    v-for="status in [null, 'safe', 'caution', 'danger']"
                    :key="status || 'empty'"
                    @click="editForm.ea_status = status"
                    :class="[
                      'px-3 py-2 rounded-lg text-sm font-medium border transition-colors',
                      editForm.ea_status === status 
                        ? status === 'safe' ? 'bg-green-500/20 text-green-400 border-green-500/50' :
                          status === 'caution' ? 'bg-yellow-500/20 text-yellow-400 border-yellow-500/50' :
                          status === 'danger' ? 'bg-red-500/20 text-red-400 border-red-500/50' :
                          'bg-dark-800/50 text-dark-400 border-dark-700'
                        : 'bg-dark-800 text-dark-400 border-dark-700 hover:border-dark-600'
                    ]"
                  >
                    {{ status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Empty' }}
                  </button>
                </div>
              </div>

              <div class="flex items-center gap-3">
                <input 
                  type="checkbox" 
                  id="should_disable_ea"
                  v-model="editForm.should_disable_ea"
                  class="rounded border-dark-600 bg-dark-800 text-primary-500 focus:ring-primary-500"
                />
                <label for="should_disable_ea" class="text-sm">Disable EA during this event</label>
              </div>

              <div v-if="editForm.should_disable_ea" class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm text-dark-400 mb-2">Minutes Before</label>
                  <input 
                    type="number" 
                    v-model.number="editForm.disable_minutes_before"
                    min="0" max="120"
                    class="input"
                  />
                </div>
                <div>
                  <label class="block text-sm text-dark-400 mb-2">Minutes After</label>
                  <input 
                    type="number" 
                    v-model.number="editForm.disable_minutes_after"
                    min="0" max="120"
                    class="input"
                  />
                </div>
              </div>

              <div>
                <label class="block text-sm text-dark-400 mb-2">Notes</label>
                <textarea 
                  v-model="editForm.user_notes"
                  rows="3"
                  class="input"
                  placeholder="Add notes about this event..."
                ></textarea>
              </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <button 
                @click="editingNews = null"
                class="px-4 py-2 text-dark-400 hover:text-white transition-colors"
              >
                Cancel
              </button>
              <button 
                @click="saveEaSettings"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-500 transition-colors"
              >
                Save Changes
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/services/api'
import dayjs from 'dayjs'

// State
const loading = ref(false)
const syncing = ref(false)
const news = ref([])
const stats = ref({})
const eaRecommendation = ref(null)
const selectedDate = ref(dayjs().format('YYYY-MM-DD'))
const selectedNews = ref([])
const editingNews = ref(null)

// Prediction State
const loadingPredictions = ref(false)
const loadingStats = ref(false)
const loadingNotableEvents = ref(false)

// Prediction 1: ForexFactory Impact Based
const forexfactoryPredictions = ref([])
const forexfactoryDayPrediction = ref(null)
const forexfactorySafeWindows = ref([])
const forexfactoryDangerWindows = ref([])

// Prediction 2: Historical Marking Based
const historicalPredictions = ref([])
const historicalDayPrediction = ref(null)
const historicalSafeWindows = ref([])
const historicalDangerWindows = ref([])

const predictionStats = ref(null)
const predictionStatsError = ref(null)
const notableEvents = ref([])
const referenceInfo = ref([])

// Prediction 3: Technical Analysis
const technicalAnalysis = ref(null)
const loadingTechnical = ref(false)
const technicalError = ref(null)
const tradingViewWidget = ref(null)

const editForm = ref({
  date: '',
  time: '',
  title: '',
  currency: '',
  impact: 'medium',
  actual: '',
  forecast: '',
  previous: '',
  ea_status: null,
  should_disable_ea: false,
  disable_minutes_before: 30,
  disable_minutes_after: 30,
  user_notes: '',
})

// Filters
const filterImpact = ref('all')
const filterCurrency = ref('')
const filterEaStatus = ref('')

const currencies = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'NZD', 'CNY']

// Computed
const filteredNews = computed(() => {
  let result = news.value

  if (filterImpact.value !== 'all') {
    result = result.filter(n => n.impact === filterImpact.value)
  }

  if (filterCurrency.value) {
    result = result.filter(n => n.currency === filterCurrency.value)
  }

  if (filterEaStatus.value) {
    if (filterEaStatus.value === 'empty') {
      result = result.filter(n => !n.ea_status || n.ea_status === '')
    } else {
      result = result.filter(n => n.ea_status === filterEaStatus.value)
    }
  }

  // Sort by time ascending (00:00 -> 23:59). Items tanpa time di akhir.
  return [...result].sort((a, b) => {
    const ta = a.time || ''
    const tb = b.time || ''
    if (!ta && !tb) return 0
    if (!ta) return 1
    if (!tb) return -1
    return ta.localeCompare(tb)
  })
})

// Methods
const fetchNews = async () => {
  loading.value = true
  try {
    const response = await api.get(`/news/date/${selectedDate.value}`)
    news.value = response.data.news || []
    stats.value = response.data.stats || {}
    eaRecommendation.value = response.data.ea_recommendation
  } catch (error) {
    console.error('Failed to fetch news:', error)
  } finally {
    loading.value = false
  }
}

const fetchEaRecommendation = async () => {
  try {
    const response = await api.get('/news/ea-recommendation')
    eaRecommendation.value = response.data
  } catch (error) {
    console.error('Failed to fetch EA recommendation:', error)
  }
}

const fetchPredictions = async () => {
  loadingPredictions.value = true
  try {
    const response = await api.get('/news/predictions', {
      params: { date: selectedDate.value }
    })
    
    // Prediction 1: ForexFactory Impact Based
    if (response.data.prediction_1_forexfactory) {
      forexfactoryPredictions.value = response.data.prediction_1_forexfactory.predictions || []
      forexfactoryDayPrediction.value = response.data.prediction_1_forexfactory.day_prediction || null
      forexfactorySafeWindows.value = response.data.prediction_1_forexfactory.safe_windows || []
      forexfactoryDangerWindows.value = response.data.prediction_1_forexfactory.danger_windows || []
    }
    
    // Prediction 2: Historical Marking Based
    if (response.data.prediction_2_historical) {
      historicalPredictions.value = response.data.prediction_2_historical.predictions || []
      historicalDayPrediction.value = response.data.prediction_2_historical.day_prediction || null
      historicalSafeWindows.value = response.data.prediction_2_historical.safe_windows || []
      historicalDangerWindows.value = response.data.prediction_2_historical.danger_windows || []
    }
  } catch (error) {
    console.error('Failed to fetch predictions:', error)
  } finally {
    loadingPredictions.value = false
  }
}

const fetchNotableEvents = async () => {
  loadingNotableEvents.value = true
  try {
    const response = await api.get('/news/notable-events', {
      params: { date: selectedDate.value }
    })
    notableEvents.value = response.data.notable_events || []
    referenceInfo.value = response.data.reference_info || []
  } catch (error) {
    console.error('Failed to fetch notable events:', error)
  } finally {
    loadingNotableEvents.value = false
  }
}

const fetchPredictionStats = async () => {
  loadingStats.value = true
  predictionStatsError.value = null
  
  try {
    console.log('Fetching prediction stats...')
    const response = await api.get('/news/prediction-stats', {
      params: { days: 90 }
    })
    console.log('Prediction stats response:', response.data)
    predictionStats.value = response.data
    predictionStatsError.value = null
  } catch (error) {
    console.error('Failed to fetch prediction stats:', error)
    predictionStatsError.value = error.response?.data?.message || error.message || 'Gagal memuat statistik'
    
    // Show user-friendly error
    if (error.response?.status === 404) {
      predictionStatsError.value = 'Endpoint tidak ditemukan. Pastikan backend sudah ter-update.'
    } else if (error.response?.status === 500) {
      predictionStatsError.value = 'Server error. Coba lagi nanti.'
    }
  } finally {
    loadingStats.value = false
  }
}

const fetchTechnicalAnalysis = async (ohlcData = null) => {
  loadingTechnical.value = true
  technicalError.value = null
  
  try {
    console.log('Fetching technical analysis...')
    
    // If no OHLC data provided, try to get from TradingView widget
    if (!ohlcData) {
      // For now, we'll use a placeholder structure
      // In production, this should fetch from TradingView widget
      ohlcData = await getOHLCFromTradingView()
    }
    
    if (!ohlcData || !ohlcData.H1 || ohlcData.H1.length === 0) {
      technicalError.value = 'Data OHLC tidak tersedia. Pastikan TradingView chart terhubung atau gunakan data sample untuk testing.'
      loadingTechnical.value = false
      return
    }
    
    console.log('Sending OHLC data to backend:', ohlcData)
    const response = await api.post('/news/technical-analysis', {
      ohlc: ohlcData,
      symbol: 'XAUUSD',
    })
    
    console.log('Technical analysis response:', response.data)
    technicalAnalysis.value = response.data.prediction_3_technical
    
    // Log predictions per timeframe
    if (technicalAnalysis.value?.predictions) {
      console.log('M15 Prediction:', technicalAnalysis.value.predictions.M15)
      console.log('H1 Prediction:', technicalAnalysis.value.predictions.H1)
      console.log('H4 Prediction:', technicalAnalysis.value.predictions.H4)
    }
  } catch (error) {
    console.error('Failed to fetch technical analysis:', error)
    technicalError.value = error.response?.data?.message || error.message || 'Gagal mengambil analisis teknikal'
    
    // Show user-friendly error
    if (error.response?.status === 404) {
      technicalError.value = 'Endpoint tidak ditemukan. Pastikan backend sudah ter-update.'
    } else if (error.response?.status === 422) {
      technicalError.value = 'Data OHLC tidak valid: ' + (error.response?.data?.message || 'Format data salah')
    } else if (error.response?.status === 500) {
      technicalError.value = 'Server error. Coba lagi nanti.'
    }
  } finally {
    loadingTechnical.value = false
  }
}

// Placeholder function to get OHLC from TradingView
// In production, this should integrate with TradingView widget API
const getOHLCFromTradingView = async () => {
  // This is a placeholder - in production, you would:
  // 1. Use TradingView widget's getBars() method
  // 2. Or use TradingView's REST API
  // 3. Or allow manual input
  
  // For testing, return sample data
  // In production, return null and show error message
  console.log('TradingView widget not integrated yet. Using sample data for testing.')
  
  // Generate sample OHLC data for testing
  const basePrice = 2650.0 // XAUUSD base price
  const now = Date.now()
  const oneHour = 60 * 60 * 1000
  const oneMinute = 60 * 1000
  
  // Generate H1 data (last 30 hours)
  const h1Data = []
  for (let i = 30; i >= 0; i--) {
    const time = now - (i * oneHour)
    const open = basePrice + (Math.random() - 0.5) * 10
    const high = open + Math.random() * 5
    const low = open - Math.random() * 5
    const close = open + (Math.random() - 0.5) * 8
    h1Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  // Generate H4 data (last 20 periods)
  const h4Data = []
  for (let i = 20; i >= 0; i--) {
    const time = now - (i * oneHour * 4)
    const open = basePrice + (Math.random() - 0.5) * 15
    const high = open + Math.random() * 8
    const low = open - Math.random() * 8
    const close = open + (Math.random() - 0.5) * 12
    h4Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  // Generate 15m data (last 30 periods)
  const m15Data = []
  for (let i = 30; i >= 0; i--) {
    const time = now - (i * oneMinute * 15)
    const open = basePrice + (Math.random() - 0.5) * 8
    const high = open + Math.random() * 3
    const low = open - Math.random() * 3
    const close = open + (Math.random() - 0.5) * 5
    m15Data.push({
      open: parseFloat(open.toFixed(2)),
      high: parseFloat(high.toFixed(2)),
      low: parseFloat(low.toFixed(2)),
      close: parseFloat(close.toFixed(2)),
      time: new Date(time).toISOString()
    })
  }
  
  return {
    '1m': [], // Optional
    '5m': [], // Optional
    '15m': m15Data,
    'H1': h1Data, // Required
    'H4': h4Data, // Optional but recommended
  }
}

const syncNews = async () => {
  syncing.value = true
  try {
    const response = await api.post('/news/sync', { date: selectedDate.value })
    console.log('Sync response:', response.data)
    await fetchNews()
  } catch (error) {
    console.error('Failed to sync news:', error)
    alert('Failed to sync: ' + (error.response?.data?.message || error.message))
  } finally {
    syncing.value = false
  }
}

const syncWeek = async () => {
  const ok = window.confirm(
    'Sync seluruh news untuk 1 minggu ini dari ForexFactory?\n\nProses ini mungkin memakan waktu beberapa detik.'
  )
  if (!ok) return

  syncing.value = true
  try {
    const response = await api.post('/news/sync-week')
    console.log('Sync week response:', response.data)
    const { synced, updated, dates_processed } = response.data
    alert(`Sync berhasil!\n\nNews baru: ${synced}\nNews diupdate: ${updated}\nTanggal: ${dates_processed?.join(', ') || '-'}`)
    await fetchNews()
  } catch (error) {
    console.error('Failed to sync week:', error)
    alert('Failed to sync week: ' + (error.response?.data?.message || error.message))
  } finally {
    syncing.value = false
  }
}

const resyncNews = async () => {
  const ok = window.confirm(
    'Hapus semua news hasil auto-sync untuk tanggal ini dan sync ulang?\n\nCatatan: entry manual (is_manual) akan tetap disimpan.',
  )
  if (!ok) return

  syncing.value = true
  try {
    await api.post('/news/clear', { date: selectedDate.value })
    await api.post('/news/sync', { date: selectedDate.value })
    selectedNews.value = []
    await fetchNews()
  } catch (error) {
    console.error('Failed to resync news:', error)
  } finally {
    syncing.value = false
  }
}

const openCreateModal = () => {
  // Use an empty object so the modal (v-if="editingNews") becomes visible.
  // The absence of `id` will make saveEaSettings() create a new record.
  editingNews.value = {}
  editForm.value = {
    date: selectedDate.value,
    time: '',
    title: '',
    currency: '',
    impact: 'medium',
    actual: '',
    forecast: '',
    previous: '',
    ea_status: null,
    should_disable_ea: false,
    disable_minutes_before: 30,
    disable_minutes_after: 30,
    user_notes: '',
  }
}

const prevDay = () => {
  selectedDate.value = dayjs(selectedDate.value).subtract(1, 'day').format('YYYY-MM-DD')
}

const nextDay = () => {
  selectedDate.value = dayjs(selectedDate.value).add(1, 'day').format('YYYY-MM-DD')
}

const goToToday = () => {
  selectedDate.value = dayjs().format('YYYY-MM-DD')
}

const toggleSelectAll = (e) => {
  if (e.target.checked) {
    selectedNews.value = filteredNews.value.map(n => n.id)
  } else {
    selectedNews.value = []
  }
}

const cycleEaStatus = async (newsItem) => {
  // Cycle: null/empty -> safe -> caution -> danger -> null/empty
  const statuses = [null, 'safe', 'caution', 'danger']
  const currentStatus = newsItem.ea_status || null
  const currentIndex = statuses.indexOf(currentStatus)
  const nextIndex = (currentIndex + 1) % statuses.length
  const nextStatus = statuses[nextIndex]
  
  try {
    await api.put(`/news/${newsItem.id}/mark-ea`, {
      ea_status: nextStatus, // null will be sent as null, which backend should accept
      should_disable_ea: nextStatus === 'danger',
    })
    await fetchNews()
  } catch (error) {
    console.error('Failed to update EA status:', error)
  }
}

const toggleDisableEa = async (newsItem) => {
  try {
    await api.put(`/news/${newsItem.id}/mark-ea`, {
      ea_status: newsItem.ea_status,
      should_disable_ea: !newsItem.should_disable_ea,
    })
    await fetchNews()
  } catch (error) {
    console.error('Failed to toggle disable EA:', error)
  }
}

const openEditModal = (newsItem) => {
  editingNews.value = newsItem
  
  // Parse time - handle various formats
  let timeValue = ''
  if (newsItem.time) {
    // If it's already in HH:mm:ss format
    if (/^\d{2}:\d{2}:\d{2}$/.test(newsItem.time)) {
      timeValue = newsItem.time.substring(0, 5)
    } else if (/^\d{2}:\d{2}$/.test(newsItem.time)) {
      timeValue = newsItem.time
    } else {
      // Try to parse with dayjs
      const parsed = dayjs(newsItem.time, ['HH:mm:ss', 'HH:mm', 'h:mma', 'h:mm a'])
      if (parsed.isValid()) {
        timeValue = parsed.format('HH:mm')
      }
    }
  }
  
  editForm.value = {
    date: newsItem.date ? dayjs(newsItem.date).format('YYYY-MM-DD') : selectedDate.value,
    time: timeValue,
    title: newsItem.title || '',
    currency: newsItem.currency || '',
    impact: newsItem.impact || 'medium',
    actual: newsItem.actual || '',
    forecast: newsItem.forecast || '',
    previous: newsItem.previous || '',
    ea_status: newsItem.ea_status || null,
    should_disable_ea: newsItem.should_disable_ea || false,
    disable_minutes_before: newsItem.disable_minutes_before || 30,
    disable_minutes_after: newsItem.disable_minutes_after || 30,
    user_notes: newsItem.user_notes || '',
  }
}

const saveEaSettings = async () => {
  try {
    const payload = {
      date: editForm.value.date || selectedDate.value,
      time: editForm.value.time || null,
      title: editForm.value.title,
      currency: editForm.value.currency,
      impact: editForm.value.impact,
      actual: editForm.value.actual || null,
      forecast: editForm.value.forecast || null,
      previous: editForm.value.previous || null,
      ea_status: editForm.value.ea_status,
      should_disable_ea: editForm.value.should_disable_ea,
      disable_minutes_before: editForm.value.disable_minutes_before,
      disable_minutes_after: editForm.value.disable_minutes_after,
      user_notes: editForm.value.user_notes || null,
    }

    if (editingNews.value && editingNews.value.id) {
      const response = await api.put(`/news/${editingNews.value.id}`, payload)
      console.log('Update response:', response.data)
    } else {
      const response = await api.post('/news', payload)
      console.log('Create response:', response.data)
    }

    editingNews.value = null
    await fetchNews()
  } catch (error) {
    console.error('Failed to save EA settings:', error)
    const message = error.response?.data?.message || error.response?.data?.errors || error.message
    alert(`Error saving: ${JSON.stringify(message)}`)
  }
}

const deleteNews = async (newsItem) => {
  const ok = window.confirm(`Delete event "${newsItem.title}"?`)
  if (!ok) return

  try {
    const response = await api.delete(`/news/${newsItem.id}`)
    console.log('Delete response:', response.data)
    await fetchNews()
  } catch (error) {
    console.error('Failed to delete news:', error)
    const message = error.response?.data?.message || error.message
    alert(`Error deleting: ${message}`)
  }
}

const bulkMark = async (status) => {
  try {
    await api.post('/news/bulk-mark-ea', {
      news_ids: selectedNews.value,
      ea_status: status, // null untuk empty
      should_disable_ea: status === 'danger',
    })
    selectedNews.value = []
    await fetchNews()
  } catch (error) {
    console.error('Failed to bulk mark:', error)
    alert('Failed to mark news: ' + (error.response?.data?.message || error.message))
  }
}

const getCurrencyClass = (currency) => {
  const classes = {
    'USD': 'bg-green-500/20 text-green-400',
    'EUR': 'bg-blue-500/20 text-blue-400',
    'GBP': 'bg-purple-500/20 text-purple-400',
    'JPY': 'bg-red-500/20 text-red-400',
    'CHF': 'bg-orange-500/20 text-orange-400',
    'CAD': 'bg-cyan-500/20 text-cyan-400',
    'AUD': 'bg-yellow-500/20 text-yellow-400',
    'NZD': 'bg-pink-500/20 text-pink-400',
    'CNY': 'bg-rose-500/20 text-rose-400',
  }
  return classes[currency] || 'bg-gray-500/20 text-gray-400'
}

const getImpactClass = (impact) => {
  const classes = {
    'high': 'bg-red-500/20 text-red-400',
    'medium': 'bg-yellow-500/20 text-yellow-400',
    'low': 'bg-green-500/20 text-green-400',
  }
  return classes[impact] || 'bg-gray-500/20 text-gray-400'
}

const getImpactDotClass = (impact) => {
  const classes = {
    'high': 'bg-red-500',
    'medium': 'bg-yellow-500',
    'low': 'bg-green-500',
  }
  return classes[impact] || 'bg-gray-500'
}

const getValueClass = (actual, forecast) => {
  if (!actual || !forecast) return 'text-dark-300'
  
  const actualNum = parseFloat(actual.replace(/[^0-9.-]/g, ''))
  const forecastNum = parseFloat(forecast.replace(/[^0-9.-]/g, ''))
  
  if (isNaN(actualNum) || isNaN(forecastNum)) return 'text-dark-300'
  
  if (actualNum > forecastNum) return 'text-green-400'
  if (actualNum < forecastNum) return 'text-red-400'
  return 'text-dark-300'
}

const formatDate = (item) => {
  // Prefer backend formatted_date if available
  if (item.formatted_date) return item.formatted_date
  if (item.date) return dayjs(item.date).format('YYYY-MM-DD')
  return selectedDate.value
}

// Watchers
watch(selectedDate, () => {
  fetchNews()
  fetchPredictions()
  fetchNotableEvents()
})

// Lifecycle
onMounted(() => {
  fetchNews()
  fetchEaRecommendation()
  fetchPredictions()
  fetchNotableEvents()
  
  // Refresh EA recommendation every minute
  setInterval(fetchEaRecommendation, 60000)
  
  // Auto-fetch technical analysis every 5 minutes if available
  // setInterval(() => {
  //   if (tradingViewWidget.value) {
  //     fetchTechnicalAnalysis()
  //   }
  // }, 300000)
})
</script>

