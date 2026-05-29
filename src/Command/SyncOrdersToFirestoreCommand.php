<?php

namespace App\Command;

use App\Repository\OrderRepository;
use App\Service\Firebase\FirebaseAdminFactory;
use App\Service\Firestore\FirestoreOrderSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-orders-firestore',
    description: 'Push all MySQL orders into Firestore (one-time backfill or repair).',
)]
final class SyncOrdersToFirestoreCommand extends Command
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly FirestoreOrderSyncService $firestoreOrders,
        private readonly FirebaseAdminFactory $firebase,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->firebase->isConfigured()) {
            $io->error('Firebase Admin is not configured. Set FIREBASE_CREDENTIALS_JSON on the server.');

            return Command::FAILURE;
        }

        $all = $this->orders->findAll();
        $count = 0;

        foreach ($all as $order) {
            $this->firestoreOrders->sync($order);
            ++$count;
        }

        $io->success(sprintf('Synced %d order(s) to Firestore.', $count));

        return Command::SUCCESS;
    }
}
