<?php

/*
 * @copyright   2020 SteerCampaign. All rights reserved
 * @author      Mohammad Abu Musa<m.abumusa@gmail.com>
 *
 * @link        https://steercampaign.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\SteercampaignSqsBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Mautic\PluginBundle\Helper\IntegrationHelper;

/**
 * CLI command to process the e-mail queue.
 */
class ProcessSQSCommand extends ModeratedCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('steercampaign:sqs:send')
            ->setDescription('Processes SwiftMail\'s SQS')
            ->addOption('--message-limit', null, InputOption::VALUE_OPTIONAL, 'Limit number of messages sent at a time. Defaults to value set in config.')
            ->addOption('--time-limit', null, InputOption::VALUE_OPTIONAL, 'Limit the number of seconds per batch. Defaults to value set in config.')
            ->addOption('--do-not-clear', null, InputOption::VALUE_NONE, 'By default, failed messages older than the --recover-timeout setting will be attempted one more time then deleted if it fails again.  If this is set, sending of failed messages will continue to be attempted.')
            ->addOption('--recover-timeout', null, InputOption::VALUE_OPTIONAL, 'Sets the amount of time in seconds before attempting to resend failed messages.  Defaults to value set in config.')
            ->addOption('--clear-timeout', null, InputOption::VALUE_OPTIONAL, 'Sets the amount of time in seconds before deleting failed messages.  Defaults to value set in config.')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command is used to process the application's e-mail queue

<info>php %command.full_name%</info>
EOT
        );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $options    = $input->getOptions();
        $env        = (!empty($options['env'])) ? $options['env'] : 'dev';
        $container  = $this->getContainer();
        $dispatcher = $container->get('event_dispatcher');
        $skipClear  = $input->getOption('do-not-clear');
        $quiet      = $input->hasOption('quiet') ? $input->getOption('quiet') : false;
        $timeout    = $input->getOption('clear-timeout');
     
        $container = $this->getContainer();

        /** @var \Mautic\CoreBundle\Factory\MauticFactory $factory */
        $factory = $container->get('mautic.factory');
        $integrationHelper = $factory->getHelper('integration');
        $integrationObject = $integrationHelper->getIntegrationObject('Sqs');
        $queueMode  = $integrationObject->getIntegrationSettings()->isPublished();

        if ('sqs' != $queueMode) {
            $output->writeln('Mautic is not set to SQS email.');

            return 0;
        }

        if (!$this->checkRunStatus($input, $output)) {
            return 0;
        }

        if (empty($timeout)) {
            $timeout = $container->getParameter('mautic.mailer_spool_clear_timeout');
        }

        //now process new emails
        if (!$quiet) {
            $output->setVerbosity(OutputInterface::VERBOSITY_QUIET);
        }

        $command     = $this->getApplication()->find('swiftmailer:spool:send');
        $commandArgs = [
            'command' => 'swiftmailer:spool:send',
            '--env'   => $env,
        ];
        if ($quiet) {
            $commandArgs['--quiet'] = true;
        }

        //set spool message limit
        if ($msgLimit = $input->getOption('message-limit')) {
            $commandArgs['--message-limit'] = $msgLimit;
        } elseif ($msgLimit = $container->getParameter('mautic.mailer_spool_msg_limit')) {
            $commandArgs['--message-limit'] = $msgLimit;
        }

        //set time limit
        if ($timeLimit = $input->getOption('time-limit')) {
            $commandArgs['--time-limit'] = $timeLimit;
        } elseif ($timeLimit = $container->getParameter('mautic.mailer_spool_time_limit')) {
            $commandArgs['--time-limit'] = $timeLimit;
        }

        //set the recover timeout
        if ($timeout = $input->getOption('recover-timeout')) {
            $commandArgs['--recover-timeout'] = $timeout;
        } elseif ($timeout = $container->getParameter('mautic.mailer_spool_recover_timeout')) {
            $commandArgs['--recover-timeout'] = $timeout;
        }
        $input      = new ArrayInput($commandArgs);
        $returnCode = $command->run($input, $output);

        $this->completeRun();

        if (0 !== $returnCode) {
            return $returnCode;
        }

        return 0;
    }
}
