<?php
declare(strict_types=1);

namespace Company\SupplierSync\Console\Command;

use Company\SupplierSync\Api\SupplierRepositoryInterface;
use Company\SupplierSync\Api\SupplierStockRepositoryInterface;
use Company\SupplierSync\Model\ResourceModel\SupplierStock as SupplierStockResource;
use Company\SupplierSync\Model\SupplierFactory;
use Company\SupplierSync\Model\SupplierStockFactory;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SimulateConcurrencyCommand extends Command
{
    private SupplierFactory $supplierFactory;
    private SupplierRepositoryInterface $supplierRepository;
    private SupplierStockFactory $supplierStockFactory;
    private SupplierStockRepositoryInterface $supplierStockRepository;
    private SupplierStockResource $stockResource;

    public function __construct(
        SupplierFactory $supplierFactory,
        SupplierRepositoryInterface $supplierRepository,
        SupplierStockFactory $supplierStockFactory,
        SupplierStockRepositoryInterface $supplierStockRepository,
        SupplierStockResource $stockResource,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->supplierFactory = $supplierFactory;
        $this->supplierRepository = $supplierRepository;
        $this->supplierStockFactory = $supplierStockFactory;
        $this->supplierStockRepository = $supplierStockRepository;
        $this->stockResource = $stockResource;
    }

    protected function configure(): void
    {
        $this->setName('supplier:simulate:concurrency')
            ->setDescription('Demonstrate concurrency safety with 20 parallel reservation attempts competing for 10 units')
            ->addOption('sku', null, InputOption::VALUE_OPTIONAL, 'SKU to test', 'CONCURRENCY-TEST-SKU')
            ->addOption('units', 'u', InputOption::VALUE_OPTIONAL, 'Available stock units', '10')
            ->addOption('threads', 't', InputOption::VALUE_OPTIONAL, 'Number of concurrent attempts', '20')
            ->addOption('qty', null, InputOption::VALUE_OPTIONAL, 'Quantity per attempt', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sku = (string)$input->getOption('sku');
        $units = (float)$input->getOption('units');
        $threads = (int)$input->getOption('threads');
        $qtyPerAttempt = (float)$input->getOption('qty');
        $supplierCode = 'TEST_CONCURRENCY_SUPPLIER';

        $output->writeln('<info>=========================================================</info>');
        $output->writeln('<info> Supplier Stock Concurrency Safety Demonstration </info>');
        $output->writeln('<info>=========================================================</info>');
        $output->writeln(sprintf('Parameters: Initial Stock = %.0f units | Concurrent Attempts = %d | Qty/Attempt = %.0f', $units, $threads, $qtyPerAttempt));

        // 1. Ensure test supplier exists
        try {
            $supplier = $this->supplierRepository->getByCode($supplierCode);
        } catch (\Exception $e) {
            $supplier = $this->supplierFactory->create();
            $supplier->setSupplierCode($supplierCode);
            $supplier->setName('Concurrency Test Supplier');
            $supplier->setIsEnabled(true);
            $this->supplierRepository->save($supplier);
        }

        $supplierId = (int)$supplier->getSupplierId();

        // 2. Setup clean stock row: units = 10, reserved = 0
        try {
            $stock = $this->supplierStockRepository->getBySupplierSkuAndWebsite($supplierId, $sku, 1);
        } catch (\Exception $e) {
            $stock = $this->supplierStockFactory->create();
            $stock->setSupplierId($supplierId);
            $stock->setSku($sku);
            $stock->setWebsiteId(1);
        }

        $stock->setQuantity($units);
        $stock->setReservedQuantity(0.0);
        $stock->setUnitPrice(99.00);
        $stock->setCurrency('USD');
        $stock->setVersion(1);
        $this->supplierStockRepository->save($stock);

        $stockId = (int)$stock->getStockId();
        $output->writeln(sprintf('Test Stock Row ID #%d prepared. Available = %.0f, Reserved = 0.00', $stockId, $units));

        // 3. Launch parallel worker processes using proc_open
        $output->writeln(sprintf('Launching %d parallel worker processes competing for the row...', $threads));

        $phpBinary = PHP_BINARY;
        $bootstrapScript = BP . '/bin/magento';
        $cmdTemplate = "%s %s supplier:simulate:worker --stock-id=%d --qty=%.4f";

        $processes = [];
        $pipes = [];

        for ($i = 0; $i < $threads; $i++) {
            $cmd = sprintf($cmdTemplate, escapeshellarg($phpBinary), escapeshellarg($bootstrapScript), $stockId, $qtyPerAttempt);
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $proc = proc_open($cmd, $descriptor, $pipes[$i]);
            if (is_resource($proc)) {
                $processes[$i] = $proc;
            }
        }

        $successCount = 0;
        $rejectedCount = 0;

        foreach ($processes as $i => $proc) {
            $stdout = stream_get_contents($pipes[$i][1]);
            $stderr = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][0]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $returnCode = proc_close($proc);

            if ($returnCode === 0 && strpos($stdout, 'SUCCESS') !== false) {
                $successCount++;
            } else {
                $rejectedCount++;
            }
        }

        // 4. Verify Database State
        $updatedStock = $this->supplierStockRepository->getById($stockId);
        $finalReserved = $updatedStock->getReservedQuantity();
        $finalAvailable = $updatedStock->getQuantity();

        $output->writeln('<info>---------------------------------------------------------</info>');
        $output->writeln(sprintf('Total Attempts:     %d', $threads));
        $output->writeln(sprintf('Successful Orders:  <info>%d</info>', $successCount));
        $output->writeln(sprintf('Rejected (No Stock):<comment>%d</comment>', $rejectedCount));
        $output->writeln(sprintf('Final Stock Qty:    %.2f', $finalAvailable));
        $output->writeln(sprintf('Final Reserved Qty: <info>%.2f</info>', $finalReserved));
        $output->writeln('<info>---------------------------------------------------------</info>');

        if ($finalReserved <= $units && $successCount <= ($units / $qtyPerAttempt)) {
            $output->writeln('<info>[PASS] CONCURRENCY SAFETY VERIFIED! No over-reservation occurred.</info>');
            return Cli::RETURN_SUCCESS;
        } else {
            $output->writeln('<error>[FAIL] Over-reservation detected! Database race condition found.</error>');
            return Cli::RETURN_FAILURE;
        }
    }
}
