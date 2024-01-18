<?php

namespace App\Controller;

use App\Service\LinkedInAuthService;
use App\Service\LinkedinClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class LinkedInController extends AbstractController
{
	// Constructeur avec injection de dépendances pour les services nécessaires
	public function __construct(
		private CsrfTokenManagerInterface $csrfTokenManager,
		public LinkedinClientService $linkedinClientService,
		public LinkedInAuthService $linkedInAuthService
	){}

	// Route pour récupérer et afficher les posts LinkedIn
	#[Route('/linkedin/posts', name: 'linkedin_posts')]
	public function fetchLinkedInPosts(Request $request): Response
	{
		// Obtention du token d'accès via LinkedInAuthService
		$accessToken = $this->linkedInAuthService->obtainOrRefreshAccessToken($request);

		// Gestion du cas où aucun token n'est obtenu
		if (!$accessToken) {
			return $this->redirectToRoute('linkedin_oauth');
		}

		// Récupération des posts et des détails de l'organisation via LinkedinClientService
		$organizationId = $_ENV['LINKEDIN_ORGANIZATION_ID'];
		$postsResponse = $this->linkedinClientService->fetchOrganizationPosts($accessToken, $organizationId);
		$organizationDetails = $this->linkedinClientService->fetchOrganizationDetails($accessToken, $organizationId);

		// Récupération de l'URL du logo de l'organisation
		$logoUrl = $this->linkedinClientService->getOrganizationLogoUrl($organizationDetails, $accessToken);
		$decodeLogoUrl = json_decode($logoUrl);

		// Rendu du template avec les données des posts et de l'organisation
		return $this->render('linkedin/posts.html.twig', [
			'posts' => $postsResponse['elements'],
			'organizationName' => $organizationDetails['localizedName'] ?? 'Nom Inconnu',
			'logoUrl' => $decodeLogoUrl,
		]);
	}

	// Route pour initier le processus d'authentification OAuth2 avec LinkedIn
	#[Route('/linkedin/oauth', name: 'linkedin_oauth')]
	public function redirectToLinkedIn(Request $request): Response
	{
		// Création et stockage du jeton CSRF
		$csrfToken = $this->csrfTokenManager->getToken('linkedin_auth')->getValue();
		$request->getSession()->set('linkedin_csrf_token', $csrfToken);

		// Construction de l'URL pour la redirection vers LinkedIn
		$url = "https://www.linkedin.com/oauth/v2/authorization";
		$queryParams = http_build_query([
			'response_type' => 'code',
			'client_id' => $_ENV['LINKEDIN_CLIENT_ID'],
			'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'],
			'scope' => 'r_organization_social r_basicprofile r_organization_admin profile email',
			'state' => $csrfToken,
		]);

		// Redirection vers l'URL d'authentification LinkedIn
		return $this->redirect($url . '?' . $queryParams);
	}
}
