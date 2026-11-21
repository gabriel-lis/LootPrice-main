# LootPrice

Site de comparação de preços de jogos (PHP + MySQL, rodando no XAMPP em `c:\xampp\htdocs\LootPrice-main`).

## Regras de trabalho (obrigatórias)

### 1. Antes de responder ou alterar: consultar o Graphify

Sempre que o usuário fizer uma pergunta sobre o código, ou pedir qualquer alteração:

1. **Primeiro** consulte o grafo em `graphify-out/` (não comece lendo arquivos ou fazendo grep):
   - Leia `graphify-out/GRAPH_REPORT.md` (comunidades, nós centrais, conexões).
   - Use `graphify query "<pergunta>"`, `graphify path "<A>" "<B>"` ou `graphify explain "<nó>"` para localizar arquivos, funções e relações.
2. Só depois abra os arquivos indicados pelo grafo para ler ou editar o código.
3. Se `graphify-out/graph.json` não existir, rode `/graphify .` antes de continuar.

### 2. Depois de qualquer alteração no código: atualizar Graphify e este arquivo

Toda vez que eu criar, editar, mover ou apagar arquivos do projeto, **automaticamente, sem esperar o usuário pedir**:

1. **Atualizar o Graphify** com `/graphify . --update` (reextrai só o que mudou). Se foram apagados arquivos e o grafo encolher, usar `--force`.
2. **Atualizar este `CLAUDE.md`** se a mudança afetar algo descrito aqui (estrutura de pastas, arquivos, convenções, fluxos). Ajustar também o "Registro de alterações" abaixo com uma linha: data, o que mudou, arquivos principais.
3. Informar ao usuário, em uma linha, que o grafo e o `CLAUDE.md` foram atualizados.

Não pular esses passos mesmo em mudanças pequenas.

## Estrutura do projeto

| Pasta / arquivo | Conteúdo |
|---|---|
| `index.html`, `sobre.html` | Home (catálogo, filtro, busca, menu lateral) e página Sobre |
| `sites/jogos/*.html`, `sites/login/*.html`, `sites/admin/admin.html` | Apenas redirecionamentos (meta refresh) para as páginas PHP (`jogo.php?id=N`, `login.php`, `cadastro.php`, `admin/index.php`) |
| `admin/` | Painel administrativo em PHP (jogos, preços, usuários, atualizar preços) |
| `index.php`, `catalogo.php`, `descontos.php` | Home (Destaques e recomendações → Descontos → Jogos → botão "Catálogo completo"); catálogo com os filtros (busca, plataforma, categoria, preço); descontos por faixa (`?ate=10/50/100`) |
| `login.php`, `cadastro.php` | `login.php` = página única com abas Entrar/Criar conta (lógica de login e cadastro); `cadastro.php` só redireciona para `login.php?aba=cadastro` |
| `perfil.php`, `css_index/perfil.css`, `fotos/perfis/` | **Perfil do usuário** (só o próprio, sem busca/página pública): foto de perfil, imagem de fundo (capa), nome, username (único; avisa se já estiver em uso) e e-mail (nunca exibido na tela de perfil, só no formulário de edição; só trocar o e-mail exige a senha atual). Sem foto = círculo com a inicial do nome (`avatarHtml()` em `includes/auth.php`, usado no header). Upload validado (JPG/PNG/WEBP/GIF, 5 MB, nome aleatório); `fotos/perfis/.htaccess` bloqueia execução de scripts. Colunas `usuarios.foto_perfil`, `usuarios.foto_fundo`, `usuarios.data_nascimento`, `usuarios.telefone`. Edição em blocos (públicas, pessoais, alterar senha com hash) |
| `desejos.php`, `includes/desejos.php` | **Lista de desejos**: botão na página do jogo (`jogo.php`) adiciona/remove (POST em `desejos.php`, exige login); a lista aparece na aba "Lista de desejos" do perfil (`perfil.php?aba=desejos`), com cards e botão "Remover da lista". Tabela `lista_desejos` (criada sob demanda por `ensureDesejosTable`). Funções: `isInWishlist`, `setWishlist`, `getWishlistGames`, `countWishlist` |
| `config/db.php` | Conexão com o banco (`getDB()`) |
| `includes/` | `functions.php` (consultas), `recomendacoes.php` (acessos, popularidade, recomendações, descontos), `components.php` (cards `renderPosterCard`/`renderGameRowCard`), `auth.php` (login/sessão/admin), `header.php`, `footer.php` |
| `includes/coletor.php`, `robo/` | **Robô de preços** (PHP puro): `coletor.php` consulta Steam, GOG, Xbox, Nuuvem e Epic, grava em `precos`, lembra o jogo de cada loja em `jogo_loja_ref` e cadastra jogos populares (SteamSpy/Steam, até 8 por execução, máx. 100 no site). `robo/coletar.php` = entrada CLI; `robo/agendar_14h.ps1` = cria a tarefa do Windows "LootPrice - Coleta de precos" (todo dia 14:00); `robo/logs/` = `coletor.log` e `ultima_execucao.json`. Rodar manual: `C:\xampp\php\php.exe robo\coletar.php` ou botão em `admin/atualizar_precos.php`. A Epic bloqueia robôs (Cloudflare): o robô não contorna, mantém o último preço da Epic |
| `database/` | Scripts SQL |
| `js_index/` | JS do front (`auth.js`, `menulateral.js`, `price-promo.js`, `platform-icons.js`, `admin.js`, `cadastro.js`, `login.js`, `pesquisa.js`, `darkmode.js`, `navbar.js`) |
| `css_index/` | Estilos. **`theme.css` é a única fonte de cores/fonte** (modo escuro `:root`, claro `body.light-mode`); `cards.css` (catálogo), `home.css` (home/descontos/poster), `lootprice.css` (base/navbar/sidebar) |
| `fotos/` | Imagens (logos de lojas, capas de jogos, ícones de tema) |
| `ideias/` | Diagrama de casos de uso e rascunhos (`.docx`) |
| `documentacao/LootPrice2.pdf`, `documentacao/Lootprice2-1.pdf` | Documentação do projeto. `LootPrice2.pdf` = versão 1 (RF01–RF09, RNF01–RNF05); `Lootprice2-1.pdf` = versão 2.1 do módulo de modelagem de dados (introdução, objetivos, RN01–RN25, RF01–RF21, casos de uso, classes, MER/DER/modelo lógico, telas) |
| `graphify-out/` | Grafo de conhecimento (`graph.html`, `graph.json`, `GRAPH_REPORT.md`) |

