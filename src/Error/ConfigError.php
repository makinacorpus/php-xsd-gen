<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Error;

class ConfigError extends \InvalidArgumentException implements SoapGeneratorError
{
}
