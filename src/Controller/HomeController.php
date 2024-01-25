<?php

namespace App\Controller;

use App\Service\LinkedInAuthService;
use App\Service\LinkedinClientService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/')]
class HomeController extends AbstractController
{


	// Constructeur avec injection de dépendances pour les services nécessaires
	public function __construct(
		private CsrfTokenManagerInterface $csrfTokenManager,
		public LinkedinClientService $linkedinClientService,
		public LinkedInAuthService $linkedInAuthService
	){}

	#[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
	    // Obtention du token d'accès via LinkedInAuthService
	    $accessTokenLinkedin = $this->linkedInAuthService->obtainOrRefreshAccessToken($request);

	    // Gestion du cas où aucun token n'est obtenu
	    if (!$accessTokenLinkedin) {
		    return $this->redirectToRoute('linkedin_oauth');
	    }
	    // Récupération des posts et des détails de l'organisation via LinkedinClientService
	    $organizationId = $_ENV['LINKEDIN_ORGANIZATION_ID'];
	    $postsResponse = $this->linkedinClientService->fetchOrganizationAuthorPosts(
		    $accessTokenLinkedin,
		    $organizationId,
		    12
	    );
	    $organizationDetails = $this->linkedinClientService->fetchOrganizationDetails(
		    $accessTokenLinkedin,
		    $organizationId
	    );

	    // Récupération de l'URL du logo de l'organisation
	    $logoUrl = $this->linkedinClientService->getOrganizationLogoUrl(
		    $organizationDetails,
		    $accessTokenLinkedin
	    );
	    $decodeLogoUrl = json_decode($logoUrl);

	    // Traitement des posts
	    $this->linkedinClientService->processPosts(
		    $postsResponse['elements'],
		    $accessTokenLinkedin
	    );

	    // Traitement des reshared posts
	    $this->linkedinClientService->processResharedPosts(
		    $postsResponse['elements'],
		    $accessTokenLinkedin
	    );
	    $postsResponse['elements'] = $this->linkedinClientService->formatPostContent($postsResponse['elements']);

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
	        'posts' => $postsResponse['elements'],
	        'organizationName' => $organizationDetails['localizedName'] ?? 'Nom Inconnu',
	        'logoUrl' => $decodeLogoUrl,
        ]);
    }
}
