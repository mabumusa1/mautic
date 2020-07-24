<?php

/*
 * @copyright   2020 SteerCampaign. All rights reserved
 * @author      Mohammad Abu Musa<m.abumusa@gmail.com>
 *
 * @link        https://steercampaign.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'name'        => 'AWS SQS Integration',
    'description' => 'Integreate Mautic with SQS for mass sending',
    'version'     => '1.0',
    'author'      => 'Mohammad Abu Musa <m.abumusa@gmail.com>',
    'other'       => [
        'mautic.spool.delegator' => [
            'class'     => \MauticPlugin\ScSQSBundle\Swiftmailer\Spool\DelegatingSpool::class,
            'arguments' => [
                'mautic.helper.core_parameters',
                'swiftmailer.transport.real',
            ],
        ],

        // Mailers
        'mautic.transport.spool' => [
            'class'     => \MauticPlugin\ScSQSBundle\Swiftmailer\Transport\SpoolTransport::class,
            'arguments' => [
                'swiftmailer.mailer.default.transport.eventdispatcher',
                'mautic.spool.delegator',
            ],
        ],
    ],
];
