<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Live;

return function (array $params): array {
    $action = (string)($params['action'] ?? '');

    // SEM captura real desta resposta no HAR: em vez de deixar o fallback generico
    // (abaixo) sintetizar leaderboard_characters (shape errada p/ este painel de
    // guildas), devolvemos direto uma lista de guildas vazia + a guilda do jogador.
    if ($action === 'retrieveGuildCompetitionTournamentLeaderboardGuildsAroundOwnGuild') {
        $data = ['leaderboard_guilds' => [], 'max_guilds' => 0, 'centered_rank' => 1];
        $guild = Live::guildForUser((int)($params['user_id'] ?? 0));
        if ($guild !== null) {
            $entry = Live::shapeGuild($guild);
            $entry['rank'] = 1;
            $data['leaderboard_guilds'] = [$entry];
            $data['max_guilds'] = 1;
        }
        return Live::timeKeys($data);
    }

    $data = Live::template($action);
    $sort = match ($action) {
        'retrieveLeagueGroupLeaderboard' => 'league',
        default => ((string)($params['level_sort'] ?? '') === 'true' ? 'level' : 'honor'),
    };
    $tpl = $data['leaderboard_characters'][0] ?? $data['leaderboard_hideouts'][0] ?? [];
    $list = Live::leaderboardCharacters($tpl, $sort);
    if (array_key_exists('leaderboard_characters', $data)) {
        $data['leaderboard_characters'] = $list;
        $data['max_characters'] = count($list);
    }
    if (!array_key_exists('leaderboard_characters', $data) && !array_key_exists('leaderboard_hideouts', $data) && !array_key_exists('leaderboard_guilds', $data)) {
        $data['leaderboard_characters'] = $list;
        $data['max_characters'] = count($list);
    }
    if (array_key_exists('leaderboard_hideouts', $data)) {
        $data['leaderboard_hideouts'] = $list;
        $data['max_hideouts'] = count($list);
    }
    if (array_key_exists('leaderboard_guilds', $data)) {
        $data['leaderboard_guilds'] = [];
        $data['max_guilds'] = 0;
    }
    $data['centered_rank'] = 1;
    try {
        $char = Character::loadByUser((int)($params['user_id'] ?? 0));
        if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
    } catch (\HeroZero\GameError $e) {}
    return $data;
};
