//+------------------------------------------------------------------+
//|                                      TradingJournalBridge.mq5    |
//|                              Trading Journal Portfolio Dashboard  |
//|                                      https://tradingjournal.local |
//+------------------------------------------------------------------+
#property copyright "Trading Journal"
#property link      "https://tradingjournal.local"
#property version   "1.00"

//--- Input parameters
input string   API_URL = "https://your-domain.com/api/v1/incoming/trades";  // API Endpoint URL
input string   API_TOKEN = "";                                               // Your API Token
input int      ACCOUNT_ID = 0;                                               // Account ID from Dashboard
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
   Print("TradingJournalBridge: Sync interval: ", SYNC_INTERVAL_SECONDS, " seconds");
   
   // Set timer for periodic syncs
   EventSetTimer(SYNC_INTERVAL_SECONDS);
   
   // Perform initial sync
   SyncTrades();
   
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Expert deinitialization function                                   |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   EventKillTimer();
   Print("TradingJournalBridge: Deinitialized. Total syncs: ", syncCounter);
}

//+------------------------------------------------------------------+
//| Timer function                                                     |
//+------------------------------------------------------------------+
void OnTimer()
{
   SyncTrades();
}

//+------------------------------------------------------------------+
//| Expert tick function                                               |
//+------------------------------------------------------------------+
void OnTick()
{
   // Timer handles periodic syncs
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
      Print("TradingJournalBridge: Sync #", syncCounter, " completed");
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
   json += "\"balance\":" + DoubleToString(AccountInfoDouble(ACCOUNT_BALANCE), 2) + ",";
   json += "\"equity\":" + DoubleToString(AccountInfoDouble(ACCOUNT_EQUITY), 2) + ",";
   json += "\"margin\":" + DoubleToString(AccountInfoDouble(ACCOUNT_MARGIN), 2) + ",";
   json += "\"free_margin\":" + DoubleToString(AccountInfoDouble(ACCOUNT_MARGIN_FREE), 2) + ",";
   json += "\"margin_level\":" + DoubleToString(AccountInfoDouble(ACCOUNT_MARGIN_LEVEL), 2);
   json += "},";
   
   // Trades array
   json += "\"trades\":[";
   
   bool firstTrade = true;
   
   // Open positions
   int totalPositions = PositionsTotal();
   for(int i = 0; i < totalPositions; i++)
   {
      ulong ticket = PositionGetTicket(i);
      if(ticket > 0)
      {
         if(!firstTrade) json += ",";
         json += BuildPositionJson(ticket);
         firstTrade = false;
      }
   }
   
   // Historical deals (if enabled)
   if(SYNC_HISTORY)
   {
      datetime startDate = TimeCurrent() - (HISTORY_DAYS * 24 * 60 * 60);
      
      if(HistorySelect(startDate, TimeCurrent()))
      {
         int totalDeals = HistoryDealsTotal();
         for(int i = 0; i < totalDeals; i++)
         {
            ulong dealTicket = HistoryDealGetTicket(i);
            if(dealTicket > 0)
            {
               // Only include entry and out deals
               ENUM_DEAL_ENTRY entry = (ENUM_DEAL_ENTRY)HistoryDealGetInteger(dealTicket, DEAL_ENTRY);
               if(entry == DEAL_ENTRY_OUT)
               {
                  if(!firstTrade) json += ",";
                  json += BuildDealJson(dealTicket);
                  firstTrade = false;
               }
            }
         }
      }
   }
   
   json += "]}";
   
   return json;
}

//+------------------------------------------------------------------+
//| Build JSON for an open position                                    |
//+------------------------------------------------------------------+
string BuildPositionJson(ulong ticket)
{
   string json = "{";
   
   json += "\"ticket\":" + IntegerToString(ticket) + ",";
   json += "\"pair\":\"" + PositionGetString(POSITION_SYMBOL) + "\",";
   json += "\"type\":\"" + GetPositionTypeString((ENUM_POSITION_TYPE)PositionGetInteger(POSITION_TYPE)) + "\",";
   json += "\"open_time\":\"" + TimeToString((datetime)PositionGetInteger(POSITION_TIME), TIME_DATE|TIME_SECONDS) + "\",";
   json += "\"close_time\":null,";
   json += "\"open_price\":" + DoubleToString(PositionGetDouble(POSITION_PRICE_OPEN), (int)SymbolInfoInteger(PositionGetString(POSITION_SYMBOL), SYMBOL_DIGITS)) + ",";
   json += "\"close_price\":null,";
   json += "\"lots\":" + DoubleToString(PositionGetDouble(POSITION_VOLUME), 4) + ",";
   json += "\"profit\":" + DoubleToString(PositionGetDouble(POSITION_PROFIT), 2) + ",";
   json += "\"swap\":" + DoubleToString(PositionGetDouble(POSITION_SWAP), 2) + ",";
   json += "\"commission\":0,";
   json += "\"stop_loss\":" + DoubleToString(PositionGetDouble(POSITION_SL), (int)SymbolInfoInteger(PositionGetString(POSITION_SYMBOL), SYMBOL_DIGITS)) + ",";
   json += "\"take_profit\":" + DoubleToString(PositionGetDouble(POSITION_TP), (int)SymbolInfoInteger(PositionGetString(POSITION_SYMBOL), SYMBOL_DIGITS)) + ",";
   json += "\"comment\":\"" + EscapeJsonString(PositionGetString(POSITION_COMMENT)) + "\",";
   json += "\"magic_number\":" + IntegerToString(PositionGetInteger(POSITION_MAGIC));
   
   json += "}";
   
   return json;
}

