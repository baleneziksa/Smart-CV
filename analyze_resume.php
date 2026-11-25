<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

ini_set('display_errors', '0');
set_time_limit(900);

function respond(int $status, array $data): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function fail(int $status, string $message): never
{
    respond($status, [
        'success' => false,
        'message' => $message
    ]);
}

function smartLog(string $message): void
{
    error_log('[SmartCV] ' . $message);
}

function cleanText(string $text): string
{
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

    $text = preg_replace(
        '/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u',
        '',
        $text
    ) ?? $text;

    $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

    return trim($text);
}

function cleanValue(mixed $value): string
{
    if (!is_string($value) && !is_numeric($value)) {
        return '';
    }

    $value = cleanText((string)$value);

    $missing = [
        'null', 'none', 'unknown', 'n/a',
        'not provided', 'not mentioned',
        'not specified', 'not available',
        'غير مذكور', 'غير محدد', 'لا يوجد',
        'غير متوفر', '未提及'
    ];

    if (in_array(
        mb_strtolower($value, 'UTF-8'),
        $missing,
        true
    )) {
        return '';
    }

    return $value;
}

function normalizeForMatch(string $text): string
{
    $text = mb_strtolower(cleanText($text), 'UTF-8');

    $text = strtr($text, [
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي'
    ]);

    $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text) ?? $text;
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

function meaningfulWords(string $text): array
{
    $text = normalizeForMatch($text);
    $stop = [
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'in', 'of', 'at', 'to', 'a', 'an', 'on', 'as',
        'في', 'من', 'الى', 'على', 'عن', 'مع', 'او', 'و', 'ال'
    ];
    $words = preg_split('/\s+/u', $text) ?: [];
    return array_values(array_unique(array_filter(
        $words,
        static function ($word) use ($stop): bool {
            return mb_strlen($word, 'UTF-8') >= 3 && !in_array($word, $stop, true);
        }
    )));
}

function supportedBySource(string $value, string $source, float $requiredRatio = 0.65): bool
{
    $value = cleanValue($value);
    if ($value === '') return false;

    $normalizedValue = normalizeForMatch($value);
    $normalizedSource = normalizeForMatch($source);
    if ($normalizedValue === '' || $normalizedSource === '') return false;

    if (mb_strpos($normalizedSource, $normalizedValue) !== false) return true;

    $words = meaningfulWords($value);
    if (!$words) return false;

    $matched = 0;
    foreach ($words as $word) {
        if (mb_strpos(' ' . $normalizedSource . ' ', ' ' . $word . ' ') !== false) {
            $matched++;
        }
    }
    return ($matched / count($words)) >= $requiredRatio;
}

function isPlaceholder(string $value): bool
{
    $normalized = normalizeForMatch($value);
    $placeholders = [
        'project title', 'project name', 'description of project', 'project description',
        'example project', 'volunteer role', 'volunteer organization', 'description of the volunteer experience',
        'reference name', 'reference position', 'reference organization', 'reference contact',
        'company name', 'job title', 'university name', 'school name', 'degree name',
        'course name', 'certificate title', 'example company', 'not provided',
        'bachelor s business administration', 'bachelors business administration'
    ];
    foreach ($placeholders as $placeholder) {
        if ($normalized === normalizeForMatch($placeholder)) return true;
    }
    return false;
}

function verifyRecord(string $section, array $record, string $source): bool
{
    $primaryFields = match ($section) {
        'education' => ['degree', 'school'],
        'experience' => ['position', 'company'],
        'skills' => ['name'],
        'languages' => ['name'],
        'certifications' => ['title'],
        'projects' => ['title'],
        'volunteering' => ['role', 'organization'],
        'references' => ['name', 'organization'],
        default => []
    };

    $present = [];
    $supported = 0;

    foreach ($primaryFields as $field) {
        $value = cleanValue($record[$field] ?? '');
        if ($value === '') continue;
        if (isPlaceholder($value)) return false;
        $present[] = $value;
        if (supportedBySource($value, $source)) $supported++;
    }

    if (!$present) return false;
    if ($supported === 0) return false;

    if (in_array($section, ['education', 'experience', 'volunteering'], true)) {
        return $supported >= min(2, count($present));
    }

    return true;
}

function verifyField(string $field, string $value, string $source): string
{
    $value = cleanValue($value);
    if ($value === '' || isPlaceholder($value)) return '';

    if ($field === 'summary') {
        return supportedBySource($value, $source, 0.35) ? $value : '';
    }

    if (in_array($field, ['email', 'phone', 'linkedin'], true)) {
        $compactSource = preg_replace('/\s+/u', '', mb_strtolower($source, 'UTF-8'));
        $compactValue = preg_replace('/\s+/u', '', mb_strtolower($value, 'UTF-8'));
        return str_contains($compactSource, $compactValue) ? $value : '';
    }

    return supportedBySource($value, $source, 0.60) ? $value : '';
}

