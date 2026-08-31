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
    /**
     * IgnitionSolutionsRepository constructor.
     */
    public function __construct(
        /**
         * Holds an instance of ignition solutions provider repository.
         */
        protected IgnitionSolutionProviderRepository|SolutionProviderRepository $solutionProviderRepository // @phpstan-ignore-line
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getFromThrowable(Throwable $throwable): array // @phpstan-ignore-line
    {
        return $this->solutionProviderRepository->getSolutionsForThrowable($throwable); // @phpstan-ignore-line
    }
}
