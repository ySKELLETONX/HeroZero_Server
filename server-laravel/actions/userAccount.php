<?php
declare(strict_types=1);

use HeroZero\Db;
use HeroZero\Live;

/**
 * Preferencias/conta do usuario: operacoes pequenas que gravam na tabela
 * `user` e devolvem o estado vivo (o cliente so espera o eco do accountState).
 */
return function (array $params): array {
    $userId = (int)($params['user_id'] ?? 0);
    $action = (string)($params['action'] ?? '');

    switch ($action) {
        case 'setUserLocale':
            $locale = (string)($params['locale'] ?? '');
            if ($userId > 0 && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $locale)) {
                Db::exec('UPDATE `user` SET locale = ? WHERE id = ?', [$locale, $userId]);
            }
            break;

        case 'setUserPassword':
            $pass = (string)($params['password'] ?? $params['new_password'] ?? '');
            if ($userId > 0 && strlen($pass) >= 4) {
                Db::exec('UPDATE `user` SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $userId]);
            }
            break;

        case 'optInUserMarketing':
            if ($userId > 0) Db::exec('UPDATE `user` SET email_notifications = 1 WHERE id = ?', [$userId]);
            break;

        case 'optOutUserMarketing':
            if ($userId > 0) Db::exec('UPDATE `user` SET email_notifications = 0 WHERE id = ?', [$userId]);
            break;

        case 'gameReportUser':
            // Denuncia de jogador: apenas registra no log do servidor.
            error_log('[herozero] gameReportUser user=' . $userId . ' target=' . (string)($params['reported_character_id'] ?? $params['character_id'] ?? ''));
            break;

        // setUserLatestPP / setUserLatestToS / registerUserNotificationDevice /
        // resendUserConfirmationEmail / bindUserToNetwork / renewSession /
        // refreshUser: sem estado persistido proprio; o eco do accountState
        // ja satisfaz o contrato do cliente.
        default:
            break;
    }

    return Live::accountState($userId);
};
