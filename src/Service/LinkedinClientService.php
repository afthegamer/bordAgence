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

	// Méthode pour obtenir un nouveau token d'accès ou rafraîchir un token existant
	public function fetchAccessTokenFromLinkedIn(string $code, ?string $refreshToken, SessionInterface $session): array
	{
		// URL de l'API LinkedIn pour obtenir le token d'accès
		$url = "https://www.linkedin.com/oauth/v2/accessToken";

		// Paramètres de base pour la demande
		$params = [
			'client_id' => $_ENV['LINKEDIN_CLIENT_ID'],  // Identifiant client LinkedIn
			'client_secret' => $_ENV['LINKEDIN_CLIENT_SECRET'],  // Secret client LinkedIn
			'redirect_uri' => $_ENV['LINKEDIN_REDIRECT_URI'],  // URI de redirection configurée
			// Type de demande : soit pour un nouveau token, soit pour rafraîchir un token existant
			'grant_type' => $refreshToken ? 'refresh_token' : 'authorization_code',
		];

		// Ajout du refreshToken ou du code à la demande selon le cas
		if ($refreshToken) {
			$params['refresh_token'] = $refreshToken;  // Ajout du refreshToken pour rafraîchir le token
		} else {
			$params['code'] = $code;  // Ajout du code pour un nouveau token
		}

		// Effectue la demande POST à LinkedIn
		$response = $this->httpClient->request('POST', $url, ['body' => $params]);
		// Convertit la réponse en tableau
		$data = $response->toArray();

		// Calcule et stocke l'heure d'expiration du token d'accès dans la session
		$expirationTime = time() + ($data['expires_in'] ?? 3600); // Utilise 'expires_in' ou une valeur par défaut
		$session->set('linkedin_access_token_expiration_time', $expirationTime);

		// Renvoie le token d'accès et, le cas échéant, un nouveau refreshToken
		return [
			'access_token' => $data['access_token'] ?? null,
			'refresh_token' => $data['refresh_token'] ?? $refreshToken,
		];
	}

	// Méthode pour vérifier si le token d'accès est expiré
	public function isAccessTokenExpired(SessionInterface $session): bool
	{
		// Récupère l'heure d'expiration du token de la session
		$expirationTime = $session->get('linkedin_access_token_expiration_time');

		// Compare l'heure actuelle à l'heure d'expiration pour déterminer si le token est expiré
		return time() >= $expirationTime;
	}


// Méthode pour obtenir le logo d'une organisation
	public function getOrganizationLogo(string $accessToken, string $logoUrn): ?string
	{
		// Modifie l'URN pour le format correct de l'API
		$imageUrn = str_replace('digitalmediaAsset', 'image', $logoUrn);

		// Construit l'URL pour la requête de l'image
		$url = "https://api.linkedin.com/rest/images/$imageUrn";

		// Effectue la requête GET et récupère la réponse
		$response = $this->makeGetRequest($url, $accessToken);

		// Retourne le contenu de la réponse (URL de l'image ou données binaires)
		return $response->getContent();
	}

// Méthode générale pour effectuer des requêtes GET
	public function makeGetRequest($url, $accessToken) {
		// Prépare les en-têtes avec le token d'accès pour l'authentification

		$response = $this->httpClient->request('GET', $url, [
				'headers' => [
					'Authorization' => 'Bearer ' . $accessToken,
					'LinkedIn-Version:202309' // Spécifie la version de l'API LinkedIn utilisée
				]
			]);
			return $response;
	}
	// Méthode pour récupérer les posts d'une organisation
	public function fetchOrganizationPosts(string $accessToken, string $organizationId): array
	{
		// Construit l'URL pour récupérer les posts de l'organisation
		$nbGetPost = 12;
		$postsUrl = "https://api.linkedin.com/v2/shares?q=owners&owners=urn:li:organization:$organizationId&count=$nbGetPost&sortBy=LAST_MODIFIED";

		// Utilise makeGetRequest pour effectuer la requête et retourne le résultat converti en tableau
		return $this->makeGetRequest($postsUrl, $accessToken)->toArray();
	}

	// Méthode pour récupérer les détails d'une organisation
	public function fetchOrganizationDetails(string $accessToken, string $organizationId): array
	{
		// Construit l'URL pour récupérer les détails de l'organisation
		$organizationUrl = "https://api.linkedin.com/v2/organizations/$organizationId";

		// Utilise makeGetRequest pour effectuer la requête et retourne le résultat converti en tableau
		return $this->makeGetRequest($organizationUrl, $accessToken)->toArray();
	}

	// Méthode pour obtenir l'URL du logo d'une organisation
	public function getOrganizationLogoUrl(array $organizationDetails, string $accessToken): ?string
	{
		// Récupère l'URN du logo de l'organisation
		$logoUrn = $organizationDetails['logoV2']['original'] ?? null;

		// Si un URN de logo est trouvé, utilise getOrganizationLogo pour obtenir l'URL du logo
		return $logoUrn ? $this->getOrganizationLogo($accessToken, $logoUrn) : null;
	}
}