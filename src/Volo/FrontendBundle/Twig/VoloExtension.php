<?php

namespace Volo\FrontendBundle\Twig;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig_Extensions_Extension_Intl;
use Volo\FrontendBundle\Service\CartManagerService;

class VoloExtension extends Twig_Extensions_Extension_Intl
{
    /**
     * @var string
     */
    private $locale;

    /**
     * @var CartManagerService
     */
    private $cartManager;

    /**
     * @param string $locale
     * @param CartManagerService $cartManager
     */
    public function __construct($locale, CartManagerService $cartManager)
    {
        parent::__construct();

        $this->locale = $locale;
        $this->cartManager = $cartManager;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return array_merge(parent::getFilters(), [
            new \Twig_SimpleFilter('price', array($this, 'priceFilter')),
            new \Twig_SimpleFilter('formatTime', array($this, 'formatTime')),
            new \Twig_SimpleFilter('dayOfTheWeek', array($this, 'formatDayOfTheWeek')),
            new \Twig_SimpleFilter('formatOpeningDay', array($this, 'formatOpeningDay')),
            new \Twig_SimpleFilter('prepareLogoUrl', array($this, 'prepareLogoUrl')),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('get_configuration', array($this, 'getConfiguration')),
            new \Twig_SimpleFunction('get_default_cart_count', array($this, 'getDefaultCartCount')),
            new \Twig_SimpleFunction('get_default_cart_vendor_id', array($this, 'getDefaultCartVendorId')),
        ];
    }

    /**
     * @param float $number
     *
     * @return string
     * @throws \Twig_Error_Syntax
     */
    public function priceFilter($number)
    {
        $formatter = twig_get_number_formatter($this->locale, 'currency');
        $currency = $formatter->getTextAttribute(\NumberFormatter::CURRENCY_CODE);

        return $formatter->formatCurrency($number, $currency);
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        $formatter = twig_get_number_formatter($this->locale, 'currency');
        $currencyIso = $formatter->getTextAttribute(\NumberFormatter::CURRENCY_CODE);

        return [
            'currency' => [
                'currency_symbol_iso' => $currencyIso,
            ],
        ];
    }

    /**
     * @param SessionInterface $session
     *
     * @return int
     */
    public function getDefaultCartCount(SessionInterface $session)
    {
        $cart = $this->cartManager->getDefaultCart($session);

        return $cart === null ? 0 : array_sum(array_column($cart['products'], 'quantity'));
    }

    /**
     * @param SessionInterface $session
     *
     * @return string
     */
    public function getDefaultCartVendorId(SessionInterface $session)
    {
        $cart = $this->cartManager->getDefaultCart($session);

        return ($cart === null || !array_key_exists('vendor_id', $cart)) ? '' : $cart['vendor_id'];
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'volo_frontend.twig_extension';
    }

    /**
     * @param \DateTime $dateTime
     *
     * @return string
     */
    public function formatTime(\DateTime $dateTime)
    {
        $formatter = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::NONE, \IntlDateFormatter::SHORT);

        return $formatter->format($dateTime);
    }

    /**
     * @param int $dayIndex
     *
     * @return string
     */
    public function formatDayOfTheWeek($dayIndex)
    {
        $timestamp = strtotime("+{$dayIndex} day", strtotime('next Sunday'));
        $dateTime = new \DateTime();
        $dateTime->setTimestamp($timestamp);
        $formatter = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::SHORT);

        // @see http://userguide.icu-project.org/formatparse/datetime for formats
        $formatter->setPattern('eee');

        return $formatter->format($dateTime);
    }

    /**
     * @param \DateTime $day
     *
     * @return bool|string
     */
    public function formatOpeningDay(\DateTime $day)
    {
        $formatter = \IntlDateFormatter::create($this->locale, \IntlDateFormatter::GREGORIAN, \IntlDateFormatter::NONE);

        // @see http://userguide.icu-project.org/formatparse/datetime for formats
        $formatter->setPattern('eee MMM d');
        return $formatter->format($day);
    }

    /**
     * @param string $logoUrl
     * @param array $dimensions [w, h]
     *
     * @return string
     */
    public function prepareLogoUrl($logoUrl, $dimensions)
    {
        return sprintf($logoUrl, $dimensions[0], $dimensions[1]);
    }
}
