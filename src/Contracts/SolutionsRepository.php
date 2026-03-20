<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Contracts;

use Spatie\Ignition\Contracts\Solution;
use Throwable;
/**
 * @internal
 */
interface Solutions_Repository
{
    /**
     * Gets the solutions from the given `$throwable`.
     *
     * @return array<int, Solution>
     */
    public function get_from_throwable(Throwable $throwable): array;
    // @phpstan-ignore-line
}