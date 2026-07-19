<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $data = Live::template('getCharacter');
    $boot = Live::template('autoLoginUser');
    $viewer = Character::loadByUser((int)($params['user_id'] ?? 0));
    $data['user'] = $viewer->overlayUser($data['user'] ?? $boot['user'] ?? []);
    $data['character'] = $viewer->overlayCharacter($boot['character'] ?? []);
    $data['inventory'] = $viewer->inventoryData($boot['inventory'] ?? []);
    $data['items'] = $viewer->itemsData($boot['items'][0] ?? []);
    $data['trainings'] = [$boot['trainings'][0] ?? []];
    $data = Live::attachTrainingState($data, $viewer);

    $characterId = (int)($params['character_id'] ?? 0);
    if ($characterId <= 0) $characterId = $viewer->id();
    // Fallback p/ shapes do boot: template raso aqui = requested_character sem
    // campos obrigatorios (gender, ...) e o cliente crasha ao montar o perfil.
    $data['requested_character'] = Live::requestedCharacter($characterId, ($data['requested_character'] ?? null) ?: ($boot['character'] ?? []));
    $data['requested_character_inventory'] = Live::inventoryForCharacter($characterId, ($data['requested_character_inventory'] ?? null) ?: ($boot['inventory'] ?? []));
    $data['requested_character_inventory_items'] = Live::itemsForCharacter($characterId, ($data['requested_character_inventory_items'][0] ?? null) ?: ($boot['items'][0] ?? []));
    $data['requested_character_optical_changes'] = [];
    $data['requested_character_allowed_to_attack'] = $characterId !== $viewer->id();

    // Guilda/sidekick REAIS do personagem consultado; o template (captura) traz
    // os de outro jogador. Chaves omitidas sao seguras: o cliente guarda ambas
    // com hasData antes de usar.
    $reqGuild = \HeroZero\Db::row(
        'SELECT g.* FROM `guild` g JOIN `character` c ON c.guild_id = g.id WHERE c.id = ?',
        [$characterId]
    );
    if ($reqGuild !== null) {
        $data['requested_character_guild'] = Live::shapeGuild($reqGuild);
    } else {
        unset($data['requested_character_guild']);
    }
    unset($data['requested_character_sidekick']);   // sem tabela de sidekicks ainda
    return $data;
};
