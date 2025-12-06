//+------------------------------------------------------------------+
//|                    TradingJournalBridge-Local.mq4                |
//|              Alternative version using localhost (no ngrok)      |
//+------------------------------------------------------------------+
#property copyright "Trading Journal"
#property version   "1.01"
#property strict

//--- Input parameters
input string   API_URL = "http://127.0.0.1:8000/api/v1/incoming/trades";  // Local API URL (no ngrok)
input string   API_TOKEN = "2df47a53914c0dfb545a472ce813d74f4b2d07c788b1765ac3b90848c8fa2ac1";  // Generated token
input int      ACCOUNT_ID = 8;                                               // Account ID from Dashboard
input int      SYNC_INTERVAL_SECONDS = 60;                                   // Sync interval (seconds)
input bool     SYNC_HISTORY = true;                                          // Sync historical trades
input int      HISTORY_DAYS = 30;                                            // Days of history to sync

//--- Global variables
datetime lastSyncTime = 0;
int syncCounter = 0;

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
   Print("TradingJournalBridge: Using LOCAL URL (no ngrok): ", API_URL);
   Print("TradingJournalBridge: Sync interval: ", SYNC_INTERVAL_SECONDS, " seconds");
   
   // Perform initial sync
   SyncTrades();
   
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Expert deinitialization function                                   |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
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
   
   // Send to API
   string response = SendToAPI(json);
   
   if(response != "")
   {
      Print("TradingJournalBridge: Sync #", syncCounter, " completed. Response: ", StringSubstr(response, 0, 100));
   }
   else
   {
      Print("TradingJournalBridge: Sync #", syncCounter, " failed");
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
   
   // Open trades
   for(int i = 0; i < OrdersTotal(); i++)
   {
      if(OrderSelect(i, SELECT_BY_POS, MODE_TRADES))
      {
         if(!firstTrade) json += ",";
         json += BuildTradeJson(true);
         firstTrade = false;
      }
   }
   
   // Historical trades (if enabled)
   if(SYNC_HISTORY)
   {
      datetime startDate = TimeCurrent() - (HISTORY_DAYS * 24 * 60 * 60);
      
      for(int i = OrdersHistoryTotal() - 1; i >= 0; i--)
      {
         if(OrderSelect(i, SELECT_BY_POS, MODE_HISTORY))
         {
            // Only sync trades within date range
            if(OrderCloseTime() >= startDate)
            {
               if(!firstTrade) json += ",";
               json += BuildTradeJson(false);
               firstTrade = false;
            }
         }
      }
   }
   
   json += "]}";
   
   return json;
}

//+------------------------------------------------------------------+
//| Build JSON for a single trade                                      |
//+------------------------------------------------------------------+
string BuildTradeJson(bool isOpen)
{
   string json = "{";
   
   json += "\"ticket\":" + IntegerToString(OrderTicket()) + ",";
   json += "\"pair\":\"" + OrderSymbol() + "\",";
   json += "\"type\":\"" + GetOrderTypeString(OrderType()) + "\",";
   json += "\"open_time\":\"" + TimeToString(OrderOpenTime(), TIME_DATE|TIME_SECONDS) + "\",";
   
   if(!isOpen)
   {
      json += "\"close_time\":\"" + TimeToString(OrderCloseTime(), TIME_DATE|TIME_SECONDS) + "\",";
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
   json += "\"commission\":" + DoubleToString(OrderCommission(), 2) + ",";
   json += "\"stop_loss\":" + DoubleToString(OrderStopLoss(), (int)MarketInfo(OrderSymbol(), MODE_DIGITS)) + ",";
   json += "\"take_profit\":" + DoubleToString(OrderTakeProfit(), (int)MarketInfo(OrderSymbol(), MODE_DIGITS)) + ",";
   json += "\"comment\":\"" + EscapeJsonString(OrderComment()) + "\",";
   json += "\"magic_number\":" + IntegerToString(OrderMagicNumber());
   
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
   // Simple headers for localhost (no ngrok)
   string headers = "";
   headers += "Content-Type: application/json\r\n";
   headers += "Accept: application/json\r\n";
   headers += "X-API-Token: " + API_TOKEN + "\r\n";
   headers += "X-Account-ID: " + IntegerToString(ACCOUNT_ID) + "\r\n";
   
   Print("TradingJournalBridge: Sending to ", API_URL);
   Print("TradingJournalBridge: Account ID: ", ACCOUNT_ID);
   Print("TradingJournalBridge: Payload size: ", StringLen(jsonData), " bytes");
   
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
         Print("TradingJournalBridge: Invalid URL or connection refused. Check Laravel server is running on port 8000.");
      }
      else if(error == 5203)
      {
         Print("TradingJournalBridge: Connection failed. Possible causes:");
         Print("  1. Laravel server not running");
         Print("  2. URL not added to MT4 allowed URLs");
         Print("  3. Firewall blocking connection");
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

