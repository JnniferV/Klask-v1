<?php

namespace App\Service;

use App\Dto\ActiviteVisitee;
use App\Dto\BilanEleve;
use App\Dto\BilanGroupe;
use App\Entity\User;
use App\Repository\ScanRepository;
use App\Repository\UserRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

class BilanService
{
    public const MIN_SCANS_FOR_PDF = 3;
    private const MIN_DURATION_SECONDS = 3600;

    public function __construct(
        private readonly ScanRepository $scanRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function buildStudentBilan(User $user): BilanEleve
    {
        $group = $user->getGroup();
        $scans = $this->scanRepository->findByUserWithDetails($user);

        return new BilanEleve(
            $user,
            $group,
            $scans,
            $user->getScore() ?? 0,
            $group?->getScore() ?? 0,
            $this->isStudentPdfUnlocked($user, count($scans)),
        );
    }

    public function buildGroupBilan(User $user): BilanGroupe
    {
        $group = $user->getGroup();
        if (null === $group) {
            return BilanGroupe::sansGroupe();
        }

        $rows = $this->scanRepository->findGroupScanRows($group);
        $spheresStats = [];
        $activitiesStats = [];

        foreach ($rows as $row) {
            $sphereName = (string) $row['sphereName'];
            $activityName = (string) $row['activityName'];
            $categoryType = (string) $row['categoryType'];

            $spheresStats[$sphereName] = ($spheresStats[$sphereName] ?? 0) + 1;

            $activitiesStats[$activityName] = isset($activitiesStats[$activityName])
                ? $activitiesStats[$activityName]->avecUnEleveDePlus()
                : new ActiviteVisitee($categoryType, $row['activityDescription'], 1);
        }

        uasort($activitiesStats, static fn (ActiviteVisitee $a, ActiviteVisitee $b): int => $b->count <=> $a->count);

        $totalScans = count($rows);

        return new BilanGroupe(
            true,
            $group,
            $this->userRepository->countStudentsByGroup($group),
            $group->getScore() ?? 0,
            $totalScans,
            $spheresStats,
            $activitiesStats,
            $totalScans >= self::MIN_SCANS_FOR_PDF,
        );
    }

    public function createPdfResponse(string $html, string $filename): Response
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function isStudentPdfUnlocked(User $user, int $scanCount): bool
    {
        if ($scanCount >= self::MIN_SCANS_FOR_PDF) {
            return true;
        }

        if (0 === $scanCount) {
            return false;
        }

        $firstScanAt = $this->scanRepository->findFirstAt($user);
        if (null === $firstScanAt) {
            return false;
        }

        return (new \DateTimeImmutable())->getTimestamp() - $firstScanAt->getTimestamp() >= self::MIN_DURATION_SECONDS;
    }
}
