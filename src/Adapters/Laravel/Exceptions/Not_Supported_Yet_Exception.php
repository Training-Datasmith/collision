<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Laravel\Exceptions;

use Nuno_Maduro\Collision\Contracts\Renderless_Editor;
use Nuno_Maduro\Collision\Contracts\Renderless_Trace;
use RuntimeException;
/**
 * @internal
 */
final class Not_Supported_Yet_Exception extends RuntimeException implements Renderless_Editor, Renderless_Trace
{
}