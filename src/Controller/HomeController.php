<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        // Sinon, afficher la page d'accueil avec le bouton de connexion
        return $this->render('home/index.html.twig');
    }

    #[Route('/test', name: 'app_test')]
    public function test(): Response
    {
        return $this->render('test.html.twig');
    }

    #[Route('/simple', name: 'app_simple')]
    public function simple(): Response
    {
        return $this->render('home/simple.html.twig');
    }

    #[Route('/debug', name: 'app_debug')]
    public function debug(): Response
    {
        return $this->render('debug.html.twig');
    }

    #[Route('/minimal', name: 'app_minimal')]
    public function minimal(): Response
    {
        return $this->render('home/minimal.html.twig');
    }
}
