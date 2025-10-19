<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, le rediriger vers la page appropriée
        if ($this->getUser()) {
            $roles = $this->getUser()->getRoles();
            
            if (in_array('ROLE_ADMINISTRATEUR_RH', $roles)) {
                return $this->redirectToRoute('administrateur_rh_dashboard');
            }
            if (in_array('ROLE_RESPONSABLE_RH', $roles)) {
                return $this->redirectToRoute('responsable_rh_dashboard');
            }
            if (in_array('ROLE_EMPLOYEE', $roles)) {
                return $this->redirectToRoute('employee_dashboard');
            }
        }
        
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $response = $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
        
        // Prevent caching of login page
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request, TokenStorageInterface $tokenStorage, SessionInterface $session): Response
    {
        // Invalidate the session
        $session->invalidate();
        
        // Clear the security token
        $tokenStorage->setToken(null);
        
        // Clear all session data
        $session->clear();
        
        // Create a response that redirects to login
        $response = $this->redirectToRoute('app_login');
        
        // Add cache control headers to prevent back button access
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        // Clear any cookies
        $response->headers->clearCookie('PHPSESSID');
        
        return $response;
    }
}