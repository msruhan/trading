//+------------------------------------------------------------------+
//|                                      TradingJournalBridge.mq4    |
//|                              Trading Journal Portfolio Dashboard  |
//|                                      https://tradingjournal.local |
//+------------------------------------------------------------------+
#property copyright "Trading Journal"
#property link      "https://tradingjournal.local"
#property version   "1.00"
#property strict

//--- Input parameters
input string   API_URL = "https://ghislaine-boundless-billye.ngrok-free.dev/api/v1/incoming/trades";  // API Endpoint URL (ngrok)
// Alternative: Use localhost if ngrok fails: "http://127.0.0.1:8000/api/v1/incoming/trades"
input string   API_TOKEN = "2df47a53914c0dfb545a472ce813d74f4b2d07c788b1765ac3b90848c8fa2ac1";  // Generated token
input int      ACCOUNT_ID = 8;                                               // Account ID from Dashboard
input int      SYNC_INTERVAL_SECONDS = 60;                                   // Sync interval for normal sync (seconds)
input bool     SYNC_HISTORY = true;                                           // Sync historical trades (ENABLED - limited to prevent large payload)
input int      HISTORY_DAYS = 7;                                              // Days of history to sync (start with 7 days)
input int      MAX_HISTORY_TRADES = 30;                                       // Max history trades per sync (ngrok free tier limit ~500KB)
input int      HISTORY_SYNC_INTERVAL_SECONDS = 5;                             // Fast sync interval when syncing history batches (seconds)
input bool     INTERCEPT_ALL_TRADING = false;                                  // Intercept ALL trading on this account (true) or specific magic numbers (false)
input int      TARGET_MAGIC_BUY = 0;                                           // Magic number for BUY orders of EA to control (0 = all, ignored if INTERCEPT_ALL_TRADING = true)
input int      TARGET_MAGIC_SELL = 0;                                          // Magic number for SELL orders of EA to control (0 = all, ignored if INTERCEPT_ALL_TRADING = true)
input bool     SYNC_ON_DEMAND = true;                                          // Sync only when triggered from web (true) or auto-sync (false)

//--- Global variables
datetime lastSyncTime = 0;
int syncCounter = 0;
int historyOffset = 0;  // Track how many history trades have been synced
int lastHistoryCount = 0;  // Track how many history trades were sent in last sync
int totalEligibleHistory = 0;  // Total eligible history trades to sync
bool isSyncingHistory = false;  // Flag to indicate if we're still syncing history batches
int pendingHistoryOffset = 0;  // Temporary offset that will be saved after successful API call
int secondsSinceLastSync = 0;  // Manual counter for seconds since last sync (works even when market is closed)
bool hasSyncedBefore = false;  // Track if EA has ever synced before (first time sync)
bool syncRequested = false;  // Track if sync was requested from web

//--- Trading Control variables
bool isPaused = false;  // Pause trading flag (prevents other EAs from opening positions)
int scheduleDays[7] = {0, 0, 0, 0, 0, 0, 0};  // Schedule days (0=disabled, 1=enabled) [Monday=0, Sunday=6]
string scheduleStartTime = "";  // Schedule start time (HH:MM format)
string scheduleEndTime = "";  // Schedule end time (HH:MM format)
bool scheduleActive = false;  // Whether schedule is currently active

//+------------------------------------------------------------------+
//| Expert initialization function                                     |
//+------------------------------------------------------------------+
int OnInit()
{
   if(API_TOKEN == "" || ACCOUNT_ID == 0)
   {
      Alert("TradingJournalBridge: Please configure API_TOKEN and ACCOUNT_ID");
      return(INIT_FAILED);
   }
   
   Print("TradingJournalBridge: Initialized for Account ID ", ACCOUNT_ID);
   Print("TradingJournalBridge: Normal sync interval: ", SYNC_INTERVAL_SECONDS, " seconds");
   Print("TradingJournalBridge: Using NGROK - History sync: ", (SYNC_HISTORY ? "ENABLED" : "DISABLED"));
   if(SYNC_HISTORY)
   {
      Print("TradingJournalBridge: History settings - Days: ", HISTORY_DAYS, ", Max trades per batch: ", MAX_HISTORY_TRADES);
      Print("TradingJournalBridge: Fast sync interval for history batches: ", HISTORY_SYNC_INTERVAL_SECONDS, " seconds");
      Print("TradingJournalBridge: Incremental sync enabled - will sync in batches");
      
      // Set timer to check sync interval every second (for fast sync support)
      EventSetTimer(1);
      Print("TradingJournalBridge: Timer set to 1 second for interval checking");
      
      // Load history offset from Global Variables
      string gvKey = "TJ_HistoryOffset_" + IntegerToString(ACCOUNT_ID);
      if(GlobalVariableCheck(gvKey))
      {
         historyOffset = (int)GlobalVariableGet(gvKey);
         Print("TradingJournalBridge: Resumed from history offset: ", historyOffset);
      }
      else
      {
         historyOffset = 0;
         Print("TradingJournalBridge: Starting fresh history sync from beginning");
      }
      
      // Load sync flag from Global Variables
      string flagKey = "TJ_IsSyncingHistory_" + IntegerToString(ACCOUNT_ID);
      if(GlobalVariableCheck(flagKey))
      {
         isSyncingHistory = (GlobalVariableGet(flagKey) > 0.5);
         Print("TradingJournalBridge: Resumed sync flag: ", (isSyncingHistory ? "true (fast sync)" : "false (normal sync)"));
      }
      else
      {
         // If offset > 0, we're still syncing history, so use fast interval
         if(historyOffset > 0)
         {
            isSyncingHistory = true;
            Print("TradingJournalBridge: History sync in progress - using fast interval (", HISTORY_SYNC_INTERVAL_SECONDS, "s)");
         }
         else
         {
            isSyncingHistory = true; // Start with fast sync for first batch
            Print("TradingJournalBridge: Starting with fast sync interval (", HISTORY_SYNC_INTERVAL_SECONDS, "s)");
         }
      }
      
      // Set timer to check sync interval every second (for fast sync support)
      EventSetTimer(1);
      Print("TradingJournalBridge: Timer set to 1 second for interval checking");
   }
   else
   {
      // Set timer even if history sync is disabled, for normal sync
      EventSetTimer(1);
      Print("TradingJournalBridge: Timer set to 1 second for interval checking");
   }
   
   // Initialize counters
   lastSyncTime = 0;
   secondsSinceLastSync = 0;  // Will be incremented by OnTimer
   
   // Load sync state from Global Variables
   string hasSyncedKey = "TJ_HasSynced_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(hasSyncedKey))
   {
      hasSyncedBefore = (GlobalVariableGet(hasSyncedKey) > 0.5);
      Print("TradingJournalBridge: Has synced before: ", (hasSyncedBefore ? "YES" : "NO"));
   }
   else
   {
      hasSyncedBefore = false;
      Print("TradingJournalBridge: First time attach - will wait for web trigger (SYNC_ON_DEMAND mode)");
   }
   
   if(SYNC_ON_DEMAND)
   {
      Print("TradingJournalBridge: SYNC_ON_DEMAND mode ENABLED - will only sync when triggered from web (even on first time)");
   }
   else
   {
      Print("TradingJournalBridge: Auto-sync mode ENABLED - will sync automatically every ", SYNC_INTERVAL_SECONDS, " seconds");
   }
   
   // Load pause and schedule state from Global Variables
   LoadPauseState();
   LoadScheduleState();
   
   // Load target magic numbers from Global Variables (if set via command)
   string magicBuyGvKey = "TJ_TargetMagicBuy_" + IntegerToString(ACCOUNT_ID);
   string magicSellGvKey = "TJ_TargetMagicSell_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(magicBuyGvKey))
   {
      int loadedMagicBuy = (int)GlobalVariableGet(magicBuyGvKey);
      if(loadedMagicBuy > 0 && loadedMagicBuy != TARGET_MAGIC_BUY)
      {
         Print("TradingJournalBridge: WARNING - Target magic BUY from Global Variables (", loadedMagicBuy, ") differs from input parameter (", TARGET_MAGIC_BUY, ")");
         Print("TradingJournalBridge: Using Global Variable value: ", loadedMagicBuy);
      }
   }
   if(GlobalVariableCheck(magicSellGvKey))
   {
      int loadedMagicSell = (int)GlobalVariableGet(magicSellGvKey);
      if(loadedMagicSell > 0 && loadedMagicSell != TARGET_MAGIC_SELL)
      {
         Print("TradingJournalBridge: WARNING - Target magic SELL from Global Variables (", loadedMagicSell, ") differs from input parameter (", TARGET_MAGIC_SELL, ")");
         Print("TradingJournalBridge: Using Global Variable value: ", loadedMagicSell);
      }
   }
   
   // Check if intercept all trading mode is enabled
   bool interceptAll = INTERCEPT_ALL_TRADING;
   string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(interceptAllGvKey))
   {
      interceptAll = (GlobalVariableGet(interceptAllGvKey) > 0.5);
   }
   
   if(interceptAll)
   {
      Print("TradingJournalBridge: =========================================");
      Print("TradingJournalBridge: INTERCEPT ALL TRADING MODE ENABLED");
      Print("TradingJournalBridge: Will intercept ALL trading on this account");
      Print("TradingJournalBridge: (Magic BUY/SELL parameters are ignored)");
      Print("TradingJournalBridge: =========================================");
   }
   else if(TARGET_MAGIC_BUY > 0 || TARGET_MAGIC_SELL > 0)
   {
      Print("TradingJournalBridge: Will intercept EA with Magic BUY: ", TARGET_MAGIC_BUY, ", Magic SELL: ", TARGET_MAGIC_SELL);
   }
   else
   {
      Print("TradingJournalBridge: Will intercept ALL EAs (Magic BUY/SELL: 0 = all)");
   }
   
   if(isPaused)
   {
      Print("TradingJournalBridge: Trading is PAUSED");
   }
   else
   {
      Print("TradingJournalBridge: Trading is ACTIVE");
   }
   
   // NEVER sync automatically in on-demand mode - always wait for web trigger
   // Even on first time, must wait for trigger from web
   if(!SYNC_ON_DEMAND)
   {
      Print("TradingJournalBridge: Starting initial sync (auto-sync mode)...");
      SyncTrades();
   }
   else
   {
      Print("TradingJournalBridge: =========================================");
      Print("TradingJournalBridge: SYNC_ON_DEMAND mode - WAITING for trigger");
      Print("TradingJournalBridge: =========================================");
      Print("TradingJournalBridge: EA is ready but will NOT sync automatically");
      Print("TradingJournalBridge: Please click 'Sync Now' button on the website");
      Print("TradingJournalBridge: EA will check for sync request every 10 seconds");
      Print("TradingJournalBridge: =========================================");
      // DO NOT call SyncTrades() here - wait for web trigger
   }
   
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Expert deinitialization function                                   |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   // Kill timer
   EventKillTimer();
   
   // Save history offset before EA stops
   if(SYNC_HISTORY)
   {
      string gvKey = "TJ_HistoryOffset_" + IntegerToString(ACCOUNT_ID);
      GlobalVariableSet(gvKey, historyOffset);
      Print("TradingJournalBridge: Saved history offset: ", historyOffset, " before deinitialization");
      
      // Also save sync flag
      string flagKey = "TJ_IsSyncingHistory_" + IntegerToString(ACCOUNT_ID);
      GlobalVariableSet(flagKey, isSyncingHistory ? 1.0 : 0.0);
   }
   
   Print("TradingJournalBridge: Deinitialized. Total syncs: ", syncCounter);
}

