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
	public function getPostMedia(string $accessToken, string $mediaUrn): ?string {
		// Détermine si l'URN est pour une vidéo ou une image
		$isVideo = strpos($mediaUrn, 'urn:li:video') !== false;

		// Modifie l'URN pour le format correct de l'API si nécessaire
		if (!$isVideo && strpos($mediaUrn, 'digitalmediaAsset') !== false) {
			$mediaUrn = str_replace('digitalmediaAsset', 'image', $mediaUrn);
		}

		// Construit l'URL pour la requête en fonction du type de média
		$url = $isVideo ? "https://api.linkedin.com/rest/videos/$mediaUrn"
			: "https://api.linkedin.com/rest/images/$mediaUrn";

		// Effectue la requête GET et récupère la réponse
		$response = $this->makeGetRequest($url, $accessToken);

		// Retourne le contenu de la réponse (URL du média ou données binaires)
		return $response->getContent();
	}

	// recupere les posts reshared
	public function fetchResharedPostDetails(string $accessToken, string $postUrn): ?array {
//		$encodedPostUrn = urlencode($postUrn);

		$encodedPostUrn = str_replace('urn:li:ugcPost:','',$postUrn);
//		$url = "https://api.linkedin.com/rest/posts/{$postUrn}";
//		$url = "https://api.linkedin.com/v2/posts/{$encodedPostUrn}";
		$url = "https://api.linkedin.com/rest/posts/{$postUrn}";

//		dump($url);


		// En-têtes supplémentaires pour cette requête spéciale
		$additionalHeaders = [
			'X-Restli-Protocol-Version' => '2.0.0',
			// Autres en-têtes si nécessaires
		];

		$response = $this->makeGetRequest($url, $accessToken, /*$additionalHeaders*/);
		if ($response->getStatusCode() === 200) {
//			dump($url,$response->toArray());

			return $response->toArray();
		} else {
			return $this->fetchPersonalPosts($accessToken, $postUrn);
		}

	}

	public function fetchPersonalPosts(string $accessToken,string $postUrn): ?array
	{
		if (str_contains($postUrn, 'urn:li:ugcPost')) {
			$postUrn = str_replace('urn:li:ugcPost:','',$postUrn);
		}elseif (str_contains($postUrn, 'urn:li:share')) {
			$postUrn = str_replace('urn:li:share:','',$postUrn);
		}
		// Utiliser l'endpoint approprié pour récupérer les publications du profil personnel
		$url = "https://api.linkedin.com/v2/activities?ids={$postUrn}";

		$response = $this->makeGetRequest($url, $accessToken);

		if ($response->getStatusCode() === 200) {
			return $response->toArray();
		} else {
			return null;
		}
	}

	// recupere les details du post rehsard
	public function fetchResharedOrganisation(string $accessToken, string $postUrn): ?array {
		$encodedPostUrn = str_replace('urn:li:organization:','',$postUrn);

		$url = "https://api.linkedin.com/rest/organizations/{$encodedPostUrn}";
		// En-têtes supplémentaires pour cette requête spéciale
		$additionalHeaders = [
			'X-Restli-Protocol-Version' => '2.0.0',
			// Autres en-têtes si nécessaires
		];
//		dump($url);

		$response = $this->makeGetRequest($url, $accessToken);
//		$response = $this->makeGetRequest($url, $accessToken, $additionalHeaders);

//		dd($response->toArray());
		if ($response->getStatusCode() === 200) {
//			dump($response->toArray());

			return $response->toArray();
		} else {
//			dump($url,'pas trouver');
			$url = "https://api.linkedin.com/rest/organizationsLookup?ids={$encodedPostUrn}";
			$response = $this->makeGetRequest($url, $accessToken);

			if ($response->getStatusCode() === 200) {
//				dump($response->toArray());
				return $response->toArray();
			} else {
//				dd($response->toArray());
				// Traiter l'erreur, par exemple, enregistrer les détails ou notifier une erreur
				return null;
			}


			// Traiter l'erreur, par exemple, enregistrer les détails ou notifier une erreur
			return null;
		}

	}


