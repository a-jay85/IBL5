<?php

declare(strict_types=1);

namespace Tests\Security\Fixtures;

/**
 * Deliberately wrong module factory: its public method takes an actor identity.
 *
 * Lives under tests/ so the production scan of classes/Module/Factories never
 * sees it. ModuleFactorySecuritySurfaceTest feeds it to the identity-parameter
 * detector to prove the detector can fail.
 */
final class IdentityLeakingFactory
{
    public function controller(int $loggedInTeamID): string
    {
        return 'controller for team ' . $loggedInTeamID;
    }
}
