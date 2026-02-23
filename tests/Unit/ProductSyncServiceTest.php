<?php

namespace Tests\Unit;

use App\Services\ProductSyncService;
use PHPUnit\Framework\TestCase;

class ProductSyncServiceTest extends TestCase
{
    private ProductSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductSyncService();
    }

    public function test_sanitize_collection_removes_prefix_and_suffix(): void
    {
        $this->assertEquals('VERAO', $this->service->sanitizeCollectionName('001 - VERAO/2025'));
    }

    public function test_sanitize_collection_handles_dash_without_spaces(): void
    {
        $this->assertEquals('INVERNO', $this->service->sanitizeCollectionName('002-INVERNO/2024'));
    }

    public function test_sanitize_collection_removes_only_suffix(): void
    {
        $this->assertEquals('VERAO', $this->service->sanitizeCollectionName('VERAO/2025'));
    }

    public function test_sanitize_collection_uppercase(): void
    {
        $this->assertEquals('VERAO', $this->service->sanitizeCollectionName('001 - verao/2025'));
    }

    public function test_sanitize_collection_trims_whitespace(): void
    {
        $this->assertEquals('PRIMAVERA', $this->service->sanitizeCollectionName('  003 - PRIMAVERA  '));
    }

    public function test_sanitize_collection_no_prefix_no_suffix(): void
    {
        $this->assertEquals('CASUAL', $this->service->sanitizeCollectionName('CASUAL'));
    }

    public function test_sanitize_collection_numeric_prefix_with_space(): void
    {
        $this->assertEquals('OUTONO', $this->service->sanitizeCollectionName('004 OUTONO'));
    }

    public function test_sanitize_collection_empty_string(): void
    {
        $this->assertEquals('', $this->service->sanitizeCollectionName(''));
    }
}
