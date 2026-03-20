<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision;

use Whoops\Run;
use Whoops\Run_Interface;
/**
 * @internal
 *
 * @see \Tests\Unit\ProviderTest
 */
final readonly class Provider
{
    /**
     * Holds an instance of the Run.
     */
    private Run_Interface $run;
    /**
     * Holds an instance of the handler.
     */
    private Handler $handler;
    /**
     * Creates a new instance of the Provider.
     */
    public function __construct(?Run_Interface $run = null, ?Handler $handler = null)
    {
        $this->run = $run ?: new Run();
        $this->handler = $handler ?: new Handler();
    }
    /**
     * Registers the current Handler as Error Handler.
     */
    public function register(): self
    {
        $this->run->push_handler($this->handler)->register();
        return $this;
    }
    /**
     * Returns the handler.
     */
    public function get_handler(): Handler
    {
        return $this->handler;
    }
}