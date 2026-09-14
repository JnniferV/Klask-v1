<?php

namespace App\Repository;

use App\Entity\Group;
use App\Entity\User;
use App\Security\RoleSecurity;
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

    public function findByIdentifierEager(string $identifier): ?User
    {
        return $this->createQueryBuilder('u')
            ->addSelect('g', 'e', 'a', 'ar', 'r')
            ->leftJoin('u.group', 'g')
            ->leftJoin('g.establishment', 'e')
            ->leftJoin('u.authority', 'a')
            ->leftJoin('a.authorityRoles', 'ar')  // évite lazy load
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

    /** @return array<int, array{id: int, pseudo: string, score: int, blockedUntil: ?\DateTimeImmutable, lastScanAt: ?int}> */
    public function findStudentScoresByGroup(Group $group): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.id, u.pseudo, COALESCE(u.score, 0) AS score, u.blockedUntil, u.createdAt, MAX(s.hourValidation) AS lastScanAt')
            ->join('u.authority', 'a')
            ->leftJoin('u.scans', 's')
            ->where('u.group = :group')
            ->andWhere('a.authorityUser = :role')
            ->andWhere('u.pseudo IS NOT NULL')
            ->setParameter('group', $group)
            ->setParameter('role', RoleSecurity::STUDENT->value)
            ->groupBy('u.id', 'u.pseudo', 'u.score', 'u.blockedUntil', 'u.createdAt')
            ->orderBy('score', 'DESC')
            ->getQuery()
            ->getScalarResult();

        $eventStart = $group->getEvent()?->getBeginningHourEvent()?->getTimestamp() ?? 0;

        // casts explicites
        $toDate = static fn (?string $v): ?\DateTimeImmutable => $v ? new \DateTimeImmutable($v) : null;

        return array_map(static function (array $r) use ($eventStart, $toDate): array {
            $lastScan = $toDate($r['lastScanAt']);
            $since = max($toDate($r['createdAt'])?->getTimestamp() ?? 0, $eventStart);

            return [
                'id' => (int) $r['id'],
                'pseudo' => (string) $r['pseudo'],
                'score' => (int) $r['score'],
                'blockedUntil' => $toDate($r['blockedUntil']),
                'lastScanAt' => $lastScan?->getTimestamp() ?? ($since ?: null),
            ];
        }, $rows);
    }

    /** @return list<array{pseudo: mixed, score: mixed, groupName: mixed, scanCount: mixed}> */
    public function findTopStudents(int $limit = 10): array
    {
        /** @var list<array{pseudo: mixed, score: mixed, groupName: mixed, scanCount: mixed}> */
        $rows = $this->createQueryBuilder('u')
            ->select('u.pseudo', 'COALESCE(u.score, 0) AS score', 'g.name AS groupName', 'COUNT(s.id) AS scanCount')
            ->join('u.authority', 'a')
            ->leftJoin('u.group', 'g')
            ->leftJoin('u.scans', 's')
            ->where('a.authorityUser = :role')
            ->setParameter('role', RoleSecurity::STUDENT->value)
            ->groupBy('u.id', 'g.name')
            ->orderBy('score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return $rows;
    }

    /** @return string[] */
    public function findTakenPseudos(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.pseudo')
            ->where('u.pseudo IS NOT NULL')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function findStudentInGroup(int $id, Group $group): ?User
    {
        return $this->findOneBy(['id' => $id, 'group' => $group]);
    }

    public function countStudentsByGroup(Group $group): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->join('u.authority', 'a')
            ->where('u.group = :group')
            ->andWhere('a.authorityUser = :role')
            ->setParameter('group', $group)
            ->setParameter('role', RoleSecurity::STUDENT->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<array{establishment: mixed, level: mixed, students: mixed}> */
    public function findStudentsByLevelAndEstablishment(): array
    {
        /** @var list<array{establishment: mixed, level: mixed, students: mixed}> */
        $rows = $this->createQueryBuilder('u')
            ->select(
                "COALESCE(e.name, 'Sans établissement') AS establishment",
                "COALESCE(g.name, 'Sans niveau') AS level",
                'COUNT(u.id) AS students',
            )
            ->leftJoin('u.group', 'g')
            ->leftJoin('g.establishment', 'e')
            ->join('u.authority', 'a')
            ->where('a.authorityUser = :role')
            ->setParameter('role', RoleSecurity::STUDENT->value)
            ->groupBy('e.id', 'g.id')
            ->orderBy('establishment', 'ASC')
            ->addOrderBy('level', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return $rows;
    }

    /** @return User[] */
    public function findByAuthorityRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.authority', 'a')
            ->where('a.authorityUser = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getResult();
    }
}
