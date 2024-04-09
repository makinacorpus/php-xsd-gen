<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Helper;

use Psr\Log\LoggerInterface;

trait ContextTrait
{
    private ?LoggerInterface $logger = null;

    /**
     * All errors, warnings, notices will be logged into this logger.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    #[\Override]
    public function logInfo(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        } else {
            \trigger_error(EchoLogger::buildLogString($message, $context), E_USER_NOTICE);
        }
    }

    #[\Override]
    public function logWarn(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning($message, $context);
        } else {
            \trigger_error(EchoLogger::buildLogString($message, $context), E_USER_WARNING);
        }
    }

    #[\Override]
    public function logErr(string|\Stringable $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        } else {
            \trigger_error(EchoLogger::buildLogString($message, $context), E_USER_ERROR);
        }
    }
}
