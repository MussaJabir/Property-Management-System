<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/__test/client-ip', fn () => request()->ip());
});

it('reads the visitor IP that host nginx forwards through the Docker network', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
        ->withHeader('X-Forwarded-For', '41.59.17.211')
        ->get('/__test/client-ip')
        ->assertOk()
        ->assertSeeText('41.59.17.211');
});

it('ignores an address the visitor tries to spoof in front of the real one', function () {
    // nginx appends the real address to whatever the visitor sent.
    $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.1'])
        ->withHeader('X-Forwarded-For', '1.2.3.4, 41.59.17.211')
        ->get('/__test/client-ip')
        ->assertSeeText('41.59.17.211')
        ->assertDontSeeText('1.2.3.4');
});

it('ignores forwarded headers from a source that is not a trusted proxy', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
        ->withHeader('X-Forwarded-For', '41.59.17.211')
        ->get('/__test/client-ip')
        ->assertSeeText('8.8.8.8')
        ->assertDontSeeText('41.59.17.211');
});
