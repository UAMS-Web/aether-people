<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People;

use Illuminate\Contracts\Foundation\Application;
use Statamic\Providers\AddonServiceProvider;

/**
 * Service provider for the add-on.
 *
 * The host application discovers it through `extra.laravel.providers` in
 * `composer.json`. Register the add-on's tags, fieldtypes, listeners, routes and
 * the like here, using the properties `AddonServiceProvider` documents, or in
 * `bootAddon()` for anything that needs Statamic booted first.
 */
class ServiceProvider extends AddonServiceProvider
{
    /**
     * The Vite build of the Control Panel assets. `input` and `publicDirectory`
     * must match `vite.config.js`; the built files are committed under
     * `resources/dist`.
     *
     * Assigned in the constructor rather than as a property default: coverage
     * drivers do not report a property default as an executable line, so
     * mutation testing could not reach it there.
     *
     * @var array{input: list<string>, publicDirectory: string}
     */
    protected $vite;

    /**
     * Create the provider and set the Vite build.
     *
     * @param  Application  $app
     */
    public function __construct($app)
    {
        parent::__construct($app);

        // Point Statamic at the Control Panel entry points and the committed build
        $this->vite = [
            'input' => [
                'resources/js/cp.js',
                'resources/css/cp.css',
            ],
            'publicDirectory' => 'resources/dist',
        ];
    }

    /**
     * Boot the add-on once Statamic has booted.
     */
    public function bootAddon(): void
    {
        // Register the add-on's behavior here
    }
}
