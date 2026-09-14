<?php

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Sphere;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use App\Service\ActivityService;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpKernel\KernelInterface;

class ActivityFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ActivityService $activityService,
    ) {
    }

    /**
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    // 1 stand par sphère, coordonnées centrées sur la sphère
    private const STANDS = [
        // [nom, description, sphère, x%, y%] du cadre image, pas du bâtiment
        // le plan mesuré n'occupe que x 18,7–81,2 % et y 12,1–94,3 %
        // grille 3x2 dans le hall, réglage fin via /admin
        ['Stand CRÉATIF',      'Atelier céramique, design graphique et architecture. 3-4 intervenants métiers créatifs.',              'CRÉATIF',     46.0, 42.0],
        ['Stand RIGOUREUX',    'Comptabilité, droit et audit. 3-4 intervenants métiers de la gestion et du droit.',                    'RIGOUREUX',   60.0, 42.0],
        ['Stand NOUVEAUTÉ',    'Tech & IA, cybersécurité et réalité virtuelle. 3-4 intervenants du numérique.',                        'NOUVEAUTÉ',   74.0, 42.0],
        ['Stand EXTÉRIEUR',    'Environnement, agriculture et sport. 3-4 intervenants métiers de terrain.',                            'EXTÉRIEUR',   46.0, 62.0],
        ['Stand COMMUNIQUER',  'RH, journalisme et réseaux sociaux. 3-4 intervenants métiers de la communication.',                    'COMMUNIQUER', 60.0, 62.0],
        ['Stand UTILE',        'Soins, éducation et sécurité. 3-4 intervenants métiers du service à la personne.',                     'UTILE',       74.0, 62.0],
    ];

    /**
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    // hors sphère, horaire saisi par l'admin au dernier moment
    private const STANDALONE = [
        // [nom, description, categoryRef, x%, y%]
        // salles du bas, hors du hall pour éviter les sphères
        ['Atelier Qui est-ce ?', 'Atelier interactif : devine le métier caché derrière des indices. Seul ou en groupe, 15 min chrono.', ActivityCategoryFixtures::CATEGORY_ATELIER_REFERENCE,    50.0, 77.0],
        ['Conférence Métiers',   'Conférence plénière de 20 min : panorama des secteurs qui recrutent et témoignages de pros.',          ActivityCategoryFixtures::CATEGORY_CONFERENCE_REFERENCE, 68.0, 77.0],
    ];

    public function load(ObjectManager $manager): void
    {
        $outputDir = $this->kernel->getProjectDir().'/public/'.ActivityService::QRCODE_DIR.'/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // supprime les QR obsolètes avant régénération
        foreach (glob($outputDir.'act-*.png') ?: [] as $old) {
            unlink($old);
        }

        $standCategory = $this->getReference(ActivityCategoryFixtures::CATEGORY_STAND_REFERENCE, ActivityCategory::class);

        // 6 stands (1 par sphère)
        foreach (self::STANDS as [$name, $description, $sphereName, $x, $y]) {
            $sphere = $this->getReference('sphere_'.$sphereName, Sphere::class);
            $activity = $this->makeActivity($name, $description, $standCategory, $x, $y);
            $activity->setSphere($sphere);
            $this->activityService->initQrCode($activity);
            $manager->persist($activity);
        }

        // atelier + conférence standalone, sans sphère
        foreach (self::STANDALONE as [$name, $description, $categoryRef, $x, $y]) {
            $category = $this->getReference($categoryRef, ActivityCategory::class);
            $activity = $this->makeActivity($name, $description, $category, $x, $y);
            $this->activityService->initQrCode($activity);
            $manager->persist($activity);
        }

        $manager->flush();
    }

    private function makeActivity(
        string $name,
        string $description,
        ActivityCategory $category,
        float $x,
        float $y,
    ): Activity {
        $activity = new Activity();
        $activity->setName($name);
        $activity->setDescription($description);
        $activity->setCategory($category);
        $activity->setPointX($x);
        $activity->setPointY($y);

        return $activity;
    }


    public function getDependencies(): array
    {
        return [
            ActivityCategoryFixtures::class,
            SphereFixtures::class,
        ];
    }
}