//+------------------------------------------------------------------+
//| Expert tick function                                               |
//+------------------------------------------------------------------+
void OnTick()
{
   // Determine sync interval based on whether we're still syncing history
   int currentSyncInterval = SYNC_INTERVAL_SECONDS;
   
   if(SYNC_HISTORY && isSyncingHistory)
   {
      // Use faster interval when syncing history batches
      currentSyncInterval = HISTORY_SYNC_INTERVAL_SECONDS;
   }
   
   // Check if it's time to sync
   int timeSinceLastSync = (int)(TimeCurrent() - lastSyncTime);
   if(timeSinceLastSync >= currentSyncInterval)
   {
      Print("TradingJournalBridge: Time to sync! Last sync: ", timeSinceLastSync, "s ago, Interval: ", currentSyncInterval, "s, isSyncingHistory: ", isSyncingHistory);
      SyncTrades();
   }
}

//+------------------------------------------------------------------+
//| Timer function (called every second for interval checking)        |
//+------------------------------------------------------------------+
void OnTimer()
{
   // OnTimer is called every second (set in OnInit)
   // This ensures sync happens even when market is closed or no ticks occur
   
   // Increment manual counter (works even when market is closed)
   secondsSinceLastSync++;
   
   // Monitor and control other EAs (intercept trading)
   MonitorAndControlTrading();
   
   // Determine sync interval based on whether we're still syncing history
   int currentSyncInterval = SYNC_INTERVAL_SECONDS;
   
   if(SYNC_HISTORY && isSyncingHistory)
   {
      // Use faster interval when syncing history batches
      currentSyncInterval = HISTORY_SYNC_INTERVAL_SECONDS;
   }
   
   // Debug logging every 5 seconds to show timer is working
   static int lastDebugTime = 0;
   if(secondsSinceLastSync - lastDebugTime >= 5)
   {
      Print("TradingJournalBridge: [OnTimer] Timer active. Last sync: ", secondsSinceLastSync, "s ago, Interval: ", currentSyncInterval, "s, isSyncingHistory: ", isSyncingHistory, ", historyOffset: ", historyOffset);
      Print("TradingJournalBridge: [OnTimer] TimeCurrent: ", TimeToString(TimeCurrent(), TIME_DATE|TIME_SECONDS), ", TimeLocal: ", TimeToString(TimeLocal(), TIME_DATE|TIME_SECONDS));
      lastDebugTime = secondsSinceLastSync;
   }
   
   // Check if sync should be triggered
   bool shouldSync = false;
   
   if(SYNC_ON_DEMAND)
   {
      // In on-demand mode, only sync if sync was requested from web
      // Even on first time, must wait for trigger from web
      // BUT: If we're syncing history batches, continue syncing automatically until done
      if(isSyncingHistory && SYNC_HISTORY)
      {
         // We're in the middle of syncing history batches - continue automatically
         // Don't check for sync request, just sync based on interval
         if(secondsSinceLastSync >= currentSyncInterval)
         {
            shouldSync = true;
            Print("TradingJournalBridge: [OnTimer] History sync in progress - continuing batch sync (interval: ", currentSyncInterval, "s)");
         }
      }
      else if(syncRequested)
      {
         // Sync was requested from web - trigger sync
         shouldSync = true;
         Print("TradingJournalBridge: [OnTimer] Sync requested from web - triggering sync");
      }
      else
      {
         // Check for sync request from web by calling health check endpoint
         // This allows EA to check sync_requested status without doing full sync
         static datetime lastCheckTime = 0;
         static bool firstCheckDone = false;
         
         // Wait at least 10 seconds after OnInit before first check (give time for EA to fully initialize)
         if(!firstCheckDone)
         {
            // Initialize lastCheckTime to current time on first OnTimer call
            if(lastCheckTime == 0)
            {
               lastCheckTime = TimeCurrent();
               Print("TradingJournalBridge: [OnTimer] Initialized - will start checking for sync request in 10 seconds");
            }
            // Wait 10 seconds before first check
            else if(TimeCurrent() - lastCheckTime >= 10)
            {
               firstCheckDone = true;
               lastCheckTime = TimeCurrent();
               Print("TradingJournalBridge: [OnTimer] Starting to check for sync request from web...");
            }
         }
         
         // Check every 10 seconds (after initial 10 second wait)
         if(firstCheckDone && TimeCurrent() - lastCheckTime >= 10)
         {
            lastCheckTime = TimeCurrent();
            if(CheckSyncRequest())
            {
               syncRequested = true;
               shouldSync = true;
               Print("TradingJournalBridge: [OnTimer] Sync request detected from web - triggering sync NOW");
            }
            else
            {
               // Log status every 30 seconds to show EA is waiting
               static datetime lastStatusLog = 0;
               if(TimeCurrent() - lastStatusLog >= 30)
               {
                  lastStatusLog = TimeCurrent();
                  Print("TradingJournalBridge: [OnTimer] Status: WAITING for sync trigger from web (checking every 10s)");
               }
            }
         }
      }
   }
   else
   {
      // Auto-sync mode: sync based on interval
      if(secondsSinceLastSync >= currentSyncInterval)
      {
         shouldSync = true;
         Print("TradingJournalBridge: [OnTimer] Time to sync! Last sync: ", secondsSinceLastSync, "s ago, Interval: ", currentSyncInterval, "s, isSyncingHistory: ", isSyncingHistory);
         Print("TradingJournalBridge: [OnTimer] TimeCurrent: ", TimeToString(TimeCurrent(), TIME_DATE|TIME_SECONDS), ", TimeLocal: ", TimeToString(TimeLocal(), TIME_DATE|TIME_SECONDS));
      }
   }
   
   if(shouldSync)
   {
      SyncTrades();
   }
}

