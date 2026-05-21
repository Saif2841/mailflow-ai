<?php

namespace App\Command;

use App\Service\Supabase\SupabaseRestClient;
use RuntimeException;
use SplFileObject;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:sync-orders-csv',
    description: 'Sync orders from storage/imports/orders.csv into Supabase.'
)]
final class SyncOrdersCsvCommand extends Command
{
    public function __construct(
        private SupabaseRestClient $supabase,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvPath = $this->projectDir.'/storage/imports/orders.csv';

        if (!is_file($csvPath)) {
            throw new RuntimeException('Orders CSV not found at '.$csvPath);
        }

        $file = new SplFileObject($csvPath);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $headers = [];
        $rowCount = 0;
        $upserted = 0;

        foreach ($file as $index => $row) {
            if ($row === [null] || $row === false) {
                continue;
            }

            if ($index === 0) {
                $headers = array_map('trim', $row);
                continue;
            }

            if (count($headers) === 0) {
                $io->error('CSV header row is missing.');
                return Command::FAILURE;
            }

            $data = array_combine($headers, $row);
            if ($data === false) {
                continue;
            }

            $rowCount++;

            $payload = [
                'order_number' => (string) ($data['order_number'] ?? ''),
                'customer_name' => (string) ($data['customer_name'] ?? ''),
                'customer_email' => (string) ($data['customer_email'] ?? ''),
                'product_name' => (string) ($data['product_name'] ?? ''),
                'product_sku' => (string) ($data['product_sku'] ?? ''),
                'quantity' => $this->toInt($data['quantity'] ?? null),
                'total_amount' => $this->toFloat($data['total_amount'] ?? null),
                'order_date' => $this->toDate($data['order_date'] ?? null),
                'shipping_status' => (string) ($data['shipping_status'] ?? ''),
                'tracking_number' => (string) ($data['tracking_number'] ?? ''),
                'carrier' => (string) ($data['carrier'] ?? ''),
                'delivery_address' => (string) ($data['delivery_address'] ?? ''),
            ];

            if ($payload['order_number'] === '') {
                continue;
            }

            $this->supabase->upsert('orders', $payload, 'order_number');
            $upserted++;
        }

        $io->success(sprintf('Processed %d rows, upserted %d orders.', $rowCount, $upserted));

        return Command::SUCCESS;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    private function toDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
