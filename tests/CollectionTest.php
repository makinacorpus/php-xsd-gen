<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Tests;

use MakinaCorpus\XsdGen\Generator;
use MakinaCorpus\XsdGen\Helper\EchoLogger;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testCollectionDefaults(): void
    {
        (new Generator())
            ->defaultDirectory(__DIR__ . '/Generated')
            ->defaultNamespace('MakinaCorpus\\XsdGen\\Tests\\Generated')
            ->namespace('https://schemas.makina-corpus.com/testing', 'Defaults')
            ->logger(new EchoLogger())
            ->file(__DIR__ . '/resources/collection.xsd')
            ->generate()
        ;

        self::expectNotToPerformAssertions();
    }

    public function testCollectionLegacy(): void
    {
        (new Generator())
            ->defaultDirectory(__DIR__ . '/Generated')
            ->defaultNamespace('MakinaCorpus\\XsdGen\\Tests\\Generated')
            ->namespace('https://schemas.makina-corpus.com/testing', 'Legacy')
            ->propertyGetter(true)
            ->propertyPromotion(false)
            ->propertyPublic(false)
            ->propertyReadonly(false)
            ->logger(new EchoLogger())
            ->file(__DIR__ . '/resources/collection.xsd')
            ->generate()
        ;

        self::expectNotToPerformAssertions();
    }
}
