<?php
namespace Tests\Unit;

use App\Helpers\View;
use PHPUnit\Framework\TestCase;

class MaskAadhaarTest extends TestCase
{
    public function test_twelve_digit_number_masked_correctly(): void
    {
        $this->assertSame('XXXX-XXXX-9012', View::maskAadhaar('123456789012'));
    }

    public function test_null_returns_dash(): void
    {
        $this->assertSame('—', View::maskAadhaar(null));
    }

    public function test_four_digit_number_shows_all(): void
    {
        $this->assertSame('XXXX-XXXX-1234', View::maskAadhaar('1234'));
    }

    public function test_empty_string_returns_dash(): void
    {
        $this->assertSame('—', View::maskAadhaar(''));
    }

    public function test_three_digit_number_returns_dash(): void
    {
        $this->assertSame('—', View::maskAadhaar('123'));
    }
}
