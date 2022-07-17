<?php

declare(strict_types=1);

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\Woosmap\Tests;

use Geocoder\Exception\InvalidServerResponse;
use Geocoder\IntegrationTest\BaseTestCase;
use Geocoder\Location;
use Geocoder\Model\Address;
use Geocoder\Model\AddressCollection;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\ReverseQuery;
use Geocoder\Provider\Woosmap\Woosmap;
use Psr\Http\Message\RequestInterface;

class WoosmapTest extends BaseTestCase
{
    protected function getCacheDir()
    {
        if (isset($_SERVER['USE_CACHED_RESPONSES']) && true === $_SERVER['USE_CACHED_RESPONSES']) {
            return __DIR__.'/.cached_responses';
        }

        return null;
    }

    public function testGetName()
    {
        $provider = new Woosmap($this->getMockedHttpClient(), null, 'mock-api-key');
        $this->assertEquals('woosmap', $provider->getName());
    }

    public function testGeocodeWithLocalhostIPv4()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage('The Woosmap provider does not support IP addresses, only street addresses.');

        $provider = new Woosmap($this->getMockedHttpClient(), null, 'mock-api-key');
        $provider->geocodeQuery(GeocodeQuery::create('127.0.0.1'));
    }

    public function testGeocodeWithLocalhostIPv6()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage('The Woosmap provider does not support IP addresses, only street addresses.');

        $provider = new Woosmap($this->getMockedHttpClient(), null, 'mock-api-key');
        $provider->geocodeQuery(GeocodeQuery::create('::1'));
    }

    public function testGeocodeWithRealIp()
    {
        $this->expectException(\Geocoder\Exception\UnsupportedOperation::class);
        $this->expectExceptionMessage('The Woosmap provider does not support IP addresses, only street addresses.');

        $provider = $this->getWoosmapProvider();
        $provider->geocodeQuery(GeocodeQuery::create('74.200.247.59'));
    }

    public function testGeocodeWithQuotaExceeded()
    {
        $this->expectException(\Geocoder\Exception\QuotaExceeded::class);

        $provider = new Woosmap($this->getMockedHttpClient('{"message":"Daily quota has been reached"}', 429), null, 'mock-api-key');
        $provider->geocodeQuery(GeocodeQuery::create('10 avenue Gambetta, Paris, France'));
    }

    public function testGeocodeWithRealAddress()
    {
        if (!isset($_SERVER['WOOSMAP_PRIVATE_KEY'])) {
            $this->markTestSkipped('You need to configure the WOOSMAP_PRIVATE_KEY value in phpunit.xml');
        }

        $provider = new Woosmap($this->getHttpClient($_SERVER['WOOSMAP_PRIVATE_KEY']), null, $_SERVER['WOOSMAP_PRIVATE_KEY']);

        $results = $provider->geocodeQuery(GeocodeQuery::create('10 avenue Gambetta, Paris, France')->withLocale('fr-FR'));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertCount(1, $results);

        /** @var Location $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEqualsWithDelta(48.8630462, $result->getCoordinates()->getLatitude(), 0.001);
        $this->assertEqualsWithDelta(2.3882487, $result->getCoordinates()->getLongitude(), 0.001);
        $this->assertEquals(10, $result->getStreetNumber());
        $this->assertEquals('Avenue Gambetta', $result->getStreetName());
        $this->assertEquals(75020, $result->getPostalCode());
        $this->assertEquals('Paris', $result->getLocality());
        $this->assertEquals('France', $result->getCountry()->getName());
        $this->assertEquals('FRA', $result->getCountry()->getCode());

        // not provided
        $this->assertNull($result->getTimezone());
    }

    public function testReverse()
    {
        $this->expectException(\Geocoder\Exception\InvalidServerResponse::class);

        $provider = new Woosmap($this->getMockedHttpClient(), null, 'mock-api-key');
        $provider->reverseQuery(ReverseQuery::fromCoordinates(1, 2));
    }

    public function testReverseWithRealCoordinates()
    {
        $provider = $this->getWoosmapProvider();
        $query = ReverseQuery::fromCoordinates(48.8631507, 2.388911);
        $results = $provider->reverseQuery(ReverseQuery::fromCoordinates(48.8631507, 2.388911));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertCount(5, $results);

        /** @var Location $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEquals(8, $result->getStreetNumber());
        $this->assertEquals('Avenue Gambetta', $result->getStreetName());
        $this->assertEquals(75020, $result->getPostalCode());
        $this->assertEquals('Paris', $result->getLocality());
        $this->assertEquals('France', $result->getCountry()->getName());
        $this->assertEquals('FRA', $result->getCountry()->getCode());
    }

    public function testReverseWithRealCoordinatesAndLocale()
    {
        $provider = $this->getWoosmapProvider();
        $results = $provider->reverseQuery(ReverseQuery::fromCoordinates(48.8631507, 2.388911)->withLocale('fr-FR'));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertCount(5, $results);

        /** @var Location $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEquals(8, $result->getStreetNumber());
        $this->assertEquals('Avenue Gambetta', $result->getStreetName());
        $this->assertEquals(75020, $result->getPostalCode());
        $this->assertEquals('Paris', $result->getLocality());
        $this->assertEquals('France', $result->getCountry()->getName());
        $this->assertEquals('FRA', $result->getCountry()->getCode());
    }

    public function testGeocodeWithInvalidApiKey()
    {
        $this->expectException(\Geocoder\Exception\InvalidCredentials::class);
        $this->expectExceptionMessage('API key is invalid https://api.woosmap.com/address/geocode/json?address=10%20avenue%20Gambetta%2C%20Paris%2C%20France&limit=5&private_key=mock-api-key');

        $provider = new Woosmap($this->getMockedHttpClient('{"status": "REQUEST_DENIED", "results": [], "error_message": "Incorrect authentication credentials. Please check or use a valid API Key"}'), null, 'mock-api-key');
        $provider->geocodeQuery(GeocodeQuery::create('10 avenue Gambetta, Paris, France'));
    }

    public function testGeocodeWithRealValidApiKey()
    {
        $provider = $this->getWoosmapProvider();
        $results = $provider->geocodeQuery(GeocodeQuery::create('Columbia University, New York'));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertCount(5, $results);

        /** @var Location $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertNotNull($result->getCoordinates()->getLatitude());
        $this->assertNotNull($result->getCoordinates()->getLongitude());
        $this->assertEquals('New York', $result->getLocality());
    }

    public function testGeocodeWithComponentFiltering()
    {
        $provider = $this->getWoosmapProvider();
        $query = GeocodeQuery::create('Sankt Petri')
            ->withData('components', [
                'country' => 'SE',
            ])
        ->withLocale('sv');

        $results = $provider->geocodeQuery($query);

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertCount(1, $results);

        /** @var Location $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEquals('Malmö', $result->getLocality());
        $this->assertNotNull($result->getCountry());
        $this->assertEquals('SWE', $result->getCountry()->getCode());
    }

    public function testCorctlySerializesComponents()
    {
        $uri = '';

        $provider = new Woosmap(
            $this->getMockedHttpClientCallback(
                function (RequestInterface $request) use (&$uri) {
                    $uri = (string) $request->getUri();
                }
            ),
            null,
            'test-api-key'
        );

        $query = GeocodeQuery::create('address')->withData('components', [
            'country' => 'SE',
        ]);

        try {
            $provider->geocodeQuery($query);
        } catch (InvalidServerResponse $e) {
        }

        $this->assertEquals(
            'https://api.woosmap.com/address/geocode/json'.
            '?address=address'.
            '&components=country%3ASE&limit=5&private_key=test-api-key',
            $uri
        );
    }

    public function testCorrectlySetsComponents()
    {
        $uri = '';

        $provider = new Woosmap(
            $this->getMockedHttpClientCallback(
                function (RequestInterface $request) use (&$uri) {
                    $uri = (string) $request->getUri();
                }
            ),
            null,
            'test-api-key'
        );

        $query = GeocodeQuery::create('address')
            ->withData('components', 'country:SE');

        try {
            $provider->geocodeQuery($query);
        } catch (InvalidServerResponse $e) {
        }

        $this->assertEquals(
            'https://api.woosmap.com/address/geocode/json'.
            '?address=address'.
            '&components=country%3ASE&limit=5&private_key=test-api-key',
            $uri
        );
    }

    public function testGeocodePostalTown()
    {
        $provider = $this->getWoosmapProvider();
        $results = $provider->geocodeQuery(GeocodeQuery::create('18000, France'));

        $this->assertInstanceOf(AddressCollection::class, $results);
        $this->assertCount(1, $results);

        /** @var Location $result */
        $result = $results->first();
        $this->assertInstanceOf(Address::class, $result);
        $this->assertEquals('Bourges', $result->getLocality());
    }

    private function getWoosmapProvider(): Woosmap
    {
        if (!isset($_SERVER['WOOSMAP_PRIVATE_KEY'])) {
            $this->markTestSkipped('You need to configure the WOOSMAP_PRIVATE_KEY value in phpunit.xml');
        }

        $provider = new Woosmap(
            $this->getHttpClient($_SERVER['WOOSMAP_PRIVATE_KEY']),
            null,
            $_SERVER['WOOSMAP_PRIVATE_KEY']
        );

        return $provider;
    }
}
