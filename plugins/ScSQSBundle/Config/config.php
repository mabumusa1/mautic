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
    'description' => 'Make SQS the default Spool Engine for queining emails',
    'version'     => '1.0',
    'author'      => 'Mohammad Abu Musa <m.abumusa@gmail.com>',
    'description' => 'Saves all the emails that are queued for sending on SQS instead of saving them on the filesystem',

    'routes' => [
        'main' => [
            'sqs' => [
                'path'       => '/sqs/test',
                'controller' => 'ScSQSBundle:Sqs:test',
                'method'     => 'GET'
            ],
        ],
    ],

    'services' => [
        'others' => [
            'mautic.spool.delegatorSqs' => [
                'class'     => MauticPlugin\ScSQSBundle\Swiftmailer\Spool\DelegatingSpoolSqs::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'swiftmailer.transport.real'                
                ],
            ],
            'mautic.transport.sqs' => [
                'class' => MauticPlugin\ScSQSBundle\Swiftmailer\Transport\SqsTransport::class,
                'arguments' => [
                    'swiftmailer.mailer.default.transport.eventdispatcher',
                    'mautic.spool.delegatorSqs',                     
                ],
            ]
        ],
        'integrations' => [
            'mautic.integration.sqs' => [
                'class'     => MauticPlugin\ScSQSBundle\Integration\SqsIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'session',
                    'request_stack',
                    'router',
                    'translator',
                    'logger',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                ],
            ]
        ]    
    ],
];