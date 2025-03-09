<?php declare(strict_types=1);

namespace Tests\Feature;

use AliesDev\LaravelSupabaseFlysystem\SupabaseAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(SupabaseAdapter::class)]
final class FileOperationsFeatureTest extends FeatureTestCase
{
    #[Test]
    public function it_checks_if_file_exists(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(null, 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $result = $adapter->fileExists('test-file.txt');

        // Assert the result
        $this->assertTrue($result);

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'HEAD' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET.'/test-file.txt' &&
               $request->hasHeader('Authorization', 'Bearer '.self::TEST_KEY) &&
               $request->hasHeader('apiKey', self::TEST_KEY));
    }

    #[Test]
    public function it_checks_if_file_does_not_exist(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $result = $adapter->fileExists('test-file.txt');

        // Assert the result
        $this->assertFalse($result);

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'HEAD' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET.'/test-file.txt');
    }

    #[Test]
    public function it_reads_file(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response('file content', 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $result = $adapter->read('test-file.txt');

        // Assert the result
        $this->assertSame('file content', $result);

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'GET' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET.'/test-file.txt');
    }

    #[Test]
    public function it_throws_exception_when_read_file_fails(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response('Not found', 404),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Assert that an exception is thrown
        $this->expectException(UnableToReadFile::class);

        // Call the method
        $adapter->read('test-file.txt');
    }

    #[Test]
    public function it_writes_file(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(['Id' => 'file-id'], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $adapter->write('test-file.txt', 'file content', new Config());

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET.'/test-file.txt' &&
               $request->hasHeader('x-upsert', 'true') &&
               $request->body() === 'file content');
    }

    #[Test]
    public function it_throws_exception_when_write_file_fails(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Assert that an exception is thrown
        $this->expectException(UnableToWriteFile::class);

        // Call the method
        $adapter->write('test-file.txt', 'file content', new Config());
    }

    #[Test]
    public function it_deletes_file(): void
    {
        // Mock the HTTP responses
        Http::fake([
            // First request for fileExists
            '*' => Http::sequence()
                ->push(null, 200) // HEAD request for fileExists returns 200
                ->push(['message' => 'Deleted'], 200), // DELETE request returns 200
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $adapter->delete('test-file.txt');

        // Assert the HTTP requests were made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'HEAD' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET.'/test-file.txt');

        Http::assertSent(static fn(Request $request): bool => $request->method() === 'DELETE' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET &&
               $request->data()['prefixes'][0] === 'test-file.txt');
    }

    #[Test]
    public function it_skips_deleting_non_existent_file(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(null, 404),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $adapter->delete('test-file.txt');

        // Assert only the HEAD request was made, not the DELETE
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'HEAD' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/'.self::TEST_BUCKET.'/test-file.txt');

        Http::assertNotSent(static fn(Request $request): bool => $request->method() === 'DELETE');
    }
}
