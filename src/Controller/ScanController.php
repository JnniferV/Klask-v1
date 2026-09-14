<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ScanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ScanController extends AbstractController
{
    public const SESSION_PENDING_SCAN = 'pending_scan_token';

    public function __construct(private readonly ScanService $scanService)
    {
    }

    // scan via BarcodeDetector in-app
    #[IsGranted('ROLE_STUDENT')]
    #[Route('/scan', name: 'app_scan', methods: ['POST'])]
    public function scan(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('scan', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['ok' => false, 'error' => 'Token CSRF invalide.'], 403);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Non connecté.'], 401);
        }

        $token = trim((string) $request->request->get('token', ''));
        if ('' === $token) {
            return $this->json(['ok' => false, 'error' => 'Token manquant.'], 400);
        }

        return $this->json($this->scanService->process($user, $token));
    }

    // scan via scanner natif du tél (URL dans le QR) — PUBLIC
    // si connecté : page de confirmation légère puis redirect /map
    // si non connecté : stocke le token en session, redirect /inscription
    #[Route('/scan/qr/{token}', name: 'app_scan_qr', methods: ['GET'])]
    public function scanQr(string $token, Request $request): Response
    {
        $dest = $request->headers->get('Sec-Fetch-Dest');
        if (null !== $dest && 'document' !== $dest) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            $request->getSession()->set(self::SESSION_PENDING_SCAN, $token);

            return $this->render('scan/confirm.html.twig', [
                'ok' => false,
                'points' => 0,
                'bonus' => 0,
                'activityName' => '',
                'error' => 'Inscris-toi pour valider ce stand et gagner tes points !',
                'redirect' => $this->generateUrl('app_inscription_show'),
            ]);
        }

        $result = $this->scanService->process($user, $token);

        return $this->render('scan/confirm.html.twig', [
            'ok' => $result->ok,
            'points' => $result->points,
            'bonus' => $result->bonus,
            'activityName' => $result->activityName,
            'error' => $result->error,
            'redirect' => $this->generateUrl('app_map'),
        ]);
    }
}
