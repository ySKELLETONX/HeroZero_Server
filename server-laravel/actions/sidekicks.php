<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\Live;

/**
 * Sidekicks: lista vem da tabela `sidekicks`; o sidekick ativo fica em
 * inventory.sidekick_id. Resposta usa a chave `sidekicks` (lista de DOs com
 * os campos da tabela).
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();
    $sidekickId = (int)($params['sidekick_id'] ?? $params['id'] ?? 0);

    switch ($action) {
        case 'setSidekick':
            if ($sidekickId >= 0) {
                $owned = $sidekickId === 0
                    || (int)Db::value('SELECT COUNT(*) FROM `sidekicks` WHERE id = ? AND character_id = ?', [$sidekickId, $cid]) === 1;
                if ($owned) {
                    Db::exec('UPDATE `inventory` SET sidekick_id = ? WHERE character_id = ?', [$sidekickId, $cid]);
                }
            }
            break;

        case 'renameSidekick': {
            $name = trim((string)($params['name'] ?? ''));
            if ($sidekickId > 0 && $name !== '' && mb_strlen($name) <= 30) {
                Db::exec('UPDATE `sidekicks` SET name = ? WHERE id = ? AND character_id = ?', [$name, $sidekickId, $cid]);
            }
            break;
        }

        case 'releaseSidekick':
        case 'unbindSidekick':
            if ($sidekickId > 0) {
                if ($action === 'releaseSidekick') {
                    Db::exec('DELETE FROM `sidekicks` WHERE id = ? AND character_id = ?', [$sidekickId, $cid]);
                }
                Db::exec('UPDATE `inventory` SET sidekick_id = 0 WHERE character_id = ? AND sidekick_id = ?', [$cid, $sidekickId]);
            }
            break;

        // getSidekicks / mergeSidekick / reskillSidekick / sewSidekick /
        // setSidekickOrder: leitura ou sem persistencia propria — a lista
        // atual sempre vai na resposta abaixo.
        default:
            break;
    }

    $data = Live::accountState($userId);
    $rows = Db::rows('SELECT * FROM `sidekicks` WHERE character_id = ? ORDER BY id', [$cid]);
    $data['sidekicks'] = array_map(static function (array $r): array {
        foreach ($r as $k => $v) {
            if ($k !== 'identifier' && $k !== 'name' && is_numeric($v)) $r[$k] = (int)$v;
        }
        return $r;
    }, $rows);
    // O dialogo de sidekicks (aba "Todos") faz get_all_sidekicks().iterator() sem
    // null-check: chave ausente = null = crash (padrao chave-ausente-vira-null).
    // Lista vazia itera normal, entao ecoamos a mesma lista do jogador.
    $data['all_sidekicks'] = $data['sidekicks'];
    return $data;
};
