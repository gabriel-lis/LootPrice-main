# LootPrice

Site de comparação de preços de jogos (PHP + MySQL), pensado para rodar no XAMPP.
Mostra o menor preço de cada jogo em Steam, Epic Games, Xbox, GOG e Nuuvem, com histórico de preços,
lista de desejos com aviso de promoção por e-mail.

## Como rodar (XAMPP)

1. Copie a pasta para `C:\xampp\htdocs\LootPrice-main` e ligue **Apache** e **MySQL** no painel do XAMPP
   (ou rode `robo\mysql_como_servico.ps1` como administrador para o MySQL subir junto com o Windows).
2. Importe o banco: phpMyAdmin → importar `database/schema.sql` (pode importar de novo sem medo:
   ele só cria o que falta e **nunca apaga** usuários nem jogos).
3. Crie o administrador (não existe usuário padrão):
   `C:\xampp\php\php.exe robo\criar_admin.php --email=voce@exemplo.com --nome="Seu Nome" --username=voce`
4. (Opcional) E-mail: copie `config/mail.example.php` para `config/mail.php` e preencha com a senha de app do Gmail.
5. (Opcional) Credenciais do banco diferentes de `root` sem senha: copie `config/db.local.example.php` para
   `config/db.local.php`. **Em produção nunca use `root` sem senha.**
6. Preços automáticos: `.\robo\agendar_14h.ps1` agenda a coleta diária às 14:00. Teste manual:
   `C:\xampp\php\php.exe robo\coletar.php`.

7. (Opcional) Plano B para Epic e Nuuvem, que limitam/bloqueiam robôs: copie `config/itad.example.php` para
   `config/itad.php` e cole a chave grátis da API da IsThereAnyDeal. Detalhes em
   `documentacao/Limitacoes-Coleta-Precos.md`.

Abra `http://localhost/LootPrice-main/`.

## Estrutura

Veja `CLAUDE.md` (mapa de pastas e arquivos, convenções e registro de alterações).

## Limitações da coleta de preços

Epic Games bloqueia robôs (Cloudflare) e a Nuuvem limita acessos (HTTP 429). O robô respeita esses limites
(espaça as buscas, obedece ao `Retry-After`, pausa a loja, mantém o último preço, avisa o admin) e não tenta
burlá-los. Veja `documentacao/Limitacoes-Coleta-Precos.md`.

## Segurança e erros

- Erros do banco e do robô vão para `logs/erros.log` e `robo/logs/` (nunca aparecem na tela).
- Apagar o banco do zero (só em desenvolvimento): `database/reset_dev.sql`, depois importar o `schema.sql`
  e apagar `logs/schema_*.ok`.
