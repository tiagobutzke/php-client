<?php

namespace Foodpanda\ApiSdk\Entity\City;

use Foodpanda\ApiSdk\Entity\DataResultObject;

class CityResults extends DataResultObject
{
    public function __construct()
    {
        $this->items = new CitiesCollection();
    }

    /**
     * @return CitiesCollection|City[]
     */
    public function getItems()
    {
        return $this->items;
    }
}