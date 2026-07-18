<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SearchController extends AbstractController
{
    #[Route('/api/reservations/search', name: 'api_reservations_search', methods: ['GET'], priority: 10)]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function search(Request $request, Connection $connection): JsonResponse
    {
        $query = $request->query->get('q', '');

        $sql = 'SELECT * FROM reservation WHERE resource_name LIKE :search';

        $results = $connection->fetchAllAssociative($sql, [
            'search' => '%' . $query . '%',
        ]);

        return $this->json($results);
    }
}