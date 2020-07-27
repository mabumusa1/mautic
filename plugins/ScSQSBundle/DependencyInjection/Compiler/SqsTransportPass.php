<?php

/*
 * @copyright   2020 SteerCampaign. All rights reserved
 * @author      Mohammad Abu Musa<m.abumusa@gmail.com>
 *
 * @link        https://steercampaign.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\ScSQSBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class SqsTransportPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        // Override Swiftmailer's \Swift_TransportSpool with our own that delegates writing to the filesystem or sending immediately
        // based on the configuration
        $container->setAlias('swiftmailer.mailer.sqs.transport', 'mautic.transport.sqs');
    }
}
