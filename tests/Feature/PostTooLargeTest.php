<?php

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;

test('a PostTooLargeException renders as a friendly JSON error instead of a raw exception', function () {
    $request = Request::create('/api/admin/tracks', 'POST');
    $request->headers->set('Accept', 'application/json');

    $response = app(ExceptionHandler::class)->render($request, new PostTooLargeException('The POST data is too large.'));

    expect($response->getStatusCode())->toBe(413);
    expect(json_decode($response->getContent(), true)['message'])->toContain('too large');
});
