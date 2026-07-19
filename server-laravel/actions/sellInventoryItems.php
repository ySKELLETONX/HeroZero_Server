<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);
    $ids = preg_split('/[;,]/', (string)($params['item_ids'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if ($ids === []) return Live::accountState($userId);

    $pdo = Db::pdo();
    $pdo->beginTransaction();
    try {
        $inventory = Db::row('SELECT * FROM `inventory` WHERE character_id = ? FOR UPDATE', [$char->id()]);
        if ($inventory === null) throw new GameError('errInventoryNotFound');

        $bagByItem = [];
        foreach ($inventory as $field => $value) {
            if (str_starts_with((string)$field, 'bag_item') && str_ends_with((string)$field, '_id')) {
                $bagByItem[(int)$value] = (string)$field;
            }
        }
        foreach ($ids as $id) {
            if (!isset($bagByItem[$id])) throw new GameError('errInventoryInvalidItem');
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));
        $items = Db::rows(
            "SELECT id, sell_price FROM `items` WHERE character_id = ? AND id IN ($marks) FOR UPDATE",
            array_merge([$char->id()], $ids)
        );
        if (count($items) !== count($ids)) throw new GameError('errInventoryInvalidItem');

        $sellPrice = array_sum(array_map(static fn(array $item): int => max(0, (int)$item['sell_price']), $items));
        $sets = [];
        foreach ($ids as $id) $sets[] = '`' . $bagByItem[$id] . '` = 0';
        Db::exec('UPDATE `inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', [$char->id()]);
        Db::exec("DELETE FROM `items` WHERE character_id = ? AND id IN ($marks)", array_merge([$char->id()], $ids));
        Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$sellPrice, $char->id()]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return Live::accountState($userId);
};
