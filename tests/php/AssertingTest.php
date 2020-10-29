<?php

namespace Marcz\Cleaner\Tests;

use SilverStripe\Dev\SapphireTest;

class AssertingTest extends SapphireTest
{
    public function testAssertions()
    {
        $this->assertTrue(true);
        $this->assertFalse(false);
    }
}
