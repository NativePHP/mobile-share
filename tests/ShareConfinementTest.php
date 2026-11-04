<?php

/**
 * Share.File is exported in the JS bridge, so a web view page can call it
 * through /_native/api/call with a filePath of its choosing. Left open that is
 * a file-exfil primitive: a page can name .env or the SQLite database and have
 * the native share sheet vend it out (user-gated, but still a read-out). The
 * fix confines the shared path in the native implementations, which no PHP test
 * can execute — these assert the Swift and Kotlin twins keep the guard, so one
 * platform can't regress without the other.
 *
 * Run with: ./vendor/bin/pest
 */
function shareFunctionsSource(string $path): string
{
    // PLUGIN_PATH lets CI run this suite from an external Pest harness; locally
    // the suite sits inside the plugin as usual.
    $pluginPath = getenv('PLUGIN_PATH') ?: dirname(__DIR__);

    return file_get_contents($pluginPath.'/resources/'.$path);
}

$ios = 'ios/ShareFunctions.swift';
$android = 'android/ShareFunctions.kt';

it('confines the shared file path before the share sheet opens on both platforms', function (string $path, string $callMarker, string $shareSheet) {
    $source = shareFunctionsSource($path);

    // The confinement call runs exactly once — in Share.File, not Share.Url.
    expect(substr_count($source, $callMarker))->toBe(1);

    // And it runs before the file reaches the OS share sheet.
    $guardAt = strpos($source, $callMarker);
    $sheetAt = strpos($source, $shareSheet);
    expect($guardAt)->toBeLessThan($sheetAt, 'the share sheet opens before the confinement guard');
})->with([
    'iOS' => [$ios, 'ShareFunctions.confinementError(path: filePath)', 'shareItems.append(fileURL)'],
    'Android' => [$android, 'confinementError(filePath)', 'FileProvider.getUriForFile'],
]);

it('canonicalises the path before checking it, so .. and symlinks cannot escape', function (string $path, string $needle) {
    expect(shareFunctionsSource($path))->toContain($needle);
})->with([
    'iOS resolves symlinks' => [$ios, 'resolvingSymlinksInPath()'],
    'Android resolves via canonicalPath' => [$android, 'canonicalPath'],
]);

it('allows exactly the scratch and storage roots on both platforms', function (string $path, array $needles) {
    $source = shareFunctionsSource($path);

    foreach ($needles as $needle) {
        expect($source)->toContain($needle);
    }
})->with([
    'iOS temp + caches + storage' => [$ios, ['NSTemporaryDirectory()', 'cachesDirectory', 'storageAppRoot']],
    'Android cache + external cache + storage' => [$android, ['cacheDir', 'externalCacheDir', 'storageAppRoot']],
]);

it('anchors the storage root to the real storage_path, not the ephemeral app extract', function (string $path, string $needle) {
    // storage_path() on both platforms points outside the swappable app tree
    // (iOS: Application Support via LARAVEL_STORAGE_PATH; Android:
    // persisted_data/storage). Anchoring the root anywhere under the extracted
    // app would reject legitimate storage_path('app') shares. Pin the real
    // source of truth.
    expect(shareFunctionsSource($path))->toContain($needle);
})->with([
    'iOS reads LARAVEL_STORAGE_PATH' => [$ios, 'getenv("LARAVEL_STORAGE_PATH")'],
    'Android uses persisted_data/storage' => [$android, 'persisted_data/storage/app'],
]);

it('does not confine the URL branch, so Share.File of a URL still works', function (string $path, string $urlCheck) {
    // A filePath that is actually a URL (http/https/ftp) shares a string, not a
    // readable file — no exfil surface — so the guard must skip it. Pin that the
    // URL check still exists and is not short-circuited by the guard.
    expect(shareFunctionsSource($path))->toContain($urlCheck);
})->with([
    'iOS keeps isURL' => [$ios, 'if isURL(filePath)'],
    'Android guards on !isUrl' => [$android, '!isUrl(filePath)'],
]);
