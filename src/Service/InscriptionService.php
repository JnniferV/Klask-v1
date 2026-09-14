<?php

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\Group;
use App\Entity\User;
use App\Repository\AuthorityRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Twig\AvatarExtension;
use Symfony\Component\Asset\Packages;

class InscriptionService
{
    private const ANIMALS = [
        'Dauphin', 'Goéland', 'Cormoran', 'Aigrette', 'Phoque', 'Hermine',
        'Coccinelle', 'Ragondin', 'Chevreuil', 'Sanglier',
        'Renard', 'Requin', 'Oursin', 'Crevette', 'Crabe',
        'Mérou', 'Sauterelle', 'Escargot', 'Crapaud', 'Salamandre',
    ];

    private const ADJECTIVES = [
        'du rêve', 'cosmique', 'magique', 'intrépide', 'cyber',
        'casse-cou', 'chic', 'perplexe', 'à lunettes', 'gastronome',
        'scolaire', 'globe-trotter', 'de la royauté', 'aquatique',
        'musicos', 'excentrique', 'des îles', 'cool', 'aristocrate', 'héroïque',
    ];

    public function __construct(
        private readonly AuthorityRepository $authorityRepository,
        private readonly UserRepository $userRepository,
        private readonly GroupRepository $groupRepository,
        private readonly AppParameterService $params,
        private readonly RealtimeNotifier $notifier,
        private readonly AvatarExtension $avatars,
        private readonly Packages $packages,
    ) {
    }

    public function generateUniquePseudo(string $preferred = ''): string
    {
        $taken = array_flip($this->userRepository->findTakenPseudos());

        if ('' !== $preferred && !isset($taken[$preferred])) {
            return $preferred;
        }

        $free = array_filter($this->allPseudos(), static fn (string $p): bool => !isset($taken[$p]));

        if ([] === $free) {
            throw new \RuntimeException('Toutes les identités sont attribuées.');
        }

        return $free[array_rand($free)];
    }

    /** @return string[] */
    public function allPseudos(): array
    {
        $pseudos = [];
        foreach (self::ANIMALS as $animal) {
            foreach (self::ADJECTIVES as $adjective) {
                $pseudos[] = $animal.' '.$adjective;
            }
        }

        return $pseudos;
    }

    public function findGroupByCode(string $code): ?Group
    {
        return $this->groupRepository->findByCode($code);
    }

    // anti-énumération codes
    public function refusalReason(?Group $group, Establishment $establishment, string $level): ?string
    {
        if (null === $group
            || $group->getEstablishment()?->getId() !== $establishment->getId()
            || $group->getName() !== $level
        ) {
            return 'Code de groupe invalide ou ne correspond pas à votre établissement/classe. Vérifiez avec votre accompagnateur.';
        }

        $max = $this->params->getInt('MAX_STUDENTS_PER_GROUP', 40);
        if ($this->groupRepository->countUsersByGroupId((int) $group->getId()) >= $max) {
            return sprintf('Ce groupe est complet (%d élèves maximum).', $max);
        }

        return null;
    }

    public function registerStudent(User $student): User
    {
        $student->setAuthority($this->authorityRepository->getByRole('STUDENT'));
        $student->setPseudo($this->generateUniquePseudo((string) $student->getPseudo()));

        $created = $this->userRepository->insertStudent($student);

        if (($code = $created->getGroup()?->getCode()) !== null) {
            $this->notifier->publish('group-score/'.$code, ['newStudent' => [
                'id' => $created->getId(),
                'pseudo' => $created->getPseudo(),
                'score' => $created->getScore() ?? 0,
                'avatar' => $this->packages->getUrl($this->avatars->avatar($created->getPseudo(), 'petitvaisseau')),
            ]]);
        }

        return $created;
    }
}
