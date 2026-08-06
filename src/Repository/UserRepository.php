<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // charge user par email/pseudo avec tous les JOIN pour AppUserProvider (1 requête/session)
    public function findByIdentifierEager(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->addSelect('g', 'e', 'a', 'ar', 'r')
            ->leftJoin('u.group', 'g')
            ->leftJoin('g.establishment', 'e')
            ->leftJoin('u.authority', 'a')
            ->leftJoin('a.authorityRoles', 'ar')  // évite lazy load sur getRoles
            ->leftJoin('ar.role', 'r')
            ->where('u.email = :id OR u.pseudo = :id')
            ->setParameter('id', $identifier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function insertStudent(User $user): User
    {
        $em = $this->getEntityManager();
        $em->persist($user);
        $em->flush();

        return $user;
    }

    // Élèves d'un groupe avec leur score
    /** @return array<int, array{id: int, pseudo: string, score: int}> */
    public function findStudentScoresByGroup(Group $group): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.id, u.pseudo, COALESCE(u.score, 0) AS score')
            ->where('u.group = :group')
            ->setParameter('group', $group)
            ->orderBy('score', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn(array $r) => [
            'id'    => (int) $r['id'],
            'pseudo' => (string) $r['pseudo'],
            'score' => (int) $r['score'],
        ], $rows);
    }

    public function findTopStudents(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.pseudo', 'COALESCE(u.score, 0) AS score', 'g.name AS groupName', 'COUNT(s.id) AS scanCount')
            ->leftJoin('u.group', 'g')
            ->leftJoin('u.scans', 's')
            ->where('u.pseudo IS NOT NULL')
            ->groupBy('u.id', 'g.name')
            ->orderBy('score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();
    }

    /**
     * por les pseudos déjà attribués
     * @return string[]
     */
    public function findTakenPseudos(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.pseudo')
            ->where('u.pseudo IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
