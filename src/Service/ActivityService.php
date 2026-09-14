<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Sphere;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Uid\Uuid;

class ActivityService
{
    public const QRCODE_DIR = 'images/qrcodes';

    public function __construct(
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%env(APP_URL)%')]
        private readonly string $appUrl,
    ) {
    }

    public function initQrCode(Activity $activity): void
    {
        if (null !== $activity->getQrcodeToken()) {
            return;
        }

        $token = Uuid::v4()->toRfc4122();
        $fileName = $this->buildQrFileName($token, $activity->getSphere(), $activity->getCategory());
        $dir = $this->projectDir.'/public/'.self::QRCODE_DIR.'/';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        (new PngWriter())->write(new QrCode(rtrim($this->appUrl, '/').'/scan/qr/'.$token))->saveToFile($dir.$fileName);

        $activity->setQrcodeToken($token)->setQrcode(self::QRCODE_DIR.'/'.$fileName);
    }

    // pendant de initQrCode, sinon le PNG reste orphelin
    public function deleteQrCode(Activity $activity): void
    {
        $path = $activity->getQrcode();
        if (null === $path) {
            return;
        }

        // basename : impossible de sortir du dossier des QR
        @unlink($this->projectDir.'/public/'.self::QRCODE_DIR.'/'.basename($path));
    }

    public function createFromMap(Sphere $sphere, ActivityCategory $category, string $name, ?string $description, float $x, float $y): Activity
    {
        $activity = (new Activity())
            ->setName($name)
            ->setDescription($description)
            ->setPointX($x)
            ->setPointY($y)
            ->setSphere($sphere)
            ->setCategory($category);

        $this->initQrCode($activity);
        $this->em->persist($activity);
        $this->em->flush();

        return $activity;
    }

    private function buildQrFileName(string $token, ?Sphere $sphere, ?ActivityCategory $category): string
    {
        $slugger = new AsciiSlugger();
        $label = null !== $sphere
            ? $slugger->slug($sphere->getName())->lower()->toString()
            : match ($category?->getType()) {
                ActivityCategory::TYPE_ATELIER => 'atelier',
                ActivityCategory::TYPE_CONFERENCE => 'conference',
                null => 'activite',
                default => $slugger->slug($category->getType())->lower()->toString(),
            };

        return sprintf('act-%s-%s.png', $token, $label);
    }
}
