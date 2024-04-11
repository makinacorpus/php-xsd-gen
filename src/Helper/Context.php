<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Helper;

interface Context
{
    public function logInfo(string|\Stringable $message, array $context = []): void;

    public function logWarn(string|\Stringable $message, array $context = []): void;

    public function logErr(string|\Stringable $message, array $context = []): void;
}
