<?php

namespace App\Tests\Service;

use App\Entity\Sphere;
use App\Entity\UserSphereRating;
use App\Repository\SphereRepository;
use App\Repository\UserSphereRatingRepository;
use App\Service\Impl\UserServiceImpl;
use App\Tests\Support\EntityBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UserServiceImplTest extends TestCase
{
    /**
     * @param array<int, array{sphereId: int, rating: int}> $ratings
     * @param Sphere[] $spheres
     */
    private function service(array $ratings = [], array $spheres = [], ?EntityManagerInterface $em = null): UserServiceImpl
    {
        $ratingRepository = $this->createMock(UserSphereRatingRepository::class);
        $ratingRepository->method('findRatingsOrderedByScore')->willReturn($ratings);

        $sphereRepository = $this->createMock(SphereRepository::class);
        $sphereRepository->method('findBy')->willReturn($spheres);

        return new UserServiceImpl($sphereRepository, $ratingRepository, $em ?? $this->entityManager());
    }

    private function entityManager(): EntityManagerInterface&MockObject
    {
        $query = $this->createMock(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $query->method('execute')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQuery')->willReturn($query);

        return $em;
    }

    public function testSepareLesTroisMeilleuresEtLesTroisMoinsBonnesSpheres(): void
    {
        $ratings = [
            ['sphereId' => 10, 'rating' => 6],
            ['sphereId' => 11, 'rating' => 5],
            ['sphereId' => 12, 'rating' => 4],
            ['sphereId' => 13, 'rating' => 3],
            ['sphereId' => 14, 'rating' => 2],
            ['sphereId' => 15, 'rating' => 1],
        ];

        $result = $this->service($ratings)->getTopAndBottomSphereIds(EntityBuilder::student());

        $this->assertSame([10, 11, 12], $result['top']);
        $this->assertSame([13, 14, 15], $result['bottom']);
    }

    public function testRetourneDesListesVidesSansQuestionnaireRempli(): void
    {
        $result = $this->service()->getTopAndBottomSphereIds(EntityBuilder::student());

        $this->assertSame([], $result['top']);
        $this->assertSame([], $result['bottom']);
    }

    public function testLeQuestionnaireEstConsidereRempliDesQuUneSphereEstNotee(): void
    {
        $service = $this->service([['sphereId' => 10, 'rating' => 6]]);

        $this->assertTrue($service->hasCompletedQuestionnaire(EntityBuilder::student()));
    }

    public function testLeQuestionnaireResteAFaireSansAucuneNote(): void
    {
        $this->assertFalse($this->service()->hasCompletedQuestionnaire(EntityBuilder::student()));
    }

    public function testEnregistreUneNoteParSphereEtLesRetourneTrieesParNoteDecroissante(): void
    {
        $spheres = [
            EntityBuilder::sphere(10, 'CRÉATIF'),
            EntityBuilder::sphere(11, 'RIGOUREUX'),
            EntityBuilder::sphere(12, 'UTILE'),
        ];

        $persisted = [];
        $em = $this->entityManager();
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            if ($entity instanceof UserSphereRating) {
                $persisted[] = $entity;
            }
        });

        $result = $this->service([], $spheres, $em)->saveRatings(EntityBuilder::student(), [
            'CRÉATIF'   => 2,
            'RIGOUREUX' => 6,
            'UTILE'     => 4,
        ]);

        $this->assertCount(3, $persisted);
        $this->assertSame([6, 4, 2], array_column($result, 'rating'));
        $this->assertSame([11, 12, 10], array_column($result, 'sphereId'));
    }

    public function testLaNoteEnregistreeCorrespondBienALaSphere(): void
    {
        $sphere = EntityBuilder::sphere(10, 'CRÉATIF');

        $persisted = null;
        $em = $this->entityManager();
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });

        $this->service([], [$sphere], $em)->saveRatings(EntityBuilder::student(), ['CRÉATIF' => 5]);

        $this->assertInstanceOf(UserSphereRating::class, $persisted);
        $this->assertSame(5, $persisted->getRating());
        $this->assertInstanceOf(Sphere::class, $persisted->getSphere());
        $this->assertSame(10, $persisted->getSphere()->getId());
    }
}
