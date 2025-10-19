<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEventListener(event: KernelEvents::RESPONSE)]
class SecurityHeadersListener
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        
        // Get the current route
        $route = $request->attributes->get('_route');
        
        // Define protected routes that need cache control headers
        $protectedRoutes = [
            'administrateur_rh_dashboard',
            'responsable_rh_dashboard',
            'app_dashboard'
        ];
        
        // Check if current route is protected and user is authenticated
        if (in_array($route, $protectedRoutes) && $this->security->getUser()) {
            // Prevent caching of authenticated pages
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            
            // Add security headers
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('X-XSS-Protection', '1; mode=block');
        }
        
        // For login and register pages, also prevent caching
        if (in_array($route, ['app_login', 'app_register'])) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }
}
