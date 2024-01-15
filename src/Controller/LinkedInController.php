<?php

namespace App\Controller;

use App\Service\LinkedinClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class LinkedInController extends AbstractController
{

	public function __construct(
		private HttpClientInterface $httpClient,
		private CsrfTokenManagerInterface $csrfTokenManager,
		public LinkedinClientService $linkedinClientService
	)
	{}

	#[Route('/linkedin/posts', name: 'linkedin_posts')]
	public function fetchLinkedInPosts(Request $request): Response
	{
		$session = $request->getSession();
		$accessToken = $session->get('linkedin_access_token');
		$refreshToken = $session->get('linkedin_refresh_token');

		if (!$accessToken || $this->linkedinClientService->isAccessTokenExpired($session)) {
			if ($code = $request->query->get('code')) {
				$csrfToken = $request->query->get('state');
				$sessionCsrfToken = $session->get('linkedin_csrf_token');
				if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('linkedin_auth', $csrfToken)) || $csrfToken !== $sessionCsrfToken) {
					throw new \Exception("Invalid CSRF token");
				}

				$tokens = $this->linkedinClientService->getAccessToken($code, $refreshToken, $session);
				$accessToken = $tokens['access_token'] ?? null;

				if ($accessToken) {
					$session->set('linkedin_access_token', $accessToken);
					// Redirection pour nettoyer l'URL après la connexion
					return $this->redirectToRoute('linkedin_posts');
				}
			} else {
				return $this->redirectToRoute('linkedin_oauth');
			}
		}

		// Récupération des posts de l'organisation
		$organizationId = $_ENV['LINKEDIN_ORGANIZATION_ID'];
		$postsUrl = "https://api.linkedin.com/v2/shares?q=owners&owners=urn:li:organization:$organizationId&count=12&sortBy=LAST_MODIFIED";
		$postsResponse = $this->linkedinClientService->makeGetRequest(
			$postsUrl,
			$accessToken
		)->toArray();

		// Récupération du nom de l'organisation
		$organizationUrl = "https://api.linkedin.com/v2/organizations/$organizationId";
		$organizationResponse = $this->linkedinClientService->makeGetRequest(
			$organizationUrl,
			$accessToken
		)->toArray();
		$organizationName = $organizationResponse['localizedName'] ?? 'Nom Inconnu';

		// Récupération du logo de l'organisation
		$logoUrn = $organizationResponse['logoV2']['original'] ?? null;
		$logoUrl = json_decode(
			$this->linkedinClientService->getOrganizationLogo(
				$accessToken,
				$logoUrn)
		);

		return $this->render('linkedin/posts.html.twig', [
			'posts' => $postsResponse['elements'],
			'organizationName' => $organizationName,
			'logoUrl' => $logoUrl,
		]);
	}




	#[Route('/linkedin/oauth', name: 'linkedin_oauth')]
	public function redirectToLinkedIn(Request $request): Response
	{
		$csrfToken = $this->csrfTokenManager->getToken('linkedin_auth')->getValue();
		$request->getSession()->set('linkedin_csrf_token', $csrfToken);

		$url = "https://www.linkedin.com/oauth/v2/authorization";
		$queryParams = http_build_query([
			'response_type' => 'code',
			'client_id' => $_ENV['LINKEDIN_CLIENT_ID'],
			'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'],
			'scope' => 'r_organization_social r_basicprofile r_organization_admin profile email',  // Mise à jour des scopes
			'state' => $csrfToken,
		]);

		return $this->redirect($url . '?' . $queryParams);
	}
}
