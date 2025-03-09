<?php declare(strict_types=1);

namespace Tests\Feature;

use AliesDev\LaravelSupabaseFlysystem\SupabaseAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use League\Flysystem\UnableToReadFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(SupabaseAdapter::class)]
final class MetadataOperationsFeatureTest extends FeatureTestCase
{
    #[Test]
    public function it_gets_mime_type(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response([
                [
                    'name' => 'test-file.txt',
                    'id' => '1',
                    'metadata' => ['mimetype' => 'text/plain; charset=utf-8'],
                ],
            ], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $result = $adapter->mimeType('test-file.txt');

        // Assert the result
        $this->assertSame('text/plain', $result->mimeType());

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['search'] === 'test-file.txt');
    }

    #[Test]
    public function it_gets_last_modified(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response([
                [
                    'name' => 'test-file.txt',
                    'id' => '1',
                    'metadata' => ['lastModified' => '2023-01-01T00:00:00Z'],
                ],
            ], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $result = $adapter->lastModified('test-file.txt');

        // Assert the result
        $this->assertSame(strtotime('2023-01-01T00:00:00Z'), $result->lastModified());

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['search'] === 'test-file.txt');
    }

    #[Test]
    public function it_gets_file_size(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response([
                [
                    'name' => 'test-file.txt',
                    'id' => '1',
                    'metadata' => ['size' => 100],
                ],
            ], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $result = $adapter->fileSize('test-file.txt');

        // Assert the result
        $this->assertSame(100, $result->fileSize());

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['search'] === 'test-file.txt');
    }

    #[Test]
    public function it_throws_exception_when_metadata_not_found(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Assert that an exception is thrown
        $this->expectException(UnableToReadFile::class);

        // Call the method
        $adapter->mimeType('test-file.txt');
    }
}
