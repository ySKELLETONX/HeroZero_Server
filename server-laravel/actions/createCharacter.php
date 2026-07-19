<?php
declare(strict_types=1);

/**
 * action: createCharacter  (tela de criacao do heroi, apos registerUserSSO)
 * Entrada: gender, hair_color, skin_color, hair_type, head_type, eyes_type,
 *          eyebrows_type, nose_type, mouth_type, facial_hair_type, decoration_type
 * Cria o character (nome vazio; setCharacterName roda logo em seguida no fluxo real)
 * com a aparencia escolhida. Idempotente: se a conta ja tem character, so reaplica
 * a aparencia enviada em vez de falhar (o cliente pode reenviar em retry de rede).
 * SEM captura real desta resposta no HAR; shape best-effort (boot completo).
 */

use HeroZero\Character;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new GameError('errRequestInvalidParameter');
    }
    $gender = ((string)($params['gender'] ?? 'm')) === 'f' ? 'f' : 'm';

    try {
        $char = Character::loadByUser($userId);
    } catch (GameError $e) {
        $char = Character::createNew($userId, '', $gender);
    }

    $char->setAppearance($params);

    return Live::accountState($userId);
};
