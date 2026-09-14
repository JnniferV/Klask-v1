<?php

namespace App\Tests\Service;

use App\Entity\Sphere;
use App\Entity\UserSphereRating;
use App\Repository\SphereRepository;
use App\Repository\UserSphereRatingRepository;
use App\Service\UserService;
use App\Tests\Support\EntityBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UserServiceTest extends TestCase
{
    /**
     * @param array<int, array{sphereId: int, rating: int}> $ratings
     * @param Sphere[]                                      $spheres
     */
    private function service(array $ratings = [], array $spheres = [], ?EntityManagerInterface $em = null): UserService
    {
        $ratingRepository = $this->createStub(UserSphereRatingRepository::class);
        $ratingRepository->method('findRatingsOrderedByScore')->willReturn($ratings);

        $sphereRepository = $this->createStub(SphereRepository::class);
        $sphereRepository->method('findBy')->willReturn($spheres);

        return new UserService($sphereRepository, $ratingRepository, $em ?? $this->entityManager());
    }

    private function entityManager(): EntityManagerInterface&Stub
    {
        $query = $this->createStub(Query::class);
        $query->method('setParameter')->willReturnSelf();
        $query->method('execute')->willReturn(0);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQuery')->willReturn($query);

        return $em;
    }

    public function testSepareLesTroisMeilleuresEtLesTroisMoinsBonnesSpheres(): void
    {
        $ratings = [
            ['sphereId' => 10, 'rating' => 1],
            ['sphereId' => 11, 'rating' => 2],
            ['sphereId' => 12, 'rating' => 3],
            ['sphereId' => 13, 'rating' => 4],
            ['sphereId' => 14, 'rating' => 5],
            ['sphereId' => 15, 'rating' => 6],
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

    public function testEnregistreUneNoteParSphereEtLesRetourneTrieesParNoteCroissante(): void
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
            'CRÉATIF' => 2,
            'RIGOUREUX' => 6,
            'UTILE' => 4,
        ]);

        $this->assertCount(3, $persisted);
        $this->assertSame([2, 4, 6], array_column($result, 'rating'));
        $this->assertSame([10, 12, 11], array_column($result, 'sphereId'));
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
