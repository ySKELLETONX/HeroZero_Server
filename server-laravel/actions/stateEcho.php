<?php
declare(strict_types=1);

use HeroZero\Live;

/**
 * Handler generico para acoes que nao mutam estado persistido proprio:
 * responde a shape capturada da acao (quando ha fixture/HAR) sobreposta com o
 * estado vivo da conta; sem captura, devolve o accountState completo — o
 * cliente re-sincroniza e nenhuma chave DO esperada fica faltando.
 */
return function (array $params): array {
    $userId = Live::currentUserId($params);
    $action = (string)($params['action'] ?? '');
    $tpl = Live::template($action);
    // Sem captura o template so tem time keys + constants: nesse caso o
    // accountState completo e a resposta mais segura p/ o merge do cliente.
    $meaningful = array_diff_key($tpl, array_flip(['server_time', 'time_correction', 'constants']));
    if ($meaningful === [] || $userId <= 0) {
        return $userId > 0 ? Live::accountState($userId) : $tpl;
    }
    return Live::accountState($userId, Live::overlayAccount($tpl, $userId));
};
