<?php

/**
 * The share test vocabulary this plugin registers on the FakeBridge
 * (assertShared / assertSharedUrl / assertSharedFile / assertNothingShared)
 * — the sugar app developers use instead of raw bridge method strings.
 *
 * Skipped on cores whose FakeBridge predates macro support.
 */

use Native\Mobile\Share;
use Native\Mobile\Testing\FakeBridge;
use Native\Mobile\Testing\Native;
use PHPUnit\Framework\AssertionFailedError;

uses(Tests\TestCase::class);

beforeEach(function () {
    if (! method_exists(FakeBridge::class, 'macro')) {
        $this->markTestSkipped('This core\'s FakeBridge does not support macros.');
    }

    $this->bridge = Native::fakeBridge();
});

describe('assertShared()', function () {
    it('passes when a URL was shared', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com');

        $this->bridge->assertShared();
    });

    it('passes when a file was shared', function () {
        (new Share)->file('Title', 'Message', '/storage/app/file.txt');

        $this->bridge->assertShared();
    });

    it('fails when nothing was shared', function () {
        expect(fn () => $this->bridge->assertShared())
            ->toThrow(AssertionFailedError::class);
    });
});

describe('assertSharedUrl()', function () {
    it('passes when any URL was shared', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com');

        $this->bridge->assertSharedUrl();
    });

    it('matches the exact shared url', function () {
        (new Share)->url('First', 'Text', 'https://nativephp.com/first');
        (new Share)->url('Second', 'Text', 'https://nativephp.com/second');

        $this->bridge->assertSharedUrl('https://nativephp.com/second');
    });

    it('fails when nothing was shared', function () {
        expect(fn () => $this->bridge->assertSharedUrl())
            ->toThrow(AssertionFailedError::class);
    });

    it('fails when a different url was shared, naming what was', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com/actual');

        expect(fn () => $this->bridge->assertSharedUrl('https://nativephp.com/expected'))
            ->toThrow(AssertionFailedError::class, 'https://nativephp.com/actual');
    });

    it('fails when only a file was shared', function () {
        (new Share)->file('Title', 'Message', '/storage/app/file.txt');

        expect(fn () => $this->bridge->assertSharedUrl())
            ->toThrow(AssertionFailedError::class);
    });
});

describe('assertSharedFile()', function () {
    it('passes when any file was shared', function () {
        (new Share)->file('Title', 'Message', '/storage/app/file.txt');

        $this->bridge->assertSharedFile();
    });

    it('matches the exact shared filePath', function () {
        (new Share)->file('First', 'Message', '/storage/app/first.txt');
        (new Share)->file('Second', 'Message', '/storage/app/second.txt');

        $this->bridge->assertSharedFile('/storage/app/second.txt');
    });

    it('fails when nothing was shared', function () {
        expect(fn () => $this->bridge->assertSharedFile())
            ->toThrow(AssertionFailedError::class);
    });

    it('fails when a different file was shared, naming what was', function () {
        (new Share)->file('Title', 'Message', '/storage/app/actual.txt');

        expect(fn () => $this->bridge->assertSharedFile('/storage/app/expected.txt'))
            ->toThrow(AssertionFailedError::class, '/storage/app/actual.txt');
    });

    it('fails when only a url was shared', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com');

        expect(fn () => $this->bridge->assertSharedFile())
            ->toThrow(AssertionFailedError::class);
    });
});

describe('assertNothingShared()', function () {
    it('passes when nothing was shared', function () {
        $this->bridge->assertNothingShared();
    });

    it('fails after a url share', function () {
        (new Share)->url('Title', 'Text', 'https://nativephp.com');

        expect(fn () => $this->bridge->assertNothingShared())
            ->toThrow(AssertionFailedError::class);
    });

    it('fails after a file share', function () {
        (new Share)->file('Title', 'Message', '/storage/app/file.txt');

        expect(fn () => $this->bridge->assertNothingShared())
            ->toThrow(AssertionFailedError::class);
    });
});
