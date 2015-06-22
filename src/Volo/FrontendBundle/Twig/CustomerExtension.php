<?php

namespace Volo\FrontendBundle\Twig;

use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\SecurityContextInterface;

class CustomerExtension extends \Twig_Extension
{
    /**
     * @var SecurityContextInterface
     */
    protected $securityContext;

    /**
     * @var AuthorizationChecker
     */
    protected $authorizationChecker;

    public function __construct(SecurityContextInterface $securityContext, AuthorizationChecker $authorizationChecker)
    {
        $this->securityContext = $securityContext;
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * @return array
     */
    public function getFunctions()
    {
        return array(
            new \Twig_SimpleFunction('is_customer_authenticated_fully', [$this, 'isCustomerAuthenticatedFully'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('get_authenticated_customer', [$this, 'getAuthenticatedCustomer'], ['is_safe' => ['html']]),
        );
    }

    /**
     * @return bool
     */
    public function isCustomerAuthenticatedFully()
    {
        return count($this->securityContext->getToken()->getRoles()) > 0
            && $this->authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY');
    }

    /**
     * @return array
     */
    public function getAuthenticatedCustomer()
    {
        $attributes = $this->securityContext->getToken()->getAttributes();
        if (isset($attributes['customer'])) {
            return $attributes['customer'];
        }

        return [];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'volo_frontend.customer_extension';
    }
}