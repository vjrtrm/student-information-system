<?php
namespace Tests\Integration;

use Tests\TestCase;
use App\Helpers\Db;
use App\Models\PromotionBatch;

class PromotionWindowTest extends TestCase
{
    public function testWindowClosedByDefault(): void
    {
        $this->assertFalse(PromotionBatch::isWindowOpen());
    }

    public function testToggleWindowOpen(): void
    {
        Db::execute("UPDATE settings SET value = '1' WHERE key = 'promotion_window_open'");
        $this->assertTrue(PromotionBatch::isWindowOpen());
    }

    public function testToggleWindowClosed(): void
    {
        Db::execute("UPDATE settings SET value = '1' WHERE key = 'promotion_window_open'");
        Db::execute("UPDATE settings SET value = '0' WHERE key = 'promotion_window_open'");
        $this->assertFalse(PromotionBatch::isWindowOpen());
    }

    public function testReplaceIntoSettings(): void
    {
        // INSERT OR REPLACE works on SQLite
        Db::execute("INSERT OR REPLACE INTO settings (key, value) VALUES ('promotion_window_open', '1')");
        $this->assertTrue(PromotionBatch::isWindowOpen());

        Db::execute("INSERT OR REPLACE INTO settings (key, value) VALUES ('promotion_window_open', '0')");
        $this->assertFalse(PromotionBatch::isWindowOpen());
    }

    public function testAuditLogOnWindowToggle(): void
    {
        \App\Helpers\MasterAuditLogger::log('toggle_window', 'promotion_window', null, ['new_state' => 'open']);
        $entry = Db::selectOne("SELECT * FROM audit_log WHERE action = 'toggle_window'");
        $this->assertNotNull($entry);
        $this->assertSame('promotion_window', $entry['entity']);
    }
}
