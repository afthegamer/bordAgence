<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class LinkedInController extends AbstractController
{
	private $httpClient;
	private $csrfTokenManager;

	public function __construct(HttpClientInterface $httpClient, CsrfTokenManagerInterface $csrfTokenManager)
	{
		$this->httpClient = $httpClient;
		$this->csrfTokenManager = $csrfTokenManager;
	}

	#[Route('/linkedin/posts', name: 'linkedin_posts')]
	public function fetchLinkedInPosts(Request $request): Response
	{
		$session = $request->getSession();
		$accessToken = $session->get('linkedin_access_token');

		if (!$accessToken) {
			if ($request->query->get('code')) {
				$csrfToken = $request->query->get('state');
				$sessionCsrfToken = $session->get('linkedin_csrf_token');
				if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('linkedin_auth', $csrfToken)) || $csrfToken !== $sessionCsrfToken) {
					throw new \Exception("Invalid CSRF token");
				}

				$accessToken = $this->getAccessToken($request->query->get('code'));
				$session->set('linkedin_access_token', $accessToken);

				return $this->redirectToRoute('linkedin_posts');
			} else {
				return $this->redirectToRoute('linkedin_oauth');
			}
		}

		// Récupération des posts de l'organisation
		$organizationId = $_ENV['LINKEDIN_ORGANIZATION_ID'];
		$postsUrl = "https://api.linkedin.com/v2/shares?q=owners&owners=urn:li:organization:$organizationId&count=12&sortBy=LAST_MODIFIED";
		$postsResponse = $this->httpClient->request('GET', $postsUrl, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
			],
		]);
		$posts = $postsResponse->toArray();

		// Récupération du nom de l'organisation
		$organizationUrl = "https://api.linkedin.com/v2/organizations/$organizationId";
		$organizationResponse = $this->httpClient->request('GET', $organizationUrl, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
			],
		]);
		$organizationData = $organizationResponse->toArray();
		$organizationName = $organizationData['localizedName'] ?? 'Nom Inconnu';

		// Récupération du logo de l'organisation
		$logoUrn = $organizationData['logoV2']['original'] ?? null;
		$logoUrl = json_decode($this->getOrganizationLogo($accessToken, $logoUrn));

		return $this->render('linkedin/posts.html.twig', [
			'posts' => $posts['elements'],
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



	private function getAccessToken(string $code, ?string $refreshToken = null): ?string
	{
		$url = "https://www.linkedin.com/oauth/v2/accessToken";
		$params = [
			'client_id' => $_ENV['LINKEDIN_CLIENT_ID'],
			'client_secret' => $_ENV['LINKEDIN_CLIENT_SECRET'],
			'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'],
		];

		if ($refreshToken) {
			$params['grant_type'] = 'refresh_token';
			$params['refresh_token'] = $refreshToken;
		} else {
			$params['grant_type'] = 'authorization_code';
			$params['code'] = $code;
		}

		$response = $this->httpClient->request('POST', $url, ['body' => $params]);
		$data = $response->toArray();

		// Stocker le refresh token si disponible
		if (isset($data['refresh_token'])) {
			// Stocker le refresh token dans la session ou un endroit sécurisé
		}

		return $data['access_token'] ?? null;
	}
	private function getOrganizationLogo(string $accessToken, string $logoUrn): ?string
	{
		// Remplacement pour obtenir le bon format d'URN
		$imageUrn = str_replace('digitalmediaAsset', 'image', $logoUrn);

		$url = "https://api.linkedin.com/rest/images/$imageUrn";

		$response = $this->httpClient->request('GET', $url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
				'LinkedIn-Version:202309'
			],
		]);

		return $response->getContent(); // Ou une autre méthode pour extraire l'URL ou le contenu de l'image
	}



}
