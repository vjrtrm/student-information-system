<?php
namespace Tests\Unit;

use App\Helpers\EnrolmentNumberGenerator;
use Tests\TestCase;

/**
 * Tests EnrolmentNumberGenerator::format() exhaustively.
 * No DB needed — pure logic.
 */
class EnrolmentNumberGeneratorTest extends TestCase
{
    private array $ugDept;
    private array $pgDept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ugDept = ['code' => 'BCA', 'level' => 'UG'];
        $this->pgDept = ['code' => 'MCA', 'level' => 'PG'];
    }

    public function testUgDept2024Serial41(): void
    {
        $result = EnrolmentNumberGenerator::format($this->ugDept, '2024-25', 41);
        $this->assertSame('24UBCA041', $result);
    }

    public function testPgDept2026Serial100(): void
    {
        $result = EnrolmentNumberGenerator::format($this->pgDept, '2026-27', 100);
        $this->assertSame('26PMCA100', $result);
    }

    public function testSerial1IsPaddedTo3Digits(): void
    {
        $result = EnrolmentNumberGenerator::format($this->ugDept, '2024-25', 1);
        $this->assertSame('24UBCA001', $result);
    }

    public function testSerial999IsNotPadded(): void
    {
        $result = EnrolmentNumberGenerator::format($this->ugDept, '2024-25', 999);
        $this->assertSame('24UBCA999', $result);
    }

    public function testYear2030ExtractsTwoDigits(): void
    {
        $result = EnrolmentNumberGenerator::format($this->ugDept, '2030-31', 5);
        $this->assertSame('30UBCA005', $result);
    }

    public function testLevelUgProducesU(): void
    {
        $dept   = ['code' => 'BSC', 'level' => 'UG'];
        $result = EnrolmentNumberGenerator::format($dept, '2025-26', 10);
        $this->assertStringContainsString('U', $result);
        $this->assertSame('25UBSC010', $result);
    }

    public function testLevelPgProducesP(): void
    {
        $dept   = ['code' => 'MBA', 'level' => 'PG'];
        $result = EnrolmentNumberGenerator::format($dept, '2025-26', 7);
        $this->assertStringContainsString('P', $result);
        $this->assertSame('25PMBA007', $result);
    }

    public function testCodeIsUppercased(): void
    {
        $dept   = ['code' => 'bca', 'level' => 'UG'];
        $result = EnrolmentNumberGenerator::format($dept, '2024-25', 1);
        $this->assertSame('24UBCA001', $result);
    }
}
