# Graph Report - LootPrice-main  (2026-11-20)

## Corpus Check
- 41 files · ~28,536 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 15 file(s) not represented in the graph (top: .css 10, (none) 3, .lock 1)

## Summary
- 282 nodes · 402 edges · 41 communities (18 shown, 23 thin omitted)
- Extraction: 84% EXTRACTED · 16% INFERRED · 0% AMBIGUOUS · INFERRED: 63 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `3dfb5451`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- LootPrice project
- getDB
- Catalogo de jogos
- LootPrice Home
- Ator: Administrador
- RF05 real-time price comparison
- Game cover art
- auth.php
- admin.js
- menulateral.js
- price-promo.js
- LootPrice brand
- auth.js
- platform-icons.js
- Metacritic
- cadastro.js
- admin/index.php
- Search feature
- Light theme
- coletor.php
- recomendacoes.php
- perfil.php

## God Nodes (most connected - your core abstractions)
1. `LootPrice project` - 24 edges
2. `getDB()` - 23 edges
3. `lp_descobrir_populares()` - 12 edges
4. `Catalogo de jogos` - 11 edges
5. `LootPrice Home` - 11 edges
6. `Estrutura do projeto` - 10 edges
7. `Game cover art` - 10 edges
8. `lp_coletar()` - 8 edges
9. `lp_json()` - 8 edges
10. `lp_melhor()` - 8 edges

## Surprising Connections (you probably didn't know these)
- `Estrutura do projeto` --references--> `avatarHtml()`  [INFERRED]
  CLAUDE.md → includes/auth.php
- `Convenções` --references--> `getDB()`  [INFERRED]
  CLAUDE.md → config/db.php
- `Registro de alterações` --references--> `lp_melhor()`  [INFERRED]
  CLAUDE.md → includes/coletor.php
- `{closure#1}()` --calls--> `sanitize()`  [INFERRED]
  login.php → includes/functions.php
- `Estrutura do projeto` --references--> `getDB()`  [INFERRED]
  CLAUDE.md → config/db.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **Compared digital stores** — c_steam, c_epic, c_xbox, documentacao_lootprice2_gog, documentacao_lootprice2_nuuvem [EXTRACTED 1.00]
- **Fluxo de cadastro de jogos com bancos** — ideias_dcu_lootprice_drawio_fluxo_cadastro_jogos, ideias_dcu_lootprice_drawio_sinopse_jogo, ideias_dcu_lootprice_drawio_inserir_info_jogo, ideias_dcu_lootprice_drawio_db_jogos, ideias_dcu_lootprice_drawio_db_sinopse, ideias_dcu_lootprice_drawio_db_precos [EXTRACTED 1.00]
- **Casos de uso principais do LootPrice** — ideias_dcu_lootprice_drawio_cadastro_usuario, ideias_dcu_lootprice_drawio_cadastro_jogos, ideias_dcu_lootprice_drawio_visualizar_catalogo, ideias_dcu_lootprice_drawio_comentar_avaliar [EXTRACTED 1.00]
- **Static game pages redirecting to jogo.php** — sites_jogos_ds_pagina, sites_jogos_r6_pagina, sites_jogos_apex_pagina, sites_jogos_dbd_pagina, sites_jogos_er_pagina, sites_jogos_ror_pagina, sites_jogos_sot_pagina, sites_jogos_frame_pagina, sites_jogos_witcher_pagina, sites_jogos_ds3_pagina [EXTRACTED 1.00]
- **Store logos** — c_steam, c_epic, c_xbox, c_metacritic [INFERRED 0.75]
- **Price comparison flow** — documentacao_lootprice2_rf04, documentacao_lootprice2_rf05, documentacao_lootprice2_rf06, documentacao_lootprice2_rf09 [INFERRED 0.85]
- **Game cover gallery** — fotos_jogos_apex, fotos_jogos_dbd, fotos_jogos_ds, fotos_jogos_ds3, fotos_jogos_er, fotos_jogos_r6, fotos_jogos_ror2, fotos_jogos_sot, fotos_jogos_warframe, fotos_jogos_witcher [INFERRED 0.85]

## Communities (41 total, 23 thin omitted)

### Community 0 - "LootPrice project"
Cohesion: 0.08
Nodes (27): Actor: savings-seeking consumer, Actor: content creators and streamers, Actor: Game Pass-checking consumer, Class diagram, DER (entity-relationship diagram), External price APIs, Game search/catalog page, Xbox Game Pass subscription (+19 more)

### Community 1 - "getDB"
Cohesion: 0.12
Nodes (24): Estrutura do projeto, getDB(), renderGameRowCard(), renderPosterCard(), countWishlist(), ensureDesejosTable(), getWishlistGames(), isInWishlist() (+16 more)

### Community 2 - "Catalogo de jogos"
Cohesion: 0.09
Nodes (22): Catalogo de jogos, Hero O melhor preco, jogo.php?id=1 (Death Stranding), jogo.php?id=10 (Dark Souls 3), jogo.php?id=2 (Rainbow Six Siege), jogo.php?id=3 (Apex Legends), jogo.php?id=4 (Dead by Daylight), jogo.php?id=5 (Elden Ring) (+14 more)

