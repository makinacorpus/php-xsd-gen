<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Helper;

use Psr\Log\AbstractLogger;

class EchoLogger extends AbstractLogger
{
    public static function buildLogString(string|\Stringable $message, array $context = []): string
    {
        $rep = [];
        foreach ($context as $key => $value) {
            if ('{' !== $key[0]) {
                $rep['{' . $key . '}'] = $value;
            } else {
                $rep[$key] = $value;
            }
        }

        return \strtr($message, $rep);
    }

    #[\Override]
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        echo $level . ': ' . $this->buildLogString($message, $context) . "\n";
    }
}
