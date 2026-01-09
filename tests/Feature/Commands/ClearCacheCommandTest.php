<?php

declare(strict_types=1);

describe('ClearCacheCommand', function () {
    it('clears the mandate cache', function () {
        $this->artisan('mandate:cache-clear')
            ->assertSuccessful();
    });
});
