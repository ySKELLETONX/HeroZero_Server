<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/**
 * Operacoes de inventario/banco/cinto de missils.
 *
 * Mapa de slots do cliente html5_257 (moveInventoryItem):
 *   1..8  = equipamento (mask, cape, suit, belt, boots, weapon, gadget, missiles)
 *   9..26 = mochila (bag_item1..bag_item18)
 * Banco: bank_item1..bank_item90 (tabela bank_inventory, max_bank_index).
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');
    $char = Character::loadByUser($userId);
    $cid = $char->id();

    $slotField = static function (int $slot): ?string {
        $equip = [1 => 'mask_item_id', 2 => 'cape_item_id', 3 => 'suit_item_id', 4 => 'belt_item_id',
                  5 => 'boots_item_id', 6 => 'weapon_item_id', 7 => 'gadget_item_id', 8 => 'missiles_item_id'];
        if (isset($equip[$slot])) return $equip[$slot];
        if ($slot >= 9 && $slot <= 26) return 'bag_item' . ($slot - 8) . '_id';
        return null;
    };

    $findSlotOf = static function (array $inv, int $itemId, string $prefix = ''): ?string {
        foreach ($inv as $field => $value) {
            if ((int)$value !== $itemId) continue;
            $f = (string)$field;
            if (!str_ends_with($f, '_item_id') && !preg_match('/^(bag|bank|shop)/', $f) ) continue;
            if ($prefix !== '' && !str_starts_with($f, $prefix)) continue;
            if (str_starts_with($f, 'shop')) continue;
            return $f;
        }
        return null;
    };

    $deleteItem = static function (int $cid, int $itemId): void {
        Db::exec('DELETE FROM `items` WHERE character_id = ? AND id = ?', [$cid, $itemId]);
    };

    switch ($action) {
        case 'moveInventoryItem': {
            $itemId = (int)($params['item_id'] ?? 0);
            $target = $slotField((int)($params['target_slot'] ?? 0));
            if ($itemId > 0 && $target !== null) {
                $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
                $source = $findSlotOf($inv, $itemId);
                $displaced = (int)($inv[$target] ?? 0);
                $sets = ["`{$target}` = " . $itemId];
                if ($source !== null && $source !== $target) {
                    // swap: o item que estava no destino volta pro slot de origem
                    $sets[] = "`{$source}` = " . ($displaced > 0 ? $displaced : 0);
                }
                Db::exec('UPDATE `inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', [$cid]);
            }
            break;
        }

        case 'sellInventoryItem': {
            $itemId = (int)($params['item_id'] ?? 0);
            if ($itemId > 0) {
                $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
                $slot = $findSlotOf($inv, $itemId);
                $item = Db::row('SELECT id, sell_price FROM `items` WHERE character_id = ? AND id = ?', [$cid, $itemId]);
                if ($slot !== null && $item !== null) {
                    Db::exec("UPDATE `inventory` SET `{$slot}` = 0 WHERE character_id = ?", [$cid]);
                    $deleteItem($cid, $itemId);
                    Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [max(0, (int)$item['sell_price']), $cid]);
                }
            }
            break;
        }

        case 'useInventoryItem': {
            $itemId = (int)($params['item_id'] ?? 0);
            if ($itemId > 0) {
                $item = Db::row('SELECT id, charges FROM `items` WHERE character_id = ? AND id = ?', [$cid, $itemId]);
                if ($item !== null) {
                    $charges = (int)$item['charges'] - 1;
                    if ($charges > 0) {
                        Db::exec('UPDATE `items` SET charges = ? WHERE character_id = ? AND id = ?', [$charges, $cid, $itemId]);
                    } else {
                        $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
                        $slot = $findSlotOf($inv, $itemId);
                        if ($slot !== null) {
                            Db::exec("UPDATE `inventory` SET `{$slot}` = 0 WHERE character_id = ?", [$cid]);
                        }
                        $deleteItem($cid, $itemId);
                    }
                }
            }
            break;
        }

        case 'washInventoryItem':
            Db::exec('UPDATE `character` SET ts_last_wash_item = ? WHERE id = ?', [time(), $cid]);
            break;

        case 'addInventoryItemToBank':
        case 'removeBankItemToInventory':
        case 'moveBankInventoryItem': {
            $itemId = (int)($params['item_id'] ?? 0);
            if ($itemId <= 0) break;
            Db::exec('INSERT INTO `bank_inventory` (character_id, max_bank_index) SELECT ?, 9 FROM DUAL
                      WHERE NOT EXISTS (SELECT 1 FROM `bank_inventory` WHERE character_id = ?)', [$cid, $cid]);
            $bank = Db::row('SELECT * FROM `bank_inventory` WHERE character_id = ?', [$cid]) ?? [];
            $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
            if ($action === 'addInventoryItemToBank') {
                $slot = $findSlotOf($inv, $itemId, 'bag');
                $free = null;
                $max = max(1, (int)($bank['max_bank_index'] ?? 9));
                for ($i = 1; $i <= min(90, $max); $i++) {
                    if ((int)($bank['bank_item' . $i . '_id'] ?? -1) <= 0) { $free = 'bank_item' . $i . '_id'; break; }
                }
                if ($slot !== null && $free !== null) {
                    Db::exec("UPDATE `inventory` SET `{$slot}` = 0 WHERE character_id = ?", [$cid]);
                    Db::exec("UPDATE `bank_inventory` SET `{$free}` = ? WHERE character_id = ?", [$itemId, $cid]);
                }
            } elseif ($action === 'removeBankItemToInventory') {
                $slot = $findSlotOf($bank, $itemId, 'bank');
                $free = null;
                for ($i = 1; $i <= 18; $i++) {
                    if ((int)($inv['bag_item' . $i . '_id'] ?? 0) <= 0) { $free = 'bag_item' . $i . '_id'; break; }
                }
                if ($slot !== null && $free !== null) {
                    Db::exec("UPDATE `bank_inventory` SET `{$slot}` = 0 WHERE character_id = ?", [$cid]);
                    Db::exec("UPDATE `inventory` SET `{$free}` = ? WHERE character_id = ?", [$itemId, $cid]);
                }
            } else { // moveBankInventoryItem
                $targetSlot = (int)($params['target_slot'] ?? 0);
                $target = ($targetSlot >= 1 && $targetSlot <= 90) ? 'bank_item' . $targetSlot . '_id' : null;
                $source = $findSlotOf($bank, $itemId, 'bank');
                if ($target !== null) {
                    $displaced = (int)($bank[$target] ?? 0);
                    $sets = ["`{$target}` = " . $itemId];
                    if ($source !== null && $source !== $target) {
                        $sets[] = "`{$source}` = " . max(0, $displaced);
                    }
                    Db::exec('UPDATE `bank_inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', [$cid]);
                }
            }
            break;
        }

        case 'sellBankInventoryItem':
        case 'sellAllBankInventoryItems': {
            $bank = Db::row('SELECT * FROM `bank_inventory` WHERE character_id = ?', [$cid]);
            if ($bank === null) break;
            $ids = [];
            if ($action === 'sellBankInventoryItem') {
                $one = (int)($params['item_id'] ?? 0);
                if ($one > 0 && $findSlotOf($bank, $one, 'bank') !== null) $ids[] = $one;
            } else {
                for ($i = 1; $i <= 90; $i++) {
                    $v = (int)($bank['bank_item' . $i . '_id'] ?? 0);
                    if ($v > 0) $ids[] = $v;
                }
            }
            if ($ids === []) break;
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $items = Db::rows("SELECT id, sell_price FROM `items` WHERE character_id = ? AND id IN ($marks)", array_merge([$cid], $ids));
            $total = array_sum(array_map(static fn(array $it): int => max(0, (int)$it['sell_price']), $items));
            $sets = [];
            foreach ($ids as $id) {
                $slot = $findSlotOf($bank, $id, 'bank');
                if ($slot !== null) $sets[] = "`{$slot}` = 0";
            }
            if ($sets !== []) {
                Db::exec('UPDATE `bank_inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', [$cid]);
            }
            Db::exec("DELETE FROM `items` WHERE character_id = ? AND id IN ($marks)", array_merge([$cid], $ids));
            Db::exec('UPDATE `character` SET game_currency = game_currency + ? WHERE id = ?', [$total, $cid]);
            break;
        }

        case 'upgradeBankInventory': {
            Db::exec('INSERT INTO `bank_inventory` (character_id, max_bank_index) SELECT ?, 9 FROM DUAL
                      WHERE NOT EXISTS (SELECT 1 FROM `bank_inventory` WHERE character_id = ?)', [$cid, $cid]);
            Db::exec('UPDATE `bank_inventory` SET max_bank_index = LEAST(90, max_bank_index + 9) WHERE character_id = ?', [$cid]);
            break;
        }

        case 'moveAmmoBeltItem': {
            $itemId = (int)($params['item_id'] ?? 0);
            $targetSlot = (int)($params['target_slot'] ?? 0);
            if ($itemId > 0 && $targetSlot >= 1 && $targetSlot <= 4) {
                $target = 'missiles' . $targetSlot . '_item_id';
                $inv = Db::row('SELECT * FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
                $source = $findSlotOf($inv, $itemId);
                $sets = ["`{$target}` = " . $itemId];
                if ($source !== null && $source !== $target) {
                    $sets[] = "`{$source}` = " . max(-1, (int)($inv[$target] ?? -1));
                }
                Db::exec('UPDATE `inventory` SET ' . implode(', ', $sets) . ' WHERE character_id = ?', [$cid]);
            }
            break;
        }

        case 'upgradeAmmoBelt': {
            // Destrava o proximo slot do cinto (missilesN_item_id: -1 = travado, 0 = vazio).
            // Precos oficiais (constants_json do CDN): ammo_belt_slotN_unlock_premium_currency_amount.
            $costs = [1 => 10, 2 => 15, 3 => 20, 4 => 25];
            $inv = Db::row('SELECT missiles1_item_id, missiles2_item_id, missiles3_item_id, missiles4_item_id
                            FROM `inventory` WHERE character_id = ?', [$cid]) ?? [];
            $next = null;
            for ($i = 1; $i <= 4; $i++) {
                if ((int)($inv['missiles' . $i . '_item_id'] ?? 0) === -1) { $next = $i; break; }
            }
            if ($next === null) break; // todos ja destravados
            $cost = $costs[$next];
            $paid = Db::exec(
                'UPDATE `user` SET premium_currency = premium_currency - ? WHERE id = ? AND premium_currency >= ?',
                [$cost, $userId, $cost]
            );
            if ($paid < 1) {
                throw new GameError('errRemovePremiumCurrencyNotEnough');
            }
            Db::exec("UPDATE `inventory` SET `missiles{$next}_item_id` = 0 WHERE character_id = ?", [$cid]);
            break;
        }

        // sewInventoryItem / lockBankItem / unlockBankItem:
        // sem coluna persistida propria — o eco do accountState mantem o cliente
        // consistente com o que o banco realmente tem.
        default:
            break;
    }

    return Live::accountState($userId);
};
