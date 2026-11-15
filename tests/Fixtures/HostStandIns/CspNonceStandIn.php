<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Tests\Fixtures\HostStandIns;

use Statamic\Tags\Tags;

/**
 * A stand-in for the host's `csp_nonce` tag, which the moved templates put on an inline `<style>`.
 */
final class CspNonceStandIn extends Tags
{
    /**
     * The nonce the host's security headers would bind to the request.
     */
    public function index(): string
    {
        return 'test-nonce';
    }
}
