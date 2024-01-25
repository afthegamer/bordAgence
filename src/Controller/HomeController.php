<?php

namespace App\Controller;

use App\Service\CalendarService;
use App\Service\EmailService;
use App\Service\GoogleClientService;
use App\Service\LinkedInAuthService;
use App\Service\LinkedinClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/')]
class HomeController extends AbstractController
{


	// Constructeur avec injection de dépendances pour les services nécessaires
	public function __construct(
//		private CsrfTokenManagerInterface $csrfTokenManager,
		public LinkedinClientService $linkedinClientService,
		public LinkedInAuthService $linkedInAuthService,
		private GoogleClientService $googleClientService
	){}

	#[Route('/', name: 'app_home')]
	public function index(
		Request $request,
		SessionInterface $session,
		EmailService $emailService,
		CalendarService $calendarService
	): Response
	{
		// Obtention du token d'accès via LinkedInAuthService
		$accessTokenLinkedin = $this->linkedInAuthService->obtainOrRefreshAccessToken($request);

		// Gestion du cas où aucun token n'est obtenu
		if (!$accessTokenLinkedin) {
			return $this->redirectToRoute('linkedin_oauth');
		}
		if ($accessTokenLinkedin) {
			// Récupération des posts et des détails de l'organisation via LinkedinClientService
			$organizationId = $_ENV['LINKEDIN_ORGANIZATION_ID'];
			$postsResponse = $this->linkedinClientService->fetchOrganizationAuthorPosts(
				$accessTokenLinkedin,
				$organizationId,
				12
			);
			$organizationDetails = $this->linkedinClientService->fetchOrganizationDetails(
				$accessTokenLinkedin,
				$organizationId
			);

			// Récupération de l'URL du logo de l'organisation
			$logoUrl = $this->linkedinClientService->getOrganizationLogoUrl(
				$organizationDetails,
				$accessTokenLinkedin
			);
			$decodeLogoUrl = json_decode($logoUrl);

			// Traitement des posts
			$this->linkedinClientService->processPosts(
				$postsResponse['elements'],
				$accessTokenLinkedin
			);

			// Traitement des reshared posts
			$this->linkedinClientService->processResharedPosts(
				$postsResponse['elements'],
				$accessTokenLinkedin
			);
			$postsResponse['elements'] = $this->linkedinClientService->formatPostContent($postsResponse['elements']);
		}
		// Récupérer le token d'accès de la session
		$accessTokenCalendare = $session->get('access_token');

		// Vérifier si l'utilisateur est authentifié et dispose d'un token d'accès
		if (!$accessTokenCalendare) {
			// Si non, rediriger vers la page de login Google
			return $this->redirectToRoute('google_login');
		}


		// Mettre à jour le token d'accès pour le client Google
		$this->googleClientService->setAccessToken($accessTokenCalendare);
		// Récupérer les événements du calendrier à l'aide du CalendarService
		$events = $calendarService->getEvents();
//			dd($events);

		return $this->render('home/index.html.twig', [
			'controller_name' => 'HomeController',
			'posts' => $postsResponse['elements'],
			'organizationName' => $organizationDetails['localizedName'] ?? 'Nom Inconnu',
			'logoUrl' => $decodeLogoUrl,
			'events' => $events,
		]);
	}
}
