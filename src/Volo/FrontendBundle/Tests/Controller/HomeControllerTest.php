<?php

namespace Volo\FrontendBundle\Tests\Controller;

class HomeControllerTest extends VoloTestCase
{
    public function testHome()
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->isSuccessful($client->getResponse());
    }
}
