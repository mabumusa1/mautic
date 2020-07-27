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
use MauticPlugin\ScSQSBundle\Swiftmailer\Spool\DelegatingSpoolSqs;
use Mautic\EmailBundle\Swiftmailer\Transport\MomentumTransport;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Aws\Sqs\SqsClient;
use Monolog\Logger;

class DelegatingSpoolSqsTest extends TestCase
{
    
    /**
     * @var CoreParametersHelper|MockObject
     */
    private $coreParametersHelper;

    /**
     * @var \Swift_Transport|MockObject
     */
    private $realTransport;

    /**
     * @var \Swift_Mime_SimpleMessage|MockObject
     */
    private $message;

    /**
    * @var Aws\Sqs\SqsClient
    */
    private $sqs;

    protected function setUp()
    {
        $this->coreParametersHelper = $this->createMock(CoreParametersHelper::class);
        $this->realTransport        = $this->createMock(\Swift_Transport::class);
        $this->message              = $this->createMock(\Swift_Mime_SimpleMessage::class);
        $this->sqs                  = new SqsClient([
            'region' => 'us-east-2',
            'version' => '2012-11-05',
            'credentials' => [
                'key'    => 'AKIAQZPZ6QV7HZGEA66M',
                'secret' => '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',
            ],
        ]);
        $this->logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();


    }

    public function testEmailIsQueuedIfSpoolingIsEnabled()
    {

        $this->coreParametersHelper->expects($this->exactly(4))
        ->method('get')
        ->withConsecutive(['mailer_spool_type'], ['mailer_spool_sqs_url'], ['mailer_spool_sqs_max_message_size'], ['mailer_spool_sqs_long_polling_timeout'])
        ->willReturnOnConsecutiveCalls('sqs', 'https://sqs.us-east-2.amazonaws.com/054748153214/sqs-01', 50, 20);

        $spool = new DelegatingSpoolSqs($this->coreParametersHelper, $this->realTransport, $this->logger, $this->sqs);
        $spool->delegateMessage($this->message);
        $this->assertTrue($spool->wasMessageSpooled());
    }

    public function testEmailIsSentImmediatelyIfSpoolingIsDisabled()
    {
        
        $this->coreParametersHelper->expects($this->exactly(4))
        ->method('get')
        ->withConsecutive(['mailer_spool_type'], ['mailer_spool_sqs_url'], ['mailer_spool_sqs_max_message_size'], ['mailer_spool_sqs_long_polling_timeout'])
        ->willReturnOnConsecutiveCalls('memory', 'https://sqs.us-east-2.amazonaws.com/054748153214/sqs-01', 50, 20);


        $this->realTransport->expects($this->once())
            ->method('send')
            ->willReturn(1);

        $spool = new DelegatingSpoolSqs($this->coreParametersHelper, $this->realTransport, $this->logger, $this->sqs);

        $failed = [];
        $sent   = $spool->delegateMessage($this->message, $failed);

        $this->assertFalse($spool->wasMessageSpooled());
        $this->assertEquals(1, $sent);
    }

    public function testThatTokenizationIsDisabledIfSqsSpoolIsEnabled()
    {
        $this->realTransport = $this->createMock(MomentumTransport::class);
        $this->coreParametersHelper->expects($this->exactly(4))
            ->method('get')
            ->withConsecutive(['mailer_spool_type'], ['mailer_spool_sqs_url'], ['mailer_spool_sqs_max_message_size'], ['mailer_spool_sqs_long_polling_timeout'])
            ->willReturnOnConsecutiveCalls('sqs', null);

        $spool = new DelegatingSpoolSqs($this->coreParametersHelper, $this->realTransport, $this->logger, $this->sqs);
        $this->assertFalse($spool->isTokenizationEnabled());
    }

    public function testThatTokenizationIsEnabledIfSqsSpoolIsDisabled()
    {
        $this->realTransport = $this->createMock(MomentumTransport::class);
        $this->coreParametersHelper->expects($this->exactly(4))
            ->method('get')
            ->withConsecutive(['mailer_spool_type'], ['mailer_spool_sqs_url'], ['mailer_spool_sqs_max_message_size'], ['mailer_spool_sqs_long_polling_timeout'])
            ->willReturnOnConsecutiveCalls('notFile', null);

        $spool = new DelegatingSpoolSqs($this->coreParametersHelper, $this->realTransport, $this->logger, $this->sqs);
        $this->assertTrue($spool->isTokenizationEnabled());
    }

    public function testThatTokenizationIsDisabledIfRealTransposrtDoesNotImplementTokenTransportInterface()
    {
        $this->coreParametersHelper->expects($this->exactly(4))
            ->method('get')
            ->withConsecutive(['mailer_spool_type'], ['mailer_spool_sqs_url'], ['mailer_spool_sqs_max_message_size'], ['mailer_spool_sqs_long_polling_timeout'])
            ->willReturnOnConsecutiveCalls('notFile', null);

        $spool = new DelegatingSpoolSqs($this->coreParametersHelper, $this->realTransport, $this->logger, $this->sqs);
        $this->assertFalse($spool->isTokenizationEnabled());
    }

    public function testDelegateMessageWillReturnIntEvenIfTransportWillNot()
    {
        
        $this->coreParametersHelper->expects($this->exactly(4))
            ->method('get')
            ->withConsecutive(['mailer_spool_type'], ['mailer_spool_sqs_url'], ['mailer_spool_sqs_max_message_size'], ['mailer_spool_sqs_long_polling_timeout'])
            ->willReturnOnConsecutiveCalls('memory', null);


        $this->realTransport->expects($this->once())
            ->method('send')
            ->willReturn(null); // null. Not int. Must be typed to int.

        $spool = new DelegatingSpoolSqs($this->coreParametersHelper, $this->realTransport, $this->logger, $this->sqs);        $failed = [];
        $sent   = $spool->delegateMessage($this->message, $failed);

        $this->assertEquals(0, $sent);

    }    
}
