<?php
declare(strict_types=1);

/**
 * action: loginUser  (login por email/senha)
 *
 * Entrada: email, password, platform, platform_user_id, client_id,
 *          app_version, device_info(json)
 *
 * Sucesso -> application.onLogin(data, isLoginUser=true).
 * TODO(RE): mapear a estrutura exata de `data` (user id, session id, character...).
 *   Necessita captura de trafego real ou analise dos data-objects DO*.
 */

use HeroZero\Character;
use HeroZero\Db;
use HeroZero\GameError;
use HeroZero\Live;

return function (array $params): array {
    $email    = (string)($params['email'] ?? '');
    $password = (string)($params['password'] ?? '');

    if ($email === '' || $password === '') {
        throw new GameError('errLoginInvalid');
    }

    $u = Db::row('SELECT id, password_hash, session_id FROM `user` WHERE email = ?', [$email]);
    if (!$u || !password_verify($password, (string)$u['password_hash'])) {
        throw new GameError('errLoginInvalid');
    }

    $session = bin2hex(random_bytes(15));
    Db::exec(
        'UPDATE `user`
            SET session_id_cache5 = session_id_cache4,
                session_id_cache4 = session_id_cache3,
                session_id_cache3 = session_id_cache2,
                session_id_cache2 = session_id_cache1,
                session_id_cache1 = session_id,
                session_id = ?,
                ts_last_login = UNIX_TIMESTAMP(),
                login_count = login_count + 1
          WHERE id = ?',
        [$session, (int)$u['id']]
    );
    $char = Character::loadByUser((int)$u['id']);

    $data = Live::template('autoLoginUser');
    if (isset($data['user'])) $data['user'] = $char->overlayUser($data['user']);
    if (isset($data['character'])) $data['character'] = $char->overlayCharacter($data['character']);
    $data['user']['session_id'] = $session;
    return $data;
};
