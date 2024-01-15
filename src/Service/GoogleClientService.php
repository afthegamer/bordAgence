<?php

namespace App\Service;

use Google\Client;
use Google_Service_Calendar;
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
		$this->client->addScope(\Google_Service_Gmail::GMAIL_READONLY);
		$this->client->addScope(\Google_Service_Calendar::CALENDAR_READONLY);
	}

	public function getClient(): Client
	{
		return $this->client;
	}
	public function setAccessToken($accessToken): void
	{
		$this->client->setAccessToken($accessToken);
	}

	public function refreshTokenIfNeeded(SessionInterface $session)
	{
		if ($this->client->isAccessTokenExpired()) {
			$refreshToken = $session->get('refresh_token');
			if ($refreshToken) {
				$newAccessToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
				if (!array_key_exists('error', $newAccessToken)) {
					$session->set('access_token', $newAccessToken['access_token']);
					// Mettre à jour le refresh_token si un nouveau est fourni
					if (isset($newAccessToken['refresh_token'])) {
						$session->set('refresh_token', $newAccessToken['refresh_token']);
					}
				}
			}
		}
	}
}
