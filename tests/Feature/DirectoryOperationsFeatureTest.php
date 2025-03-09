<?php declare(strict_types=1);

namespace Tests\Feature;

use AliesDev\LaravelSupabaseFlysystem\SupabaseAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\UnableToCreateDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(SupabaseAdapter::class)]
final class DirectoryOperationsFeatureTest extends FeatureTestCase
{
    #[Test]
    public function it_checks_if_directory_exists(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response([['name' => 'file.txt']], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $result = $adapter->directoryExists('test-dir');

        // Assert the result
        $this->assertTrue($result);

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['prefix'] === 'test-dir' &&
               $request->data()['limit'] === 1);
    }

    #[Test]
    public function it_checks_if_directory_does_not_exist(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response([], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $result = $adapter->directoryExists('test-dir');

        // Assert the result
        $this->assertFalse($result);

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['prefix'] === 'test-dir');
    }

    #[Test]
    public function it_creates_directory(): void
    {
        // Mock the HTTP responses
        Http::fake([
            // First request for directoryExists
            '*' => Http::sequence()
                ->push([], 200) // POST request for directoryExists returns empty array
                ->push(['Id' => 'placeholder-id'], 200), // POST request for write returns success
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $adapter->createDirectory('test-dir', new Config());

        // Assert the HTTP requests were made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['prefix'] === 'test-dir');

        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET.'/test-dir/.emptyFolderPlaceholder' &&
               $request->hasHeader('x-upsert', 'true') &&
               $request->body() === '');
    }

    #[Test]
    public function it_throws_exception_when_create_directory_fails(): void
    {
        // Mock the HTTP responses
        Http::fake([
            // First request for directoryExists
            '*' => Http::sequence()
                ->push([], 200) // POST request for directoryExists returns empty array
                ->push('Error', 500), // POST request for write returns error
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Assert that an exception is thrown
        $this->expectException(UnableToCreateDirectory::class);

        // Call the method
        $adapter->createDirectory('test-dir', new Config());
    }

    #[Test]
    public function it_deletes_directory(): void
    {
        // Mock the HTTP responses
        Http::fake([
            // First request for directoryExists
            '*' => Http::sequence()
                ->push([['name' => 'file.txt']], 200) // POST request for directoryExists returns items
                ->push([
                    ['path' => 'test-dir/file1.txt', 'name' => 'file1.txt', 'id' => '1'],
                    ['path' => 'test-dir/file2.txt', 'name' => 'file2.txt', 'id' => '2'],
                ], 200) // POST request for listContents returns items
                ->push(['message' => 'Deleted'], 200), // DELETE request returns success
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $adapter->deleteDirectory('test-dir');

        // Assert the HTTP requests were made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['prefix'] === 'test-dir' &&
               $request->data()['limit'] === 1);

        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['prefix'] === 'test-dir');

        Http::assertSent(static fn(Request $request): bool => $request->method() === 'DELETE' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET &&
               is_array($request->data()['prefixes']) &&
               count($request->data()['prefixes']) === 2);
    }

    #[Test]
    public function it_lists_contents(): void
    {
        // Mock the HTTP response with correct structure
        Http::fake([
            '*' => Http::response([
                [
                    'name' => 'file1.txt',
                    'id' => '1',
                    'metadata' => [
                        'size' => 100,
                        'mimetype' => 'text/plain',
                        'lastModified' => '2023-01-01T00:00:00Z',
                    ],
                ],
                [
                    'name' => 'file2.txt',
                    'id' => '2',
                    'metadata' => [
                        'size' => 200,
                        'mimetype' => 'text/plain',
                        'lastModified' => '2023-01-02T00:00:00Z',
                    ],
                ],
            ], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $contents = iterator_to_array($adapter->listContents('test-dir', false));

        // Assert the result
        $this->assertCount(2, $contents);
        $this->assertIsList($contents);
        $this->assertInstanceOf(\League\Flysystem\StorageAttributes::class, $firstFileAttributes = $contents[0]);
        $this->assertInstanceOf(\League\Flysystem\StorageAttributes::class, $secondFileAttributes = $contents[1]);
        $this->assertSame('test-dir/file1.txt', $firstFileAttributes->path());
        $this->assertSame('test-dir/file2.txt', $secondFileAttributes->path());
        $this->assertSame(100, $firstFileAttributes->fileSize());
        $this->assertSame(200, $secondFileAttributes->fileSize());
        $this->assertSame('text/plain', $firstFileAttributes->mimeType());
        $this->assertSame('text/plain', $secondFileAttributes->mimeType());

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/list/'.self::TEST_BUCKET &&
               $request->data()['prefix'] === 'test-dir');
    }
}
