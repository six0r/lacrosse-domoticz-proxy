# lacrosse-domoticz-proxy
A simple proxy to forward realtime weather data from a Lacrosse MobileAlerts gateway to a Domoticz server

# how to
- Create two dummy Temp+Hum sensors (interior + exterior) in your Domoticz instance
- Change the dom_ids at the top of the source code to match the created Domoticz device indexes.
- Run the proxy server from the command line or an init script
- Use the lacrosse gateway configuration tool to enable proxy usage and enter the right IP and port.
