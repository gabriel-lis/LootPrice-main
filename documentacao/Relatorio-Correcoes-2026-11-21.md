# LootPrice — Relatório de correções

**Data:** 21/11/2026 · **Branch:** `revisao-correcoes` (criada a partir de `envio-email`) · **Origem:** `Relatorio-Revisao-LootPrice.pdf`

Nada foi commitado: as mudanças estão no disco, na branch, para você revisar e commitar quando quiser.

## Como foi verificado (e o que não foi)

- `php -l` em todos os PHP alterados: sem erros.
- Novo `tests/executar.php`: **37 testes passando** (funções puras + consultas só de leitura).
- Teste real via HTTP (visitante e admin logado): home, catálogo (`?q[]=x`, `?q=%`, página fora do intervalo), jogo, descontos, login, perfil e as 7 telas do admin responderam 200 sem erro PHP. Também testei na prática: POST sem CSRF recusado, preço inválido e `javascript:` recusados, preço salvo duas vezes na mesma loja (atualiza, não duplica), admin tentando se rebaixar (bloqueado), avaliação com HTML (escapado), logout por GET (não desloga), bloqueio de login na 6ª tentativa.
- Backup do banco antes de mexer: `…\scratchpad\backup_antes_0145.sql` (fora do projeto). Os dados que usei nos testes (um preço e uma avaliação) foram revertidos.
- **Não testado:** visual no navegador (tema claro, celular), a coleta completa do robô (rodar de verdade enviaria e-mails reais de promoção e alteraria preços — testei as partes novas dentro de uma transação desfeita) e o envio de e-mail.

## O que ainda depende de você

1. **Rodar `robo\agendar_14h.ps1`** para o Windows pegar a tarefa nova (espera o MySQL, tenta 3×). Opcional: `robo\mysql_como_servico.ps1` como administrador, para o MySQL subir junto com o Windows.
2. **Contas antigas do seu banco local** (`admin@lootprice.com` / `gamer@lootprice.com`) continuam com as senhas `admin123` / `gamer123`: não mexi nas suas contas. Troque pelo perfil ou com `php robo\criar_admin.php --email=… --senha=…`.
3. **Em produção:** crie `config/db.local.php` com um usuário de banco próprio (modelo em `config/db.local.example.php`).
4. A documentação em PDF (RF05 "tempo real") não foi regenerada: o site agora mostra a data real da coleta, mas o texto do PDF precisa ser ajustado por você.

## Item a item (seguindo o relatório)

### Crítico / alto

| # | Problema | O que foi feito | Arquivos |
|---|---|---|---|
| 1 | Robô parado (MySQL desligado na hora) | Pela linha de comando o robô **espera o MySQL subir** (até 20 min). Agendador tenta de novo 3× (a cada 10 min). Script para instalar o MySQL como serviço. `jogo.php` agora mostra "Última coleta: data" por loja e **avisa quando o preço tem mais de 3 dias** (substitui "Atualizado automaticamente"). Dashboard do admin alerta coleta falha/velha. O horário de 20h em vez de 14h é o `StartWhenAvailable` (PC ligado tarde): continua, mas agora a coleta funciona quando roda. | `includes/coletor.php`, `robo/agendar_14h.ps1`, `robo/mysql_como_servico.ps1`, `jogo.php`, `admin/index.php` |
| 2 | Sem CSRF; cookie; fixação de sessão; sem limite de login | CSRF em **todos** os formulários (login, cadastro, desejos, avaliações, admin inteiro) e **logout só por POST**. Cookie `HttpOnly`+`SameSite` e `session_regenerate_id` já existiam; mantidos. **Limite de tentativas**: 5 erros por conta e 30 por IP em 15 min bloqueiam por 15 min (tabela `tentativas_login`). | `includes/auth.php`, `login.php`, `logout.php`, `desejos.php`, `avaliar.php`, `includes/admin.php`, `includes/header.php`, `perfil.php` |
| 3 | Papel de admin "congelado"; admin se rebaixa | `refreshSessionUser()`: a cada requisição o papel, nome, fotos e e-mail confirmado vêm do banco; conta excluída ou **senha trocada em outro lugar derruba a sessão** (resolve também "trocar a senha não derruba outras sessões"). O servidor impede o admin de se rebaixar/excluir e de **ficar sem nenhum administrador**. | `includes/auth.php`, `admin/usuarios.php` |
| 4 | Credenciais e banco inseguros | `schema.sql` **sem DROP** (reimportar é seguro), sem usuários com senha conhecida; `database/reset_dev.sql` para recomeçar do zero (só dev); `robo/criar_admin.php` cria o admin. Credenciais do banco vêm de `config/db.local.php` (fora do git). Pastas `config/`, `robo/`, `database/`, `tests/` bloqueadas por `.htaccess` (antes `robo/logs/*.json` e `schema.sql` abriam no navegador). | `database/schema.sql`, `database/reset_dev.sql`, `robo/criar_admin.php`, `config/db.php`, `config/db.local.example.php`, `*/.htaccess` |
| 5 | Dados falsos como se fossem reais | **Todo mock removido** (jogos, preços, categorias, plataformas, lojas, assinaturas). Se a consulta falhar, a página mostra vazio e o erro vai para `logs/erros.log` (nenhum `catch` engole erro em silêncio). | `includes/functions.php`, `includes/recomendacoes.php`, `config/db.php` |
| 6 | Regra do visitante inconsistente | Decisão: **manter a regra documentada** (visitante não vê preço). Agora também nos cards da home, catálogo e descontos o preço aparece borrado (valor falso) e o desconto some. Fácil de reverter em `includes/components.php` se preferir preço público. | `includes/components.php`, `css_index/cards.css` |
| 7 | "Atualizar preços" prende o painel | O botão **dispara o robô em segundo plano** e a página mostra barra de andamento e as últimas linhas do log (consulta a cada 3 s). Sessão livre, sem estourar o Timeout do Apache. | `admin/atualizar_precos.php`, `includes/coletor.php` |

