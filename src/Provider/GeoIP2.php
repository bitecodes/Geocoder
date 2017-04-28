<?php

/**
 * This file is part of the Geocoder package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Geocoder\Provider;

use Geocoder\Exception\ZeroResults;
use Geocoder\Model\Query\GeocodeQuery;
use Geocoder\Model\Query\ReverseQuery;
use GeoIp2\Exception\AddressNotFoundException;
use Geocoder\Adapter\GeoIP2Adapter;
use Geocoder\Exception\NoResult;
use Geocoder\Exception\UnsupportedOperation;

/**
 * @author Jens Wiese <jens@howtrueisfalse.de>
 */
final class GeoIP2 extends AbstractProvider implements LocaleAwareGeocoder, IpAddressGeocoder, Provider
{

    /**
     * @var GeoIP2Adapter
     */
    private $adapter;

    public function __construct(GeoIP2Adapter $adapter)
    {
        parent::__construct();

        $this->adapter = $adapter;
    }

    /**
     * {@inheritDoc}
     */
    public function geocodeQuery(GeocodeQuery $query)
    {
        $address = $query->getText();
        $locale = $query->getLocale() ?: 'en'; // Default to English
        if (!filter_var($address, FILTER_VALIDATE_IP)) {
            throw new UnsupportedOperation('The GeoIP2 provider does not support street addresses, only IP addresses.');
        }

        if ('127.0.0.1' === $address) {
            return $this->returnResults([ $this->getLocalhostDefaults() ]);
        }

        $result = json_decode($this->executeQuery($address));
        $adminLevels = [];

        if (isset($result->subdivisions) && is_array($result->subdivisions)) {
            foreach ($result->subdivisions as $i => $subdivision) {
                $name = (isset($subdivision->names->{$locale}) ? $subdivision->names->{$locale} : null);
                $code = (isset($subdivision->iso_code) ? $subdivision->iso_code : null);

                if (null !== $name || null !== $code) {
                    $adminLevels[] = ['name' => $name, 'code' => $code, 'level' => $i + 1];
                }
            }
        }

        return $this->returnResults([
            array_merge($this->getDefaults(), array(
                'countryCode' => (isset($result->country->iso_code) ? $result->country->iso_code : null),
                'country'     => (isset($result->country->names->{$locale}) ? $result->country->names->{$locale} : null),
                'locality'    => (isset($result->city->names->{$locale}) ? $result->city->names->{$locale} : null),
                'latitude'    => (isset($result->location->latitude) ? $result->location->latitude : null),
                'longitude'   => (isset($result->location->longitude) ? $result->location->longitude : null),
                'timezone'    => (isset($result->location->time_zone) ? $result->location->time_zone : null),
                'postalCode'  => (isset($result->postal->code) ? $result->postal->code : null),
                'adminLevels' => $adminLevels,
            ))
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function reverseQuery(ReverseQuery $query)
    {
        throw new UnsupportedOperation('The GeoIP2 provider is not able to do reverse geocoding.');
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return 'geoip2';
    }

    /**
     * @param string $address
     */
    private function executeQuery($address)
    {
        $uri = sprintf('file://geoip?%s', $address);

        try {
            $result = $this->adapter->getContent($uri);
        } catch (AddressNotFoundException $e) {
            throw new ZeroResults(sprintf('No results found for IP address "%s".', $address));
        }

        return $result;
    }
}
