<?php

namespace App\Tests\Service;

use App\Entity\Authority;
use App\Entity\Establishment;
use App\Entity\User;
use App\Repository\AuthorityRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Service\AppParameterService;
use App\Service\InscriptionService;
use App\Service\RealtimeNotifier;
use App\Tests\Support\EntityBuilder;
use App\Twig\AvatarExtension;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Mercure\HubInterface;

class InscriptionServiceTest extends TestCase
{
    private function service(
        ?GroupRepository $groupRepository = null,
        ?UserRepository $userRepository = null,
        ?AuthorityRepository $authorityRepository = null,
    ): InscriptionService {
        $params = $this->createStub(AppParameterService::class);
        $params->method('getInt')->willReturnCallback(static fn (string $key, int $default = 0) => $default);

        return new InscriptionService(
            $authorityRepository ?? $this->createStub(AuthorityRepository::class),
            $userRepository ?? $this->createStub(UserRepository::class),
            $groupRepository ?? $this->createStub(GroupRepository::class),
            $params,
            new RealtimeNotifier($this->createStub(HubInterface::class), $this->createStub(LoggerInterface::class)),
            new AvatarExtension(''),
            $this->createStub(Packages::class),
        );
    }

    private function establishment(int $id, string $name = 'Lycée X'): Establishment
    {
        return EntityBuilder::withId((new Establishment())->setName($name), $id);
    }

    public function testAucunMotifDeRefusQuandEtablissementEtNiveauCorrespondent(): void
    {
        $establishment = $this->establishment(1);
        $group = EntityBuilder::group();
        $group->setName('Première')->setEstablishment($establishment);

        $this->assertNull($this->service()->refusalReason($group, $establishment, 'Première'));
    }

    public function testRefusQuandLeCodeDeGroupeEstInconnu(): void
    {
        $refus = $this->service()->refusalReason(null, $this->establishment(1), 'Première');

        $this->assertSame('Code de groupe invalide ou ne correspond pas à votre établissement/classe. Vérifiez avec votre accompagnateur.', $refus);
    }

    public function testRefusQuandLeNiveauDiffere(): void
    {
        $establishment = $this->establishment(1);
        $group = EntityBuilder::group();
        $group->setName('Première')->setEstablishment($establishment);

        $refus = $this->service()->refusalReason($group, $establishment, 'Seconde');

        $this->assertStringContainsString('ne correspond pas', (string) $refus);
    }

    public function testRefusQuandLEtablissementDiffere(): void
    {
        $inscrit = $this->establishment(1, 'Lycée X');
        $autre = $this->establishment(2, 'Lycée Y');
        $group = EntityBuilder::group();
        $group->setName('Première')->setEstablishment($autre);

        $refus = $this->service()->refusalReason($group, $inscrit, 'Première');

        $this->assertStringContainsString('ne correspond pas', (string) $refus);
    }

    public function testRefusQuandLeGroupeAtteintQuaranteEleves(): void
    {
        $establishment = $this->establishment(1);
        $group = EntityBuilder::group();
        $group->setName('Première')->setEstablishment($establishment);

        $plein = $this->createStub(GroupRepository::class);
        $plein->method('countUsersByGroupId')->willReturn(40);

        $refus = $this->service($plein)->refusalReason($group, $establishment, 'Première');

        $this->assertSame('Ce groupe est complet (40 élèves maximum).', $refus);
    }

    public function testUnQuaranteEtUniemeEleveEstEncoreAccepteATrenteNeuf(): void
    {
        $establishment = $this->establishment(1);
        $group = EntityBuilder::group();
        $group->setName('Première')->setEstablishment($establishment);

        $presquePlein = $this->createStub(GroupRepository::class);
        $presquePlein->method('countUsersByGroupId')->willReturn(39);

        $this->assertNull($this->service($presquePlein)->refusalReason($group, $establishment, 'Première'));
    }

    public function testLePseudoGenereCombineUnAnimalEtUnAdjectif(): void
    {
        $pseudo = $this->service()->generateUniquePseudo();

        $this->assertMatchesRegularExpression('/^\S+ \S.*$/u', $pseudo, 'Format attendu : "Animal adjectif".');
    }

    public function testLInscriptionEchoueSansAutoriteStudentEnBase(): void
    {
        $authorityRepository = $this->createStub(AuthorityRepository::class);
        $authorityRepository->method('getByRole')->willThrowException(new \RuntimeException('Autorité "STUDENT" introuvable.'));

        $this->expectException(\RuntimeException::class);

        $this->service(null, null, $authorityRepository)->registerStudent(new User());
    }

    public function testLInscriptionAffecteLAutoriteStudentEtConserveLePseudoLibre(): void
    {
        $authority = (new Authority())->setAuthorityUser('STUDENT');
        $authorityRepository = $this->createStub(AuthorityRepository::class);
        $authorityRepository->method('getByRole')->willReturn($authority);

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findTakenPseudos')->willReturn([]);
        $userRepository->method('insertStudent')->willReturnArgument(0);

        $student = (new User())->setPseudo('Renard cosmique');
        $created = $this->service(null, $userRepository, $authorityRepository)->registerStudent($student);

        $this->assertSame($authority, $created->getAuthority());
        $this->assertSame('Renard cosmique', $created->getPseudo());
    }

    public function testUnPseudoDejaPrisEstRemplaceParUnAutre(): void
    {
        $authorityRepository = $this->createStub(AuthorityRepository::class);
        $authorityRepository->method('getByRole')->willReturn(new Authority());

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findTakenPseudos')->willReturn(['Renard cosmique']);
        $userRepository->method('insertStudent')->willReturnArgument(0);

        $student = (new User())->setPseudo('Renard cosmique');
        $created = $this->service(null, $userRepository, $authorityRepository)->registerStudent($student);

        $this->assertNotSame('Renard cosmique', $created->getPseudo());
        $this->assertNotEmpty($created->getPseudo());
    }
}
