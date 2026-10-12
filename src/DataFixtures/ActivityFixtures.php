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
    // un stand par sphère, posé au centre
    private const STANDS = [
        // nom, description, sphère, puis position x et y en %
        // plan utile x 18,7-81,2 et y 12,1-94,3
        // grille 3x2 dans le hall, à régler dans l'admin
        // titre de l'affiche, description avec les intervenants
        ['J\'aime créer de mes mains, je suis créatif',
            'Journée entière : Boucher seul - Presta Job · Expert en sellerie - Sellerie Clément · Aérogommage brocante - Aérogom & Broc · Technicien usinage - FH Industrie. Après-midi : Mécanicien Bus - QUB · Couvreur - LE MENN · Photographe - MUNOZ · Vidéaste - QBO · Graphiste & Web designer - Maison Cadiou.',
            'CRÉATIF', 46.0, 42.0],
        ['Je suis rigoureux',
            'Journée entière : Assureur - Sarrut · Ingénieur qualité - Atmus · RH - Cadiou + Saveol · Resp. Admin et Juridique - TiKerneRH. Après-midi : Conseillère en gestion de patrimoine - Corre Finance · Métiers de la production - Chancerelle.',
            'RIGOUREUX', 60.0, 42.0],
        ['J\'aime la nouveauté',
            'Journée entière : Ingénieur - Anritsu · Chargé d\'affaire - TiKerneRH · Métiers du recrutement - ASTEN. Après-midi : Audioprothésiste - Audika.',
            'NOUVEAUTÉ', 74.0, 42.0],
        ['J\'aime être en extérieur',
            'Après-midi : Pisciniers - Desjoyaux · Maéva Parent Couverture - Couvreur · Agriculteur - Gwénaël LE BERRE.',
            'EXTÉRIEUR', 46.0, 62.0],
        ['J\'aime communiquer',
            'Journée entière : Location Vente Réparation - Locaciem · Acheteur - Atmus · Chef de projet - Produit en Bretagne · Responsable RH - ENGIE · Appui manager - ENGIE. Après-midi : Resp. Dev Commercial - Samsic · Eco agent (ambassadeur du tri) - QBO · Chargée de communication - QBO.',
            'COMMUNIQUER', 60.0, 62.0],
        ['J\'aime me sentir utile, j\'aime les gens',
            'Journée entière : Educatrice familiale - Action Enfance · Musicothérapeute & Formatrice - Comunissons · Educatrice spécialisée - Masse Trevidy. Après-midi : Conducteur Bus - QUB RATP DEV · Infirmier - STC.',
            'UTILE', 74.0, 62.0],
    ];

    /**
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    // hors sphère, l'admin met l'horaire au dernier moment
    private const STANDALONE = [
        // nom, description, catégorie, puis position x et y
        // salles du bas, loin des sphères du hall
        ['Atelier Qui est-ce ?', 'Atelier interactif : devine le métier caché derrière des indices. Seul ou en groupe, 15 min chrono.', ActivityCategoryFixtures::CATEGORY_ATELIER_REFERENCE,    50.0, 77.0],
        ['Conférence Métiers',   'Conférence plénière de 20 min : panorama des secteurs qui recrutent et témoignages de pros.',          ActivityCategoryFixtures::CATEGORY_CONFERENCE_REFERENCE, 68.0, 77.0],
        // bar au milieu à gauche, à régler dans l'admin
        ['Point pause — BAR',    'Point pause au bar.',                                                                                   ActivityCategoryFixtures::CATEGORY_PAUSE_REFERENCE,      30.0, 52.0],
    ];

    public function load(ObjectManager $manager): void
    {
        $outputDir = $this->kernel->getProjectDir().'/public/'.ActivityService::QRCODE_DIR.'/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // on vire les vieux QR avant d'en refaire
        foreach (glob($outputDir.'act-*.png') ?: [] as $old) {
            unlink($old);
        }

        $standCategory = $this->getReference(ActivityCategoryFixtures::CATEGORY_STAND_REFERENCE, ActivityCategory::class);

        // les 6 stands, un par sphère
        foreach (self::STANDS as [$name, $description, $sphereName, $x, $y]) {
            $sphere = $this->getReference('sphere_'.$sphereName, Sphere::class);
            $activity = $this->makeActivity($name, $description, $standCategory, $x, $y);
            $activity->setSphere($sphere);
            $this->activityService->initQrCode($activity);
            $manager->persist($activity);
        }

        // atelier, conférence et pause, sans aucune sphère
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
