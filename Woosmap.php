<?php

declare(strict_types=1);

/*
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider\Woosmap;

use Geocoder\Collection;
use Geocoder\Exception\InvalidCredentials;
use Geocoder\Exception\InvalidServerResponse;
use Geocoder\Exception\QuotaExceeded;
use Geocoder\Exception\UnsupportedOperation;
use Geocoder\Model\AddressCollection;
use Geocoder\Model\AddressBuilder;
use Geocoder\Query\GeocodeQuery;
use Geocoder\Query\Query;
use Geocoder\Query\ReverseQuery;
use Geocoder\Http\Provider\AbstractHttpProvider;
use Geocoder\Provider\Woosmap\Model\WoosmapAddress;
use Geocoder\Provider\Provider;
use Http\Client\HttpClient;

final class Woosmap extends AbstractHttpProvider implements Provider
{
    /**
     * @var string
     */
    const GEOCODE_ENDPOINT_URL_SSL = 'https://api.woosmap.com/address/geocode/json?address=%s';

    /**
     * @var string
     */
    const REVERSE_ENDPOINT_URL_SSL = 'https://api.woosmap.com/address/geocode/json?latlng=%F,%F';

    /**
     * @var string|null
     */
    private $publicKey;

    /**
     * @var string|null
     */
    private $privateKey;

    /**
     * @param HttpClient  $client An HTTP adapter
     * @param string|null $publicKey Your Public API key (optional)
     * @param string|null $privateKey Your Private API Key (optional)
     */
    public function __construct(HttpClient $client, string $publicKey = null, string $privateKey = null)
    {
        parent::__construct($client);

        $this->publicKey = $publicKey;
        $this->privateKey = $privateKey;
    }

    public function geocodeQuery(GeocodeQuery $query): Collection
    {
        // Woosmap API returns invalid data if IP address given
        // This API doesn't handle IPs
        if (filter_var($query->getText(), FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The Woosmap provider does not support IP addresses, only street addresses.');
        }

        $url = sprintf(self::GEOCODE_ENDPOINT_URL_SSL, rawurlencode($query->getText()));

        return $this->geocodeOrReverseQuery($url, $query);
    }

    public function reverseQuery(ReverseQuery $query): Collection
    {
        $coordinate = $query->getCoordinates();
        $url = sprintf(self::REVERSE_ENDPOINT_URL_SSL, $coordinate->getLatitude(), $coordinate->getLongitude());

        return $this->geocodeOrReverseQuery($url, $query);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'woosmap';
    }

    private function geocodeOrReverseQuery(string $url, Query $query): Collection
    {
        if (null !== $components = $query->getData('components')) {
            $serializedComponents = is_string($components) ? $components : $this->serializeComponents($components);
            $url .= sprintf('&components=%s', urlencode($serializedComponents));
        }

        if (null !== $ccFormat = $query->getData('cc_format')) {
            $url .= sprintf('&cc_format=%s', $ccFormat);
        }

        return $this->fetchUrl($url, $query->getLocale(), $query->getLimit());
    }

    /**
     * @param string $url
     * @param string|null $locale
     *
     * @return string query with extra params
     */
    private function buildQuery(string $url, string $locale = null): string
    {
        if (null !== $locale) {
            $url = sprintf('%s&language=%s', $url, $locale);
        }

        if (null !== $this->publicKey) {
            $url = sprintf('%s&key=%s', $url, $this->publicKey);
        } elseif (null !== $this->privateKey) {
            $url = sprintf('%s&private_key=%s', $url, $this->privateKey);
        }

        return $url;
    }

    /**
     * @param string $url
     * @param string|null $locale
     * @param int    $limit
     *
     * @return AddressCollection
     *
     * @throws InvalidServerResponse
     * @throws InvalidCredentials
     */
    private function fetchUrl(string $url, string $locale = null, int $limit): AddressCollection
    {
        $url = $this->buildQuery($url, $locale);
        $content = $this->getUrlContents($url);
        $json = $this->validateResponse($url, $content);

        // no result
        if (!isset($json->results) || !count($json->results) || 'OK' !== $json->status) {
            return new AddressCollection([]);
        }

        $results = [];
        foreach ($json->results as $result) {
            $builder = new AddressBuilder($this->getName());

            $coordinates = $result->geometry->location;
            $builder->setCoordinates($coordinates->lat, $coordinates->lng);

            // update address components
            foreach ($result->address_components as $component) {
                foreach ($component->types as $type) {
                    $this->updateAddressComponent($builder, $type, $component);
                }
            }

            /** @var WoosmapAddress $address */
            $address = $builder->build(WoosmapAddress::class);
            $address = $address->withId($builder->getValue('id'));
            if (isset($result->geometry->location_type)) {
                $address = $address->withLocationType($result->geometry->location_type);
            }
            if (isset($result->types)) {
                $address = $address->withResultType($result->types);
            }
            if (isset($result->formatted_address)) {
                $address = $address->withFormattedAddress($result->formatted_address);
            }
            $address = $address->withStreetAddress($builder->getValue('street_address'));
            $address = $address->withIntersection($builder->getValue('intersection'));
            $address = $address->withPolitical($builder->getValue('political'));
            $address = $address->withColloquialArea($builder->getValue('colloquial_area'));
            $address = $address->withWard($builder->getValue('ward'));
            $address = $address->withNeighborhood($builder->getValue('neighborhood'));
            $address = $address->withPremise($builder->getValue('premise'));
            $address = $address->withSubpremise($builder->getValue('subpremise'));
            $address = $address->withNaturalFeature($builder->getValue('natural_feature'));
            $address = $address->withAirport($builder->getValue('airport'));
            $address = $address->withPark($builder->getValue('park'));
            $address = $address->withPointOfInterest($builder->getValue('point_of_interest'));
            $address = $address->withEstablishment($builder->getValue('establishment'));
            $address = $address->withSubLocalityLevels($builder->getValue('subLocalityLevel', []));
            $results[] = $address;

            if (count($results) >= $limit) {
                break;
            }
        }

        return new AddressCollection($results);
    }

    /**
     * Update current resultSet with given key/value.
     *
     * @param AddressBuilder $builder
     * @param string         $type    Component type
     * @param object         $values  The component values
     */
    private function updateAddressComponent(AddressBuilder $builder, string $type, $values)
    {
        switch ($type) {
            case 'postal_code':
                $builder->setPostalCode($values->long_name);

                break;

            case 'locality':
            case 'postal_town':
                $builder->setLocality($values->long_name);

                break;

            case 'administrative_area_level_1':
            case 'administrative_area_level_2':
            case 'administrative_area_level_3':
            case 'administrative_area_level_4':
            case 'administrative_area_level_5':
                $builder->addAdminLevel(intval(substr($type, -1)), $values->long_name, $values->short_name);

                break;

            case 'sublocality_level_1':
            case 'sublocality_level_2':
            case 'sublocality_level_3':
            case 'sublocality_level_4':
            case 'sublocality_level_5':
                $subLocalityLevel = $builder->getValue('subLocalityLevel', []);
                $subLocalityLevel[] = [
                    'level' => intval(substr($type, -1)),
                    'name' => $values->long_name,
                    'code' => $values->short_name,
                ];
                $builder->setValue('subLocalityLevel', $subLocalityLevel);

                break;

            case 'country':
                $builder->setCountry($values->long_name);
                $builder->setCountryCode($values->short_name);

                break;

            case 'street_number':
                $builder->setStreetNumber($values->long_name);

                break;

            case 'route':
                $builder->setStreetName($values->long_name);

                break;

            case 'sublocality':
                $builder->setSubLocality($values->long_name);

                break;

            case 'street_address':
            case 'intersection':
            case 'political':
            case 'colloquial_area':
            case 'ward':
            case 'neighborhood':
            case 'premise':
            case 'subpremise':
            case 'natural_feature':
            case 'airport':
            case 'park':
            case 'point_of_interest':
            case 'establishment':
                $builder->setValue($type, $values->long_name);

                break;

            default:
        }
    }

    /**
     * Serialize the component query parameter.
     *
     * @param array $components
     *
     * @return string
     */
    private function serializeComponents(array $components): string
    {
        return implode('|', array_map(function ($name, $value) {
            return sprintf('%s:%s', $name, $value);
        }, array_keys($components), $components));
    }

    /**
     * Decode the response content and validate it to make sure it does not have any errors.
     *
     * @param string $url
     * @param string $content
     *
     * @return mixed result form json_decode()
     *
     * @throws InvalidCredentials
     * @throws InvalidServerResponse
     */
    private function validateResponse(string $url, $content)
    {
        $json = json_decode($content);

        // API error
        if (!isset($json)) {
            throw InvalidServerResponse::create($url);
        }

        if ('REQUEST_DENIED' === $json->status && 'Incorrect authentication credentials. Please check or use a valid API Key' === $json->error_message) {
            throw new InvalidCredentials(sprintf('API key is invalid %s', $url));
        }

        if ('REQUEST_DENIED' === $json->status) {
            throw new InvalidServerResponse(
                sprintf('API access denied. Request: %s - Message: %s', $url, $json->error_message)
            );
        }

        return $json;
    }
}