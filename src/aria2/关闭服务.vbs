
Set ws = CreateObject("Wscript.Shell")
ws.run "cmd /c taskkill /f /im aria2c.exe >nul 2>&1", 0