Nós mais centrais no grafo: `getDB()`, `LootPrice Home`, `Catalogo de jogos`.

## Convenções

- Visual: cor de marca = roxo (`--accent`); verde só para desconto/Game Pass; amarelo/verde/vermelho só na nota Metacritic. Nunca escrever cores fixas nos CSS/PHP: usar as variáveis de `css_index/theme.css` (`--surface`, `--border`, `--text-muted`, `--accent`...). Fonte Inter.
- Idioma do projeto e das respostas: português (Brasil).
- Acesso ao banco sempre via `getDB()` de `config/db.php`; consultas reutilizáveis ficam em `includes/functions.php`.
- Verificação de login/admin via funções de `includes/auth.php` (`requireLogin()`, `requireAdmin()`).

## Registro de alterações

- 2026-11-20: regra para visitantes: na página do jogo, quem não está logado vê os preços borrados (valores falsos gerados no servidor, sem links de compra) com botão "Entrar" e link "Criar conta"; após entrar/cadastrar volta para o jogo (`login.php?voltar=jogo.php?id=N`, guardado em `$_SESSION['voltar_para']`, só aceita esse formato). Arquivos: `jogo.php`, `login.php`.

- 2026-11-20: lista de desejos do perfil mostra "Gratuito" (em vez de "A partir de Grátis") quando o menor preço é 0. Arquivo: `perfil.php`.

- 2026-11-20: capas da lista de desejos no perfil passaram de 16:9 (cortava muito) para 3:4 (72px de largura), próximo da proporção real das capas. Arquivo: `css_index/perfil.css`.

- 2026-11-20: box do perfil alargada (`.perfil-wrap` com `width:100%` e máx. 1040px; antes encolhia ao conteúdo, ~420px). Arquivo: `css_index/perfil.css`.

- 2026-11-20: lista de desejos do perfil agora é uma lista (linhas com capa pequena, nome, menor preço e botão "Remover") em vez de cards em grade. Arquivos: `perfil.php`, `css_index/perfil.css`.

- 2026-11-20: mensagens flash (login, perfil atualizado etc.) viraram um toast fixo no topo que some sozinho após 1,5 s, com barra de tempo diminuindo. Arquivo: `includes/header.php`.

- 2026-11-20: edição de perfil em blocos "Informações públicas" (nome de exibição, username, fotos) e "Informações pessoais" (sexo, data de nascimento, telefone, e-mail), todos editáveis e salvos no banco; novo bloco "Alterar senha" (senha atual + nova + confirmação; `password_hash`, mín. 6 caracteres; POST com `acao=senha`). Sem CPF. Novas colunas `usuarios.data_nascimento` e `usuarios.telefone` (aplicadas no banco local e em `database/schema.sql`). Arquivos: `perfil.php`, `css_index/perfil.css`, `database/schema.sql`.

