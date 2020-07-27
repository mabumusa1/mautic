<?php

/*
 * @copyright   2020 SteerCampaign. All rights reserved
 * @author      Mohammad Abu Musa<m.abumusa@gmail.com>
 *
 * @link        https://steercampaign.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\ScSQSBundle\Swiftmailer\Spool;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\Swiftmailer\Transport\TokenTransportInterface;
use Swift_Mime_SimpleMessage;
use Aws\Sqs\SqsClient;
use AwsAwsException\AwsException;
use Psr\Log\LoggerInterface;

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
     * @var CoreParametersHelper
     */
    private $coreParametersHelper;

    /**
     * @var bool
     */
    private $messageSpooled = false;

    /**
     * @var integer The minimum number of messages that may be fetched at one time
     */
    const MIN_NUMBER_MESSAGES = 1;

    /**
     * @var integer The maximum number of messages that may be fetched at one time
     */
    const MAX_NUMBER_MESSAGES = 10;

    /**
     * @var int default value
     */
    const DEFAULT_AWS_SQS_LONG_POLLING_TIMEOUT = 10; //in seconds


    /**
    * @var SqsClient
    */
    private $sqs;

    /**
     * Constructor.
     *
     * @param CoreParametersHelper $coreParametersHelper
     * @param AmazonSQS $sqs
     *
     */
    public function __construct(CoreParametersHelper $coreParametersHelper, \Swift_Transport $realTransport)
    {
        $this->sqsSpoolEnabled     = 'sqs' === $coreParametersHelper->get('mailer_spool_type');
        $this->coreParametersHelper = $coreParametersHelper;
        $this->realTransport        = $realTransport;

        $this->sqs = new SqsClient([
            'region' => $coreParametersHelper->get('mailer_spool_sqs_region'),
            'version' => '2012-11-05',
            'credentials' => [
                'key'    => $coreParametersHelper->get('mailer_spool_sqs_username'),
                'secret' => $coreParametersHelper->get('mailer_spool_sqs_password'),
            ],
        ]);

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
        // Write to filesystem if file spooling is enabled
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

        if (strlen($messageBody) > $this->coreParametersHelper->get('mailer_spool_sqs_max_message_size') * 1024) {
            return false;
        }

        try {
            $response = $this->sqs->sendMessage([
                'QueueUrl' => $this->coreParametersHelper->get('mailer_spool_sqs_url'),
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
                static::MAX_NUMBER_MESSAGES
            ),
            static::MIN_NUMBER_MESSAGES
        );

        $options = [
            'MaxNumberOfMessages' => $maxNumberOfMessages,
            'WaitTimeSeconds' => static::DEFAULT_AWS_SQS_LONG_POLLING_TIMEOUT
        ];

        while (true) {
            try {
                $response = $this->sqs->receiveMessage([
                        'QueueUrl' => $this->coreParametersHelper->get('mailer_spool_sqs_url'),
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
                'QueueUrl' => $this->coreParametersHelper->get('mailer_spool_sqs_url'),
                'ReceiptHandle' => $receiptHandle
            ]);
        } catch (AwsException $e) {
            return false;
        }

        return true;
    }
}
