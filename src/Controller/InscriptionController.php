<?php

namespace App\Controller;

use App\Entity\Establishment;
use App\Entity\User;
use App\Form\InscriptionFormType;
use App\Service\InscriptionService;
use App\Service\ScanService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

class InscriptionController extends AbstractController
{
    private const SESSION_PSEUDO = 'inscription_pseudo';

    public function __construct(
        private readonly LoggerInterface   $logger,
        private readonly InscriptionService $inscriptionService,
        private readonly ScanService        $scanService,
        private readonly Security           $security,
    ) {}

    #[Route('/inscription', name: 'app_inscription_show', methods: ['GET'])]
    public function show(Request $request): Response
    {
        // une seule co par élève pour toute la journée : déjà inscrit = plus d'accès au formulaire
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_map');
        }

        $session = $request->getSession();
        $pseudo  = $session->get(self::SESSION_PSEUDO);

        if (!is_string($pseudo) || $pseudo === '') {
            $pseudo = $this->inscriptionService->generateUniquePseudo();
            $session->set(self::SESSION_PSEUDO, $pseudo);
        }

        return $this->renderForm($this->buildForm(new User(), $pseudo));
    }

    #[Route('/inscription/save', name: 'app_inscription_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_map');
        }

        $session = $request->getSession();
        $pseudo  = (string) $session->get(self::SESSION_PSEUDO);
        $user    = (new User())->setPseudo($pseudo);
        $form    = $this->buildForm($user, $pseudo);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderForm($form);
        }

        $establishment = $form->get('establishment')->getData();
        if (!$establishment instanceof Establishment) {
            return $this->refuse('Veuillez sélectionner un établissement.', $form);
        }

        $group  = $this->inscriptionService->findGroupByCode(trim((string) $user->getGroupCode()));
        $refus  = $this->inscriptionService->refusalReason($group, $establishment, (string) $form->get('groupLevel')->getData());

        if ($refus !== null) {
            return $this->refuse($refus, $form);
        }

        $user->setGroup($group);

        try {
            $created = $this->inscriptionService->registerStudent($user);
            $session->remove(self::SESSION_PSEUDO);
            $this->security->login($created, 'form_login', 'main');
            $this->addFlash('success', sprintf(
                'Bienvenue, %s ! Tu rejoins le groupe %s (%s).',
                $created->getPseudo(),
                $group?->getName() ?? '',
                $establishment->getName()
            ));

            $this->flashPendingScan($created, $session);

            return $this->redirectToRoute('app_bienvenue');
        } catch (Throwable $e) {
            $this->logger->error('Erreur création utilisateur', ['exception' => $e]);

            return $this->refuse('Erreur lors de la création. Veuillez réessayer.', $form);
        }
    }

    private function buildForm(User $user, string $pseudo): FormInterface
    {
        return $this->createForm(InscriptionFormType::class, $user, ['nom_depart' => $pseudo]);
    }

    // valide le QR scanné avant l'inscription (stocké en session par ScanController)
    private function flashPendingScan(User $student, SessionInterface $session): void
    {
        $token = $session->get(ScanController::SESSION_PENDING_SCAN);
        if (!is_string($token) || $token === '') {
            return;
        }

        $session->remove(ScanController::SESSION_PENDING_SCAN);
        $result = $this->scanService->process($student, $token);

        $this->addFlash(
            $result['ok'] ? 'success' : 'warning',
            $result['ok']
                ? '+' . ($result['points'] + $result['bonus']) . ' pts — ' . $result['activityName'] . ' ✓'
                : ($result['error'] ?? 'QR invalide.')
        );
    }

    // réaffiche le formulaire avec le refus
    private function refuse(string $message, FormInterface $form): Response
    {
        $this->addFlash('error', $message);

        return $this->renderForm($form);
    }

    private function renderForm(FormInterface $form): Response
    {
        // no-store : le pseudo est attribué une fois pour toute
        $response = $this->render('inscription/inscription.html.twig', [
            'inscriptionForm' => $form->createView(),
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