- 2026-11-20: botão da lista de desejos na página do jogo movido para a direita da linha do título. Arquivo: `css_index/perfil.css`.
- 2026-11-20: lista de desejos: botão "Adicionar à lista de desejos" na página do jogo (deslogado leva ao login) e aba "Lista de desejos" no perfil com os jogos salvos e opção de remover. Nova tabela `lista_desejos`. Arquivos: `desejos.php`, `includes/desejos.php`, `includes/functions.php`, `jogo.php`, `perfil.php`, `css_index/perfil.css`, `database/schema.sql`.
- 2026-11-20: editor de recorte no perfil (arrastar + zoom; foto de perfil em quadro redondo, fundo em faixa 3,5:1; recorte feito no navegador e enviado já cortado) e correção do botão "Alterar fundo", que estava coberto pela faixa do avatar. Arquivos: `perfil.php`, `css_index/perfil.css`.
- 2026-11-20: removido o círculo roxo vazio ao lado do avatar no header (restos de `.user-greet::before` em `css_index/lootprice.css`).
- 2026-11-20: e-mail não aparece mais na tela do perfil (só no formulário de edição); aviso claro quando o username já está em uso. Arquivo: `perfil.php`.
- 2026-11-20: perfil maior (capa 280px, avatar 160px) e edição de e-mail com confirmação da senha atual. Arquivos: `perfil.php`, `css_index/perfil.css`.
- 2026-11-20: sistema de perfil de usuário: nova página `perfil.php` (abre só para visualizar; ícone "Editar perfil" libera a edição; foto de perfil, imagem de fundo, nome e username editáveis; avatar com inicial do nome quando não há foto), avatar clicável no header levando ao perfil, item "Meu perfil" no menu lateral. Novas colunas `usuarios.foto_perfil`/`foto_fundo` (já aplicadas no banco local e em `database/schema.sql`). Arquivos: `perfil.php`, `css_index/perfil.css`, `includes/auth.php` (`avatarHtml`), `includes/header.php`, `login.php`, `fotos/perfis/`.

