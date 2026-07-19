# Share Plugin for NativePHP Mobile

Native share sheet for sharing URLs and files in NativePHP Mobile applications.

## Overview

The Share API provides cross-platform native share sheet functionality for sharing URLs, text, and files.

## Installation

```bash
composer require nativephp/mobile-share
```

## Usage

### Share a URL

#### PHP (Livewire/Blade)

```php
use Native\Mobile\Facades\Share;

// Share a URL with title and text
Share::url('Check this out!', 'Found this great article', 'https://example.com');
```

#### JavaScript (Vue/React/Inertia)

```js
import { Share } from '#nativephp';

// Share a URL
await Share.url('Check this out!', 'Found this great article', 'https://example.com');
```

### Share a File

#### PHP

```php
use Native\Mobile\Facades\Share;

// Share a file
Share::file('My Recording', 'Listen to this!', '/path/to/audio.m4a');

// Share just text (no file)
Share::file('Hello', 'This is my message', '');
```

#### JavaScript

```js
import { Share } from '#nativephp';

// Share a file
await Share.file('My Recording', 'Listen to this!', '/path/to/audio.m4a');

// Share just text
await Share.file('Hello', 'This is my message');
```

## Methods

### `url(string $title, string $text, string $url)`

Opens the native share sheet with a URL.

| Parameter | Type | Description |
|-----------|------|-------------|
| `title` | string | Share dialog title / subject |
| `text` | string | Text message to include with the URL |
| `url` | string | The URL to share |

### `file(string $title, string $text, string $filePath)`

Opens the native share sheet with a file or text.

| Parameter | Type | Description |
|-----------|------|-------------|
| `title` | string | Share dialog title / subject |
| `text` | string | Text message to share |
| `filePath` | string | Absolute path to file (optional) |

## Supported File Types

The share sheet automatically detects MIME types for common file formats:

**Audio:** m4a, aac, mp3, wav, ogg, flac
**Video:** mp4, m4v, mov, avi, mkv, webm
**Images:** jpg, jpeg, png, gif, webp
**Documents:** pdf, txt

## Platform Behavior

### Android
- Uses `Intent.ACTION_SEND` with `Intent.createChooser`
- Files are shared via `FileProvider` for security
- Temporary copies are made for files in app storage
- Old share temp files are automatically cleaned up

### iOS
- Uses `UIActivityViewController`
- Supports iPad popover presentation
- Files are shared directly via file URLs

## Testing

The plugin extends the NativePHP testing suite with share-specific helpers, so your app tests can assert share sheet activity without knowing any bridge internals:

```php
use Native\Mobile\Testing\Native;

it('shares the invite link', function () {
    Native::test(ShareSheet::class)
        ->tap('Share link')
        ->assertSharedUrl('https://example.com/invite/abc');
});

it('shares the exported report', function () {
    Native::test(ReportScreen::class)
        ->tap('Share report')
        ->assertSharedFile('/storage/app/report.pdf');
});

it('does not share anything before the button is tapped', function () {
    Native::test(ReportScreen::class)
        ->assertNothingShared();
});
```

### Helpers

- `assertShared()` — assert something was shared, a URL or a file.
- `assertSharedUrl(?string $url = null)` — assert a URL was shared, or exactly `$url` when given.
- `assertSharedFile(?string $filePath = null)` — assert a file was shared, or exactly `$filePath` when given.
- `assertNothingShared()` — assert neither `Share::url()` nor `Share::file()` was called.

The helpers are available on `Native::fakeBridge()` and chain directly off `Native::test(...)`. They register automatically while running tests (requires a core with a macroable FakeBridge; on older cores they simply don't register).