### Médio

| Problema | O que foi feito |
|---|---|
| Admin não funciona no tema claro / cores fixas | Painel reescrito com `css_index/admin.css` (só variáveis do tema). Cores fixas trocadas por variáveis em `header.php`, `login.php`, `jogo.php`, `perfil.css`, `home.css`. **Parcial:** sobram cores fixas em regras antigas de `lootprice.css` (não auditei esse arquivo inteiro). |
| Variáveis `--input-*`, `--label-color`, `--title-color`, `--highlight` indefinidas | Movidas para `theme.css` (com apelidos declarados nos dois temas) e removidas de `cadastro.css`/`jogos.css`. Novas: `--warning`, `--info`, `--danger-*`, `--on-accent`, `--scrim`. |
| `admin/precos.php`: valor/link sem validação; "salvar" sempre inseria | Valor validado (aceita `59,90`, `1.299,90`; recusa texto e negativo), link só `http(s)` até 255 caracteres; **`UNIQUE(id_jogo,id_loja)`** e salvar na mesma loja **atualiza**. O robô continua com um preço por loja (agora garantido). |
| `jogo_form.php`: nota 0, nome >100, capa à mão | Nota 0 vira 0 (só vazio = sem nota), 0–100 validado; nome ≤100; data validada; **upload de capa** (JPG/PNG/WEBP/GIF ≤3 MB, nome aleatório); ids de categoria/plataforma conferidos. |
| `$pdo` nulo no admin / `$ultimosJogos` indefinida | `adminIniciar()` mostra "Banco indisponível" em vez de quebrar. |
| Arquivos órfãos | Excluir jogo apaga a capa (se ninguém mais usa); excluir usuário apaga as fotos; trocar/remover capa apaga a antiga. |
| `?q[]=x` dá erro 500 | Helpers `lp_get`/`lp_post`/`lp_senha` (só aceitam texto) em catálogo, header, login, perfil, verificação, recuperar senha, descadastrar. |
| Robô só no Windows | Certificado achado por `lp_cafile()` (php.ini, XAMPP ou `/etc/ssl`); PHP do agendador localizado; admin dispara com `php.exe` ou `nohup` conforme o SO. |
| Robô nunca remove preço | Remove preço de loja que não atualiza há 14 dias **se a loja respondeu bem nesta coleta** (Epic fica de fora: bloqueia o robô). |
| Capa que falha vira NULL/imagem quebrada | Capa padrão `fotos/jogos/sem-capa.svg` na tela; o robô tenta recuperar capas que faltam. |
| HTML das lojas muda sem aviso | Alerta `lojas_sem_dados` quando uma loja não devolve nenhum preço (no resumo da coleta e no dashboard). |
| `coletor.log` cresce sem limite | Passou de 1 MB → vira `.log.1`. Mesmo para `logs/erros.log`. |
| Desempenho: home chamava o catálogo 3–4× | Cache por requisição (`getGamesCatalog`, ofertas). |
| Header consultava categorias/plataformas sempre | Só em `catalogo.php` (`$precisaFiltros`). |
| `acessos_jogos` sem limpeza | Robô apaga acessos com mais de 180 dias. |
| `CREATE TABLE IF NOT EXISTS` a cada requisição | `lp_schema_uma_vez()`: roda uma vez e deixa marcador em `logs/schema_*.ok` (se recriar o banco, apague esses marcadores). |
| Filtro "Até R$ 100" incluía grátis e "Até R$ 50" não | Ambos incluem grátis. |
| Busca só pelo nome; `%` e `_` sem escape | Busca no nome, sinopse e categorias; `%`/`_` escapados. |
| "Em alta agora" com 1 acesso | Exige ≥3 acessos e ≥40% do jogo mais acessado. |
| `voltar_para` ficava na sessão | Limpo ao abrir o login sem vir de um jogo. |

