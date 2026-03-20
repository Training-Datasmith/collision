<?php

declare (strict_types=1);
namespace Nuno_Maduro\Collision\Adapters\Phpunit;

use Nuno_Maduro\Collision\Adapters\Phpunit\Subscribers\Ensure_Printer_Is_Registered_Subscriber;
use Php_Unit\Runner\Version;
if (class_exists(Version::class) && (int) Version::series() >= 10) {
    Ensure_Printer_Is_Registered_Subscriber::register();
}