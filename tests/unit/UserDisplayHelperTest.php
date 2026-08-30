<?php

declare(strict_types=1);

namespace forumaker\Friendship\Tests\unit;

use forumaker\Friendship\Support\UserDisplayHelper;
use PHPUnit\Framework\TestCase;

class UserDisplayHelperTest extends TestCase
{
    public function test_resolve_returns_null_and_fallback_for_missing_user(): void
    {
        $this->assertNull(UserDisplayHelper::resolve(null));
        $this->assertSame('#42', UserDisplayHelper::resolve(null, 42));
    }
}
