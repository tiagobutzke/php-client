<?php

namespace Volo\FrontendBundle\Controller;

use Foodpanda\ApiSdk\Entity\Cart\AbstractLocation;
use Foodpanda\ApiSdk\Entity\Vendor\Vendor;
use Foodpanda\ApiSdk\Exception\EntityNotFoundException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Foodpanda\ApiSdk\Entity\Vendor\VendorsCollection;

class LocationController extends Controller
{
    /**
     * @Route(
     *      "/city/{cityUrlKey}",
     *      name="volo_location_search_vendors_by_city",
     *      requirements={"cityUrlKey"="[a-z-]+"}
     * )
     * @Route(
     *      "/restaurants/lat/{latitude}/lng/{longitude}/plz/{postcode}/city/{city}/address/{address}",
     *      name="volo_location_search_vendors_by_gps",
     *      options={"expose"=true},
     *      requirements={
     *          "latitude"="-?(\d*[.])?\d+",
     *          "longitude"="-?(\d*[.])?\d+",
     *          "postcode"="\d+"
     *      }
     * )
     * @Method({"GET"})
     * @Template("VoloFrontendBundle:Location:vendors_list.html.twig")
     * @ParamConverter("location", converter="user_location_converter")
     *
     * @param AbstractLocation $location
     * @param array $formattedLocation
     * @param int $cityId
     *
     * @return array
     */
    public function locationAction(AbstractLocation $location, array $formattedLocation, $cityId)
    {
        $vendors = $this->get('volo_frontend.provider.vendor')->findVendorsByLocation($location);

        list($openVendors, $closedVendorsWithPreorder) = $vendors->getItems()->partition(function ($key, Vendor $vendor) {
            return $vendor->getMetadata()->getAvailableIn() === null;
        });

        return [
            'gpsSearch' => $location->getLocationType() === 'polygon',
            'formattedLocation' => $formattedLocation,
            'openVendors' => $openVendors,
            'closedVendors' => $closedVendorsWithPreorder,
            'cityId' => $cityId
        ];
    }
}
