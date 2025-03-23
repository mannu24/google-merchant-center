<?php

namespace Manu\GMCIntegration\Repositories\Interfaces;

interface GMCRepositoryInterface
{
    public function uploadProduct(array $productData);
    public function deleteProduct(string $productId);
}
