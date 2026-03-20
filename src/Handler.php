<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision;

use Symfony\Component\Console\Output\Output_Interface;
use Whoops\Handler\Handler as AbstractHandler;
/**
 * @internal
 *
 * @see \Tests\Unit\HandlerTest
 */
final class Handler extends Abstract_Handler
{
    /**
     * Holds an instance of the writer.
     */
    private readonly Writer $writer;
    /**
     * Creates an instance of the Handler.
     */
    public function __construct(?Writer $writer = null)
    {
        $this->writer = $writer ?: new Writer();
    }
    /**
     * {@inheritdoc}
     */
    public function handle(): int
    {
        $this->writer->write($this->get_inspector());
        // @phpstan-ignore-line
        return self::QUIT;
    }
    /**
     * {@inheritdoc}
     */
    public function set_output(Output_Interface $output): self
    {
        $this->writer->set_output($output);
        return $this;
    }
    /**
     * {@inheritdoc}
     */
    public function get_writer(): Writer
    {
        return $this->writer;
    }
}