<?php

namespace App\Service;

use Google_Service_Calendar;
use Google_Service_Calendar_Event;

class CalendarService
{
	private $googleClient;

	public function __construct(GoogleClientService $googleClientService)
	{
		$this->googleClient = $googleClientService->getClient();
	}

	public function getEvents()
	{
		$calendarService = new Google_Service_Calendar($this->googleClient);
		$calendarId = 'primary';
		$optParams = [
			'maxResults' => 10, // Limiter le nombre de résultats
			'orderBy' => 'startTime',
			'singleEvents' => true,
			'timeMin' => date('c'),
		];
		$events = $calendarService->events->listEvents($calendarId, $optParams);

		return $events->getItems();
	}
}
