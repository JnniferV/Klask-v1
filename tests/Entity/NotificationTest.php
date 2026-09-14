<?php

namespace App\Tests\Entity;

use App\Entity\Notification;
use App\Tests\Support\EntityBuilder;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    private function notification(string $recipientType, ?string $value = null): Notification
    {
        return (new Notification())
            ->setMessage('Message de test')
            ->setRecipientType($recipientType)
            ->setRecipientValue($value);
    }

    public function testTopicGeneralParDefaut(): void
    {
        $this->assertSame('event-alert', $this->notification('all')->getMercureTopic());
    }

    public function testTopicReserveAuxEleves(): void
    {
        $this->assertSame('event-alert/student', $this->notification('student')->getMercureTopic());
    }

    public function testTopicReserveAuxAccompagnateurs(): void
    {
        $this->assertSame('event-alert/accompagnateur', $this->notification('accompagnateur')->getMercureTopic());
    }

    public function testTopicCibleUneClasse(): void
    {
        $this->assertSame('event-alert/class/GRP0001', $this->notification('class', 'GRP0001')->getMercureTopic());
    }

    public function testUnTypeInconnuRetombeSurLeTopicGeneral(): void
    {
        $this->assertSame('event-alert', $this->notification('inconnu')->getMercureTopic());
    }

    public function testLePayloadMercureContientLesChampsAttendusParLeFront(): void
    {
        $notification = EntityBuilder::withId($this->notification('all'), 42)
            ->setTitle('Conférence')
            ->setLink('https://klask.test/infos')
            ->setType('warning');

        // id requis rejeu
        $this->assertSame([
            'alert' => true,
            'id' => 42,
            'title' => 'Conférence',
            'message' => 'Message de test',
            'link' => 'https://klask.test/infos',
            'type' => 'warning',
        ], $notification->toMercurePayload());
    }

    public function testUneNotificationEstMarqueeUneFoisEnvoyee(): void
    {
        $notification = $this->notification('all');
        $this->assertFalse($notification->isSent());

        $notification->setSentAt(new \DateTimeImmutable());
        $this->assertTrue($notification->isSent());
    }
}
