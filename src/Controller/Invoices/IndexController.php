<?php

namespace App\Controller\Invoices;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends AbstractController
{
    #[Route('/invoices', name: 'invoice_index')]
    public function index(Request $request): Response
    {
        return $this->render('views/invoices/index.html.twig', []);
    }
}
