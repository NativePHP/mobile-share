<?php

namespace Native\Mobile\Providers\Testing;

use Native\Mobile\Testing\FakeBridge;
use PHPUnit\Framework\Assert;

/**
 * Share test vocabulary for the NativePHP testing suite, registered as
 * FakeBridge macros so app tests read in share terms instead of raw bridge
 * method strings:
 *
 *     Native::test(ShareSheet::class)
 *         ->tap('Share link')
 *         ->assertSharedUrl('https://example.com/invite/abc');
 *
 * Registered by ShareServiceProvider when the app is running unit tests on
 * a core whose FakeBridge supports macros.
 */
class ShareMacros
{
    public static function register(): void
    {
        /** Assert something was shared — a URL, a file, or either. */
        FakeBridge::macro('assertShared', function () {
            $shared = [...$this->callsTo('Share.Url'), ...$this->callsTo('Share.File')];

            Assert::assertNotEmpty($shared, 'Expected the share sheet to be opened, but it was not.');

            return $this;
        });

        /** Assert a URL was shared — any URL, or exactly $url when given. */
        FakeBridge::macro('assertSharedUrl', function (?string $url = null) {
            if ($url === null) {
                return $this->assertCalled('Share.Url');
            }

            $shared = array_map(
                fn (array $call) => $call['params']['url'] ?? '',
                $this->callsTo('Share.Url')
            );

            Assert::assertContains(
                $url,
                $shared,
                "Expected [{$url}] to be shared. Shared: "
                    .($shared === [] ? '(nothing)' : '['.implode('], [', $shared).']')
            );

            return $this;
        });

        /** Assert a file was shared — any file, or exactly $filePath when given. */
        FakeBridge::macro('assertSharedFile', function (?string $filePath = null) {
            if ($filePath === null) {
                return $this->assertCalled('Share.File');
            }

            $shared = array_map(
                fn (array $call) => $call['params']['filePath'] ?? '',
                $this->callsTo('Share.File')
            );

            Assert::assertContains(
                $filePath,
                $shared,
                "Expected [{$filePath}] to be shared. Shared: "
                    .($shared === [] ? '(nothing)' : '['.implode('], [', $shared).']')
            );

            return $this;
        });

        /** Assert nothing was shared — no Share.Url or Share.File call. */
        FakeBridge::macro('assertNothingShared', function () {
            $shared = [...$this->callsTo('Share.Url'), ...$this->callsTo('Share.File')];

            Assert::assertEmpty(
                $shared,
                'Expected nothing to be shared, but the share sheet was opened.'
            );

            return $this;
        });
    }
}