//+------------------------------------------------------------------+
//| Main sync function                                                 |
//+------------------------------------------------------------------+
void SyncTrades()
{
   syncCounter++;
   
   // First, calculate total eligible history to determine if we're still syncing
   int localTotalEligibleHistory = 0;
   if(SYNC_HISTORY)
   {
      datetime startDate = TimeCurrent() - (HISTORY_DAYS * 24 * 60 * 60);
      for(int i = OrdersHistoryTotal() - 1; i >= 0; i--)
      {
         if(OrderSelect(i, SELECT_BY_POS, MODE_HISTORY))
         {
            if(OrderCloseTime() >= startDate)
            {
               localTotalEligibleHistory++;
            }
         }
      }
      totalEligibleHistory = localTotalEligibleHistory;
   }
   
   // Check if we're still syncing history batches - if so, skip hash check and force sync
   bool skipHashCheck = false;
   if(SYNC_HISTORY && isSyncingHistory && historyOffset < totalEligibleHistory && totalEligibleHistory > 0)
   {
      // Still syncing history batches - don't skip sync even if hash matches
      skipHashCheck = true;
      Print("TradingJournalBridge: History sync in progress (offset: ", historyOffset, " / ", totalEligibleHistory, ") - will sync regardless of hash");
   }
   
   // First, check if data has changed by sending data hash (unless we're syncing history)
   string checkResponse = "";
   bool forceSync = false;
   if(!skipHashCheck)
   {
      string dataHash = CalculateDataHash();
      Print("TradingJournalBridge: Data hash: ", dataHash);
      
      // Check if data has changed
      checkResponse = CheckDataChanged(dataHash);
      if(checkResponse != "")
      {
         // Check for force_sync flag (after clear data or sync requested)
         int forceSyncPos = StringFind(checkResponse, "\"force_sync\":");
         if(forceSyncPos >= 0)
         {
            int forceSyncStart = StringFind(checkResponse, ":", forceSyncPos) + 1;
            int forceSyncEnd = StringFind(checkResponse, ",", forceSyncStart);
            if(forceSyncEnd < 0) forceSyncEnd = StringFind(checkResponse, "}", forceSyncStart);
            string forceSyncStr = StringSubstr(checkResponse, forceSyncStart, forceSyncEnd - forceSyncStart);
            StringReplace(forceSyncStr, " ", "");
            if(StringFind(forceSyncStr, "true") >= 0)
            {
               forceSync = true;
               Print("TradingJournalBridge: Force sync detected - will sync regardless of data hash");
               
               // Reset history offset if force sync (after clear data)
               int hasNoDataPos = StringFind(checkResponse, "\"has_no_data\":");
               if(hasNoDataPos >= 0)
               {
                  int hasNoDataStart = StringFind(checkResponse, ":", hasNoDataPos) + 1;
                  int hasNoDataEnd = StringFind(checkResponse, ",", hasNoDataStart);
                  if(hasNoDataEnd < 0) hasNoDataEnd = StringFind(checkResponse, "}", hasNoDataStart);
                  string hasNoDataStr = StringSubstr(checkResponse, hasNoDataStart, hasNoDataEnd - hasNoDataStart);
                  StringReplace(hasNoDataStr, " ", "");
                  if(StringFind(hasNoDataStr, "true") >= 0)
                  {
                     // Reset history offset and sync flags
                     historyOffset = 0;
                     hasSyncedBefore = false;
                     isSyncingHistory = false; // Reset sync flag to prevent infinite loop
                     string gvKey = "TJ_HistoryOffset_" + IntegerToString(ACCOUNT_ID);
                     GlobalVariableDel(gvKey);
                     string hasSyncedKey = "TJ_HasSynced_" + IntegerToString(ACCOUNT_ID);
                     GlobalVariableDel(hasSyncedKey);
                     string flagKey = "TJ_IsSyncingHistory_" + IntegerToString(ACCOUNT_ID);
                     GlobalVariableDel(flagKey);
                     Print("TradingJournalBridge: Account has no data - reset history offset and sync flags");
                     Print("TradingJournalBridge: isSyncingHistory reset to false to start fresh");
                  }
               }
            }
         }
         
         // Parse response to check if data has changed (only if not force sync and not syncing history)
         if(!forceSync && !skipHashCheck)
         {
            int noChangesPos = StringFind(checkResponse, "\"no_changes\":");
            if(noChangesPos >= 0)
            {
               int noChangesStart = StringFind(checkResponse, ":", noChangesPos) + 1;
               int noChangesEnd = StringFind(checkResponse, ",", noChangesStart);
               if(noChangesEnd < 0) noChangesEnd = StringFind(checkResponse, "}", noChangesStart);
               string noChangesStr = StringSubstr(checkResponse, noChangesStart, noChangesEnd - noChangesStart);
               StringReplace(noChangesStr, " ", "");
               if(StringFind(noChangesStr, "true") >= 0)
               {
                  Print("TradingJournalBridge: Data unchanged - skipping sync");
                  // Still process commands if any
                  ProcessCommands(checkResponse);
                  // Reset counter even if no sync
                  secondsSinceLastSync = 0;
                  lastSyncTime = TimeLocal();
                  return;
               }
            }
         }
      }
   }
   
   // Process commands from check response if available
   if(checkResponse != "")
   {
      ProcessCommands(checkResponse);
   }
   
   // Data has changed or check failed - proceed with full sync
   Print("TradingJournalBridge: Data changed or first sync - proceeding with full sync");
   
   // Build JSON payload
   string json = BuildPayload();
   
   if(json == "")
   {
      Print("TradingJournalBridge: Failed to build payload");
      return;
   }
   
   // Debug: Print first 500 chars of payload to verify trades are included
   if(StringLen(json) > 500)
   {
      Print("TradingJournalBridge: Payload preview: ", StringSubstr(json, 0, 500), "...");
   }
   else
   {
      Print("TradingJournalBridge: Full payload: ", json);
   }
   
   // Send to API
   string response = SendToAPI(json);
   
   if(response != "")
   {
      Print("TradingJournalBridge: Sync #", syncCounter, " completed. Response: ", StringSubstr(response, 0, 200));
      
      // Check if sync was requested from web
      int syncRequestedPos = StringFind(response, "\"sync_requested\":");
      if(syncRequestedPos >= 0)
      {
         int syncRequestedStart = StringFind(response, ":", syncRequestedPos) + 1;
         int syncRequestedEnd = StringFind(response, ",", syncRequestedStart);
         if(syncRequestedEnd < 0) syncRequestedEnd = StringFind(response, "}", syncRequestedStart);
         string syncRequestedStr = StringSubstr(response, syncRequestedStart, syncRequestedEnd - syncRequestedStart);
         StringReplace(syncRequestedStr, " ", "");
         if(StringFind(syncRequestedStr, "true") >= 0)
         {
            syncRequested = false; // Clear flag after processing
            Print("TradingJournalBridge: Sync was requested from web - processed successfully");
         }
      }
      
      // Process commands from response
      ProcessCommands(response);
      
      // Mark as synced before
      if(!hasSyncedBefore)
      {
         hasSyncedBefore = true;
         string hasSyncedKey = "TJ_HasSynced_" + IntegerToString(ACCOUNT_ID);
         GlobalVariableSet(hasSyncedKey, 1.0);
         Print("TradingJournalBridge: First sync completed - marked as synced");
      }
      
      // Reset manual counter AFTER successful sync
      secondsSinceLastSync = 0;
      
      // Also update lastSyncTime for reference (using TimeLocal for reliability)
      lastSyncTime = TimeLocal();
      Print("TradingJournalBridge: Reset sync counter. TimeLocal: ", TimeToString(lastSyncTime, TIME_DATE|TIME_SECONDS), ", TimeCurrent: ", TimeToString(TimeCurrent(), TIME_DATE|TIME_SECONDS));
      
      // Save history offset and sync flag to Global Variables after successful sync
      if(SYNC_HISTORY)
      {
         // Update historyOffset with pending offset (calculated in BuildPayload)
         historyOffset = pendingHistoryOffset;
         
         // Check if we've completed all history
         if(historyOffset >= totalEligibleHistory && totalEligibleHistory > 0)
         {
            Print("TradingJournalBridge: All history trades synced! (", totalEligibleHistory, " total)");
            Print("TradingJournalBridge: Resetting offset to 0 for next cycle");
            historyOffset = 0;
            isSyncingHistory = false; // Switch to normal sync interval
            // Clear syncRequested flag now that history sync is complete
            if(SYNC_ON_DEMAND)
            {
               syncRequested = false;
               Print("TradingJournalBridge: History sync complete - cleared syncRequested flag");
            }
            Print("TradingJournalBridge: Switching to normal sync interval (", SYNC_INTERVAL_SECONDS, "s)");
         }
         else if(historyOffset < totalEligibleHistory && totalEligibleHistory > 0)
         {
            Print("TradingJournalBridge: History sync in progress. Offset: ", historyOffset, " / ", totalEligibleHistory);
            isSyncingHistory = true; // Continue fast sync
            Print("TradingJournalBridge: Next batch will sync in ", HISTORY_SYNC_INTERVAL_SECONDS, " seconds");
            Print("TradingJournalBridge: IMPORTANT - Will skip hash check on next sync to continue history batches");
         }
         else if(totalEligibleHistory == 0)
         {
            // No history trades at all
            Print("TradingJournalBridge: No history trades found. Switching to normal sync interval.");
            isSyncingHistory = false;
            // Clear syncRequested flag if no history to sync
            if(SYNC_ON_DEMAND)
            {
               syncRequested = false;
               Print("TradingJournalBridge: No history to sync - cleared syncRequested flag");
            }
         }
         
         // Save to Global Variables
         string gvKey = "TJ_HistoryOffset_" + IntegerToString(ACCOUNT_ID);
         GlobalVariableSet(gvKey, historyOffset);
         Print("TradingJournalBridge: Saved history offset: ", historyOffset);
         
         // Also save sync flag
         string flagKey = "TJ_IsSyncingHistory_" + IntegerToString(ACCOUNT_ID);
         GlobalVariableSet(flagKey, isSyncingHistory ? 1.0 : 0.0);
         Print("TradingJournalBridge: Saved sync flag: ", (isSyncingHistory ? "true (fast sync)" : "false (normal sync)"));
      }
   }
   else
   {
      Print("TradingJournalBridge: Sync #", syncCounter, " failed - offset not updated, will retry same batch");
      // Don't update lastSyncTime or offset if sync failed - will retry same batch next time
      // But still update lastSyncTime to prevent immediate retry
      lastSyncTime = TimeCurrent();
   }
}

//+------------------------------------------------------------------+
//| Calculate hash of current data (balance + open trades summary)    |
//+------------------------------------------------------------------+
string CalculateDataHash()
{
   // Build a simple hash string from balance and open trades
   string hashStr = "";
   
   // Add balance info
   hashStr += DoubleToString(AccountBalance(), 2);
   hashStr += "|";
   hashStr += DoubleToString(AccountEquity(), 2);
   hashStr += "|";
   hashStr += DoubleToString(AccountMargin(), 2);
   hashStr += "|";
   
   // Add open trades summary (ticket + profit)
   int openTradesCount = 0;
   double totalOpenProfit = 0;
   string openTickets = "";
   
   for(int i = 0; i < OrdersTotal(); i++)
   {
      if(OrderSelect(i, SELECT_BY_POS, MODE_TRADES))
      {
         openTradesCount++;
         totalOpenProfit += OrderProfit();
         if(StringLen(openTickets) > 0) openTickets += ",";
         openTickets += IntegerToString(OrderTicket()) + ":" + DoubleToString(OrderProfit(), 2);
      }
   }
   
   hashStr += IntegerToString(openTradesCount);
   hashStr += "|";
   hashStr += DoubleToString(totalOpenProfit, 2);
   hashStr += "|";
   hashStr += openTickets;
   
   // Simple hash: just return the hash string (backend will compare)
   // In a real implementation, you might want to use a hash function
   return hashStr;
}

//+------------------------------------------------------------------+
//| Check if data has changed by sending hash to backend             |
//+------------------------------------------------------------------+
string CheckDataChanged(string dataHash)
{
   string checkUrl = StringSubstr(API_URL, 0, StringFind(API_URL, "/incoming")) + "/incoming/check-data";
   
   string json = "{";
   json += "\"data_hash\":\"" + dataHash + "\"";
   json += "}";
   
   string headers = "";
   headers += "Content-Type: application/json\r\n";
   headers += "Accept: application/json\r\n";
   if(StringFind(API_URL, "ngrok") >= 0)
   {
      headers += "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n";
      headers += "ngrok-skip-browser-warning: true\r\n";
   }
   headers += "X-API-Token: " + API_TOKEN + "\r\n";
   headers += "X-Account-ID: " + IntegerToString(ACCOUNT_ID) + "\r\n";
   
   char post[];
   char result[];
   string resultHeaders;
   
   StringToCharArray(json, post, 0, StringLen(json));
   ArrayResize(post, StringLen(json));
   
   int response = WebRequest("POST", checkUrl, headers, 5000, post, result, resultHeaders);
   
   if(response == 200)
   {
      return CharArrayToString(result);
   }
   else
   {
      Print("TradingJournalBridge: Check data changed failed - HTTP ", response, " - will proceed with sync");
      return "";
   }
}

