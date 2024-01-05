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
				// Vérification du token CSRF
				$csrfToken = $request->query->get('state');
				$sessionCsrfToken = $session->get('linkedin_csrf_token');
				if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('linkedin_auth', $csrfToken)) || $csrfToken !== $sessionCsrfToken) {
					throw new \Exception("Invalid CSRF token");
				}

				$accessToken = $this->getAccessToken($request->query->get('code'));
				$session->set('linkedin_access_token', $accessToken);
			} else {
				return $this->redirectToRoute('linkedin_oauth');
			}
		}

		$organizationId = $_ENV['LINKEDIN_ORGANIZATION_ID'];
		$url = "https://api.linkedin.com/v2/shares?q=owners&owners=urn:li:organization:$organizationId&count=12&sortBy=LAST_MODIFIED";

		$response = $this->httpClient->request('GET', $url, [
			'headers' => [
				'Authorization' => 'Bearer ' . $accessToken,
			],
		]);

		$posts = $response->toArray();

		return $this->render('linkedin/posts.html.twig', [
			'posts' => $posts['elements'],
		]);
	}

//	#[Route('/linkedin/oauth', name: 'linkedin_oauth')]
//	public function redirectToLinkedIn(Request $request): Response
//	{
//		// Générer un token CSRF
//		$csrfToken = $this->csrfTokenManager->getToken('linkedin_auth')->getValue();
//		$request->getSession()->set('linkedin_csrf_token', $csrfToken);
//
//		$url = "https://www.linkedin.com/oauth/v2/authorization";
//		$queryParams = http_build_query([
//			'response_type' => 'code',
//			'client_id' => $_ENV['LINKEDIN_CLIENT_ID'],
//			'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'],
//			'scope' => 'r_liteprofile', // Retirez 'r_emailaddress' si vous n'en avez pas besoin
//			'state' => $csrfToken,
//		]);
//
//		return $this->redirect($url . '?' . $queryParams);
//	}

	#[Route('/linkedin/oauth', name: 'linkedin_oauth')]
	public function redirectToLinkedIn(Request $request): Response
	{
		// Générer un token CSRF
		$csrfToken = $this->csrfTokenManager->getToken('linkedin_auth')->getValue();
		$request->getSession()->set('linkedin_csrf_token', $csrfToken);

		$url = "https://www.linkedin.com/oauth/v2/authorization";
		$queryParams = http_build_query([
			'response_type' => 'code',
			'client_id' => $_ENV['LINKEDIN_CLIENT_ID'],
			'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'],
			'scope' => 'openid profile w_member_social email',
			'state' => $csrfToken,  // Inclure le token CSRF ici
		]);

		return $this->redirect($url . '?' . $queryParams);
	}


	private function getAccessToken(string $code): ?string
	{
		$url = "https://www.linkedin.com/oauth/v2/accessToken";
		$response = $this->httpClient->request('POST', $url, [
			'body' => [
				'grant_type' => 'authorization_code',
				'code' => $code,
				'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'],
				'client_id' => $_ENV['LINKEDIN_CLIENT_ID'],
				'client_secret' => $_ENV['LINKEDIN_CLIENT_SECRET'],
			],
		]);

		$data = $response->toArray();

		return $data['access_token'] ?? null;
	}
}
