# Instala o MySQL do XAMPP como servico do Windows, para ele iniciar junto com o PC.
# Assim o robo de precos (e o site) nao dependem de abrir o painel do XAMPP.
# Rode como ADMINISTRADOR:  .\robo\mysql_como_servico.ps1
# (Feche o MySQL pelo painel do XAMPP antes. Para desfazer: C:\xampp\mysql\bin\mysqld.exe --remove MySQL)

$mysqld = 'C:\xampp\mysql\bin\mysqld.exe'
$ini    = 'C:\xampp\mysql\bin\my.ini'
if (-not (Test-Path $mysqld)) { Write-Error "mysqld.exe nao encontrado em $mysqld"; exit 1 }

& $mysqld --install MySQL "--defaults-file=$ini"
Set-Service -Name MySQL -StartupType Automatic
Start-Service -Name MySQL
Get-Service MySQL
