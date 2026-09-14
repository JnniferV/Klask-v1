<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\BilanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BilanController extends AbstractController
{
    public function __construct(
        private readonly BilanService $bilanService,
        private readonly RateLimiterFactoryInterface $bilanPdfLimiter,
    ) {
    }

    #[IsGranted('ROLE_STUDENT')]
    #[Route('/bilan/eleve', name: 'app_bilan_eleve', methods: ['GET'])]
    public function student(): Response
    {
        return $this->noStore($this->render('bilan/eleve.html.twig', ['bilan' => $this->bilanService->buildStudentBilan($this->currentUser())]));
    }

    #[IsGranted('ROLE_STUDENT')]
    #[Route('/bilan/eleve/pdf', name: 'app_bilan_eleve_pdf', methods: ['GET'])]
    public function studentPdf(): Response
    {
        $user = $this->currentUser();
        $this->throttlePdf($user);
        $data = $this->bilanService->buildStudentBilan($user);
        if (!$data->isPdfUnlocked) {
            $this->addFlash('error', 'Visite au moins 3 stands ou participe pendant 1 heure pour débloquer ton bilan PDF.');

            return $this->redirectToRoute('app_bilan_eleve');
        }

        $safeFilename = 'Mon_Bilan_AJE29_'.preg_replace('/[^A-Za-z0-9\-]/', '_', $user->getUserIdentifier()).'.pdf';
        $html = $this->renderView('bilan/eleve_pdf.html.twig', ['bilan' => $data]);

        return $this->bilanService->createPdfResponse($html, $safeFilename);
    }

    #[IsGranted('ROLE_ACCOMPANYING')]
    #[Route('/bilan/groupe', name: 'app_bilan_groupe', methods: ['GET'])]
    public function group(): Response
    {
        return $this->noStore($this->render('bilan/accompagnateur.html.twig', ['bilan' => $this->bilanService->buildGroupBilan($this->currentUser())]));
    }

    #[IsGranted('ROLE_ACCOMPANYING')]
    #[Route('/bilan/groupe/pdf', name: 'app_bilan_groupe_pdf', methods: ['GET'])]
    public function groupPdf(): Response
    {
        $user = $this->currentUser();
        $this->throttlePdf($user);
        $data = $this->bilanService->buildGroupBilan($user);
        if (!$data->hasGroup) {
            return $this->redirectToRoute('app_map');
        }

        if (!$data->isPdfUnlocked) {
            $this->addFlash('error', 'Le bilan PDF nécessite au moins 3 scans validés par la classe.');

            return $this->redirectToRoute('app_bilan_groupe');
        }

        $safeFilename = 'Bilan_Groupe_'.preg_replace('/[^A-Za-z0-9\-]/', '_', (string) $data->group).'.pdf';
        $html = $this->renderView('bilan/accompagnateur_pdf.html.twig', ['bilan' => $data, 'accompanying' => $user]);

        return $this->bilanService->createPdfResponse($html, $safeFilename);
    }

    private function throttlePdf(User $user): void
    {
        if (!$this->bilanPdfLimiter->create($user->getUserIdentifier())->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException();
        }
    }

    // pseudo, établissement, classe et parcours à l'écran : jamais dans le cache du navigateur
    private function noStore(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    // isGranted garantit déjà un user connecté
    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