//+------------------------------------------------------------------+
//| Build JSON payload with balance and trades                         |
//+------------------------------------------------------------------+
string BuildPayload()
{
   string json = "{";
   
   // Account balance info
   json += "\"balance\":{";
   json += "\"balance\":" + DoubleToString(AccountBalance(), 2) + ",";
   json += "\"equity\":" + DoubleToString(AccountEquity(), 2) + ",";
   json += "\"margin\":" + DoubleToString(AccountMargin(), 2) + ",";
   json += "\"free_margin\":" + DoubleToString(AccountFreeMargin(), 2) + ",";
   json += "\"margin_level\":" + DoubleToString(AccountInfoDouble(ACCOUNT_MARGIN_LEVEL), 2);
   json += "},";
   
   // Trades array
   json += "\"trades\":[";
   
   bool firstTrade = true;
   int openTradesCount = 0;
   
   // Open trades
   int totalOrders = OrdersTotal();
   Print("TradingJournalBridge: Total open orders: ", totalOrders);
   
   for(int i = 0; i < totalOrders; i++)
   {
      if(OrderSelect(i, SELECT_BY_POS, MODE_TRADES))
      {
         if(!firstTrade) json += ",";
         json += BuildTradeJson(true);
         firstTrade = false;
         openTradesCount++;
         Print("TradingJournalBridge: Added open trade #", OrderTicket(), " - ", OrderSymbol());
      }
   }
   
   Print("TradingJournalBridge: Open trades added: ", openTradesCount);
   
   // Historical trades (if enabled) - INCREMENTAL SYNC with offset
   int historyCount = 0;
   int localTotalEligibleHistory = 0;
   
   if(SYNC_HISTORY)
   {
      datetime startDate = TimeCurrent() - (HISTORY_DAYS * 24 * 60 * 60);
      int maxHistoryTrades = MAX_HISTORY_TRADES; // Limit per batch to prevent large payload
      int totalHistory = OrdersHistoryTotal();
      
      // First pass: count eligible trades
      for(int i = OrdersHistoryTotal() - 1; i >= 0; i--)
      {
         if(OrderSelect(i, SELECT_BY_POS, MODE_HISTORY))
         {
            // Only count trades within date range
            if(OrderCloseTime() >= startDate)
            {
               localTotalEligibleHistory++;
            }
         }
      }
      
      Print("TradingJournalBridge: Total history orders: ", totalHistory);
      Print("TradingJournalBridge: Eligible history trades (last ", HISTORY_DAYS, " days): ", localTotalEligibleHistory);
      Print("TradingJournalBridge: Current history offset: ", historyOffset, " (syncing batch of max ", maxHistoryTrades, " trades)");
      
      // Second pass: sync trades starting from offset
      int processedCount = 0;
      int skippedCount = 0;
      
      for(int i = OrdersHistoryTotal() - 1; i >= 0 && historyCount < maxHistoryTrades; i--)
      {
         if(OrderSelect(i, SELECT_BY_POS, MODE_HISTORY))
         {
            // Only sync trades within date range
            if(OrderCloseTime() >= startDate)
            {
               // Skip trades that have already been synced (based on offset)
               if(skippedCount < historyOffset)
               {
                  skippedCount++;
                  continue;
               }
               
               // Add this trade to payload
               if(!firstTrade) json += ",";
               json += BuildTradeJson(false);
               firstTrade = false;
               historyCount++;
               processedCount++;
               
               Print("TradingJournalBridge: Added history trade #", OrderTicket(), " - ", OrderSymbol(), " closed: ", TimeToString(OrderCloseTime(), TIME_DATE|TIME_SECONDS), " (", processedCount, "/", maxHistoryTrades, " in this batch)");
            }
         }
      }
      
      Print("TradingJournalBridge: History trades added in this batch: ", historyCount);
      Print("TradingJournalBridge: Progress: ", (historyOffset + historyCount), " / ", localTotalEligibleHistory, " trades synced");
      
      // Calculate next offset (will be saved after successful API call)
      pendingHistoryOffset = historyOffset;
      if(historyCount > 0)
      {
         pendingHistoryOffset = historyOffset + historyCount;
      }
      
      // Update global totalEligibleHistory for interval calculation
      totalEligibleHistory = localTotalEligibleHistory;
      
      // Determine if we should continue syncing history
      // IMPORTANT: Don't update historyOffset here - it will be updated after successful API call
      // Only set isSyncingHistory flag here - actual historyOffset will be updated after successful sync
      if(pendingHistoryOffset >= localTotalEligibleHistory && localTotalEligibleHistory > 0)
      {
         Print("TradingJournalBridge: All history trades will be synced after this batch! (", localTotalEligibleHistory, " total)");
         Print("TradingJournalBridge: After sync, will reset offset to 0 and switch to normal interval");
         // Will be set to false after successful sync
         isSyncingHistory = true; // Keep true for now, will be set to false after sync completes
      }
      else if(historyCount >= maxHistoryTrades && pendingHistoryOffset < localTotalEligibleHistory)
      {
         // Batch limit reached but still have more to sync
         Print("TradingJournalBridge: Batch limit reached. Next sync will continue from offset ", pendingHistoryOffset);
         Print("TradingJournalBridge: Remaining trades to sync: ", (localTotalEligibleHistory - pendingHistoryOffset));
         Print("TradingJournalBridge: Will use fast sync interval (", HISTORY_SYNC_INTERVAL_SECONDS, "s) for next batch");
         isSyncingHistory = true; // Continue fast sync for next batch
      }
      else if(historyCount > 0 && pendingHistoryOffset < localTotalEligibleHistory)
      {
         // Still have more trades to sync (even if batch not full)
         Print("TradingJournalBridge: Still have more trades to sync (", (localTotalEligibleHistory - pendingHistoryOffset), " remaining). Setting isSyncingHistory = true");
         isSyncingHistory = true;
      }
      else if(historyCount == 0 && historyOffset == 0)
      {
         // No history trades at all
         Print("TradingJournalBridge: No history trades found. Switching to normal sync interval.");
         isSyncingHistory = false;
      }
      else if(historyCount == 0 && historyOffset > 0)
      {
         // No more trades in this batch, but we have offset
         // Check if we've actually reached the end
         if(historyOffset >= localTotalEligibleHistory)
         {
            Print("TradingJournalBridge: No more trades in batch, but offset (", historyOffset, ") >= total (", localTotalEligibleHistory, ") - sync complete");
            isSyncingHistory = false; // Stop syncing
         }
         else
         {
            Print("TradingJournalBridge: WARNING - No trades in this batch, but offset (", historyOffset, ") < total (", localTotalEligibleHistory, ")");
            Print("TradingJournalBridge: This may indicate history was changed. Will stop to prevent infinite loop.");
            isSyncingHistory = false; // Stop to prevent infinite loop
         }
      }
      else
      {
         // Default: stop syncing if we can't determine status
         Print("TradingJournalBridge: Cannot determine sync status. Stopping to prevent infinite loop.");
         Print("TradingJournalBridge: historyCount: ", historyCount, ", historyOffset: ", historyOffset, ", pendingHistoryOffset: ", pendingHistoryOffset, ", localTotalEligibleHistory: ", localTotalEligibleHistory);
         isSyncingHistory = false;
      }
      
      // Store pending offset (will be saved after successful API call)
   }
   else
   {
      Print("TradingJournalBridge: History sync is DISABLED. Enable SYNC_HISTORY to sync closed trades.");
   }
   
   json += "],";
   
   // Market Regime Analysis (EMA200 + ADX(14) for XAUUSD H1)
   string marketRegime = CalculateMarketRegime();
   json += "\"market_regime\":{";
   json += "\"regime\":\"" + marketRegime + "\"";
   json += "}";
   
   json += "}";
   
   // Count total trades in JSON for logging
   int totalTradesInPayload = openTradesCount + historyCount;
   
   // Save historyCount to global variable so it can be accessed in SyncTrades()
   lastHistoryCount = historyCount;
   
   Print("TradingJournalBridge: Summary - Open: ", openTradesCount, ", History: ", historyCount, ", Total: ", totalTradesInPayload);
   Print("TradingJournalBridge: Market Regime: ", marketRegime);
   
   return json;
}

