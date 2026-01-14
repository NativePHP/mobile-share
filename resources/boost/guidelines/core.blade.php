## nativephp/share

Native share sheet for distributing content through system share interfaces.

### PHP Usage (Livewire/Blade)

@verbatim
<code-snippet name="Sharing URLs" lang="php">
use Native\Mobile\Facades\Share;

Share::url(
    title: 'Check this out',
    text: 'I found something interesting',
    url: 'https://example.com/article'
);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Sharing Files" lang="php">
use Native\Mobile\Facades\Share;

Share::file(
    title: 'Share Document',
    text: 'Check out this PDF',
    filePath: '/path/to/document.pdf'
);
</code-snippet>
@endverbatim

### JavaScript Usage (Vue/React/Inertia)

@verbatim
<code-snippet name="Sharing in JavaScript" lang="javascript">
import { share } from '#nativephp';

// Share a URL
await share.url(
    'Check this out',
    'I found something interesting',
    'https://example.com/article'
);

// Share a file
await share.file(
    'Share Document',
    'Check out this PDF',
    '/path/to/document.pdf'
);
</code-snippet>
@endverbatim

### Methods

- `Share::url(string $title, string $text, string $url)` - Share a URL
- `Share::file(string $title, string $text, string $filePath)` - Share a file

### Key Notes

- No events are dispatched by the Share API
- No way to determine which app user selected or if cancelled
- File paths must be absolute and must exist before invocation
- Supports any file type (PDFs, images, videos, documents)