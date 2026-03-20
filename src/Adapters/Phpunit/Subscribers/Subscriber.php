<?php

declare (strict_types=1);
/**
 * This file is part of Collision.
 *
 * (c) Nuno Maduro <enunomaduro@gmail.com>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
namespace Nuno_Maduro\Collision\Adapters\Phpunit\Subscribers;

use Nuno_Maduro\Collision\Adapters\Phpunit\Printers\Reportable_Printer;
/**
 * @internal
 */
abstract class Subscriber
{
    /**
     * Creates a new subscriber.
     */
    public function __construct(
        /**
         * The printer instance.
         */
        private readonly Reportable_Printer $printer
    )
    {
    }
    /**
     * Returns the printer instance.
     */
    protected function printer(): Reportable_Printer
    {
        return $this->printer;
    }
}