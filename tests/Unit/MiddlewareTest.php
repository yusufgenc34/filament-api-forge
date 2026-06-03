<?php

use YusufGenc34\FilamentApiForge\Http\Middleware\ForceJsonResponse;
use Illuminate\Http\Request;

it('ForceJsonResponse sets Accept header to application/json', function () {
    $request = new Request();
    $middleware = new ForceJsonResponse();

    $middleware->handle($request, function (Request $req) {
        expect($req->headers->get('Accept'))->toBe('application/json');
        return response()->noContent();
    });
});

it('ForceJsonResponse overrides existing Accept header', function () {
    $request = new Request();
    $request->headers->set('Accept', 'text/html');

    $middleware = new ForceJsonResponse();

    $middleware->handle($request, function (Request $req) {
        expect($req->headers->get('Accept'))->toBe('application/json');
        return response()->noContent();
    });
});
