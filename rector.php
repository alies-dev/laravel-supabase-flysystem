<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    // uncomment to reach your current PHP version
    ->withPhpSets(php82: true)
    ->withSets([
        \Rector\Set\ValueObject\SetList::CODE_QUALITY,
        \Rector\Set\ValueObject\SetList::CODING_STYLE,
        \Rector\Set\ValueObject\SetList::TYPE_DECLARATION,
        \Rector\Set\ValueObject\SetList::PRIVATIZATION,
        \Rector\PHPUnit\Set\PHPUnitSetList::ANNOTATIONS_TO_ATTRIBUTES,
        \Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        \Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_110,
    ])
    ->withDeadCodeLevel(0);
