<?php

namespace App\DataFixtures;

use App\Entity\Authority;
use App\Entity\Group;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

//Crée un compte ADMIN et un compte ACCOMPANYING avec des mots de passe temporaires
class StaffUserFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->getStaffDefinitions() as [$email, $plainPassword, $authorityRef, $groupCode]) {
            /** @var Authority $authority */
            $authority = $this->getReference($authorityRef, Authority::class);

            $user = new User();
            $user->setEmail($email);
            $user->setAuthority($authority);
            $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
            if ($groupCode !== null) {
                $user->setGroup($manager->getRepository(Group::class)->findOneBy(['code' => $groupCode]));
            }

            $manager->persist($user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AuthorityFixtures::class, GroupFixtures::class];
    }

    /**
     * @return array<array{string, string, string, ?string}>
     */
    private function getStaffDefinitions(): array
    {
        return [
            ['admin@klask.fr',          'AdminKlask2026!',  AuthorityFixtures::AUTHORITY_ADMIN_REFERENCE,         null],
            ['accompagnateur@klask.fr', 'AccKlask2026!',    AuthorityFixtures::AUTHORITY_ACCOMPANYING_REFERENCE,  'GRP0002'],
        ];
    }
}
