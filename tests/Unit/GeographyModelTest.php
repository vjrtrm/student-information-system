<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\State;
use App\Models\District;
use App\Models\Taluk;

/**
 * Unit tests for the State, District, and Taluk models (Module 2 — Master Data).
 *
 * The geography tables (states, districts, taluks) are NOT in the base
 * sis_test_schema(), so they are created in setUp() here.  Once Module 2
 * is promoted to the canonical schema these CREATE TABLE calls should be
 * moved into bootstrap.php's sis_test_schema().
 */
class GeographyModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // creates base schema + injects PDO into Db

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS states (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS districts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                state_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT
            )"
        );
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS taluks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                district_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                created_at TEXT
            )"
        );
    }

    // =========================================================================
    // State
    // =========================================================================

    public function testStateCreateAndFind(): void
    {
        $id    = State::create('Tamil Nadu');
        $state = State::find($id);
        $this->assertNotNull($state);
        $this->assertSame('Tamil Nadu', $state['name']);
        $this->assertSame('active', $state['status']);
    }

    public function testStateNameIsTrimmedOnCreate(): void
    {
        $id    = State::create('  Kerala  ');
        $state = State::find($id);
        $this->assertSame('Kerala', $state['name']);
    }

    public function testStateFindByName(): void
    {
        State::create('Goa');
        $found = State::findByName('Goa');
        $this->assertNotNull($found);
        $this->assertSame('Goa', $found['name']);
    }

    public function testStateFindByNameTrimsInput(): void
    {
        State::create('Goa');
        $found = State::findByName('  Goa  ');
        $this->assertNotNull($found);
    }

    public function testStateFindByNameReturnsNullWhenAbsent(): void
    {
        $this->assertNull(State::findByName('NonExistent'));
    }

    public function testStateFindReturnsNullForMissingId(): void
    {
        $this->assertNull(State::find(9999));
    }

    public function testStateDeactivateSetsInactive(): void
    {
        $id = State::create('Maharashtra');
        State::deactivate($id);
        $this->assertSame('inactive', State::find($id)['status']);
    }

    public function testStateReactivateSetsActive(): void
    {
        $id = State::create('Maharashtra');
        State::deactivate($id);
        State::reactivate($id);
        $this->assertSame('active', State::find($id)['status']);
    }

    public function testStateUpdateChangesNameAndStatus(): void
    {
        $id = State::create('Old Name');
        State::update($id, 'New Name', 'inactive');
        $state = State::find($id);
        $this->assertSame('New Name', $state['name']);
        $this->assertSame('inactive', $state['status']);
    }

    public function testAllActiveStatesExcludesInactive(): void
    {
        $id1 = State::create('ActiveState');
        $id2 = State::create('InactiveState');
        State::deactivate($id2);

        $active = State::allActive();
        $this->assertCount(1, $active);
        $this->assertSame('ActiveState', $active[0]['name']);
    }

    public function testAllStatesReturnsEverything(): void
    {
        $id1 = State::create('State A');
        $id2 = State::create('State B');
        State::deactivate($id2);
        $this->assertCount(2, State::all());
    }

    public function testStateInUseReturnsTrueWhenDistrictExists(): void
    {
        $stateId = State::create('Tamil Nadu');
        District::create($stateId, 'Chennai');
        $this->assertTrue(State::inUse($stateId));
    }

    public function testStateInUseReturnsFalseWhenNoDistricts(): void
    {
        $stateId = State::create('Isolated State');
        $this->assertFalse(State::inUse($stateId));
    }

    // =========================================================================
    // District
    // =========================================================================

    public function testDistrictCreateAndFind(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $district   = District::find($districtId);
        $this->assertNotNull($district);
        $this->assertSame('Chennai', $district['name']);
        $this->assertSame($stateId, (int)$district['state_id']);
        $this->assertSame('active', $district['status']);
    }

    public function testDistrictByStateReturnsOnlyThatStatesRows(): void
    {
        $stateId1 = State::create('Tamil Nadu');
        $stateId2 = State::create('Kerala');
        District::create($stateId1, 'Chennai');
        District::create($stateId1, 'Coimbatore');
        District::create($stateId2, 'Thiruvananthapuram');

        $tn = District::byState($stateId1, false);
        $this->assertCount(2, $tn);

        $kl = District::byState($stateId2, false);
        $this->assertCount(1, $kl);
    }

    public function testDistrictByStateDefaultActiveOnly(): void
    {
        $stateId = State::create('Tamil Nadu');
        $d1      = District::create($stateId, 'Chennai');
        $d2      = District::create($stateId, 'Coimbatore');
        District::deactivate($d2);

        // Default argument is activeOnly = true
        $active = District::byState($stateId);
        $this->assertCount(1, $active);
        $this->assertSame('Chennai', $active[0]['name']);
    }

    public function testDistrictByStateActiveOnlyFalseReturnsAll(): void
    {
        $stateId = State::create('Tamil Nadu');
        $d1      = District::create($stateId, 'Chennai');
        $d2      = District::create($stateId, 'Coimbatore');
        District::deactivate($d2);

        $all = District::byState($stateId, false);
        $this->assertCount(2, $all);
    }

    public function testDistrictFindByNameAndState(): void
    {
        $stateId = State::create('Tamil Nadu');
        District::create($stateId, 'Madurai');
        $found = District::findByNameAndState('Madurai', $stateId);
        $this->assertNotNull($found);
        $this->assertSame('Madurai', $found['name']);
    }

    public function testDistrictFindByNameAndStateReturnsNullForWrongState(): void
    {
        $stateId1 = State::create('Tamil Nadu');
        $stateId2 = State::create('Kerala');
        District::create($stateId1, 'Madurai');
        $this->assertNull(District::findByNameAndState('Madurai', $stateId2));
    }

    public function testDistrictDeactivateAndReactivate(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Salem');
        District::deactivate($districtId);
        $this->assertSame('inactive', District::find($districtId)['status']);
        District::reactivate($districtId);
        $this->assertSame('active', District::find($districtId)['status']);
    }

    public function testDistrictUpdateChangesFields(): void
    {
        $stateId1   = State::create('Tamil Nadu');
        $stateId2   = State::create('Kerala');
        $districtId = District::create($stateId1, 'OldName');
        District::update($districtId, $stateId2, 'NewName', 'inactive');
        $d = District::find($districtId);
        $this->assertSame('NewName', $d['name']);
        $this->assertSame($stateId2, (int)$d['state_id']);
        $this->assertSame('inactive', $d['status']);
    }

    public function testDistrictInUseReturnsTrueWhenTalukExists(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        Taluk::create($districtId, 'Ambattur');
        $this->assertTrue(District::inUse($districtId));
    }

    public function testDistrictInUseReturnsFalseWhenNoTaluks(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $this->assertFalse(District::inUse($districtId));
    }

    // =========================================================================
    // Taluk
    // =========================================================================

    public function testTalukCreateAndFind(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $talukId    = Taluk::create($districtId, 'Ambattur');
        $taluk      = Taluk::find($talukId);
        $this->assertNotNull($taluk);
        $this->assertSame('Ambattur', $taluk['name']);
        $this->assertSame($districtId, (int)$taluk['district_id']);
        $this->assertSame('active', $taluk['status']);
    }

    public function testTalukByDistrictDefaultActiveOnly(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $t1 = Taluk::create($districtId, 'Ambattur');
        $t2 = Taluk::create($districtId, 'Avadi');
        Taluk::deactivate($t2);

        $active = Taluk::byDistrict($districtId);
        $this->assertCount(1, $active);
        $this->assertSame('Ambattur', $active[0]['name']);
    }

    public function testTalukByDistrictAllWhenNotActiveOnly(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $t1 = Taluk::create($districtId, 'Ambattur');
        $t2 = Taluk::create($districtId, 'Avadi');
        Taluk::deactivate($t2);

        $all = Taluk::byDistrict($districtId, false);
        $this->assertCount(2, $all);
    }

    public function testTalukDeactivateAndReactivate(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $talukId    = Taluk::create($districtId, 'Ambattur');
        Taluk::deactivate($talukId);
        $this->assertSame('inactive', Taluk::find($talukId)['status']);
        Taluk::reactivate($talukId);
        $this->assertSame('active', Taluk::find($talukId)['status']);
    }

    public function testTalukFindByNameAndDistrict(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        Taluk::create($districtId, 'Perambur');
        $found = Taluk::findByNameAndDistrict('Perambur', $districtId);
        $this->assertNotNull($found);
        $this->assertSame('Perambur', $found['name']);
    }

    public function testTalukFindByNameAndDistrictReturnsNullForWrongDistrict(): void
    {
        $stateId  = State::create('Tamil Nadu');
        $distId1  = District::create($stateId, 'Chennai');
        $distId2  = District::create($stateId, 'Coimbatore');
        Taluk::create($distId1, 'Perambur');
        $this->assertNull(Taluk::findByNameAndDistrict('Perambur', $distId2));
    }

    public function testTalukAllIncludesDistrictName(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $talukId    = Taluk::create($districtId, 'Ambattur');

        $taluks = Taluk::all();
        $this->assertSame('Chennai', $taluks[0]['district_name']);
        $this->assertSame($talukId, (int)$taluks[0]['id']);
    }

    public function testTalukUpdateChangesFields(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $talukId    = Taluk::create($districtId, 'OldTaluk');
        Taluk::update($talukId, $districtId, 'NewTaluk', 'inactive');
        $t = Taluk::find($talukId);
        $this->assertSame('NewTaluk', $t['name']);
        $this->assertSame('inactive', $t['status']);
    }

    /**
     * Taluks are the leaf of the hierarchy; ReferenceCheck::MAP['taluk'] is empty,
     * so inUse() must always return false.
     */
    public function testTalukInUseAlwaysReturnsFalse(): void
    {
        $stateId    = State::create('Tamil Nadu');
        $districtId = District::create($stateId, 'Chennai');
        $talukId    = Taluk::create($districtId, 'Ambattur');
        $this->assertFalse(Taluk::inUse($talukId));
    }
}
