<?php
namespace App\Security;

use App\Security\UserProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    public const LOGIN_ROUTE = 'app_login';
    private UrlGeneratorInterface $urlGenerator;
    private UserProvider $userProvider;

    public function __construct(UrlGeneratorInterface $urlGenerator, UserProvider $userProvider)
    {
        $this->urlGenerator = $urlGenerator;
        $this->userProvider = $userProvider;
    }

    public function authenticate(Request $request): Passport
    {
        $username = (string) $request->request->get('username', '');
        $password = (string) $request->request->get('password', '');
        $request->getSession()->set('_security.last_username', $username);

        // Use the UserProvider explicitly to load the user
        $userBadge = new UserBadge($username, function (string $userIdentifier) {
            try {
                return $this->userProvider->loadUserByIdentifier($userIdentifier);
            } catch (\Symfony\Component\Security\Core\Exception\UserNotFoundException $e) {
                throw new \Symfony\Component\Security\Core\Exception\BadCredentialsException('Invalid credentials.');
            }
        });

        return new Passport(
            $userBadge,
            new PasswordCredentials($password)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $roles = $token->getRoleNames();

        if (in_array('ROLE_ADMINISTRATEUR_RH', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('administrateur_rh_dashboard'));
        }
        if (in_array('ROLE_RESPONSABLE_RH', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('responsable_rh_dashboard'));
        }
        // Managers with ROLE_EMPLOYEE should access employee dashboard
        if (in_array('ROLE_EMPLOYEE', $roles, true) || in_array('ROLE_MANAGER', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
