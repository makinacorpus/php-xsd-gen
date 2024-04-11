<?php

declare(strict_types=1);

namespace MakinaCorpus\XsdGen\Tests;

use MakinaCorpus\XsdGen\Generator;
use MakinaCorpus\XsdGen\Helper\EchoLogger;
use MakinaCorpus\XsdGen\Tests\Generated\Defaults;
use MakinaCorpus\XsdGen\Tests\Generated\Modern;
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

        $instance = new Defaults\Inheritance\AddressAndPhone(addressLine: 'foo', country: 'bar', phoneNumber: null);
        self::assertInstanceOf(Defaults\Inheritance\AddressAndPhone::class, $instance);
        self::assertInstanceOf(Defaults\Inheritance\Address::class, $instance);

        self::assertSame('foo', $instance->getAddressLine());
        self::assertSame('bar', $instance->getCountry());
        self::assertNull($instance->getPhoneNumber());

        self::assertSame(
            [
                'addressLine' => ['string', false],
                'country' => ['string', false],
                'phoneNumber' => ['string', false],
            ],
            Defaults\Inheritance\AddressAndPhone::propertyMetadata()
        );

        $other = Defaults\Inheritance\AddressAndPhone::create([
            'AddressLine' => 'foo',
            'Country' => 'bar',
        ]);
        self::assertEquals($instance, $other);
    }

    public function testInheritanceModern(): void
    {
        (new Generator())
            ->defaultDirectory(__DIR__ . '/Generated')
            ->defaultNamespace('MakinaCorpus\\XsdGen\\Tests\\Generated')
            ->namespace('https://schemas.makina-corpus.com/testing', 'Modern')
            ->propertyGetter(false)
            ->propertyPromotion(true)
            ->propertyPublic(true)
            ->propertyReadonly(true)
            ->logger(new EchoLogger())
            ->file(__DIR__ . '/resources/inheritance.xsd')
            ->generate()
        ;

        $instance = new Modern\Inheritance\AddressAndPhone(addressLine: 'foo', country: 'bar', phoneNumber: null);
        self::assertInstanceOf(Modern\Inheritance\AddressAndPhone::class, $instance);
        self::assertInstanceOf(Modern\Inheritance\Address::class, $instance);

        self::assertSame('foo', $instance->addressLine);
        self::assertSame('bar', $instance->country);
        self::assertNull($instance->phoneNumber);

        self::assertSame(
            [
                'addressLine' => ['string', false],
                'country' => ['string', false],
                'phoneNumber' => ['string', false],
            ],
            Modern\Inheritance\AddressAndPhone::propertyMetadata()
        );

        $other = Modern\Inheritance\AddressAndPhone::create([
            'AddressLine' => 'foo',
            'Country' => 'bar',
        ]);
        self::assertEquals($instance, $other);
    }
}
