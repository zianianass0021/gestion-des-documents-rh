<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/responsable-rh/performance')]
#[IsGranted('ROLE_RESPONSABLE_RH')]
class PerformanceController extends AbstractController
{
    #[Route('/rapport-a', name: 'performance_rapport_a')]
    public function rapportA(): Response
    {
        // Charger les données du fichier A.json
        $jsonData = file_get_contents(__DIR__ . '/../../A.json');
        $data = json_decode($jsonData, true);
        
        return $this->render('responsable-rh/performance/rapport_a.html.twig', [
            'data' => $data,
            'title' => 'Rapport A - Performance par Nature de Contrat'
        ]);
    }

    #[Route('/rapport-b', name: 'performance_rapport_b')]
    public function rapportB(): Response
    {
        // Charger les données du fichier B.json
        $jsonData = file_get_contents(__DIR__ . '/../../B.json');
        $data = json_decode($jsonData, true);
        
        return $this->render('responsable-rh/performance/rapport_b.html.twig', [
            'data' => $data,
            'title' => 'Rapport B - Performance par Organisation'
        ]);
    }

    #[Route('/rapport-c', name: 'performance_rapport_c')]
    public function rapportC(): Response
    {
        // Charger les données du fichier C.json
        $jsonData = file_get_contents(__DIR__ . '/../../C.json');
        $data = json_decode($jsonData, true);
        
        return $this->render('responsable-rh/performance/rapport_c.html.twig', [
            'data' => $data,
            'title' => 'Rapport C - Performance Détaillée par Organisation'
        ]);
    }

    #[Route('/rapport-d', name: 'performance_rapport_d')]
    public function rapportD(): Response
    {
        // Charger les données du fichier D.json
        $jsonData = file_get_contents(__DIR__ . '/../../D.json');
        $data = json_decode($jsonData, true);
        
        return $this->render('responsable-rh/performance/rapport_d.html.twig', [
            'data' => $data,
            'title' => 'Rapport D - Performance Détaillée par Nature de Contrat'
        ]);
    }

    #[Route('/rapport-e', name: 'performance_rapport_e')]
    public function rapportE(): Response
    {
        // Charger les données du fichier E.json
        $jsonData = file_get_contents(__DIR__ . '/../../E.json');
        $data = json_decode($jsonData, true);
        
        return $this->render('responsable-rh/performance/rapport_e.html.twig', [
            'data' => $data,
            'title' => 'Rapport E - Performance par Organisation et Nature de Contrat'
        ]);
    }

    #[Route('/rapport-f', name: 'performance_rapport_f')]
    public function rapportF(): Response
    {
        // Charger les données du fichier F.json
        $jsonData = file_get_contents(__DIR__ . '/../../F.json');
        $data = json_decode($jsonData, true);
        
        return $this->render('responsable-rh/performance/rapport_f.html.twig', [
            'data' => $data,
            'title' => 'Rapport F - Performance Détaillée par Organisation et Nature'
        ]);
    }

    #[Route('/', name: 'performance_index')]
    public function index(): Response
    {
        return $this->render('responsable-rh/performance/index.html.twig', [
            'title' => 'Rapports de Performance'
        ]);
    }
}
