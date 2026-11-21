# Agenda o robo de precos do LootPrice para rodar todos os dias as 14:00.
# Uso (PowerShell):  .\robo\agendar_14h.ps1
#
# - Se o PC estiver desligado as 14:00, a coleta roda assim que ele ligar (StartWhenAvailable).
# - O robo ESPERA o MySQL subir (ate 20 min) em vez de falhar: antes ele falhava com
#   "Sem conexao com o banco" porque o PC ligava e a tarefa rodava antes do XAMPP iniciar o banco.
# - Se a tarefa falhar, o Windows tenta de novo 3 vezes (a cada 10 min).
# - Para o banco subir junto com o Windows, rode (como administrador) .\robo\mysql_como_servico.ps1

# Acha o PHP: o do XAMPP ou o que estiver no PATH
$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path $php)) {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { $php = $cmd.Source } else { Write-Error 'PHP nao encontrado. Ajuste a variavel $php neste arquivo.'; exit 1 }
}
$script = Join-Path $PSScriptRoot 'coletar.php'
$nome   = 'LootPrice - Coleta de precos'

$acao      = New-ScheduledTaskAction -Execute $php -Argument "`"$script`"" -WorkingDirectory $PSScriptRoot
$gatilho   = New-ScheduledTaskTrigger -Daily -At 14:00
$config    = New-ScheduledTaskSettingsSet -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
             -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 10) -ExecutionTimeLimit (New-TimeSpan -Hours 1)

Register-ScheduledTask -TaskName $nome -Action $acao -Trigger $gatilho -Settings $config -Force | Out-Null
Write-Host "Tarefa '$nome' criada: todos os dias as 14:00 (php: $php)."
Get-ScheduledTask -TaskName $nome | Get-ScheduledTaskInfo | Select-Object NextRunTime