// Méthode générale pour effectuer des requêtes GET
	public function makeGetRequest($url, $accessToken, array $additionalHeaders = []) {
		// En-têtes de base
		$headers = [
			'Authorization' => 'Bearer ' . $accessToken,
			'LinkedIn-Version' => '202309' // Version par défaut de l'API LinkedIn
		];

		// Ajouter ou écraser les en-têtes avec les en-têtes supplémentaires
		foreach ($additionalHeaders as $key => $value) {
			$headers[$key] = $value;
		}

		// Effectue la requête GET avec les en-têtes
		$response = $this->httpClient->request('GET', $url, [
			'headers' => $headers
		]);

		return $response;
	}


	// Méthode pour récupérer les posts d'une organisation
	public function fetchOrganizationAuthorPosts(string $accessToken, string $organizationId, int $nbGetPost): array
	{
		$postsUrl = "https://api.linkedin.com/rest/posts?q=author&author=urn:li:organization:$organizationId&count=$nbGetPost&sortBy=LAST_MODIFIED";

		// Utilise makeGetRequest pour effectuer la requête et retourne le résultat converti en tableau
		return $this->makeGetRequest($postsUrl, $accessToken)->toArray();
	}
	public function fetchOrganizationRePosts(string $accessToken, string $organizationId, int $nbGetPost): array
	{

		$postsUrl = "https://api.linkedin.com/rest/posts?q=owners&owners=urn:li:organization:$organizationId&count=$nbGetPost&sortBy=LAST_MODIFIED";

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

		// Si un URN de logo est trouvé, utilise getPostMedia pour obtenir l'URL du logo
		return $logoUrn ? $this->getPostMedia($accessToken, $logoUrn) : null;
	}


	public function processPosts(array &$posts, string $accessToken): void
	{
		foreach ($posts as &$post) {
			// Vérifie si 'content' et 'content.media' existent
			if (isset($post['content']['media']['id'])) {
				$mediaId = $post['content']['media']['id'];
				$mediaJson = $this->getPostMedia($accessToken, $mediaId);

				// Décodez la réponse JSON pour obtenir l'URL réelle du média
				$mediaData = json_decode($mediaJson, true);
				if (is_array($mediaData) && isset($mediaData['downloadUrl'])) {
					$post['content']['media']['mediaUrl'] = $mediaData['downloadUrl'];
				}

				// Ajoutez une clé pour distinguer les images des vidéos
				$post['content']['media']['isVideo'] = str_contains($mediaId, 'urn:li:video');
			}
		}
	}

	public function processResharedPosts(array &$posts, string $accessToken): void
	{
		foreach ($posts as &$post) {
//			dump($post);
			// Vérifie si le post est un reshare
			if (isset($post['reshareContext']['root'])) {
				// Récupère les détails du post reshared
				$postUrn = $post['reshareContext']['root'];
//				dump($postUrn);
				$resharedPostDetails = $this->fetchResharedPostDetails($accessToken, $postUrn);
//				dump($resharedPostDetails);
				// Récupère les détails de l'organisation associée à l'auteur du post reshared
				if (isset($resharedPostDetails['author'])) {
					$resharedAuthorId = $resharedPostDetails['author'];
					$resharedAuthorDetails = $this->fetchResharedOrganisation($accessToken, $resharedAuthorId);
					$resharedPostDetails['authorDetails'] = $resharedAuthorDetails;

					if (isset($resharedAuthorDetails['logoV2']['original'])) {
						$logoUrl = $this->getOrganizationLogoUrl($resharedAuthorDetails, $accessToken);
						$logoData = json_decode($logoUrl);

						$resharedPostDetails['authorDetails']['logoUrl'] = $logoData;
					}
				}

				// Vérifiez si le post reshared a des médias
				if (isset($resharedPostDetails['content']['media']) && is_array($resharedPostDetails['content']['media'])) {
					foreach ($resharedPostDetails['content'] as &$mediaItem) {

						if (isset($mediaItem['id'])) {
							// Utilisez getPostMedia pour obtenir l'URL du média
							$mediaJson = $this->getPostMedia($accessToken, $mediaItem['id']);
							$mediaData = json_decode($mediaJson, true);

							if (is_array($mediaData) && isset($mediaData['downloadUrl'])) {
								$mediaItem['mediaUrl'] = $mediaData['downloadUrl'];
							}

							// Déterminez si c'est une vidéo ou une image
							$mediaItem['isVideo'] = str_contains($mediaItem['id'], 'urn:li:video');
						}
					}
				}

				// Ajoute les détails reshared au post original
				$post['resharedPostDetails'] = $resharedPostDetails;
			}

		}
	}



	public function formatPostContent($posts) {
		foreach ($posts as &$post) {
			// Traitement du commentaire principal
			if (isset($post['commentary'])) {
				$post['commentary'] = $this->formatCommentary($post['commentary']);
			}

			// Traitement du commentaire du post partagé
			if (isset($post['resharedPostDetails']['commentary'])) {
				$post['resharedPostDetails']['commentary'] = $this->formatCommentary($post['resharedPostDetails']['commentary']);
			}
		}
		return $posts;
	}

	private function formatCommentary($commentary) {
		// Format hashtags
		$commentary = preg_replace('/\{hashtag\|\\\#\|([^\}]+)\}/', '<span style="font-weight:bold; color:blue;">#$1</span>', $commentary);

		// Format links
		$commentary = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" style="color:blue;">$1</a>', $commentary);

		// Format mentions
		$commentary = preg_replace('/@\[([^\]]+)\]\(urn:li:organization:\d+\)/', '<span style="font-weight:bold; color:blue;">$1</span>', $commentary);

		return $commentary;
	}


}