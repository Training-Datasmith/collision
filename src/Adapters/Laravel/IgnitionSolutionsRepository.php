<?php

declare(strict_types=1);

namespace NunoMaduro\Collision\Adapters\Laravel;

use NunoMaduro\Collision\Contracts\SolutionsRepository;
use Spatie\ErrorSolutions\Contracts\SolutionProviderRepository;
use Spatie\Ignition\Contracts\SolutionProviderRepository as IgnitionSolutionProviderRepository;
use Throwable;

/**
 * @internal
 */
final class IgnitionSolutionsRepository implements SolutionsRepository
{
    // @phpstan-ignore-line

    /**
     * IgnitionSolutionsRepository constructor.
     */
    public function __construct(
        /**
         * Holds an instance of ignition solutions provider repository.
         */
        protected IgnitionSolutionProviderRepository|SolutionProviderRepository $solutionProviderRepository
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getFromThrowable(Throwable $throwable): array // @phpstan-ignore-line
    {
        return $this->solutionProviderRepository->getSolutionsForThrowable($throwable); // @phpstan-ignore-line
    }
}
