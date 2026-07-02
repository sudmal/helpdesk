<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Inertia\Testing\AssertableInertia;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
}
