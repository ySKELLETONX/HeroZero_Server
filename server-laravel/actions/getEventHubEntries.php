<?php
declare(strict_types=1);

use HeroZero\Live;

/**
 * action: getEventHubEntries
 * As capturas do HAR vieram com response nula, entao nao ha template real.
 * O cliente EXIGE a chave `event_hub_entries` (array): sem ela o EventHub do
 * personagem fica null e o painel crasha em get_entries() (ver last_error.json).
 * Lista vazia = hub abre sem eventos ativos.
 */
return function (array $params): array {
    $userId = Live::currentUserId($params);
    $data = $userId > 0 ? Live::accountState($userId) : Live::timeKeys([]);
    $data['event_hub_entries'] = [];
    return $data;
};
