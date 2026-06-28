<?php
namespace Tests\Unit;

use App\Helpers\RtcFieldHelper;
use Tests\TestCase;

class RtcFieldHelperTest extends TestCase
{
    private function makeStudent(string $level = 'UG'): array
    {
        return ['id' => 1, 'programme_level' => $level, 'department_id' => 1];
    }

    private function makeProfile(): array
    {
        return [
            'family_situation'      => 'both_parents',
            'mother_name'           => 'Rani',
            'father_name'           => 'Raja',
            'physically_challenged' => 0,
            'comm_same_as_perm'     => 0,
            'admission_type'        => 'management',
        ];
    }

    public function testBuildsCorrectChangeset(): void
    {
        $changeset = RtcFieldHelper::buildChangeset(
            ['mother_name' => 'Rani Kumar'],
            $this->makeProfile(),
            $this->makeStudent()
        );

        $this->assertCount(1, $changeset);
        $this->assertEquals('mother_name', $changeset[0]['field_key']);
        $this->assertEquals('Rani',        $changeset[0]['current_value']);
        $this->assertEquals('Rani Kumar',  $changeset[0]['proposed_value']);
        $this->assertFalse($changeset[0]['is_file']);
    }

    public function testLockedKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RtcFieldHelper::buildChangeset(
            ['mobile' => '9999999999'],
            $this->makeProfile(),
            $this->makeStudent()
        );
    }

    public function testUnknownKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RtcFieldHelper::buildChangeset(
            ['nonexistent_field' => 'value'],
            $this->makeProfile(),
            $this->makeStudent()
        );
    }

    public function testEmptyPostThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RtcFieldHelper::buildChangeset([], $this->makeProfile(), $this->makeStudent());
    }

    public function testNoOpChangeThrows(): void
    {
        // Same proposed value as current → empty changeset → InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        RtcFieldHelper::buildChangeset(
            ['mother_name' => 'Rani'], // identical to current
            $this->makeProfile(),
            $this->makeStudent()
        );
    }
}
