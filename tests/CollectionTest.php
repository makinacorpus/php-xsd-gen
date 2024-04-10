<?php

declare(strict_types=1);

namespace MakinaCorpus\SoapGenerator\Tests;

use MakinaCorpus\SoapGenerator\Generator;
use MakinaCorpus\SoapGenerator\Helper\EchoLogger;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    public function testCollectionDefaults(): void
    {
        (new Generator())
            ->defaultDirectory(__DIR__ . '/Generated')
            ->defaultNamespace('MakinaCorpus\\SoapGenerator\\Tests\\Generated')
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
            ->defaultNamespace('MakinaCorpus\\SoapGenerator\\Tests\\Generated')
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