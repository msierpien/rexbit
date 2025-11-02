<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\WarehouseDocument;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use Mockery;

class AuditTrailTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_auditable_trait_methods_exist(): void
    {
        $model = new class extends Model {
            use Auditable;
        };

        $this->assertTrue(method_exists($model, 'auditLogs'));
        $this->assertTrue(method_exists($model, 'auditStatusChange'));
        $this->assertTrue(method_exists($model, 'logCustomAction'));
    }

    public function test_warehouse_document_has_audit_trail(): void
    {
        $this->assertTrue(in_array(Auditable::class, class_uses(WarehouseDocument::class)));
    }

    public function test_audit_log_model_structure(): void
    {
        $auditLog = new AuditLog();

        $expectedFillable = [
            'user_id',
            'action',
            'auditable_type',
            'auditable_id',
            'old_values',
            'new_values',
            'ip_address',
            'user_agent',
        ];

        $this->assertEquals($expectedFillable, $auditLog->getFillable());
        
        $casts = $auditLog->getCasts();
        
        $this->assertEquals('array', $casts['old_values']);
        $this->assertEquals('array', $casts['new_values']);
        $this->assertEquals('datetime', $casts['created_at']);
    }

    public function test_warehouse_document_stock_movement_methods_exist(): void
    {
        $document = new WarehouseDocument();
        
        $reflectionClass = new \ReflectionClass($document);
        
        $this->assertTrue($reflectionClass->hasMethod('applyStockMovements'));
        $this->assertTrue($reflectionClass->hasMethod('reverseStockMovements'));
        $this->assertTrue($reflectionClass->hasMethod('getQuantityChangeForType'));
        $this->assertTrue($reflectionClass->hasMethod('handleStockMovementChange'));
    }

    public function test_document_type_quantity_calculations(): void
    {
        $document = new WarehouseDocument();
        $reflectionClass = new \ReflectionClass($document);
        $method = $reflectionClass->getMethod('getQuantityChangeForType');
        $method->setAccessible(true);

        // Test receiving documents (PZ, IN) - should be positive
        $document->type = 'PZ';
        $this->assertEquals(10, $method->invoke($document, 10));

        $document->type = 'IN';
        $this->assertEquals(5, $method->invoke($document, 5));

        // Test issuing documents (WZ, OUT) - should be negative
        $document->type = 'WZ';
        $this->assertEquals(-10, $method->invoke($document, 10));

        $document->type = 'OUT';
        $this->assertEquals(-5, $method->invoke($document, 5));
    }

    public function test_audit_log_static_method(): void
    {
        $this->assertTrue(method_exists(AuditLog::class, 'log'));
        
        $reflectionMethod = new \ReflectionMethod(AuditLog::class, 'log');
        $this->assertTrue($reflectionMethod->isStatic());
        $this->assertTrue($reflectionMethod->isPublic());
    }
}
