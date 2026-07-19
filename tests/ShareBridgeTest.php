<?php

/**
 * Contract tests for the Share facade (which ships in nativephp/mobile)
 * against THIS plugin's bridge functions, driven through the NativePHP
 * FakeBridge. They pin the PHP → native contract: calling the facade fires
 * the right bridge function with the right payload. Requires
 * nativephp/mobile, so this file is bound to the Testbench TestCase.
 *
 * Run with: ./test-plugins.sh --element share
 */

use Native\Mobile\Share;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();
});

describe('url()', function () {
    it('fires Share.Url with title, text and url keys', function () {
        (new Share)->url('Check this out', 'You have to see this', 'https://nativephp.com');

        $this->bridge->assertCalled('Share.Url', function (array $p) {
            expect($p)->toHaveKeys(['title', 'text', 'url']);
            expect($p['title'])->toBe('Check this out');
            expect($p['text'])->toBe('You have to see this');
            expect($p['url'])->toBe('https://nativephp.com');

            return true;
        });
    });

    it('does not send a message or filePath key', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com');

        $this->bridge->assertCalled('Share.Url', function (array $p) {
            expect($p)->not->toHaveKey('message');
            expect($p)->not->toHaveKey('filePath');

            return true;
        });
    });

    it('passes each argument through positionally without transposing them', function () {
        (new Share)->url('the-title', 'the-text', 'the-url');

        $this->bridge->assertCalled('Share.Url', function (array $p) {
            expect($p['title'])->toBe('the-title');
            expect($p['text'])->toBe('the-text');
            expect($p['url'])->toBe('the-url');

            return true;
        });
    });

    it('preserves multiline text with quotes verbatim', function () {
        $snippet = "<native:text class=\"font-bold\">\n    Hello 'world'\n</native:text>";
        (new Share)->url('Title', $snippet, 'https://nativephp.com');

        $this->bridge->assertCalled('Share.Url', function (array $p) use ($snippet) {
            expect($p['text'])->toBe($snippet);

            return true;
        });
    });

    it('preserves special characters and symbols verbatim in the title', function () {
        $title = 'Save 50% off — "limited time" <offer> & more!';
        (new Share)->url($title, 'Text', 'https://nativephp.com');

        $this->bridge->assertCalled('Share.Url', function (array $p) use ($title) {
            expect($p['title'])->toBe($title);

            return true;
        });
    });

    it('preserves unicode characters and emoji verbatim', function () {
        $text = 'こんにちは世界 🌍 — café résumé naïve';
        (new Share)->url('Title', $text, 'https://nativephp.com');

        $this->bridge->assertCalled('Share.Url', function (array $p) use ($text) {
            expect($p['text'])->toBe($text);

            return true;
        });
    });

    it('preserves query strings and fragments in the url verbatim', function () {
        $url = 'https://nativephp.com/docs?ref=share&utm_source=app#install';
        (new Share)->url('Title', 'Text', $url);

        $this->bridge->assertCalled('Share.Url', function (array $p) use ($url) {
            expect($p['url'])->toBe($url);

            return true;
        });
    });

    it('passes through empty strings for title and text', function () {
        (new Share)->url('', '', 'https://nativephp.com');

        $this->bridge->assertCalled('Share.Url', function (array $p) {
            expect($p['title'])->toBe('');
            expect($p['text'])->toBe('');
            expect($p['url'])->toBe('https://nativephp.com');

            return true;
        });
    });

    it('returns void', function () {
        $result = (new Share)->url('Title', 'Text', 'https://nativephp.com');

        expect($result)->toBeNull();
    });

    it('fires Share.Url exactly once per call', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com');

        $this->bridge->assertCalledTimes('Share.Url', 1);
    });

    it('does not fire Share.File', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com');

        $this->bridge->assertNotCalled('Share.File');
    });

    it('records each call independently across multiple invocations', function () {
        (new Share)->url('First', 'First text', 'https://nativephp.com/first');
        (new Share)->url('Second', 'Second text', 'https://nativephp.com/second');

        $calls = $this->bridge->callsTo('Share.Url');
        expect($calls)->toHaveCount(2);
        expect($calls[0]['params']['title'])->toBe('First');
        expect($calls[0]['params']['url'])->toBe('https://nativephp.com/first');
        expect($calls[1]['params']['title'])->toBe('Second');
        expect($calls[1]['params']['url'])->toBe('https://nativephp.com/second');
    });
});

