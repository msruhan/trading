//+------------------------------------------------------------------+
//| TradingJournalBridge.mq4
//| Trading Journal Portfolio Dashboard
//| https://tradingjournal.local
//+------------------------------------------------------------------+
#property copyright "Trading Journal"
#property link "https://tradingjournal.local"
#property version "1.00"
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
input int      TRADING_MODE = 0;                                               // Trading Mode: 0=Auto (based on EMA200+ADX), 1=Buy Only, 2=Sell Only

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
         // Only resume history sync if there's a sync request from web
         // Don't auto-start history sync on EA restart - wait for web trigger
         bool savedFlag = (GlobalVariableGet(flagKey) > 0.5);
         if(savedFlag && historyOffset > 0)
         {
            Print("TradingJournalBridge: Found saved history sync flag, but will wait for web trigger");
            Print("TradingJournalBridge: History offset: ", historyOffset, " - will continue when sync is requested from web");
            // Don't set isSyncingHistory = true here - wait for web trigger
            isSyncingHistory = false;
         }
         else
         {
            isSyncingHistory = false; // Always start with false - wait for web trigger
            Print("TradingJournalBridge: Resumed sync flag: false (will wait for web trigger)");
         }
      }
      else
      {
         // Always start with false - wait for web trigger
         isSyncingHistory = false;
         Print("TradingJournalBridge: Starting fresh - will wait for web trigger before syncing history");
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
   // IMPORTANT: If Global Variable exists with value 0 and input parameter > 0, delete Global Variable to use input parameter
   string magicBuyGvKey = "TJ_TargetMagicBuy_" + IntegerToString(ACCOUNT_ID);
   string magicSellGvKey = "TJ_TargetMagicSell_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(magicBuyGvKey))
   {
      int loadedMagicBuy = (int)GlobalVariableGet(magicBuyGvKey);
      if(loadedMagicBuy > 0)
      {
         if(loadedMagicBuy != TARGET_MAGIC_BUY)
         {
            Print("TradingJournalBridge: Target magic BUY from Global Variables (", loadedMagicBuy, ") differs from input parameter (", TARGET_MAGIC_BUY, ")");
            Print("TradingJournalBridge: Using Global Variable value: ", loadedMagicBuy);
         }
      }
      else if(loadedMagicBuy == 0 && TARGET_MAGIC_BUY > 0)
      {
         // Global Variable is 0 but input parameter is set (> 0) - delete Global Variable to use input parameter
         GlobalVariableDel(magicBuyGvKey);
         Print("TradingJournalBridge: Removed Global Variable for Magic Buy (was 0), using input parameter: ", TARGET_MAGIC_BUY);
      }
   }
   if(GlobalVariableCheck(magicSellGvKey))
   {
      int loadedMagicSell = (int)GlobalVariableGet(magicSellGvKey);
      if(loadedMagicSell > 0)
      {
         if(loadedMagicSell != TARGET_MAGIC_SELL)
         {
            Print("TradingJournalBridge: Target magic SELL from Global Variables (", loadedMagicSell, ") differs from input parameter (", TARGET_MAGIC_SELL, ")");
            Print("TradingJournalBridge: Using Global Variable value: ", loadedMagicSell);
         }
      }
      else if(loadedMagicSell == 0 && TARGET_MAGIC_SELL > 0)
      {
         // Global Variable is 0 but input parameter is set (> 0) - delete Global Variable to use input parameter
         GlobalVariableDel(magicSellGvKey);
         Print("TradingJournalBridge: Removed Global Variable for Magic Sell (was 0), using input parameter: ", TARGET_MAGIC_SELL);
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
      Print("TradingJournalBridge: Magic BUY/SELL parameters are ignored");
      Print("TradingJournalBridge: =========================================");
   }
   else if(TARGET_MAGIC_BUY > 0 || TARGET_MAGIC_SELL > 0)
   {
      Print("TradingJournalBridge: Will intercept EA with Magic BUY: ", TARGET_MAGIC_BUY, ", Magic SELL: ", TARGET_MAGIC_SELL);
   }
   else
   {
      Print("TradingJournalBridge: Will intercept ALL EAs (Magic BUY/SELL: 0 means all)");
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
      Print("TradingJournalBridge: Please click Sync Now button on the website");
      Print("TradingJournalBridge: EA will check for sync request every 10 seconds");
      Print("TradingJournalBridge: =========================================");
      // DO NOT call SyncTrades() here - wait for web trigger
   }
   
   // Initial chart display update
   UpdateChartDisplay();
   
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
   // Check for new orders immediately on every tick for faster intercept
   MonitorAndControlTrading();
   
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
   
   // Update chart display with important information
   UpdateChartDisplay();
   
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
      // IMPORTANT: History sync batches should ONLY continue if sync_requested is still true
      // This prevents infinite looping when no sync is requested
      if(isSyncingHistory && SYNC_HISTORY)
      {
         // Check if syncRequested is still true - if not, stop history sync to prevent infinite loop
         if(!syncRequested)
         {
            Print("TradingJournalBridge: [OnTimer] [WARNING] History sync in progress but syncRequested is false - stopping to prevent infinite loop");
            isSyncingHistory = false;
            historyOffset = 0; // Reset offset
            // Save sync flag to Global Variables
            string flagKey = "TJ_IsSyncingHistory_" + IntegerToString(ACCOUNT_ID);
            GlobalVariableSet(flagKey, 0.0);
            Print("TradingJournalBridge: History sync stopped. Will wait for next web trigger.");
         }
         else if(secondsSinceLastSync >= currentSyncInterval)
         {
            // syncRequested is still true - continue syncing history batches
            shouldSync = true;
            Print("TradingJournalBridge: [OnTimer] History sync in progress - continuing batch sync (web-triggered, interval: ", currentSyncInterval, "s)");
         }
      }
      else if(syncRequested)
      {
         // Sync was requested from web - trigger sync
         shouldSync = true;
         Print("TradingJournalBridge: [OnTimer] [SYNC REQUEST] Sync requested from web - triggering sync NOW");
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
            Print("TradingJournalBridge: [OnTimer] Checking for sync request from web...");
            if(CheckSyncRequest())
            {
               syncRequested = true;
               shouldSync = true;
               Print("TradingJournalBridge: =========================================");
               Print("TradingJournalBridge: [SYNC REQUEST] ✅ SYNC REQUEST DETECTED FROM WEB");
               Print("TradingJournalBridge: =========================================");
               Print("TradingJournalBridge: Account ID: ", ACCOUNT_ID);
               Print("TradingJournalBridge: Time: ", TimeToString(TimeCurrent(), TIME_DATE|TIME_SECONDS));
               Print("TradingJournalBridge: Preparing to sync...");
               Print("TradingJournalBridge: =========================================");
            }
            else
            {
               // Log status every 30 seconds to show EA is waiting
               static datetime lastStatusLog = 0;
               if(TimeCurrent() - lastStatusLog >= 30)
               {
                  lastStatusLog = TimeCurrent();
                  Print("TradingJournalBridge: [OnTimer] Status: ⏳ WAITING for sync trigger from web (checking every 10s)");
                  Print("TradingJournalBridge: [OnTimer] Last check: ", TimeToString(TimeCurrent(), TIME_DATE|TIME_SECONDS));
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
   
   // Check if this sync was triggered from web
   bool webTriggered = syncRequested;
   
   if(webTriggered)
   {
      Print("TradingJournalBridge: =========================================");
      Print("TradingJournalBridge: [SYNC] SYNC TRIGGERED FROM WEB");
      Print("TradingJournalBridge: =========================================");
      Print("TradingJournalBridge: Account ID: ", ACCOUNT_ID);
      Print("TradingJournalBridge: Sync Counter: #", syncCounter);
      Print("TradingJournalBridge: Time: ", TimeToString(TimeCurrent(), TIME_DATE|TIME_SECONDS));
      Print("TradingJournalBridge: =========================================");
   }
   
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
   // IMPORTANT: Only continue history sync if sync was requested from web
   bool skipHashCheck = false;
   if(SYNC_HISTORY && isSyncingHistory && historyOffset < totalEligibleHistory && totalEligibleHistory > 0 && (syncRequested || webTriggered))
   {
      // Still syncing history batches - don't skip sync even if hash matches
      // But ONLY if sync was requested from web
      skipHashCheck = true;
      Print("TradingJournalBridge: History sync in progress (offset: ", historyOffset, " / ", totalEligibleHistory, ") - will sync regardless of hash (web-triggered)");
   }
   else if(SYNC_HISTORY && isSyncingHistory && !syncRequested && !webTriggered)
   {
      // History sync flag is set but no sync request from web - stop to prevent infinite loop
            Print("TradingJournalBridge: [WARNING] History sync flag is set but no sync request from web");
      Print("TradingJournalBridge: Stopping history sync to prevent infinite loop");
      isSyncingHistory = false;
      historyOffset = 0; // Reset offset
      string gvKey = "TJ_HistoryOffset_" + IntegerToString(ACCOUNT_ID);
      GlobalVariableDel(gvKey);
      string flagKey = "TJ_IsSyncingHistory_" + IntegerToString(ACCOUNT_ID);
      GlobalVariableDel(flagKey);
      Print("TradingJournalBridge: History sync stopped and offset reset");
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
      Print("TradingJournalBridge: [ERROR] Failed to build payload");
      if(webTriggered)
      {
         Print("TradingJournalBridge: [WARNING] Web-triggered sync failed - payload build error");
      }
      return;
   }
   
   // Count trades in payload for logging
   int openTradesCount = 0;
   int historyTradesCount = 0;
   int tradesStartPos = StringFind(json, "\"trades\":[");
   if(tradesStartPos >= 0)
   {
      int tradesEndPos = StringFind(json, "],", tradesStartPos);
      if(tradesEndPos > tradesStartPos)
      {
         string tradesStr = StringSubstr(json, tradesStartPos, tradesEndPos - tradesStartPos);
         // Count open trades (no close_time)
         int openCount = 0;
         int pos = 0;
         while((pos = StringFind(tradesStr, "\"close_time\":null", pos)) >= 0)
         {
            openCount++;
            pos += 1;
         }
         openTradesCount = openCount;
         
         // Count history trades (has close_time)
         int historyCount = 0;
         pos = 0;
         while((pos = StringFind(tradesStr, "\"close_time\":\"", pos)) >= 0)
         {
            historyCount++;
            pos += 1;
         }
         historyTradesCount = historyCount;
      }
   }
   
   // Extract balance info for logging
   double balance = AccountBalance();
   double equity = AccountEquity();
   double margin = AccountMargin();
   double freeMargin = AccountFreeMargin();
   
   // Log sync information
   if(webTriggered)
   {
      Print("TradingJournalBridge: =========================================");
      Print("TradingJournalBridge: [SEND] SENDING DATA TO WEB");
      Print("TradingJournalBridge: =========================================");
      Print("TradingJournalBridge: Account Balance: ", DoubleToString(balance, 2));
      Print("TradingJournalBridge: Account Equity: ", DoubleToString(equity, 2));
      Print("TradingJournalBridge: Account Margin: ", DoubleToString(margin, 2));
      Print("TradingJournalBridge: Free Margin: ", DoubleToString(freeMargin, 2));
      Print("TradingJournalBridge: Open Trades: ", openTradesCount);
      Print("TradingJournalBridge: History Trades: ", historyTradesCount);
      Print("TradingJournalBridge: Total Trades: ", (openTradesCount + historyTradesCount));
      Print("TradingJournalBridge: Payload Size: ", StringLen(json), " bytes (", DoubleToString(StringLen(json) / 1024.0, 2), " KB)");
      Print("TradingJournalBridge: =========================================");
   }
   else
   {
      Print("TradingJournalBridge: Sync #", syncCounter, " - Sending data: ", openTradesCount, " open, ", historyTradesCount, " history trades");
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
      // Parse response to get sync results
      int newTradesPos = StringFind(response, "\"new_trades\":");
      int updatedTradesPos = StringFind(response, "\"updated_trades\":");
      int newTrades = 0;
      int updatedTrades = 0;
      
      if(newTradesPos >= 0)
      {
         int newTradesStart = StringFind(response, ":", newTradesPos) + 1;
         int newTradesEnd = StringFind(response, ",", newTradesStart);
         if(newTradesEnd < 0) newTradesEnd = StringFind(response, "}", newTradesStart);
         string newTradesStr = StringSubstr(response, newTradesStart, newTradesEnd - newTradesStart);
         StringReplace(newTradesStr, " ", "");
         newTrades = (int)StringToInteger(newTradesStr);
      }
      
      if(updatedTradesPos >= 0)
      {
         int updatedTradesStart = StringFind(response, ":", updatedTradesPos) + 1;
         int updatedTradesEnd = StringFind(response, ",", updatedTradesStart);
         if(updatedTradesEnd < 0) updatedTradesEnd = StringFind(response, "}", updatedTradesStart);
         string updatedTradesStr = StringSubstr(response, updatedTradesStart, updatedTradesEnd - updatedTradesStart);
         StringReplace(updatedTradesStr, " ", "");
         updatedTrades = (int)StringToInteger(updatedTradesStr);
      }
      
      if(webTriggered)
      {
         Print("TradingJournalBridge: =========================================");
         Print("TradingJournalBridge: [SUCCESS] SYNC COMPLETED SUCCESSFULLY");
         Print("TradingJournalBridge: =========================================");
         Print("TradingJournalBridge: New Trades: ", newTrades);
         Print("TradingJournalBridge: Updated Trades: ", updatedTrades);
         Print("TradingJournalBridge: Total Processed: ", (newTrades + updatedTrades));
         Print("TradingJournalBridge: Response: ", StringSubstr(response, 0, 300));
         Print("TradingJournalBridge: =========================================");
      }
      else
      {
         Print("TradingJournalBridge: Sync #", syncCounter, " completed. New: ", newTrades, ", Updated: ", updatedTrades, ". Response: ", StringSubstr(response, 0, 200));
      }
      
      // Check if sync was requested from web
      // IMPORTANT: Don't clear syncRequested here - keep it true throughout all history batch syncs
      // Only clear it when ALL history batches are complete (see below)
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
            // Keep syncRequested = true throughout all history batch syncs
            // Only clear it when ALL history batches are complete (see below)
            Print("TradingJournalBridge: [SUCCESS] Sync was requested from web - keeping flag true for history batch syncs");
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
            Print("TradingJournalBridge: [SUCCESS] All history trades synced! (", totalEligibleHistory, " total)");
            Print("TradingJournalBridge: Resetting offset to 0 for next cycle");
            historyOffset = 0;
            isSyncingHistory = false; // Switch to normal sync interval - STOP history sync
            // Clear syncRequested flag now that history sync is complete
            if(SYNC_ON_DEMAND)
            {
               syncRequested = false;
               Print("TradingJournalBridge: [SUCCESS] History sync complete - cleared syncRequested flag");
               Print("TradingJournalBridge: History sync stopped. Will wait for next web trigger.");
            }
            Print("TradingJournalBridge: Switching to normal sync interval (", SYNC_INTERVAL_SECONDS, "s)");
         }
         else if(historyOffset < totalEligibleHistory && totalEligibleHistory > 0)
         {
            // Still have more history to sync, but ONLY continue if sync was requested from web
            // IMPORTANT: Use syncRequested (global) not webTriggered (local) - syncRequested stays true throughout all batches
            if(syncRequested)
            {
               Print("TradingJournalBridge: History sync in progress. Offset: ", historyOffset, " / ", totalEligibleHistory);
               isSyncingHistory = true; // Continue fast sync ONLY if web-triggered
               Print("TradingJournalBridge: Next batch will sync in ", HISTORY_SYNC_INTERVAL_SECONDS, " seconds (web-triggered)");
               Print("TradingJournalBridge: IMPORTANT - Will skip hash check on next sync to continue history batches");
            }
            else
            {
               // No sync request from web - STOP history sync to prevent infinite loop
               Print("TradingJournalBridge: [WARNING] History sync in progress but no sync request from web");
               Print("TradingJournalBridge: Stopping history sync to prevent infinite loop");
               Print("TradingJournalBridge: Remaining trades: ", (totalEligibleHistory - historyOffset), " / ", totalEligibleHistory);
               isSyncingHistory = false;
               historyOffset = 0; // Reset offset
               syncRequested = false; // Clear flag since we're stopping
               Print("TradingJournalBridge: History sync stopped. Will wait for next web trigger.");
            }
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
      if(webTriggered)
      {
         Print("TradingJournalBridge: =========================================");
         Print("TradingJournalBridge: [ERROR] SYNC FAILED");
         Print("TradingJournalBridge: =========================================");
         Print("TradingJournalBridge: Web-triggered sync failed - no response from server");
         Print("TradingJournalBridge: Will retry on next sync cycle");
         Print("TradingJournalBridge: =========================================");
      }
      else
      {
         Print("TradingJournalBridge: Sync #", syncCounter, " failed - offset not updated, will retry same batch");
      }
      
      // If sync failed and we're in history sync mode, stop history sync to prevent infinite loop
      if(SYNC_HISTORY && isSyncingHistory && !syncRequested && !webTriggered)
      {
         Print("TradingJournalBridge: [WARNING] Sync failed and no sync request from web - stopping history sync");
         isSyncingHistory = false;
         // Don't reset offset on failure - keep it for retry when sync is requested again
      }
      
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
      // IMPORTANT: Only continue if sync was requested from web (syncRequested = true)
      // This prevents infinite looping when no sync is requested
      // Don't update historyOffset here - it will be updated after successful API call
      // Only set isSyncingHistory flag here - actual historyOffset will be updated after successful sync
      
      // Check if this sync was triggered from web
      // IMPORTANT: Use syncRequested (global) not webTriggered (local) - syncRequested stays true throughout all batches
      bool continueHistorySync = syncRequested;
      
      if(pendingHistoryOffset >= localTotalEligibleHistory && localTotalEligibleHistory > 0)
      {
         Print("TradingJournalBridge: All history trades will be synced after this batch! (", localTotalEligibleHistory, " total)");
         Print("TradingJournalBridge: After sync, will reset offset to 0 and switch to normal interval");
         // Will be set to false after successful sync
         isSyncingHistory = continueHistorySync; // Only true if web-triggered
      }
      else if(historyCount >= maxHistoryTrades && pendingHistoryOffset < localTotalEligibleHistory)
      {
         // Batch limit reached but still have more to sync
         Print("TradingJournalBridge: Batch limit reached. Next sync will continue from offset ", pendingHistoryOffset);
         Print("TradingJournalBridge: Remaining trades to sync: ", (localTotalEligibleHistory - pendingHistoryOffset));
         if(continueHistorySync)
         {
            Print("TradingJournalBridge: Will use fast sync interval (", HISTORY_SYNC_INTERVAL_SECONDS, "s) for next batch (web-triggered)");
            isSyncingHistory = true; // Continue fast sync ONLY if web-triggered
         }
         else
         {
            Print("TradingJournalBridge: [WARNING] No sync request from web - stopping history sync to prevent infinite loop");
            isSyncingHistory = false;
            historyOffset = 0; // Reset offset
         }
      }
      else if(historyCount > 0 && pendingHistoryOffset < localTotalEligibleHistory)
      {
         // Still have more trades to sync (even if batch not full)
         if(continueHistorySync)
         {
            Print("TradingJournalBridge: Still have more trades to sync (", (localTotalEligibleHistory - pendingHistoryOffset), " remaining). Setting isSyncingHistory = true (web-triggered)");
            isSyncingHistory = true;
         }
         else
         {
            Print("TradingJournalBridge: [WARNING] No sync request from web - stopping history sync to prevent infinite loop");
            Print("TradingJournalBridge: Remaining trades: ", (localTotalEligibleHistory - pendingHistoryOffset), " / ", localTotalEligibleHistory);
            isSyncingHistory = false;
            historyOffset = 0; // Reset offset
         }
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
            Print("TradingJournalBridge: [WARNING] No trades in this batch, but offset (", historyOffset, ") < total (", localTotalEligibleHistory, ")");
            Print("TradingJournalBridge: This may indicate history was changed. Will stop to prevent infinite loop.");
            isSyncingHistory = false; // Stop to prevent infinite loop
            historyOffset = 0; // Reset offset
         }
      }
      else
      {
         // Default: stop syncing if we can't determine status
         Print("TradingJournalBridge: [WARNING] Cannot determine sync status. Stopping to prevent infinite loop.");
         Print("TradingJournalBridge: historyCount: ", historyCount, ", historyOffset: ", historyOffset, ", pendingHistoryOffset: ", pendingHistoryOffset, ", localTotalEligibleHistory: ", localTotalEligibleHistory);
         isSyncingHistory = false;
         historyOffset = 0; // Reset offset
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
      Print("TradingJournalBridge: [ERROR] WebRequest error ", error);
      
      if(error == 4060)
      {
         Print("TradingJournalBridge: [WARNING] URL not allowed. Add ", API_URL, " to Tools > Options > Expert Advisors");
      }
      else if(error == 5200)
      {
         Print("TradingJournalBridge: [WARNING] Invalid URL or connection refused. Check ngrok is running.");
      }
      else if(error == 5203)
      {
         Print("TradingJournalBridge: [WARNING] Connection failed. Possible causes:");
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
      Print("TradingJournalBridge: [ERROR] HTTP error ", response);
      Print("TradingJournalBridge: Response: ", StringSubstr(responseText, 0, 200));
      
      if(response == 401)
      {
         Print("TradingJournalBridge: [WARNING] Unauthorized - Check API_TOKEN and ACCOUNT_ID");
      }
      else if(response == 403)
      {
         Print("TradingJournalBridge: [WARNING] Forbidden - Check account permissions");
      }
      else if(response == 422)
      {
         Print("TradingJournalBridge: [WARNING] Validation error - Check payload format");
      }
      
      return "";
   }
   
   Print("TradingJournalBridge: [SUCCESS] HTTP 200 OK - Data received successfully");
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
      Print("TradingJournalBridge: [CheckSyncRequest] Health check response received (", StringLen(responseText), " bytes)");
      Print("TradingJournalBridge: [CheckSyncRequest] Response preview: ", StringSubstr(responseText, 0, 300));
      
      // Check for sync_requested flag in response
      int syncRequestedPos = StringFind(responseText, "\"sync_requested\":");
      if(syncRequestedPos >= 0)
      {
         int syncRequestedStart = StringFind(responseText, ":", syncRequestedPos) + 1;
         int syncRequestedEnd = StringFind(responseText, ",", syncRequestedStart);
         if(syncRequestedEnd < 0) syncRequestedEnd = StringFind(responseText, "}", syncRequestedStart);
         string syncRequestedStr = StringSubstr(responseText, syncRequestedStart, syncRequestedEnd - syncRequestedStart);
         StringReplace(syncRequestedStr, " ", "");
         Print("TradingJournalBridge: [CheckSyncRequest] Found sync_requested field: ", syncRequestedStr);
         
         if(StringFind(syncRequestedStr, "true") >= 0)
         {
            Print("TradingJournalBridge: [SYNC REQUEST] ✅ Sync request detected from health check endpoint");
            Print("TradingJournalBridge: Web has requested a sync - EA will process on next check");
            return true;
         }
         else
         {
            Print("TradingJournalBridge: [CheckSyncRequest] sync_requested is false - no sync needed");
         }
      }
      else
      {
         Print("TradingJournalBridge: [CheckSyncRequest] sync_requested field not found in response");
      }
   }
   else
   {
      Print("TradingJournalBridge: [CheckSyncRequest] ❌ HTTP error: ", response);
      if(response == -1)
      {
         int error = GetLastError();
         Print("TradingJournalBridge: [CheckSyncRequest] WebRequest error: ", error);
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
            Print("TradingJournalBridge: [SYNC REQUEST] Sync requested from web detected in response");
            Print("TradingJournalBridge: EA will sync on next timer check");
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
               UpdateChartDisplay(); // Update chart display after intercept_all change
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
      UpdateChartDisplay(); // Update chart display after pause
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
      UpdateChartDisplay(); // Update chart display after resume
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
         
         // Extract magic buy and sell from schedule params if provided
         // IMPORTANT: If magic numbers are set (>0), automatically disable interceptAll
         int magicBuyPos = StringFind(cmdJson, "\"magic_buy\":", paramsPos);
         if(magicBuyPos >= 0)
         {
            int magicBuyStart = StringFind(cmdJson, ":", magicBuyPos) + 1;
            int magicBuyEnd = StringFind(cmdJson, ",", magicBuyStart);
            if(magicBuyEnd < 0) magicBuyEnd = StringFind(cmdJson, "}", magicBuyStart);
            string magicBuyStr = StringSubstr(cmdJson, magicBuyStart, magicBuyEnd - magicBuyStart);
            StringReplace(magicBuyStr, " ", "");
            int magicBuyNum = (int)StringToInteger(magicBuyStr);
            // Save magic buy to Global Variable even if 0 (0 means "all")
            string magicBuyGvKey = "TJ_TargetMagicBuy_" + IntegerToString(ACCOUNT_ID);
            GlobalVariableSet(magicBuyGvKey, (double)magicBuyNum);
            Print("TradingJournalBridge: Target magic BUY updated to ", magicBuyNum, (magicBuyNum == 0 ? " (ALL)" : ""));
            
            // If magic number is set (>0), disable interceptAll
            if(magicBuyNum > 0)
            {
               string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(interceptAllGvKey, 0.0);
               Print("TradingJournalBridge: Magic BUY set to ", magicBuyNum, " - interceptAll disabled");
            }
            
            UpdateChartDisplay(); // Update chart display after magic buy change
         }
         
         int magicSellPos = StringFind(cmdJson, "\"magic_sell\":", paramsPos);
         if(magicSellPos >= 0)
         {
            int magicSellStart = StringFind(cmdJson, ":", magicSellPos) + 1;
            int magicSellEnd = StringFind(cmdJson, ",", magicSellStart);
            if(magicSellEnd < 0) magicSellEnd = StringFind(cmdJson, "}", magicSellStart);
            string magicSellStr = StringSubstr(cmdJson, magicSellStart, magicSellEnd - magicSellStart);
            StringReplace(magicSellStr, " ", "");
            int magicSellNum = (int)StringToInteger(magicSellStr);
            // Save magic sell to Global Variable even if 0 (0 means "all")
            string magicSellGvKey = "TJ_TargetMagicSell_" + IntegerToString(ACCOUNT_ID);
            GlobalVariableSet(magicSellGvKey, (double)magicSellNum);
            Print("TradingJournalBridge: Target magic SELL updated to ", magicSellNum, (magicSellNum == 0 ? " (ALL)" : ""));
            
            // If magic number is set (>0), disable interceptAll
            if(magicSellNum > 0)
            {
               string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(interceptAllGvKey, 0.0);
               Print("TradingJournalBridge: Magic SELL set to ", magicSellNum, " - interceptAll disabled");
            }
            
            UpdateChartDisplay(); // Update chart display after magic sell change
         }
         
         SaveScheduleState();
         success = true;
         result = "Schedule updated";
         Print("TradingJournalBridge: Command ", cmdId, " executed - Schedule updated");
      }
   }
   else if(cmdType == "set_trading_mode")
   {
      // Extract trading_mode from params
      int paramsPos = StringFind(cmdJson, "\"params\":");
      if(paramsPos >= 0)
      {
         int tradingModePos = StringFind(cmdJson, "\"trading_mode\":", paramsPos);
         if(tradingModePos >= 0)
         {
            int tradingModeStart = StringFind(cmdJson, ":", tradingModePos) + 1;
            int tradingModeEnd = StringFind(cmdJson, ",", tradingModeStart);
            if(tradingModeEnd < 0) tradingModeEnd = StringFind(cmdJson, "}", tradingModeStart);
            string tradingModeStr = StringSubstr(cmdJson, tradingModeStart, tradingModeEnd - tradingModeStart);
            StringReplace(tradingModeStr, " ", "");
            int tradingModeNum = (int)StringToInteger(tradingModeStr);
            
            // Validate trading mode (0=Auto, 1=Buy Only, 2=Sell Only)
            if(tradingModeNum >= 0 && tradingModeNum <= 2)
            {
               // Save trading mode to Global Variable (can't change input parameter at runtime)
               string tradingModeGvKey = "TJ_TradingMode_" + IntegerToString(ACCOUNT_ID);
               GlobalVariableSet(tradingModeGvKey, (double)tradingModeNum);
               
               string modeName = "";
               if(tradingModeNum == 1) modeName = "Buy Only";
               else if(tradingModeNum == 2) modeName = "Sell Only";
               else modeName = "Auto (EMA200+ADX)";
               
               Print("TradingJournalBridge: Trading mode updated to ", tradingModeNum, " (", modeName, ")");
               UpdateChartDisplay(); // Update chart display after trading mode change
               success = true;
               result = "Trading mode set to " + modeName;
            }
            else
            {
               Print("TradingJournalBridge: Invalid trading mode value: ", tradingModeNum, " (must be 0, 1, or 2)");
               success = false;
               result = "Invalid trading mode (must be 0=Auto, 1=Buy Only, 2=Sell Only)";
            }
         }
         else
         {
            Print("TradingJournalBridge: trading_mode parameter not found in command");
            success = false;
            result = "trading_mode parameter not found";
         }
      }
      else
      {
         Print("TradingJournalBridge: params not found in command");
         success = false;
         result = "params not found";
      }
   }
   
   if(success)
   {
      UpdateCommandStatus(cmdId, "completed", result);
      UpdateChartDisplay(); // Update chart display after any command execution
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
   // IMPORTANT: Always use Global Variable if it exists (even if 0), as it represents the value set from form
   // Only fall back to input parameters if Global Variable doesn't exist
   int effectiveMagicBuy = TARGET_MAGIC_BUY;
   int effectiveMagicSell = TARGET_MAGIC_SELL;
   string magicBuyGvKey = "TJ_TargetMagicBuy_" + IntegerToString(ACCOUNT_ID);
   string magicSellGvKey = "TJ_TargetMagicSell_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(magicBuyGvKey))
   {
      // Always use Global Variable value if it exists (even if 0), as it represents the value from form
      effectiveMagicBuy = (int)GlobalVariableGet(magicBuyGvKey);
   }
   // If Global Variable doesn't exist, use input parameter (TARGET_MAGIC_BUY)
   
   if(GlobalVariableCheck(magicSellGvKey))
   {
      // Always use Global Variable value if it exists (even if 0), as it represents the value from form
      effectiveMagicSell = (int)GlobalVariableGet(magicSellGvKey);
   }
   // If Global Variable doesn't exist, use input parameter (TARGET_MAGIC_SELL)
   
   // Check if intercept all trading mode is enabled
   bool interceptAll = INTERCEPT_ALL_TRADING;
   string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(interceptAllGvKey))
   {
      interceptAll = (GlobalVariableGet(interceptAllGvKey) > 0.5);
   }
   
   // IMPORTANT: If magic numbers are set (>0), they take precedence over interceptAll
   // If magic numbers are set, we only intercept those specific magic numbers, not all trading
   if(effectiveMagicBuy > 0 || effectiveMagicSell > 0)
   {
      interceptAll = false; // Magic numbers take precedence
      Print("TradingJournalBridge: [Monitor] Magic numbers set (Buy: ", effectiveMagicBuy, ", Sell: ", effectiveMagicSell, ") - interceptAll disabled");
   }
   
   // Get Trading Mode (from Global Variable if set, otherwise from input)
   int tradingMode = TRADING_MODE;
   string tradingModeGvKey = "TJ_TradingMode_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(tradingModeGvKey))
   {
      tradingMode = (int)GlobalVariableGet(tradingModeGvKey);
   }
   
   // Get Market Regime for trading direction control
   string marketRegime = CalculateMarketRegime();
   bool blockSellOnBullish = (marketRegime == "BULLISH");
   bool blockBuyOnBearish = (marketRegime == "BEARISH");
   
   // Determine blocking based on trading mode
   bool shouldBlockBuy = false;
   bool shouldBlockSell = false;
   string tradingModeReason = "";
   
   if(tradingMode == 1) // Buy Only
   {
      shouldBlockSell = true;
      tradingModeReason = "Trading Mode: Buy Only";
   }
   else if(tradingMode == 2) // Sell Only
   {
      shouldBlockBuy = true;
      tradingModeReason = "Trading Mode: Sell Only";
   }
   else if(tradingMode == 0) // Auto (based on EMA200+ADX)
   {
      // Use market regime to determine blocking
      if(blockSellOnBullish)
      {
         shouldBlockSell = true;
         tradingModeReason = "Trading Mode: Auto (Market Regime BULLISH)";
      }
      else if(blockBuyOnBearish)
      {
         shouldBlockBuy = true;
         tradingModeReason = "Trading Mode: Auto (Market Regime BEARISH)";
      }
      else
      {
         tradingModeReason = "Trading Mode: Auto (Market Regime SIDEWAYS)";
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
   
   // Check all orders (including pending orders) and intercept if needed
   static datetime lastCheckTime = 0;
   // Check every 2 seconds for faster response
   if(TimeCurrent() - lastCheckTime >= 2)
   {
      lastCheckTime = TimeCurrent();
      
      // Check for all orders (open positions and pending orders)
      for(int i = OrdersTotal() - 1; i >= 0; i--)
      {
         if(OrderSelect(i, SELECT_BY_POS, MODE_TRADES))
         {
            int orderMagic = OrderMagicNumber();
            int orderType = OrderType();
            
            // Check if this order should be closed/deleted based on intercept rules
            bool shouldClose = false;
            string closeReason = "";
            
            // IMPORTANT: Check trading mode FIRST before intercept all or pause
            // This ensures Buy Only mode allows BUY orders, Sell Only mode allows SELL orders
            bool matchesMagic = false;
            if(effectiveMagicBuy == 0 && effectiveMagicSell == 0)
            {
               matchesMagic = true; // All magic numbers
            }
            else if((orderType == OP_BUY || orderType == OP_BUYLIMIT || orderType == OP_BUYSTOP) && effectiveMagicBuy > 0 && orderMagic == effectiveMagicBuy)
            {
               matchesMagic = true;
            }
            else if((orderType == OP_SELL || orderType == OP_SELLLIMIT || orderType == OP_SELLSTOP) && effectiveMagicSell > 0 && orderMagic == effectiveMagicSell)
            {
               matchesMagic = true;
            }
            else if((orderType == OP_BUY || orderType == OP_BUYLIMIT || orderType == OP_BUYSTOP) && effectiveMagicBuy == 0)
            {
               matchesMagic = true; // Magic = 0 means all
            }
            else if((orderType == OP_SELL || orderType == OP_SELLLIMIT || orderType == OP_SELLSTOP) && effectiveMagicSell == 0)
            {
               matchesMagic = true; // Magic = 0 means all
            }
            
            // Check trading mode FIRST - only block orders that should be blocked based on trading mode
            if(matchesMagic)
            {
               // Block based on trading mode (Buy Only, Sell Only, or Auto)
               // Buy Only mode: shouldBlockSell = true, shouldBlockBuy = false -> only block SELL orders
               // Sell Only mode: shouldBlockBuy = true, shouldBlockSell = false -> only block BUY orders
               // Auto mode: depends on market regime
               if(shouldBlockSell && (orderType == OP_SELL || orderType == OP_SELLLIMIT || orderType == OP_SELLSTOP))
               {
                  shouldClose = true;
                  closeReason = tradingModeReason;
                  Print("TradingJournalBridge: [Monitor] Blocking SELL order #", OrderTicket(), " - ", tradingModeReason);
               }
               else if(shouldBlockBuy && (orderType == OP_BUY || orderType == OP_BUYLIMIT || orderType == OP_BUYSTOP))
               {
                  shouldClose = true;
                  closeReason = tradingModeReason;
                  Print("TradingJournalBridge: [Monitor] Blocking BUY order #", OrderTicket(), " - ", tradingModeReason);
               }
               // If order matches magic but trading mode allows it, DON'T block
               // Example: Buy Only mode + BUY order = ALLOW (don't block)
               else
               {
                  Print("TradingJournalBridge: [Monitor] Order #", OrderTicket(), " (Type: ", orderType, ", Magic: ", orderMagic, ") ALLOWED by trading mode (", tradingModeReason, ")");
               }
            }
            
            // Check if intercept all is enabled (only if not already blocked by trading mode AND magic numbers are not set)
            // If magic numbers are set, we only intercept those specific magic numbers (handled above)
            if(!shouldClose && interceptAll && effectiveMagicBuy == 0 && effectiveMagicSell == 0)
            {
               shouldClose = true;
               closeReason = "Intercept ALL trading enabled";
               Print("TradingJournalBridge: [Monitor] Blocking order #", OrderTicket(), " - Intercept ALL enabled");
            }
            // If magic numbers are set but order doesn't match, don't intercept
            else if(!shouldClose && (effectiveMagicBuy > 0 || effectiveMagicSell > 0) && !matchesMagic)
            {
               // Order doesn't match magic numbers, don't intercept
               // This allows other EAs to trade freely
            }
            // Check if trading is blocked (paused or outside schedule) - only if not already blocked
            else if(!shouldClose && shouldBlockTrading)
            {
               // Check if this order matches magic buy or sell
               if(effectiveMagicBuy == 0 && effectiveMagicSell == 0)
               {
                  // No magic specified - close all trades
                  shouldClose = true;
                  closeReason = reason;
               }
               else
               {
                  // Check if order type matches magic buy or sell
                  if(orderType == OP_BUY && effectiveMagicBuy > 0 && orderMagic == effectiveMagicBuy)
                  {
                     shouldClose = true;
                     closeReason = reason;
                  }
                  else if(orderType == OP_SELL && effectiveMagicSell > 0 && orderMagic == effectiveMagicSell)
                  {
                     shouldClose = true;
                     closeReason = reason;
                  }
                  else if((orderType == OP_BUYLIMIT || orderType == OP_BUYSTOP) && effectiveMagicBuy > 0 && orderMagic == effectiveMagicBuy)
                  {
                     shouldClose = true;
                     closeReason = reason;
                  }
                  else if((orderType == OP_SELLLIMIT || orderType == OP_SELLSTOP) && effectiveMagicSell > 0 && orderMagic == effectiveMagicSell)
                  {
                     shouldClose = true;
                     closeReason = reason;
                  }
               }
            }
            
            if(shouldClose)
            {
               bool success = false;
               
               // Handle market orders (OP_BUY, OP_SELL)
               if(orderType == OP_BUY || orderType == OP_SELL)
               {
                  success = OrderClose(OrderTicket(), OrderLots(), OrderClosePrice(), 3);
               }
               // Handle pending orders (OP_BUYLIMIT, OP_BUYSTOP, OP_SELLLIMIT, OP_SELLSTOP)
               else if(orderType == OP_BUYLIMIT || orderType == OP_BUYSTOP || orderType == OP_SELLLIMIT || orderType == OP_SELLSTOP)
               {
                  success = OrderDelete(OrderTicket());
               }
               
               if(success)
               {
                  string orderTypeStr = "";
                  if(orderType == OP_BUY) orderTypeStr = "BUY";
                  else if(orderType == OP_SELL) orderTypeStr = "SELL";
                  else if(orderType == OP_BUYLIMIT) orderTypeStr = "BUY LIMIT";
                  else if(orderType == OP_BUYSTOP) orderTypeStr = "BUY STOP";
                  else if(orderType == OP_SELLLIMIT) orderTypeStr = "SELL LIMIT";
                  else if(orderType == OP_SELLSTOP) orderTypeStr = "SELL STOP";
                  
                  Print("TradingJournalBridge: Intercepted and closed/deleted order #", OrderTicket(), " (Type: ", orderTypeStr, ", Magic: ", orderMagic, ") - Reason: ", closeReason);
               }
               else
               {
                  Print("TradingJournalBridge: Failed to intercept order #", OrderTicket(), " - Error: ", GetLastError());
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
//| Get XAUUSD symbol (try XAUUSD, XAUUSDm, XAUUSDc, etc.)          |
//+------------------------------------------------------------------+
string GetXAUUSDSymbol()
{
   // Try common XAUUSD symbol variations
   string symbols[] = {"XAUUSD", "XAUUSDm", "XAUUSDc", "GOLD", "GOLDm", "GOLDc"};
   
   for(int i = 0; i < ArraySize(symbols); i++)
   {
      if(MarketInfo(symbols[i], MODE_BID) > 0)
      {
         Print("TradingJournalBridge: Using symbol: ", symbols[i], " for market regime analysis");
         return symbols[i];
      }
   }
   
   // If none found, try to use current chart symbol if it contains "XAU" or "GOLD"
   string chartSymbol = Symbol();
   if(StringFind(chartSymbol, "XAU") >= 0 || StringFind(chartSymbol, "GOLD") >= 0 || StringFind(chartSymbol, "xau") >= 0 || StringFind(chartSymbol, "gold") >= 0)
   {
      Print("TradingJournalBridge: Using chart symbol: ", chartSymbol, " for market regime analysis");
      return chartSymbol;
   }
   
   // Default fallback
   Print("TradingJournalBridge: WARNING - XAUUSD symbol not found, using default: XAUUSD");
   return "XAUUSD";
}

//+------------------------------------------------------------------+
//| Calculate EMA200 for XAUUSD H1                                    |
//+------------------------------------------------------------------+
double CalculateEMA200()
{
   string symbol = GetXAUUSDSymbol();
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
//| shift: 0 = current candle, 1 = closed candle (no repaint)       |
//+------------------------------------------------------------------+
double CalculateADX14(int shift = 1)
{
   string symbol = GetXAUUSDSymbol();
   int timeframe = PERIOD_H1;
   int period = 14;
   
   // Use iADX (Average Directional Movement Index) indicator
   // MODE_MAIN returns the ADX line value
   // Shift = 1 means use closed candle (no repaint), 0 = current candle
   double adx = iADX(symbol, timeframe, period, PRICE_CLOSE, MODE_MAIN, shift);
   
   if(adx == 0 || adx == EMPTY_VALUE)
   {
      Print("TradingJournalBridge: ADX(14) calculation failed or insufficient data");
      return 0;
   }
   
   return adx;
}

//+------------------------------------------------------------------+
//| Get Current ADX Value (for display)                              |
//+------------------------------------------------------------------+
double GetCurrentADX()
{
   return CalculateADX14(0); // Use current candle for display
}

//+------------------------------------------------------------------+
//| Calculate Market Regime based on EMA200 and ADX(14)              |
//| Returns: "SIDEWAYS", "BULLISH", or "BEARISH"                     |
//+------------------------------------------------------------------+
string CalculateMarketRegime()
{
   string symbol = GetXAUUSDSymbol();
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
//| Update Chart Display with Important Information                  |
//| Displays: Magic Buy, Magic Sell, Market Regime                 |
//+------------------------------------------------------------------+
void UpdateChartDisplay()
{
   // Get effective magic numbers (from Global Variables if set, otherwise from input parameters)
   // IMPORTANT: Always use Global Variable if it exists (even if 0), as it represents the value set from form
   // Only fall back to input parameters if Global Variable doesn't exist
   int effectiveMagicBuy = TARGET_MAGIC_BUY;
   int effectiveMagicSell = TARGET_MAGIC_SELL;
   
   string magicBuyGvKey = "TJ_TargetMagicBuy_" + IntegerToString(ACCOUNT_ID);
   string magicSellGvKey = "TJ_TargetMagicSell_" + IntegerToString(ACCOUNT_ID);
   
   if(GlobalVariableCheck(magicBuyGvKey))
   {
      // Always use Global Variable value if it exists (even if 0), as it represents the value from form
      effectiveMagicBuy = (int)GlobalVariableGet(magicBuyGvKey);
      Print("TradingJournalBridge: [UpdateChartDisplay] Using Magic Buy from Global Variable: ", effectiveMagicBuy);
   }
   else
   {
      Print("TradingJournalBridge: [UpdateChartDisplay] No Global Variable for Magic Buy, using input parameter: ", TARGET_MAGIC_BUY);
   }
   
   if(GlobalVariableCheck(magicSellGvKey))
   {
      // Always use Global Variable value if it exists (even if 0), as it represents the value from form
      effectiveMagicSell = (int)GlobalVariableGet(magicSellGvKey);
      Print("TradingJournalBridge: [UpdateChartDisplay] Using Magic Sell from Global Variable: ", effectiveMagicSell);
   }
   else
   {
      Print("TradingJournalBridge: [UpdateChartDisplay] No Global Variable for Magic Sell, using input parameter: ", TARGET_MAGIC_SELL);
   }
   
   // Debug: Print final values being used
   Print("TradingJournalBridge: [UpdateChartDisplay] Final effectiveMagicBuy: ", effectiveMagicBuy, ", effectiveMagicSell: ", effectiveMagicSell);
   
   // Check intercept all trading flag
   bool interceptAll = INTERCEPT_ALL_TRADING;
   string interceptAllGvKey = "TJ_InterceptAll_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(interceptAllGvKey))
   {
      interceptAll = (GlobalVariableGet(interceptAllGvKey) > 0.5);
   }
   
   // Display magic numbers - if magic numbers are set (>0), always show them
   // If interceptAll is true AND magic numbers are 0, show "ALL"
   // If magic numbers are set (>0), they take precedence and interceptAll should be false
   string magicBuyDisplay = "";
   string magicSellDisplay = "";
   
   // If magic numbers are set (>0), always display them (they take precedence over interceptAll)
   if(effectiveMagicBuy > 0)
   {
      magicBuyDisplay = IntegerToString(effectiveMagicBuy);
   }
   else if(interceptAll)
   {
      magicBuyDisplay = "ALL";
   }
   else
   {
      magicBuyDisplay = "ALL"; // Magic = 0 means all
   }
   
   if(effectiveMagicSell > 0)
   {
      magicSellDisplay = IntegerToString(effectiveMagicSell);
   }
   else if(interceptAll)
   {
      magicSellDisplay = "ALL";
   }
   else
   {
      magicSellDisplay = "ALL"; // Magic = 0 means all
   }
   
   // Count open positions that match intercept criteria
   int interceptedBuyCount = 0;
   int interceptedSellCount = 0;
   int totalOpenTrades = 0;
   
   for(int i = OrdersTotal() - 1; i >= 0; i--)
   {
      if(OrderSelect(i, SELECT_BY_POS, MODE_TRADES))
      {
         if(OrderType() == OP_BUY || OrderType() == OP_SELL)
         {
            totalOpenTrades++;
            
            bool shouldIntercept = false;
            if(interceptAll)
            {
               shouldIntercept = true;
            }
            else
            {
               if(OrderType() == OP_BUY && (effectiveMagicBuy == 0 || OrderMagicNumber() == effectiveMagicBuy))
                  shouldIntercept = true;
               else if(OrderType() == OP_SELL && (effectiveMagicSell == 0 || OrderMagicNumber() == effectiveMagicSell))
                  shouldIntercept = true;
            }
            
            if(shouldIntercept)
            {
               if(OrderType() == OP_BUY)
                  interceptedBuyCount++;
               else
                  interceptedSellCount++;
            }
         }
      }
   }
   
   // Get Trading Mode (from Global Variable if set, otherwise from input)
   int tradingMode = TRADING_MODE;
   string tradingModeGvKey = "TJ_TradingMode_" + IntegerToString(ACCOUNT_ID);
   if(GlobalVariableCheck(tradingModeGvKey))
   {
      tradingMode = (int)GlobalVariableGet(tradingModeGvKey);
   }
   
   // Get Trading Mode Display String
   string tradingModeDisplay = "";
   if(tradingMode == 1)
   {
      tradingModeDisplay = "Buy Only";
   }
   else if(tradingMode == 2)
   {
      tradingModeDisplay = "Sell Only";
   }
   else
   {
      tradingModeDisplay = "Auto";
   }
   
   // Get Market Regime
   string marketRegime = CalculateMarketRegime();
   string xauSymbol = GetXAUUSDSymbol();
   
   // Get Current ADX Value
   double currentADX = GetCurrentADX();
   string adxDisplay = "N/A";
   if(currentADX > 0 && currentADX != EMPTY_VALUE)
   {
      adxDisplay = DoubleToString(currentADX, 2);
   }
   
   // Check if trading is paused
   string interceptStatus = "";
   if(isPaused)
   {
      interceptStatus = "PAUSED - All trading blocked";
   }
   else if(interceptAll)
   {
      interceptStatus = "ACTIVE - Intercepting ALL trades";
   }
   else if(effectiveMagicBuy > 0 || effectiveMagicSell > 0)
   {
      interceptStatus = "ACTIVE - Intercepting Magic Buy:" + IntegerToString(effectiveMagicBuy) + " / Sell:" + IntegerToString(effectiveMagicSell);
   }
   else
   {
      interceptStatus = "ACTIVE - Intercepting ALL trades (Magic = 0)";
   }
   
   // Build display string
   string displayText = "\n";
   displayText += "=========================================\n";
   displayText += "  Trading Journal Bridge EA\n";
   displayText += "=========================================\n";
   displayText += "\n";
   displayText += "Magic Buy  : " + magicBuyDisplay + "\n";
   displayText += "Magic Sell : " + magicSellDisplay + "\n";
   displayText += "\n";
   displayText += "Trading Mode:\n";
   displayText += "  " + tradingModeDisplay + "\n";
   displayText += "\n";
   displayText += "Intercept Status:\n";
   displayText += "  " + interceptStatus + "\n";
   displayText += "\n";
   displayText += "Open Positions:\n";
   displayText += "  Total: " + IntegerToString(totalOpenTrades) + "\n";
   displayText += "  Intercepted Buy: " + IntegerToString(interceptedBuyCount) + "\n";
   displayText += "  Intercepted Sell: " + IntegerToString(interceptedSellCount) + "\n";
   displayText += "\n";
   displayText += "Market Regime (" + xauSymbol + " H1):\n";
   displayText += "  EMA200 + ADX(14) Analysis\n";
   displayText += "  Status: " + marketRegime + "\n";
   displayText += "  ADX Current: " + adxDisplay + "\n";
   displayText += "\n";
   displayText += "=========================================\n";
   
   // Display on chart
   Comment(displayText);
}
//+------------------------------------------------------------------+

