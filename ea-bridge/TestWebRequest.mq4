//+------------------------------------------------------------------+
//|                                              TestWebRequest.mq4  |
//|                                                    Test WebRequest|
//+------------------------------------------------------------------+
#property copyright "Test"
#property version   "1.00"
#property strict

int OnInit()
{
   Print("Testing WebRequest to ngrok URL");
   
   string url = "https://ghislaine-boundless-billye.ngrok-free.dev/api/v1/incoming/health";
   string apiToken = "18106e28f5d0bc7cd39b1b3a23cfea2d052fc05c428e0fc218bafba65677a5fe";
   int accountId = 1;
   string headers = "Accept: application/json\r\n";
   headers += "X-API-Token: " + apiToken + "\r\n";
   headers += "X-Account-ID: " + IntegerToString(accountId) + "\r\n";
   
   char post[];
   char result[];
   string resultHeaders;
   
   int timeout = 5000;
   
   ResetLastError();
   
   int response = WebRequest(
      "GET",
      url,
      headers,
      timeout,
      post,
      result,
      resultHeaders
   );
   
   int error = GetLastError();
   
   if(response == -1)
   {
      Print("WebRequest FAILED!");
      Print("Error code: ", error);
      
      if(error == 4060)
         Print("URL not allowed. Add https://ghislaine-boundless-billye.ngrok-free.dev to Tools > Options > Expert Advisors");
      else if(error == 5200)
         Print("Invalid URL or connection refused");
      else if(error == 5203)
         Print("Connection failed - server might be down");
         
      Alert("WebRequest Error: ", error);
   }
   else
   {
      Print("WebRequest SUCCESS!");
      Print("HTTP Response Code: ", response);
      Print("Response: ", CharArrayToString(result));
      Alert("WebRequest OK! Response: ", response);
   }
   
   return(INIT_SUCCEEDED);
}

void OnDeinit(const int reason)
{
   Print("Test EA removed");
}

void OnTick()
{
   // Do nothing
}
//+------------------------------------------------------------------+

