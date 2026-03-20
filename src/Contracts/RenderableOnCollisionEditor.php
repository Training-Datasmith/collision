<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Contracts;

use Whoops\Exception\Frame;
interface Renderable_On_Collision_Editor
{
    /**
     * Returns the frame to be used on the Collision Editor.
     */
    public function to_collision_editor(): Frame;
}