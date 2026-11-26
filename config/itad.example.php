<?php
/**
 * Modelo de configuração da IsThereAnyDeal (API de preços usada como plano B para Epic e Nuuvem).
 *
 * COMO USAR:
 *   1. Crie uma conta grátis em https://isthereanydeal.com e registre um app em
 *      https://isthereanydeal.com/apps/ para receber a chave (API key).
 *   2. Copie este arquivo para config/itad.php (esse NÃO vai para o git) e cole a chave.
 *
 * Sem este arquivo o robô funciona normalmente; só não tem o plano B quando Epic/Nuuvem bloqueiam.
 */
return [
    'chave' => 'COLE_A_CHAVE_AQUI',
];
