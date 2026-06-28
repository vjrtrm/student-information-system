<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\OptionList;
use App\Models\OptionValue;

/**
 * Unit tests for OptionList and OptionValue models (Module 2 — Master Data).
 *
 * option_lists and option_values are NOT in the base sis_test_schema(), so
 * they are created in setUp() here.  Once Module 2 is promoted to the
 * canonical schema these CREATE TABLE statements should move into
 * bootstrap.php's sis_test_schema().
 */
class OptionValueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // creates base schema + injects PDO into Db

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS option_lists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_key TEXT NOT NULL,
                label TEXT NOT NULL,
                created_at TEXT
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS option_values (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                list_id INTEGER NOT NULL,
                value TEXT NOT NULL,
                display TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT
            )"
        );

        // Seed two lists for use across tests
        $this->pdo->exec("INSERT INTO option_lists (list_key, label) VALUES ('blood_group', 'Blood Group')");
        $this->pdo->exec("INSERT INTO option_lists (list_key, label) VALUES ('religion', 'Religion')");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function bloodGroupListId(): int
    {
        return (int)$this->pdo->query(
            "SELECT id FROM option_lists WHERE list_key = 'blood_group'"
        )->fetchColumn();
    }

    private function religionListId(): int
    {
        return (int)$this->pdo->query(
            "SELECT id FROM option_lists WHERE list_key = 'religion'"
        )->fetchColumn();
    }

    // =========================================================================
    // OptionList model
    // =========================================================================

    public function testOptionListFindById(): void
    {
        $id  = $this->bloodGroupListId();
        $row = OptionList::find($id);
        $this->assertNotNull($row);
        $this->assertSame('blood_group', $row['list_key']);
    }

    public function testOptionListFindByKey(): void
    {
        $row = OptionList::findByKey('religion');
        $this->assertNotNull($row);
        $this->assertSame('Religion', $row['label']);
    }

    public function testOptionListFindByKeyReturnsNullForUnknownKey(): void
    {
        $this->assertNull(OptionList::findByKey('no_such_key'));
    }

    public function testOptionListAll(): void
    {
        $all = OptionList::all();
        $this->assertCount(2, $all);
    }

    public function testOptionListWithCountsIncludesActiveValueCount(): void
    {
        $listId = $this->bloodGroupListId();
        OptionValue::create($listId, 'A+', 'A Positive', 10);
        $v2 = OptionValue::create($listId, 'B+', 'B Positive', 20);
        OptionValue::deactivate($v2); // inactive — should not be counted

        $rows = OptionList::withCounts();
        $bg   = array_values(array_filter($rows, fn($r) => $r['list_key'] === 'blood_group'));
        $this->assertCount(1, $bg);
        $this->assertSame(1, (int)$bg[0]['value_count']); // only the active one
    }

    // =========================================================================
    // OptionValue::create / find
    // =========================================================================

    public function testCreateAndFindOptionValue(): void
    {
        $listId = $this->bloodGroupListId();
        $vid    = OptionValue::create($listId, 'AB+', 'AB Positive', 40);
        $v      = OptionValue::find($vid);
        $this->assertNotNull($v);
        $this->assertSame('AB+', $v['value']);
        $this->assertSame('AB Positive', $v['display']);
        $this->assertSame(40, (int)$v['sort_order']);
        $this->assertSame('active', $v['status']);
    }

    public function testCreateTrimsValueAndDisplay(): void
    {
        $listId = $this->bloodGroupListId();
        $vid    = OptionValue::create($listId, '  O+  ', '  O Positive  ', 30);
        $v      = OptionValue::find($vid);
        $this->assertSame('O+', $v['value']);
        $this->assertSame('O Positive', $v['display']);
    }

    // =========================================================================
    // OptionValue::update
    // =========================================================================

    public function testUpdateChangesDisplayAndSortOrder(): void
    {
        $listId = $this->bloodGroupListId();
        $vid    = OptionValue::create($listId, 'AB+', 'AB Positive', 40);
        OptionValue::update($vid, 'AB+', 'AB Positive (updated)', 45);
        $v = OptionValue::find($vid);
        $this->assertSame('AB Positive (updated)', $v['display']);
        $this->assertSame(45, (int)$v['sort_order']);
    }

    public function testUpdateChangesValue(): void
    {
        $listId = $this->bloodGroupListId();
        $vid    = OptionValue::create($listId, 'OldVal', 'Old Display', 10);
        OptionValue::update($vid, 'NewVal', 'New Display', 15);
        $this->assertSame('NewVal', OptionValue::find($vid)['value']);
    }

    // =========================================================================
    // OptionValue::deactivate / reactivate
    // =========================================================================

    public function testDeactivateSetsStatusInactive(): void
    {
        $listId = $this->bloodGroupListId();
        $vid    = OptionValue::create($listId, 'O+', 'O Positive', 30);
        OptionValue::deactivate($vid);
        $this->assertSame('inactive', OptionValue::find($vid)['status']);
    }

    public function testReactivateSetsStatusActive(): void
    {
        $listId = $this->bloodGroupListId();
        $vid    = OptionValue::create($listId, 'O+', 'O Positive', 30);
        OptionValue::deactivate($vid);
        OptionValue::reactivate($vid);
        $this->assertSame('active', OptionValue::find($vid)['status']);
    }

    // =========================================================================
    // OptionValue::byListKey
    // =========================================================================

    public function testByListKeyReturnsAllActiveValues(): void
    {
        $listId = $this->bloodGroupListId();
        OptionValue::create($listId, 'A+', 'A Positive', 10);
        OptionValue::create($listId, 'B+', 'B Positive', 20);
        $values = OptionValue::byListKey('blood_group');
        $this->assertCount(2, $values);
    }

    public function testByListKeyActiveOnlyExcludesInactive(): void
    {
        $listId = $this->bloodGroupListId();
        $v1     = OptionValue::create($listId, 'A+', 'A Positive', 10);
        $v2     = OptionValue::create($listId, 'B+', 'B Positive', 20);
        OptionValue::deactivate($v2);

        $active = OptionValue::byListKey('blood_group', true);
        $this->assertCount(1, $active);
        $this->assertSame('A+', $active[0]['value']);
    }

    public function testByListKeyActiveOnlyFalseReturnsAll(): void
    {
        $listId = $this->bloodGroupListId();
        $v1     = OptionValue::create($listId, 'A+', 'A Positive', 10);
        $v2     = OptionValue::create($listId, 'B+', 'B Positive', 20);
        OptionValue::deactivate($v2);

        $all = OptionValue::byListKey('blood_group', false);
        $this->assertCount(2, $all);
    }

    public function testByListKeyDoesNotReturnValuesFromOtherList(): void
    {
        $bgId  = $this->bloodGroupListId();
        $relId = $this->religionListId();
        OptionValue::create($bgId,  'A+',     'A Positive', 10);
        OptionValue::create($relId, 'Hindu',  'Hindu',      10);
        OptionValue::create($relId, 'Muslim', 'Muslim',     20);

        $bg  = OptionValue::byListKey('blood_group');
        $rel = OptionValue::byListKey('religion');
        $this->assertCount(1, $bg);
        $this->assertCount(2, $rel);
    }

    public function testByListKeyReturnsEmptyForUnknownKey(): void
    {
        $this->assertSame([], OptionValue::byListKey('no_such_key'));
    }

    // =========================================================================
    // OptionValue::byList (by numeric id)
    // =========================================================================

    public function testByListActiveOnlyByDefault(): void
    {
        $listId = $this->bloodGroupListId();
        $v1     = OptionValue::create($listId, 'A+', 'A Positive', 10);
        $v2     = OptionValue::create($listId, 'B+', 'B Positive', 20);
        OptionValue::deactivate($v2);

        $active = OptionValue::byList($listId);
        $this->assertCount(1, $active);
    }

    // =========================================================================
    // OptionValue::maxSortOrder
    // =========================================================================

    public function testMaxSortOrderIsZeroForEmptyList(): void
    {
        $listId = $this->bloodGroupListId();
        $this->assertSame(0, OptionValue::maxSortOrder($listId));
    }

    public function testMaxSortOrderReturnsHighestValue(): void
    {
        $listId = $this->bloodGroupListId();
        OptionValue::create($listId, 'A+', 'A Positive', 10);
        $this->assertSame(10, OptionValue::maxSortOrder($listId));
        OptionValue::create($listId, 'B+', 'B Positive', 50);
        $this->assertSame(50, OptionValue::maxSortOrder($listId));
    }

    public function testMaxSortOrderIsListScoped(): void
    {
        $bgId  = $this->bloodGroupListId();
        $relId = $this->religionListId();
        OptionValue::create($bgId,  'A+',    'A Positive', 100);
        OptionValue::create($relId, 'Hindu', 'Hindu',       5);

        $this->assertSame(100, OptionValue::maxSortOrder($bgId));
        $this->assertSame(5,   OptionValue::maxSortOrder($relId));
    }
}
