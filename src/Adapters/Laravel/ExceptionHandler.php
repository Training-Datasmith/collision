<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\Exception_Handler as ExceptionHandlerContract;
use Nuno_Maduro\Collision\Provider;
use Symfony\Component\Console\Exception\Exception_Interface as SymfonyConsoleExceptionInterface;
use Throwable;
/**
 * @internal
 */
final class Exception_Handler implements Exception_Handler_Contract
{
    /**
     * Holds an instance of the container.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    protected $container;
    /**
     * Creates a new instance of the ExceptionHandler.
     */
    public function __construct(
        Container $container,
        /**
         * Holds an instance of the application exception handler.
         */
        protected Exception_Handler_Contract $app_exception_handler
    )
    {
        $this->container = $container;
    }
    /**
     * {@inheritdoc}
     */
    public function report(Throwable $e): void
    {
        $this->app_exception_handler->report($e);
    }
    /**
     * {@inheritdoc}
     */
    public function render($request, Throwable $e)
    {
        return $this->app_exception_handler->render($request, $e);
    }
    /**
     * {@inheritdoc}
     */
    public function render_for_console(\Symfony\Component\Console\Output\Output_Interface $output, Throwable $e): void
    {
        if ($e instanceof Symfony_Console_Exception_Interface) {
            $this->app_exception_handler->render_for_console($output, $e);
        } else {
            /** @var Provider $provider */
            $provider = $this->container->make(Provider::class);
            $handler = $provider->register()->get_handler()->set_output($output);
            $handler->set_inspector(new Inspector($e));
            $handler->handle();
        }
    }
    /**
     * Determine if the exception should be reported.
     *
     * @return bool
     */
    public function should_report(Throwable $e)
    {
        return $this->app_exception_handler->should_report($e);
    }
    /**
     * Register a reportable callback.
     *
     * @return \Illuminate\Foundation\Exceptions\ReportableHandler
     */
    public function reportable(callable $report_using)
    {
        return $this->app_exception_handler->reportable($report_using);
    }
    /**
     * Register a renderable callback.
     *
     * @return $this
     */
    public function renderable(callable $render_using): self
    {
        $this->app_exception_handler->renderable($render_using);
        return $this;
    }
    /**
     * Do not report duplicate exceptions.
     *
     * @return $this
     */
    public function dont_report_duplicates(): self
    {
        $this->app_exception_handler->dont_report_duplicates();
        return $this;
    }
}