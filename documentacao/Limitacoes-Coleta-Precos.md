# Limitações da coleta de preços e como o LootPrice lida com elas

O LootPrice coleta preços automaticamente (robô em PHP, todo dia às 14:00). Cada loja permite
um nível diferente de acesso automático. Esta página registra o que foi observado e as decisões tomadas.

## Situação por loja (testado em 26/11/2026)

| Loja | Como o robô lê o preço | Situação |
|---|---|---|
| Steam | API pública da loja (`appdetails`, `storesearch`) | Funciona |
| GOG | Catálogo público em JSON | Funciona |
| Xbox | `DisplayCatalog` da Microsoft | Funciona |
| Nuuvem | HTML da página de busca | **Limita acessos (HTTP 429)**: responde vazio quando recebe muitas requisições seguidas |
| Epic Games | GraphQL da loja | **Bloqueia robôs (Cloudflare, HTTP 403)** |

## Decisão de projeto: respeitar os bloqueios, não burlar

O robô se identifica de forma honesta (`LootPriceBot/1.0`) e **não** usa disfarce de navegador,
troca de IP ou técnicas para driblar proteções anti-robô. Motivos: os termos de uso das lojas
proíbem, o método quebra a qualquer mudança do site e não é uma prática adequada para um projeto acadêmico.

## O que o robô faz quando uma loja barra o acesso

1. **Espaça os pedidos por site**: 3 s entre buscas na Nuuvem (0,35 s nas demais).
2. **Respeita o `Retry-After`**: ao receber 429, espera o tempo pedido pela loja (entre 5 e 30 s) e tenta uma única vez de novo.
3. **Pausa o site**: se continuar barrado, para de consultar aquela loja até o fim da execução, para não piorar o bloqueio.
4. **Não apaga nada por causa do bloqueio**: o jogo não é marcado como "não encontrado na loja" e o último preço conhecido é mantido (preços com mais de 14 dias são removidos pela regra normal do robô).
5. **Plano B oficial (opcional)**: com uma chave grátis da API da [IsThereAnyDeal](https://docs.isthereanydeal.com) em `config/itad.php`, o preço da Epic/Nuuvem vem dessa API (preço em reais, vindo de uma fonte que tem permissão para coletá-los). O log marca "preço obtido pela IsThereAnyDeal".
6. **Avisa o administrador**: o dashboard e a tela do robô mostram "*loja* bloqueou o robô (limite de acessos), tente de novo mais tarde". Isso é diferente do aviso de "site pode ter mudado".
7. **Preço manual**: em último caso o administrador cadastra o preço em `admin/precos.php`.

## Limitações conhecidas

- Sem a chave da IsThereAnyDeal, os preços da Epic dependem de cadastro manual.
- O link de compra vindo da IsThereAnyDeal é um link de redirecionamento para a loja.
- Os preços da IsThereAnyDeal podem ter alguns minutos/horas de atraso em relação à loja.
- O robô roda uma vez por dia; promoções relâmpago podem não aparecer até a próxima coleta.
