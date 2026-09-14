<?php

namespace App\Tests\Entity;

use App\Entity\AppParameter;
use PHPUnit\Framework\TestCase;

class AppParameterTest extends TestCase
{
    private function parameter(string $value, string $type): AppParameter
    {
        return (new AppParameter())->setParamKey('CLE')->setParamValue($value)->setParamType($type);
    }

    public function testCastEntier(): void
    {
        $this->assertSame(5, $this->parameter('5', 'integer')->getCastedValue());
    }

    public function testCastFlottant(): void
    {
        $this->assertSame(1.5, $this->parameter('1.5', 'float')->getCastedValue());
    }

    public function testCastBooleenVrai(): void
    {
        $this->assertTrue($this->parameter('true', 'boolean')->getCastedValue());
    }

    public function testCastBooleenFaux(): void
    {
        $this->assertFalse($this->parameter('false', 'boolean')->getCastedValue());
    }

    public function testUneValeurSansTypeResteUneChaine(): void
    {
        $this->assertSame('texte', $this->parameter('texte', 'string')->getCastedValue());
    }

    public function testLaDateDeMiseAJourSuitLaModificationDeValeur(): void
    {
        $parameter = $this->parameter('1', 'integer');
        $avant = $parameter->getUpdatedAt();

        $parameter->setParamValue('2');

        $this->assertGreaterThanOrEqual($avant, $parameter->getUpdatedAt());
    }
}
