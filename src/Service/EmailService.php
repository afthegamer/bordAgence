<?php

namespace App\Service;

use Google\Client;
use Google\Service\Gmail;

class EmailService
{
	private $client;
	private $service;

	public function __construct(GoogleClientService $googleClientService)
	{
		$this->client = $googleClientService->getClient();
		$this->service = new Gmail($this->client);
	}

	public function getLatestEmailContent(string $user = 'me'): ?string
	{
		$list = $this->service->users_messages->listUsersMessages($user, ['maxResults' => 1]);
		$messages = $list->getMessages();

		if (!empty($messages)) {
			$messageId = $messages[0]->getId();
			return $this->getGmailMessage($user, $messageId);
		}

		return null;
	}

	private function getGmailMessage($userId, $messageId): ?string
	{
		$message = $this->service->users_messages->get($userId, $messageId);
		$messagePayload = $message->getPayload();
		$parts = $messagePayload->getParts();

		return $this->getPartBody($parts);
	}

	private function getPartBody($parts): ?string
	{
		foreach ($parts as $part) {
			if ($part->getBody()->getSize() > 0) {
				return base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
			}

			if ($part->getParts()) {
				return $this->getPartBody($part->getParts());
			}
		}

		return null;
	}
	public function setAccessToken($accessToken)
	{
		$this->client->setAccessToken($accessToken);
	}

}

