<?php declare(strict_types=1);

namespace Tests\Feature;

use AliesDev\LaravelSupabaseFlysystem\SupabaseAdapter;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class FeatureTestCase extends BaseTestCase
{
    protected const TEST_ENDPOINT = 'https://example.com';

    protected const TEST_BUCKET = 'test-bucket';

    protected const TEST_KEY = 'test-key';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function createAdapter(array $config = []): SupabaseAdapter
    {
        return new SupabaseAdapter(array_merge([
            'endpoint' => self::TEST_ENDPOINT,
            'bucket' => self::TEST_BUCKET,
            'key' => self::TEST_KEY,
            'public' => true, // Ensure public URL generation is enabled
        ], $config));
    }
}
