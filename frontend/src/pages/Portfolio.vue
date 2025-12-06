<script setup>
import { onMounted, computed } from 'vue'
import { useAccountsStore } from '@/stores/accounts'
import { useDashboardStore } from '@/stores/dashboard'
import StatCard from '@/components/dashboard/StatCard.vue'
import {
  CurrencyDollarIcon,
  ChartBarIcon,
  BriefcaseIcon,
  ArrowTrendingUpIcon,
} from '@heroicons/vue/24/outline'

const accountsStore = useAccountsStore()
const dashboardStore = useDashboardStore()

onMounted(async () => {
  await accountsStore.fetchAccounts()
  await dashboardStore.fetchMetrics()
})

const totalInitialBalance = computed(() => 
  accountsStore.accounts.reduce((sum, a) => sum + (a.initial_balance || 0), 0)
)

const totalProfitLoss = computed(() => 
  accountsStore.totalBalance - totalInitialBalance.value
)

const totalReturn = computed(() => {
  if (totalInitialBalance.value <= 0) return 0
  return ((accountsStore.totalBalance - totalInitialBalance.value) / totalInitialBalance.value) * 100
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold text-dark-100">Portfolio Overview</h1>
      <p class="text-dark-400">Aggregated view of all your trading accounts</p>
    </div>

    <!-- Summary stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <StatCard
        label="Total Balance"
        :value="accountsStore.totalBalance"
        prefix="$"
        :icon="CurrencyDollarIcon"
        :loading="accountsStore.loading"
      />
      <StatCard
        label="Total Equity"
        :value="accountsStore.totalEquity"
        prefix="$"
        :icon="ChartBarIcon"
        :loading="accountsStore.loading"
      />
      <StatCard
        label="Total P/L"
        :value="totalProfitLoss"
        prefix="$"
        :type="totalProfitLoss >= 0 ? 'profit' : 'loss'"
        :icon="ArrowTrendingUpIcon"
        :loading="accountsStore.loading"
      />
      <StatCard
        label="Total Return"
        :value="totalReturn.toFixed(2)"
        suffix="%"
        :type="totalReturn >= 0 ? 'profit' : 'loss'"
        :icon="BriefcaseIcon"
        :loading="accountsStore.loading"
      />
    </div>

    <!-- Accounts breakdown -->
    <div class="card overflow-hidden">
      <div class="p-4 border-b border-dark-800">
        <h3 class="text-sm font-medium text-dark-300">Accounts Breakdown</h3>
      </div>
      
      <div class="table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Account</th>
              <th>Broker</th>
              <th>Type</th>
              <th class="text-right">Initial</th>
              <th class="text-right">Balance</th>
              <th class="text-right">Equity</th>
              <th class="text-right">P/L</th>
              <th class="text-right">Return</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="account in accountsStore.accounts" :key="account.id">
              <td>
                <router-link 
                  :to="`/accounts/${account.id}`"
                  class="font-medium text-dark-100 hover:text-primary-400"
                >
                  {{ account.name || account.login_masked }}
                </router-link>
              </td>
              <td class="text-dark-400">{{ account.broker_name }}</td>
              <td>
                <span :class="[
                  'px-2 py-0.5 text-xs rounded',
                  account.account_type === 'live' 
                    ? 'bg-green-500/20 text-green-400' 
                    : 'bg-blue-500/20 text-blue-400'
                ]">
                  {{ account.account_type }}
                </span>
              </td>
              <td class="text-right font-mono text-dark-400">
                ${{ (account.initial_balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="text-right font-mono text-dark-100">
                ${{ (account.latest_balance?.balance || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="text-right font-mono text-dark-100">
                ${{ (account.latest_balance?.equity || 0).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="text-right font-mono">
                <span :class="(account.latest_balance?.balance || 0) - (account.initial_balance || 0) >= 0 ? 'profit' : 'loss'">
                  {{ (account.latest_balance?.balance || 0) - (account.initial_balance || 0) >= 0 ? '+' : '' }}${{ ((account.latest_balance?.balance || 0) - (account.initial_balance || 0)).toFixed(2) }}
                </span>
              </td>
              <td class="text-right font-mono">
                <span 
                  v-if="account.initial_balance > 0"
                  :class="((account.latest_balance?.balance || 0) - account.initial_balance) / account.initial_balance >= 0 ? 'profit' : 'loss'"
                >
                  {{ ((account.latest_balance?.balance || 0) - account.initial_balance) / account.initial_balance >= 0 ? '+' : '' }}{{ (((account.latest_balance?.balance || 0) - account.initial_balance) / account.initial_balance * 100).toFixed(1) }}%
                </span>
                <span v-else class="text-dark-500">-</span>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-dark-800/50">
              <td colspan="3" class="font-medium text-dark-200">Total</td>
              <td class="text-right font-mono text-dark-300">
                ${{ totalInitialBalance.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="text-right font-mono font-medium text-dark-100">
                ${{ accountsStore.totalBalance.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="text-right font-mono font-medium text-dark-100">
                ${{ accountsStore.totalEquity.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
              </td>
              <td class="text-right font-mono font-medium">
                <span :class="totalProfitLoss >= 0 ? 'profit' : 'loss'">
                  {{ totalProfitLoss >= 0 ? '+' : '' }}${{ totalProfitLoss.toFixed(2) }}
                </span>
              </td>
              <td class="text-right font-mono font-medium">
                <span :class="totalReturn >= 0 ? 'profit' : 'loss'">
                  {{ totalReturn >= 0 ? '+' : '' }}{{ totalReturn.toFixed(1) }}%
                </span>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Performance summary -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="card p-4">
        <h3 class="text-sm font-medium text-dark-300 mb-4">Trading Statistics</h3>
        <div class="space-y-3">
          <div class="flex justify-between py-2 border-b border-dark-800">
            <span class="text-dark-400">Total Trades</span>
            <span class="font-mono text-dark-100">{{ dashboardStore.totalTrades }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-dark-800">
            <span class="text-dark-400">Winning Trades</span>
            <span class="font-mono profit">{{ dashboardStore.metrics?.winning_trades || 0 }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-dark-800">
            <span class="text-dark-400">Win Rate</span>
            <span class="font-mono text-dark-100">{{ dashboardStore.winrate }}%</span>
          </div>
          <div class="flex justify-between py-2 border-b border-dark-800">
            <span class="text-dark-400">Open Trades</span>
            <span class="font-mono text-blue-400">{{ dashboardStore.openTrades }}</span>
          </div>
          <div class="flex justify-between py-2">
            <span class="text-dark-400">Active Accounts</span>
            <span class="font-mono text-dark-100">{{ accountsStore.activeAccounts.length }}</span>
          </div>
        </div>
      </div>

      <div class="card p-4">
        <h3 class="text-sm font-medium text-dark-300 mb-4">Quick Actions</h3>
        <div class="space-y-3">
          <router-link to="/accounts" class="block p-3 rounded-lg bg-dark-800 hover:bg-dark-700 transition-colors">
            <p class="font-medium text-dark-100">Manage Accounts</p>
            <p class="text-sm text-dark-400">Add or configure trading accounts</p>
          </router-link>
          <router-link to="/trades/new" class="block p-3 rounded-lg bg-dark-800 hover:bg-dark-700 transition-colors">
            <p class="font-medium text-dark-100">Add Manual Trade</p>
            <p class="text-sm text-dark-400">Record a trade manually</p>
          </router-link>
          <router-link to="/reports" class="block p-3 rounded-lg bg-dark-800 hover:bg-dark-700 transition-colors">
            <p class="font-medium text-dark-100">Export Reports</p>
            <p class="text-sm text-dark-400">Download monthly reports</p>
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

