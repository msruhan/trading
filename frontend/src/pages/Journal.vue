<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { journalAPI } from '@/services/api'
import { useAccountsStore } from '@/stores/accounts'
import dayjs from 'dayjs'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  BookOpenIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const accountsStore = useAccountsStore()

const entries = ref([])
const loading = ref(false)
const pagination = ref({ current_page: 1, last_page: 1 })

const showModal = ref(false)
const editingEntry = ref(null)
const saving = ref(false)

const form = reactive({
  entry_date: dayjs().format('YYYY-MM-DD'),
  entry_type: 'journal',
  title: '',
  content: '',
  mood: '',
  tags: [],
})

const entryTypes = [
  { value: 'journal', label: 'Journal' },
  { value: 'note', label: 'Note' },
  { value: 'analysis', label: 'Analysis' },
  { value: 'lesson', label: 'Lesson Learned' },
  { value: 'strategy', label: 'Strategy' },
]

const moods = [
  { value: 'confident', label: '😊 Confident', color: 'text-green-400' },
  { value: 'neutral', label: '😐 Neutral', color: 'text-gray-400' },
  { value: 'anxious', label: '😰 Anxious', color: 'text-yellow-400' },
  { value: 'frustrated', label: '😤 Frustrated', color: 'text-red-400' },
]

onMounted(async () => {
  await accountsStore.fetchAccounts()
  
  // Check for date query param
  if (route.query.date) {
    form.entry_date = route.query.date
  }
  
  await fetchEntries()
})

const fetchEntries = async (page = 1) => {
  loading.value = true
  try {
    const response = await journalAPI.getAll({ page, per_page: 10 })
    entries.value = response.data.data
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
    }
  } catch (error) {
    console.error('Failed to fetch entries:', error)
  } finally {
    loading.value = false
  }
}

