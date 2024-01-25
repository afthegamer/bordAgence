<?php

namespace App\Controller;

use App\Service\CalendarService;
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

	public function __construct(
		private GoogleClientService $googleClientService
	){}


	#[Route('/google', name: 'google_index')]
	public function index(
		SessionInterface $session,
		EmailService $emailService
	): Response
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

			$emails = $emailService->getEmailsFromLastDays(
				$addresses,
				$subjectFilters
			);

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
				return $this->redirectToRoute('app_home');
			}
		}
		return $this->redirectToRoute('google_login');
	}

	#[Route('/calendar/events', name: 'calendar_events')]
	public function calendarEvents(SessionInterface $session, CalendarService $calendarService): Response
	{
		// Récupérer le token d'accès de la session
		$accessToken = $session->get('access_token');

		// Vérifier si l'utilisateur est authentifié et dispose d'un token d'accès
		if (!$accessToken) {
			// Si non, rediriger vers la page de login Google
			return $this->redirectToRoute('google_login');
		}

		// Rafraîchir le token si nécessaire
		$this->googleClientService->refreshTokenIfNeeded($session);

		// Mettre à jour le token d'accès pour le client Google
		$this->googleClientService->setAccessToken($accessToken);

		try {
			// Récupérer les événements du calendrier à l'aide du CalendarService
			$events = $calendarService->getEvents();
//			dd($events);
			return $this->render('calendar/events.html.twig', [
				'events' => $events,
			]);
		} catch (\Exception $e) {
			// Gestion des erreurs, par exemple, un problème avec l'API Google
			// Vous pouvez également logger cette erreur pour un débogage ultérieur
			return new Response('Erreur lors de la récupération des événements du calendrier : ' . $e->getMessage());
		}
	}
}
