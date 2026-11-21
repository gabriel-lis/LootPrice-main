# Graph Report - LootPrice-main  (2026-11-21)

## Corpus Check
- 54 files · ~59,529 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 18 file(s) not represented in the graph (top: .css 11, (none) 5, .lock 1)

## Summary
- 503 nodes · 966 edges · 54 communities (16 shown, 38 thin omitted)
- Extraction: 85% EXTRACTED · 15% INFERRED · 0% AMBIGUOUS · INFERRED: 144 edges (avg confidence: 0.86)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `5bf29f51`
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
- Exception
- perfil.php
- auth.php
- lp_enviar_email

## God Nodes (most connected - your core abstractions)
1. `PHPMailer` - 126 edges
2. `SMTP` - 45 edges
3. `LootPrice project` - 24 edges
4. `getDB()` - 23 edges
5. `Exception` - 22 edges
6. `Estrutura do projeto` - 21 edges
7. `lp_enviar_email()` - 13 edges
8. `lp_descobrir_populares()` - 12 edges
9. `Catalogo de jogos` - 11 edges
10. `LootPrice Home` - 11 edges

## Surprising Connections (you probably didn't know these)
- `Estrutura do projeto` --references--> `lp_enviar_avisos_promocao()`  [INFERRED]
  CLAUDE.md → includes/avisos.php
- `Registro de alterações` --references--> `lp_melhor()`  [INFERRED]
  CLAUDE.md → includes/coletor.php
- `Estrutura do projeto` --references--> `lp_executar()`  [INFERRED]
  CLAUDE.md → includes/coletor.php
- `Estrutura do projeto` --references--> `avatarHtml()`  [INFERRED]
  CLAUDE.md → includes/auth.php
- `Estrutura do projeto` --references--> `csrfField()`  [INFERRED]
  CLAUDE.md → includes/auth.php

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

## Communities (54 total, 38 thin omitted)

### Community 0 - "LootPrice project"
Cohesion: 0.06
Nodes (41): Epic Games store, Steam store, Xbox store, Actor: savings-seeking consumer, Actor: content creators and streamers, Actor: Game Pass-checking consumer, Class diagram, DER (entity-relationship diagram) (+33 more)

### Community 1 - "getDB"
Cohesion: 0.08
Nodes (42): Estrutura do projeto, getDB(), currentUser(), renderGameRowCard(), renderPosterCard(), countWishlist(), ensureDesejosTable(), getWishlistGames() (+34 more)

### Community 2 - "Catalogo de jogos"
Cohesion: 0.05
Nodes (41): Area de autenticacao, Barra de pesquisa, Catalogo de jogos, Filtro de categorias, Hero O melhor preco, Navbar, LootPrice Home, js_index scripts (pesquisa, darkmode, menulateral, auth) (+33 more)

### Community 4 - "Ator: Administrador"
Cohesion: 0.16
Nodes (16): Ator: Administrador, Caso de uso: Cadastro de jogos, Caso de uso: Cadastro de usuario, Caso de uso: Comentar/avaliar jogos, Banco: Banco de dados (usuarios), Banco: Jogos, Banco: Precos, Banco: Sinopse (+8 more)

### Community 6 - "Game cover art"
Cohesion: 0.18
Nodes (11): Game cover art, Apex Legends game cover, Dead by Daylight game cover, Death Stranding game cover, Dark Souls III game cover, Elden Ring game cover, Rainbow Six Siege game cover, Risk of Rain 2 game cover (+3 more)

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

### Community 45 - "perfil.php"
Cohesion: 0.36
Nodes (4): abrir(), aplicarZoom(), desenhar(), limitar()

### Community 46 - "auth.php"
Cohesion: 0.16
Nodes (15): 1. Antes de responder ou alterar: consultar o Graphify, 2. Depois de qualquer alteração no código: atualizar Graphify e este arquivo, Convenções, LootPrice, Registro de alterações, Regras de trabalho (obrigatórias), avatarHtml(), csrfField() (+7 more)

### Community 50 - "lp_enviar_email"
Cohesion: 0.18
Nodes (12): {closure#2}(), ensureAvisosSchema(), lp_dinheiro(), lp_email_promocao(), lp_enviar_avisos_promocao(), lp_promocoes_atuais(), lp_token_descadastro(), lp_url_descadastro() (+4 more)

## Ambiguous Edges - Review These
- `Fluxo: Cadastro do usuario` → `Passo: Sinopse do jogo`  [AMBIGUOUS]
  ideias/DCU_LOOTPRICE.drawio.png · relation: next_step

## Knowledge Gaps
- **60 isolated node(s):** `Actor: savings-seeking consumer`, `Actor: content creators and streamers`, `Actor: Game Pass-checking consumer`, `GOG store`, `Metacritic rating` (+55 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 161 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **38 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `PHPMailer` connect `RF05 real-time price comparison` to `LootPrice Home`, `.mailSend`, `Exception`, `desejos.php`, `.normalizeBreaks`, `.addOrEnqueueAnAddress`, `lp_enviar_email`, `.createBody`?**
  _High betweenness centrality (0.288) - this node is a cross-community bridge._
- **Are the 21 inferred relationships involving `getDB()` (e.g. with `Convenções` and `Estrutura do projeto`) actually correct?**
  _`getDB()` has 21 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Actor: savings-seeking consumer`, `Actor: content creators and streamers`, `Actor: Game Pass-checking consumer` to the rest of the system?**
  _60 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `LootPrice project` be split into smaller, more focused modules?**
  _Cohesion score 0.05731707317073171 - nodes in this community are weakly interconnected._
- **Why does `lp_enviar_email()` connect `lp_enviar_email` to `getDB`, `LootPrice Home`, `RF05 real-time price comparison`, `Exception`, `.addOrEnqueueAnAddress`?**
  _High betweenness centrality (0.242) - this node is a cross-community bridge._
- **Are the 18 inferred relationships involving `Exception` (e.g. with `.addAnAddress()` and `.addAttachment()`) actually correct?**
  _`Exception` has 18 INFERRED edges - model-reasoned connections that need verification._

### Low-confidence Hints
_AMBIGUOUS edges — the extractor was unsure. Verify before acting on these._

- **What is the exact relationship between `Fluxo: Cadastro do usuario` and `Passo: Sinopse do jogo`?**
  _Edge tagged AMBIGUOUS (relation: next_step) - confidence is low._