<?php
declare(strict_types=1);

namespace Company\SupplierSync\Logger;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger as MonologLogger;

class Handler extends BaseHandler
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/supplier_sync.log';

    /**
     * @var int
     */
    protected $loggerType = MonologLogger::DEBUG;
}
