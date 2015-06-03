<?php

namespace Volo\FrontendBundle\Controller;

use Foodpanda\ApiSdk\Api\Auth\Credentials;
use Foodpanda\ApiSdk\Exception\ValidationEntityException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Volo\FrontendBundle\Service\Exception\PhoneNumberValidationException;

class CustomerController extends Controller
{
    /**
     * @Route("/customer", name="customer.create")
     * @Template()
     * @Method({"GET", "POST"})
     *
     * @param Request $request
     *
     * @return array
     */
    public function createAction(Request $request)
    {
        $errorMessages = [];
        $customer = [];
        if ($request->isMethod(Request::METHOD_POST)) {
            $customerService = $this->get('volo_frontend.service.customer');

            try {
                $customerData = $request->request->get('customer');
                $newCustomer = $customerService->createCustomer($customerData);

                $credentials = new Credentials($newCustomer->getEmail(), $customerData['password']);
                $token = $this->container->get('volo_frontend.security.authenticator')->login($credentials);

                $this->get('security.token_storage')->setToken($token);

                return $this->redirectToRoute('home');
            } catch (PhoneNumberValidationException $e) {
                $errorMessages[] = $this->get('translator')->trans(sprintf('%s: %s', 'Phone number', $e->getMessage()));
            } catch (ValidationEntityException $e) {
                $errors = json_decode($e->getMessage(), true)['data']['items'];
                $errorMessages = $this->createErrors($errors);
            } catch (\Exception $e) {
                // @TODO: ask PMs about the appropriate message and use the translation
                $errorMessages[] = 'An error occurred, please try again';
            }

            $customer = $request->request->get('customer', []);
        }

        return [
            'customer' => $customer,
            'errors'   => $errorMessages,
        ];
    }

    protected function createErrors(array $errors)
    {
        $errorMessages = [];
        foreach ($errors as $error) {
            foreach ($error['violation_messages'] as $violationMessage) {
                $errorMessages[] = sprintf('%s: %s', $error['field_name'], $violationMessage);
            }
        }

        return $errorMessages;
    }
}
