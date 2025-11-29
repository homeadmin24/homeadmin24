<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Detect if this is the demo environment
        $host = $request->getHost();
        $isDemoEnvironment = str_contains($host, 'demo.homeadmin24.de');

        // Calculate next reset time (on the hour and half-hour)
        $nextResetTime = null;
        if ($isDemoEnvironment) {
            $now = new \DateTime();
            $minutes = (int) $now->format('i');

            // If before :30, next reset is :30, otherwise it's next hour :00
            if ($minutes < 30) {
                $nextResetTime = (clone $now)->setTime((int) $now->format('H'), 30, 0);
            } else {
                $nextResetTime = (clone $now)->modify('+1 hour')->setTime((int) $now->format('H') + 1, 0, 0);
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'is_demo' => $isDemoEnvironment,
            'next_reset_time' => $nextResetTime,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
