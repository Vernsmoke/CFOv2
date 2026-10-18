<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AcademicCatalog;
use Database\Seeders\AcademicCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $academic = []): array
    {
        return [...$this->studentAcademicSelection($academic),
            'student_id' => '24-10723',
            'first_name' => 'Catalog',
            'last_name' => 'Student',
            'email' => 'catalog.student@gmail.com',
            'password' => 'CfoTest!234',
            'password_confirmation' => 'CfoTest!234',
        ];
    }

    public function test_catalog_contains_the_supplied_departments_courses_and_majors(): void
    {
        $catalog = AcademicCatalog::frontend();

        $this->assertCount(10, $catalog['departments']);
        $business = collect($catalog['departments'])->firstWhere('name', 'College of Business Education');
        $businessAdministration = collect($business['courses'])->firstWhere('name', 'Bachelor of Science in Business Administration');
        $this->assertSame(['Human Resources Management', 'Financial Management', 'Marketing Management'], $businessAdministration['majors']);

        $education = collect($catalog['departments'])->firstWhere('name', 'College of Education');
        $secondaryEducation = collect($education['courses'])->firstWhere('name', 'Bachelor of Secondary Education');
        $this->assertSame(['English', 'Filipino', 'Mathematics', 'Sciences', 'Social Studies'], $secondaryEducation['majors']);
    }

    public function test_catalog_seeder_adds_courses_year_levels_and_sections_without_duplicates(): void
    {
        $this->seed(AcademicCatalogSeeder::class);
        $this->seed(AcademicCatalogSeeder::class);

        $this->assertDatabaseCount('academic_options', count(AcademicCatalog::courseNames()) + 4 + 26);
        $this->assertDatabaseHas('academic_options', ['type' => 'course', 'name' => 'Bachelor of Science in Information Technology']);
    }

    public function test_public_registration_stores_a_valid_department_course_and_major_path(): void
    {
        $academic = [
            'department' => 'college of business education',
            'course' => 'bachelor of science in business administration',
            'major' => 'marketing management',
        ];

        $this->withVerifiedRegistrationEmail('catalog.student@gmail.com')
            ->postJson('/api/register', $this->registration($academic))
            ->assertCreated();

        $student = User::where('email', 'catalog.student@gmail.com')->firstOrFail();
        $this->assertSame('College of Business Education', $student->department);
        $this->assertSame('Bachelor of Science in Business Administration', $student->course);
        $this->assertSame('Marketing Management', $student->major);
    }

    public function test_public_registration_rejects_a_course_from_another_department(): void
    {
        $this->withVerifiedRegistrationEmail('catalog.student@gmail.com')
            ->postJson('/api/register', $this->registration([
                'department' => 'College of Accountancy',
                'course' => 'Bachelor of Science in Information Technology',
            ]))
            ->assertUnprocessable()->assertJsonValidationErrors('course');
    }

    public function test_public_registration_requires_a_major_only_when_the_course_has_majors(): void
    {
        $this->withVerifiedRegistrationEmail('catalog.student@gmail.com')
            ->postJson('/api/register', $this->registration([
                'department' => 'College of Education',
                'course' => 'Bachelor of Secondary Education',
                'major' => null,
            ]))
            ->assertUnprocessable()->assertJsonValidationErrors('major');
    }
}
