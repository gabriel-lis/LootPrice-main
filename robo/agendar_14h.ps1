# Agenda o robô de preços do LootPrice para rodar todos os dias às 14:00.
# Uso (PowerShell):  .\robo\agendar_14h.ps1
# Se o PC estiver desligado às 14:00, a coleta roda assim que ele ligar (StartWhenAvailable).

$php    = 'C:\xampp\php\php.exe'
$script = Join-Path $PSScriptRoot 'coletar.php'
$nome   = 'LootPrice - Coleta de precos'

$acao      = New-ScheduledTaskAction -Execute $php -Argument "`"$script`"" -WorkingDirectory $PSScriptRoot
$gatilho   = New-ScheduledTaskTrigger -Daily -At 14:00
$config    = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 1)

Register-ScheduledTask -TaskName $nome -Action $acao -Trigger $gatilho -Settings $config -Force | Out-Null
Write-Host "Tarefa '$nome' criada: todos os dias as 14:00."
Get-ScheduledTask -TaskName $nome | Get-ScheduledTaskInfo | Select-Object NextRunTime
