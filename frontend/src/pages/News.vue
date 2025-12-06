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
            <option value="safe">Safe</option>
            <option value="caution">Caution</option>
            <option value="danger">Danger</option>
            <option value="unknown">Unknown</option>
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
                  >
                    {{ news.ea_status?.toUpperCase() || 'UNKNOWN' }}
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

    <!-- Bulk Actions -->
    <div v-if="selectedNews.length > 0" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50">
      <div class="flex items-center gap-3 px-6 py-3 bg-dark-800 border border-dark-700 rounded-xl shadow-xl">
        <span class="text-sm text-dark-300">{{ selectedNews.length }} selected</span>
        <div class="w-px h-6 bg-dark-700"></div>
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
                    v-for="status in ['safe', 'caution', 'danger', 'unknown']"
                    :key="status"
                    @click="editForm.ea_status = status"
                    :class="[
                      'px-3 py-2 rounded-lg text-sm font-medium border transition-colors',
                      editForm.ea_status === status 
                        ? status === 'safe' ? 'bg-green-500/20 text-green-400 border-green-500/50' :
                          status === 'caution' ? 'bg-yellow-500/20 text-yellow-400 border-yellow-500/50' :
                          status === 'danger' ? 'bg-red-500/20 text-red-400 border-red-500/50' :
                          'bg-gray-500/20 text-gray-400 border-gray-500/50'
                        : 'bg-dark-800 text-dark-400 border-dark-700 hover:border-dark-600'
                    ]"
                  >
                    {{ status.charAt(0).toUpperCase() + status.slice(1) }}
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
const editForm = ref({
  date: '',
  time: '',
  title: '',
  currency: '',
  impact: 'medium',
  actual: '',
  forecast: '',
  previous: '',
  ea_status: 'unknown',
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
    result = result.filter(n => n.ea_status === filterEaStatus.value)
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
    ea_status: 'unknown',
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
  const statuses = ['unknown', 'safe', 'caution', 'danger']
  const currentIndex = statuses.indexOf(newsItem.ea_status || 'unknown')
  const nextStatus = statuses[(currentIndex + 1) % statuses.length]
  
  try {
    await api.put(`/news/${newsItem.id}/mark-ea`, {
      ea_status: nextStatus,
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
    ea_status: newsItem.ea_status || 'unknown',
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
      ea_status: status,
      should_disable_ea: status === 'danger',
    })
    selectedNews.value = []
    await fetchNews()
  } catch (error) {
    console.error('Failed to bulk mark:', error)
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
})

// Lifecycle
onMounted(() => {
  fetchNews()
  fetchEaRecommendation()
  
  // Refresh EA recommendation every minute
  setInterval(fetchEaRecommendation, 60000)
})
</script>