### Community 3 - "LootPrice Home"
Cohesion: 0.10
Nodes (19): Area de autenticacao, Barra de pesquisa, Filtro de categorias, Navbar, LootPrice Home, js_index scripts (pesquisa, darkmode, menulateral, auth), Sidebar menu, Alternar tema (+11 more)

### Community 4 - "Ator: Administrador"
Cohesion: 0.16
Nodes (16): Ator: Administrador, Caso de uso: Cadastro de jogos, Caso de uso: Cadastro de usuario, Caso de uso: Comentar/avaliar jogos, Banco: Banco de dados (usuarios), Banco: Jogos, Banco: Precos, Banco: Sinopse (+8 more)

### Community 5 - "RF05 real-time price comparison"
Cohesion: 0.18
Nodes (14): Epic Games store, Steam store, Xbox store, Game detail page, GOG store, Nuuvem reseller, RF04 detail screen with platforms, RF05 real-time price comparison (+6 more)

### Community 6 - "Game cover art"
Cohesion: 0.18
Nodes (11): Game cover art, Apex Legends game cover, Dead by Daylight game cover, Death Stranding game cover, Dark Souls III game cover, Elden Ring game cover, Rainbow Six Siege game cover, Risk of Rain 2 game cover (+3 more)

### Community 7 - "auth.php"
Cohesion: 0.18
Nodes (11): 1. Antes de responder ou alterar: consultar o Graphify, 2. Depois de qualquer alteração no código: atualizar Graphify e este arquivo, Convenções, LootPrice, Registro de alterações, Regras de trabalho (obrigatórias), avatarHtml(), isAdmin() (+3 more)

### Community 8 - "admin.js"
Cohesion: 0.68
Nodes (7): createTable(), enterEditMode(), getUsers(), removeUser(), render(), saveEdit(), saveUsers()

### Community 9 - "menulateral.js"
Cohesion: 0.29
Nodes (4): menuBtn, sidebar, sidebarBackdrop, sidebarClose

### Community 10 - "price-promo.js"
Cohesion: 0.52
Nodes (6): computeDiscount(), init(), parseDateIfAny(), parsePrice(), renderCard(), setPlatformSale()

### Community 11 - "LootPrice brand"
Cohesion: 0.50
Nodes (5): LootPrice brand, Dark theme, Dark mode icon (crescent moon), LootPrice site logo (white text, for dark theme), LootPrice site logo (dark text, for light theme)

### Community 12 - "auth.js"
Cohesion: 0.60
Nodes (3): computePrefixToRoot(), injectAdminLink(), renderAuth()

### Community 13 - "platform-icons.js"
Cohesion: 0.83
Nodes (3): buildSiblingNames(), getFilename(), updatePlatformIcons()

### Community 14 - "Metacritic"
Cohesion: 1.00
Nodes (3): Metacritic, Metacritic logo on black, Metacritic logo (white text, transparent)

### Community 33 - "coletor.php"
Cohesion: 0.20
Nodes (26): lp_baixar_capa(), lp_categoria_id(), lp_categorias_do_jogo(), lp_coletar(), lp_core(), lp_data_lancamento(), lp_descobrir_populares(), lp_executar() (+18 more)

### Community 36 - "recomendacoes.php"
Cohesion: 0.23
Nodes (10): currentUser(), attachBestOffer(), {closure#1}(), ensureAcessosTable(), gameCategoryList(), getDiscountedGames(), getPopularGameCounts(), getRecommendedGames() (+2 more)

### Community 39 - "perfil.php"
Cohesion: 0.36
Nodes (4): abrir(), aplicarZoom(), desenhar(), limitar()

## Ambiguous Edges - Review These
- `Fluxo: Cadastro do usuario` → `Passo: Sinopse do jogo`  [AMBIGUOUS]
  ideias/DCU_LOOTPRICE.drawio.png · relation: next_step

## Knowledge Gaps
- **60 isolated node(s):** `Actor: savings-seeking consumer`, `Actor: content creators and streamers`, `Actor: Game Pass-checking consumer`, `Metacritic rating`, `Problem: scattered game price/rating info` (+55 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 112 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **23 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `sanitize()` connect `getDB` to `LootPrice Home`?**
  _High betweenness centrality (0.108) - this node is a cross-community bridge._
- **Are the 21 inferred relationships involving `getDB()` (e.g. with `Convenções` and `Estrutura do projeto`) actually correct?**
  _`getDB()` has 21 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Actor: savings-seeking consumer`, `Actor: content creators and streamers`, `Actor: Game Pass-checking consumer` to the rest of the system?**
  _60 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `LootPrice project` be split into smaller, more focused modules?**
  _Cohesion score 0.08262108262108261 - nodes in this community are weakly interconnected._
- **Why does `{closure#1}()` connect `LootPrice Home` to `getDB`?**
  _High betweenness centrality (0.102) - this node is a cross-community bridge._
- **Should `getDB` be split into smaller, more focused modules?**
  _Cohesion score 0.125 - nodes in this community are weakly interconnected._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `Fluxo: Cadastro do usuario` and `Passo: Sinopse do jogo`?**
  _Edge tagged AMBIGUOUS (relation: next_step) - confidence is low._