// ========================================
// التحقق من المستخدم
// ========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'طريقة الطلب غير مسموحة.');
}

session_start();
$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !ctype_digit((string)$userId)) {
    fail(401, 'يجب تسجيل الدخول أولًا.');
}
$userId = (int)$userId;

if (!function_exists('mb_convert_encoding') || !function_exists('curl_init')) {
    fail(500, 'إضافات PHP المطلوبة غير مفعلة.');
}

// [تعديل جديد] جلب اسم موديل الذكاء الاصطناعي من جدول الإعدادات
$aiModel = 'qwen2.5:3b'; // الافتراضي
try {
    require_once __DIR__ . '/db.php';
    $stmtSetting = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmtSetting->execute(['ai_model']);
    $fetchedModel = $stmtSetting->fetchColumn();
    if ($fetchedModel) {
        $aiModel = $fetchedModel;
    }
} catch (Throwable $e) {
    smartLog('Settings Error: ' . $e->getMessage());
}

// ========================================
// تحديد الملف
// ========================================
$filePath = '';
$extension = '';
$fileSize = 0;
$uploadId = $_POST['upload_id'] ?? null;

if ($uploadId !== null) {
    if (!is_string($uploadId) || !preg_match('/^[1-9][0-9]*$/', $uploadId)) {
        fail(400, 'رقم الملف غير صالح.');
    }

    try {
        if (!isset($pdo)) require_once __DIR__ . '/db.php';
        $stmt = $pdo->prepare('SELECT stored_name FROM uploaded_resumes WHERE upload_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([(int)$uploadId, $userId]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) fail(404, 'الملف غير موجود.');

        $storedName = (string)$record['stored_name'];
        if (!preg_match('/^[a-f0-9]{32}\.(pdf|docx|doc)$/i', $storedName)) {
            fail(422, 'اسم الملف غير صالح.');
        }

        $extension = strtolower(pathinfo($storedName, PATHINFO_EXTENSION));

        foreach ([dirname(__DIR__, 3), dirname(__DIR__, 2)] as $parent) {
            $candidate = $parent . DIRECTORY_SEPARATOR . 'smart_cv_private_uploads' . DIRECTORY_SEPARATOR . $userId . DIRECTORY_SEPARATOR . $storedName;
            if (is_file($candidate)) {
                $filePath = $candidate;
                break;
            }
        }

        if ($filePath === '') fail(404, 'الملف غير موجود على القرص.');
        $fileSize = (int)filesize($filePath);

    } catch (PDOException $e) {
        smartLog($e->getMessage());
        fail(500, 'تعذر قراءة بيانات الملف.');
    }
} else {
    $file = $_FILES['resume'] ?? null;
    if (!is_array($file) || ($file['error'] ?? -1) !== UPLOAD_ERR_OK || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        fail(400, 'تعذر استقبال الملف.');
    }
    $filePath = $file['tmp_name'];
    $fileSize = (int)$file['size'];
    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
}

if ($fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
    fail(400, 'حجم الملف يجب ألا يتجاوز 5 ميجابايت.');
}

if (!in_array($extension, ['pdf', 'docx'], true)) {
    fail(415, 'يدعم التحليل PDF وDOCX فقط.');
}

if (!is_readable($filePath)) {
    fail(422, 'تعذر قراءة الملف.');
}

// ========================================
// استخراج النص
// ========================================
$text = '';

try {
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($filePath);

    if ($extension === 'pdf') {
        if (file_get_contents($filePath, false, null, 0, 5) !== '%PDF-' || $mime !== 'application/pdf') {
            throw new RuntimeException('ملف PDF غير صالح.');
        }

        $pdftotext = 'C:\\poppler\\poppler-26.09.0\\Library\\bin\\pdftotext.exe';
        if (!is_file($pdftotext) || !function_exists('exec')) {
            throw new RuntimeException('برنامج Poppler غير متاح.');
        }

        $temp = tempnam(sys_get_temp_dir(), 'smartcv_');
        if ($temp === false) throw new RuntimeException('تعذر إنشاء ملف مؤقت.');

        try {
            $command = escapeshellarg($pdftotext) . ' -layout -enc UTF-8 ' . escapeshellarg($filePath) . ' ' . escapeshellarg($temp) . ' 2>NUL';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            if ($exitCode !== 0) throw new RuntimeException('فشل استخراج PDF.');
            $result = file_get_contents($temp);
            if ($result === false) throw new RuntimeException('تعذر قراءة النص.');
            $text = $result;
        } finally {
            if (is_file($temp)) unlink($temp);
        }
    } else {
        if (!class_exists('ZipArchive') || !in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true)) {
            throw new RuntimeException('ملف DOCX غير صالح.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) throw new RuntimeException('تعذر فتح DOCX.');

        try {
            if ($zip->locateName('word/document.xml') === false) throw new RuntimeException('ملف DOCX غير صالح.');
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) throw new RuntimeException('تعذر قراءة DOCX.');
        } finally {
            $zip->close();
        }

        $xml = preg_replace('/<w:tab\b[^>]*\/>/u', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<w:br\b[^>]*\/>/u', "\n", $xml) ?? $xml;
        $xml = str_replace('</w:p>', "\n", $xml);

        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
} catch (Throwable $e) {
    smartLog('Extraction: ' . $e->getMessage());
    fail(422, 'تعذر استخراج النص من الملف.');
}

$text = cleanText($text);
if (mb_strlen($text, 'UTF-8') < 15) {
    fail(422, 'لم نجد نصًا كافيًا في الملف.');
}

// ========================================
// تقسيم النص حسب الأقسام
// ========================================
function sectionText(string $text, string $group, int $limit = 4200): string {
    if ($group === 'basic') {
        return mb_substr($text, 0, $limit, 'UTF-8');
    }

    $keywords = match ($group) {
        'education' => ['education', 'academic', 'qualification', 'university', 'degree', 'phd', 'master', 'bachelor', 'diploma', 'التعليم', 'المؤهلات', 'الجامعة', 'الكلية', 'دبلوم', 'بكالوريوس', 'ماجستير'],
        'experience' => ['experience', 'employment', 'work history', 'career', 'internship', 'training', 'الخبرات', 'الخبرة', 'التدريب', 'العمل', 'الوظائف', 'التعاوني'],
        'skills' => ['skills', 'languages', 'certifications', 'courses', 'projects', 'volunteer', 'references', 'المهارات', 'اللغات', 'الدورات', 'الشهادات', 'المشاريع', 'التطوع', 'المراجع'],
        default => []
    };

    $lines = preg_split('/\R/u', $text) ?: [$text];
    $selected = [];

    foreach ($lines as $index => $line) {
        $lower = mb_strtolower($line, 'UTF-8');
        $matched = false;
        foreach ($keywords as $keyword) {
            if (mb_strpos($lower, $keyword) !== false) {
                $matched = true;
                break;
            }
        }
        if (!$matched) continue;

        $start = max(0, $index - 1);
        $end = min(count($lines) - 1, $index + 8);
        for ($i = $start; $i <= $end; $i++) {
            $selected[$i] = $lines[$i];
        }
    }

    if (!$selected) return mb_substr($text, 0, $limit, 'UTF-8');
    ksort($selected);
    return mb_substr(implode("\n", $selected), 0, $limit, 'UTF-8');
}

// ========================================
// الاتصال بـ Ollama
// ========================================
// [تعديل] إضافة متغير $model لاستقبال اسم الموديل من الإعدادات
function askOllama(string $source, array $shape, string $group, string $model): ?array {
    $schema = json_encode($shape, JSON_UNESCAPED_UNICODE);

    $prompt = <<<PROMPT
You are a strict resume information extractor.

Extract ONLY facts explicitly supported by the CV.

IMPORTANT RULES:
1. Never invent education, jobs, projects, volunteering, certificates or references.
2. Do not add examples or sample data.
3. Never copy placeholder values from the requested JSON schema.
4. If a section is absent, return an empty array.
5. If a field is absent, return an empty string.
6. Preserve Arabic and English exactly as written when possible.
7. Do not translate names or qualifications.
8. Never assume someone has a bachelor's degree.
9. Never infer experience from skills.
10. Never infer projects from courses.
11. Return valid JSON only.
12. Every listed record must have evidence in the CV.

Return this structure:
{$schema}

CV TEXT:
{$source}
PROMPT;

    $payload = json_encode([
        'model' => $model, // [تعديل] استخدام الموديل الديناميكي
        'prompt' => $prompt,
        'stream' => false,
        'format' => 'json',
        'keep_alive' => '10m',
        'options' => [
            'temperature' => 0,
            'num_ctx' => 4096,
            'num_predict' => 1800
        ]
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) return null;

    $ch = curl_init('http://127.0.0.1:11434/api/generate');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_NOSIGNAL => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        smartLog($group . ': ' . $error);
        return null;
    }

    $outer = json_decode((string)$response, true);
    if (!is_array($outer) || !is_string($outer['response'] ?? null)) return null;

    $inner = json_decode(trim($outer['response']), true);
    if (!is_array($inner) || array_is_list($inner)) {
        smartLog($group . ': invalid JSON');
        return null;
    }

    return $inner;
}

// ========================================
// هيكل البيانات
// ========================================
$data = [
    'fullName' => '', 'jobTitle' => '', 'email' => '', 'phone' => '', 'city' => '', 'linkedin' => '', 'summary' => '',
    'education' => [], 'experience' => [], 'skills' => [], 'languages' => [], 'certifications' => [], 'projects' => [], 'volunteering' => [], 'references' => []
];

$sectionFields = [
    'education' => ['degree', 'major', 'school', 'year'],
    'experience' => ['position', 'company', 'start', 'end', 'description'],
    'skills' => ['name'],
    'languages' => ['name', 'level'],
    'certifications' => ['title', 'issuer', 'date', 'url'],
    'projects' => ['title', 'url', 'description'],
    'volunteering' => ['role', 'organization', 'start', 'end', 'description'],
    'references' => ['name', 'position', 'organization', 'contact']
];

$groups = [
    'basic' => ['fullName' => '', 'jobTitle' => '', 'email' => '', 'phone' => '', 'city' => '', 'linkedin' => '', 'summary' => ''],
    'education' => ['education' => [['degree' => '', 'major' => '', 'school' => '', 'year' => '']]],
    'experience' => ['experience' => [['position' => '', 'company' => '', 'start' => '', 'end' => '', 'description' => '']]],
    'skills' => [
        'skills' => [['name' => '']], 'languages' => [['name' => '', 'level' => '']], 'certifications' => [['title' => '', 'issuer' => '', 'date' => '', 'url' => '']],
        'projects' => [['title' => '', 'url' => '', 'description' => '']], 'volunteering' => [['role' => '', 'organization' => '', 'start' => '', 'end' => '', 'description' => '']], 'references' => [['name' => '', 'position' => '', 'organization' => '', 'contact' => '']]
    ]
];

// ========================================
// تحليل كل مجموعة والتحقق من نتائجها
// ========================================
$failedGroups = [];
$rejectedRecords = [];

foreach ($groups as $group => $shape) {
    $input = sectionText($text, $group, 4200);
    $answer = askOllama($input, $shape, $group, $aiModel); // [تعديل] تمرير الموديل

    if ($answer === null) {
        $answer = askOllama(sectionText($text, $group, 2200), $shape, $group . '_retry', $aiModel); // [تعديل] تمرير الموديل
    }

    if ($answer === null) {
        $failedGroups[] = $group;
        continue;
    }

    $foundKey = false;
    foreach (array_keys($shape) as $key) {
        if (array_key_exists($key, $answer)) {
            $foundKey = true;
            break;
        }
    }
    if (!$foundKey) {
        $failedGroups[] = $group;
        continue;
    }

    foreach ($shape as $key => $sample) {
        $value = $answer[$key] ?? null;

        if (!is_array($sample)) {
            $data[$key] = verifyField($key, cleanValue($value), $text);
            continue;
        }

        if (!is_array($value) || !array_is_list($value)) continue;

        foreach ($value as $item) {
            if (!is_array($item)) continue;
            $record = [];
            foreach ($sectionFields[$key] as $field) {
                $record[$field] = cleanValue($item[$field] ?? '');
            }

            if (!verifyRecord($key, $record, $text)) {
                $rejectedRecords[$key] = ($rejectedRecords[$key] ?? 0) + 1;
                continue;
            }

            $secondaryFields = match ($key) {
                'education' => ['major', 'year'],
                'experience' => ['start', 'end', 'description'],
                'languages' => ['level'],
                'certifications' => ['issuer', 'date', 'url'],
                'projects' => ['url', 'description'],
                'volunteering' => ['start', 'end', 'description'],
                'references' => ['position', 'contact'],
                default => []
            };

            foreach ($secondaryFields as $field) {
                if ($record[$field] === '' || isPlaceholder($record[$field])) {
                    $record[$field] = '';
                    continue;
                }
                $threshold = in_array($field, ['description'], true) ? 0.40 : 0.60;
                if (!supportedBySource($record[$field], $text, $threshold)) {
                    $record[$field] = '';
                }
            }

            if (!in_array($record, $data[$key], true)) {
                $data[$key][] = $record;
            }
        }
    }
}

if (count($failedGroups) === count($groups)) {
    fail(503, 'تعذر تحليل السيرة. تأكد من تشغيل Ollama.');
}

$complete = count($failedGroups) === 0;
smartLog('Analysis complete=' . ($complete ? 'yes' : 'no') . ' failed=' . implode(',', $failedGroups) . ' rejected=' . json_encode($rejectedRecords));

respond(200, [
    'success' => true,
    'message' => $complete ? 'اكتمل استخراج البيانات. راجعها قبل الحفظ.' : 'اكتمل التحليل جزئيًا. راجع البيانات قبل الحفظ.',
    'resume' => $data,
    'analysis_complete' => $complete,
    'failed_groups' => $failedGroups,
    'rejected_records' => $rejectedRecords
]);