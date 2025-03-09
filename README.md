# Laravel Supabase Flysystem

[![Total Downloads](https://img.shields.io/packagist/dt/alies-dev/laravel-supabase-flysystem.svg?style=flat-square)](https://packagist.org/packages/alies-dev/laravel-supabase-flysystem)
[![License](https://img.shields.io/packagist/l/alies-dev/laravel-supabase-flysystem.svg?style=flat-square)](https://packagist.org/packages/alies-dev/laravel-supabase-flysystem)
[![Type coverage](https://shepherd.dev/github/alies-dev/laravel-supabase-flysystem/coverage.svg)](https://shepherd.dev/github/alies-dev/laravel-supabase-flysystem)
[![Tests](https://github.com/alies-dev/laravel-supabase-flysystem/actions/workflows/phpunit.yml/badge.svg)](https://github.com/alies-dev/laravel-supabase-flysystem/actions/workflows/phpunit.yml)
[![codecov](https://codecov.io/gh/alies-dev/laravel-supabase-flysystem/graph/badge.svg?token=PJG9VED36T)](https://codecov.io/gh/alies-dev/laravel-supabase-flysystem)

A [Laravel Flysystem](https://laravel.com/docs/master/filesystem) adapter for [Supabase Storage](https://supabase.com/docs/guides/storage) with proper handling of signed URLs and transform options.

## Installation

You can install the package via composer:

```bash
composer require alies-dev/laravel-supabase-flysystem
```

The package will automatically register its service provider if you're using Laravel's package auto-discovery.

## Configuration

### Environment Variables

Add the following variables to your `.env` file:

```dotenv
SUPABASE_STORAGE_KEY=your-supabase-key
SUPABASE_STORAGE_BUCKET=your-bucket-name
SUPABASE_STORAGE_ENDPOINT=https://your-project-ref.supabase.co
```

### Full Configuration Options

Add the following config to the disk array in `config/filesystems.php`:

```php
'supabase' => [
    'driver' => 'supabase',
    'key' => env('SUPABASE_STORAGE_KEY'),
    'bucket' => env('SUPABASE_STORAGE_BUCKET'),
    'endpoint' => env('SUPABASE_STORAGE_ENDPOINT'),

    // Optional configuration
    'url' => env('SUPABASE_STORAGE_URL'), // Custom URL for public access
    'public' => env('SUPABASE_STORAGE_PUBLIC', false), // Set to true for public buckets

    'signed_url_ttl,' => 60 * 60 * 24, // Default TTL for signed URLs (1 day)
],
```

## Usage

### Basic Operations

```php
// Store a file
Storage::disk('supabase')->put('path/to/file.jpg', $contents);

// Check if a file exists
if (Storage::disk('supabase')->exists('path/to/file.jpg')) {
    // File exists
}

// Get file contents
$contents = Storage::disk('supabase')->get('path/to/file.jpg');

// Delete a file
Storage::disk('supabase')->delete('path/to/file.jpg');

// Copy a file
Storage::disk('supabase')->copy('original.jpg', 'copy.jpg');

// Move/rename a file
Storage::disk('supabase')->move('old-name.jpg', 'new-name.jpg');
```

### URL Generation

```php
// Get a URL (signed or public based on configuration)
$url = Storage::disk('supabase')->url('path/to/file.jpg');

// Get a signed URL with default options
$signedUrl = Storage::disk('supabase')->temporaryUrl(
    'path/to/file.jpg',
    now()->addHour()
);

// Get a signed URL with custom transform options
$transformedUrl = Storage::disk('supabase')->url('path/to/file.jpg', [
    'transform' => [
        'width' => 100,
        'height' => 100,
        'format' => 'webp',
    ]
]);

// Get a signed URL with a download option
$downloadUrl = Storage::disk('supabase')->url('path/to/file.jpg', [
    'download' => true,
]);
```

### Directory Operations

```php
// Create a directory
Storage::disk('supabase')->makeDirectory('path/to/directory');

// Delete a directory and all its contents
Storage::disk('supabase')->deleteDirectory('path/to/directory');

// List contents of a directory
$files = Storage::disk('supabase')->files('path/to/directory');
$directories = Storage::disk('supabase')->directories('path/to/directory');

// List all files recursively
$allFiles = Storage::disk('supabase')->allFiles('path/to/directory');
```

### Metadata Operations

```php
// Get file size
$size = Storage::disk('supabase')->size('path/to/file.jpg');

// Get file mime type
$mimeType = Storage::disk('supabase')->mimeType('path/to/file.jpg');

// Get file last modified timestamp
$lastModified = Storage::disk('supabase')->lastModified('path/to/file.jpg');
```

## Advanced Usage

### Image Transformations

Supabase Storage supports [image transformations](https://supabase.com/docs/guides/storage/serving/image-transformations) for images. You can specify transformation options when generating URLs:

```php
$url = Storage::disk('supabase')->url('path/to/image.jpg', [
    'transform' => [
        'width' => 300,
        'height' => 200,
        'resize' => 'cover', // 'cover', 'contain', or 'fill'
        'format' => 'webp',  // 'webp', 'png', 'jpg', etc.
        'quality' => 80,     // 1-100
    ]
]);
```

### Temporary URLs

Generate URLs that expire after a specific time:

```php
$url = Storage::disk('supabase')->temporaryUrl(
    'path/to/file.jpg',
    now()->addMinutes(30), // Expires in 30 minutes
    [
        'transform' => [
            'width' => 100,
            'height' => 100,
        ],
        'download' => true, // Force download
    ]
);
```

## Testing

The package includes a comprehensive test suite that covers all major functionality:

```bash
composer test
```

All tests use Laravel's HTTP client fakes to mock Supabase API responses, ensuring no actual API calls are made during testing.

## Changelog

Please refer to [GitHub Releases](https://github.com/alies-dev/laravel-supabase-flysystem/releases) for the changelog.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- [Quix Labs](https://github.com/quix-labs) - For providing a great starting point for this package
- [Alies](https://github.com/alies-dev)
- [All Contributors](../../contributors)
