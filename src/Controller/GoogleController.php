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
		$this->googleClientService->refreshTokenIfNeeded($session);

		$accessToken = $session->get('access_token');
		if ($accessToken) {
			$emailService->setAccessToken($accessToken);

			// Adresses email pour le filtrage
			$addresses = [
				'powertools-list@clever-age.com',
				'all@clever-age.com',
				'omartinerie@clever-age.com',
				'fbon@clever-age.com'
			];
			// Ajoutez ici vos mots-clés ou phrases
			$subjectFilters = [
				'Démos du jour et à venir',
				'Démos passées et à venir',
				'bilan',
			];

			$emails = $emailService->getEmailsFromLastDays($addresses,$subjectFilters);

			return $this->render('google/index.html.twig', [
				'emails' => $emails,
			]);
		}

		return $this->redirectToRoute('google_login');
	}




	#[Route('/google/login', name: 'google_login')]
	public function googleLogin()
	{
		$client = $this->googleClientService->getClient();
		$client->setAccessType('offline');
		$client->setPrompt('consent');
		$authUrl = $client->createAuthUrl();

		return $this->redirect($authUrl);
	}


	#[Route('/callback', name: 'google_callback', methods: ['GET'])]
	public function googleCallback(Request $request, SessionInterface $session)
	{
		$code = $request->query->get('code');
		if ($code) {
			$accessToken = $this->googleClientService->getClient()->fetchAccessTokenWithAuthCode($code);
			if (!array_key_exists('error', $accessToken)) {
				$session->set('access_token', $accessToken['access_token']);
				if (isset($accessToken['refresh_token'])) {
					$session->set('refresh_token', $accessToken['refresh_token']);
				}
				return $this->redirectToRoute('google_index');
			}
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


}
