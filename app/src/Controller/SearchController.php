<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    #[Route('/api/reservations/search', name: 'api_reservations_search', methods: ['GET'], priority: 10)]
    public function search(Request $request, Connection $connection): JsonResponse
    {
        $query = $request->query->get('q', '');

        // VULNÉRABLE : concaténation directe de l'entrée utilisateur dans la requête SQL
        $sql = "SELECT * FROM reservation WHERE resource_name LIKE '%" . $query . "%'";

        $results = $connection->fetchAllAssociative($sql);

        return $this->json($results);
    }
}