<?php
declare(strict_types=1);

use HeroZero\GameError;

return function (array $params): array {
    throw new GameError('errDeleteUserDisabledLocal');
};
