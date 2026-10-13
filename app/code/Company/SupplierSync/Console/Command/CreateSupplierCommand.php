<?php
declare(strict_types=1);

namespace Company\SupplierSync\Console\Command;

use Company\SupplierSync\Api\SupplierRepositoryInterface;
use Company\SupplierSync\Model\SupplierFactory;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateSupplierCommand extends Command
{
    private SupplierFactory $supplierFactory;
    private SupplierRepositoryInterface $supplierRepository;

    public function __construct(
        SupplierFactory $supplierFactory,
        SupplierRepositoryInterface $supplierRepository,
        ?string $name = null
    ) {
        parent::__construct($name);
        $this->supplierFactory = $supplierFactory;
        $this->supplierRepository = $supplierRepository;
    }

    protected function configure(): void
    {
        $this->setName('supplier:create')
            ->setDescription('Create or update a supplier entity')
            ->addOption('code', 'c', InputOption::VALUE_REQUIRED, 'Unique Supplier Code (e.g. SUPPLIER_001)')
            ->addOption('name', 'm', InputOption::VALUE_REQUIRED, 'Supplier Name (e.g. ACME Parts)')
            ->addOption('enabled', 'e', InputOption::VALUE_OPTIONAL, 'Enabled status (1 or 0)', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = trim((string)$input->getOption('code'));
        $name = trim((string)$input->getOption('name'));
        $enabled = (int)$input->getOption('enabled');

        if (empty($code) || empty($name)) {
            $output->writeln('<error>Both --code and --name are required options.</error>');
            return Cli::RETURN_FAILURE;
        }

        try {
            try {
                $supplier = $this->supplierRepository->getByCode($code);
                $output->writeln(sprintf('<comment>Supplier with code "%s" already exists (ID: %d). Updating...</comment>', $code, $supplier->getSupplierId()));
            } catch (\Exception $e) {
                $supplier = $this->supplierFactory->create();
                $supplier->setSupplierCode($code);
            }

            $supplier->setName($name);
            $supplier->setIsEnabled($enabled === 1);
            $this->supplierRepository->save($supplier);

            $output->writeln(sprintf(
                '<info>Successfully saved supplier "%s" (Code: %s, ID: %d, Status: %s)</info>',
                $supplier->getName(),
                $supplier->getSupplierCode(),
                $supplier->getSupplierId(),
                $supplier->getIsEnabled() ? 'Enabled' : 'Disabled'
            ));

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Error saving supplier: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }
}
