<?php

/*
 * @copyright   2020 SteerCampaign. All rights reserved
 * @author      Mohammad Abu Musa<m.abumusa@gmail.com>
 *
 * @link        https://steercampaign.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\SteercampaignSqsBundle\Swiftmailer\Spool;

use Mautic\EmailBundle\Swiftmailer\Transport\TokenTransportInterface;
use Swift_Mime_SimpleMessage;
use Aws\Sqs\SqsClient;
use AwsAwsException\AwsException;
use Psr\Log\LoggerInterface;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\SteercampaignSqsBundle\Integration\SqsIntegration;
use Monolog\Logger;

/**
 * Class DelegatingSpoolSqs
*/
class DelegatingSpoolSqs extends \Swift_ConfigurableSpool
{

    /**
     * @var bool
     */
    private $sqsSpoolEnabled = false;

    /**
     * @var \Swift_Transport
     */
    private $realTransport;


    /**
     * @var bool
     */
    private $messageSpooled = false;

    /**
     * @var integer The minimum number of messages that may be fetched at one time
     */
    private $min_number_messages = 1;
    

    /**
     * @var integer The maximum number of messages that may be fetched at one time
     */
    private $max_number_messages = 1;
    

    /**
     * @var int default value
     */
    private $long_polling_timeout = 10; //in seconds
    
    /**
     * @var int default value
     */
    private $size = 1000; //in bytes


    /**
     * @var bool|SqsIntegration
     */
    protected $integration;

    /**
     * @var Logger
     */
    protected $logger;


    /**
    * @var SqsClient
    */
    private $sqs;

    /**
    * @var String
    */
    private $queueUrl;
    
    /**
     * Constructor.
     *
     * @param IntegrationHelper $integrationHelper
     * @param Logger $logger
     * @param Swift_Transport $realTransport
     *
     */
    public function __construct(
        IntegrationHelper $integrationHelper,
        Logger $logger,
        \Swift_Transport $realTransport
    )
    {

        $this->integration  = $integrationHelper->getIntegrationObject('Sqs');
        $this->logger       = $logger;

        $this->sqsSpoolEnabled     = $this->integration->getIntegrationSettings()->isPublished();
        $this->realTransport        = $realTransport;

        $keys = $this->integration->getDecryptedApiKeys();
        $this->queueUrl = $keys['url'];

        try {
            $this->sqs = new SqsClient([
                'region' => $keys['region'],
                'version' => '2012-11-05',
                'credentials' => [
                    'key'    => $keys['username'],
                    'secret' => $keys['password'],
                ],
            ]);
            
            $this->min_number_messages = $keys['min_number_messages'];
            $this->max_number_messages = $keys['max_number_messages'];
            $this->long_polling_timeout = $keys['long_polling_timeout'];
            $this->size = $keys['size'];
        } catch (AwsException $e) {
            $this->logger->error('Error Connecting to SQS: '.$exception->getMessage());
        }

    }
    
    /**
     * {@inheritdoc}
     */
    public function isStarted()
    {
        return true;
    }    

    /**
     * {@inheritdoc}
     */
    public function start()
    {
    }

    /**
    * {@inheritdoc}
    */
    public function stop()
    {
    }


    public function delegateMessage(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->messageSpooled = false;
        // Write to sqs if file spooling is enabled
        if ($this->sqsSpoolEnabled) {
            return $this->queueMessage($message);
        }

        // Send immediately otherwise
        return (int) $this->realTransport->send($message, $failedRecipients);
    }

    /**
     * Queues a message.
     *
     * @param Swift_Mime_Message $message The message to store
     *
     * @return bool    Whether the operation has succeeded
     */
    public function queueMessage(\Swift_Mime_SimpleMessage $message)
    {
        $messageBody = base64_encode(gzcompress(serialize($message)));

        if (strlen($messageBody) > $this->size * 1024) {
            return false;
        }

        try {
            $response = $this->sqs->sendMessage([
                'QueueUrl' => $this->queueUrl,
                'MessageBody' => $messageBody
            ]);
            $this->messageSpooled = true;
        } catch (AwsException $e) {
            $this->messageSpooled = false;
        }
        return 1;
    }

    public function wasMessageSpooled(): bool
    {
        return $this->messageSpooled;
    }

    public function isTokenizationEnabled(): bool
    {
        return !$this->sqsSpoolEnabled && $this->realTransport instanceof TokenTransportInterface;
    }

    public function getRealTransport(): \Swift_Transport
    {
        return $this->realTransport;
    }

    /**
     * Sends messages using the given transport instance.
     *
     * @param Swift_Transport $transport A transport instance
     * @param string[] $failedRecipients An array of failures by-reference
     *
     * @return int     The number of sent emails
     */

    public function flushQueue(\Swift_Transport $transport, &$failedRecipients = null)
    {        
        $failedRecipients = (array)$failedRecipients;
        $count = 0;
        $time = time();

        $maxNumberOfMessages = max(
            min(
                $this->getMessageLimit(),
                $this->max_number_messages
            ),
            $this->min_number_messages
        );

        $options = [
            'MaxNumberOfMessages' => $maxNumberOfMessages,
            'WaitTimeSeconds' => $this->long_polling_timeout
        ];

        while (true) {
            try {
                $response = $this->sqs->receiveMessage([
                        'QueueUrl' => $this->queueUrl,
                    ] + $options);
            } catch (AwsException $e) {
                return $count;
            }

            $messages = $response->get('Messages');
            if (!is_array($messages) || count($messages) == 0) {
                return $count;
            }

            foreach ($messages as $sqsMessage) {
                if (!$transport->isStarted()) {
                    $transport->start();
                }

                $message = unserialize(gzuncompress(base64_decode((string)$sqsMessage['Body'])));

                try {
                    $count += $transport->send($message, $failedRecipients);
                } catch (AwsException $e) {
                    echo $e->getMessage();
                }

                $this->removeSqsMessage((string)$sqsMessage['ReceiptHandle']);

                if ($this->getMessageLimit() && ($count >= $this->getMessageLimit())) {
                    return $count;
                }

                if ($this->getTimeLimit() && ((time() - $time) >= $this->getTimeLimit())) {
                    return $count;
                }
            }
        }

        return $count;        
    }

    /**
     * Remove the message with the given receipt handle.
     *
     * @param string $receiptHandle
     *
     * @return boolean True if the remove was successful; otherwise false
     */
   protected function removeSqsMessage($receiptHandle)
    {
        try {
            $response = $this->sqs->deleteMessage([
                'QueueUrl' => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle
            ]);
        } catch (AwsException $e) {
            return false;
        }

        return true;
    }
}
