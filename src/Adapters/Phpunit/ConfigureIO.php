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
namespace Nuno_Maduro\Collision\Adapters\Phpunit;

use Reflection_Object;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output;
/**
 * @internal
 */
final class Configure_Io
{
    /**
     * Configures both given input and output with
     * options from the environment.
     *
     * @throws \ReflectionException
     */
    public static function of(Input_Interface $input, Output $output): void
    {
        $application = new Application();
        $reflector = new Reflection_Object($application);
        $method = $reflector->get_method('configureIO');
        $method->invoke($application, $input, $output);
    }
}