//+------------------------------------------------------------------+
//| Build JSON for a single trade                                      |
//+------------------------------------------------------------------+
string BuildTradeJson(bool isOpen)
{
   string json = "{";
   
   // Format time for Laravel (ISO 8601 compatible: YYYY.MM.DD HH:MM:SS)
   string openTimeStr = TimeToString(OrderOpenTime(), TIME_DATE|TIME_SECONDS);
   // Convert MT4 format (YYYY.MM.DD HH:MM:SS) to ISO format (YYYY-MM-DD HH:MM:SS)
   StringReplace(openTimeStr, ".", "-");
   
   json += "\"ticket\":" + IntegerToString(OrderTicket()) + ",";
   json += "\"pair\":\"" + OrderSymbol() + "\",";
   json += "\"type\":\"" + GetOrderTypeString(OrderType()) + "\",";
   json += "\"open_time\":\"" + openTimeStr + "\",";
   
   if(!isOpen)
   {
      // Format close time for Laravel
      string closeTimeStr = TimeToString(OrderCloseTime(), TIME_DATE|TIME_SECONDS);
      StringReplace(closeTimeStr, ".", "-");
      json += "\"close_time\":\"" + closeTimeStr + "\",";
      json += "\"close_price\":" + DoubleToString(OrderClosePrice(), (int)MarketInfo(OrderSymbol(), MODE_DIGITS)) + ",";
   }
   else
   {
      json += "\"close_time\":null,";
      json += "\"close_price\":null,";
   }
   
   json += "\"open_price\":" + DoubleToString(OrderOpenPrice(), (int)MarketInfo(OrderSymbol(), MODE_DIGITS)) + ",";
   json += "\"lots\":" + DoubleToString(OrderLots(), 4) + ",";
   json += "\"profit\":" + DoubleToString(OrderProfit(), 2) + ",";
   json += "\"swap\":" + DoubleToString(OrderSwap(), 2) + ",";
   json += "\"commission\":" + DoubleToString(OrderCommission(), 2);
   
   // Optional fields (only if set) - reduce JSON size for ngrok compatibility
   double sl = OrderStopLoss();
   double tp = OrderTakeProfit();
   string comment = OrderComment();
   int magic = OrderMagicNumber();
   
   // Build optional fields with proper comma handling
   string optionalFields = "";
   if(sl > 0) optionalFields += ",\"stop_loss\":" + DoubleToString(sl, (int)MarketInfo(OrderSymbol(), MODE_DIGITS));
   if(tp > 0) optionalFields += ",\"take_profit\":" + DoubleToString(tp, (int)MarketInfo(OrderSymbol(), MODE_DIGITS));
   if(StringLen(comment) > 0) optionalFields += ",\"comment\":\"" + EscapeJsonString(comment) + "\"";
   if(magic != 0) optionalFields += ",\"magic_number\":" + IntegerToString(magic);
   
   json += optionalFields;
   
   json += "}";
   
   return json;
}

//+------------------------------------------------------------------+
//| Convert order type to string                                       |
//+------------------------------------------------------------------+
string GetOrderTypeString(int type)
{
   switch(type)
   {
      case OP_BUY:       return "buy";
      case OP_SELL:      return "sell";
      case OP_BUYLIMIT:  return "buy_limit";
      case OP_SELLLIMIT: return "sell_limit";
      case OP_BUYSTOP:   return "buy_stop";
      case OP_SELLSTOP:  return "sell_stop";
      default:           return "unknown";
   }
}

//+------------------------------------------------------------------+
//| Escape special characters in JSON string                           |
//+------------------------------------------------------------------+
string EscapeJsonString(string str)
{
   string result = str;
   StringReplace(result, "\\", "\\\\");
   StringReplace(result, "\"", "\\\"");
   StringReplace(result, "\n", "\\n");
   StringReplace(result, "\r", "\\r");
   StringReplace(result, "\t", "\\t");
   return result;
}

//+------------------------------------------------------------------+
//| Send data to API endpoint                                          |
//+------------------------------------------------------------------+
string SendToAPI(string jsonData)
{
   // Build headers - ngrok free tier requires browser-like headers
   string headers = "";
   headers += "Content-Type: application/json\r\n";
   headers += "Accept: application/json\r\n";
   
   // Only add ngrok skip header if using ngrok URL
   if(StringFind(API_URL, "ngrok") >= 0)
   {
      headers += "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n";
      headers += "ngrok-skip-browser-warning: true\r\n";
   }
   
   headers += "X-API-Token: " + API_TOKEN + "\r\n";
   headers += "X-Account-ID: " + IntegerToString(ACCOUNT_ID) + "\r\n";
   
   int payloadSize = StringLen(jsonData);
   Print("TradingJournalBridge: Sending to ", API_URL);
   Print("TradingJournalBridge: Account ID: ", ACCOUNT_ID);
   Print("TradingJournalBridge: Payload size: ", payloadSize, " bytes (", DoubleToString(payloadSize / 1024.0, 2), " KB)");
   
   // Ngrok free tier limit is ~500KB, reject if too large
   if(payloadSize > 500000) // 500KB limit for ngrok free tier
   {
      Print("TradingJournalBridge: ERROR - Payload too large (", DoubleToString(payloadSize / 1024.0, 2), " KB)");
      Print("TradingJournalBridge: Ngrok free tier limit is ~500KB. Request will be blocked.");
      Print("TradingJournalBridge: Solutions:");
      Print("  1. Set SYNC_HISTORY = false");
      Print("  2. Reduce MAX_HISTORY_TRADES to 10-20");
      Print("  3. Reduce HISTORY_DAYS to 1-3 days");
      return "";
   }
   
   // Warn if payload is getting large
   if(payloadSize > 300000) // 300KB warning
   {
      Print("TradingJournalBridge: WARNING - Payload is large (", DoubleToString(payloadSize / 1024.0, 2), " KB)");
      Print("TradingJournalBridge: Consider reducing history sync to avoid ngrok blocking.");
   }
   
   char post[];
   char result[];
   string resultHeaders;
   
   // Convert string to char array
   StringToCharArray(jsonData, post, 0, StringLen(jsonData));
   ArrayResize(post, StringLen(jsonData));
   
   // Make HTTP request
   int timeout = 10000; // 10 seconds
   int response = WebRequest(
      "POST",
      API_URL,
      headers,
      timeout,
      post,
      result,
      resultHeaders
   );
   
   if(response == -1)
   {
      int error = GetLastError();
      Print("TradingJournalBridge: WebRequest error ", error);
      
      if(error == 4060)
      {
         Print("TradingJournalBridge: URL not allowed. Add ", API_URL, " to Tools > Options > Expert Advisors");
      }
      else if(error == 5200)
      {
         Print("TradingJournalBridge: Invalid URL or connection refused. Check ngrok is running.");
      }
      else if(error == 5203)
      {
         Print("TradingJournalBridge: Connection failed. Possible causes:");
         Print("  1. Ngrok not running or URL changed");
         Print("  2. Ngrok free tier browser warning blocking request");
         Print("  3. URL not added to MT4 allowed URLs");
         Print("  4. Firewall blocking connection");
      }
      
      return "";
   }
   
   if(response != 200)
   {
      string responseText = CharArrayToString(result);
      Print("TradingJournalBridge: HTTP error ", response);
      Print("TradingJournalBridge: Response: ", StringSubstr(responseText, 0, 200));
      
      if(response == 401)
      {
         Print("TradingJournalBridge: Unauthorized - Check API_TOKEN and ACCOUNT_ID");
      }
      else if(response == 403)
      {
         Print("TradingJournalBridge: Forbidden - Check account permissions");
      }
      else if(response == 422)
      {
         Print("TradingJournalBridge: Validation error - Check payload format");
      }
      
      return "";
   }
   
   return CharArrayToString(result);
}

//+------------------------------------------------------------------+
//| Health check function                                              |
//+------------------------------------------------------------------+
bool CheckAPIConnection()
{
   string healthUrl = StringSubstr(API_URL, 0, StringFind(API_URL, "/incoming")) + "/incoming/health";
   
   string headers = "";
   headers += "Accept: application/json\r\n";
   headers += "X-API-Token: " + API_TOKEN + "\r\n";
   headers += "X-Account-ID: " + IntegerToString(ACCOUNT_ID) + "\r\n";
   
   char post[];
   char result[];
   string resultHeaders;
   
   int response = WebRequest("GET", healthUrl, headers, 5000, post, result, resultHeaders);
   
   return (response == 200);
}

//+------------------------------------------------------------------+
//| Check if sync is requested from web                               |
//+------------------------------------------------------------------+
bool CheckSyncRequest()
{
   string healthUrl = StringSubstr(API_URL, 0, StringFind(API_URL, "/incoming")) + "/incoming/health";
   
   string headers = "";
   headers += "Accept: application/json\r\n";
   if(StringFind(API_URL, "ngrok") >= 0)
   {
      headers += "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n";
      headers += "ngrok-skip-browser-warning: true\r\n";
   }
   headers += "X-API-Token: " + API_TOKEN + "\r\n";
   headers += "X-Account-ID: " + IntegerToString(ACCOUNT_ID) + "\r\n";
   
   char post[];
   char result[];
   string resultHeaders;
   
   int response = WebRequest("GET", healthUrl, headers, 5000, post, result, resultHeaders);
   
   if(response == 200)
   {
      string responseText = CharArrayToString(result);
      // Check for sync_requested flag in response
      int syncRequestedPos = StringFind(responseText, "\"sync_requested\":");
      if(syncRequestedPos >= 0)
      {
         int syncRequestedStart = StringFind(responseText, ":", syncRequestedPos) + 1;
         int syncRequestedEnd = StringFind(responseText, ",", syncRequestedStart);
         if(syncRequestedEnd < 0) syncRequestedEnd = StringFind(responseText, "}", syncRequestedStart);
         string syncRequestedStr = StringSubstr(responseText, syncRequestedStart, syncRequestedEnd - syncRequestedStart);
         StringReplace(syncRequestedStr, " ", "");
         if(StringFind(syncRequestedStr, "true") >= 0)
         {
            Print("TradingJournalBridge: Sync request detected from health check");
            return true;
         }
      }
   }
   
   return false;
}

