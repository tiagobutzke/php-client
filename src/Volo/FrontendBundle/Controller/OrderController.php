<?php

namespace Volo\FrontendBundle\Controller;

use CommerceGuys\Guzzle\Oauth2\AccessToken;
use Foodpanda\ApiSdk\Entity\Order\OrderPayment;
use Foodpanda\ApiSdk\Exception\OrderNotFoundException;
use Foodpanda\ApiSdk\Provider\OrderProvider;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @Route("/orders")
 */
class OrderController extends BaseController
{
    const SESSION_GUEST_ORDER_ACCESS_TOKEN = 'guest_order_access_token';
    const SESSION_ORDER_PAYMENT_CODE = 'order_payment_code';

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
        $session = $request->getSession();
        $accessToken = $this->createAccessToken($orderCode, $session);

        $orderProvider = $this->get('volo_frontend.provider.order');
        try {
            $status = $orderProvider->fetchOrderStatus($orderCode, $accessToken);
            $orderPayment = $this->createOrderPayment($orderCode, $session, $status, $orderProvider, $accessToken);
        } catch (OrderNotFoundException $e) {
            throw $this->createNotFoundException(sprintf('Order "%s" not found.', $orderCode), $e);
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

    /**
     * @param string $orderCode
     * @param SessionInterface $session
     * @param array $status
     * @param OrderProvider $orderProvider
     * @param AccessToken $accessToken
     *
     * @return OrderPayment
     */
    private function createOrderPayment(
        $orderCode,
        SessionInterface $session,
        array $status,
        OrderProvider $orderProvider,
        AccessToken $accessToken
    )
    {
        if (in_array($session->get(static::SESSION_ORDER_PAYMENT_CODE), ['cod', 'invoice'])) {
            $orderPayment = new OrderPayment();
            $orderPayment->setStatus('pending');
            $orderPayment->setReference(($session->get(static::SESSION_ORDER_PAYMENT_CODE)));
            $orderPayment->setAmount($status['total_value']);

            return $orderPayment;
        } else {
            $orderPayment = $orderProvider->fetchOrderPaymentInformation($orderCode, $accessToken);

            return $orderPayment;
        }
    }

    /**
     * @param string $orderCode
     * @param SessionInterface $session
     *
     * @return AccessToken
     */
    private function createAccessToken($orderCode, SessionInterface $session)
    {
        if ($this->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY')) {
            $accessToken = $this->getToken()->getAccessToken();
        } else {
            if (!$session->has(static::SESSION_GUEST_ORDER_ACCESS_TOKEN)) {
                throw $this->createNotFoundException(sprintf('Order "%s" not found for guest user.', $orderCode));
            }
            $accessToken = new AccessToken($session->get(static::SESSION_GUEST_ORDER_ACCESS_TOKEN), 'bearer');
        }

        return $accessToken;
    }
}
