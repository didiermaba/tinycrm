<?php

namespace App\Controller;

use App\Entity\Transaction;
use App\Form\PaymentType;
use App\Repository\ClientRepository;
use App\Repository\OffreRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\StripeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payment')]
class PaymentController extends AbstractController
{
    #[Route('/', name: 'app_payment')]
    public function index(
        Request $request,
        StripeService $stripeService,
        OffreRepository $offres,
        ClientRepository $clients,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        TransactionRepository $transaction,
        ): Response
    {

        $form = $this->createForm(PaymentType::class);
        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $offre = $offres->findOneBy(['id' => $data['offre']->getId()]); // Offre à vendre (titre et montant)
            $clientEmail = $clients->findOneBy(['id' => $data['client']->getId()])->getEmail();
            $apiKey = $this->getParameter('STRIPE_API_KEY_SECRET'); // Clé API secrète
            $link = $stripeService->makePayment(
                 $apiKey,
                 $offre->getMontant(),
                 $offre->getTitre(),
                 $clientEmail
             );

        // Envoie du lien au client
        $email = (new Email())
             ->from('hello@tinycrm.app')
             ->to($clientEmail)
             ->priority(Email::PRIORITY_HIGH)
             ->subject('Merci de procéder au paiement de votre offre')
             ->html('<div style="background-colr: #f4f4f4; padding: 20px; text-align: center;">
             <h1>Bonjour</h1><br><br>
             <p>Voici le lien pour effectuer le reglement de votre offre:</p><br>
             <a href="'.$link.'">Payer</a></br>
             <hr>
             <p>Ce lien est valable pour une duree limitée</p><br></div>
    ');

    $mailer->send($email);

    $transaction = new Transaction();
    $transaction->setClient($data['client'])
                ->setMontant($offre->getMontant())
                ->setStatut('En attente')
                ;
    $em->persist($transaction); // EntityManagerInterface
    $em->flush();            

    }

    return $this->render('payment/index.html.twig', [
        'form' => $form->createView(),
    ]);

}

    #[Route('/success', name: 'payment_success')]
    public function success(): Response
    {
        return $this->render('payment/success.html.twig', [
            'controller_name' => 'PaymentController',
        ]);
    }
    #[Route('/cancel', name: 'payment_cancel')]
    public function cancel(): Response
    {
        return $this->render('payment/cancel.html.twig', [
            'controller_name' => 'PaymentController',
        ]);
    }
}