<?php

// PHPStan-only: declares the runtime class_alias() calls from tests/bootstrap.php
// so static analysis matches what PHPUnit sees at runtime.
class_alias(\Tests\WideUnit\Mocks\MockDatabase::class, 'MockDatabase');
class_alias(\Tests\WideUnit\Mocks\MockDatabaseResult::class, 'MockDatabaseResult');
class_alias(\Tests\WideUnit\Mocks\MockPreparedStatement::class, 'MockPreparedStatement');
class_alias(\Tests\WideUnit\Mocks\MockMysqliResult::class, 'MockMysqliResult');
class_alias(\Tests\WideUnit\Mocks\UI::class, 'UI');
class_alias(\Tests\WideUnit\Mocks\Season::class, 'Season\Season');
