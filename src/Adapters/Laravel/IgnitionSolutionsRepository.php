<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Laravel;

use Nuno_Maduro\Collision\Contracts\Solutions_Repository;
use Spatie\Error_Solutions\Contracts\Solution_Provider_Repository;
use Spatie\Ignition\Contracts\Solution_Provider_Repository as IgnitionSolutionProviderRepository;
use Throwable;
/**
 * @internal
 */
final class Ignition_Solutions_Repository implements Solutions_Repository
{
    // @phpstan-ignore-line
    /**
     * IgnitionSolutionsRepository constructor.
     */
    public function __construct(
        /**
         * Holds an instance of ignition solutions provider repository.
         */
        protected Ignition_Solution_Provider_Repository|Solution_Provider_Repository $solution_provider_repository
    )
    {
    }
    /**
     * {@inheritdoc}
     */
    public function get_from_throwable(Throwable $throwable): array
    {
        return $this->solution_provider_repository->get_solutions_for_throwable($throwable);
        // @phpstan-ignore-line
    }
}