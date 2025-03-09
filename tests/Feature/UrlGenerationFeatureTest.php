<?php declare(strict_types=1);

namespace Tests\Feature;

use AliesDev\LaravelSupabaseFlysystem\SupabaseAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(SupabaseAdapter::class)]
final class UrlGenerationFeatureTest extends FeatureTestCase
{
    #[Test]
    public function it_gets_public_url(): void
    {
        // Create the adapter with proper public configuration
        $adapter = $this->createAdapter([
            'public' => 'public', // String value is required for public URL generation
            'url' => 'https://example.com',
        ]);

        // Call the method
        $result = $adapter->getPublicUrl('test-file.txt');

        // Assert the result
        $this->assertSame('https://example.com/object/public/'.self::TEST_BUCKET.'/test-file.txt', $result);
    }

    #[Test]
    public function it_gets_public_url_with_transform(): void
    {
        // Create the adapter with proper public configuration
        $adapter = $this->createAdapter([
            'public' => 'public', // String value is required for public URL generation
            'url' => 'https://example.com',
        ]);

        // Call the method
        $result = $adapter->getPublicUrl('test-file.jpg', [
            'transform' => ['width' => 100, 'height' => 100],
        ]);

        // Assert the result
        $this->assertStringContainsString('https://example.com/render/image/public/'.self::TEST_BUCKET.'/test-file.jpg', $result);
        $this->assertStringContainsString('transform=', $result);
        $this->assertStringContainsString('width', $result);
        $this->assertStringContainsString('height', $result);
    }

    #[Test]
    public function it_gets_signed_url(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(['signedURL' => '/signed-url'], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter([
            'url' => 'https://example.com',
        ]);

        // Call the method
        $result = $adapter->getSignedUrl('test-file.txt');

        // Assert the result
        $this->assertSame('https://example.com/signed-url?transform={"format":"origin"}', $result);

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/sign/'.self::TEST_BUCKET.'/test-file.txt' &&
               $request->data()['expiresIn'] === 3600);
    }

    #[Test]
    public function it_gets_signed_url_with_options(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(['signedURL' => '/signed-url'], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter([
            'url' => 'https://example.com',
        ]);

        // Call the method
        $result = $adapter->getSignedUrl('test-file.txt', [
            'expiresIn' => 1800,
            'download' => true,
        ]);

        // Assert the result
        $this->assertSame('https://example.com/signed-url?transform={"format":"origin"}&download', $result);

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/sign/'.self::TEST_BUCKET.'/test-file.txt' &&
               $request->data()['expiresIn'] === 1800);
    }

    #[Test]
    public function it_throws_exception_when_signed_url_fails(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter();

        // Assert that an exception is thrown
        $this->expectException(UnableToGenerateTemporaryUrl::class);

        // Call the method
        $adapter->getSignedUrl('test-file.txt');
    }

    #[Test]
    public function it_gets_temporary_url(): void
    {
        // Mock the HTTP response
        Http::fake([
            '*' => Http::response(['signedURL' => '/signed-url'], 200),
        ]);

        // Create the adapter
        $adapter = $this->createAdapter([
            'url' => 'https://example.com',
        ]);

        // Call the method
        $result = $adapter->getTemporaryUrl('test-file.txt', now()->addHour(), []);

        // Assert the result
        $this->assertSame('https://example.com/signed-url?transform={"format":"origin"}', $result);

        // Assert the HTTP request was made
        Http::assertSent(static fn(Request $request): bool => $request->method() === 'POST' &&
               $request->url() === self::TEST_ENDPOINT.'/storage/v1/object/sign/'.self::TEST_BUCKET.'/test-file.txt');
    }
}
