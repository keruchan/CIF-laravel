<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class CifFields
{
    // Candidate-facing fields from REC-REV-24-03 SBR; HR-only assessments are excluded.
    public static function cifDefinition(): array
    {
        $field = static fn (string $label, bool $required = false, string $type = 'text', array $options = [], int $max = 500): array => compact('label', 'required', 'type', 'options', 'max');

        return [
            'sections' => [
                'Personal Information' => [
                    'desired_position' => $field('Applied position', true, 'text', [], 150),
                    'expected_salary' => $field('Expected monthly salary (PHP)', false, 'money'),
                    'applied_via' => $field('How did you know about us?', true, 'select', ['BESO (Barangay Hall)', 'BestJobs', 'Employee Referral', 'Facebook', 'FastJobs', 'Gate Posting (Site)', 'Indeed', 'Jobstreet', 'Jora', 'LinkedIn', 'PESO (City/Municipal Hall)', 'Rehire', 'TESDA', 'University/School Career Fair', 'Walk-In']),
                    'fn' => $field('First name', true, 'text', [], 100),
                    'mn' => $field('Middle name', false, 'text', [], 100),
                    'ln' => $field('Last name', true, 'text', [], 100),
                    'sx' => $field('Suffix', false, 'text', [], 30),
                    'nickname' => $field('Nickname', false, 'text', [], 100),
                    'place_of_birth' => $field('Place of birth', true),
                    'dob' => $field('Date of birth', true, 'date'),
                    'height_cm' => $field('Height (cm)', false, 'measurement'),
                    'weight_kg' => $field('Weight (kg)', false, 'measurement'),
                    'religion' => $field('Religion', true, 'text', [], 150),
                    'cs' => $field('Civil status', true, 'select', ['Single', 'Married', 'Widowed', 'Separated']),
                    'sex' => $field('Sex', true, 'select', ['Female', 'Male']),
                    'nat' => $field('Nationality', true, 'text', [], 100),
                    'mob' => $field('Contact number', true, 'tel', [], 50),
                    'contact_email' => $field('Email', true, 'email', [], 254),
                    'facebook_account' => $field('Facebook account link / name', false, 'text', [], 300),
                    'sss_number' => $field('SSS number', false, 'text', [], 50),
                    'philhealth_number' => $field('PhilHealth number', false, 'text', [], 50),
                    'pagibig_number' => $field('Pag-IBIG number', false, 'text', [], 50),
                    'tin_number' => $field('TIN', false, 'text', [], 50),
                    'blood_type' => $field('Blood type (optional)', false, 'select', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown']),
                    'id_photo' => $field('ID picture — upper body (optional)', false, 'photo', [], 2800000),
                ],
                'Residential Information' => [
                    'adr' => $field('Present address', true, 'textarea'),
                    'permanent_address' => $field('Permanent / provincial address', true, 'textarea'),
                    'city' => $field('City / municipality', true),
                    'prov' => $field('Province', true),
                    'zip' => $field('ZIP code', false, 'text', [], 20),
                    'ecn' => $field('Emergency contact person', true, 'text', [], 150),
                    'emergency_address' => $field('Emergency contact address', true, 'textarea'),
                    'ecp' => $field('Emergency contact number', true, 'tel', [], 50),
                    'ecr' => $field('Emergency contact relationship', true, 'text', [], 150),
                ],
                'Education & licensure' => [
                    'edu' => $field('Highest educational attainment', true, 'select', ['Elementary', 'High school', 'Senior high school', 'Vocational', 'College undergraduate', "Bachelor's degree", "Master's degree", 'Doctorate', 'Other']),
                    'sch' => $field('School / university', true),
                    'crs' => $field('Course taken', true),
                    'education_years' => $field('Inclusive years (from–to)', true, 'year_range'),
                    'yr' => $field('Year graduated (optional)', false, 'year'),
                    'distinctions' => $field('Distinctions, if any', false, 'textarea'),
                    'licensure_exam' => $field('Licensure examination'),
                    'licensure_month' => $field('Month and year taken', false, 'month'),
                    'board_rating' => $field('Board examination rating (%)', false, 'rating'),
                ],
                'Awards & affiliations' => [],
                'Employment record' => [
                    'emp' => $field('Current employment', true, 'select', ['Employed', 'Unemployed', 'Self-employed', 'Student']),
                ],
                'Certification & consent' => [
                    'certification_name' => $field('Typed full name — certification of information', true, 'text', [], 330),
                    'background_check_name' => $field('Typed full name — background check consent', true, 'text', [], 330),
                ],
            ],
            'groups' => [
                'awards' => ['section' => 'Awards & affiliations', 'label' => 'Awards & achievements', 'max' => 2, 'anchor' => 'award', 'fields' => [
                    'award' => $field('Award'), 'year_given' => $field('Year given', false, 'year'), 'given_by' => $field('Given by'),
                ]],
                'activities' => ['section' => 'Awards & affiliations', 'label' => 'Affiliations / extra-curricular activities', 'max' => 2, 'anchor' => 'organization', 'fields' => [
                    'organization' => $field('Name of organization'), 'position_held' => $field('Position held'), 'inclusive_years' => $field('Inclusive years'),
                ]],
                'employment' => ['section' => 'Employment record', 'label' => 'Employment records (most recent first)', 'max' => 3, 'anchor' => 'company_name', 'fields' => [
                    'company_name' => $field('Company name'), 'company_address' => $field('Company address'), 'position_title' => $field('Position title'),
                    'employment_period' => $field('Employment period (from–to)'), 'previous_salary' => $field('Previous monthly salary (PHP)', false, 'money'),
                    'reason_for_separation' => $field('Reason for separation'), 'immediate_superior' => $field('Immediate superior'), 'superior_contact' => $field('Superior contact number', false, 'tel', [], 50),
                ]],
                'references' => ['section' => 'Employment record', 'label' => 'Character references', 'max' => 2, 'anchor' => 'name', 'fields' => [
                    'name' => $field('Name'), 'company_position' => $field('Company & position'), 'contact_number' => $field('Contact number', false, 'tel', [], 50), 'relationship' => $field('Relationship'),
                ]],
                'connections' => ['section' => 'Employment record', 'label' => 'APEC connections', 'max' => 2, 'anchor' => 'name', 'fields' => [
                    'name' => $field('Employee name'), 'relationship' => $field('Relationship'),
                ]],
            ],
        ];
    }

    public static function cifScalarFields(): array
    {
        return array_merge(...array_values(CifFields::cifDefinition()['sections']));
    }
}
