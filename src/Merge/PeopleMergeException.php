<?php

declare(strict_types=1);

namespace UAMSWeb\Aether\People\Merge;

use RuntimeException;

/**
 * Raised when a people-merge request is invalid or unsafe to execute (UAMS-Web/uams-statamic-archive#838):
 * fewer than two candidates, a non-`people` entry in the set, a survivor that
 * is not among the candidates, or a field selection naming an unknown source.
 *
 * The merge is destructive, so the service refuses rather than guessing; the CP
 * controller maps this to a 422 with the message shown to the user.
 */
class PeopleMergeException extends RuntimeException
{
    //
}