### Baixo / limpeza

- **Código morto removido:** `index.html`, `sobre.html`, `sites/login/*.html`, `sites/jogos/*.html`, `sites/admin/`, `sites/jogos/js_jogos/`, `sites/login/js_login/`, `navbar.css`, `js_index/auth.js`, `js_index/pesquisa.js`, script do `categoryBtn` do footer. (Links antigos para essas páginas HTML deixam de funcionar.)
- **Repositório:** `.gitignore` ampliado e destrackeados do git (continuam no disco): `graphify-out/cache` (160 arquivos), fotos de perfil enviadas, `robo/logs`. `LootPrice-2/` já estava ignorado e não é rastreado (deixei a pasta onde estava). `README.md` real.
- **Seeds:** PlayStation, Switch, EA Play e PS Plus ganharam ícones próprios (`fotos/extras/*.svg`, no banco e no `schema.sql`); `visitante` removido do enum; usuários 1 e 2 receberam username (`admin`, `gamer`).
- **Links:** categoria em `jogo.php` aponta direto para `catalogo.php`.
- **Outros:** `perfil.css` só carrega em perfil e jogo; `meta description` em todas as páginas; modal de recorte do perfil agora prende o foco e devolve ao fechar; 37 testes automáticos.
- **Não feito:** fonte Inter ainda vem do Google Fonts (decisão sua: baixar e hospedar localmente).

### "O que falta fazer" do relatório

| Item | Situação |
|---|---|
| Recuperar senha e confirmação de e-mail | Já feito antes (commit anterior). |
| Alerta de promoção da lista de desejos | Já feito antes. |
| **Histórico de preços** | **Feito.** Tabela `historico_precos` (carga inicial com os preços de hoje); grava a cada mudança (robô e admin); `jogo.php` mostra o **menor preço já registrado** e um gráfico (para quem está logado; aparece com ≥2 dias de dados). |
| **Comentários e avaliações** | **Feito.** Nota 1–5 + comentário na página do jogo; uma por usuário (pode editar/excluir); admin modera em `admin/avaliacoes.php`. |
| **CRUD de lojas, categorias e assinaturas; upload de capa** | **Feito** (`admin/cadastros.php` inclui plataformas; upload no formulário do jogo). |
| **Paginação do catálogo** | **Feito** (24 por página, mantém os filtros). |
| RF05 "tempo real" na documentação | Site corrigido (mostra a data da coleta); o PDF fica com você. |

## Arquivos novos

`includes/admin.php`, `includes/migracoes.php`, `includes/avaliacoes.php`, `avaliar.php`, `admin/cadastros.php`, `admin/avaliacoes.php`, `css_index/admin.css`, `config/db.local.example.php`, `database/reset_dev.sql`, `robo/criar_admin.php`, `robo/mysql_como_servico.ps1`, `tests/executar.php`, `fotos/jogos/sem-capa.svg`, `fotos/extras/{playstation,switch,eaplay,psplus}.svg`, `.htaccess` em `config/ robo/ database/ tests/`.

## Mudanças no banco local (aplicadas automaticamente na primeira abertura do site)

`precos` ganhou `UNIQUE(id_jogo,id_loja)` (nenhum duplicado existia); criadas `historico_precos` (63 linhas de carga inicial), `avaliacoes` e `tentativas_login`; enum `tipo_usuario` sem `visitante`; usernames `admin`/`gamer`; ícones de plataformas/assinaturas.

## Documentação segundo as suas regras

- **Graphify:** atualizado (`graphify update .`): 511 nós, 1010 arestas, 55 comunidades.
- **CLAUDE.md:** tabela de estrutura atualizada e entrada nova no "Registro de alterações".
