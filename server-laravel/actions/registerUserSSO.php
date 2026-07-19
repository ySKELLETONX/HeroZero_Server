<?php
declare(strict_types=1);

/**
 * action: registerUserSSO  (registro de conta convidado/SSO, tela inicial do jogo)
 * Entrada: platform, platform_user_id, registration_source, device_info(json), app_version
 * Cria uma linha `user` nova (sem character ainda: o cliente segue para a tela de
 * criacao de heroi, que chama createCharacter e setCharacterName em seguida).
 * Resposta: so o `user`, na shape do template de boot (autoLoginUser).
 * SEM captura real desta resposta no HAR (payload nao gravado); shape best-effort.
 */

use HeroZero\Db;
use HeroZero\Live;

return function (array $params): array {
    $platform = (string)($params['platform'] ?? 'guest');
    $source   = (string)($params['registration_source'] ?? '');
    $deviceInfo = json_decode((string)($params['device_info'] ?? ''), true);
    $locale = 'pt_BR';
    if (is_array($deviceInfo) && isset($deviceInfo['language']) && $deviceInfo['language'] === 'en') {
        $locale = 'en_US';
    }

    $session = bin2hex(random_bytes(15));
    $email = $platform . '_' . bin2hex(random_bytes(6)) . '@guest.local';

    Db::exec(
        "INSERT INTO `user`
            (email, password_hash, session_id, premium_currency, locale, confirmed, trusted,
             network, ts_creation, registration_source, last_login_ip, login_count, ts_last_login)
         VALUES (?, ?, ?, 30, ?, 0, 0, ?, UNIX_TIMESTAMP(), ?, ?, 1, UNIX_TIMESTAMP())",
        [$email, password_hash($session, PASSWORD_DEFAULT), $session, $locale, $platform, $source, (string)($_SERVER['REMOTE_ADDR'] ?? '')]
    );
    $userId = (int)Db::pdo()->lastInsertId();

    $tpl = Live::template('autoLoginUser');
    $user = $tpl['user'] ?? [];
    foreach ($user as $key => $value) {
        if (is_int($value)) $user[$key] = 0;
        elseif (is_bool($value)) $user[$key] = false;
        elseif (is_string($value)) $user[$key] = '';
    }
    $user['id'] = $userId;
    $user['session_id'] = $session;
    $user['premium_currency'] = 30;
    $user['locale'] = $locale;
    $user['email'] = $email;

    return [
        'user' => $user,
        'server_time' => time(),
        'time_correction' => 0,
    ];
};
