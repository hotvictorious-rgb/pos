<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public string $productCode;
    public string $productName;
    public int $warehouseId;
    public int $availableStock;
    public int $requestedQuantity;

    public function __construct(
        string $message,
        string $productCode = '',
        string $productName = '',
        int $warehouseId = 0,
        int $availableStock = 0,
        int $requestedQuantity = 0,
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->productCode = $productCode;
        $this->productName = $productName;
        $this->warehouseId = $warehouseId;
        $this->availableStock = $availableStock;
        $this->requestedQuantity = $requestedQuantity;
    }
}
