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
import { share } from '#nativephp';

// Share a URL
await share.url('Check this out!', 'Found this great article', 'https://example.com');
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
import { share } from '#nativephp';

// Share a file
await share.file('My Recording', 'Listen to this!', '/path/to/audio.m4a');

// Share just text
await share.file('Hello', 'This is my message');
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

## Examples

### Share Recording After Capture

```php
use Native\Mobile\Facades\Microphone;
use Native\Mobile\Facades\Share;
use Native\Mobile\Events\Microphone\RecordingFinished;

#[OnNative(RecordingFinished::class)]
public function handleRecording($path, $duration)
{
    Share::file(
        'Voice Note',
        "Recording: {$duration}s",
        $path
    );
}
```

### Share Photo

```php
use Native\Mobile\Facades\Camera;
use Native\Mobile\Facades\Share;
use Native\Mobile\Events\Camera\PhotoTaken;

#[OnNative(PhotoTaken::class)]
public function handlePhoto($path)
{
    Share::file('Photo', 'Check out this photo!', $path);
}
```

## License

MIT
