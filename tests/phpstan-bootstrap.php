<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

// Runs after Larastan has booted its application: put the add-on's views on the finder, as the service
// provider does at runtime, so a `view-string` naming one of them is known to the analysis.
View::addLocation(__DIR__.'/../resources/views');
