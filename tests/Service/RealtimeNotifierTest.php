<?php

namespace App\Tests\Service;

use App\Service\RealtimeNotifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class RealtimeNotifierTest extends TestCase
{
    public function testLaMiseAJourEstPublieeSurLeTopicDemandeEnJson(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(
                fn(Update $update) => $update->getTopics() === ['map-update']
                    && $update->getData() === '{"activityId":7}'
            ));

        $this->assertTrue((new RealtimeNotifier($hub))->publish('map-update', ['activityId' => 7]));
    }

    public function testUnHubIndisponibleNInterromptPasLActionEnCours(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('publish')->willThrowException(new \RuntimeException('hub injoignable'));

        $this->assertFalse((new RealtimeNotifier($hub))->publish('map-update', []));
    }
}
