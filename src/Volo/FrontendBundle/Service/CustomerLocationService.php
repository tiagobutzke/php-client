<?php

namespace Volo\FrontendBundle\Service;

use Doctrine\Common\Cache\Cache;
use Volo\FrontendBundle\Exception\Location\MissingKeysException;

class CustomerLocationService
{
    /**
     * @var Cache
     */
    protected $cache;

    const SESSION_KEY_PREFIX = 'customer_locations:';

    const KEY_LAT = 'latitude';
    const KEY_LNG = 'longitude';
    const KEY_POSTAL_INDEX = 'post_code';

    /**
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param string $sessionId
     * @param array $location
     */
    public function set($sessionId, array $location)
    {
        $this->validate($location);
        $this->cache->save($this->createSessionKey($sessionId), $location);
    }

    /**
     * @param string $sessionId
     *
     * @return array
     */
    public function get($sessionId)
    {
        $value = $this->cache->fetch($this->createSessionKey($sessionId));

        return $value;
    }

    /**
     * @param float $lat
     * @param float $lng
     * @param string $postalIndex
     *
     * @return array
     */
    public function create($lat, $lng, $postalIndex)
    {
        return [
            static::KEY_LAT => $lat,
            static::KEY_LNG => $lng,
            static::KEY_POSTAL_INDEX => $postalIndex,
        ];
    }

    /**
     * @param array $location
     *
     * @throws MissingKeysException
     */
    protected function validate(array $location)
    {
        $missingKeys = array_diff(
            [static::KEY_LAT, static::KEY_LNG, static::KEY_POSTAL_INDEX],
            array_keys(array_filter($location))
        );

        if (count($missingKeys) > 0) {
            throw new MissingKeysException($missingKeys);
        }
    }

    /**
     * @param string $sessionId
     *
     * @return string
     */
    protected function createSessionKey($sessionId)
    {
        return sprintf('%s:%s', static::SESSION_KEY_PREFIX, $sessionId);
    }
}
