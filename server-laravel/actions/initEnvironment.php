<?php
declare(strict_types=1);

use HeroZero\Replay;

return function (array $params): array {
    return Replay::data('initEnvironment');
};
