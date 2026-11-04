<?php

namespace Tests;

use App\Models\MapLayer;
use App\Models\PublicationCategory;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function enablePublicationFixture(string $systemKey, MapLayer $layer): void
    {
        PublicationCategory::where('source_layer_id', $layer->id)->where('system_key', '!=', $systemKey)
            ->update(['source_layer_id' => null, 'is_public' => false]);
        PublicationCategory::whereKey($systemKey)->update(['source_layer_id' => $layer->id, 'is_public' => true]);
    }

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if (! $app->environment('testing') || $app->configurationIsCached()
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Feature tests require uncached testing configuration and isolated SQLite :memory:.');
        }

        return $app;
    }
}
