<?php

namespace Volo\FrontendBundle\Service;

use Doctrine\Common\Cache\Cache;
use Foodpanda\ApiSdk\Entity\Configuration\Configuration;
use Foodpanda\ApiSdk\Provider\ConfigurationProvider;

class ConfigurationService
{
    /**
     * @var Cache
     */
    protected $cache;

    /**
     * @var ConfigurationProvider
     */
    protected $configurationProvider;

    /**
     * @var int
     */
    protected $lifeTime;

    /**
     * @var string
     */
    private $cacheId = 'configuration';

    /**
     * @param Cache $cache
     * @param ConfigurationProvider $configurationProvider
     * @param int $lifeTime
     */
    public function __construct(Cache $cache, ConfigurationProvider $configurationProvider, $lifeTime)
    {
        $this->cache = $cache;
        $this->configurationProvider = $configurationProvider;
        $this->lifeTime = $lifeTime;
    }

    /**
     * Get the cached Configuration object
     *
     * @return Configuration
     */
    public function getConfiguration()
    {
        if (!$this->cache->contains($this->cacheId)) {
            $this->refreshConfiguration();
        }

        return $this->cache->fetch($this->cacheId);
    }

    /**
     * Fetch the configuration from the API and cache it
     */
    public function refreshConfiguration()
    {
        $config = $this->configurationProvider->findAll();
        $this->cache->save($this->cacheId, $config, $this->lifeTime);
    }
}