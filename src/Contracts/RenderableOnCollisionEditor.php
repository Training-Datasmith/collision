<?php

declare(strict_types=1);

namespace NunoMaduro\Collision\Contracts;

use Whoops\Exception\Frame;

interface RenderableOnCollisionEditor
{
    /**
     * Returns the frame to be used on the Collision Editor.
     */
    public function toCollisionEditor(): Frame;
}
