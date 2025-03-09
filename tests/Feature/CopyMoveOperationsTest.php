<?php declare(strict_types=1);

namespace Tests\Feature;

use AliesDev\LaravelSupabaseFlysystem\SupabaseAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(SupabaseAdapter::class)]
final class CopyMoveOperationsTest extends FeatureTestCase
{
    #[Test]
    public function it_copies_file(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(['Key' => 'destination-file.txt'], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $adapter->copy('source-file.txt', 'destination-file.txt', new Config());

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/copy' &&
               $request->data()['bucketId'] === self::TEST_BUCKET &&
               $request->data()['sourceKey'] === 'source-file.txt' &&
               $request->data()['destinationKey'] === 'destination-file.txt');
    }

    #[Test]
    public function it_throws_exception_when_copy_file_fails(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Assert that an exception is thrown
        $this->expectException(UnableToCopyFile::class);

        // Call the method
        $adapter->copy('source-file.txt', 'destination-file.txt', new Config());
    }

    #[Test]
    public function it_moves_file(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(['message' => 'Moved'], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Call the method
        $adapter->move('source-file.txt', 'destination-file.txt', new Config());

        // Assert the HTTP request was made
        Http::assertSent(static function (Request $request): bool {
            return $request->method() === 'POST' &&
                $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/move' &&
                $request->data()['bucketId'] === self::TEST_BUCKET &&
                $request->data()['sourceKey'] === 'source-file.txt' &&
                $request->data()['destinationKey'] === 'destination-file.txt';
        });
    }

    #[Test]
    public function it_throws_exception_when_move_file_fails(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Assert that an exception is thrown
        $this->expectException(UnableToMoveFile::class);

        // Call the method
        $adapter->move('source-file.txt', 'destination-file.txt', new Config());
    }
}
