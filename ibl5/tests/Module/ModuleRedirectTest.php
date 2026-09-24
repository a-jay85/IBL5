<?php

declare(strict_types=1);

namespace Tests\Module;

use Module\ModuleRedirect;
use PHPUnit\Framework\TestCase;

class ModuleRedirectTest extends TestCase
{
    public function testTargetForMapsEachRetiredModule(): void
    {
        $this->assertSame('modules.php?name=ApiKeys', ModuleRedirect::targetFor('PlayerExportGuide'));
        $this->assertSame('modules.php?name=Voting', ModuleRedirect::targetFor('VotingResults'));
        $this->assertSame(
            'modules.php?name=RecordHolders&op=allstar',
            ModuleRedirect::targetFor('AllStarAppearances')
        );
    }

    public function testTargetForReturnsNullForUnknownModule(): void
    {
        $this->assertNull(ModuleRedirect::targetFor('Voting'));
        $this->assertNull(ModuleRedirect::targetFor(''));
        // Lookup is case-sensitive: a case-insensitive map would resolve this.
        $this->assertNull(ModuleRedirect::targetFor('votingresults'));
    }
}
