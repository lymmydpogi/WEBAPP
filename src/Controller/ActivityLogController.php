<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;



#[IsGranted('ROLE_ADMIN')]
#[Route('/activity-logs')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'admin_activity_logs_index')]
    public function index(ActivityLogRepository $repo): Response
    {
        $logs = $repo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('ADMIN/activity_logs/index.html.twig', [
            'logs' => $logs
        ]);
    }
}


