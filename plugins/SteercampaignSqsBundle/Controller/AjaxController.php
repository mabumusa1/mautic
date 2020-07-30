<?php

/*
 * @copyright   2016 Mautic, Inc. All rights reserved
 * @author      Mautic, Inc
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\SteercampaignSqsBundle\Controller;

use Aws\Sqs\SqsClient;
use AwsAwsException\AwsException;
use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Helper\InputHelper;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    protected function getTestSqsAction(Request $request)
    {
        $username = $request->request->get('username');
        $password = $request->request->get('password');
        $region   = $request->request->get('region');
        $url      = $request->request->get('url');

        $dataArray = [
            'html'    => '',
            'success' => 0,
        ];

        try {
            $client = new SqsClient([
                'region'      => $region,
                'version'     => '2012-11-05',
                'credentials' => [
                    'key'    => $username,
                    'secret' => $password,
                ],
            ]);

            $result = $client->listQueues();

            $dataArray['html'] = 'Connected Successfully, please make sure this user has enough priviliges to read and write from the queue';
        } catch (AwsException $e) {
            $dataArray['html'] = $e->getMessage();
        } catch (\Exception $e) {
            $dataArray['html'] = $e->getMessage();
        }

        return $this->sendJsonResponse($dataArray);
    }
}
