<?php
declare(strict_types=1);

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

/**
 * Entrar na liga. league_group_id codifica divisao E grupo: o client deriva a divisao
 * como floor(league_group_id / 100000) (Dd.getLeagueDivisionId). Divisao 1 = 100000 --
 * setar 1 aqui deixava a divisao em 0 e o client achava que nunca tinha entrado
 * (botao "Ingressar agora!" reenviando pra sempre). Referencia: HeroZServer do Owryn
 * (enterLeagueDivision.req.php) usa exatamente 100000 e exige honor >= 500
 * (constants: league_min_honor_points).
 *
 * Resposta via accountState() (monta 'character' do banco; template de captura para
 * essa action nao existe) + league_opponents pela MESMA fonte da tela da liga
 * (Live::leagueOpponents, tambem usada em getLeagueOpponents/refreshLeagueOpponents),
 * que persiste a lista em character.league_opponents.
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $char = Character::loadByUser($userId);

    $row = Db::row('SELECT league_group_id, honor FROM `character` WHERE id = ?', [$char->id()]) ?? [];
    if ((int)($row['league_group_id'] ?? 0) === 0) {
        if ((int)($row['honor'] ?? 0) < 500) {
            throw new GameError('errEnterLeagueDivisionMinHonorNotReached');
        }
        Db::exec('UPDATE `character` SET league_group_id = 100000, league_points = IF(league_points = 0, 100, league_points) WHERE id = ?', [$char->id()]);
    }

    $opponents = Live::leagueOpponents($char->id());
    $data = Live::accountState($userId);
    $data['league_opponents'] = $opponents;
    return $data;
};
