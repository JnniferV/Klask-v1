<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[Assert\Expression(
    "this.getRecipientType() not in ['class', 'establishment'] or this.getRecipientValue()",
    message: 'Renseignez le code classe ou l\'ID établissement selon le destinataire choisi.',
)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, options: ['default' => ''])]
    private string $title = '';

    #[ORM\Column(length: 255)]
    private string $message;

    #[Assert\Url(protocols: ['http', 'https'])]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(length: 20, options: ['default' => 'info'])]
    private string $type = 'info';

    #[ORM\Column(length: 20)]
    private string $recipientType = 'all';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $recipientValue = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return mb_strimwidth($this->message, 0, 60, '…');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function setLink(?string $link): static
    {
        $this->link = $link;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toMercurePayload(): array
    {
        return [
            'alert' => true,
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->message,
            'link' => $this->link,
            'type' => $this->type,
        ];
    }

    public function getRecipientType(): string
    {
        return $this->recipientType;
    }

    public function setRecipientType(string $recipientType): static
    {
        $this->recipientType = $recipientType;

        return $this;
    }

    public function getRecipientValue(): ?string
    {
        return $this->recipientValue;
    }

    public function setRecipientValue(?string $recipientValue): static
    {
        $this->recipientValue = $recipientValue;

        return $this;
    }

    public function getMercureTopic(): string
    {
        return match ($this->recipientType) {
            'student' => 'event-alert/student',
            'accompagnateur' => 'event-alert/accompagnateur',
            'class' => 'event-alert/class/'.($this->recipientValue ?? ''),
            'establishment' => 'event-alert/establishment/'.($this->recipientValue ?? ''),
            default => 'event-alert',
        };
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isSent(): bool
    {
        return null !== $this->sentAt;
    }
}