//+------------------------------------------------------------------+
//| Process commands from API response                                |
//+------------------------------------------------------------------+
void ProcessCommands(string response)
{
   // Check for sync_requested flag in response
   int syncRequestedPos = StringFind(response, "\"sync_requested\":");
   if(syncRequestedPos >= 0)
   {
      int syncRequestedStart = StringFind(response, ":", syncRequestedPos) + 1;
      int syncRequestedEnd = StringFind(response, ",", syncRequestedStart);
      if(syncRequestedEnd < 0) syncRequestedEnd = StringFind(response, "}", syncRequestedStart);
      string syncRequestedStr = StringSubstr(response, syncRequestedStart, syncRequestedEnd - syncRequestedStart);
      StringReplace(syncRequestedStr, " ", "");
      if(StringFind(syncRequestedStr, "true") >= 0)
      {
         syncRequested = true;
         Print("TradingJournalBridge: Sync requested from web detected in response");
      }
   }
   
   // Parse JSON response to extract commands array
   int commandsPos = StringFind(response, "\"commands\":[");
   if(commandsPos < 0) return; // No commands in response
   
   int commandsStart = commandsPos + StringLen("\"commands\":[");
   int commandsEnd = StringFind(response, "]", commandsStart);
   if(commandsEnd < 0) return;
   
   string commandsStr = StringSubstr(response, commandsStart, commandsEnd - commandsStart);
   
   // Parse each command (simple parsing for JSON array)
   int cmdStart = 0;
   while(cmdStart < StringLen(commandsStr))
   {
      int cmdOpen = StringFind(commandsStr, "{", cmdStart);
      if(cmdOpen < 0) break;
      
      int cmdClose = StringFind(commandsStr, "}", cmdOpen);
      if(cmdClose < 0) break;
      
      string cmdJson = StringSubstr(commandsStr, cmdOpen, cmdClose - cmdOpen + 1);
      
      // Extract command ID
      int idPos = StringFind(cmdJson, "\"id\":");
      if(idPos >= 0)
      {
         int idStart = StringFind(cmdJson, ":", idPos) + 1;
         int idEnd = StringFind(cmdJson, ",", idStart);
         if(idEnd < 0) idEnd = StringFind(cmdJson, "}", idStart);
         string cmdIdStr = StringSubstr(cmdJson, idStart, idEnd - idStart);
         StringReplace(cmdIdStr, " ", "");
         int cmdId = (int)StringToInteger(cmdIdStr);
         
         // Extract command type
         int cmdTypePos = StringFind(cmdJson, "\"command\":\"");
         if(cmdTypePos >= 0)
         {
            int cmdTypeStart = cmdTypePos + StringLen("\"command\":\"");
            int cmdTypeEnd = StringFind(cmdJson, "\"", cmdTypeStart);
            string cmdType = StringSubstr(cmdJson, cmdTypeStart, cmdTypeEnd - cmdTypeStart);
            
            Print("TradingJournalBridge: Processing command ID ", cmdId, " - Type: ", cmdType);
            
            // Execute command
            ExecuteCommand(cmdId, cmdType, cmdJson);
         }
      }
      
      cmdStart = cmdClose + 1;
   }
}

//+------------------------------------------------------------------+
//| Execute a command                                                 |
//+------------------------------------------------------------------+
void ExecuteCommand(int cmdId, string cmdType, string cmdJson)
{
   bool success = false;
   string result = "";
   
   if(cmdType == "close_all")
   {
      // Extract intercept_all flag from params if provided
      bool interceptAll = false;
      int paramsPos = StringFind(cmdJson, "\"params\":");
      if(paramsPos >= 0)
      {
         int interceptAllPos = StringFind(cmdJson, "\"intercept_all\":", paramsPos);
         if(interceptAllPos >= 0)
         {
            int interceptAllStart = StringFind(cmdJson, ":", interceptAllPos) + 1;
            int interceptAllEnd = StringFind(cmdJson, ",", interceptAllStart);
            if(interceptAllEnd < 0) interceptAllEnd = StringFind(cmdJson, "}", interceptAllStart);
            string interceptAllStr = StringSubstr(cmdJson, interceptAllStart, interceptAllEnd - interceptAllStart);
            StringReplace(interceptAllStr, " ", "");
            if(StringFind(interceptAllStr, "true") >= 0)
            {
               interceptAll = true;
               // Save intercept_all flag to Global Variable
               string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(interceptAllGvKey, 1.0);
               Print("TradingJournalBridge: Intercept all trading enabled via command");
            }
         }
      }
      
      // Extract magic buy and sell from params if provided (only if intercept_all is false)
      int targetMagicBuy = TARGET_MAGIC_BUY;
      int targetMagicSell = TARGET_MAGIC_SELL;
      if(!interceptAll && paramsPos >= 0)
      {
         int magicBuyPos = StringFind(cmdJson, "\"magic_buy\":", paramsPos);
         if(magicBuyPos >= 0)
         {
            int magicBuyStart = StringFind(cmdJson, ":", magicBuyPos) + 1;
            int magicBuyEnd = StringFind(cmdJson, ",", magicBuyStart);
            if(magicBuyEnd < 0) magicBuyEnd = StringFind(cmdJson, "}", magicBuyStart);
            string magicBuyStr = StringSubstr(cmdJson, magicBuyStart, magicBuyEnd - magicBuyStart);
            StringReplace(magicBuyStr, " ", "");
            targetMagicBuy = (int)StringToInteger(magicBuyStr);
         }
         
         int magicSellPos = StringFind(cmdJson, "\"magic_sell\":", paramsPos);
         if(magicSellPos >= 0)
         {
            int magicSellStart = StringFind(cmdJson, ":", magicSellPos) + 1;
            int magicSellEnd = StringFind(cmdJson, ",", magicSellStart);
            if(magicSellEnd < 0) magicSellEnd = StringFind(cmdJson, "}", magicSellStart);
            string magicSellStr = StringSubstr(cmdJson, magicSellStart, magicSellEnd - magicSellStart);
            StringReplace(magicSellStr, " ", "");
            targetMagicSell = (int)StringToInteger(magicSellStr);
         }
      }
      
      // If intercept_all is true, close all trades (magic = 0, 0)
      if(interceptAll)
      {
         targetMagicBuy = 0;
         targetMagicSell = 0;
      }
      
      int closed = CloseTradesByMagicBuySell(targetMagicBuy, targetMagicSell);
      if(closed > 0)
      {
         success = true;
         result = "Closed " + IntegerToString(closed) + " trades";
         if(targetMagicBuy > 0 || targetMagicSell > 0)
         {
            result += " (Magic BUY: " + IntegerToString(targetMagicBuy) + ", Magic SELL: " + IntegerToString(targetMagicSell) + ")";
         }
      }
      else
      {
         success = true;
         result = "No open trades to close";
         if(targetMagicBuy > 0 || targetMagicSell > 0)
         {
            result += " (Magic BUY: " + IntegerToString(targetMagicBuy) + ", Magic SELL: " + IntegerToString(targetMagicSell) + ")";
         }
      }
      Print("TradingJournalBridge: Command ", cmdId, " executed - ", result);
   }
   else if(cmdType == "pause")
   {
      // Extract intercept_all flag from params if provided
      int paramsPos = StringFind(cmdJson, "\"params\":");
      if(paramsPos >= 0)
      {
         int interceptAllPos = StringFind(cmdJson, "\"intercept_all\":", paramsPos);
         if(interceptAllPos >= 0)
         {
            int interceptAllStart = StringFind(cmdJson, ":", interceptAllPos) + 1;
            int interceptAllEnd = StringFind(cmdJson, ",", interceptAllStart);
            if(interceptAllEnd < 0) interceptAllEnd = StringFind(cmdJson, "}", interceptAllStart);
            string interceptAllStr = StringSubstr(cmdJson, interceptAllStart, interceptAllEnd - interceptAllStart);
            StringReplace(interceptAllStr, " ", "");
            if(StringFind(interceptAllStr, "true") >= 0)
            {
               // Save intercept_all flag to Global Variable
               string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(interceptAllGvKey, 1.0);
               Print("TradingJournalBridge: Intercept all trading enabled via pause command");
            }
         }
      }
      
      isPaused = true;
      SavePauseState();
      success = true;
      result = "Trading paused";
      Print("TradingJournalBridge: Command ", cmdId, " executed - Trading PAUSED");
   }
   else if(cmdType == "resume")
   {
      // Extract intercept_all flag from params if provided
      int paramsPos = StringFind(cmdJson, "\"params\":");
      if(paramsPos >= 0)
      {
         int interceptAllPos = StringFind(cmdJson, "\"intercept_all\":", paramsPos);
         if(interceptAllPos >= 0)
         {
            int interceptAllStart = StringFind(cmdJson, ":", interceptAllPos) + 1;
            int interceptAllEnd = StringFind(cmdJson, ",", interceptAllStart);
            if(interceptAllEnd < 0) interceptAllEnd = StringFind(cmdJson, "}", interceptAllStart);
            string interceptAllStr = StringSubstr(cmdJson, interceptAllStart, interceptAllEnd - interceptAllStart);
            StringReplace(interceptAllStr, " ", "");
            if(StringFind(interceptAllStr, "true") >= 0)
            {
               // Save intercept_all flag to Global Variable
               string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(interceptAllGvKey, 1.0);
               Print("TradingJournalBridge: Intercept all trading enabled via resume command");
            }
         }
      }
      
      isPaused = false;
      SavePauseState();
      success = true;
      result = "Trading resumed";
      Print("TradingJournalBridge: Command ", cmdId, " executed - Trading RESUMED");
   }
   else if(cmdType == "schedule")
   {
      int paramsPos = StringFind(cmdJson, "\"params\":");
      if(paramsPos >= 0)
      {
         int daysPos = StringFind(cmdJson, "\"days\":[", paramsPos);
         if(daysPos >= 0)
         {
            int daysStart = daysPos + StringLen("\"days\":[");
            int daysEnd = StringFind(cmdJson, "]", daysStart);
            string daysStr = StringSubstr(cmdJson, daysStart, daysEnd - daysStart);
            
            for(int i = 0; i < 7; i++) scheduleDays[i] = 0;
            
            int dayStart = 0;
            while(dayStart < StringLen(daysStr))
            {
               int commaPos = StringFind(daysStr, ",", dayStart);
               if(commaPos < 0) commaPos = StringLen(daysStr);
               
               string dayStr = StringSubstr(daysStr, dayStart, commaPos - dayStart);
               StringReplace(dayStr, " ", "");
               int day = (int)StringToInteger(dayStr);
               
               if(day >= 1 && day <= 7)
               {
                  scheduleDays[day - 1] = 1;
               }
               
               dayStart = commaPos + 1;
            }
         }
         
         int startTimePos = StringFind(cmdJson, "\"start_time\":\"", paramsPos);
         if(startTimePos >= 0)
         {
            int startTimeStart = startTimePos + StringLen("\"start_time\":\"");
            int startTimeEnd = StringFind(cmdJson, "\"", startTimeStart);
            scheduleStartTime = StringSubstr(cmdJson, startTimeStart, startTimeEnd - startTimeStart);
         }
         
         int endTimePos = StringFind(cmdJson, "\"end_time\":\"", paramsPos);
         if(endTimePos >= 0)
         {
            int endTimeStart = endTimePos + StringLen("\"end_time\":\"");
            int endTimeEnd = StringFind(cmdJson, "\"", endTimeStart);
            scheduleEndTime = StringSubstr(cmdJson, endTimeStart, endTimeEnd - endTimeStart);
         }
         
         // Extract intercept_all flag from schedule params if provided
         bool interceptAll = false;
         int interceptAllPos = StringFind(cmdJson, "\"intercept_all\":", paramsPos);
         if(interceptAllPos >= 0)
         {
            int interceptAllStart = StringFind(cmdJson, ":", interceptAllPos) + 1;
            int interceptAllEnd = StringFind(cmdJson, ",", interceptAllStart);
            if(interceptAllEnd < 0) interceptAllEnd = StringFind(cmdJson, "}", interceptAllStart);
            string interceptAllStr = StringSubstr(cmdJson, interceptAllStart, interceptAllEnd - interceptAllStart);
            StringReplace(interceptAllStr, " ", "");
            if(StringFind(interceptAllStr, "true") >= 0)
            {
               interceptAll = true;
               // Save intercept_all flag to Global Variable
               string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(interceptAllGvKey, 1.0);
               Print("TradingJournalBridge: Intercept all trading enabled via schedule command");
            }
         }
         
         // Extract magic buy and sell from schedule params if provided (only if intercept_all is false)
         int magicBuyPos = StringFind(cmdJson, "\"magic_buy\":", paramsPos);
         if(!interceptAll && magicBuyPos >= 0)
         {
            int magicBuyStart = StringFind(cmdJson, ":", magicBuyPos) + 1;
            int magicBuyEnd = StringFind(cmdJson, ",", magicBuyStart);
            if(magicBuyEnd < 0) magicBuyEnd = StringFind(cmdJson, "}", magicBuyStart);
            string magicBuyStr = StringSubstr(cmdJson, magicBuyStart, magicBuyEnd - magicBuyStart);
            StringReplace(magicBuyStr, " ", "");
            int magicBuyNum = (int)StringToInteger(magicBuyStr);
            if(magicBuyNum > 0)
            {
               // Update TARGET_MAGIC_BUY via Global Variable (can't change input parameter at runtime)
               string magicBuyGvKey = "TJ_TargetMagicBuy_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(magicBuyGvKey, (double)magicBuyNum);
               Print("TradingJournalBridge: Target magic BUY updated to ", magicBuyNum);
            }
         }
         
         int magicSellPos = StringFind(cmdJson, "\"magic_sell\":", paramsPos);
         if(!interceptAll && magicSellPos >= 0)
         {
            int magicSellStart = StringFind(cmdJson, ":", magicSellPos) + 1;
            int magicSellEnd = StringFind(cmdJson, ",", magicSellStart);
            if(magicSellEnd < 0) magicSellEnd = StringFind(cmdJson, "}", magicSellStart);
            string magicSellStr = StringSubstr(cmdJson, magicSellStart, magicSellEnd - magicSellStart);
            StringReplace(magicSellStr, " ", "");
            int magicSellNum = (int)StringToInteger(magicSellStr);
            if(magicSellNum > 0)
            {
               // Update TARGET_MAGIC_SELL via Global Variable (can't change input parameter at runtime)
               string magicSellGvKey = "TJ_TargetMagicSell_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(magicSellGvKey, (double)magicSellNum);
               Print("TradingJournalBridge: Target magic SELL updated to ", magicSellNum);
            }
         }
         
         SaveScheduleState();
         success = true;
         result = "Schedule updated";
         Print("TradingJournalBridge: Command ", cmdId, " executed - Schedule updated");
      }
   }
   
   if(success)
   {
      UpdateCommandStatus(cmdId, "completed", result);
   }
   else
   {
      UpdateCommandStatus(cmdId, "failed", "Command execution failed");
   }
}

