<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Solutions_Repositories;

use Nuno_Maduro\Collision\Contracts\Solutions_Repository;
use Throwable;
/**
 * @internal
 */
final class Null_Solutions_Repository implements Solutions_Repository
{
    /**
     * {@inheritdoc}
     */
    public function get_from_throwable(Throwable $throwable): array
    {
        return [];
    }
}