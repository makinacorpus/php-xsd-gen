<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Reader;

interface ResourceLocator
{
    /**
     * Locate resource and return a local file path.
     *
     * @param string $uri
     *   Can be an absolute or relative file name, or an URI.
     * @param null|string $schemaLocation
     *   Schema location if provided in the original import tag.
     * @param null|string $directory
     *   Original file directory.
     */
    public function findResource(string $uri, ?string $schemaLocation = null, ?string $directory = null): string;
}
