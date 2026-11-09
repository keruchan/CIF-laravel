<?php

declare(strict_types=1);

namespace App\Services\Recruitment;

use Illuminate\Http\Exceptions\HttpResponseException;

final class MrfDocument
{
    public const MRF_UPLOAD_LIMIT = 2097152;

    public const MRF_TEXT_LIMIT = 262144;

    public static function mrfPdfReader(): string
    {
        $configured = config('recruitment.pdftotext');
        $candidates = $configured ? [$configured] : [dirname(PHP_BINARY).'/pdftotext.exe', (getenv('LOCALAPPDATA') ?: '').'/Programs/Git/ucrt64/bin/pdftotext.exe', 'C:/Program Files/Git/ucrt64/bin/pdftotext.exe', '/usr/bin/pdftotext'];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        throw new \InvalidArgumentException('PDF recognition is not configured on this server. Configure CIF_PDFTOTEXT, or upload a Word .docx file.');
    }

    public static function mrfPdfText(string $path): string
    {
        $process = proc_open([MrfDocument::mrfPdfReader(), '-raw', '-enc', 'UTF-8', '-nopgbrk', '-f', '1', '-l', '20', $path, '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (! is_resource($process)) {
            throw new \InvalidArgumentException('Unable to start PDF recognition. Contact your administrator.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $text = '';
        $errors = '';
        $started = microtime(true);
        $exit = -1;
        try {
            do {
                $text .= stream_get_contents($pipes[1]);
                $errors .= stream_get_contents($pipes[2]);
                if (strlen($text) > MrfDocument::MRF_TEXT_LIMIT || strlen($errors) > 32768) {
                    throw new \InvalidArgumentException('The PDF has too much content. Upload only the MRF pages.');
                }
                if (microtime(true) - $started > 20) {
                    throw new \InvalidArgumentException('PDF recognition took too long. Try a smaller PDF or a Word .docx file.');
                }
                $status = proc_get_status($process);
                if (! $status['running']) {
                    $exit = $status['exitcode'];
                    break;
                }
                usleep(20000);
            } while (true);
            $text .= stream_get_contents($pipes[1]);
            if ($exit !== 0) {
                throw new \InvalidArgumentException('Unable to read this PDF. Check that it is valid and not password-protected.');
            }
        } finally {
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process);
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        if (strlen($text) > MrfDocument::MRF_TEXT_LIMIT) {
            throw new \InvalidArgumentException('The PDF has too much content. Upload only the MRF pages.');
        }
        if (mb_strlen(trim($text)) < 15) {
            throw new \InvalidArgumentException('This PDF has no readable text. Scanned/image-only PDFs need OCR first. Upload a searchable PDF or Word .docx file.');
        }

        return $text;
    }

    public static function mrfDocxText(string $path): string
    {
        try {
            // Read only known XML members; never extract an archive or execute macros.
            $archive = new \PharData($path);
            if (! isset($archive['word/document.xml'], $archive['[Content_Types].xml']) || isset($archive['word/vbaProject.bin'])) {
                throw new \InvalidArgumentException('Upload a regular Word .docx document, not a macro-enabled or renamed file.');
            }
            if ($archive['word/document.xml']->getSize() > MrfDocument::MRF_TEXT_LIMIT * 4) {
                throw new \InvalidArgumentException('The Word document has too much content. Upload only the MRF.');
            }
            $xml = $archive['word/document.xml']->getContent();
        } catch (\InvalidArgumentException $error) {
            throw $error;
        } catch (\Throwable $error) {
            if ($error instanceof HttpResponseException) {
                throw $error;
            }
            throw new \InvalidArgumentException('Unable to read this Word file. Open it in Word and save it as .docx, then try again.');
        }
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new \InvalidArgumentException('This document contains unsupported XML declarations. Save a new .docx copy in Word.');
        }
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded) {
            throw new \InvalidArgumentException('The Word document XML is invalid. Save a new .docx copy in Word.');
        }
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $text = '';
        // Paragraphs outside tables and each table cell are kept in reading order.
        foreach ($xpath->query('//w:body//w:p') as $paragraph) {
            foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr | .//w:checkBox/w:checked', $paragraph) as $node) {
                if ($node->localName === 't') {
                    $text .= $node->textContent;
                } elseif ($node->localName === 'checked') {
                    $text .= $node->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val') === '0' ? ' ☐ ' : ' ☒ ';
                } else {
                    $text .= $node->localName === 'tab' ? "\t" : "\n";
                }
            }
            $next = $paragraph->nextSibling;
            while ($next && $next->nodeType !== XML_ELEMENT_NODE) {
                $next = $next->nextSibling;
            }
            $parent = $paragraph->parentNode;
            if ($parent && $parent->localName === 'body') {
                $text .= "\n";
            } elseif ($parent && $parent->localName === 'tc') {
                $nextCell = $parent->nextSibling;
                while ($nextCell && $nextCell->nodeType !== XML_ELEMENT_NODE) {
                    $nextCell = $nextCell->nextSibling;
                }
                $text .= $next && $next->localName === 'p' ? "\n" : ($nextCell ? "\t" : "\n");
            } else {
                $text .= "\n";
            }
        }
        if (strlen($text) > MrfDocument::MRF_TEXT_LIMIT) {
            throw new \InvalidArgumentException('The Word document has too much content. Upload only the MRF.');
        }
        if (mb_strlen(trim($text)) < 15) {
            throw new \InvalidArgumentException('No readable text was found in the Word document. Image-only forms need OCR first.');
        }

        return $text;
    }

