<?php

namespace App\Controller;

use App\Service\EmailService;
use App\Service\GoogleClientService;
use Google\Client;
use Google\Service\Gmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

//#[Route('/google')]

class GoogleController extends AbstractController
{
	private Client $client;
	public function __construct(private GoogleClientService $googleClientService)
	{}

	#[Route('/google', name: 'google_index')]
	public function index(SessionInterface $session, EmailService $emailService): Response
	{
		$accessToken = $session->get('access_token');
		if ($accessToken) {
			$emailService->setAccessToken($accessToken);

			$emailContent = $emailService->getLatestEmailContent();

			return $this->render('google/index.html.twig', [
				'emailContent' => $emailContent,
			]);
		}

		return $this->redirectToRoute('google_login');
	}





	#[Route('/google/login', name: 'google_login')]
	public function googleLogin()
	{
		$client = $this->googleClientService->getClient();
		$authUrl = $client->createAuthUrl();

		return $this->redirect($authUrl);
	}

	#[Route('/callback', name: 'google_callback', methods: ['GET'])]
	public function googleCallback(Request $request, SessionInterface $session)
	{
		$code = $request->query->get('code');

		if ($code) {
			// Échange du code d'autorisation contre un token d'accès
			$accessToken = $this->googleClientService->getClient()->fetchAccessTokenWithAuthCode($code);

			if (array_key_exists('error', $accessToken)) {
				// Gérer l'erreur
				return $this->redirectToRoute('google_login');
			}

			$this->googleClientService->getClient()->setAccessToken($accessToken);

			// Stocker le token dans la session
			$session->set('access_token', $accessToken);

			return $this->redirectToRoute('google_index');
		}

		return $this->redirectToRoute('google_login');
	}
	public function getEmails(Client $client)
	{
		try {
			// Assurez-vous que le client Google est correctement authentifié
			$service = new Gmail($client);

			// Récupérer la liste des emails
			$user = 'me';
			$list = $service->users_messages->listUsersMessages($user);

			// Récupérer le premier email de la liste
			if ($list->getMessages() && count($list->getMessages()) > 0) {
				$messageId = $list->getMessages()[0]->getId(); // Id du premier email
				$email = $service->users_messages->get($user, $messageId);

				// Extraire et afficher une information spécifique, par exemple, l'objet
				foreach ($email->getPayload()->getHeaders() as $header) {
					if ($header->getName() === 'Subject') {
						return new Response('Sujet de l\'email : ' . $header->getValue());
					}
				}
			}

			return new Response('Aucun email trouvé.');
		} catch (\Exception $e) {
			// Gestion des erreurs
			return new Response('Erreur lors de la récupération des emails : ' . $e->getMessage());
		}
	}
	private function getGmailMessage($service, $userId, $messageId) {
		$message = $service->users_messages->get($userId, $messageId);
		$messagePayload = $message->getPayload();
		$parts = $messagePayload->getParts();

		$body = $this->getPartBody($parts);

		return $body;
	}

	private function getPartBody($parts) {
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


}
