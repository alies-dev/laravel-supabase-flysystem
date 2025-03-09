<?php

declare(strict_types=1);

namespace AliesDev\LaravelSupabaseFlysystem;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToGenerateTemporaryUrl;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;

final class SupabaseAdapter implements FilesystemAdapter
{
    private const EMPTY_FOLDER_PLACEHOLDER_NAME = '.emptyFolderPlaceholder';

    private const ITEMS_PER_PAGE = 100;

    private readonly string $endpoint;

    private readonly string $bucket;

    private readonly string $key;

    private PendingRequest $httpClient;

    private readonly Config $config;

    /**
     * @param array<string, mixed> $config
     * @param PendingRequest|null $httpClient Custom HTTP client for requests
     * @throws \LogicException
     */
    public function __construct(array $config, ?PendingRequest $httpClient = null)
    {
        $this->config = new Config($config);
        $endpoint = $this->config->get('endpoint') ?? throw new \LogicException('Supabase endpoint is not specified');
        $this->endpoint = $endpoint.'/storage/v1';

        $this->bucket = $this->config->get('bucket') ?? throw new \LogicException('Supabase bucket is not specified');
        $this->key = $this->config->get('key') ?? throw new \LogicException('Supabase key is not specified');

        $this->httpClient = $httpClient ?? Http::baseUrl($this->endpoint)->timeout(30);
        $this->httpClient = $this->httpClient->withHeaders([
            'Authorization' => 'Bearer '.$this->key,
            'apiKey' => $this->key,
        ]);
    }

    #[\Override]
    public function fileExists(string $path): bool
    {
        return $this->httpClient->head(sprintf('/object/%s/%s', $this->bucket, $path))->successful();
    }

    #[\Override]
    public function directoryExists(string $path): bool
    {
        $response = $this->httpClient->post('/object/list/'.$this->bucket, ['prefix' => $path, 'limit' => 1]);

        return $response->successful() && count($response->json()) >= 1;
    }

    #[\Override]
    public function write(string $path, string $contents, Config $config): void
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($contents) ?: 'application/octet-stream';