//+------------------------------------------------------------------+
//| Build JSON for a historical deal                                   |
//+------------------------------------------------------------------+
string BuildDealJson(ulong ticket)
{
   string json = "{";
   
   string symbol = HistoryDealGetString(ticket, DEAL_SYMBOL);
   int digits = (int)SymbolInfoInteger(symbol, SYMBOL_DIGITS);
   
   json += "\"ticket\":" + IntegerToString(ticket) + ",";
   json += "\"pair\":\"" + symbol + "\",";
   json += "\"type\":\"" + GetDealTypeString((ENUM_DEAL_TYPE)HistoryDealGetInteger(ticket, DEAL_TYPE)) + "\",";
   json += "\"open_time\":\"" + TimeToString((datetime)HistoryDealGetInteger(ticket, DEAL_TIME), TIME_DATE|TIME_SECONDS) + "\",";
   json += "\"close_time\":\"" + TimeToString((datetime)HistoryDealGetInteger(ticket, DEAL_TIME), TIME_DATE|TIME_SECONDS) + "\",";
   json += "\"open_price\":" + DoubleToString(HistoryDealGetDouble(ticket, DEAL_PRICE), digits) + ",";
   json += "\"close_price\":" + DoubleToString(HistoryDealGetDouble(ticket, DEAL_PRICE), digits) + ",";
   json += "\"lots\":" + DoubleToString(HistoryDealGetDouble(ticket, DEAL_VOLUME), 4) + ",";
   json += "\"profit\":" + DoubleToString(HistoryDealGetDouble(ticket, DEAL_PROFIT), 2) + ",";
   json += "\"swap\":" + DoubleToString(HistoryDealGetDouble(ticket, DEAL_SWAP), 2) + ",";
   json += "\"commission\":" + DoubleToString(HistoryDealGetDouble(ticket, DEAL_COMMISSION), 2) + ",";
   json += "\"stop_loss\":0,";
   json += "\"take_profit\":0,";
   json += "\"comment\":\"" + EscapeJsonString(HistoryDealGetString(ticket, DEAL_COMMENT)) + "\",";
   json += "\"magic_number\":" + IntegerToString(HistoryDealGetInteger(ticket, DEAL_MAGIC));
   
   json += "}";
   
   return json;
}

//+------------------------------------------------------------------+
//| Convert position type to string                                    |
//+------------------------------------------------------------------+
string GetPositionTypeString(ENUM_POSITION_TYPE type)
{
   switch(type)
   {
      case POSITION_TYPE_BUY:  return "buy";
      case POSITION_TYPE_SELL: return "sell";
      default:                 return "unknown";
   }
}

//+------------------------------------------------------------------+
//| Convert deal type to string                                        |
//+------------------------------------------------------------------+
string GetDealTypeString(ENUM_DEAL_TYPE type)
{
   switch(type)
   {
      case DEAL_TYPE_BUY:  return "buy";
      case DEAL_TYPE_SELL: return "sell";
      default:             return "unknown";
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
   string headers = "";
   headers += "Content-Type: application/json\r\n";
   headers += "Accept: application/json\r\n";
   headers += "X-API-Token: " + API_TOKEN + "\r\n";
   headers += "X-Account-ID: " + IntegerToString(ACCOUNT_ID) + "\r\n";
   
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
      
      return "";
   }
   
   if(response != 200)
   {
      Print("TradingJournalBridge: HTTP error ", response);
      return "";
   }
   
   return CharArrayToString(result);
}
//+------------------------------------------------------------------+

