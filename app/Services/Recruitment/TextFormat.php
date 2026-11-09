<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

final class TextFormat
{
    // Formatting belongs at validated save boundaries, never in SQL/display-only CSS.
    // Do not apply this to credentials, catalogues, uploaded originals or audit history.
    public static function cifProperText(string $value): string
    {
        static $canonical = ['apec' => 'APEC', 'cif' => 'CIF', 'mrf' => 'MRF', 'hr' => 'HR', 'sss' => 'SSS', 'tin' => 'TIN', 'philhealth' => 'PhilHealth', 'pag-ibig' => 'Pag-IBIG', 'pagibig' => 'Pag-IBIG', 'tesda' => 'TESDA', 'beso' => 'BESO', 'peso' => 'PESO', 'prc' => 'PRC', 'nbi' => 'NBI', 'psa' => 'PSA', 'bir' => 'BIR', 'dole' => 'DOLE', 'doh' => 'DOH', 'ldt' => 'LDT', 'awol' => 'AWOL', 'heo' => 'HEO', 'psd' => 'PSD', 'md5' => 'MD5', 'spe' => 'SPE', 'acc' => 'ACC', 'amc' => 'AMC', 'mcc' => 'MCC', 'bpo' => 'BPO', 'ict' => 'ICT', 'bs' => 'BS', 'ba' => 'BA', 'bscs' => 'BSCS', 'bsit' => 'BSIT', 'bsee' => 'BSEE', 'bsce' => 'BSCE', 'mba' => 'MBA', 'phd' => 'PhD', 'cpa' => 'CPA', 'cpr' => 'CPR', 'cpd' => 'CPD', 'rn' => 'RN', 'ree' => 'REE', 'rme' => 'RME', 'sql' => 'SQL', 'php' => 'PHP', 'api' => 'API', 'html' => 'HTML', 'css' => 'CSS', 'js' => 'JS', 'ui' => 'UI', 'ux' => 'UX', 'kpi' => 'KPI', 'pdf' => 'PDF', 'docx' => 'DOCX', 'xlsx' => 'XLSX', 'url' => 'URL', 'gps' => 'GPS', 'cctv' => 'CCTV', 'ncr' => 'NCR', 'barmm' => 'BARMM', 'hiv' => 'HIV', 'aids' => 'AIDS', 'tb' => 'TB', 'ecg' => 'ECG', 'inc' => 'INC', 'lds' => 'LDS', 'sda' => 'SDA', 'sim' => 'SIM', 'ii' => 'II', 'iii' => 'III', 'iv' => 'IV', 'vi' => 'VI', 'linkedin' => 'LinkedIn', 'bestjobs' => 'BestJobs', 'fastjobs' => 'FastJobs', 'javascript' => 'JavaScript', 'typescript' => 'TypeScript', 'mysql' => 'MySQL', 'postgresql' => 'PostgreSQL', 'github' => 'GitHub'];
        // Preserve embedded links, email/handles, code spans, reference numbers and
        // mixed alphanumeric identifiers byte-for-byte. Keep whitespace/newlines.
        $pattern = <<<'REGEX'
        ~https?://[^\s<>]+|www\.[^\s<>]+|[a-z]:[\\/][^\s<>]+|[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.[\p{L}]{2,}|[@\#][\p{L}\p{N}._\-]+|[\p{L}\p{N}.\-]+\.(?:com|org|net|ph|edu|gov|io|co)(?:[/?\#][^\s<>]*)?|`[^`\r\n]+`|[\p{L}\p{N}._/\-]*\p{N}[\p{L}\p{N}._/\-]*|pag-ibig|n/a|[\p{L}\p{M}]+(?:['’][\p{L}\p{M}]+)*~iu
        REGEX;

        return preg_replace_callback($pattern, static function (array $match) use ($canonical): string {
            $word = $match[0];
            $lower = mb_strtolower($word, 'UTF-8');
            if (isset($canonical[$lower])) {
                return $canonical[$lower];
            }
            if ($lower === 'n/a') {
                return 'N/A';
            }
            // Ambiguous words are acronyms only when explicitly entered as such.
            if (in_array($word, ['IT', 'US', 'AM', 'PM', 'RN', 'RPN', 'CPA', 'CPR', 'OS', 'PC'], true)) {
                return $word;
            }
            if (! preg_match('/^[\p{L}\p{M}]+(?:[\x{0027}’][\p{L}\p{M}]+)*$/u', $word)) {
                return $word;
            }

            return mb_strtoupper(mb_substr($lower, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($lower, 1, null, 'UTF-8');
        }, $value);
    }

    public static function cifSavedText(string $value, string $key, string $type = 'text'): string
    {
        if (! in_array($type, ['text', 'textarea'], true)) {
            return $value;
        }
        if (in_array($key, ['desired_position', 'position_title', 'employee_id', 'sss_number', 'philhealth_number', 'pagibig_number', 'tin_number', 'drivers_license_code', 'vehicle_unit', 'house_number', 'zip'], true)) {
            return $value;
        }
        if ($key === 'facebook_account') {
            // A single handle or URL is an identifier; a written full name is a name.
            if (preg_match('~(?:https?://|www\.|facebook\.com|fb\.com|@)~iu', $value) || ! preg_match('/\s/u', trim($value))) {
                return $value;
            }

            return mb_strtoupper($value, 'UTF-8');
        }
        if (in_array($key, ['fn', 'mn', 'ln', 'sx', 'ecn', 'name', 'certification_name', 'background_check_name', 'recertification_name', 'immediate_superior', 'requestor_name', 'buddy_1_name', 'buddy_2_name', 'reviewed_reporting', 'reviewed_psd', 'reviewed_housing', 'reviewed_group', 'approved_department'], true)) {
            return mb_strtoupper($value, 'UTF-8');
        }
        if ($key === 'department_name' && mb_strtolower(trim($value)) === 'it') {
            return 'IT';
        }

        return TextFormat::cifProperText($value);
    }
}