describe('file()', function () {
    it('fires Share.File with title, message and filePath keys', function () {
        (new Share)->file('Vacation photo', 'Look at this!', '/storage/app/photo.jpg');

        $this->bridge->assertCalled('Share.File', function (array $p) {
            expect($p)->toHaveKeys(['title', 'message', 'filePath']);
            expect($p['title'])->toBe('Vacation photo');
            expect($p['message'])->toBe('Look at this!');
            expect($p['filePath'])->toBe('/storage/app/photo.jpg');

            return true;
        });
    });

    it('sends the text argument under the message key, not text', function () {
        (new Share)->file('Title', 'the shared text', '/storage/app/file.pdf');

        $this->bridge->assertCalled('Share.File', function (array $p) {
            expect($p)->not->toHaveKey('text');
            expect($p['message'])->toBe('the shared text');

            return true;
        });
    });

    it('does not send a url key', function () {
        (new Share)->file('Title', 'Text', '/storage/app/file.pdf');

        $this->bridge->assertCalled('Share.File', function (array $p) {
            expect($p)->not->toHaveKey('url');

            return true;
        });
    });

    it('passes each argument through positionally without transposing them', function () {
        (new Share)->file('the-title', 'the-message', '/the/file/path.txt');

        $this->bridge->assertCalled('Share.File', function (array $p) {
            expect($p['title'])->toBe('the-title');
            expect($p['message'])->toBe('the-message');
            expect($p['filePath'])->toBe('/the/file/path.txt');

            return true;
        });
    });

    it('preserves multiline messages with quotes verbatim', function () {
        $snippet = "<native:text class=\"font-bold\">\n    Hello 'world'\n</native:text>";
        (new Share)->file('Title', $snippet, '/storage/app/file.txt');

        $this->bridge->assertCalled('Share.File', function (array $p) use ($snippet) {
            expect($p['message'])->toBe($snippet);

            return true;
        });
    });

    it('preserves special characters and symbols verbatim in the title', function () {
        $title = 'Save 50% off — "limited time" <offer> & more!';
        (new Share)->file($title, 'Message', '/storage/app/file.txt');

        $this->bridge->assertCalled('Share.File', function (array $p) use ($title) {
            expect($p['title'])->toBe($title);

            return true;
        });
    });

    it('preserves unicode characters and emoji verbatim', function () {
        $message = 'こんにちは世界 🌍 — café résumé naïve';
        (new Share)->file('Title', $message, '/storage/app/file.txt');

        $this->bridge->assertCalled('Share.File', function (array $p) use ($message) {
            expect($p['message'])->toBe($message);

            return true;
        });
    });

    it('preserves file paths with spaces and special characters verbatim', function () {
        $filePath = '/storage/app/My Documents/report (final) [v2].pdf';
        (new Share)->file('Title', 'Message', $filePath);

        $this->bridge->assertCalled('Share.File', function (array $p) use ($filePath) {
            expect($p['filePath'])->toBe($filePath);

            return true;
        });
    });

    it('passes through empty strings for title and message', function () {
        (new Share)->file('', '', '/storage/app/file.txt');

        $this->bridge->assertCalled('Share.File', function (array $p) {
            expect($p['title'])->toBe('');
            expect($p['message'])->toBe('');
            expect($p['filePath'])->toBe('/storage/app/file.txt');

            return true;
        });
    });

    it('returns void', function () {
        $result = (new Share)->file('Title', 'Message', '/storage/app/file.txt');

        expect($result)->toBeNull();
    });

    it('fires Share.File exactly once per call', function () {
        (new Share)->file('Title', 'Message', '/storage/app/file.txt');

        $this->bridge->assertCalledTimes('Share.File', 1);
    });

    it('does not fire Share.Url', function () {
        (new Share)->file('Title', 'Message', '/storage/app/file.txt');

        $this->bridge->assertNotCalled('Share.Url');
    });

    it('records each call independently across multiple invocations', function () {
        (new Share)->file('First', 'First message', '/storage/app/first.txt');
        (new Share)->file('Second', 'Second message', '/storage/app/second.txt');

        $calls = $this->bridge->callsTo('Share.File');
        expect($calls)->toHaveCount(2);
        expect($calls[0]['params']['title'])->toBe('First');
        expect($calls[0]['params']['filePath'])->toBe('/storage/app/first.txt');
        expect($calls[1]['params']['title'])->toBe('Second');
        expect($calls[1]['params']['filePath'])->toBe('/storage/app/second.txt');
    });
});

describe('url() and file() together', function () {
    it('fires nothing before either method is called', function () {
        $this->bridge->assertNothingCalled();
    });

    it('calls Share.Url then Share.File in the order invoked', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com');
        (new Share)->file('Title', 'Message', '/storage/app/file.txt');

        $this->bridge->assertCallOrder(['Share.Url', 'Share.File']);
    });

    it('keeps the two bridge functions and their payloads independent', function () {
        (new Share)->url('URL title', 'URL text', 'https://nativephp.com');
        (new Share)->file('File title', 'File message', '/storage/app/file.txt');

        $this->bridge->assertCalled('Share.Url', function (array $p) {
            expect($p['title'])->toBe('URL title');
            expect($p)->not->toHaveKey('filePath');

            return true;
        });

        $this->bridge->assertCalled('Share.File', function (array $p) {
            expect($p['title'])->toBe('File title');
            expect($p)->not->toHaveKey('url');

            return true;
        });
    });
});
