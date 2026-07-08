<?php

use YusufGenc34\FilamentApiForge\Http\Middleware\EnforceApiForgeRules;
use YusufGenc34\FilamentApiForge\Services\ResourceDiscoveryService;

beforeEach(function () {
    // Create the middleware with a real ResourceDiscoveryService from the container
    $this->middleware = new EnforceApiForgeRules(
        app(ResourceDiscoveryService::class)
    );
});

function callPrivateMethod(object $object, string $method, array $args = []): mixed
{
    $ref = new ReflectionMethod($object, $method);
    return $ref->invoke($object, ...$args);
}

it('matches exact IP address', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['192.168.1.100', ['192.168.1.100']]))->toBeTrue();
});

it('rejects non-matching IP', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['192.168.1.200', ['192.168.1.100']]))->toBeFalse();
});

it('matches IP in CIDR range /24', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['10.0.0.50', ['10.0.0.0/24']]))->toBeTrue();
});

it('rejects IP outside CIDR range', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['10.0.1.50', ['10.0.0.0/24']]))->toBeFalse();
});

it('matches IP in CIDR range /16', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['10.0.50.100', ['10.0.0.0/16']]))->toBeTrue();
});

it('matches IP in CIDR range /8', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['10.50.0.100', ['10.0.0.0/8']]))->toBeTrue();
});

it('matches wildcard IP pattern', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['192.168.1.50', ['192.168.1.*']]))->toBeTrue();
});

it('rejects mismatched wildcard IP pattern', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['192.168.2.50', ['192.168.1.*']]))->toBeFalse();
});

it('returns false for empty allowed list', function () {
    expect(callPrivateMethod($this->middleware, 'isIpAllowed', ['10.0.0.1', []]))->toBeFalse();
});

it('matches one of multiple allowed IPs', function () {
    expect(
        callPrivateMethod($this->middleware, 'isIpAllowed', ['172.16.0.50', ['192.168.1.1', '172.16.0.50', '10.0.0.0/8']])
    )->toBeTrue();
});

it('skips empty string rules', function () {
    expect(
        callPrivateMethod($this->middleware, 'isIpAllowed', ['192.168.1.50', ['', '192.168.1.50']])
    )->toBeTrue();
});

it('matches IPv6 addresses in CIDR check', function () {
    expect(callPrivateMethod($this->middleware, 'ipInCidr', ['::1', '::1/128']))->toBeTrue()
        ->and(callPrivateMethod($this->middleware, 'ipInCidr', ['2001:db8::5', '2001:db8::/32']))->toBeTrue()
        ->and(callPrivateMethod($this->middleware, 'ipInCidr', ['2001:db9::5', '2001:db8::/32']))->toBeFalse()
        ->and(callPrivateMethod($this->middleware, 'ipInCidr', ['192.168.1.1', '2001:db8::/32']))->toBeFalse();
});