//+------------------------------------------------------------------+
//| Close all open trades                                             |
//+------------------------------------------------------------------+
int CloseAllTrades()
{
   // Check if intercept all trading mode is enabled
   bool interceptAll = INTERCEPT_ALL_TRADING;
   string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(interceptAllGvKey))
   {
      interceptAll = (GlobalVariableGet(interceptAllGvKey) > 0.5);
   }
   
   if(interceptAll)
   {
      return CloseTradesByMagicBuySell(0, 0); // 0,0 means all trades
   }
   else
   {
      return CloseTradesByMagicBuySell(TARGET_MAGIC_BUY, TARGET_MAGIC_SELL);
   }
}

//+------------------------------------------------------------------+
//| Close trades by magic buy and sell (0 = all trades)              |
//+------------------------------------------------------------------+
int CloseTradesByMagicBuySell(int magicBuy, int magicSell)
{
   int closed = 0;
   
   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(OrderSelect(i, SELECT_BY_POS, MODE_TRADES))
      {
         int orderMagic = OrderMagicNumber();
         int orderType = OrderType();
         
         // Check if this trade should be closed based on magic buy/sell
         bool shouldClose = false;
         
         if(magicBuy == 0 && magicSell == 0)
         {
            // No magic specified - close all trades
            shouldClose = true;
         }
         else
         {
            // Check if order type matches magic buy or sell
            if(orderType == OP_BUY && magicBuy > 0 && orderMagic == magicBuy)
            {
               shouldClose = true;
            }
            else if(orderType == OP_SELL && magicSell > 0 && orderMagic == magicSell)
            {
               shouldClose = true;
            }
         }
         
         if(!shouldClose)
         {
            continue; // Skip this trade
         }
         
         if(OrderClose(OrderTicket(), OrderLots(), OrderClosePrice(), 3))
         {
            closed++;
            Print("TradingJournalBridge: Closed trade #", OrderTicket(), " (Type: ", (orderType == OP_BUY ? "BUY" : "SELL"), ", Magic: ", orderMagic, ")");
         }
         else
         {
            Print("TradingJournalBridge: Failed to close trade #", OrderTicket(), " - Error: ", GetLastError());
         }
      }
   }
   
   return closed;
}

//+------------------------------------------------------------------+
//| Monitor and control trading (intercept other EAs)                 |
//+------------------------------------------------------------------+
void MonitorAndControlTrading()
{
   // Get effective target magic buy and sell (from Global Variable if set, otherwise from input)
   int effectiveMagicBuy = TARGET_MAGIC_BUY;
   int effectiveMagicSell = TARGET_MAGIC_SELL;
   string magicBuyGvKey = "TJ_TargetMagicBuy_" + IntegerToString(ACCOUNT_ID);
   string magicSellGvKey = "TJ_TargetMagicSell_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(magicBuyGvKey))
   {
      int gvMagicBuy = (int)GlobalVariableGet(magicBuyGvKey);
      if(gvMagicBuy > 0)
      {
         effectiveMagicBuy = gvMagicBuy;
      }
   }
   if(GlobalVariableCheck(magicSellGvKey))
   {
      int gvMagicSell = (int)GlobalVariableGet(magicSellGvKey);
      if(gvMagicSell > 0)
      {
         effectiveMagicSell = gvMagicSell;
      }
   }
   
   // Check if trading should be paused or outside schedule
   bool shouldBlockTrading = false;
   string reason = "";
   
   // Check pause flag
   if(isPaused)
   {
      shouldBlockTrading = true;
      reason = "Trading is PAUSED";
   }
   
   // Check schedule
   if(!shouldBlockTrading && StringLen(scheduleStartTime) > 0 && StringLen(scheduleEndTime) > 0)
   {
      // Get current day of week (0=Sunday, 1=Monday, ..., 6=Saturday in MT4)
      int currentDay = DayOfWeek();
      // Convert to our format (0=Monday, 6=Sunday)
      int dayIndex = (currentDay == 0) ? 6 : (currentDay - 1);
      
      // Check if current day is in schedule
      if(scheduleDays[dayIndex] == 0)
      {
         shouldBlockTrading = true;
         reason = "Outside schedule (day not allowed)";
      }
      else
      {
         // Check if current time is within schedule
         datetime currentTime = TimeLocal();
         int currentHour = TimeHour(currentTime);
         int currentMinute = TimeMinute(currentTime);
         
         // Parse start time
         int startHour = (int)StringToInteger(StringSubstr(scheduleStartTime, 0, 2));
         int startMinute = (int)StringToInteger(StringSubstr(scheduleStartTime, 3, 2));
         
         // Parse end time
         int endHour = (int)StringToInteger(StringSubstr(scheduleEndTime, 0, 2));
         int endMinute = (int)StringToInteger(StringSubstr(scheduleEndTime, 3, 2));
         
         // Convert to minutes for comparison
         int currentMinutes = currentHour * 60 + currentMinute;
         int startMinutes = startHour * 60 + startMinute;
         int endMinutes = endHour * 60 + endMinute;
         
         // Check if current time is within schedule
         if(currentMinutes < startMinutes || currentMinutes > endMinutes)
         {
            shouldBlockTrading = true;
            reason = "Outside schedule (time not allowed)";
         }
      }
   }
   
   // If trading should be blocked, close any open positions with target magic number
   if(shouldBlockTrading)
   {
      static datetime lastCheckTime = 0;
      // Only check every 5 seconds to avoid too frequent operations
      if(TimeCurrent() - lastCheckTime >= 5)
      {
         lastCheckTime = TimeCurrent();
         
         // Check for new positions opened by target EA
         for(int i = OrdersTotal() - 1; i >= 0; i--)
         {
            if(OrderSelect(i, SELECT_BY_POS, MODE_TRADES))
            {
               int orderMagic = OrderMagicNumber();
               int orderType = OrderType();
               
               // Check if this trade should be closed based on magic buy/sell
               bool shouldClose = false;
               
               if(effectiveMagicBuy == 0 && effectiveMagicSell == 0)
               {
                  // No magic specified - close all trades
                  shouldClose = true;
               }
               else
               {
                  // Check if order type matches magic buy or sell
                  if(orderType == OP_BUY && effectiveMagicBuy > 0 && orderMagic == effectiveMagicBuy)
                  {
                     shouldClose = true;
                  }
                  else if(orderType == OP_SELL && effectiveMagicSell > 0 && orderMagic == effectiveMagicSell)
                  {
                     shouldClose = true;
                  }
               }
               
               if(shouldClose)
               {
                  if(OrderClose(OrderTicket(), OrderLots(), OrderClosePrice(), 3))
                  {
                     Print("TradingJournalBridge: Intercepted and closed trade #", OrderTicket(), " (Type: ", (orderType == OP_BUY ? "BUY" : "SELL"), ", Magic: ", orderMagic, ") - Reason: ", reason);
                  }
                  else
                  {
                     Print("TradingJournalBridge: Failed to intercept trade #", OrderTicket(), " - Error: ", GetLastError());
                  }
               }
            }
         }
      }
   }
   
   // Also set Global Variable to signal other EAs (if they can read it)
   string gvKey = "TJ_TradingBlocked_" + IntegerToString(ACCOUNT_ID);
   GlobalVariableSet(gvKey, shouldBlockTrading ? 1.0 : 0.0);
   
   // Set magic-specific flags
   if(effectiveMagicBuy > 0)
   {
      string magicBuyBlockGvKey = "TJ_BlockMagicBuy_" + IntegerToString(ACCOUNT_ID) + "_" + IntegerToString(effectiveMagicBuy);
      GlobalVariableSet(magicBuyBlockGvKey, shouldBlockTrading ? 1.0 : 0.0);
   }
   if(effectiveMagicSell > 0)
   {
      string magicSellBlockGvKey = "TJ_BlockMagicSell_" + IntegerToString(ACCOUNT_ID) + "_" + IntegerToString(effectiveMagicSell);
      GlobalVariableSet(magicSellBlockGvKey, shouldBlockTrading ? 1.0 : 0.0);
   }
}

