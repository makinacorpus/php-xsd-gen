<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Reader;

use MakinaCorpus\XsdGen\Error\ResourceCouldNotBeFoundError;

class DefaultResourceLocator implements ResourceLocator
{
    private array $resources = [];

    #[\Override]
    public function findResource(string $uri, ?string $schemaLocation = null, ?string $directory = null): string
    {
        $where = $schemaLocation ? $schemaLocation : $uri;

        if ($found = $this->resources[$where] ?? null) {
            return $found;
        }

        if (false !== \strpos($where, '://')) {
            list($scheme, $filename) = \explode('://', $where, 2);
        } else {
            $scheme = null;
            $filename = $where;
        }

        if ($scheme && $scheme !== 'file') {
            // OK, we should download the file.
            throw new ResourceCouldNotBeFoundError(\sprintf("%s: resource download is not implemented yet.", $uri));
        }

        if ($directory && !\str_starts_with($filename, '/')) {
            $filename = \rtrim($directory, '/') . '/' . $filename;
        }

        if (!\file_exists($filename)) {
            throw new ResourceCouldNotBeFoundError(\sprintf("%s: %s: file does not exist", $uri, $filename));
        }

        $this->resources[$uri] = $filename;

        return $filename;
    }
}
