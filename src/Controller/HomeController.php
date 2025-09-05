<?php

namespace App\Controller;

use App\Entity\Incident;
use App\Form\IncidentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\MailNotification;

final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(
        Request $request, 
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        LoggerInterface $logger
        ): Response
    {

        $incident = new Incident();

        $incident->setUser($this->getUser())
                 ->setCreatedAt(new \DateTimeImmutable('now'));
        $form = $this->createForm(IncidentType::class, $incident);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($incident);
            $entityManager->flush();

            try {
                
                $logger->info('Tentative d\'envoi d\'email pour l\'incident #' . $incident->getId());
                
                $messageBus->dispatch(new MailNotification($incident->getUser()->getEmail(), $incident->getId(), $incident->getDescription()));
                
                $logger->info('Email envoyé avec succès pour l\'incident #' . $incident->getId());
                $this->addFlash('success', 'Incident créé et email envoyé avec succès !');
                
            } catch (\Exception $e) {
                $logger->error('Erreur lors de l\'envoi d\'email: ' . $e->getMessage());
                $this->addFlash('warning', 'Incident créé mais erreur lors de l\'envoi d\'email: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_home');
        }

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'form' => $form->createView(),
        ]);
    }
}
