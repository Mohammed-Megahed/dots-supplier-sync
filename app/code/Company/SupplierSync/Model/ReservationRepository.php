<?php
declare(strict_types=1);

namespace Company\SupplierSync\Model;

use Company\SupplierSync\Api\Data\ReservationInterface;
use Company\SupplierSync\Model\ResourceModel\Reservation as ReservationResource;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class ReservationRepository
{
    private ReservationResource $resource;
    private ReservationFactory $reservationFactory;

    public function __construct(
        ReservationResource $resource,
        ReservationFactory $reservationFactory
    ) {
        $this->resource = $resource;
        $this->reservationFactory = $reservationFactory;
    }

    public function save(ReservationInterface $reservation): ReservationInterface
    {
        try {
            $this->resource->save($reservation);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save reservation: %1', $e->getMessage()), $e);
        }
        return $reservation;
    }

    public function getById(int $reservationId): ReservationInterface
    {
        $reservation = $this->reservationFactory->create();
        $this->resource->load($reservation, $reservationId);
        if (!$reservation->getReservationId()) {
            throw new NoSuchEntityException(__('Reservation "%1" does not exist.', $reservationId));
        }
        return $reservation;
    }

    public function getByOrderItemId(int $orderItemId): ReservationInterface
    {
        $reservation = $this->reservationFactory->create();
        $this->resource->loadByOrderItemId($reservation, $orderItemId);
        if (!$reservation->getReservationId()) {
            throw new NoSuchEntityException(__('Reservation for order item "%1" does not exist.', $orderItemId));
        }
        return $reservation;
    }
}
