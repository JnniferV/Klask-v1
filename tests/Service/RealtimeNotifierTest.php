<?php

namespace App\Tests\Service;

use App\Service\RealtimeNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
                fn (Update $update) => $update->getTopics() === ['map-update']
                    && '{"activityId":7}' === $update->getData()
            ));

        $notifier = new RealtimeNotifier($hub, $this->createStub(LoggerInterface::class));

        $this->assertTrue($notifier->publish('map-update', ['activityId' => 7]));
    }

    public function testUnHubIndisponibleNInterromptPasLActionEnCoursEtEstJournalise(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish')->willThrowException(new \RuntimeException('hub injoignable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $notifier = new RealtimeNotifier($hub, $logger);

        $this->assertFalse($notifier->publish('map-update', []));
    }
}
