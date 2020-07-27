<?php

/*
 * @copyright   2020 SteerCampaign. All rights reserved
 * @author      Mohammad Abu Musa<m.abumusa@gmail.com>
 *
 * @link        https://steercampaign.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */


namespace MauticPlugin\ScSQSBundle\Swiftmailer\Transport;

use MauticPlugin\ScSQSBundle\Swiftmailer\Spool\DelegatingSpoolSqs;

class SqsTransport extends \Swift_Transport_SpoolTransport
{
    /**
     * @var \Swift_Events_EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var DelegatingSpoolSqs
     */
    private $spool;

    /**
     * SpoolTransport constructor.
     */
    public function __construct(\Swift_Events_EventDispatcher $eventDispatcher, DelegatingSpoolSqs $delegatingSpool)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->spool           = $delegatingSpool;

        parent::__construct($eventDispatcher, $delegatingSpool);
    }

    /**
     * Sends or queues the given message.
     *
     * @param string[] $failedRecipients An array of failures by-reference
     *
     * @return int The number of sent e-mails
     *
     * @throws \Swift_IoException
     */
    public function send(\Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        if ($evt = $this->eventDispatcher->createSendEvent($this, $message)) {
            $this->eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled()) {
                return 0;
            }
        }

        $count = $this->spool->delegateMessage($message, $failedRecipients);

        if ($evt) {
            $successResult = $this->spool->wasMessageSpooled() ? \Swift_Events_SendEvent::RESULT_SPOOLED : \Swift_Events_SendEvent::RESULT_SUCCESS;
            $evt->setResult($count ? $successResult : \Swift_Events_SendEvent::RESULT_FAILED);
            $this->eventDispatcher->dispatchEvent($evt, 'sendPerformed');
        }

        return $count;
    }

    public function supportsTokenization(): bool
    {
        return $this->spool->isTokenizationEnabled();
    }

    public function getMaxBatchLimit()
    {
        return $this->spool->getRealTransport()->getMaxBatchLimit();
    }

    public function getBatchRecipientCount(\Swift_Message $message, $toBeAdded = 1, $type = 'to')
    {
        return $this->spool->getRealTransport()->getBatchRecipientCount($message, $toBeAdded, $type);
    }
}
