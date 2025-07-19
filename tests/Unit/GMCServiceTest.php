<?php

namespace Mannu24\GMCIntegration\Tests\Unit;

use Mannu24\GMCIntegration\Tests\TestCase;
use Mannu24\GMCIntegration\Services\GMCService;

class GMCServiceTest extends TestCase
{
    /** @test */
    public function it_can_instantiate_gmc_service()
    {
        $service = $this->app->make(GMCService::class);
        
        $this->assertInstanceOf(GMCService::class, $service);
    }
    
    /** @test */
    public function it_validates_product_data_correctly()
    {
        $service = $this->app->make(GMCService::class);
        
        $validData = [
            'offerId' => '123',
            'title' => 'Test Product',
            'description' => 'Test Description',
            'link' => 'https://example.com/product/123',
            'imageLink' => 'https://example.com/image.jpg',
            'price' => ['value' => '29.99', 'currency' => 'USD'],
            'availability' => 'in stock'
        ];
        
        $this->assertTrue($service->validateProductData($validData));
    }
} 