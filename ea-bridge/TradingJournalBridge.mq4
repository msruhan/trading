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
input int      SYNC_INTERVAL_SECONDS = 60;                                   // Sync interval (seconds)
input bool     SYNC_HISTORY = true;                                           // Sync historical trades (ENABLED - limited to prevent large payload)
input int      HISTORY_DAYS = 7;                                              // Days of history to sync (start with 7 days)
input int      MAX_HISTORY_TRADES = 30;                                       // Max history trades per sync (ngrok free tier limit ~500KB)

//--- Global variables
datetime lastSyncTime = 0;
int syncCounter = 0;
int historyOffset = 0;  // Track how many history trades have been synced
int lastHistoryCount = 0;  // Track how many history trades were sent in last sync

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
   Print("TradingJournalBridge: Sync interval: ", SYNC_INTERVAL_SECONDS, " seconds");
   Print("TradingJournalBridge: Using NGROK - History sync: ", (SYNC_HISTORY ? "ENABLED" : "DISABLED"));
   if(SYNC_HISTORY)
   {
      Print("TradingJournalBridge: History settings - Days: ", HISTORY_DAYS, ", Max trades per batch: ", MAX_HISTORY_TRADES);
      Print("TradingJournalBridge: Incremental sync enabled - will sync in batches");
      
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
   }
   
   // Perform initial sync
   SyncTrades();
   
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Expert deinitialization function                                   |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   // Save history offset before EA stops
   if(SYNC_HISTORY)
   {
      string gvKey = "TJ_HistoryOffset_" + IntegerToString(ACCOUNT_ID);
      GlobalVariableSet(gvKey, historyOffset);
      Print("TradingJournalBridge: Saved history offset: ", historyOffset, " before deinitialization");
   }
   
   Print("TradingJournalBridge: Deinitialized. Total syncs: ", syncCounter);
}

//+------------------------------------------------------------------+
//| Expert tick function                                               |
//+------------------------------------------------------------------+
void OnTick()
{
   // Check if it's time to sync
   if(TimeCurrent() - lastSyncTime >= SYNC_INTERVAL_SECONDS)
   {
      SyncTrades();
   }
}

//+------------------------------------------------------------------+
//| Timer function (alternative to OnTick for less frequent updates)   |
//+------------------------------------------------------------------+
void OnTimer()
{
   SyncTrades();
}

//+------------------------------------------------------------------+
//| Main sync function                                                 |
//+------------------------------------------------------------------+
void SyncTrades()
{
   lastSyncTime = TimeCurrent();
   syncCounter++;
   
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
      
      // Save history offset to Global Variables after successful sync
      if(SYNC_HISTORY && lastHistoryCount > 0)
      {
         string gvKey = "TJ_HistoryOffset_" + IntegerToString(ACCOUNT_ID);
         GlobalVariableSet(gvKey, historyOffset);
         Print("TradingJournalBridge: Saved history offset: ", historyOffset);
      }
   }
   else
   {
      Print("TradingJournalBridge: Sync #", syncCounter, " failed - offset not updated, will retry same batch");
      // Don't update offset if sync failed - will retry same batch next time
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
   int totalEligibleHistory = 0;
   
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
               totalEligibleHistory++;
            }
         }
      }
      
      Print("TradingJournalBridge: Total history orders: ", totalHistory);
      Print("TradingJournalBridge: Eligible history trades (last ", HISTORY_DAYS, " days): ", totalEligibleHistory);
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
      Print("TradingJournalBridge: Progress: ", (historyOffset + historyCount), " / ", totalEligibleHistory, " trades synced");
      
      // Update offset for next sync (will be saved after successful API call)
      if(historyCount > 0)
      {
         historyOffset += historyCount;
      }
      
      // Check if we've completed syncing all history
      if(historyOffset >= totalEligibleHistory)
      {
         Print("TradingJournalBridge: All history trades synced! (", totalEligibleHistory, " total)");
         Print("TradingJournalBridge: Resetting offset to 0 for next cycle (will re-sync all, but backend uses updateOrCreate so no duplicates)");
         // Reset offset to 0 - next sync will start from beginning
         // This is safe because backend uses updateOrCreate based on ticket, so no duplicates will be created
         historyOffset = 0;
      }
      else if(historyCount >= maxHistoryTrades)
      {
         Print("TradingJournalBridge: Batch limit reached. Next sync will continue from offset ", historyOffset);
         Print("TradingJournalBridge: Remaining trades to sync: ", (totalEligibleHistory - historyOffset));
      }
      else if(historyCount == 0 && historyOffset > 0)
      {
         // No more trades to sync in this batch, but we haven't reached the end
         // This shouldn't happen, but if it does, reset offset
         Print("TradingJournalBridge: No trades in this batch, but offset > 0. Resetting offset.");
         historyOffset = 0;
      }
   }
   else
   {
      Print("TradingJournalBridge: History sync is DISABLED. Enable SYNC_HISTORY to sync closed trades.");
   }
   
   json += "]}";
   
   // Count total trades in JSON for logging
   int totalTradesInPayload = openTradesCount + historyCount;
   
   // Save historyCount to global variable so it can be accessed in SyncTrades()
   lastHistoryCount = historyCount;
   
   Print("TradingJournalBridge: Summary - Open: ", openTradesCount, ", History: ", historyCount, ", Total: ", totalTradesInPayload);
   
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

