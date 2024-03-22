<?php

use Carbon\Carbon;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Worksome\Exchange\ExchangeRateProviders\CurrencyGEOProvider;
use Worksome\Exchange\Support\Rates;

it('is able to make a real call to the API', function () {
    $client = new Factory();
    $fixerProvider = new CurrencyGEOProvider($client, getenv('CURRENCY_GEO_ACCESS_KEY'));
    $rates = $fixerProvider->getRates('EUR', currencies());

    expect($rates)->toBeInstanceOf(Rates::class);
})
    ->skip(getenv('CURRENCY_GEO_ACCESS_KEY') === false, 'No CURRENCY_GEO_ACCESS_KEY was defined.')
    ->group('integration');

it('makes a HTTP request to the correct endpoint', function () {
    $client = new Factory();
    $client->fake(['*' => [
        'timestamp' => now()->subDay()->timestamp,
        'rates' => [
            'EUR' => 1, // Even though this is an int, it should be converted to a float
            'GBP' => 2.5
        ],
    ]]);

    $fixerProvider = new CurrencyGEOProvider($client, 'password');
    $fixerProvider->getRates('EUR', currencies());

    $client->assertSent(function (Request $request) {
        return str_starts_with($request->url(), 'https://api.getgeoapi.com/v2/currency/convert');
    });
});

it('returns floats for all rates', function () {
    $client = new Factory();
    $client->fake(['*' => [
        'timestamp' => now()->subDay()->timestamp,
        'rates' => [
            'EUR' => 1, // Even though this is an int, it should be converted to a float
            'GBP' => 2.5
        ],
    ]]);

    $fixerProvider = new CurrencyGEOProvider($client, 'password');
    $rates = $fixerProvider->getRates('EUR', currencies());

    expect($rates->getRates())->each->toBeFloat();
});

it('sets the returned timestamp as the retrievedAt timestamp', function () {
    Carbon::setTestNow(now());

    $client = new Factory();
    $client->fake(['*' => [
        'timestamp' => now()->subDay()->timestamp,
        'rates' => [],
    ]]);

    $fixerProvider = new CurrencyGEOProvider($client, 'password');
    $rates = $fixerProvider->getRates('EUR', currencies());

    expect($rates->getRetrievedAt()->timestamp)->toBe(now()->subDay()->timestamp);
});

it('throws a RequestException if a 500 error occurs', function () {
    $client = new Factory();
    $client->fake(['*' => Create::promiseFor(new Response(500))]);

    $fixerProvider = new CurrencyGEOProvider($client, 'password');
    $fixerProvider->getRates('EUR', currencies());
})->throws(RequestException::class);
