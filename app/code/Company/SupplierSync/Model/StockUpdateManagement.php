<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\StockUpdateInputInterface;
use Company\SupplierSync\Api\Data\StockUpdateResponseInterface;
use Company\SupplierSync\Api\Data\StockUpdateResponseInterfaceFactory;
use Company\SupplierSync\Api\StockUpdateAuditRepositoryInterface;
use Company\SupplierSync\Api\StockUpdateManagementInterface;
use Company\SupplierSync\Model\Queue\StockUpdateMessageFactory;
use Company\SupplierSync\Model\Queue\StockUpdatePublisher;
use Company\SupplierSync\Model\Service\IdempotencyService;
use Company\SupplierSync\Model\Service\StockUpdateValidator;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

class StockUpdateManagement implements StockUpdateManagementInterface
{
    private IdempotencyService $idempotencyService;
    private StockUpdateValidator $validator;
    private StockUpdateAuditFactory $auditFactory;
    private StockUpdateAuditRepositoryInterface $auditRepository;
    private StockUpdatePublisher $publisher;
    private StockUpdateMessageFactory $messageFactory;
    private StockUpdateResponseInterfaceFactory $responseFactory;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;

    public function __construct(
        IdempotencyService $idempotencyService,
        StockUpdateValidator $validator,
        StockUpdateAuditFactory $auditFactory,
        StockUpdateAuditRepositoryInterface $auditRepository,
        StockUpdatePublisher $publisher,
        StockUpdateMessageFactory $messageFactory,
        StockUpdateResponseInterfaceFactory $responseFactory,
        SerializerInterface $serializer,
        LoggerInterface $logger
    ) {
        $this->idempotencyService = $idempotencyService;
        $this->validator = $validator;
        $this->auditFactory = $auditFactory;
        $this->auditRepository = $auditRepository;
        $this->publisher = $publisher;
        $this->messageFactory = $messageFactory;
        $this->responseFactory = $responseFactory;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    public function postUpdate(StockUpdateInputInterface $update): StockUpdateResponseInterface
    {
        $normalized = $this->idempotencyService->getNormalizedPayload($update);
        $payloadHash = $this->idempotencyService->computePayloadHash($normalized);

        // 1. Check idempotency conflict or duplicate replay
        $existingAudit = $this->idempotencyService->validateAndCheckExisting($update, $payloadHash);
        if ($existingAudit !== null) {
            $this->logger->info(sprintf(
                '[SupplierSync] Idempotent request replay for key "%s". Returning current status "%s".',
                $existingAudit->getIdempotencyKey(),
                $existingAudit->getStatus()
            ));

            /** @var StockUpdateResponseInterface $response */
            $response = $this->responseFactory->create();
            $response->setStatus($existingAudit->getStatus());
            $response->setIdempotencyKey($existingAudit->getIdempotencyKey());
            $response->setAuditId((int)$existingAudit->getAuditId());
            $response->setMessage('Request already accepted under identical idempotency key.');
            return $response;
        }

        // 2. Validate business rules & scopes
        $this->validator->validate($update);

        // 3. Persist audit / outbox entry
        /** @var StockUpdateAudit $audit */
        $audit = $this->auditFactory->create();
        $audit->setIdempotencyKey(trim($update->getIdempotencyKey()));
        $audit->setSupplierCode(trim($update->getSupplierCode()));
        $audit->setSku(trim($update->getSku()));
        $audit->setWebsiteCode(trim($update->getWebsiteCode()));
        $audit->setPayloadHash($payloadHash);
        $audit->setRequestPayload($this->serializer->serialize($normalized));
        $audit->setStatus(StockUpdateAudit::STATUS_PENDING);
        $audit->setAttempts(0);

        $this->auditRepository->save($audit);

        // 4. Publish message to asynchronous message queue
        $message = $this->messageFactory->create();
        $message->setAuditId((int)$audit->getAuditId());
        $message->setIdempotencyKey($audit->getIdempotencyKey());
        $message->setSupplierCode($audit->getSupplierCode());
        $message->setSku($audit->getSku());
        $message->setQuantity((float)$update->getQuantity());
        $message->setUnitPrice((float)$update->getUnitPrice());
        $message->setCurrency($audit->getWebsiteCode());
        $message->setWebsiteCode($update->getWebsiteCode());
        $message->setCurrency(strtoupper(trim($update->getCurrency())));

        $published = $this->publisher->publish($message);
        if (!$published) {
            $this->logger->warning(sprintf(
                '[SupplierSync] Outbox message saved but publishing failed immediately for key "%s". Outbox worker will retry.',
                $audit->getIdempotencyKey()
            ));
        }

        /** @var StockUpdateResponseInterface $response */
        $response = $this->responseFactory->create();
        $response->setStatus(StockUpdateAudit::STATUS_PENDING);
        $response->setIdempotencyKey($audit->getIdempotencyKey());
        $response->setAuditId((int)$audit->getAuditId());
        $response->setMessage('Stock update request has been accepted for asynchronous processing.');

        return $response;
    }
}
