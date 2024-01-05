<?php

namespace App\Service;

use Google\Client;
use Google\Service\Gmail;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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

	public function getEmailsFromLastDays(array $emailAddresses, array $subjectFilters): array
	{
		$nbDay=30;
		$emails = [];
		$user = 'me';

		// Construire la partie from de la requête
		$fromQueries = array_map(function($email) {
			return "from:$email";
		}, $emailAddresses);
		$fromQueryPart = implode(' OR ', $fromQueries);

		// Construire la partie subject de la requête
		$subjectQueries = array_map(function($filter) {
			return "subject:\"$filter\"";
		}, $subjectFilters);
		$subjectQueryPart = implode(' OR ', $subjectQueries);

		// Requête finale
		$query = "newer_than:".$nbDay."d ($fromQueryPart) AND ($subjectQueryPart)";


		$list = $this->service->users_messages->listUsersMessages($user, ['q' => $query]);
		foreach ($list->getMessages() as $messageItem) {
			$messageId = $messageItem->getId();
			$message = $this->service->users_messages->get($user, $messageId);
			$emails[] = $this->extractEmailData($message);
		}


		return $emails;
	}

	private function extractEmailData($message): array
	{
		$headers = $message->getPayload()->getHeaders();
		$body = $this->getMessageBody($message);
		$isHtml = $message->getPayload()->getMimeType() === 'text/html';

		$emailData = [
			'subject' => $this->getHeader($headers, 'Subject'),
			'from'    => $this->getHeader($headers, 'From'),
			'date'    => $this->getHeader($headers, 'Date'),
			'content' => $isHtml ? $body : $this->convertPlainTextToHtml($body),
		];

		return $emailData;
	}


	private function getHeader($headers, $name)
	{
		foreach ($headers as $header) {
			if ($header->getName() === $name) {
				return $header->getValue();
			}
		}

		return null;
	}

	private function getMessageBody($message)
	{
		$payload = $message->getPayload();

		// Si l'email est simple (pas multipart)
		if ($payload->getBody()->getSize() > 0) {
			$bodyData = $payload->getBody()->getData();
			return $this->decodeBody($bodyData);
		}

		// Si l'email est multipart
		foreach ($payload->getParts() as $part) {
			if ($part->getMimeType() === 'text/plain' || $part->getMimeType() === 'text/html') {
				$bodyData = $part->getBody()->getData();
				return $this->decodeBody($bodyData);
			}
		}

		return '';
	}

	private function decodeBody($bodyData)
	{
		$sanitizedData = strtr($bodyData, '-_', '+/');
		return base64_decode($sanitizedData);
	}

	private function extractOriginalContent($emailContent)
	{
		$lines = explode("\n", $emailContent);
		$originalContent = [];

		foreach ($lines as $line) {
			// Vérifiez si la ligne semble être le début d'une réponse ou d'une citation
			if (preg_match('/^>|\bOn .+ wrote:|\bLe .+ à .+ a écrit :/', $line)) {
				break;
			}
			$originalContent[] = $line;
		}

		return implode("\n", $originalContent);
	}



	private function convertPlainTextToHtml($plainText)
	{
		$htmlContent = htmlspecialchars($plainText, ENT_QUOTES, 'UTF-8');
		$htmlContent = preg_replace('/(\r\n|\r|\n)/', '<br>', $htmlContent);
		return $htmlContent;
	}







}

