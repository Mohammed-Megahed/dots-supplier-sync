<?php
declare(strict_types=1);

namespace Company\SupplierSync\Console\Command;

use Company\SupplierSync\Model\ResourceModel\SupplierStock as SupplierStockResource;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SimulateWorkerCommand extends Command
{
    private SupplierStockResource $stockResource;

    public function __construct(
        SupplierStockResource $stockResource,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->stockResource = $stockResource;
    }

    protected function configure(): void
    {
        $this->setName('supplier:simulate:worker')
            ->setDescription('Internal worker command to test atomic reservation under race conditions')
            ->addOption('stock-id', null, InputOption::VALUE_REQUIRED, 'Stock row ID')
            ->addOption('qty', null, InputOption::VALUE_REQUIRED, 'Quantity to reserve');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stockId = (int)$input->getOption('stock-id');
        $qty = (float)$input->getOption('qty');

        if ($stockId <= 0 || $qty <= 0) {
            $output->writeln('INVALID_INPUT');
            return Cli::RETURN_FAILURE;
        }

        $reserved = $this->stockResource->atomicReserveById($stockId, $qty);
        if ($reserved) {
            $output->writeln('SUCCESS');
            return Cli::RETURN_SUCCESS;
        }

        $output->writeln('FAILED_INSUFFICIENT_STOCK');
        return Cli::RETURN_FAILURE;
    }
}
