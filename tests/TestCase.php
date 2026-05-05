<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature-тести не мають залежати від локально зібраного Vite manifest.
        $this->withoutVite();
    }
}
