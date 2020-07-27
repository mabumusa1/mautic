<?php

/*
 * @copyright   2016 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\ScSQSBundle\Controller;

use Mautic\FormBundle\Controller\FormController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SqsController extends FormController
{
    public function testAction()
    {
        return new JsonResponse(
            [
                'closeModal' => true,
                'flashes'    => 'test'
            ]
        );

    }
}