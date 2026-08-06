<?php

namespace App\Service\Impl;

use App\Entity\Establishment;
use App\Entity\Group;
use App\Entity\User;
use App\Repository\AuthorityRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\AppParameterService;
use App\Service\InscriptionService;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias]
class InscriptionServiceImpl implements InscriptionService
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
    ) {}

    //Tire une identité parmi les 400 combinaisons animal + accessoire encore libres
     //une seule requête quel que soit le nombre d'inscriptions simultanée et pas d'échec tant qu'il reste une combinaison
    public function generateUniquePseudo(string $preferred = ''): string
    {
        $taken = array_flip($this->userRepository->findTakenPseudos());

        if ($preferred !== '' && !isset($taken[$preferred])) {
            return $preferred;
        }

        $free = [];
        foreach (self::ANIMALS as $animal) {
            foreach (self::ADJECTIVES as $adjective) {
                $candidate = $animal . ' ' . $adjective;
                if (!isset($taken[$candidate])) {
                    $free[] = $candidate;
                }
            }
        }

        if ($free === []) {
            throw new RuntimeException('Toutes les identités sont attribuées.');
        }

        return $free[array_rand($free)];
    }

    public function findGroupByCode(string $code): ?Group
    {
        return $this->groupRepository->findByCode($code);
    }

    public function refusalReason(?Group $group, Establishment $establishment, string $level): ?string
    {
        if ($group === null) {
            return 'Code de groupe invalide. Vérifiez le code avec votre accompagnateur.';
        }

        if ($group->getEstablishment()?->getId() !== $establishment->getId() || $group->getName() !== $level) {
            return 'Ce code de groupe ne correspond pas à votre établissement ou à votre groupe de classe.';
        }

        $max = $this->params->getInt('MAX_STUDENTS_PER_GROUP', 40);
        if ($this->groupRepository->countUsersByGroupId((int) $group->getId()) >= $max) {
            return sprintf('Ce groupe est complet (%d élèves maximum).', $max);
        }

        return null;
    }

    public function registerStudent(User $student): User
    {
        $authority = $this->authorityRepository->findByRole('STUDENT');

        if ($authority === null) {
            throw new RuntimeException('Autorité STUDENT introuvable.');
        }

        $student->setAuthority($authority);
        $student->setPseudo($this->generateUniquePseudo((string) $student->getPseudo()));

        return $this->userRepository->insertStudent($student);
    }
}
