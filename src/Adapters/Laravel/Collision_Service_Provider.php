<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Laravel;

use Illuminate\Contracts\Debug\Exception_Handler as ExceptionHandlerContract;
use Illuminate\Support\Service_Provider;
use Nuno_Maduro\Collision\Adapters\Laravel\Commands\Test_Command;
use Nuno_Maduro\Collision\Handler;
use Nuno_Maduro\Collision\Provider;
use Nuno_Maduro\Collision\Solutions_Repositories\Null_Solutions_Repository;
use Nuno_Maduro\Collision\Writer;
use Spatie\Ignition\Contracts\Solution_Provider_Repository;
/**
 * @internal
 *
 * @final
 */
class Collision_Service_Provider extends Service_Provider
{
    /**
     * {@inheritdoc}
     */
    protected bool $defer = true;
    /**
     * Boots application services.
     */
    public function boot(): void
    {
        $this->commands([Test_Command::class]);
    }
    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        if ($this->app->running_in_console() && !$this->app->running_unit_tests()) {
            $this->app->bind(Provider::class, function (): \Nuno_Maduro\Collision\Provider {
                if ($this->app->has(Solution_Provider_Repository::class)) {
                    // @phpstan-ignore-line
                    /** @var SolutionProviderRepository $solutionProviderRepository */
                    $solution_provider_repository = $this->app->get(Solution_Provider_Repository::class);
                    // @phpstan-ignore-line
                    $solutions_repository = new Ignition_Solutions_Repository($solution_provider_repository);
                } else {
                    $solutions_repository = new Null_Solutions_Repository();
                }
                $writer = new Writer($solutions_repository);
                $handler = new Handler($writer);
                return new Provider(null, $handler);
            });
            /** @var \Illuminate\Contracts\Debug\ExceptionHandler $appExceptionHandler */
            $app_exception_handler = $this->app->make(Exception_Handler_Contract::class);
            $this->app->singleton(Exception_Handler_Contract::class, fn($app) => new Exception_Handler($app, $app_exception_handler));
        }
    }
    /**
     * {@inheritdoc}
     */
    public function provides()
    {
        return [Provider::class];
    }
}