//+------------------------------------------------------------------+
//| Save pause state to Global Variables                              |
//+------------------------------------------------------------------+
void SavePauseState()
{
   string gvKey = "TJ_Paused_" + IntegerToString(ACCOUNT_ID);
   GlobalVariableSet(gvKey, isPaused ? 1.0 : 0.0);
}

//+------------------------------------------------------------------+
//| Load pause state from Global Variables                           |
//+------------------------------------------------------------------+
void LoadPauseState()
{
   string gvKey = "TJ_Paused_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(gvKey))
   {
      isPaused = (GlobalVariableGet(gvKey) > 0.5);
   }
   else
   {
      isPaused = false;
   }
}

//+------------------------------------------------------------------+
//| Save schedule state to Global Variables                           |
//+------------------------------------------------------------------+
void SaveScheduleState()
{
   string gvKey = "TJ_ScheduleDays_" + IntegerToString(ACCOUNT_ID);
   string daysStr = "";
   for(int i = 0; i < 7; i++)
   {
      if(i > 0) daysStr += ",";
      daysStr += IntegerToString(scheduleDays[i]);
   }
   GlobalVariableSet(gvKey, StringToDouble(daysStr));
}

//+------------------------------------------------------------------+
//| Load schedule state from Global Variables                         |
//+------------------------------------------------------------------+
void LoadScheduleState()
{
   string gvKey = "TJ_ScheduleDays_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(gvKey))
   {
      double daysValue = GlobalVariableGet(gvKey);
      string daysStr = DoubleToString(daysValue, 0);
      int dayStart = 0;
      int dayIndex = 0;
      while(dayStart < StringLen(daysStr) && dayIndex < 7)
      {
         int commaPos = StringFind(daysStr, ",", dayStart);
         if(commaPos < 0) commaPos = StringLen(daysStr);
         
         string dayStr = StringSubstr(daysStr, dayStart, commaPos - dayStart);
         scheduleDays[dayIndex] = (int)StringToInteger(dayStr);
         dayIndex++;
         dayStart = commaPos + 1;
      }
   }
}

//+------------------------------------------------------------------+
//| Update command status on backend                                 |
//+------------------------------------------------------------------+
void UpdateCommandStatus(int cmdId, string status, string result)
{
   string baseUrl = API_URL;
   int incomingPos = StringFind(baseUrl, "/incoming");
   if(incomingPos < 0) return;
   
   string url = StringSubstr(baseUrl, 0, incomingPos) + "/ea-commands/" + IntegerToString(cmdId) + "/status";
   
   string json = "{";
   json += "\"status\":\"" + status + "\"";
   if(StringLen(result) > 0)
   {
      json += ",\"result\":\"" + EscapeJsonString(result) + "\"";
   }
   json += "}";
   
   string headers = "";
   headers += "Content-Type: application/json\r\n";
   headers += "Accept: application/json\r\n";
   if(StringFind(API_URL, "ngrok") >= 0)
   {
      headers += "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n";
      headers += "ngrok-skip-browser-warning: true\r\n";
   }
   headers += "X-API-Token: " + API_TOKEN + "\r\n";
   headers += "X-Account-ID: " + IntegerToString(ACCOUNT_ID) + "\r\n";
   
   char post[];
   char resultData[];
   string resultHeaders;
   
   StringToCharArray(json, post, 0, StringLen(json));
   ArrayResize(post, StringLen(json));
   
   int response = WebRequest("PUT", url, headers, 5000, post, resultData, resultHeaders);
   
   if(response == 200)
   {
      Print("TradingJournalBridge: Command ", cmdId, " status updated to ", status);
   }
   else
   {
      Print("TradingJournalBridge: Failed to update command ", cmdId, " status - HTTP ", response);
   }
}

//+------------------------------------------------------------------+
//| Calculate EMA200 for XAUUSD H1                                    |
//+------------------------------------------------------------------+
double CalculateEMA200()
{
   string symbol = "XAUUSD";
   int timeframe = PERIOD_H1;
   int period = 200;
   
   // Use iMA (Moving Average) indicator with EMA mode
   // Shift = 1 means use closed candle (no repaint)
   double ema200 = iMA(symbol, timeframe, period, 0, MODE_EMA, PRICE_CLOSE, 1);
   
   if(ema200 == 0 || ema200 == EMPTY_VALUE)
   {
      Print("TradingJournalBridge: EMA200 calculation failed or insufficient data");
      return 0;
   }
   
   return ema200;
}

//+------------------------------------------------------------------+
//| Calculate ADX(14) for XAUUSD H1                                   |
//+------------------------------------------------------------------+
double CalculateADX14()
{
   string symbol = "XAUUSD";
   int timeframe = PERIOD_H1;
   int period = 14;
   
   // Use iADX (Average Directional Movement Index) indicator
   // MODE_MAIN returns the ADX line value
   // Shift = 1 means use closed candle (no repaint)
   double adx = iADX(symbol, timeframe, period, PRICE_CLOSE, MODE_MAIN, 1);
   
   if(adx == 0 || adx == EMPTY_VALUE)
   {
      Print("TradingJournalBridge: ADX(14) calculation failed or insufficient data");
      return 0;
   }
   
   return adx;
}

//+------------------------------------------------------------------+
//| Calculate Market Regime based on EMA200 and ADX(14)              |
//| Returns: "SIDEWAYS", "BULLISH", or "BEARISH"                     |
//+------------------------------------------------------------------+
string CalculateMarketRegime()
{
   string symbol = "XAUUSD";
   int timeframe = PERIOD_H1;
   
   // Calculate ADX(14) - use closed candle (shift = 1)
   double adx = CalculateADX14();
   
   if(adx == 0 || adx == EMPTY_VALUE)
   {
      Print("TradingJournalBridge: Cannot calculate market regime - ADX data unavailable");
      return "UNKNOWN";
   }
   
   // ADX < 20 → SIDEWAYS
   if(adx < 20.0)
   {
      Print("TradingJournalBridge: Market Regime = SIDEWAYS (ADX: ", DoubleToString(adx, 2), " < 20)");
      return "SIDEWAYS";
   }
   
   // ADX >= 25 → Check EMA200 vs Close price
   if(adx >= 25.0)
   {
      // Calculate EMA200 - use closed candle (shift = 1)
      double ema200 = CalculateEMA200();
      
      if(ema200 == 0 || ema200 == EMPTY_VALUE)
      {
         Print("TradingJournalBridge: Cannot calculate market regime - EMA200 data unavailable");
         return "UNKNOWN";
      }
      
      // Get current close price (closed candle, shift = 1)
      double closePrice = iClose(symbol, timeframe, 1);
      
      if(closePrice == 0 || closePrice == EMPTY_VALUE)
      {
         Print("TradingJournalBridge: Cannot calculate market regime - Close price unavailable");
         return "UNKNOWN";
      }
      
      // ADX >= 25 AND close > EMA200 → BULLISH
      if(closePrice > ema200)
      {
         Print("TradingJournalBridge: Market Regime = BULLISH (ADX: ", DoubleToString(adx, 2), " >= 25, Close: ", DoubleToString(closePrice, 2), " > EMA200: ", DoubleToString(ema200, 2), ")");
         return "BULLISH";
      }
      // ADX >= 25 AND close < EMA200 → BEARISH
      else if(closePrice < ema200)
      {
         Print("TradingJournalBridge: Market Regime = BEARISH (ADX: ", DoubleToString(adx, 2), " >= 25, Close: ", DoubleToString(closePrice, 2), " < EMA200: ", DoubleToString(ema200, 2), ")");
         return "BEARISH";
      }
      else
      {
         // Close == EMA200 (rare case) → SIDEWAYS
         Print("TradingJournalBridge: Market Regime = SIDEWAYS (ADX: ", DoubleToString(adx, 2), " >= 25, Close: ", DoubleToString(closePrice, 2), " == EMA200: ", DoubleToString(ema200, 2), ")");
         return "SIDEWAYS";
      }
   }
   
   // ADX between 20 and 25 → SIDEWAYS (weak trend)
   Print("TradingJournalBridge: Market Regime = SIDEWAYS (ADX: ", DoubleToString(adx, 2), " between 20-25)");
   return "SIDEWAYS";
}
//+------------------------------------------------------------------+

