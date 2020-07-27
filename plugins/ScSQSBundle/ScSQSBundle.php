<?php

/*
 * @copyright   2020 SteerCampaign. All rights reserved
 * @author      Mohammad Abu Musa<m.abumusa@gmail.com>
 *
 * @link        https://steercampaign.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\ScSQSBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;
use MauticPlugin\ScSQSBundle\DependencyInjection\Compiler\SqsTransportPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;


class ScSQSBundle extends PluginBundleBase
{
    /**
    * {@inheritdoc}
    */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new SqsTransportPass());
    }
    
}
