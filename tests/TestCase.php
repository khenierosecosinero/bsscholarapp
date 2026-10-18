<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cached config from `php artisan config:cache` hard-codes local/mysql and
     * ignores phpunit.xml env (APP_ENV=testing, sqlite). Clear it before boot
     * so feature tests stay isolated from the developer database.
     */
    public function createApplication()
    {
        $cachedConfig = dirname(__DIR__).DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php';
        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }

        return parent::createApplication();
    }
}
