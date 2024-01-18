<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;

class LinkedInAuthService
{
	private LinkedinClientService $linkedinClientService;
	private CsrfTokenManagerInterface $csrfTokenManager;

	public function __construct(LinkedinClientService $linkedinClientService, CsrfTokenManagerInterface $csrfTokenManager)
	{
		$this->linkedinClientService = $linkedinClientService;
		$this->csrfTokenManager = $csrfTokenManager;
	}

	// Obtient ou rafraîchit le token d'accès LinkedIn
	public function obtainOrRefreshAccessToken(Request $request): ?string
	{
		// Récupère la session à partir de la requête
		$session = $request->getSession();

		// Tente de récupérer le token d'accès et le token de rafraîchissement depuis la session
		$accessToken = $session->get('linkedin_access_token');
		$refreshToken = $session->get('linkedin_refresh_token');

		// Vérifie si le token d'accès n'existe pas ou s'il est expiré
		if (!$accessToken || $this->linkedinClientService->isAccessTokenExpired($session)) {
			// Récupère le code d'autorisation de la requête, s'il existe
			if ($code = $request->query->get('code')) {
				// Valide le jeton CSRF pour la sécurité
				$this->validateCsrfToken($request, $session);

				// Demande un nouveau token d'accès via LinkedinClientService
				$tokens = $this->linkedinClientService->fetchAccessTokenFromLinkedIn($code, $refreshToken, $session);
				$accessToken = $tokens['access_token'] ?? null;
				// Tente de récupérer un nouveau refreshToken
				$refreshToken = $tokens['refresh_token'] ?? null;

				// Si un nouveau token d'accès est obtenu
				if ($accessToken) {
					// Enregistre le nouveau token d'accès dans la session
					$session->set('linkedin_access_token', $accessToken);
					// Enregistre le nouveau refreshToken dans la session, s'il est obtenu
					if ($refreshToken) {
						$session->set('linkedin_refresh_token', $refreshToken);
					}
					// Retourne le nouveau token d'accès
					return $accessToken;
				}
			} else {
				// Retourne null si aucun code d'autorisation n'est disponible
				return null;
			}
		}

		// Retourne le token d'accès existant s'il est toujours valide
		return $accessToken;
	}


	private function validateCsrfToken(Request $request, SessionInterface $session): void
	{
		// Récupère le jeton CSRF de la requête (généralement transmis en tant que paramètre 'state')
		$csrfToken = $request->query->get('state');

		// Récupère le jeton CSRF stocké dans la session lors de l'initialisation du processus d'authentification
		$sessionCsrfToken = $session->get('linkedin_csrf_token');

		// Vérifie deux conditions pour la validation du jeton CSRF :
		// 1. Le jeton CSRF de la requête doit être valide.
		// 2. Le jeton CSRF de la requête doit correspondre au jeton CSRF stocké dans la session.
		// La méthode isTokenValid vérifie si le jeton CSRF est valide.
		if (!$this->csrfTokenManager->isTokenValid(
			new CsrfToken(
				'linkedin_auth',
				$csrfToken
			))
			|| $csrfToken !== $sessionCsrfToken) {
			// Lance une exception si le jeton CSRF n'est pas valide.
			// Cela empêche les attaques de type CSRF en assurant que la requête provient d'une source fiable.
			throw new \Exception("Invalid CSRF token");
		}
	}
}
