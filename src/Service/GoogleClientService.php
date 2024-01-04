<?php

namespace App\Service;

use Google\Client;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class GoogleClientService
{
	private Client $client;

	public function __construct()
	{
		$this->client = new Client();
		$this->client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
		$this->client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
		$this->client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
		$this->client->addScope('https://www.googleapis.com/auth/gmail.readonly');
	}

	public function getClient(): Client
	{
		return $this->client;
	}

	public function refreshTokenIfNeeded(SessionInterface $session)
	{
		if ($this->client->isAccessTokenExpired()) {
			$refreshToken = $session->get('refresh_token');
			if ($refreshToken) {
				$this->client->fetchAccessTokenWithRefreshToken($refreshToken);
				$session->set('access_token', $this->client->getAccessToken());
			}
		}
	}
}
