<?php

namespace Mannu24\GMCIntegration\Repositories\Interfaces;

interface GMCRepositoryInterface
{
    public function uploadProduct(array $productData);
    public function updateProduct(string $productId, array $productData);
    public function deleteProduct(string $productId);
    public function getProduct(string $productId);
}
