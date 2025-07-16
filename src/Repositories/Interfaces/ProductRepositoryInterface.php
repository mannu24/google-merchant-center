<?php

namespace Manu\GMCIntegration\Repositories\Interfaces;

interface ProductRepositoryInterface
{
    public function uploadProduct(array $productData);
    public function updateProduct(string $productId, array $productData);
    public function deleteProduct(string $productId);
    public function getProduct(string $productId);
} 