<?php

/*
 * @copyright   2020 SteerCampaign. All rights reserved
 * @author      Mohammad Abu Musa<m.abumusa@gmail.com>
 *
 * @link        https://steercampaign.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\SteercampaignSqsBundle\Integration;


use Mautic\PluginBundle\Integration\AbstractIntegration;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;


class SqsIntegration extends AbstractIntegration
{
    public function getName()
    {
        return 'Sqs';
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDisplayName()
    {
        return 'Amazon SQS';
    }
    
    public function getDescription()
    {
        return 'Make SQS the default Spool Engine for queining emails';
    }


    /**
     * Return's authentication method such as oauth2, oauth1a, key, etc.
     *
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'api';
    }


    /**
     * Get the array key for clientId.
     *
     * @return string
     */
    public function getUserNameKey()
    {
        return 'username';
    }

    /**
     * Get the array key for client secret.
     *
     * @return string
     */
    public function getPasswordKey()
    {
        return 'password';
    }


    /**
     * Get the array key for queue url.
     *
     * @return string
     */
    public function getUrlKey()
    {
        return 'url';
    }
    
    /**
     * Get the array key for region url.
     *
     * @return string
     */
    public function getRegionKey()
    {
        return 'region';
    }

    /**
     * {@inheritdoc}
     */
    public function getRequiredKeyFields()
    {
        return [
            'username'     => 'mautic.integration.keyfield.username',
            'password' => 'mautic.integration.keyfield.password',            
        ];
    }
    /**
     * @param Form|FormBuilder $builder
     * @param array            $data
     * @param string           $formArea
     */
    public function appendToForm(&$builder, $data, $formArea)
    {
        if ('keys' === $formArea) {
            $builder->add(
                'url',
                TextType::class,
                [
                    'label'    => 'mautic.integration.SQS.url',
                    'attr'     => ['class'   => 'form-control'],
                    'data'     => empty($data['url']) ? '' : $data['url'],
                    'required' => true,
                    'constraints' => [
                        new Url(),
                        New NotBlank()
                    ]
                ]               
            );
            $builder->add(
                'region',
                TextType::class,
                [
                    'label'    => 'mautic.integration.SQS.region',
                    'attr'     => ['class'   => 'form-control'],
                    'data'     => empty($data['region']) ? 'us-east-1' : $data['region'],
                    'required' => false,
                ]
            );

            $builder->add(
                'size',
                NumberType::class,
                [
                    'label'    => 'mautic.integration.SQS.size',
                    'attr'     => ['class'   => 'form-control'],
                    'data'     => empty($data['size']) ? 50 : $data['size'],
                    'required' => false,
                    'constraints'   => [
                        new GreaterThanOrEqual([
                            'value' => 1
                        ])
                    ],
                ]
            );

            $builder->add(
                'min_number_messages',
                NumberType::class,
                [
                    'label'    => 'mautic.integration.SQS.max_number_messages',
                    'attr'     => ['class'   => 'form-control'],
                    'data'     => empty($data['min_number_messages']) ? 1 : $data['min_number_messages'],
                    'required' => false,
                    'constraints'   => [
                        new GreaterThanOrEqual([
                            'value' => 1
                        ])
                    ],

                ]
            );
        
            $builder->add(
                'max_number_messages',
                NumberType::class,
                [
                   'label'    => 'mautic.integration.SQS.max_number_messages',
                    'attr'     => ['class'   => 'form-control'],
                    'data'     => empty($data['max_number_messages']) ? 10 : $data['max_number_messages'],
                    'required' => false,
                    'constraints'   => [
                        new GreaterThanOrEqual([
                            'value' => 1
                        ])
                    ],

                ]
            );

            $builder->add(
                'long_polling_timeout',
                NumberType::class,
                [
                    'label'    => 'mautic.integration.SQS.long_polling_timeout',
                    'attr'     => ['class'   => 'form-control'],
                    'data'     => empty($data['long_polling_timeout']) ? 10 : $data['long_polling_timeout'],
                    'required' => false,
                    'constraints'   => [
                        new GreaterThanOrEqual([
                            'value' => 1
                        ])
                    ],
                ]
            );
            $builder->add(
                'test_sqs',
                ButtonType::class,
                [
                    'label' => 'mautic.integration.SQS.test',
                    'attr'  => [
                        'class'   => 'btn btn-primary',
                        'style'   => 'margin-bottom: 10px',
                        'onclick' => 'Mautic.testSqsApi(this)',
                    ],
                ]
            );
            $builder->add(
                'stats',
                TextareaType::class,
                [
                    'label_attr' => ['class' => 'control-label'],
                    'label'      => 'mautic.integration.SQS.stats',
                    'required'   => false,
                    'attr'       => [
                        'class'    => 'form-control',
                        'rows'     => '6',
                        'readonly' => 'readonly',
                    ],
                ]
            );
    
        }

    }

    /**
     * {@inheritdoc}
     *
     * @param $section
     *
     * @return string|array
     */
    public function getFormNotes($section)
    {        
        if ('custom' === $section) {
            return [
                'template'   => 'SteercampaignSqsBundle:Integration:form.html.php',
                'parameters' => [
                ],
            ];
        }
        return parent::getFormNotes($section);
    }

}