    public static function mrfDocumentAliases(): array
    {
        return ['requestor_name' => ['requestor\s*[’\x{0027}]?s?\s+name', 'requester\s*[’\x{0027}]?s?\s+name', 'requested by'], 'requestor_position' => ['requestor\s*[’\x{0027}]?s?\s+position', 'requester\s*[’\x{0027}]?s?\s+position'], 'position_title' => ['position title', 'applied position', 'position applied', 'position requested', 'requested position', 'request(?!\s+details|\s+justification|\s+reason)'], 'construction_phase' => ['construction phase', 'project phase'], 'site_location' => ['site location', 'location'], 'headcount' => ['headcount needed', 'headcount(?!\s+references)', 'no\.? of requests?', 'number of requests?', 'number of personnel'], 'reason' => ['reason for request', 'reason of request', 'request reason'], 'replacement_of' => ['replacement of', 'replacement for'], 'reason_details' => ['request justification', 'justification', 'details'], 'employment_status' => ['employment status', 'employment type'], 'department_name' => ['department\s*[-–—/]\s*group', 'department name', 'department'], 'group_name' => ['group name', 'group'], 'education_level' => ['education level', 'educational attainment'], 'preferred_course' => ['preferred course', 'course'], 'years_experience' => ['required years of experience', 'yrs\.? of experience', 'years of experience'], 'other_qualifications' => ['other qualifications', 'qualifications'], 'job_roles' => ['job roles', 'job responsibilities', 'duties and responsibilities'], 'remarks' => ['remarks'], 'computer' => ['computer\s*[/&]\s*laptop', 'computer set\s*[/&]\s*laptop', 'computer', 'laptop'], 'table_chair' => ['table\s*[&/]\s*chair', 'table and chair'], 'company_sim' => ['company sim'], 'staff_house' => ['staff house\s*[/]\s*barracks', 'staff house', 'barracks'], 'vehicle' => ['vehicle\s*\(for drivers\s*[/]\s*heo\)', 'vehicle unit(?! number)', 'vehicle(?! unit number)'], 'vehicle_unit' => ['vehicle unit number', 'vehicle unit no\.?'], 'buddy_1_name' => ['ka[-\s]*(?:apec\s*)?buddy\s*1\s*name', 'ka[-\s]*(?:apec\s*)?buddy\s*#?1(?!\s*contact)'], 'buddy_1_contact' => ['ka[-\s]*(?:apec\s*)?buddy\s*1\s*contact(?:\s+number)?', 'buddy\s*1\s*contact(?:\s+number)?'], 'buddy_2_name' => ['ka[-\s]*(?:apec\s*)?buddy\s*2\s*name', 'ka[-\s]*(?:apec\s*)?buddy\s*#?2(?!\s*contact)'], 'buddy_2_contact' => ['ka[-\s]*(?:apec\s*)?buddy\s*2\s*contact(?:\s+number)?', 'buddy\s*2\s*contact(?:\s+number)?'], '_contact' => ['contact number'], 'reviewed_reporting' => ['reviewed by\s*[-–—:]?\s*\(?reporting officer\s*[/]\s*spe\)?'], 'reviewed_psd' => ['reviewed by\s*[-–—:]?\s*\(?psd group\)?'], 'reviewed_housing' => ['reviewed by\s*[-–—:]?\s*\(?housing support group\)?'], 'reviewed_group' => ['reviewed by\s*[-–—:]?\s*\(?group head(?:\s*(?:[-–—]\s*if applicable|\(if applicable\)))?\)?'], 'approved_department' => ['approved by\s*[-–—:]?\s*\(?department head\)?'], '_ignore' => ['manpower request form', 'request details', 'onboarding details', 'signatories', 'date requested', 'date request', 'mrf\s*(?:no\.?|number)', 'prepared by', 'headcount references', 'acc\s*/\s*amc\s*/\s*mcc', 'sbr ver[^\r\n]*']];
    }

