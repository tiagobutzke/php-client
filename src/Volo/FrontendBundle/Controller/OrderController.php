<?php

namespace Volo\FrontendBundle\Controller;

use CommerceGuys\Guzzle\Oauth2\AccessToken;
use Foodpanda\ApiSdk\Entity\Order\GuestCustomer;
use Foodpanda\ApiSdk\Exception\OrderNotFoundException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/orders")
 */
class OrderController extends Controller
{
    const SESSION_GUEST_ORDER_ACCESS_TOKEN = 'guest_order_access_token';

    /**
     * @Route("/{orderCode}/tracking", name="order_tracking", options={"expose"=true})
     * @Method({"GET"})
     *
     * @param Request $request
     * @param string $orderCode
     * @return array
     */
    public function statusAction(Request $request, $orderCode)
    {
        $session = $this->get('session');
        $accessToken = $this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')
            ? $this->get('security.token_storage')->getToken()->getAccessToken()
            : new AccessToken($session->get(static::SESSION_GUEST_ORDER_ACCESS_TOKEN), 'bearer');
        $orderProvider = $this->get('volo_frontend.provider.order');
        try {
            $orderPayment = $orderProvider->fetchOrderPaymentInformation($orderCode, $accessToken);
            $status = $orderProvider->fetchOrderStatus($orderCode, $accessToken);
        } catch (OrderNotFoundException $e) {
            throw $this->createNotFoundException('Order not found.', $e);
        }

        $viewName = 'VoloFrontendBundle:Order:status.html.twig';
        if ($request->isXmlHttpRequest() && $request->query->get('partial')) {
            $viewName = 'VoloFrontendBundle:Order:tracking_steps.html.twig';
        }

        $content = $this->renderView($viewName, [
            'order' => $orderPayment,
            'status' => $status
        ]);

        return new Response($content);
    }
}
