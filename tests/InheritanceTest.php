<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Tests;

use MakinaCorpus\XsdGen\Generator;
use MakinaCorpus\XsdGen\Helper\EchoLogger;
use MakinaCorpus\XsdGen\Tests\Generated\Defaults;
use MakinaCorpus\XsdGen\Tests\Generated\Legacy;
use PHPUnit\Framework\TestCase;

class InheritanceTest extends TestCase
{
    public function testInheritanceDefaults(): void
    {
        (new Generator())
            ->defaultDirectory(__DIR__ . '/Generated')
            ->defaultNamespace('MakinaCorpus\\XsdGen\\Tests\\Generated')
            ->namespace('https://schemas.makina-corpus.com/testing', 'Defaults')
            ->logger(new EchoLogger())
            ->file(__DIR__ . '/resources/inheritance.xsd')
            ->generate()
        ;


        $instance = new Defaults\Inheritance\FrenchAddressWithPhone(street: 'foo', city: 'bar', country: 'baz', phoneNumber: null);
        self::assertInstanceOf(Defaults\Inheritance\FrenchAddress::class, $instance);
        self::assertInstanceOf(Defaults\Inheritance\Address::class, $instance);

        self::assertSame('foo', $instance->street);
        self::assertSame('bar', $instance->city);
        self::assertSame('baz', $instance->country);
        self::assertNull($instance->phoneNumber);

        self::assertSame(
            [
                'street' => ['string', false],
                'city' => ['string', false],
                'country' => ['string', false],
                'phoneNumber' => ['string', false],
            ],
            Defaults\Inheritance\FrenchAddressWithPhone::propertyMetadata()
        );

        $other = Defaults\Inheritance\FrenchAddressWithPhone::create([
            'Street' => 'foo',
            'City' => 'bar',
            'Country' => 'baz',
        ]);
        self::assertEquals($instance, $other);
    }

    public function testInheritanceLegacy(): void
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
            ->file(__DIR__ . '/resources/inheritance.xsd')
            ->generate()
        ;

        $instance = new Legacy\Inheritance\FrenchAddressWithPhone(street: 'foo', city: 'bar', country: 'baz', phoneNumber: null);
        self::assertInstanceOf(Legacy\Inheritance\FrenchAddress::class, $instance);
        self::assertInstanceOf(Legacy\Inheritance\Address::class, $instance);

        self::assertSame('foo', $instance->getStreet());
        self::assertSame('bar', $instance->getCity());
        self::assertSame('baz', $instance->getCountry());
        self::assertNull($instance->getPhoneNumber());

        self::assertSame(
            [
                'street' => ['string', false],
                'city' => ['string', false],
                'country' => ['string', false],
                'phoneNumber' => ['string', false],
            ],
            Legacy\Inheritance\FrenchAddressWithPhone::propertyMetadata()
        );

        $other = Legacy\Inheritance\FrenchAddressWithPhone::create([
            'Street' => 'foo',
            'City' => 'bar',
            'Country' => 'baz',
        ]);
        self::assertEquals($instance, $other);
    }
}