const openModal = (entry = null) => {
  if (entry) {
    editingEntry.value = entry
    Object.assign(form, {
      entry_date: entry.entry_date,
      entry_type: entry.entry_type,
      title: entry.title || '',
      content: entry.content,
      mood: entry.mood || '',
      tags: entry.tags || [],
    })
  } else {
    editingEntry.value = null
    Object.assign(form, {
      entry_date: dayjs().format('YYYY-MM-DD'),
      entry_type: 'journal',
      title: '',
      content: '',
      mood: '',
      tags: [],
    })
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingEntry.value = null
}

const handleSubmit = async () => {
  if (!form.content) return
  
  saving.value = true
  try {
    if (editingEntry.value) {
      await journalAPI.update(editingEntry.value.id, form)
    } else {
      await journalAPI.create(form)
    }
    closeModal()
    await fetchEntries()
  } catch (error) {
    console.error('Failed to save entry:', error)
  } finally {
    saving.value = false
  }
}

const deleteEntry = async (id) => {
  if (!confirm('Are you sure you want to delete this entry?')) return
  
  try {
    await journalAPI.delete(id)
    await fetchEntries()
  } catch (error) {
    console.error('Failed to delete entry:', error)
  }
}

const getMoodEmoji = (mood) => {
  const m = moods.find(m => m.value === mood)
  return m ? m.label.split(' ')[0] : '📝'
}
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-dark-100">Trading Journal</h1>
        <p class="text-dark-400">Document your trading journey and lessons</p>
      </div>
      <button
        class="btn-primary flex items-center gap-2"
        @click="openModal()"
      >
        <PlusIcon class="w-4 h-4" />
        New Entry
      </button>
    </div>

    <!-- Entries list -->
    <div v-if="loading" class="flex justify-center py-12">
      <div class="spinner" />
    </div>

    <div v-else-if="entries.length === 0" class="card p-12 text-center">
      <BookOpenIcon class="w-16 h-16 mx-auto text-dark-600 mb-4" />
      <h3 class="text-lg font-medium text-dark-200">No journal entries yet</h3>
      <p class="text-dark-400 mt-1">Start documenting your trading journey</p>
      <button
        class="btn-primary mt-4"
        @click="openModal()"
      >
        Create First Entry
      </button>
    </div>

    <div v-else class="space-y-4">
      <div
        v-for="entry in entries"
        :key="entry.id"
        class="card p-4 hover:border-dark-700 transition-colors"
      >
        <div class="flex items-start justify-between">
          <div class="flex items-start gap-3">
            <span class="text-2xl">{{ getMoodEmoji(entry.mood) }}</span>
            <div>
              <div class="flex items-center gap-2 mb-1">
                <span class="font-medium text-dark-100">
                  {{ entry.title || entry.entry_type }}
                </span>
                <span class="px-2 py-0.5 text-xs rounded bg-dark-800 text-dark-400">
                  {{ entry.entry_type }}
                </span>
              </div>
              <p class="text-sm text-dark-400 mb-2">
                {{ dayjs(entry.entry_date).format('MMMM D, YYYY') }}
              </p>
              <p class="text-dark-300 whitespace-pre-wrap">{{ entry.content }}</p>
              <div v-if="entry.tags?.length" class="flex flex-wrap gap-1 mt-2">
                <span
                  v-for="tag in entry.tags"
                  :key="tag"
                  class="px-2 py-0.5 text-xs rounded bg-primary-500/20 text-primary-400"
                >
                  {{ tag }}
                </span>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-1">
            <button
              class="p-1.5 rounded hover:bg-dark-800 text-dark-400"
              @click="openModal(entry)"
            >
              <PencilIcon class="w-4 h-4" />
            </button>
            <button
              class="p-1.5 rounded hover:bg-dark-800 text-red-400"
              @click="deleteEntry(entry.id)"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex justify-center gap-2">
      <button
        class="btn-secondary"
        :disabled="pagination.current_page === 1"
        @click="fetchEntries(pagination.current_page - 1)"
      >
        Previous
      </button>
      <button
        class="btn-secondary"
        :disabled="pagination.current_page === pagination.last_page"
        @click="fetchEntries(pagination.current_page + 1)"
      >
        Next
      </button>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="fixed inset-0 bg-dark-950/80 backdrop-blur-sm" @click="closeModal" />
      
      <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative w-full max-w-2xl card p-6" @click.stop>
          <h2 class="text-xl font-semibold text-dark-100 mb-4">
            {{ editingEntry ? 'Edit Entry' : 'New Journal Entry' }}
          </h2>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Date</label>
                <input v-model="form.entry_date" type="date" class="input" required />
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-dark-300">Type</label>
                <select v-model="form.entry_type" class="input">
                  <option v-for="type in entryTypes" :key="type.value" :value="type.value">
                    {{ type.label }}
                  </option>
                </select>
              </div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Title (optional)</label>
              <input v-model="form.title" type="text" class="input" placeholder="Entry title" />
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Mood</label>
              <div class="flex gap-2">
                <button
                  v-for="mood in moods"
                  :key="mood.value"
                  type="button"
                  :class="[
                    'px-3 py-2 rounded-lg border transition-colors',
                    form.mood === mood.value
                      ? 'border-primary-500 bg-primary-500/10'
                      : 'border-dark-700 hover:border-dark-600'
                  ]"
                  @click="form.mood = mood.value"
                >
                  {{ mood.label }}
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <label class="block text-sm font-medium text-dark-300">Content *</label>
              <textarea
                v-model="form.content"
                rows="6"
                class="input"
                placeholder="Write your thoughts, analysis, lessons learned..."
                required
              />
            </div>

            <div class="flex gap-3 pt-4">
              <button type="button" class="btn-secondary flex-1" @click="closeModal">
                Cancel
              </button>
              <button type="submit" class="btn-primary flex-1" :disabled="saving">
                {{ saving ? 'Saving...' : (editingEntry ? 'Update' : 'Create') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