        $res = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->key,
            'apiKey' => $this->key,
            'x-upsert' => 'true',
            'Cache-Control' => '3600',
            'Content-Type' => $mimeType,
        ])
            ->baseUrl($this->endpoint)
            ->withBody($contents, $mimeType)
            ->post(sprintf('/object/%s/%s', $this->bucket, $path));

        if (!$res->successful() || $res->json('Id') === null) {
            throw UnableToWriteFile::atLocation($path, $res->body());
        }

        // Delete empty placeholder file if not specified directly
        $filename = pathinfo($path, PATHINFO_BASENAME);
        if ($filename !== self::EMPTY_FOLDER_PLACEHOLDER_NAME) {
            $dirname = pathinfo($path, PATHINFO_DIRNAME);
            $placeholderPath = $dirname === '.' ? self::EMPTY_FOLDER_PLACEHOLDER_NAME : $dirname.'/'.self::EMPTY_FOLDER_PLACEHOLDER_NAME;
            $this->delete($placeholderPath);
        }
    }

    /**
     * Write a resource stream to a file.
     *
     * @param resource $contents
     * @throws FilesystemException
     */
    #[\Override]
    public function writeStream(string $path, $contents, Config $config): void
    {
        if (!is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'Invalid resource provided');
        }

        $tempHandle = null;
        try {
            // Get the MIME type of the stream
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $tempHandle = tmpfile();
            if ($tempHandle === false) {
                throw UnableToWriteFile::atLocation($path, 'Unable to create temporary file');
            }

            // Copy a small sample to detect MIME type
            stream_copy_to_stream($contents, $tempHandle, 8192);
            fseek($tempHandle, 0);
            $mimeType = $finfo->buffer(fread($tempHandle, 8192)) ?: 'application/octet-stream';

            // Reset the original stream position
            fseek($contents, 0);

            // Create a PSR-7 stream from the resource
            $stream = Utils::streamFor($contents);

            $res = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->key,
                'apiKey' => $this->key,
                'x-upsert' => 'true',
                'Cache-Control' => '3600',
                'Content-Type' => $mimeType,
            ])
                ->baseUrl($this->endpoint)
                ->withBody($stream, $mimeType)
                ->post(sprintf('/object/%s/%s', $this->bucket, $path));

            if (!$res->successful() || $res->json('Id') === null) {
                throw UnableToWriteFile::atLocation($path, $res->body());
            }

            // Delete empty placeholder file if not specified directly
            $filename = pathinfo($path, PATHINFO_BASENAME);
            if ($filename !== self::EMPTY_FOLDER_PLACEHOLDER_NAME) {
                $dirname = pathinfo($path, PATHINFO_DIRNAME);
                $placeholderPath = $dirname === '.' ? self::EMPTY_FOLDER_PLACEHOLDER_NAME : $dirname.'/'.self::EMPTY_FOLDER_PLACEHOLDER_NAME;
                $this->delete($placeholderPath);
            }
        } finally {
            if ($tempHandle !== null) {
                fclose($tempHandle);
            }
        }
    }

    /** @throws FilesystemException */
    #[\Override]
    public function read(string $path): string
    {
        $response = $this->httpClient->get(sprintf('/object/%s/%s', $this->bucket, $path));

        if (!$response->successful()) {
            throw UnableToReadFile::fromLocation($path, $response->body());
        }

        return $response->body();
    }

    /**
     * @return resource
     * @throws FilesystemException
     */
    #[\Override]
    public function readStream(string $path)
    {
        $response = $this->httpClient->get(sprintf('/object/%s/%s', $this->bucket, $path));

        if (!$response->successful()) {
            throw UnableToReadFile::fromLocation($path, $response->body());
        }

        $stream = Utils::streamFor($response->body());
        $resource = $stream->detach();

        if (!is_resource($resource)) {
            throw UnableToReadFile::fromLocation($path, 'Failed to create stream');
        }

        return $resource;
    }

    #[\Override]
    public function delete(string $path): void
    {
        if (!$this->fileExists($path)) {
            return;
        }

        $response = $this->httpClient->delete('/object/'.$this->bucket, ['prefixes' => [$path]]);
        if (!$response->successful()) {
            throw UnableToDeleteFile::atLocation($path, $response->body());
        }
    }

    #[\Override]
    public function deleteDirectory(string $path): void
    {
        if (!$this->directoryExists($path)) {
            return;
        }

        /** @var list<StorageAttributes> $items */
        $items = iterator_to_array($this->listContents($path, true));
        $prefixes = array_map(fn(StorageAttributes $item): string => $item->path(), $items);

        if ($prefixes === []) {
            return;
        }

        $response = $this->httpClient->delete('/object/'.$this->bucket, ['prefixes' => $prefixes]);
        if (!$response->successful()) {
            throw UnableToDeleteDirectory::atLocation($path, $response->body());
        }
    }

    #[\Override]
    public function createDirectory(string $path, Config $config): void
    {
        if ($this->directoryExists($path)) {
            return;
        }

        try {
            $this->write($this->joinPaths($path, self::EMPTY_FOLDER_PLACEHOLDER_NAME), '', $config);
        } catch (UnableToWriteFile $unableToWriteFile) {
            throw UnableToCreateDirectory::atLocation($path, $unableToWriteFile->getMessage(), $unableToWriteFile);
        }
    }

    #[\Override]
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, "Driver doesn't support visibility");
    }

    #[\Override]
    public function visibility(string $path): FileAttributes
    {
        throw UnableToSetVisibility::atLocation($path, "Driver doesn't support visibility");
    }

    #[\Override]
    public function mimeType(string $path): FileAttributes
    {
        $item = $this->fetchFileMetadata($path);
        $mimeType = $item['metadata']['mimetype'] ?? null;

        // Strip charset for consistency
        if (is_string($mimeType) && str_contains($mimeType, ';')) {
            $mimeType = mb_trim(explode(';', $mimeType)[0]);
        }

        return new FileAttributes(path: $path, mimeType: $mimeType);
    }

    #[\Override]
    public function lastModified(string $path): FileAttributes
    {
        $item = $this->fetchFileMetadata($path);
        $lastModified = $item['metadata']['lastModified'] ?? null;
        $lastModified = is_string($lastModified) ? Carbon::parse($lastModified)->unix() : null;

        return new FileAttributes(path: $path, lastModified: $lastModified);
    }

    #[\Override]
    public function fileSize(string $path): FileAttributes
    {
        $item = $this->fetchFileMetadata($path);

        return new FileAttributes(path: $path, fileSize: Arr::get($item, 'metadata.size'));
    }

    /** @return \Generator<StorageAttributes> */
    #[\Override]
    public function listContents(string $path, bool $deep): iterable
    {
        $offset = 0;

        do {
            $response = $this->httpClient->post('/object/list/'.$this->bucket, [
                'prefix' => $path,
                'limit' => self::ITEMS_PER_PAGE,
                'offset' => $offset,
                'sortBy' => [
                    'column' => 'name',
                    'order' => 'asc',
                ],
            ]);

            if (!$response->successful()) {
                return;
            }

            /** @var null|list<array{name: string, metadata?: array<string, string>, ...}> $items */
            $items = $response->json();
            if (!is_array($items) || $items === []) {
                break;
            }

            foreach ($items as $item) {
                $itemPath = $item['name'];

                $itemMetadata = $item['metadata'] ?? [];

                if (Arr::get($item, 'id') === null) {
                    yield new DirectoryAttributes(
                        path: $this->joinPaths($path, $itemPath),
                        extraMetadata: $itemMetadata,
                    );

                    if ($deep) {
                        yield from $this->listContents($this->joinPaths($path, $itemPath), true);
                    }

                    continue;
                }

                $lastModifiedRaw = $itemMetadata['lastModified'] ?? null;
                $lastModifiedTs = is_string($lastModifiedRaw) ? Carbon::parse($lastModifiedRaw)->unix() : null;

                yield new FileAttributes(
                    path: $this->joinPaths($path, $itemPath),
                    fileSize: $itemMetadata['size'] ?? null,
                    lastModified: $lastModifiedTs,
                    mimeType: $itemMetadata['mimetype'] ?? null,
                    extraMetadata: Arr::except(
                        $itemMetadata,
                        ['mimetype', 'size', 'contentLength', 'lastModified']
                    ),
                );
            }

            $offset += self::ITEMS_PER_PAGE;
        } while (count($items) === self::ITEMS_PER_PAGE);
    }

    #[\Override]
    public function move(string $source, string $destination, Config $config): void
    {
        $res = $this->httpClient->post('/object/move', [
            'bucketId' => $this->bucket,
            'sourceKey' => $source,
            'destinationKey' => $destination,
        ]);

        if (!$res->successful()) {
            throw UnableToMoveFile::fromLocationTo(
                $source,
                $destination,
                new \RuntimeException($res->body())
            );
        }
    }

    #[\Override]
    public function copy(string $source, string $destination, Config $config): void
    {
        $res = $this->httpClient->post('/object/copy', [
            'bucketId' => $this->bucket,
            'sourceKey' => $source,
            'destinationKey' => $destination,
        ]);

        if (!$res->successful() || $res->json('Key') === null) {
            throw UnableToCopyFile::fromLocationTo(
                $source,
                $destination,
                new \RuntimeException($res->body())
            );
        }
    }

    /**
     * @api
     * @see \Illuminate\Filesystem\FilesystemAdapter::url
     * @throws FilesystemException
     */
    public function getUrl(string $path): string
    {
        $defaultUrlGeneration = $this->config->get('defaultUrlGeneration', $this->config->get('public', true) ? 'public' : 'signed');
        $defaultUrlGenerationOptions = $this->config->get('defaultUrlGenerationOptions', []);

        return match ($defaultUrlGeneration) {
            'public' => $this->getPublicUrl($path, $defaultUrlGenerationOptions),
            'signed' => $this->getSignedUrl($path, $defaultUrlGenerationOptions),
            default => throw new \InvalidArgumentException('Invalid value for "defaultUrlGeneration": '.$defaultUrlGeneration),
        };
    }

    /**
     * @internal
     * @param array{expiresIn?: int, transform?: string, download?: string, ...} $options
     * @throws UnableToGenerateTemporaryUrl
     */
    public function getSignedUrl(string $path, array $options = []): string
    {
        $options['expiresIn'] ??= $this->config->get('signedUrlExpires', 3600);
        $_queryString = '';

        $transformOptions = ['format' => 'origin'];
        if (isset($options['transform'])) {
            $transformOptions = array_merge($transformOptions, $options['transform']);
            unset($options['transform']);
        }

        if (Arr::get($options, 'download')) {
            $_queryString = '&download';
            unset($options['download']);
        }

        $response = $this->httpClient->post(sprintf('/object/sign/%s/%s', $this->bucket, $path), $options);
        if (!$response->successful() || $response->json('signedURL') === null) {
            throw new UnableToGenerateTemporaryUrl($response->body(), $path);
        }

        $url = $this->config->get('url', $this->endpoint);
        $signedUrl = $this->joinPaths($url, $response->json('signedURL'));

        $transformJson = json_encode($transformOptions);
        if ($transformJson === false) {
            throw new UnableToGenerateTemporaryUrl('Failed to encode transform options', $path);
        }

        $signedUrl .= (str_contains($signedUrl, '?') ? '&' : '?').'transform='.$transformJson;

        return urldecode($signedUrl.$_queryString);
    }

    /**
     * @internal
     * @param array{transform?: string, download?: bool, transform?: array<string, scalar>, ...} $options
     * @throws \RuntimeException
     */
    public function getPublicUrl(string $path, array $options = []): string
    {
        $public = $this->config->get('public', true);
        if (! is_bool($public) || $public === false) {
            throw new \InvalidArgumentException(sprintf('Your filesystem for the %s bucket is not configured to allow public URLs', $this->bucket));
        }

        $url = $this->config->get('url', $this->endpoint);
        $renderPath = 'object';

        $_queryParams = [];

        if (isset($options['transform'])) {
            $renderPath = 'render/image';
            $_queryParams['transform'] = json_encode($options['transform']);
        }

        if (Arr::get($options, 'download')) {
            $_queryParams['download'] = null;
        }

        $_queryString = '';
        if ($_queryParams !== []) {
            $_queryString = '?'.http_build_query($_queryParams);
        }

        return urldecode($this->joinPaths($url, $renderPath, '/public/', $this->bucket, $path).$_queryString);
    }

    /**
     * Laravel's magic method.
     * @param array<mixed> $options
     * @api
     * @see \Illuminate\Filesystem\FilesystemAdapter::temporaryUrl
     */
    public function getTemporaryUrl(string $path, \DateTimeInterface $expiration, array $options): string
    {
        return $this->getSignedUrl(
            $path,
            [...$options, 'expiresIn' => max(0, (int) now()->diffInSeconds($expiration))]
        );
    }

    /** @param string ...$paths */
    private function joinPaths(...$paths): string
    {
        return collect($paths)
            ->map(fn(string $path): string => str($path)->rtrim('/')->ltrim('/')->toString())
            ->filter()
            ->join('/');
    }

    /**
     * @return array<string, mixed>
     * @throws UnableToReadFile
     */
    private function fetchFileMetadata(string $path): array
    {
        $folderPath = pathinfo($path, \PATHINFO_DIRNAME);
        $folderPath = $folderPath === '.' ? '' : $folderPath;

        $filename = pathinfo($path, \PATHINFO_BASENAME);

        $response = $this->httpClient->post('/object/list/'.$this->bucket, [
            'prefix' => $folderPath,
            'limit' => self::ITEMS_PER_PAGE,
            'search' => $filename,
        ]);

        if (!$response->successful()) {
            throw UnableToReadFile::fromLocation($path, $response->body());
        }

        $parsedResponse = $response->json();
        if (! is_array($parsedResponse) || $parsedResponse === []) {
            throw UnableToReadFile::fromLocation($path, $response->body());
        }

        $item = collect($parsedResponse)->firstWhere('name', $filename);
        if (! is_array($item)) {
            throw UnableToReadFile::fromLocation($path, 'File not found');
        }

        return $item;
    }
}