- 2026-11-20: nova documentação `documentacao/Lootprice2-1.pdf` (24 páginas, escrita em primeira pessoa de estudante): introdução, objetivos, 25 regras de negócio, 21 requisitos funcionais, casos de uso, 2 diagramas de classes, MER, DER, modelo lógico e 11 prints das telas. Gerada fora do projeto (script em pasta temporária). Arquivo: `documentacao/Lootprice2-1.pdf`.
- 2026-11-16: footer da página do jogo (claro e escuro) agora encosta no fim da página e vai de ponta a ponta: removido o padding-bottom do `body` e o padding lateral é anulado no footer (sem vão). Arquivo: `sites/jogos/css_jogos/jogos.css`.
- 2026-11-16: auditoria dos links de compra (57 verificados, 0 com problema; Epic não verificável). Corrigido no robô: comparação de nomes agora só aceita título idêntico ou mesma edição (antes confundia Risk of Rain/2 e Rainbow Six Siege/Vegas); Nuuvem só aceita link `/item/` cuja lista de plataformas inclui Windows/PC (nunca PS/Xbox/Switch puro); nova opção `php robo\coletar.php --sem-novos` (só preços, sem cadastrar jogos). Dados de GOG/Nuuvem/Xbox recoletados. Arquivos: `includes/coletor.php`, `robo/coletar.php`.
- 2026-11-16: selo "⭐ Menor Preço" da página do jogo some quando 2+ lojas empatam no menor preço (inclui jogo grátis em várias lojas). Arquivo: `jogo.php`.
- 2026-11-16: robô de preços em PHP (substitui a simulação aleatória de `admin/atualizar_precos.php`). Lê preços reais em BRL de Steam (API da loja), GOG (catálogo), Xbox (DisplayCatalog), Nuuvem (HTML da busca); Epic bloqueada pelo Cloudflare (mantém último preço). Casa o jogo em cada loja pelo nome (`lp_melhor`, prefere título exato à edição), ignora preço R$ 0 quando a Steam cobra. Cadastra até 8 jogos populares novos por execução (capa baixada em `fotos/jogos/steam_ID.jpg`). Nova tabela `jogo_loja_ref`. Agendado todo dia às 14:00 (tarefa do Windows). Arquivos: `includes/coletor.php`, `robo/coletar.php`, `robo/agendar_14h.ps1`, `admin/atualizar_precos.php`, `database/schema.sql`.
- 2026-11-16: ícone do Metacritic trocado pelo "M" redondo (`fotos/extras/metacritic-icon.png`) na página do jogo. Arquivos: `jogo.php`, `fotos/extras/metacritic-icon.png`.
- 2026-11-16: logos da Nuuvem (nuvem) e GOG adicionados em `fotos/extras/` (`nuuvem.png`, `darknuuvem.png`, `gog.png`, `darkgog.png`); `database/schema.sql` aponta para eles. Arquivos: `fotos/extras/*`, `database/schema.sql`.
- 2026-11-16: modo claro da página do jogo corrigido: textos/caixas usam variáveis do tema, logos das lojas trocam para a variante escura (`dark*.png`, ou `invert` se não existir) conforme o tema, novo botão "Voltar ao catálogo" (pílula com seta SVG) e corrigido o padding-top do `body` em `jogos.css` que fazia o conteúdo passar por cima do header fixo; `--highlight` do claro agora roxo escuro (contraste). Arquivos: `jogo.php`, `sites/jogos/css_jogos/jogos.css`.
- 2026-11-16: removido o botão X do menu lateral (fecha clicando fora, no botão de menu ou com Esc). Arquivo: `includes/header.php`.
- 2026-11-16: menu lateral simplificado: removidos pesquisa e categorias; agora só Catálogo (`catalogo.php`), Descontos (`descontos.php`) e Sobre LootPrice (+ Painel Admin para admins), com destaque na página atual. Arquivo: `includes/header.php`.
- 2026-11-16: reformulação visual. Novo `css_index/theme.css` (paleta única escuro/claro, fonte Inter) substitui os dois conjuntos de variáveis antigos (`--hltb-*` e os de `lootprice.css`/`jogos.css`/`cadastro.css`). Cards redesenhados (capa limpa, nota ao lado do título, desconto em pílula verde, tag só quando informa algo), header mais leve com botão Entrar branco, títulos de seção com barra roxa, modo claro funcionando nas páginas novas. Arquivos: `css_index/theme.css`, `cards.css`, `home.css`, `lootprice.css`, `includes/header.php`, `includes/components.php`, `includes/recomendacoes.php`, `login.php`, `sites/login/css_login/cadastro.css`, `sites/jogos/css_jogos/jogos.css`.
- 2026-11-16: home reformulada (Destaques e recomendações, Descontos, Jogos, botão Catálogo completo). Recomendação por usuário: tabela `acessos_jogos` (visitas em `jogo.php` e buscas em `catalogo.php`, criada sob demanda) → jogos que dividem categorias com o que ele acessou (selo "Parecido com X"), mais "popular agora" (acessos de 30 dias) e nota Metacritic. Filtros saíram da home para `catalogo.php`; nova `descontos.php` (Todos / até R$ 10 / 50 / 100); busca e categorias do header apontam para `catalogo.php`; `index.php?q=` redireciona. CSS dos cards movido para `css_index/cards.css`; novo `css_index/home.css`. Arquivos: `index.php`, `catalogo.php`, `descontos.php`, `includes/recomendacoes.php`, `includes/components.php`, `includes/header.php`, `jogo.php`, `database/schema.sql`.
- 2026-11-16: footer voltou ao modelo simples (só o copyright, sem links); mantida a correção da rolagem lateral; `includes/header.php` passou a versionar `lootprice.css` e `responsive.css` com `?v=filemtime` (evita CSS antigo em cache no navegador).
- 2026-11-16: corrigida a rolagem lateral (footer com `width:100%` + padding sem `box-sizing`), removido o padding-top duplicado em `html`+`body` e o scroll vertical extra; footer redesenhado (marca, links Início/Sobre e copyright em uma linha). Arquivos: `css_index/lootprice.css`, `css_index/responsive.css`, `includes/footer.php`, `login.php`.
- 2026-11-16: novo header (botão de menu + logo à esquerda, busca em pílula com lupa ao centro, sem seletor "Tudo ▾", botão "Entrar" à direita). Login e cadastro unificados em `login.php` com abas "Entrar" / "Criar minha conta" (troca sem recarregar; `?aba=cadastro`); `cadastro.php` virou só redirecionamento. Arquivos: `includes/header.php`, `login.php`, `cadastro.php`.
- 2026-11-16: página de login com o mesmo visual do cadastro (página única, sem busca, sem rolagem; emoji removido dos títulos de login e cadastro); campo único "e-mail ou username" (`name="login"`); removido o bloco "Contas de Teste" e o emoji do título. Arquivo: `login.php`.
- 2026-11-16: página de cadastro redesenhada (header de ponta a ponta, sem busca, página única sem rolagem com footer visível; campos: nome completo, username, sexo, e-mail, senha, confirmação). Novas colunas `usuarios.username` (único) e `usuarios.sexo`. Arquivos: `cadastro.php`, `includes/header.php` (flag `$hideSearch`), `database/schema.sql`.
- 2026-11-16: criação do `CLAUDE.md`; grafo inicial gerado com Graphify (203 nós, 225 arestas), incluindo `documentacao/LootPrice2.pdf`.
