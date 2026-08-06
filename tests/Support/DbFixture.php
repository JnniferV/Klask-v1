<?php

namespace App\Tests\Support;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Authority;
use App\Entity\AuthorityRole;
use App\Entity\Establishment;
use App\Entity\Event;
use App\Entity\Group;
use App\Entity\Role;
use App\Entity\Scan;
use App\Entity\Sphere;
use App\Entity\User;
use App\Entity\UserSphereRating;
use Doctrine\ORM\EntityManagerInterface;

final class DbFixture
{
    private static int $seq = 0;

    private ?ActivityCategory $standCategory = null;

    public function __construct(private readonly EntityManagerInterface $em) {}

    public function student(bool $rated = false): User
    {
        $student = $this->user('STUDENT');

        if ($rated) {
            foreach ([6, 5, 4] as $rating) {
                $this->em->persist(new UserSphereRating($student, $this->sphere(), $rating));
            }
            $this->em->flush();
        }

        return $student;
    }

    /** @param string $roleName Nom en base, sans le préfixe ROLE_ (STUDENT, ACCOMPANYING, ADMIN). */
    public function user(string $roleName): User
    {
        $n         = ++self::$seq;
        $role      = (new Role())->setNameRole($roleName);
        $authority = (new Authority())->setAuthorityUser($roleName);
        $group     = (new Group())
            ->setCode('GRP' . $n)
            ->setName('Première')
            ->setColor('#000000')
            ->setScore(0)
            ->setEstablishment((new Establishment())->setName('Lycée ' . $n))
            ->setEvent((new Event())->setName('JPO ' . $n));

        $user = (new User())->setPseudo('Élève ' . $n)->setScore(0)->setAuthority($authority)->setGroup($group);
        $this->persist(
            $role,
            $authority,
            new AuthorityRole($authority, $role),
            $group->getEstablishment(),
            $group->getEvent(),
            $group,
            $user,
        );

        return $user;
    }

    public function sphere(?string $name = null): Sphere
    {
        $n      = ++self::$seq;
        $sphere = (new Sphere())->setName($name ?? 'Sphère ' . $n)->setColor(sprintf('#%06d', $n));
        $this->persist($sphere);

        return $sphere;
    }

    public function stand(string $token, int $points = 50): Activity
    {
        if ($this->standCategory === null) {
            $this->standCategory = (new ActivityCategory())->setType(ActivityCategory::TYPE_STAND)->setNbrPoints($points);
            $this->persist($this->standCategory);
        }

        $activity = (new Activity())
            ->setName('Stand ' . ++self::$seq)
            ->setQrcodeToken($token)
            ->setSphere($this->sphere())
            ->setCategory($this->standCategory);
        $this->persist($activity);

        return $activity;
    }

    public function scan(User $user, Activity $activity, string $when = 'now'): Scan
    {
        $scan = new Scan(new \DateTimeImmutable($when), $activity, $user);
        $this->persist($scan);

        return $scan;
    }

    /** @param object ...$entities */
    private function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }
}
