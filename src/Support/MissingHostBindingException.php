<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Support;

use LogicException;

/**
 * Raised when the clinical provider layer needs a host-bound contract and the host bound none
 * (UAMS-Web/aether-people#144).
 *
 * The add-on binds a default that refuses with this exception, so the package boots without the
 * host's bindings and the refusal names the contract at the point it is first needed.
 */
final class MissingHostBindingException extends LogicException
{
    /**
     * Name the unbound contract and the two ways out.
     *
     * @param  class-string  $contract  The contract the host has not bound
     */
    public static function forContract(string $contract): self
    {
        return new self(sprintf(
            'The clinical provider layer needs a host binding for %s, and none is bound. Bind it in the host, or set PEOPLE_PROVIDER_LAYER=false to turn the layer off.',
            $contract,
        ));
    }
}
