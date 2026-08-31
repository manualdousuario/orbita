<?php

declare(strict_types=1);

/**
 * Tests Exif::sanitize() encoding filters and the tag denylist.
 */

namespace Tests\Unit;

use App\Support\Exif;

/**
 * A realistic geotagged EXIF block from a phone camera.
 */
function geotaggedBlock(): array
{
    return [
        'FileName' => 'IMG_4021.HEIC',
        'Make' => 'Apple',
        'Model' => 'iPhone 15 Pro',
        'DateTimeOriginal' => '2026:07:14 18:22:41',
        'COMPUTED' => [
            'Width' => 4032,
            'Height' => 3024,
        ],
        'GPS' => [
            'GPSLatitudeRef' => 'S',
            'GPSLatitude' => ['23/1', '33/1', '4521/100'],
            'GPSLongitudeRef' => 'W',
            'GPSLongitude' => ['46/1', '38/1', '1234/100'],
            'GPSAltitude' => '760/1',
            'GPSDateStamp' => '2026:07:14',
        ],
        'GPSLatitude' => ['23/1', '33/1', '4521/100'],
        'GPSLongitude' => ['46/1', '38/1', '1234/100'],
        'BodySerialNumber' => 'F2LX93KJQ1',
        'LensSerialNumber' => '0000c1a2b3',
    ];
}

it('gps section and coordinates never survive sanitising', function () {
    $clean = Exif::sanitize(geotaggedBlock());

    expect($clean)->not->toHaveKey('GPS');
    expect($clean)->not->toHaveKey('GPSLatitude');
    expect($clean)->not->toHaveKey('GPSLongitude');

    // Belt and braces: no coordinate value anywhere in the tree, at any depth.
    $flat = json_encode($clean);
    expect($flat)->not->toContain('4521/100');
    expect($flat)->not->toContain('1234/100');
});

it('device serial numbers are dropped', function () {
    $clean = Exif::sanitize(geotaggedBlock());

    expect($clean)->not->toHaveKey('BodySerialNumber');
    expect($clean)->not->toHaveKey('LensSerialNumber');
    expect(json_encode($clean))->not->toContain('F2LX93KJQ1');
});

it('denylist is case insensitive and covers the whole gps prefix', function () {
    $clean = Exif::sanitize([
        'gpslatitude' => ['1/1'],
        'GpsLongitude' => ['2/1'],
        'GPSImgDirection' => '180/1',
        'GPSSomethingInventedLater' => 'x',
        'Make' => 'Canon',
    ]);

    expect($clean)->toBe(['Make' => 'Canon']);
});

it('harmless technical tags are kept', function () {
    $clean = Exif::sanitize(geotaggedBlock());

    expect($clean['Make'])->toBe('Apple')
        ->and($clean['Model'])->toBe('iPhone 15 Pro')
        ->and($clean['DateTimeOriginal'])->toBe('2026:07:14 18:22:41')
        ->and($clean['COMPUTED']['Width'])->toBe(4032);
});

/**
 * Binary tags must not reach json_encode().
 */
it('non utf8 and oversized values are still dropped', function () {
    $clean = Exif::sanitize([
        'Make' => 'Nikon',
        'MakerNote' => "\xff\xfe\x00binary\x80\x81",
        'Padding' => str_repeat('a', 600),
        'THUMBNAIL' => ['THUMBNAIL' => "\xff\xd8\xff\xe0binary"],
    ]);

    expect($clean)->toBe(['Make' => 'Nikon']);
    expect(json_encode($clean))->toBeString();
});

it('an entirely denied block sanitises to an empty array', function () {
    expect(Exif::sanitize([
        'GPSLatitude' => ['23/1'],
        'SerialNumber' => 'ABC123',
    ]))->toBe([]);
});