    public static function recognizeMrfText(string $text): array
    {
        // Document text is untrusted data. It is never treated as instructions/code.
        $text = str_replace(["\r\n", "\r", "\f", ' '], ["\n", "\n", "\n", ' '], $text);
        $text = preg_replace('/<<[^<>]*>>|\{\{[^{}]*\}\}/u', '', $text);
        // PDF stream order preserves table rows but may collapse the separator
        // before the second label to one space. Restore only known two-column rows.
        $text = preg_replace_callback('/^[ \t]*(?:REQUESTOR[’\x{0027}]?S?\s+(?:NAME|POSITION)|SITE LOCATION|QUALIFICATIONS)[^\r\n]*$/imu', static fn ($match) => preg_replace('/[ \t]+(?=DATE REQUEST|MRF NO\.?|NO\.? OF REQUEST|EDUCATION LEVEL)/iu', "\t", $match[0]), $text);
        $aliases = MrfDocument::mrfDocumentAliases();
        $patterns = [];
        foreach ($aliases as $key => $values) {
            $patterns[] = '(?<f'.count($patterns).'>'.implode('|', $values).')';
        }
        // A label must begin a line/cell, or follow a colon (nested qualifications).
        $pattern = '~(?:^|(?<=\n)|(?<=\t)|(?<= {2})|(?<=:))[ \t]*(?:'.implode('|', $patterns).')(?=\s|:|$)[ \t]*[:：]?[ \t]*~imu';
        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $keys = array_keys($aliases);
        $raw = [];
        $warnings = [];
        $lastBuddy = 0;
        foreach ($matches as $index => $match) {
            $key = null;
            foreach ($keys as $number => $name) {
                if (($match['f'.$number][1] ?? -1) >= 0) {
                    $key = $name;
                    break;
                }
            }
            $start = $match[0][1] + strlen($match[0][0]);
            $end = $matches[$index + 1][0][1] ?? strlen($text);
            $value = trim(substr($text, $start, $end - $start), " \t\n:：_-|");
            if ($key === '_ignore' || $key === null) {
                continue;
            }
            if ($key === 'buddy_1_name') {
                $lastBuddy = 1;
            } elseif ($key === 'buddy_2_name') {
                $lastBuddy = 2;
            }
            if ($key === '_contact') {
                if (! $lastBuddy) {
                    continue;
                }
                $key = 'buddy_'.$lastBuddy.'_contact';
            }
            // Generic contact labels occur after each buddy name in the source form.
            if (in_array($key, ['buddy_1_name', 'buddy_2_name'], true) && preg_match('/^(.*?)\s+CONTACT NUMBER\s*[:：]?\s*(.*?)$/isu', $value, $phone)) {
                $value = trim($phone[1]);
                $raw['buddy_'.$lastBuddy.'_contact'] = trim($phone[2]);
            }
            if (isset($raw[$key]) && $raw[$key] !== '' && $value !== '' && $raw[$key] !== $value) {
                $warnings[] = 'Multiple values found for '.$key.'; review the recognized value.';

                continue;
            }
            $raw[$key] = $value;
        }
        // In the printed source, REQUEST and DEPARTMENT - GROUP combine two fields.
        foreach (['position_title' => 'construction_phase', 'department_name' => 'group_name'] as $key => $other) {
            if (empty($raw[$other]) && preg_match('/^(.+?)\s+[-–—]\s+(.+)$/u', $raw[$key] ?? '', $parts)) {
                $raw[$key] = trim($parts[1]);
                $raw[$other] = trim($parts[2]);
            }
        }
        if (empty($raw['reason']) && ! empty($raw['reason_details'])) {
            foreach (['Additional manpower', 'Replacement', 'New position', 'Other'] as $reason) {
                if (preg_match('/^'.preg_quote($reason, '/').'\b\s*[-–—:]?\s*/iu', $raw['reason_details'], $match)) {
                    $raw['reason'] = $reason;
                    $raw['reason_details'] = trim(substr($raw['reason_details'], strlen($match[0])));
                    break;
                }
            }
        }
        $form = [];
        $recognized = [];
        $missing = [];
        foreach (Mrf::mrfFields() as $fields) {
            foreach ($fields as $key => $field) {
                $value = trim($raw[$key] ?? '');
                if ((str_starts_with($key, 'reviewed_') || $key === 'approved_department') && preg_match('/reviewed by|approved by|prepared by|^\(?(?:reporting officer|housing support group|group head|department head|psd group)|acc\s*\/\s*amc\s*\/\s*mcc/iu', $value)) {
                    $value = '';
                    $warnings[] = 'Review '.$field['label'].': the signature area could not be reliably recognized.';
                }
                if (preg_match('/^(?:[_\.\-\s]+|n\/a|not provided|select)$/iu', $value)) {
                    $value = '';
                }
                if ($field['type'] === 'select') {
                    $original = $value;
                    $value = '';
                    // Multiple checkbox options need one explicit checked value.
                    $checked = preg_split('/(?=[☒☑✓✔]|\[\s*[xX]\s*\])/u', $original);
                    if (count($checked) > 1) {
                        $marked = array_values(array_filter($checked, static fn ($part) => preg_match('/^(?:[☒☑✓✔]|\[\s*[xX]\s*\])/u', trim($part))));
                        $original = count($marked) === 1 ? preg_replace('/^(?:[☒☑✓✔]|\[\s*[xX]\s*\])\s*/u', '', trim($marked[0])) : '';
                        $original = preg_split('/☐|\[\s*\]/u', $original)[0];
                    }
                    $normal = mb_strtolower(trim($original));
                    $synonyms = ['employment_status' => ['project based' => 'Project-based', 'fixed term' => 'Fixed-term'], 'education_level' => ['college graduate' => 'Bachelor’s degree', 'bachelor degree' => 'Bachelor’s degree', 'bachelors degree' => 'Bachelor’s degree', "bachelor's degree" => 'Bachelor’s degree', 'vocational' => 'Vocational / Technical'], 'computer' => ['yes' => 'Either desktop or laptop', 'none' => 'Not required', 'no' => 'Not required', 'desktop' => 'Desktop computer']];
                    if (in_array($key, ['table_chair', 'company_sim', 'staff_house', 'vehicle'], true)) {
                        $synonyms[$key] = ['yes' => 'Required', 'no' => 'Not required', 'none' => 'Not required'];
                    }
                    foreach ($field['options'] as $option) {
                        if (mb_strtolower($option) === $normal) {
                            $value = $option;
                            break;
                        }
                    }
                    $value = $value ?: $synonyms[$key][$normal] ?? '';
                    if ($value === '' && trim($raw[$key] ?? '') !== '') {
                        $warnings[] = 'Review '.$field['label'].': its answer could not be matched to a supported option.';
                    }
                } elseif ($field['type'] === 'number') {
                    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(?:years?|yrs?|people|persons?|headcounts?)?$/iu', $value, $number) && (float) $number[1] >= $field['min'] && (float) $number[1] <= $field['max'] && ($key !== 'headcount' || ctype_digit($number[1])) && ($key !== 'years_experience' || fmod((float) $number[1] * 2, 1) === 0.0)) {
                        $value = $number[1];
                    } elseif ($value !== '') {
                        $warnings[] = 'Review '.$field['label'].': a valid number could not be recognized.';
                        $value = '';
                    }
                } elseif ($field['type'] === 'tel' && $value !== '') {
                    try {
                        $value = CandidateStorage::cifContactNumber($value, $field['label']);
                    } catch (\InvalidArgumentException $error) {
                        $value = '';
                        $warnings[] = 'Review '.$field['label'].': a valid contact number could not be recognized.';
                    }
                }
                if ($field['type'] !== 'number' && mb_strlen($value) > $field['max']) {
                    $value = '';
                    $warnings[] = 'Review '.$field['label'].': extracted content was too long.';
                }
                $form[$key] = $value;
                if ($value !== '') {
                    $recognized[] = $key;
                } elseif ($field['required']) {
                    $missing[] = $field['label'];
                }
            }
        }
        if ($form['reason'] === 'Replacement' && $form['replacement_of'] === '') {
            $missing[] = 'Replacement of';
        }

