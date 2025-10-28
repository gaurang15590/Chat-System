<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('chat/index.html.twig');
    }

    #[Route('/room/{roomId}', name: 'chat_room')]
    public function room(string $roomId): Response
    {
        return $this->render('chat/index.html.twig', [
            'roomId' => $roomId,
        ]);
    }
    
    #[Route('/simple', name: 'simple_chat')]
    public function simpleChat(): Response
    {
        return new Response(file_get_contents(__DIR__ . '/../../public/simple-chat.html'));
    }
    
    #[Route('/fleet', name: 'fleet_chat')]
    public function fleetChat(): Response
    {
        return new Response(file_get_contents(__DIR__ . '/../../public/fleet-chat.html'));
    }
    
    #[Route('/test', name: 'fleet_test')]
    public function fleetTest(): Response
    {
        return new Response(file_get_contents(__DIR__ . '/../../public/fleet-test.html'));
    }
}