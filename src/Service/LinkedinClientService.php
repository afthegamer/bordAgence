<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LinkedinClientService
{

	public function __construct(
		public HttpClientInterface $httpClient,
		public CsrfTokenManagerInterface $csrfTokenManager,
	){}


	public function getAccessToken(string $code, ?string $refreshToken, SessionInterface $session): array
	{
		$url = "https://www.linkedin.com/oauth/v2/accessToken";
		$params = [
			'client_id' => $_ENV['LINKEDIN_CLIENT_ID'],
			'client_secret' => $_ENV['LINKEDIN_CLIENT_SECRET'],
			'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'],
			'grant_type' => $refreshToken ? 'refresh_token' : 'authorization_code',
		];

		if ($refreshToken) {
			$params['refresh_token'] = $refreshToken;
		} else {
			$params['code'] = $code;
		}

		$response = $this->httpClient->request('POST', $url, ['body' => $params]);
		$data = $response->toArray();

		// Stocker l'heure d'expiration du token d'accès dans la session
		$expirationTime = time() + ($data['expires_in'] ?? 3600); // Durée de vie du token en secondes
		$session->set('linkedin_access_token_expiration_time', $expirationTime);

		return [
			'access_token' => $data['access_token'] ?? null,
			'refresh_token' => $data['refresh_token'] ?? $refreshToken,
		];
	}

	public function isAccessTokenExpired(SessionInterface $session): bool
	{
		// Récupérez l'heure d'expiration du token d'accès stockée dans la session
		$expirationTime = $session->get('linkedin_access_token_expiration_time');

		// Vérifiez si l'heure actuelle est supérieure à l'heure d'expiration du token
		$currentTime = time();
		return $currentTime >= $expirationTime;
	}


	public function getOrganizationLogo(string $accessToken, string $logoUrn): ?string
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

	public function makeGetRequest($url, $accessToken) {
			$response = $this->httpClient->request('GET', $url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
				]
			]);
			return $response;
	}

}