        return ['form' => $form, 'recognized' => $recognized, 'missing' => $missing, 'warnings' => array_values(array_unique($warnings))];
    }

    public static function readMrfUpload(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Choose a Word .docx or PDF file up to 2 MB. Check the server upload limit if the upload fails.');
        }
        if (! is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('The uploaded file could not be verified. Choose the document again.');
        }
        if ($file['size'] < 1) {
            throw new \InvalidArgumentException('The uploaded file is empty. Choose a completed document.');
        }
        if ($file['size'] > MrfDocument::MRF_UPLOAD_LIMIT) {
            throw new \InvalidArgumentException('Upload a file no larger than 2 MB.');
        }
        $filename = basename(str_replace('\\', '/', (string) $file['name']));
        if (mb_strlen($filename) > 255 || preg_match('/[\x00-\x1f]/', $filename)) {
            throw new \InvalidArgumentException('Use a shorter filename without control characters.');
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $path = $file['tmp_name'];
        $bytes = file_get_contents($path);
        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($extension === 'pdf' && str_starts_with($bytes, '%PDF-') && $actualMime === 'application/pdf') {
            $text = MrfDocument::mrfPdfText($path);
            $mime = 'application/pdf';
        } elseif ($extension === 'docx' && str_starts_with($bytes, "PK\x03\x04") && in_array($actualMime, ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'], true)) {
            // PharData needs a ZIP suffix for PHP's HTTP temporary upload names.
            $copy = tempnam(sys_get_temp_dir(), 'cif-mrf-').'.zip';
            $base = substr($copy, 0, -4);
            try {
                if (! copy($path, $copy)) {
                    throw new \RuntimeException('Unable to read temporary Word upload.');
                }
                $text = MrfDocument::mrfDocxText($copy);
            } finally {
                if (is_file($copy)) {
                    unlink($copy);
                }
                if (is_file($base)) {
                    unlink($base);
                }
            }
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        } else {
            throw new \InvalidArgumentException('Upload a valid PDF or Word .docx file. Older .doc files must be saved as .docx in Word first.');
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            throw new \InvalidArgumentException('The document text could not be decoded. Save a new PDF or .docx copy.');
        }

        return ['filename' => $filename, 'mime' => $mime, 'bytes' => $bytes, 'sha256' => hash('sha256', $bytes), 'text' => $text];
    }

    public static function existingMrfDocumentRequest(\PDO $pdo, string $text): ?array
    {
        if (! preg_match('/\bMRF\s*(?:NO\.?|NUMBER)\s*[:：]?\s*(\d{4}-\d+(?:-\d+)?)\b/iu', $text, $match)) {
            return null;
        }
        $query = $pdo->prepare('SELECT id,mrf_number,form_data FROM manpower_requests WHERE mrf_number=? OR batch_number=? OR legacy_mrf_number=? ORDER BY headcount_index,id LIMIT 1');
        $query->execute([$match[1], $match[1], $match[1]]);
        $record = $query->fetch();
        if (! $record) {
            return null;
        }
        $recognized = Mrf::formatSavedMrf(MrfDocument::recognizeMrfText($text)['form']);
        $saved = json_decode($record['form_data'], true, 32, JSON_THROW_ON_ERROR);
        $same = true;
        foreach (Mrf::mrfFields() as $fields) {
            foreach (array_keys($fields) as $key) {
                if (trim((string) ($recognized[$key] ?? '')) !== trim((string) ($saved[$key] ?? ''))) {
                    $same = false;
                }
            }
        }

        return ['id' => (int) $record['id'], 'mrf_number' => $record['mrf_number'], 'source_number' => $match[1], 'same_request' => $same];
    }
}
