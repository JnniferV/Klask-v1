<?php

namespace App\Entity;

use App\Repository\ScanRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ScanRepository::class)]
#[ORM\UniqueConstraint(name: 'scan_user_activity_uniq', columns: ['user_id', 'activity_id'])]
class Scan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    //Immuable, non modifiable
    #[ORM\Column]
    private DateTimeImmutable $hourValidation;

    #[ORM\ManyToOne(inversedBy: 'scans')]
    #[ORM\JoinColumn(nullable: false)]
    private Activity $activity;

    #[ORM\ManyToOne(inversedBy: 'scans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    public function __construct(DateTimeImmutable $hourValidation, Activity $activity, User $user)
    {
        $this->hourValidation = $hourValidation;
        $this->activity       = $activity;
        $this->user           = $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHourValidation(): DateTimeImmutable
    {
        return $this->hourValidation;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
