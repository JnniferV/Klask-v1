<?php

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Sphere;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

class ActivityFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly KernelInterface $kernel,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {}

    /**
     * 1 stand par sphère (6 total) — chaque stand a plusieurs intervenants (décrits dans description).
     * Coordonnées (x%, y%) centrées sur la sphère correspondante.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    private const STANDS = [
        // [nom, description, sphère, x%, y%] — coordonnées calibrées pour carte-simple.svg (portrait)
        // Zone d'exposition principale : X 30–95%, Y 5–65%  — à affiner via /admin/placement
        ['Stand CRÉATIF',      "Atelier céramique, design graphique et architecture. 3-4 intervenants métiers créatifs.",              'CRÉATIF',     45.0, 18.0],
        ['Stand RIGOUREUX',    "Comptabilité, droit et audit. 3-4 intervenants métiers de la gestion et du droit.",                    'RIGOUREUX',   65.0, 18.0],
        ['Stand NOUVEAUTÉ',    "Tech & IA, cybersécurité et réalité virtuelle. 3-4 intervenants du numérique.",                        'NOUVEAUTÉ',   85.0, 18.0],
        ['Stand EXTÉRIEUR',    "Environnement, agriculture et sport. 3-4 intervenants métiers de terrain.",                            'EXTÉRIEUR',   45.0, 42.0],
        ['Stand COMMUNIQUER',  "RH, journalisme et réseaux sociaux. 3-4 intervenants métiers de la communication.",                    'COMMUNIQUER', 65.0, 42.0],
        ['Stand UTILE',        "Soins, éducation et sécurité. 3-4 intervenants métiers du service à la personne.",                     'UTILE',       85.0, 42.0],
    ];

    /**
     * Activités hors sphère — Atelier et Conférence.
     * L'horaire (beginningHourCategory) est renseigné par l'admin en dernier moment.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    private const STANDALONE = [
        // [nom, description, categoryRef, x%, y%]
        ['Atelier Qui est-ce ?', "Atelier interactif : devine le métier caché derrière des indices. Seul ou en groupe, 15 min chrono.", ActivityCategoryFixtures::CATEGORY_ATELIER_REFERENCE,    65.0, 28.0],
        ['Conférence Métiers',   "Conférence plénière de 20 min : panorama des secteurs qui recrutent et témoignages de pros.",          ActivityCategoryFixtures::CATEGORY_CONFERENCE_REFERENCE, 65.0, 58.0],
    ];

    public function load(ObjectManager $manager): void
    {
        $writer    = new PngWriter();
        $outputDir = $this->kernel->getProjectDir() . '/public/images/qrcodes/';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }

        // Supprime les QR obsolètes avant régénération (évite l'accumulation à chaque fixtures:load)
        foreach (glob($outputDir . 'act-*.png') ?: [] as $old) {
            unlink($old);
        }

        $standCategory = $this->getReference(ActivityCategoryFixtures::CATEGORY_STAND_REFERENCE, ActivityCategory::class);

        // 6 stands (1 par sphère)
        foreach (self::STANDS as [$name, $description, $sphereName, $x, $y]) {
            $sphere   = $this->getReference('sphere_' . $sphereName, Sphere::class);
            $activity = $this->makeActivity($name, $description, $standCategory, $x, $y, $writer, $outputDir, $sphere);
            $activity->setSphere($sphere);
            $manager->persist($activity);
        }

        // Atelier + Conférence (standalone, sans sphère)
        foreach (self::STANDALONE as [$name, $description, $categoryRef, $x, $y]) {
            $category = $this->getReference($categoryRef, ActivityCategory::class);
            $activity = $this->makeActivity($name, $description, $category, $x, $y, $writer, $outputDir);
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
        PngWriter $writer,
        string $outputDir,
        ?Sphere $sphere = null,
    ): Activity {
        $token    = Uuid::v4()->toRfc4122();
        $fileName = $this->buildQrFileName($token, $sphere, $category);

        // Le QR contient l'URL de validation — le scanner natif du téléphone l'ouvre directement
        $writer->write(new QrCode(rtrim($this->appUrl, '/') . '/scan/qr/' . $token))->saveToFile($outputDir . $fileName);

        $activity = new Activity();
        $activity->setName($name);
        $activity->setDescription($description);
        $activity->setCategory($category);
        $activity->setPointX($x);
        $activity->setPointY($y);
        $activity->setQrcodeToken($token);
        $activity->setQrcode('images/qrcodes/' . $fileName);

        return $activity;
    }

    /**
     * Nom de fichier : act-{uuid}-{sphere|catégorie}.png
     * Le token UUID en base reste inchangé ; seul le nom du fichier PNG est enrichi.
     */
    private function buildQrFileName(string $token, ?Sphere $sphere, ActivityCategory $category): string
    {
        $slugger = new AsciiSlugger();
        $label   = $sphere !== null
            ? $slugger->slug($sphere->getName())->lower()->toString()
            : match ($category->getType()) {
                ActivityCategory::TYPE_ATELIER    => 'atelier',
                ActivityCategory::TYPE_CONFERENCE => 'conference',
                default                           => $slugger->slug($category->getType())->lower()->toString(),
            };

        return sprintf('act-%s-%s.png', $token, $label);
    }

    public function getDependencies(): array
    {
        return [
            ActivityCategoryFixtures::class,
            SphereFixtures::class,
        ];
    }
}
