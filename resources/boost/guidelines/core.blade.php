## nativephp/share

Native share sheet for sharing URLs and files in NativePHP Mobile applications.

### Installation

```bash
composer require nativephp/share
php artisan native:plugin:register nativephp/share
```

### PHP Usage (Livewire/Blade)

Use the `Share` facade:

@verbatim
<code-snippet name="Sharing URLs" lang="php">
use Native\Mobile\Facades\Share;

// Share a URL with title and text
Share::url('Check this out!', 'Found this great article', 'https://example.com');
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Sharing Files" lang="php">
use Native\Mobile\Facades\Share;

// Share a file
Share::file('My Recording', 'Listen to this!', '/path/to/audio.m4a');

// Share just text (no file)
Share::file('Hello', 'This is my message', '');
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Sharing After Camera/Microphone Capture" lang="php">
use Native\Mobile\Facades\Share;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Microphone\MicrophoneRecorded;

#[OnNative(PhotoTaken::class)]
public function handlePhoto($path)
{
    Share::file('Photo', 'Check out this photo!', $path);
}

#[OnNative(MicrophoneRecorded::class)]
public function handleRecording($path, $mimeType, $id)
{
    Share::file('Voice Note', 'Listen to this recording', $path);
}
</code-snippet>
@endverbatim

### JavaScript Usage

@verbatim
<code-snippet name="Sharing in JavaScript" lang="js">
import { share } from '#nativephp';

// Share a URL
await share.url('Check this out!', 'Found this great article', 'https://example.com');

// Share a file
await share.file('My Recording', 'Listen to this!', '/path/to/audio.m4a');

// Share just text
await share.file('Hello', 'This is my message');
</code-snippet>
@endverbatim

### Available Methods

- `Share::url(string $title, string $text, string $url)` - Share a URL
- `Share::file(string $title, string $text, string $filePath)` - Share a file or text

### Method Parameters

#### url()
| Parameter | Type | Description |
|-----------|------|-------------|
| `title` | string | Share dialog title / subject |
| `text` | string | Text message to include with the URL |
| `url` | string | The URL to share |

#### file()
| Parameter | Type | Description |
|-----------|------|-------------|
| `title` | string | Share dialog title / subject |
| `text` | string | Text message to share |
| `filePath` | string | Absolute path to file (optional, empty for text-only) |

### Supported File Types

**Audio:** m4a, aac, mp3, wav, ogg, flac
**Video:** mp4, m4v, mov, avi, mkv, webm
**Images:** jpg, jpeg, png, gif, webp
**Documents:** pdf, txt

### Platform Details

- **Android**: Uses `Intent.ACTION_SEND` with `Intent.createChooser`, files shared via `FileProvider`
- **iOS**: Uses `UIActivityViewController`, supports iPad popover presentation