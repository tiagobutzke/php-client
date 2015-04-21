<?php

namespace Volo\EntityBundle\Entity\Topping;

use Volo\EntityBundle\Entity\DataObject;

class Topping extends DataObject
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */

    protected $name;

    /**
     * @var double
     */

    protected $price;

    /**
     * @var double
     */
    protected $price_before_discount;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param float $price
     */
    public function setPrice($price)
    {
        $this->price = $price;
    }

    /**
     * @return float
     */
    public function getPriceBeforeDiscount()
    {
        return $this->price_before_discount;
    }

    /**
     * @param float $price_before_discount
     */
    public function setPriceBeforeDiscount($price_before_discount)
    {
        $this->price_before_discount = $price_before_discount;
    }
}
