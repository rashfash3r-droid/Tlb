<?php
ob_start();

// ============================================================
// تحميل الإعدادات المركزية والـ Logger
// ============================================================
// ============================================================
// [MERGED] config.php
// ============================================================
/**
 * config.php
 * ملف الإعدادات المركزي - يحتوي على جميع الثوابت والمتغيرات الحساسة
 * يجب وضع هذا الملف خارج مجلد الويب إن أمكن، أو حمايته بـ .htaccess
 */

// ============================================================
// إعدادات البوت الرئيسية - عدّل هذه القيم فقط
// ============================================================

/** توكن البوت من @BotFather */
if (!defined('BOT_TOKEN')) {
    define('BOT_TOKEN', '8452140704:AAHulgLSbqZkVzAT_hbb9sObcDnfUeD1PQc');
}

/** آيدي الأدمن الرئيسي (رقم) */
if (!defined('BOT_ADMIN_ID')) {
    define('BOT_ADMIN_ID', '5254495041');
}

/** معرف الدعم الفني */
if (!defined('BOT_SUPPORT')) {
    define('BOT_SUPPORT', '@SHBH_S1');
}

// ============================================================
// مفتاح حماية الـ Webhook
// أنشئ نصاً عشوائياً وضعه هنا - سيُطلب عند إعداد الـ Webhook
// مثال: openssl rand -hex 16
// ============================================================
if (!defined('WEBHOOK_SECRET')) {
    define('WEBHOOK_SECRET', '32d8dab766cb171d71c1541b35add336');
}

// ============================================================
// مسارات الملفات - لا تغيّر إلا عند الضرورة
// ============================================================
if (!defined('DATA_DIR')) {
    define('DATA_DIR',  __DIR__ . '/data');
}
if (!defined('SUDO_DIR')) {
    define('SUDO_DIR',  __DIR__ . '/sudo');
}
if (!defined('LOG_FILE')) {
    define('LOG_FILE',  __DIR__ . '/logs/bot_errors.log');
}
if (!defined('LOG_MAX_SIZE')) {
    define('LOG_MAX_SIZE', 5 * 1024 * 1024); // 5MB - يتم تدويره عند تجاوز الحجم
}

// ============================================================
// إعدادات الأداء
// ============================================================
/** مهلة طلبات cURL بالثواني */
if (!defined('CURL_TIMEOUT')) {
    define('CURL_TIMEOUT', 10);
}

/** الحد الأقصى لعدد الطلبات في الثانية لكل مستخدم (Rate Limit) */
if (!defined('RATE_LIMIT')) {
    define('RATE_LIMIT', 0.6);
}

// ============================================================
// [MERGED] logger.php
// ============================================================
/**
 * logger.php
 * نظام تسجيل الأخطاء البسيط
 * يكتب في logs/bot_errors.log ويدوّر الملف عند تجاوز الحجم الأقصى
 */

if (!defined('LOG_FILE')) {
    define('LOG_FILE',     __DIR__ . '/logs/bot_errors.log');
    define('LOG_MAX_SIZE', 5 * 1024 * 1024);
}

/**
 * تسجيل رسالة خطأ أو حدث
 *
 * @param string $level   مستوى الخطأ: ERROR | WARNING | INFO
 * @param string $message نص الرسالة
 * @param array  $context بيانات إضافية (اختياري)
 */
function bot_log(string $level, string $message, array $context = []): void
{
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    // تدوير الملف عند تجاوز الحجم الأقصى
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) >= LOG_MAX_SIZE) {
        @rename(LOG_FILE, LOG_FILE . '.' . date('YmdHis') . '.bak');
    }

    $contextStr = $context ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
    $line = sprintf(
        "[%s] [%s] %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),
        $message,
        $contextStr
    );

    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

/**
 * تسجيل استثناء (Exception) بشكل كامل
 */
function bot_log_exception(Throwable $e, string $note = ''): void
{
    bot_log('ERROR', ($note ? "[$note] " : '') . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}

// متغيرات التوافق مع بقية الكود القديم
$VISCODEV = BOT_TOKEN;
$admin    = BOT_ADMIN_ID;
$sudo     = $admin;
if (!defined('API_KEY')) {
    define('API_KEY', BOT_TOKEN);
}

// ============================================================
// إعداد الـ Webhook - محمي بمفتاح سري
// ============================================================
if (isset($_GET['set_webhook'])) {
    // التحقق من المفتاح السري لمنع إعداد Webhook بشكل غير مصرح
    $provided_secret = $_GET['secret'] ?? '';
    if (!hash_equals(WEBHOOK_SECRET, $provided_secret)) {
        http_response_code(403);
        exit('غير مصرح');
    }
    $webhook_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
    $result = bot_curl('setWebhook', ['url' => $webhook_url]);
    echo json_encode($result);
    exit;
}

// ============================================================
// دالة استدعاء Telegram API عبر cURL (أسرع وأكثر موثوقية)
// ============================================================
function bot_curl(string $method, array $datas = [])
{
    $url = 'https://api.telegram.org/bot' . API_KEY . '/' . $method;
    $ch  = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $datas,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => CURL_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        bot_log('ERROR', 'cURL فشل في استدعاء ' . $method, ['error' => curl_error($ch)]);
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    return json_decode($response);
}

// ============================================================
// دالة bot() المتوافقة مع الكود الموجود - تستخدم cURL داخلياً
// ============================================================
function bot($method, $datas = [])
{
    return bot_curl($method, $datas);
}
// ============================================================
// [MERGED] wallet.php
// ============================================================
/**
 * wallet.php
 * سجل حركات الرصيد (Ledger) — يُستخدم فقط للتسجيل والعرض،
 * لا يُغيّر أي منطق خصم/إضافة موجود في rachq.php.
 */

if (!defined('DATA_DIR')) {
    define('DATA_DIR', __DIR__ . '/data');
}
if (!defined('WALLET_LOG_FILE')) {
    define('WALLET_LOG_FILE', __DIR__ . '/data/wallet_ledger.log');
}

if (!is_dir(dirname(WALLET_LOG_FILE))) {
    @mkdir(dirname(WALLET_LOG_FILE), 0775, true);
}

/**
 * تسجيل حركة في سجل الرصيد (إضافة، خصم، هدية، شحن كوبون ... إلخ)
 *
 * @param int|string $from_id
 * @param string     $type    charge | deduct | gift | discount | other
 * @param float      $amount
 * @param string     $note
 * @param array      $extra   بيانات إضافية اختيارية مثل balance_after
 */
function wallet_log($from_id, string $type, float $amount, string $note = '', array $extra = []): void
{
    $entry = [
        'time'    => date('Y-m-d H:i:s'),
        'from_id' => (string)$from_id,
        'type'    => $type,
        'amount'  => $amount,
        'note'    => $note,
    ] + $extra;

    @file_put_contents(
        WALLET_LOG_FILE,
        json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n",
        FILE_APPEND | LOCK_EX
    );
}

/**
 * كشف حساب نصي لآخر حركات مستخدم معيّن (لأمر /wallet)
 */
function wallet_statement_text($from_id, int $limit = 10): string
{
    $from_id = (string)$from_id;
    if (!file_exists(WALLET_LOG_FILE)) {
        return "📭 لا توجد حركات مسجّلة في رصيدك حتى الآن.";
    }

    $lines   = array_reverse(file(WALLET_LOG_FILE, FILE_IGNORE_NEW_LINES));
    $matches = [];
    foreach ($lines as $l) {
        $e = json_decode($l, true);
        if (is_array($e) && ($e['from_id'] ?? '') === $from_id) {
            $matches[] = $e;
            if (count($matches) >= $limit) {
                break;
            }
        }
    }

    if (!$matches) {
        return "📭 لا توجد حركات مسجّلة في رصيدك حتى الآن.";
    }

    $text = "💼 <b>كشف حساب آخر " . count($matches) . " حركة</b>\n━━━━━━━━━━━━━━━━━\n";
    foreach ($matches as $e) {
        $sign = in_array($e['type'], ['deduct'], true) ? '-' : '+';
        $text .= "🕐 {$e['time']} | {$sign}{$e['amount']} | {$e['note']}\n";
    }
    return $text;
}

/**
 * سجل نصي لآخر حركات كل المستخدمين (للأدمن فقط، أمر /stats)
 */
function wallet_admin_log_text(int $limit = 10): string
{
    if (!file_exists(WALLET_LOG_FILE)) {
        return "📭 لا توجد حركات مسجّلة في سجل الرصيد.";
    }

    $lines = array_slice(array_reverse(file(WALLET_LOG_FILE, FILE_IGNORE_NEW_LINES)), 0, $limit);
    if (!$lines) {
        return "📭 لا توجد حركات مسجّلة في سجل الرصيد.";
    }

    $text = "📜 <b>آخر " . count($lines) . " حركة (كل المستخدمين)</b>\n";
    foreach ($lines as $l) {
        $e = json_decode($l, true);
        if (!is_array($e)) {
            continue;
        }
        $text .= "🕐 {$e['time']} | 🆔 {$e['from_id']} | {$e['type']} | {$e['amount']} | {$e['note']}\n";
    }
    return $text;
}

// ============================================================
// [MERGED] security.php
// ============================================================
/**
 * security.php
 * Rate limiting + anti-spam + global error/exception handlers.
 * Required by rachq.php via require_once.
 */

if (!defined('DATA_DIR')) {
    define('DATA_DIR', __DIR__ . '/data');
}
if (!defined('SECURITY_DIR')) {
    define('SECURITY_DIR', __DIR__ . '/security_store');
}
if (!is_dir(SECURITY_DIR)) {
    @mkdir(SECURITY_DIR, 0775, true);
}

/**
 * تسجيل معالِجات الأخطاء والاستثناءات العامة — تكتب في اللوغ بدل توقف البوت
 */
function security_register_error_handlers(): void
{
    set_exception_handler(function (Throwable $e): void {
        if (function_exists('bot_log_exception')) {
            bot_log_exception($e, 'uncaught');
        }
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            if (function_exists('bot_log')) {
                bot_log('ERROR', 'Fatal: ' . $err['message'], [
                    'file' => $err['file'],
                    'line' => $err['line'],
                ]);
            }
        }
    });
}

/**
 * Rate limit بسيط لكل مستخدم: يمنع تكرار الطلبات أسرع من $minIntervalSeconds
 */
function security_rate_limit_allow($from_id, float $minIntervalSeconds = 0.6): bool
{
    $from_id = (string)$from_id;
    if ($from_id === '' || $from_id === '0') {
        return true;
    }
    $path = SECURITY_DIR . "/rl_{$from_id}.txt";
    $now  = microtime(true);

    $last = file_exists($path) ? (float)@file_get_contents($path) : 0;
    if (($now - $last) < $minIntervalSeconds) {
        return false;
    }
    @file_put_contents($path, (string)$now, LOCK_EX);
    return true;
}

/**
 * يمنع إرسال نفس الرسالة/الزر مرتين بسرعة غير طبيعية (تكرار خلال $windowSeconds)
 */
function security_is_duplicate_spam($from_id, string $payload, float $windowSeconds = 1.5): bool
{
    $from_id = (string)$from_id;
    if ($from_id === '' || $payload === '') {
        return false;
    }
    $path = SECURITY_DIR . "/spam_{$from_id}.json";
    $now  = microtime(true);
    $hash = md5($payload);

    $prev = null;
    if (file_exists($path)) {
        $prev = json_decode((string)@file_get_contents($path), true);
    }

    $isDup = is_array($prev)
        && ($prev['hash'] ?? '') === $hash
        && ($now - (float)($prev['t'] ?? 0)) < $windowSeconds;

    @file_put_contents($path, json_encode(['hash' => $hash, 't' => $now]), LOCK_EX);

    return $isDup;
}

// ============================================================
// [MERGED] orders.php
// ============================================================
/**
 * orders.php
 * تسجيل الطلبات وتتبّعها — كتابة ذرية في akl/orders.txt (سطر JSON لكل طلب)
 * متوافق مع الحقول المستخدمة في sec_stats والبحث عن الطلب (id, status, service, coin, created_at, from_id)
 */

if (!defined('ORDERS_FILE')) {
    define('ORDERS_FILE', __DIR__ . '/akl/orders.txt');
}

if (!is_dir(dirname(ORDERS_FILE))) {
    @mkdir(dirname(ORDERS_FILE), 0775, true);
}

/**
 * تسجيل طلب جديد بحالة "pending"
 *
 * @param string|int $orderId   رقم الطلب من المزوّد (order id)
 * @param string|int $from_id   آيدي المستخدم
 * @param string|int $serviceId رقم الخدمة
 * @param string     $link      الرابط/المحتوى المُرسَل للخدمة
 * @param mixed      $quantity  الكمية
 * @param float      $coin      السعر بالعملة (نقاط)
 */
function order_create($orderId, $from_id, $serviceId, string $link, $quantity, float $coin): bool
{
    $record = [
        'id'         => (string)$orderId,
        'from_id'    => (string)$from_id,
        'service'    => (string)$serviceId,
        'link'       => $link,
        'quantity'   => $quantity,
        'coin'       => $coin,
        'status'     => 'pending',
        'created_at' => time(),
    ];

    $line = json_encode($record, JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        if (function_exists('bot_log')) {
            bot_log('ERROR', 'order_create: فشل json_encode', ['order_id' => $orderId]);
        }
        return false;
    }

    return @file_put_contents(ORDERS_FILE, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
}

/**
 * تحديث حالة طلب موجود (pending/processing/completed/canceled)
 * يعيد كتابة الملف كاملاً بطريقة ذرية لضمان عدم تلفه عند التزامن
 */
function order_update_status($orderId, string $status): bool
{
    if (!file_exists(ORDERS_FILE)) {
        return false;
    }
    $orderId = (string)$orderId;
    $lines   = file(ORDERS_FILE, FILE_IGNORE_NEW_LINES);
    $changed = false;
    $out     = [];

    foreach ($lines as $l) {
        $od = json_decode($l, true);
        if (is_array($od) && (string)($od['id'] ?? '') === $orderId) {
            $od['status'] = $status;
            $changed       = true;
            $out[]         = json_encode($od, JSON_UNESCAPED_UNICODE);
        } else {
            $out[] = $l;
        }
    }

    if (!$changed) {
        return false;
    }

    $tmp = ORDERS_FILE . '.tmp.' . getmypid();
    if (file_put_contents($tmp, implode("\n", $out) . "\n", LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    return rename($tmp, ORDERS_FILE);
}

/**
 * نص "طلباتي" لمستخدم معيّن (لأمر /orders)
 */
function orders_user_text($from_id, int $limit = 10): string
{
    if (!file_exists(ORDERS_FILE)) {
        return "📭 لا توجد طلبات مسجّلة باسمك حتى الآن.";
    }
    $from_id = (string)$from_id;
    $statusMp = [
        'pending'    => '⏳ قيد الانتظار',
        'processing' => '⚙️ قيد التنفيذ',
        'completed'  => '✅ مكتمل',
        'canceled'   => '❌ ملغي',
    ];

    $matches = [];
    foreach (array_reverse(file(ORDERS_FILE, FILE_IGNORE_NEW_LINES)) as $l) {
        $od = json_decode($l, true);
        if (is_array($od) && (string)($od['from_id'] ?? '') === $from_id) {
            $matches[] = $od;
            if (count($matches) >= $limit) {
                break;
            }
        }
    }

    if (!$matches) {
        return "📭 لا توجد طلبات مسجّلة باسمك حتى الآن.";
    }

    $text = "📮 <b>آخر " . count($matches) . " طلب لك</b>\n━━━━━━━━━━━━━━━━━\n";
    foreach ($matches as $od) {
        $st = $statusMp[$od['status'] ?? 'pending'] ?? ($od['status'] ?? '—');
        $text .= "🔢 #{$od['id']} | 🛠 {$od['service']} | {$st}\n";
    }
    return $text;
}

/**
 * إحصائيات عامة للطلبات (لأمر /stats للأدمن)
 */
function orders_stats(): array
{
    $stats = [
        'total_orders'      => 0,
        'total_revenue'     => 0,
        'by_status'         => ['pending' => 0, 'processing' => 0, 'completed' => 0, 'canceled' => 0],
        'top_service'       => null,
        'top_service_count' => 0,
    ];

    if (!file_exists(ORDERS_FILE)) {
        return $stats;
    }

    $perService = [];
    foreach (file(ORDERS_FILE, FILE_IGNORE_NEW_LINES) as $l) {
        $od = json_decode($l, true);
        if (!is_array($od)) {
            continue;
        }
        $stats['total_orders']++;
        $stats['total_revenue'] += (float)($od['coin'] ?? 0);

        $status = $od['status'] ?? 'pending';
        if (array_key_exists($status, $stats['by_status'])) {
            $stats['by_status'][$status]++;
        }

        $svc = (string)($od['service'] ?? '');
        if ($svc !== '') {
            $perService[$svc] = ($perService[$svc] ?? 0) + 1;
        }
    }

    if ($perService) {
        arsort($perService);
        $stats['top_service']       = array_key_first($perService);
        $stats['top_service_count'] = reset($perService);
    }

    return $stats;
}

// ============================================================
// [MERGED] coupons.php
// ============================================================
/**
 * coupons.php
 * --------------------------------------------------------------
 * نظام كوبونات بسيط فوق النظام الحالي.
 * نوعان فقط:
 *   - charge  : شحن رصيد ثابت عند استخدام الكود
 *   - discount: نسبة خصم تُطبَّق يدويًا من الكود الذي يستدعي الدالة
 * كل كوبون يُستخدم مرة واحدة فقط لكل مستخدم (one-time per user).
 * التخزين JSON مع كتابة ذرية لمنع تلف الملف عند التزامن.
 * --------------------------------------------------------------
 */

if (!defined('COUPONS_DATA_DIR')) {
    define('COUPONS_DATA_DIR', __DIR__ . '/data');
}
if (!is_dir(COUPONS_DATA_DIR)) {
    @mkdir(COUPONS_DATA_DIR, 0775, true);
}

// ---------------------------------------------------------------
// دوال القراءة والكتابة مع الحماية من التلف
// ---------------------------------------------------------------

function coupons_path(): string
{
    return COUPONS_DATA_DIR . '/coupons.json';
}

function coupons_read(): array
{
    $path = coupons_path();
    if (!file_exists($path)) {
        return [];
    }
    $fp = @fopen($path, 'r');
    if (!$fp) {
        $raw  = @file_get_contents($path);
        $data = json_decode((string)$raw, true);
        return is_array($data) ? $data : [];
    }
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * كتابة ذرية: يكتب في ملف مؤقت ثم ينقله مرة واحدة لمنع تلف البيانات
 */
function coupons_write(array $coupons): bool
{
    $path    = coupons_path();
    $tmpPath = $path . '.tmp.' . getmypid();
    $json    = json_encode($coupons, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    if ($json === false) {
        if (function_exists('bot_log')) {
            bot_log('ERROR', 'coupons_write: فشل json_encode');
        }
        return false;
    }

    // الكتابة في ملف مؤقت
    if (file_put_contents($tmpPath, $json, LOCK_EX) === false) {
        @unlink($tmpPath);
        if (function_exists('bot_log')) {
            bot_log('ERROR', 'coupons_write: فشل الكتابة في الملف المؤقت');
        }
        return false;
    }

    // نقل ذري
    if (!rename($tmpPath, $path)) {
        @unlink($tmpPath);
        if (function_exists('bot_log')) {
            bot_log('ERROR', 'coupons_write: فشل rename الذري');
        }
        return false;
    }

    return true;
}

// ---------------------------------------------------------------
// التحقق من صحة المدخلات
// ---------------------------------------------------------------

function coupon_validate_code(string $code): string
{
    $code = strtoupper(trim($code));
    // الكود: حروف وأرقام وشرطة فقط، من 2 إلى 30 حرفاً
    if (!preg_match('/^[A-Z0-9_\-]{2,30}$/', $code)) {
        return '';
    }
    return $code;
}

function coupon_validate_value(float $value, string $type): bool
{
    if ($value <= 0) {
        return false;
    }
    // نسبة الخصم لا يمكن أن تتجاوز 100%
    if ($type === 'discount' && $value > 100) {
        return false;
    }
    return true;
}

// ---------------------------------------------------------------
// الدوال الرئيسية
// ---------------------------------------------------------------

/**
 * إنشاء كوبون جديد (من لوحة الأدمن).
 *
 * @param string $code     الكود (سيُحوَّل لحروف كبيرة)
 * @param string $type     charge | discount
 * @param float  $value    قيمة الشحن أو نسبة/قيمة الخصم
 * @param int    $max_uses الحد الأقصى للاستخدام (0 = بلا حد)
 * @return array|null الكوبون المُنشأ أو null عند فشل التحقق
 */
function coupon_create(string $code, string $type, float $value, int $max_uses = 0): ?array
{
    // التحقق من صحة الكود
    $code = coupon_validate_code($code);
    if ($code === '') {
        return null;
    }

    // التحقق من النوع
    if (!in_array($type, ['charge', 'discount'], true)) {
        return null;
    }

    // التحقق من القيمة
    if (!coupon_validate_value($value, $type)) {
        return null;
    }

    // max_uses لا يمكن أن يكون سالباً
    $max_uses = max(0, $max_uses);

    $coupons        = coupons_read();
    $coupons[$code] = [
        'code'       => $code,
        'type'       => $type,
        'value'      => $value,
        'max_uses'   => $max_uses,
        'used_by'    => [],
        'created_at' => time(),
        'active'     => true,
    ];
    coupons_write($coupons);
    return $coupons[$code];
}

function coupon_get(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return null;
    }
    $coupons = coupons_read();
    return $coupons[$code] ?? null;
}

function coupon_disable(string $code): bool
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return false;
    }
    $coupons = coupons_read();
    if (!isset($coupons[$code])) {
        return false;
    }
    $coupons[$code]['active'] = false;
    return coupons_write($coupons);
}

/**
 * محاولة استخدام كوبون من طرف مستخدم.
 *
 * @param string     $code
 * @param int|string $from_id
 * @return array ['ok'=>bool, 'message'=>string, 'coupon'=>array|null]
 */
function coupon_redeem(string $code, $from_id): array
{
    $code    = strtoupper(trim($code));
    $from_id = (string)$from_id;

    if ($code === '' || $from_id === '' || $from_id === '0') {
        return ['ok' => false, 'message' => 'بيانات غير صحيحة ❌', 'coupon' => null];
    }

    $coupons = coupons_read();

    if (!isset($coupons[$code])) {
        return ['ok' => false, 'message' => 'الكود غير صحيح ❌', 'coupon' => null];
    }

    $coupon = $coupons[$code];

    if (empty($coupon['active'])) {
        return ['ok' => false, 'message' => 'هذا الكود غير مفعّل حالياً ❌', 'coupon' => null];
    }

    $usedBy = $coupon['used_by'] ?? [];
    if (in_array($from_id, $usedBy, true)) {
        return ['ok' => false, 'message' => 'لقد استخدمت هذا الكود من قبل ❌', 'coupon' => null];
    }

    if ((int)$coupon['max_uses'] > 0 && count($usedBy) >= (int)$coupon['max_uses']) {
        return ['ok' => false, 'message' => 'انتهت عدد مرات استخدام هذا الكود ❌', 'coupon' => null];
    }

    // تسجيل الاستخدام
    $usedBy[]              = $from_id;
    $coupons[$code]['used_by'] = $usedBy;
    coupons_write($coupons);

    return ['ok' => true, 'message' => 'تم استخدام الكود بنجاح ✅', 'coupon' => $coupons[$code]];
}

security_register_error_handlers();

// ============================================================
// [NEW] الطبقة الجديدة (ne_*) — الواجهة المطورة + لوحة الإمبراطور
// كل هذه الدوال إضافية ولا تُعدّل أي منطق خصم/إضافة قديم.
// ============================================================

if (!defined('NE_EDID_DIR')) {
    define('NE_EDID_DIR', __DIR__ . '/edid');
}
if (!defined('NE_FLAGS_FILE')) {
    define('NE_FLAGS_FILE', __DIR__ . '/edid/ne_flags.json');
}
if (!is_dir(NE_EDID_DIR)) { @mkdir(NE_EDID_DIR, 0775, true); }

/** إجمالي مصروفات المستخدم (مأخوذة من نفس ملف العدّاد المستخدم في قسم "الحساب" الحالي) */
function ne_total_spent($chat_id): int
{
    $p = __DIR__ . "/amr/$chat_id/coirlt.txt";
    return file_exists($p) ? (int)file_get_contents($p) : 0;
}

/** العملة المختارة للمستخدم (افتراضي: ريال يمني قديم) */
function ne_currency($from_id, $cuser): string
{
    $c = $cuser["userfild"][$from_id]["currency"] ?? '';
    return $c !== '' ? $c : 'ريال يمني قديم (ر.ي.ش)';
}

/** رقم حساب ثابت مُشتق من آيدي المستخدم (بدون الحاجة لتخزين إضافي) */
function ne_account_no($from_id): string
{
    return 'AC-' . (100000 + ((int)$from_id % 900000));
}

/** مستوى المستخدم: مميز إن كان حساب تيليجرام بريميوم */
function ne_level($message): string
{
    $prem = false;
    if (isset($message->from->is_premium) && $message->from->is_premium) { $prem = true; }
    return $prem ? 'مميز 💎' : 'عادي';
}

/** نص القائمة الرئيسية الجديدة (HTML) */
function ne_home_text($chat_id, $from_id, $cuser, $message, $cdiamlaadf, $cdiamlanoo): string
{
    $coin     = (int)($cuser["userfild"][$from_id]["coin"] ?? 0);
    $spent    = ne_total_spent($chat_id);
    $currency = ne_currency($from_id, $cuser);
    $acc      = ne_account_no($from_id);
    $level    = ne_level($message);
    $txt  = "🎛 <b>رقم حسابك:</b> <code>{$acc}</code>\n";
    $txt .= "🦚 <b>المستوى:</b> {$level}\n";
    $txt .= "🆔 <b>ايديك:</b> <code>{$from_id}</code>\n";
    $txt .= "💰 <b>الرصيد المتوفر:</b> {$coin} {$cdiamlanoo}\n";
    $txt .= "💸 <b>الصرفيات:</b> {$spent} {$cdiamlanoo}\n";
    $txt .= "🇾🇪 <b>العملة:</b> {$currency}\n";
    $txt .= "--------------------------------------------\n";
    $txt .= "💁‍♂️ <b>يمكن التحكم بالبوت عبر الأزرار في الأسفل. 👇</b>";
    return $txt;
}

/** لوحة أزرار القائمة الرئيسية الجديدة */
function ne_home_keyboard(): array
{
    return [
        [['text' => '🎬 بدء تلبية رشق جديدة', 'callback_data' => 'takecoinn']],
        [['text' => '📇 أرقام وهمية', 'callback_data' => 'ne_fake_numbers'], ['text' => '🎁 شحن الألعاب', 'callback_data' => 'ne_game_topup']],
        [['text' => '⭐ خدماتي المفضلة', 'callback_data' => 'ne_favorites'], ['text' => '📚 خدمات مجانية', 'callback_data' => 'ne_free_services']],
        [['text' => '💳 شحن كرت', 'callback_data' => 'amr6'], ['text' => '💰 إشحن رصيدك', 'callback_data' => 'amr2']],
        [['text' => '🔑 مفتاح API 🌐', 'callback_data' => 'ne_api_key']],
        [['text' => '📋 طلب تعويض', 'callback_data' => 'ne_compensation'], ['text' => '🔄 تغيير العملة', 'callback_data' => 'ne_currency']],
        [['text' => '🔄 تحويل رصيد', 'callback_data' => 'sendcoin'], ['text' => '➕ رصيد مجاني', 'callback_data' => 'ne_referral']],
        [['text' => '⚙️ المزيد والاعدادات', 'callback_data' => 'ne_more']],
        [['text' => '📞 الدعم الفني', 'callback_data' => 'ne_support']],
    ];
}

/** يقرأ/يكتب أعلام الحماية الموحّدة للوحة الإمبراطور */
function ne_flags_read(): array
{
    if (!file_exists(NE_FLAGS_FILE)) { return []; }
    $d = json_decode((string)@file_get_contents(NE_FLAGS_FILE), true);
    return is_array($d) ? $d : [];
}

/** إجمالي أرباح الإحالة لمستخدم عبر سجل المحفظة (نوع gift) */
function ne_referral_earnings($from_id): int
{
    if (!file_exists(WALLET_LOG_FILE)) { return 0; }
    $sum = 0;
    foreach (file(WALLET_LOG_FILE, FILE_IGNORE_NEW_LINES) as $l) {
        $e = json_decode($l, true);
        if (is_array($e) && (string)($e['from_id'] ?? '') === (string)$from_id && ($e['type'] ?? '') === 'gift') {
            $sum += (float)($e['amount'] ?? 0);
        }
    }
    return (int)$sum;
}

/** توليد/استرجاع مفتاح API خاص بالمستخدم */
function ne_get_or_create_api_key($from_id, array $cuser): array
{
    $key = $cuser["userfild"][$from_id]["api_key"] ?? '';
    $changed = false;
    if ($key === '') {
        $key = 'RQ-' . strtoupper(bin2hex(random_bytes(16)));
        $cuser["userfild"][$from_id]["api_key"] = $key;
        $changed = true;
    }
    return [$key, $cuser, $changed];
}

/** زر رجوع موحّد */
function ne_back_row(string $cb = 'panel'): array
{
    return [['text' => '🔙 رجوع ↩️', 'callback_data' => $cb]];
}

/** لوحة إحصائيات مباشرة للأدمن (تُبنى من akl/orders.txt و wallet_ledger.log و data/user.json) */
function ne_admin_stats_text(): string
{
    $usersData = json_decode(@file_get_contents(__DIR__ . '/data/user.json'), true);
    $usersData = is_array($usersData) ? $usersData : [];
    $totalUsers = isset($usersData['userlist']) ? count($usersData['userlist']) : 0;
    $bannedCount = isset($usersData['blocklist']) ? count($usersData['blocklist']) : 0;

    $ordersPath = __DIR__ . '/akl/orders.txt';
    $total = 0; $today = 0;
    $st = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'canceled' => 0];
    $revenue = 0.0;
    $perService = [];
    if (file_exists($ordersPath)) {
        foreach (file($ordersPath, FILE_IGNORE_NEW_LINES) as $l) {
            $l = trim($l);
            if ($l === '') { continue; }
            $od = json_decode($l, true);
            if (is_array($od)) {
                $total++;
                $status = $od['status'] ?? 'pending';
                if (array_key_exists($status, $st)) { $st[$status]++; }
                $revenue += (float)($od['coin'] ?? 0);
                $svc = (string)($od['service'] ?? '');
                if ($svc !== '') { $perService[$svc] = ($perService[$svc] ?? 0) + 1; }
                if (isset($od['created_at']) && date('Y-m-d', (int)$od['created_at']) === date('Y-m-d')) { $today++; }
            } else {
                // تنسيق قديم: سطر يحتوي رقم الطلب فقط
                $total++;
            }
        }
    }
    $topService = '—';
    if ($perService) { arsort($perService); $topService = (string)array_key_first($perService); }

    $couponCharge = 0.0;
    $coupons = coupons_read();
    foreach ($coupons as $c) {
        if (($c['type'] ?? '') === 'charge') {
            $couponCharge += ((float)($c['value'] ?? 0)) * count($c['used_by'] ?? []);
        }
    }

    $txt  = "📊 <b>لوحة الإحصائيات المباشرة للمتجر:</b>\n";
    $txt .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $txt .= "👥 <b>إجمالي مستخدمي البوت:</b> <code>{$totalUsers}</code> عضو\n";
    $txt .= "🔥 <b>المتفاعلين اليوم:</b> <code>{$today}</code> عضو\n";
    $txt .= "📦 <b>إجمالي الطلبات المسجلة:</b> <code>{$total}</code> طلب\n";
    $txt .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $txt .= "⏳ <b>قيد الانتظار:</b> <code>{$st['pending']}</code> | ⚙️ <b>قيد التنفيذ:</b> <code>{$st['processing']}</code>\n";
    $txt .= "✅ <b>الطلبات المكتملة:</b> <code>{$st['completed']}</code> | ❌ <b>الطلبات الملغية:</b> <code>{$st['canceled']}</code>\n";
    $txt .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $txt .= "💰 <b>إجمالي الأرباح بالنقاط (المصروفة):</b> <code>" . number_format($revenue, 2) . "</code> نقطة\n";
    $txt .= "💳 <b>إجمالي النقاط المشحونة بكوبونات:</b> <code>" . number_format($couponCharge, 2) . "</code>\n";
    $txt .= "🔝 <b>الخدمة الأكثر طلباً في البوت:</b> <code>{$topService}</code>\n";
    $txt .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $txt .= "🚫 <b>عدد الأعضاء المحظورين:</b> <code>{$bannedCount}</code> عضو";
    return $txt;
}

// معالجة الأخطاء: تسجيل بدلاً من إيقاف البوت أو عرض أخطاء للمستخدم
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_WARNING) {
        bot_log('ERROR', "PHP Error [$errno]: $errstr", ['file' => $errfile, 'line' => $errline]);
    }
    return false; // استمرار المعالجة الافتراضية للأخطاء الحرجة
});

$raw_input = file_get_contents("php://input");
$update = json_decode($raw_input);
// حذف update.txt لأنه يكتب بيانات حساسة في كل طلب
$message    = $update->message ?? null;
$text       = $message->text ?? '';
$caption    = $message->caption ?? '';
$chat_id    = $message->chat->id ?? 0;
$from_id    = $message->from->id ?? 0;
$type       = $message->chat->type ?? '';
$message_id = $message->message_id ?? 0;
$name       = ($message->from->first_name ?? '') . ' ' . ($message->from->last_name ?? '');
$user       = strtolower($message->from->username ?? '');
$t          = $message->chat->title ?? '';
$data = ''; $mes_id = ''; $text_inline = ''; $up = null;
if(isset($update->callback_query)){
$up         = $update->callback_query;
$chat_id    = $up->message->chat->id;
$from_id    = $up->from->id;
$user       = strtolower($up->from->username ?? '');
$name       = ($up->from->first_name ?? '') . ' ' . ($up->from->last_name ?? '');
$message_id = $up->message->message_id;
$mes_id     = $update->callback_query->inline_message_id ?? '';
$data       = $up->data;
}
if(isset($update->inline_query)){
$from_id     = $update->inline_query->from->id;
$chat_id     = $from_id;
$name        = ($update->inline_query->from->first_name ?? '') . ' ' . ($update->inline_query->from->last_name ?? '');
$text_inline = $update->inline_query->query ?? '';
$mes_id      = $update->inline_query->inline_message_id ?? '';
$user        = strtolower($update->inline_query->from->username ?? '');
}
// --- Security: Rate Limit + Anti-Spam (لا يغيّر أي سلوك إلا منع الإفراط في الطلبات) ---
if($from_id){
    if(!security_rate_limit_allow($from_id, 0.6)){
        exit; // طلب أسرع من الحد المسموح، يتم تجاهله بصمت بدل معالجته
    }
    $security_payload = $data !== '' ? $data : $text;
    if($security_payload !== '' && security_is_duplicate_spam($from_id, $security_payload, 1.5)){
        exit; // تكرار لنفس الرسالة/الزر بسرعة غير طبيعية، تجاهل بصمت
    }
}
function getChatstats($chat_id, $VISCODEV)
{
    $result = bot_curl('getChatAdministrators', ['chat_id' => $chat_id]);
    return $result->ok ?? false;
}

function getmember($VISCODEV, $idchannel, $from_id)
{
    $result = bot_curl('getChatMember', ['chat_id' => $idchannel, 'user_id' => $from_id]);
    if (!$result) return 'no';
    $status = $result->result->status ?? '';
    if (in_array($status, ['left', 'kicked'], true)) return 'no';
    if (!$result->ok) return 'no';
    return 'yes';
}
@mkdir("sudo");
@mkdir("data");
@mkdir("amr");
@mkdir("akl");
@mkdir("edid");
@mkdir("edid/amr");

// تهيئة الملفات الافتراضية الناقصة في edid/ (لا تكتب فوق الموجود)
$_edid_defaults = [
    'edid/currency_name.txt' => 'نقطة',
];
foreach ($_edid_defaults as $_ef => $_ev) {
    if (!file_exists($_ef)) { @file_put_contents($_ef, $_ev); }
}
unset($_edid_defaults, $_ef, $_ev);

$filename = "baageel.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "✅");
    fclose($file);
}
$filename = "admin.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "$sudo");
    fclose($file);
}
$filename = "edid/opan.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "✅");
    fclose($file);
}
$filename = "edid/zerasase.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "✅");
    fclose($file);
}
$filename = "edid/zerasaseon.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "✅");
    fclose($file);
}
$filename = "edid/tmwel.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "✅");
    fclose($file);
}
$filename = "edid/coadd.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "✅");
    fclose($file);
}
$filename = "edid/add_day.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "✅");
    fclose($file);
}
$filename = "edid/mr_insta.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "❌");
    fclose($file);
}
$filename = "edid/mr_tektok.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "❌");
    fclose($file);
}
$filename = "edid/mr_telegram.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "❌");
    fclose($file);
}
$filename = "edid/mr_yoteop.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "❌");
    fclose($file);
}
$filename = "edid/mr_faesbook.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "❌");
    fclose($file);
}
$filename = "edid/mr_twetr.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "❌");
    fclose($file);
}
$filename = "edid/mr_free.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "❌");
    fclose($file);
}
$filename = "akl/orders.txt";
if(!file_exists($filename)) {
    $file = fopen($filename, 'w');
    fwrite($file, "");
    fclose($file);
}
$anb =$admin;
$blp = $admin;
$usernamebot = bot('getme')->result->username ?? '';
$no3mak = ''; // infobot غير معرّف في هذا الموضع
if(!file_exists("sudo/member.txt")) file_put_contents("sudo/member.txt","");
if(!file_exists("sudo/ban.txt")) file_put_contents("sudo/ban.txt","");
$member = array_filter(explode("\n",file_get_contents("sudo/member.txt")));
$cunte = count($member);
$ban = array_filter(explode("\n",file_get_contents("sudo/ban.txt")));
$countban = count($ban);
@$watawjson = json_decode(file_get_contents("../wataw.json"),true);
$st_ch_bots=$watawjson["info"]["st_ch_bots"];
$id_ch_sudo1=$watawjson["info"]["id_channel"];
$link_ch_sudo1=$watawjson["info"]["link_channel"];
$id_ch_sudo2=$watawjson["info"]["id_channel2"];
$link_ch_sudo2=$watawjson["info"]["link_channel2"];
$user_bot_sudo=$watawjson["info"]["user_bot"];
@$projson = json_decode(file_get_contents("pro.json"),true);
$pro=$projson["info"]["pro"];
$dateon=$projson["info"]["dateon"];
$dateoff=$projson["info"]["dateoff"];
$time=time()+(3600 * 1);
if($message  and $st_ch_bots=="✅" and $pro!="yes"){
$stuts = getmember($VISCODEV,$id_ch_sudo1,$from_id);
if($stuts=="no"){
bot('sendMessage',[
'chat_id'=>$chat_id,
'reply_to_message_id'=>$message_id,
'text'=>"
⁦⁉️⁩ عذرا عزيزي
🌟يجب الاشتراك في قناة البوت اولا 
⁦🎗️⁩ثم اضغط /start ⁦🛎️⁩",
'disable_web_page_preview'=>true,
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>" اضغط للاشتراك في القناة ", 'url'=>"https://t.me/ViSCo"]],
]
])
]);return false;}
$stuts = getmember($VISCODEV,$id_ch_sudo2,$from_id);
if($stuts=="no"){
bot('sendMessage',[
'chat_id'=>$chat_id,
'reply_to_message_id'=>$message_id,
'text'=>"
⁦⁉️⁩ عذرا عزيزي
🌟يجب الاشتراك في قناة البوت اولا 
⁦🎗️⁩ثم اضغط /start ⁦🛎️⁩",
'disable_web_page_preview'=>true,
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[['text'=>" اضغط للاشتراك في القناة ", 'url'=>"https://t.me/$link_ch_sudo2"]],
]
])
]);
return false;}}
if($message  and in_array($from_id,$ban)){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"❌ لا تستطيع استخدام البوت انت محظور ❌
",
]);return false;}
@$infosudo = json_decode(file_get_contents("sudo.json"),true);
if(!file_exists("sudo.json")) {
#	$put = [];
$infosudo["info"]["admins"][]="$admin";
$infosudo["info"]["st_grop"]="ممنوع";
$infosudo["info"]["st_channel"]="مسموح";
$infosudo["info"]["fwrmember"]="❎";
$infosudo["info"]["tnbih"]="✅";
$infosudo["info"]["silk"]="✅";
$infosudo["info"]["allch"]="✅";
// $update/$message/$chat_id مُعرَّفة في بداية الملف
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$infosudo["info"]["klish_sil"]="⁦⁉️⁩ عذرا عزيزي
🌟يجب الاشتراك في قناة البوت اولا 
⁦🎗️⁩ثم اضغط /start ⁦🛎️⁩ ";
file_put_contents("sudo.json", json_encode($infosudo));
}
$fwrmember=$infosudo["info"]["fwrmember"];
$tnbih=$infosudo["info"]["tnbih"];
$silk=$infosudo["info"]["silk"];
$allch=$infosudo["info"]["allch"];
$start=$infosudo["info"]["start"];
$klish_sil=$infosudo["info"]["klish_sil"];
$sudo=$infosudo["info"]["admins"];
// --- أوامر جديدة إضافية: كشف الحساب وتتبع الطلبات (لا تؤثر على أي أمر موجود) ---
if($message and $text == '/wallet'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>wallet_statement_text($from_id)]);
return false;
}
if($message and $text == '/orders'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>orders_user_text($from_id)]);
return false;
}
if($message and $text == '/stats' and in_array($from_id,$sudo)){
$ostats = orders_stats();
$statsText = "📊 لوحة الإحصائيات\n\n"
    . "👤 عدد المستخدمين: $cunte\n"
    . "📦 عدد الطلبات: {$ostats['total_orders']}\n"
    . "💰 الأرباح (كوينز مستخدمة): {$ostats['total_revenue']}\n"
    . "⏳ قيد الانتظار: {$ostats['by_status']['pending']}\n"
    . "⚙️ قيد التنفيذ: {$ostats['by_status']['processing']}\n"
    . "✅ مكتملة: {$ostats['by_status']['completed']}\n"
    . "❌ ملغية: {$ostats['by_status']['canceled']}\n"
    . "🔝 أكثر خدمة طلباً: " . ($ostats['top_service'] ?? '—') . " ({$ostats['top_service_count']} طلب)\n\n"
    . wallet_admin_log_text(10);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>$statsText]);
return false;
}
// --- نظام الكوبونات: استخدام كود من طرف المستخدم ---
if($message and preg_match('/^\/coupon\s+(\S+)$/u', $text, $mcoupon)){
$couponCode = $mcoupon[1];
$result = coupon_redeem($couponCode, $from_id);
if(!$result['ok']){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>$result['message']]);
return false;
}
$coupon = $result['coupon'];
if($coupon['type'] == 'charge'){
// كتابة ذرية لبيانات المستخدم لمنع تلف الملف
$userDataPath = "data/$from_id.json";
$cuser = [];
if(file_exists($userDataPath)){
    $raw_cuser = @file_get_contents($userDataPath);
    $cuser = $raw_cuser ? (json_decode($raw_cuser, true) ?? []) : [];
}
$coinNow = (float)($cuser["userfild"]["$from_id"]["coin"] ?? 0);
$coinAfter = $coinNow + (float)$coupon['value'];
$cuser["userfild"]["$from_id"]["coin"] = "$coinAfter";
$tmpUser = $userDataPath . '.tmp.' . getmypid();
file_put_contents($tmpUser, json_encode($cuser, JSON_UNESCAPED_UNICODE));
rename($tmpUser, $userDataPath);
wallet_log($from_id, 'charge', $coupon['value'], "شحن بكوبون: $couponCode", ['balance_after' => $coinAfter]);
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"✅ تم شحن {$coupon['value']} $cdiamlanoo باستخدام الكود $couponCode"]);
}elseif($coupon['type'] == 'discount'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"✅ تم تفعيل خصم بقيمة {$coupon['value']}% باستخدام الكود $couponCode\nسيتم تطبيقه على عمليتك القادمة حسب نوع الخدمة."]);
}
return false;
}
// --- نظام الكوبونات: إنشاء كود جديد (للأدمن فقط) ---
// الصيغة: /createcoupon CODE charge|discount VALUE [MAX_USES]
if($message and in_array($from_id,$sudo) and preg_match('/^\/createcoupon\s+(\S+)\s+(charge|discount)\s+([0-9.]+)(?:\s+([0-9]+))?$/u', $text, $mcreate)){
$ccode  = $mcreate[1];
$ctype  = $mcreate[2];
$cvalue = (float)$mcreate[3];
$cmax   = isset($mcreate[4]) ? (int)$mcreate[4] : 0;
// التحقق من صحة المدخلات قبل الإنشاء
$newCoupon = coupon_create($ccode, $ctype, $cvalue, $cmax);
if ($newCoupon === null) {
    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "❌ بيانات الكوبون غير صحيحة\nتأكد أن الكود يحتوي على حروف وأرقام فقط وأن القيمة أكبر من صفر"]);
} else {
    bot('sendMessage', ['chat_id' => $chat_id, 'text' => "✅ تم إنشاء كوبون جديد\n\nالكود: ".strtoupper($ccode)."\nالنوع: $ctype\nالقيمة: $cvalue\nالحد الأقصى للاستخدام: ".($cmax > 0 ? $cmax : 'غير محدود')]);
}
return false;
}
// --- نظام الكوبونات: تعطيل كود (للأدمن فقط) ---
// الصيغة: /disablecoupon CODE
if($message and in_array($from_id,$sudo) and preg_match('/^\/disablecoupon\s+(\S+)$/u', $text, $mdis)){
$disResult = coupon_disable($mdis[1]);
bot('sendMessage', ['chat_id' => $chat_id, 'text' => $disResult
    ? "✅ تم تعطيل الكوبون: ".strtoupper($mdis[1])
    : "❌ الكوبون غير موجود: ".strtoupper($mdis[1])]);
return false;
}
if($message){
$false="";
if($allch!="✅"){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$orothe= $infosudo["info"]["channel"];
$keyboard["inline_keyboard"]=[];
foreach($orothe as $co=>$s ){
$namechannel= $s["name"];
$st= $s["st"];
$userchannel=str_replace('@','', $s["user"]);
if($namechannel!=null){
$stuts = getmember($VISCODEV,$co,$from_id);
if($stuts=="no"){
if($st=="عامة"){
$url="t.me/$userchannel";
$tt=$s["user"];
}else{
$url =$s["user"];
$tt=$s["user"];
}
if($silk=="✅"){
	$keyboard["inline_keyboard"][] = [['text'=>$namechannel,'url'=>$url]];
}else{
$txt=$txt."\n".$tt;
}
$false="yes";
}}}
$reply_markup=json_encode($keyboard);
if($false=="yes"){
	bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"$klish_sil
$txt",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>$reply_markup,
]);
return $false;}
}else{
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$orothe= $infosudo["info"]["channel"];
foreach($orothe as $co=>$s ){
$keyboard["inline_keyboard"]=[];
$namechannel= $s["name"];
$st= $s["st"];
$userchannel=str_replace('@','', $s["user"]);
if($namechannel!=null){
$stuts = getmember($VISCODEV,$co,$from_id);
if($stuts=="no"){
if($st=="عامة"){
$url="t.me/$userchannel";
$tt=$s["user"];
}else{
$url =$s["user"];
$tt=$s["user"];
}
if($silk=="✅"){
	$keyboard["inline_keyboard"][] = [['text'=>$namechannel,'url'=>$url]];
}
#$reply_markup=json_encode($keyboard);
$j = file_get_contents('edid/IDadd.txt');
$arr = explode("\n", $j);
if(in_array($chat_id, $arr)){
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"$klish_sil
$tt",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode($keyboard),
]);return $false;
}else{
$start2 = str_replace("/start ","",$text);
file_put_contents("edid/amr/$chat_id.txt","$start2");
file_put_contents("edid/IDadd.txt","$chat_id\n",FILE_APPEND);
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"$klish_sil
$tt",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode($keyboard),
]);return $false;}}}}}}


if($update->callback_query){
$data = $update->callback_query->data;
$chat_id = $update->callback_query->message->chat->id;
$title = $update->callback_query->message->chat->title;
$message_id = $update->callback_query->message->message_id;
$name = $update->callback_query->from->first_name ?? '';
$user = strtolower($update->callback_query->from->username ?? '');
$from_id = $update->callback_query->from->id ?? 0;
}
// متغيرات مُعرَّفة في بداية الملف 
// callback_query مُعرَّف في بداية الملف
// inline_query مُعرَّف في بداية الملف
$baageel = file_exists("baageel.txt") ? file_get_contents("baageel.txt") : "✅";
  if($data ==  'amruu' && $baageel == "❎"){
bot('EditMessageText',[
'chat_id'=>$chat_id, 
   'message_id'=>$message_id,
'text'=>"
*• اهلا بك في لوحه الأدمن الخاصه بالبوت 🤖*

- يمكنك التحكم في البوت الخاص بك من هنا
~~~~~~~~~~~~~~~~~

*- عليك تفعيل الانلاين لكي يعمل البوت بشكل صحيح* [اضغط هنا لمعرفه كيف تفعل الانلاين](https://t.me/ViSCo/7820)
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
    'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"عمل البوت : ✅",'callback_data'=>"amruu"],['text'=>"اشعار الدخول : $tnbih",'callback_data'=>"tnbih"]],
[['text'=>"الردود",'callback_data'=>"redd"],['text'=>"تعديل الازرار",'callback_data'=>"azraramr"],['text'=>"توجيه الرسائل  ",'callback_data'=>"FAFAF"]],
[['text'=>"رساله الترحيب (start)",'callback_data'=>"start"]],
[['text'=>"قسم الاشتراك الاجباري",'callback_data'=>"agbary"],['text'=>"قسم الادمنية ",'callback_data'=>"admins"]], 
[['text'=>"قسم الاذاعة ",'callback_data'=>"bbcybhu"],['text'=>"قسم الاحصائيات ",'callback_data'=>"sendmgddyessage"]],
[['text'=>"• اعدادات البوت •",'callback_data'=>"sitingbots"]],
]
])
]);
file_put_contents("baageel.txt","✅");
}
if($data ==  'amruu' && $baageel == "✅"){
bot('EditMessageText',[
'chat_id'=>$chat_id, 
   'message_id'=>$message_id,
'text'=>"
*• اهلا بك في لوحه الأدمن الخاصه بالبوت 🤖*

- يمكنك التحكم في البوت الخاص بك من هنا
~~~~~~~~~~~~~~~~~

*- عليك تفعيل الانلاين لكي يعمل البوت بشكل صحيح* [اضغط هنا لمعرفه كيف تفعل الانلاين](https://t.me/ViSCo/7820)
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
    'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"عمل البوت : ❎",'callback_data'=>"amruu"],['text'=>"اشعار الدخول : $tnbih",'callback_data'=>"tnbih"]],
[['text'=>"الردود",'callback_data'=>"redd"],['text'=>"تعديل الازرار",'callback_data'=>"azraramr"],['text'=>"توجيه الرسائل  ",'callback_data'=>"FAFAF"]],
[['text'=>"رساله الترحيب (start)",'callback_data'=>"start"]],
[['text'=>"قسم الاشتراك الاجباري",'callback_data'=>"agbary"],['text'=>"قسم الادمنية ",'callback_data'=>"admins"]], 
[['text'=>"قسم الاذاعة ",'callback_data'=>"bbcybhu"],['text'=>"قسم الاحصائيات ",'callback_data'=>"sendmgddyessage"]],
[['text'=>"• اعدادات البوت •",'callback_data'=>"sitingbots"]],
]
])
]);
file_put_contents("baageel.txt","❎");
}
if($data == "ban"){
$infosudo["info"]["amr"]="ban";
file_put_contents("sudo.json", json_encode($infosudo));
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'text'=>"- قم بارسال أيدي العضو لحظره",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[

[['text'=>"- الغاء  ",'callback_data'=>"home"]],
]
])
]);
}
if($text  and $text !="/start" and $infosudo["info"]["amr"]=="ban" and in_array($from_id,$sudo) and is_numeric($text)){
if(!in_array($text,$ban)){
file_put_contents("sudo/ban.txt","$text\n",FILE_APPEND);
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"- ✅ تم حظر العضو بنجاح 
$text",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"- عودة  ",'callback_data'=>"home"]],
]
])
]);
bot('sendmessage',[
'chat_id'=>$text, 
'text'=>"❌ لقد قام الادمن بحظرك من استخدام البوت",
]);
}else{
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"🚫 العضو محظور مسبقاً",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"- عودة  ",'callback_data'=>"home"]],
]
])
]);
}
$infosudo["info"]["amr"]="null";
file_put_contents("sudo.json", json_encode($infosudo));
}
#الغاء الحظر
if($data == "unban"){
$infosudo["info"]["amr"]="unban";
file_put_contents("sudo.json", json_encode($infosudo));
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'text'=>"- قم بارسال أيدي العضو للإلغاء الحظر عنه",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"- الغاء  ",'callback_data'=>"home"]],
]
])
]);
}

if($text  and $text !="/start" and $infosudo["info"]["amr"]=="unban" and in_array($from_id,$sudo) and is_numeric($text)){
if(in_array($text,$ban)){
$str=file_get_contents("sudo/ban.txt");
$str=str_replace("$text\n",'',$str);
file_put_contents("sudo/ban.txt",$str);
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"- ✅ تم الغاء حظر العضو بنجاح 
$text",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"- عودة  ",'callback_data'=>"home"]],
]
])
]);
bot('sendmessage',[
'chat_id'=>$text, 
'text'=>"✅ لقد قام الادمن بالغاء الحظر عنك  .",
]);
}else{
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"🚫 العضو ليسِ محظور مسبقاً",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"- عودة  ",'callback_data'=>"home"]],
]
])
]);
}
$infosudo["info"]["amr"]="null";
file_put_contents("sudo.json", json_encode($infosudo));
}

if($data == "unbanall"){
if($countban>0){
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'text'=>"- ✅ تم مسح قائمة المحظورين بنجاح ",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[

[['text'=>"- الغاء  ",'callback_data'=>"home"]],
]
])
]);
}else{
bot('answercallbackquery',[
        'callback_query_id'=>$update->callback_query->id,
        'text'=>"🚫 ليس لديك اعضاء محظورين ",
        'show_alert'=>true
        ]);

}
}
if($data == "klish_sil"){
$infosudo["info"]["amr"]="klish_sil";
file_put_contents("sudo.json", json_encode($infosudo));
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'text'=>"- قم بارسال كليشة الاشتراك الاجباريي 
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[

[['text'=>"- الغاء  ",'callback_data'=>"home"]],
]
])
]);

}
if($text  and $text !="/start" and $infosudo["info"]["amr"]=="klish_sil" and in_array($from_id,$sudo)){
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"- ✅ تم حفظ كليشة الاشتراك الاجباري 
-الكليشة : 
$text ",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[

[['text'=>"- عودة  ",'callback_data'=>"home"]],
]
])
]);
$infosudo["info"]["amr"]="null";
$infosudo["info"]["klish_sil"]="$text";
file_put_contents("sudo.json", json_encode($infosudo));
}
if($data == "addchannel"){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$orothe= $infosudo["info"]["channel"];
$count=count($orothe);
if($count<4){
$infosudo["info"]["amr"]="addchannel";
file_put_contents("sudo.json", json_encode($infosudo));
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'message_id'=>$message_id,
'text'=>"- اذا كانت القناة التي تريد اضافتها عامة قم بارسال معرفها .
* اذا كانت خاصة قم بإعادة توجية منشور من القناة إلى هنا .",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]);
}else{
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'text'=>"- 🚫 لا يمكنك اضافة اكثر من  3 قنوات للإشتراك الاجباري ",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]);
}
}
if($text  and $text !="/start" and $infosudo["info"]["amr"]=="addchannel" and in_array($from_id,$sudo) and !$message->forward_from_chat ){
$ch_id = bot_curl('getChat', ['chat_id' => $text])->result->id;
$idchan=$ch_id;
if($ch_id != null){
  $checkadmin = getChatstats($text,$VISCODEV);
  if($checkadmin == true){
$namechannel = bot_curl('getChat', ['chat_id' => $text])->result->title;
$infosudo["info"]["channel"][$ch_id]["st"]="عامة";
$infosudo["info"]["channel"][$ch_id]["user"]="$text";
$infosudo["info"]["channel"][$ch_id]["name"]="$namechannel";
bot('sendMessage',[
'chat_id'=>$chat_id, 
'text'=>"
✅ تم إضافة القناة بنجاح عزيزي الادمن 
info channel 
user : $text 
name : $namechannel
id : $ch_id
 ",
 'reply_markup'=>json_encode(['inline_keyboard'=>[
 [['text'=>"- إضافة قناة آخرى  ",'callback_data'=>"addchannel"]],
 ]])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id, 
'text'=>"❌ البوت ليس ادمن في القناة 
- قم برفع البوت اولا لكي تتمكن من إضافتها 
 ",
'reply_markup'=>json_encode(['inline_keyboard'=>[

 [['text'=>"- إعادة المحاولة   ",'callback_data'=>"addchannel"]],
 ]])
]);
}
}else{
bot('sendMessage',[
'chat_id'=>$chat_id, 
'text'=>"
❌ لم تتم إضافة القناة لا توجد قناة تمتلك هذا المعرف 
$text ",
'reply_markup'=>json_encode(['inline_keyboard'=>[
 [['text'=>"• رجوع •   ",'callback_data'=>"home"]],
 ]])
]);
}
$infosudo["info"]["amr"]="null";
file_put_contents("sudo.json", json_encode($infosudo));
}
if($message->forward_from_chat and $infosudo["info"]["amr"]=="addchannel" and in_array($from_id, $sudo)){
$id_channel= $message->forward_from_chat->id;
if($id_channel != null){
  $checkadmin = getChatstats($id_channel,$VISCODEV);
  if($checkadmin == true){
$namechannel = bot_curl('getChat', ['chat_id' => $id_channel])->result->title;
$infosudo["info"]["channel_id"]="$id_channel";
bot('sendMessage',[
'chat_id'=>$chat_id, 
'text'=>"
✅ تم إضافة القناة بنجاح عزيزي الادمن 
info channel 
user : • قناة خاصة • 
name : $namechannel
id : $id_channel

*يجب عليك ارسال رابط القناة الخاص قم بارسالة الان",
 'reply_markup'=>json_encode(['inline_keyboard'=>[
 [['text'=>"• رجوع • ",'callback_data'=>"addchannel"]],
 ]])
 ]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id, 
'text'=>"❌ البوت ليس ادمن في القناة 
- قم برفع البوت اولا لكي تتمكن من إضافتها 
 ",
'reply_markup'=>json_encode(['inline_keyboard'=>[
 [['text'=>"- إعادة المحاولة   ",'callback_data'=>"addchannel"]],
 ]])
]);
}
}
$infosudo["info"]["amr"]="channel_id";
file_put_contents("sudo.json", json_encode($infosudo));
}
$channel_id=$infosudo["info"]["channel_id"];
if($text  and $text !="/start" and $infosudo["info"]["amr"]=="channel_id" and in_array($from_id,$sudo) and !$message->forward_from_chat ){
  $checkadmin = getChatstats($channel_id,$VISCODEV);
  if($checkadmin == true){
$namechannel = bot_curl('getChat', ['chat_id' => $channel_id])->result->title;
$infosudo["info"]["channel"][$channel_id]["st"]="خاصة";
$infosudo["info"]["channel"][$channel_id]["user"]="$text";
$infosudo["info"]["channel"][$channel_id]["name"]="$namechannel";
bot('sendMessage',[
'chat_id'=>$chat_id, 
'text'=>"
✅ تم إضافة القناة بنجاح عزيزي الادمن 

info channel 
link : $text 
name : $namechannel
id : $channel_id
 ",
 'reply_markup'=>json_encode(['inline_keyboard'=>[

 [['text'=>"- إضافة قناة آخرى  ",'callback_data'=>"addchannel"]],
 ]])
]);
}else{
bot('sendMessage',[
'chat_id'=>$chat_id, 
'text'=>"❌ البوت ليس ادمن في القناة 
- قم برفع البوت اولا لكي تتمكن من إضافتها 
 ",
'reply_markup'=>json_encode(['inline_keyboard'=>[
 [['text'=>"- إعادة المحاولة   ",'callback_data'=>"addchannel"]],
 ]])
]);
}
$infosudo["info"]["amr"]="null";
$infosudo["info"]["channel_id"]="null";
file_put_contents("sudo.json", json_encode($infosudo));
}
if($data == "viwechannel" and in_array($from_id, $sudo)){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$orothe= $infosudo["info"]["channel"];
$keyboard["inline_keyboard"]=[];
foreach($orothe as $co ){
$namechannel= $co["name"];
$st= $co["st"];
$userchannel= $co["user"];
if($namechannel!=null){
	$keyboard["inline_keyboard"][] = [['text'=>$namechannel,'callback_data'=>'null']];
if($st=="خاصة"){
$userchannel="null";
}
$keyboard["inline_keyboard"][] =
[['text'=>$userchannel,'callback_data'=>'cull'],['text'=>$st,'callback_data'=>'null']];
}}
	$keyboard["inline_keyboard"][] = [['text'=>"• رجوع •  ",'callback_data'=>"home"]];
$reply_markup=json_encode($keyboard);
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'message_id'=>$message_id,
'text'=>"- هذة هي قنوات الاشتراك الاجباري الخاصة بك 
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>$reply_markup
]);
}
if($data == "delchannel" and in_array($from_id, $sudo)){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$orothe= $infosudo["info"]["channel"];
$keyboard["inline_keyboard"]=[];
foreach($orothe as $co=>$s ){
$namechannel= $s["name"];
$st= $s["st"];
$userchannel= $s["user"];
if($namechannel!=null){
	$keyboard["inline_keyboard"][] = [['text'=>$namechannel,'callback_data'=>'null']];
if($st=="خاصة"){
$userchannel="null";
}
$keyboard["inline_keyboard"][] =
[['text'=>'🚫 حذف','callback_data'=>'deletchannel '.$co],['text'=>$st,'callback_data'=>'null']];
}}
	$keyboard["inline_keyboard"][] = [['text'=>"• رجوع •  ",'callback_data'=>"home"]];
$reply_markup=json_encode($keyboard);
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'message_id'=>$message_id,
'text'=>"- قم بالضغط على خيار الحذف بالاسفل 
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>$reply_markup
]);
}
if(preg_match('/^(deletchannel) (.*)/s', $data)){
$nn = str_replace('deletchannel ',"",$data);
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'text'=>"- ✅ تم حذف القناة بنجاح 
-id $nn
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[

 [['text'=>"• رجوع •  ",'callback_data'=>"delchannel"]],
 ]])
]);
unset($infosudo["info"]["channel"][$nn]);
file_put_contents("sudo.json", json_encode($infosudo));
}
$cdiamlaadf = file_get_contents("edid/cdiamlaadf.txt") ?: 'نقاط';
$cdiamlao   = $cdiamlaadf . 'ك';
$cdiamlanoo = $cdiamlaadf;
// ملاحظة: cdiamlaadf/cdiamlao/cdiamlanoo مُعرَّفة في بداية الملف
if ($user != null) {
    $user = "@" . $user;
} else {
    $user = "لا يوجد";
}
if($update and !in_array($from_id,$member)){file_put_contents("sudo/member.txt","$from_id\n",FILE_APPEND);
if($from_id != $admin){
if($tnbih == "✅" ){
bot("sendmessage",[
"chat_id"=>$blp,
"text"=>"
*٭ تم دخول شخص  الى البوت الخاص بك* 👾
            *-----------------------*    
_• معلومات العضو الجديد ._

• الاسم :  [$name](tg://user?id=$from_id)
• المعرف : [$user]
• الايدي : `$from_id`
*            ----------------------- *   
• عدد الاعضاء الكلي : *$cunte*
",
'disable_web_page_preview'=>'true',
'parse_mode'=>"markdown",
]);
}}}
if($countban<=0){
$countban="0";
}
if($message and $baageel =="❎" and $chat_id != $admin ){
 bot("sendmessage",[
 "chat_id"=>$chat_id,
 "text"=>"البوت تحت الصيانة حالياً"
 ]);return false;
}
if($text == "/start" && $baageel == "❎" and in_array($from_id,$sudo)){
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"
*• اهلا بك في لوحه الأدمن الخاصه بالبوت 🤖*

- يمكنك التحكم في البوت الخاص بك من هنا
~~~~~~~~~~~~~~~~~

*- عليك تفعيل الانلاين لكي يعمل البوت بشكل صحيح* [اضغط هنا لمعرفه كيف تفعل الانلاين](https://t.me/ViSCo/7820)
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
    'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"عمل البوت : ❎",'callback_data'=>"amruu"],['text'=>"اشعار الدخول : $tnbih",'callback_data'=>"tnbih"]],
[['text'=>"الردود",'callback_data'=>"redd"],['text'=>"تعديل الازرار",'callback_data'=>"azraramr"],['text'=>"توجيه الرسائل ",'callback_data'=>"FAFAF"]],
[['text'=>"رساله الترحيب (start)",'callback_data'=>"start"]],
[['text'=>"قسم الاشتراك الاجباري",'callback_data'=>"agbary"],['text'=>"قسم الادمنية ",'callback_data'=>"admins"]], 
[['text'=>"قسم الاذاعة ",'callback_data'=>"bbcybhu"],['text'=>"قسم الاحصائيات ",'callback_data'=>"sendmgddyessage"]],
[['text'=>"• اعدادات البوت •",'callback_data'=>"sitingbots"]],
]
])
]); 
}
if($text == "/start" && $baageel == null and in_array($from_id,$sudo)){
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"
*• اهلا بك في لوحه الأدمن الخاصه بالبوت 🤖*

- يمكنك التحكم في البوت الخاص بك من هنا
~~~~~~~~~~~~~~~~~

*- عليك تفعيل الانلاين لكي يعمل البوت بشكل صحيح* [اضغط هنا لمعرفه كيف تفعل الانلاين](https://t.me/ViSCo/7820)
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
    'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"عمل البوت : ❎",'callback_data'=>"amruu"],['text'=>"اشعار الدخول : $tnbih",'callback_data'=>"tnbih"]],
[['text'=>"الردود",'callback_data'=>"redd"],['text'=>"تعديل الازرار",'callback_data'=>"azraramr"],['text'=>" توجية الرسائل ",'callback_data'=>"FAFAF"]],
[['text'=>"رساله الترحيب (start)",'callback_data'=>"start"]],
[['text'=>"قسم الاشتراك الاجباري",'callback_data'=>"agbary"],['text'=>"قسم الادمنية ",'callback_data'=>"admins"]], 
[['text'=>"قسم الاذاعة ",'callback_data'=>"bbcybhu"],['text'=>"قسم الاحصائيات ",'callback_data'=>"sendmgddyessage"]],
[['text'=>"• اعدادات البوت •",'callback_data'=>"sitingbots"]],
]
])
]); 
}
if($text == "/start" && $baageel == "✅" and in_array($from_id,$sudo)){
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"
*• اهلا بك في لوحه الأدمن الخاصه بالبوت 🤖*

- يمكنك التحكم في البوت الخاص بك من هنا
~~~~~~~~~~~~~~~~~

*- عليك تفعيل الانلاين لكي يعمل البوت بشكل صحيح* [اضغط هنا لمعرفه كيف تفعل الانلاين](https://t.me/ViSCo/7820)
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
    'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"عمل البوت : ✅",'callback_data'=>"amruu"],['text'=>"اشعار الدخول : $tnbih",'callback_data'=>"tnbih"]],
[['text'=>"الردود",'callback_data'=>"redd"],['text'=>"تعديل الازرار",'callback_data'=>"azraramr"],['text'=>" توجيه الرسائل ",'callback_data'=>"FAFAF"]],
[['text'=>"رساله الترحيب (start)",'callback_data'=>"start"]],
[['text'=>"قسم الاشتراك الاجباري",'callback_data'=>"agbary"],['text'=>"قسم الادمنية ",'callback_data'=>"admins"]], 
[['text'=>"قسم الاذاعة ",'callback_data'=>"bbcybhu"],['text'=>"قسم الاحصائيات ",'callback_data'=>"sendmgddyessage"]],
[['text'=>"• اعدادات البوت •",'callback_data'=>"sitingbots"]],
]
])
]); 
}
if($data == "AHAHAHQH"  && $baageel == "✅"  ){
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'text'=>"
*• اهلا بك في لوحه الأدمن الخاصه بالبوت 🤖*

- يمكنك التحكم في البوت الخاص بك من هنا
~~~~~~~~~~~~~~~~~

*- عليك تفعيل الانلاين لكي يعمل البوت بشكل صحيح* [اضغط هنا لمعرفه كيف تفعل الانلاين](https://t.me/ViSCo/7820)
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
    'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"عمل البوت : ✅",'callback_data'=>"amruu"],['text'=>"اشعار الدخول : $tnbih",'callback_data'=>"tnbih"]],
[['text'=>"الردود",'callback_data'=>"redd"],['text'=>"تعديل الازرار",'callback_data'=>"azraramr"],['text'=>" توجيه الرسائل ",'callback_data'=>"FAFAF"]],
[['text'=>"رساله الترحيب (start)",'callback_data'=>"start"]],
[['text'=>"قسم الاشتراك الاجباري",'callback_data'=>"agbary"],['text'=>"قسم الادمنية ",'callback_data'=>"admins"]], 
[['text'=>"قسم الاذاعة ",'callback_data'=>"bbcybhu"],['text'=>"قسم الاحصائيات ",'callback_data'=>"sendmgddyessage"]],
[['text'=>"• اعدادات البوت •",'callback_data'=>"sitingbots"]],
]
])
]); 
}
function sendwataw($chat_id,$message_id){	
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$fwrmember=$infosudo["info"]["fwrmember"];
$tnbih=$infosudo["info"]["tnbih"];
$silk=$infosudo["info"]["silk"];
$allch=$infosudo["info"]["allch"];
$member = explode("\n",file_get_contents("sudo/member.txt"));
$baageel = file_exists("baageel.txt") ? file_get_contents("baageel.txt") : "✅";
$cunte = count($member)-1;
$ban = explode("\n",file_get_contents("sudo/ban.txt"));
$countban = count($ban)-1;
if($countban<=0){
$countban="0";
}	
bot('EditMessageText',[
'chat_id'=>$chat_id, 
     'message_id'=>$message_id,
'text'=>"
*• اهلا بك في لوحه الأدمن الخاصه بالبوت 🤖*

- يمكنك التحكم في البوت الخاص بك من هنا
~~~~~~~~~~~~~~~~~

*- عليك تفعيل الانلاين لكي يعمل البوت بشكل صحيح* [اضغط هنا لمعرفه كيف تفعل الانلاين](https://t.me/ViSCo/7820)
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
    'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"عمل البوت : $baageel",'callback_data'=>"amruu"],['text'=>"اشعار الدخول : $tnbih",'callback_data'=>"tnbih"]],
[['text'=>"الردود",'callback_data'=>"redd"],['text'=>"تعديل الازرار",'callback_data'=>"azraramr"],['text'=>" توجية الرسائل ",'callback_data'=>"FAFAF"]],
[['text'=>"رساله الترحيب (start)",'callback_data'=>"start"]],
[['text'=>"قسم الاشتراك الاجباري",'callback_data'=>"agbary"],['text'=>"قسم الادمنية ",'callback_data'=>"admins"]], 
[['text'=>"قسم الاذاعة ",'callback_data'=>"bbcybhu"],['text'=>"قسم الاحصائيات ",'callback_data'=>"sendmgddyessage"]],
[['text'=>"• اعدادات البوت •",'callback_data'=>"sitingbots"]],
]
])
]);
}
if($data == "home" and in_array($from_id,$sudo)){
$infosudo["info"]["amr"]="null";
file_put_contents("sudo.json", json_encode($infosudo));
sendwataw($chat_id,$message_id);
}
if($data == "AHAHAHQH"  && $baageel == "❎"  ){
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'message_id'=>$message_id,
'text'=>"
*• اهلا بك في لوحه الأدمن الخاصه بالبوت 🤖*

- يمكنك التحكم في البوت الخاص بك من هنا
~~~~~~~~~~~~~~~~~

*- عليك تفعيل الانلاين لكي يعمل البوت بشكل صحيح* [اضغط هنا لمعرفه كيف تفعل الانلاين](https://t.me/ViSCo/7820)
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
    'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"عمل البوت : ❎",'callback_data'=>"amruu"],['text'=>"اشعار الدخول : $tnbih",'callback_data'=>"tnbih"]],
[['text'=>"الردود",'callback_data'=>"redd"],['text'=>"تعديل الازرار",'callback_data'=>"azraramr"],['text'=>" توجيه الرسائل ",'callback_data'=>"FAFAF"]],
[['text'=>"رساله الترحيب (start)",'callback_data'=>"start"]],
[['text'=>"قسم الاشتراك الاجباري",'callback_data'=>"agbary"],['text'=>"قسم الادمنية ",'callback_data'=>"admins"]], 
[['text'=>"قسم الاذاعة ",'callback_data'=>"bbcybhu"],['text'=>"قسم الاحصائيات ",'callback_data'=>"sendmgddyessage"]],
[['text'=>"• اعدادات البوت •",'callback_data'=>"sitingbots"]],
]
])
]); 
}
// متغيرات مُعرَّفة في بداية الملف 
// callback_query مُعرَّف في بداية الملف
// inline_query مُعرَّف في بداية الملف
$nambot = file_exists("edid/nambot.txt") ? file_get_contents("edid/nambot.txt") : "DomKom";
$start = file_exists("start.txt") ? file_get_contents("start.txt") : null;
if($start==null){
$start="✰︙ مرحبا بك في بوت $nambot ✨ اقوى بوت رشق على تيليجرام 
✰] $cdiamlao : #points 
✰] ايديك : #id";
}
$bajabiabi = file_get_contents("bajabiabi.txt");
  if($data ==  'start'  && $bajabiabi == null){
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
*• مرحبا بك في قسم رساله الترحيب (*/start*)* 🌾
- ستظهر هذه الرساله عند ارسال *(*/start*)* الى البوت الخاص بك 

- ارساله الحاليه : `$start`
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"الاختصارات",'callback_data'=>"comm"]],
[['text'=>" الازرار الشفافة   ",'callback_data'=>"azraramr"]],
[['text'=>" تعين الرساله  ",'callback_data'=>"VFTGKKCSS"],['text'=>" مسح الرساله   ",'callback_data'=>"dcnhrc"]],
[['text'=>" رد على الرسائل : ✅  ",'callback_data'=>"bajnobabiab"]],
[['text'=>" • رجوع •  ",'callback_data'=>"home"]],
]])
]);
}
  if($data ==  'bysajabiab' ){
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
*• مرحبا بك في قسم رساله الترحيب (*/start*)* 🌾
- ستظهر هذه الرساله عند ارسال *(*/start*)* الى البوت الخاص بك 

- ارساله الحاليه : `$start`
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"الاختصارات",'callback_data'=>"comm"]],
[['text'=>" الازرار الشفافة   ",'callback_data'=>"azraramr"]],
[['text'=>" تعين الرساله  ",'callback_data'=>"VFTGKKCSS"],['text'=>" مسح الرساله   ",'callback_data'=>"dcnhrc"]],
[['text'=>" رد على الرسائل : ✅  ",'callback_data'=>"bajnobabiab"]],
[['text'=>" • رجوع •  ",'callback_data'=>"home"]],
]])
]);
file_put_contents("bajabiabi $bajabiabi.txt","ys");
}
  if($data ==  'start'  && $bajabiabi == "no"){
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
*• مرحبا بك في قسم رساله الترحيب (*/start*)* 🌾
- ستظهر هذه الرساله عند ارسال *(*/start*)* الى البوت الخاص بك 

- ارساله الحاليه : `$start`
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"الاختصارات",'callback_data'=>"comm"]],
[['text'=>" الازرار الشفافة   ",'callback_data'=>"azraramr"]],
[['text'=>" تعين الرساله  ",'callback_data'=>"VFTGKKCSS"],['text'=>" مسح الرساله   ",'callback_data'=>"dcnhrc"]],
[['text'=>" رد على الرسائل : ❎  ",'callback_data'=>"bysajabiab"]],
[['text'=>" • رجوع •  ",'callback_data'=>"home"]],
]])
]);
}
  if($data ==  'start'  && $bajabiabi == "ys"){
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
*• مرحبا بك في قسم رساله الترحيب (*/start*)* 🌾
- ستظهر هذه الرساله عند ارسال *(*/start*)* الى البوت الخاص بك 

- ارساله الحاليه : `$start`
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"الاختصارات",'callback_data'=>"comm"]],
[['text'=>"الاختصارات",'callback_data'=>"comm"]],
[['text'=>" الازرار الشفافة   ",'callback_data'=>"azraramr"]],
[['text'=>" تعين الرساله  ",'callback_data'=>"VFTGKKCSS"],['text'=>" مسح الرساله   ",'callback_data'=>"dcnhrc"]],
[['text'=>" رد على الرسائل : ✅  ",'callback_data'=>"bajabiab"]],
[['text'=>" • رجوع •  ",'callback_data'=>"home"]],
]])
]);
}
  if($data ==  'bajnobabiab' ){
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
*• مرحبا بك في قسم رساله الترحيب (*/start*)* 🌾
- ستظهر هذه الرساله عند ارسال *(*/start*)* الى البوت الخاص بك 

- ارساله الحاليه : `$start`
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"الاختصارات",'callback_data'=>"comm"]],
[['text'=>" الازرار الشفافة   ",'callback_data'=>"azraramr"]],
[['text'=>" تعين الرساله  ",'callback_data'=>"VFTGKKCSS"],['text'=>" مسح الرساله   ",'callback_data'=>"dcnhrc"]],
[['text'=>" رد على الرسائل : ❎  ",'callback_data'=>"bysajabiab"]],
[['text'=>" • رجوع •  ",'callback_data'=>"home"]],
]])
]);
file_put_contents("bajabiabi $bajabiabi.txt","no");
}
if($data == "admins" and $chat_id != $admin){
bot('EditMessageText',[
'chat_id'=>$chat_id, 
   'message_id'=>$message_id,
'text'=>"*• لا يمكنك الدخول الى هذه القسم !* ً",
'parse_mode'=>'markdown',
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]); return false;}
$comm = json_decode(file_get_contents("comm.json"),true);
if($data == 'comm'){
 if(in_array($chat_id,$comm['comm']['admins']) or $chat_id == $admin){
$reply_markup = [];
  foreach($comm['com'] as $code => $sale){
   $reply_markup['inline_keyboard'][] = [['text'=>$sale['com1'],'callback_data'=>"s"],['text'=>'🗑️','callback_data'=>'dellll×'.$code]];
  }
  $reply_markup['inline_keyboard'][] = [['text'=>'مسح جميع الاختصارات','callback_data'=>'deleda'],['text'=>'اضافه اختصار','callback_data'=>'adddcd']];
  $reply_markup['inline_keyboard'][] = [['text'=>'رجوع','callback_data'=>'home']];
$json = json_encode($reply_markup);
  bot('editMessageText',[
    'chat_id'=>$chat_id,
    'message_id'=>$message_id,
    'text'=>'*• مرحبا بك في قسم الاختصارات ✨*

- يمكنك وضع اوامر مختصره في البوت من خلال هاذا القسم',
'parse_mode'=>'markdown',
    'reply_markup'=>$json
  ]);
  exit;
 }
 }
  $ex = explode('×',$data);
 if($ex[0] == "dellll"){
 if(in_array($chat_id,$comm['comm']['admins']) or $chat_id == $admin){
  if($comm['com'][$ex[1]] != null){
  unset($comm['com'][$ex[1]]);
  $comm['modee'] = null;
  file_put_contents('comm.json', json_encode($comm));
$comm = json_decode(file_get_contents('comm.json'),true);
$reply_markup = [];
  foreach($comm['com'] as $code => $sale){
   $reply_markup['inline_keyboard'][] = [['text'=>$sale['com1'],'callback_data'=>"s"],['text'=>'🗑️','callback_data'=>'dellll×'.$code]];
  }
  $reply_markup['inline_keyboard'][] = [['text'=>'مسح جميع الاختصارات','callback_data'=>'deleda'],['text'=>'اضافه اختصار','callback_data'=>'adddcd']];
  $reply_markup['inline_keyboard'][] = [['text'=>'رجوع','callback_data'=>'home']];
$json = json_encode($reply_markup);
bot('setmyCommands');
  bot('editMessageText',[
    'chat_id'=>$chat_id,
    'message_id'=>$message_id,
    'text'=>'*• مرحبا بك في قسم الاختصارات ✨*

- يمكنك وضع اوامر مختصره في البوت من خلال هاذا القسم',
'parse_mode'=>'markdown',
    'reply_markup'=>$json,
  ]);
  $delatb = $sale['com1'];
 bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"تم مسح الاختصار : $delatb",
'show_alert'=>true,
  ]);
 }else{
 $reply_markup = [];
foreach($comm['com'] as $code => $sale){
   $reply_markup['inline_keyboard'][] = [['text'=>$sale['com1'],'callback_data'=>"s"],['text'=>'🗑️','callback_data'=>'dellll×'.$code]];
  }
  $reply_markup['inline_keyboard'][] = [['text'=>'مسح جميع الاختصارات','callback_data'=>'deleda'],['text'=>'اضافه اختصار','callback_data'=>'adddcd']];
  $reply_markup['inline_keyboard'][] = [['text'=>'رجوع','callback_data'=>'home']];
$json = json_encode($reply_markup);
  bot('editMessageText',[
    'chat_id'=>$chat_id,
    'message_id'=>$message_id,
    'text'=>'عذرا الاختصار ليس موجود',
    'reply_markup'=>$json
  ]);
 }
 }
 }
  if($data == 'adddcd'){
  bot('editMessageText',[
    'chat_id'=>$chat_id,
    'message_id'=>$message_id,
    'text'=>'*• ارسل الاختصار الان بالشكل التالي : *
`start - رساله البدء`',
'parse_mode'=>'markdown',
    'reply_markup'=>json_encode([
     'inline_keyboard'=>[
      [['text'=>'رجوع','callback_data'=>'home']]
      ]
    ])
  ]);
  $comm['modee'] = 'add';
  file_put_contents('comm.json', json_encode($comm));
  exit;
 }
 $ex2 = explode(' - ',$text);
 if($text != '/start' and $text != null and $comm['modee'] == 'add'){
 if(in_array($chat_id,$comm['comm']['admins']) or $chat_id == $admin){
$code = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz12345689807'),1,7);
$set = $text;
$ex = explode("\n",$set);
foreach($ex as $ex1){
$ex2 = explode(" - ",$ex1);
$Commands[] = ['command'=>$ex2[0],'description'=>$ex2[1]];
}
bot('setMyCommands',[
'commands'=>json_encode($Commands)
]);
  bot('sendMessage',[
   'chat_id'=>$chat_id,
   'text'=>'• تم الحفظ !',
'reply_to_message_id'=>$message->message_id, 
    'reply_markup'=>json_encode([
     'inline_keyboard'=>[
      [['text'=>'رجوع','callback_data'=>'home']]
      ]
    ])
  ]);
  $comm['com'][$code]['com1'] = $ex2[0];
  $comm['com'][$code]['com2'] = $ex2[1];
  unset($comm['modee']);
  file_put_contents('comm.json', json_encode($comm));
  exit;
 }
 }
if($data == "deleda"){
bot('setmyCommands');
bot('EditMessageText',[
'chat_id'=>$chat_id, 
   'message_id'=>$message_id,
'text'=>"*• مرحبا بك في قسم الاختصارات ✨*

- يمكنك وضع اوامر مختصره في البوت من خلال هاذا القسم",
'parse_mode'=>'markdown',
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>'مسح جميع الاختصارات','callback_data'=>'deleda'],['text'=>'اضافه اختصار','callback_data'=>'adddcd']],
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]); 
unlink("comm.json");
 bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"تم مسح جميع الاختصارات ⏱",
'show_alert'=>true,
  ]);return false;}
if($data == "admins" and $from_id ==$admin){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$orothe= $infosudo["info"]["admins"];
$keyboard["inline_keyboard"]=[];
foreach($orothe as $co=>$sss ){
if($co!=null and $co!=$admin ){
$keyboard["inline_keyboard"][] =
[['text'=>"$sss",'url'=>'tg://openmessage?user_id=$sss'],['text'=>' 🗑','callback_data'=>'deleteadmin '.$co.'#'.$sss]];
}}
$keyboard["inline_keyboard"][] = [['text'=>"• اضافه ادمن جديد •  ",'callback_data'=>"addadmin"]];
$keyboard["inline_keyboard"][] = [['text'=>"• رجوع •  ",'callback_data'=>"home"]];
$reply_markup=json_encode($keyboard);
bot('EditMessageText',[
'chat_id'=>$chat_id, 
   'message_id'=>$message_id,
'text'=>"
*• مرحبا بك في الادمنيه 👮‍♀️*
- يمكنك رفع 5 ادمنيه في البوت او حذفهم 

- يمكن للادمنيه تحكم في لوحه البوت مثلك ولا يمكنهم رفع ادمنيه او استلام رسائل الموجهة او سايت او تواصل .",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>$reply_markup
]);
}
if($data == "addadmin" and $from_id ==$admin){
$infosudo["info"]["amr"]="addadmin";
file_put_contents("sudo.json", json_encode($infosudo));
bot('EditMessageText',[
'chat_id'=>$chat_id, 
     'message_id'=>$message_id,
'text'=>"• ارسل ايدي الشخص الان او معرف الشخص ! ",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]);
}
if($text  and $text !="/start" and $infosudo["info"]["amr"]=="addadmin" and $from_id ==$admin and is_numeric($text)){
if(!in_array($text,$sudo)){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$orothe= $infosudo["info"]["channel"];
$count=count($orothe);
if($count<6){
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"• تم الاضافه الى الادمنيه بنجاح ",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"admins"]],
]
])
]);
$infosudo["info"]["admins"][]="$text";
}else{
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"🚫 لايمكنك اضافة اكثر من 5 ادمنية ً",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"admins"]],
]
])
]);
}
}else{
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"- ⚠ الادمن مضاف مسبقاً",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"admins"]],
]
])
]);
}
$infosudo["info"]["amr"]="null";
file_put_contents("sudo.json", json_encode($infosudo));
}
if(preg_match('/^(deleteadmin) (.*)/s', $data)){
$nn = str_replace('deleteadmin ',"",$data);
$ex=explode('#',$nn);
$id=$ex[1];
$n=$ex[0];
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$orothe= $infosudo["info"]["admins"];
$keyboard["inline_keyboard"]=[];
foreach($orothe as $co=>$sss ){
if($co!=null and $co!=$admin ){
$keyboard["inline_keyboard"][] =
[['text'=>"$sss",'url'=>'tg://openmessage?user_id=$sss'],['text'=>' 🗑','callback_data'=>'deleteadmin '.$co.'#'.$sss]];
}}
	$keyboard["inline_keyboard"][] = [['text'=>"• اضافه ادمن جديد •  ",'callback_data'=>"addadmin"]];
	$keyboard["inline_keyboard"][] = [['text'=>"• رجوع •  ",'callback_data'=>"home"]];
$reply_markup=json_encode($keyboard);
bot('EditMessageText',[
'chat_id'=>$chat_id, 
   'message_id'=>$message_id,
'text'=>"
*• مرحبا بك في الادمنيه 👮‍♀️*
- يمكنك رفع 5 ادمنيه في البوت او حذفهم 

- يمكن للادمنيه تحكم في لوحه البوت مثلك ولا يمكنهم رفع ادمنيه او استلام رسائل الموجهة او سايت او تواصل .
",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>$reply_markup
 
]);
unset($infosudo["info"]["admins"][$n]);
file_put_contents("sudo.json", json_encode($infosudo));
}
if($data == "azraramr") {
    $lines = file('edid/aklamrnmo.txt', FILE_IGNORE_NEW_LINES);
    $key = ['inline_keyboard' => []];
    $shown_texts = [];
    foreach ($lines as $line) {
        $namezer = $line;
        $deletzzr = file_get_contents("edid/aklamrnmo.txt");
        if ($deletzzr == null) {
            $deletzzr = "";
        } else {
            $deletzzr = "🗑";
            if (!in_array($namezer, $shown_texts)) {
                $key['inline_keyboard'][] = [['text' => "$namezer", 'callback_data' => "null"], ['text' => "$deletzzr", 'callback_data' => "deletzernm#$namezer"]];
                $shown_texts[] = $namezer;
            }
        }
    }
    $key['inline_keyboard'][] = [['text' => "تعديل اسماء الازرار", 'callback_data' => "serzer"]];
    $key['inline_keyboard'][] = [['text' => "قسم الازرار الشفافة", 'callback_data' => "zrar"]];
    $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "home"]];
    bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "*• مرحبا بك في قسم تعديل ازرار البوت 👋🏼*
        
- يمكنك اضافة تعديلات للازرار وحذفها 
*ـ اضغط على الزر لعرض محتوي قسم الزر .*",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode($key),
    ]);
}
$ex = explode("#",$data);
if($ex[0] == "deletzernm"){
$file_path = "edid/aklamrnmo.txt";
$deleted_text = "$ex[1]";
$file_content = file_get_contents($file_path);
$file_content = preg_replace("/^.*?$deleted_text.*?\R?/m", "", $file_content);
file_put_contents($file_path, $file_content);
$lines = file('edid/aklamrnmo.txt', FILE_IGNORE_NEW_LINES);
$key = ['inline_keyboard' => []];
foreach ($lines as $line) {
$namezer = $line;
$i = $line;
$key['inline_keyboard'][] = [['text' => "$namezer", 'callback_data' =>"null"],['text' => "🗑", 'callback_data' => "deletzernm#$namezer"]];
}
$key['inline_keyboard'][] = [['text' => "تعديل اسماء الازرار", 'callback_data' => "serzer"]];
$key['inline_keyboard'][] = [['text' => "قسم الازرار الشفافة", 'callback_data' => "zrar"]];
$key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "home"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*• مرحبا بك في قسم تعديل ازرار البوت 👋🏼*

- يمكنك اضافه تعديلات للازرار وحذفها 
*ـ اضغط على الزر لعرض محتوي قسم الزر .*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($key),]);
$namzeon = file_get_contents("edid/$ex[1].txt");
unlink("edid/$namzeon");
unlink("edid/$ex[1].txt");
}
$aklamrnm1 = file_exists("edid/aklamrnm1.txt") ? file_get_contents("edid/aklamrnm1.txt") : "الخدمات 🗂";
$aklamrnm2 = file_exists("edid/aklamrnm2.txt") ? file_get_contents("edid/aklamrnm2.txt") : "تجميع ✳️";
$aklamrnm3 = file_exists("edid/aklamrnm3.txt") ? file_get_contents("edid/aklamrnm3.txt") : "الحساب 🗃️";
$aklamrnm4 = file_exists("edid/aklamrnm4.txt") ? file_get_contents("edid/aklamrnm4.txt") : "استخدام كود 💳";
$aklamrnm5 = file_exists("edid/aklamrnm5.txt") ? file_get_contents("edid/aklamrnm5.txt") : "تحويل $cdiamlaadf ♻️";
$aklamrnm6 = file_exists("edid/aklamrnm6.txt") ? file_get_contents("edid/aklamrnm6.txt") : "معلومات الطلب 📥";
$aklamrnm7 = file_exists("edid/aklamrnm7.txt") ? file_get_contents("edid/aklamrnm7.txt") : "طلباتي 📮";
$aklamrnm8 = file_exists("edid/aklamrnm8.txt") ? file_get_contents("edid/aklamrnm8.txt") : "قناة البوت 🤍";
$aklamrnm9 = file_exists("edid/aklamrnm9.txt") ? file_get_contents("edid/aklamrnm9.txt") : "شحن $cdiamlaadf 💰";
$aklamrnm10 = file_exists("edid/aklamrnm10.txt") ? file_get_contents("edid/aklamrnm10.txt") : "الشروط 📜";
$aklamrnm11=file_get_contents("edid/aklamrnm11.txt");
if($aklamrnm11==null){
$amrorders = count(file("akl/orders.txt"));
$aklamrnm11="عدد الطلبات : $amrorders ✅";
}
if($data == 'serzer'){
$reply_markup[] =   [['text'=>"$aklamrnm1",'callback_data'=>'serzer1']];
$reply_markup[] =   [['text'=>"$aklamrnm2",'callback_data'=>'serzer2'],['text'=>"$aklamrnm3",'callback_data'=>'serzer3']];
$reply_markup[] =   [['text'=>"$aklamrnm4",'callback_data'=>'serzer4'],['text'=>"$aklamrnm5",'callback_data'=>'serzer5']];
$reply_markup[] =   [['text'=>"$aklamrnm6",'callback_data'=>'serzer6'],['text'=>"$aklamrnm7",'callback_data'=>'serzer7']];
$reply_markup[] =   [['text'=>"$aklamrnm8",'callback_data'=>"serzer8"]];
$reply_markup[] =   [['text'=>"$aklamrnm9",'callback_data'=>'serzer9'],['text'=>"$aklamrnm10",'callback_data'=>'serzer10']];
$reply_markup[] =   [['text'=>"$aklamrnm11",'callback_data'=>'serzer11']];
$reply_markup[] = [['text'=>"رجوع",'callback_data'=>"home"]];
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*مرحبا بك عزيزي 👋🏼*

اختار الزر المراد تغير اسمه من خلال الازرار *التالية*",
'parse_mode'=>"markdown",
 'reply_markup'=>$reply_markup,              
]);
}
if($data == 'serzer1'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer1");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer1" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm1.txt",$text);
file_put_contents("edid/$text.txt",'aklamrnm1.txt');
file_put_contents("edid/aklamrnmo.txt","$text\n",FILE_APPEND);
}
if($data == 'serzer2'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer2");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer2" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm2.txt",$text);
file_put_contents("edid/$text.txt",'aklamrnm2.txt');
file_put_contents("edid/aklamrnmo.txt","$text\n",FILE_APPEND);
}
if($data == 'serzer3'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer3");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer3" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm3.txt",$text);
file_put_contents("edid/$text.txt",'aklamrnm3.txt');
file_put_contents("edid/aklamrnmo.txt","$text\n",FILE_APPEND);
}
if($data == 'serzer4'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer4");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer4" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm4.txt",$text);
}
if($data == 'serzer5'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer5");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer5" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm5.txt",$text);
}
if($data == 'serzer6'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer6");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer6" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm6.txt",$text);
}
if($data == 'serzer7'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer7");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer7" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm7.txt",$text);
}
if($data == 'serzer8'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer8");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer8" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm8.txt",$text);
}
if($data == 'serzer9'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer9");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer9" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm9.txt",$text);
}
if($data == 'serzer10'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer10");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer10" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm10.txt",$text);
}
if($data == 'serzer11'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال اسم الزر الجديد الان*☄",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])]);
file_put_contents("edid/serzeron.txt","serzer11");
}
if($text and file_get_contents("edid/serzeron.txt") == "serzer11" and $text != '/start'){
bot('sendMessage',['chat_id'=>$chat_id,'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",'parse_mode'=>"MARKDOWN",'reply_to_message_id'=>$message->message_id,'reply_markup'=>json_encode(['inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],]])
]);
unlink("edid/serzeron.txt");
file_put_contents("edid/aklamrnm11.txt",$text);
}
$txt = $caption; // caption مُعرَّف في بداية الملف
$text = $message->text;
$chat_id = $message->chat->id;
$from_id = $message->from->id;
$new = $message->new_chat_members;
$message_id = $message->message_id;
$type = $message->chat->type;
$name = $message->from->first_name;
if(isset($update->callback_query)){
$up = $update->callback_query;
$chat_id = $up->message->chat->id;
$from_id = $up->from->id;
$user = $up->from->username;
$name = $up->from->first_name;
$message_id = $up->message->message_id;
$data = $up->data ?? '';
$namebot = "@".$getbot->username;
}
// متغيرات مُعرَّفة في بداية الملف 
// callback_query مُعرَّف في بداية الملف
 $button  = json_decode(file_get_contents('button.json'),true);
 $sttings = json_decode(file_get_contents("CEPO/hiim.json"),1);
function save($array){
file_put_contents('button.json', json_encode($array,128|34|256));
}
if($user != null){
$use = "@$user";
}else
if($user == null){
$use = "لا يوجد معرف";
}
$zerasase = file_get_contents("edid/zerasase.txt");
if($data == "zrar"){
$amrorders = count(file("akl/orders.txt"));
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$reply_markup[] =   [['text'=>"$aklamrnm1",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"$aklamrnm2",'callback_data'=>'null'],['text'=>"$aklamrnm3",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"$aklamrnm4",'callback_data'=>'null'],['text'=>"$aklamrnm5",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"$aklamrnm6",'callback_data'=>'null'],['text'=>"$aklamrnm7",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"$aklamrnm8",'callback_data'=>"null"]];
$reply_markup[] =   [['text'=>"$aklamrnm9",'callback_data'=>'null'],['text'=>"$aklamrnm10",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"$aklamrnm11",'callback_data'=>'null']];
      foreach($button['codzer'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
      foreach($button['buttons'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
foreach($button['links'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
$reply_markup[] = [['text'=>"+",'callback_data'=>"addbtn"]];
$reply_markup[] = [['text'=>"قسم تعديل الازرار",'callback_data'=>"serzer"],['text'=>"الازرار الاساسية : $zerasase",'callback_data'=>"zerasase"]];
$reply_markup[] = [['text'=>"رجوع",'callback_data'=>"home"]];
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*
• مرحبا بك في قسم الازرار الشفافة ✨
*
- يمكنك اضافه ازرار شفافة او حذفها ( لا يمكنك حذف الازرار الاساسية ولاكن يمكنك تعديلها من قسم تعديل الازرار )
",
'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"markdown",
 'reply_markup'=>$reply_markup,
]);
file_put_contents("set.txt",".");
$button['n'] = null;
$button['mode'] = null;
save($button);
  }
if($data ==  'zerasase' and $zerasase =='✅'){
$amrorders = count(file("akl/orders.txt"));
$zerasase = file_get_contents("edid/zerasase.txt");
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$reply_markup[] =   [['text'=>"الخدمات 🗂",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"تجميع ✳️",'callback_data'=>'null'],['text'=>"الحساب 🗃️",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"استخدام كود 💳",'callback_data'=>'null'],['text'=>"تحويل $cdiamlaadf ♻️",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"معلومات الطلب 📥",'callback_data'=>'null'],['text'=>"طلباتي 📮",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"قناة البوت 🤍",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"شحن $cdiamlaadf 💰",'callback_data'=>'null'],['text'=>"الشروط 📜",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"عدد الطلبات : $amrorders ✅",'callback_data'=>'null']];
      foreach($button['codzer'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
      foreach($button['buttons'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
foreach($button['links'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
$reply_markup[] = [['text'=>"+",'callback_data'=>"addbtn"]];
$reply_markup[] = [['text'=>"قسم تعديل الازرار",'callback_data'=>"serzer"],['text'=>"الازرار الاساسية : ❌",'callback_data'=>"zerasase"]];
$reply_markup[] =[['text'=>"رجوع",'callback_data'=>"home"]];
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*
• مرحبا بك في قسم الازرار الشفافة ✨
*
- يمكنك اضافه ازرار شفافة او حذفها ( لا يمكنك حذف الازرار الاساسية ولاكن يمكنك تعديلها من قسم تعديل الازرار )
",
'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"markdown",
 'reply_markup'=>$reply_markup,
]);
file_put_contents("edid/zerasase.txt","❌");
file_put_contents("edid/zerasaseon.txt","❌");
file_put_contents("set.txt",".");
$button['n'] = null;
$button['mode'] = null;
save($button);
  }
if($data ==  'zerasase' and $zerasase =='❌'){
$amrorders = count(file("akl/orders.txt"));
$zerasase = file_get_contents("edid/zerasase.txt");
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$reply_markup[] =   [['text'=>"الخدمات 🗂",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"تجميع ✳️",'callback_data'=>'null'],['text'=>"الحساب 🗃️",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"استخدام كود 💳",'callback_data'=>'null'],['text'=>"تحويل $cdiamlaadf ♻️",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"معلومات الطلب 📥",'callback_data'=>'null'],['text'=>"طلباتي 📮",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"قناة البوت 🤍",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"شحن $cdiamlaadf 💰",'callback_data'=>'null'],['text'=>"الشروط 📜",'callback_data'=>'null']];
$reply_markup[] =   [['text'=>"عدد الطلبات : $amrorders ✅",'callback_data'=>'null']];
      foreach($button['codzer'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
      foreach($button['buttons'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
foreach($button['links'] as $f5f7 => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>'zh|'.$f5f7]];
}
$reply_markup[] = [['text'=>"+",'callback_data'=>"addbtn"]];
$reply_markup[] = [['text'=>"قسم تعديل الازرار",'callback_data'=>"serzer"],['text'=>"الازرار الاساسية : ✅",'callback_data'=>"zerasase"]];
$reply_markup[] =[['text'=>"رجوع",'callback_data'=>"home"]];
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*
• مرحبا بك في قسم الازرار الشفافة ✨
*
- يمكنك اضافه ازرار شفافة او حذفها ( لا يمكنك حذف الازرار الاساسية ولاكن يمكنك تعديلها من قسم تعديل الازرار )
",
'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"markdown",
 'reply_markup'=>$reply_markup,
]);
file_put_contents("edid/zerasase.txt","✅");
file_put_contents("edid/zerasaseon.txt","✅");
file_put_contents("set.txt",".");
$button['n'] = null;
$button['mode'] = null;
save($button);
  }
$zhend = explode("|",$data);
if($data == "zh|$zhend[1]"){
$pri = $button['buttons'][$zhend[1]]['mo'];
$prilinks = $button['links'][$zhend[1]]['mo'];
$prilicodzer = $button['codzer'][$zhend[1]]['mo'];
$name = $button['buttons'][$zhend[1]]['name'];
$namezera = $button['codzer'][$zhend[1]]['name'];
$namelinks = $button['links'][$zhend[1]]['name'];
$Type = $button['buttons'][$zhend[1]]['Type'];
$Tyyrj = $button['codzer'][$zhend[1]]['tymyzr'];
if($Type == "EditMessageText"){
$offer = "تعديل الرساله";
}
if($Type == "sendMessage"){
$offer = "رساله جديده";
}
if($Type == "answercallbackquery"){
$offer = "همسة";
}
if($Tyyrj == "زر مختصر"){
$offer = "زر مختصر";
}
$fro = "محتوى نصي";
if(strpos($prilinks, "http://") === 0 || strpos($prilinks, "https://") === 0){
bot('editMessageText',[
      'chat_id'=>$chat_id,
      'message_id'=>$message_id,
      'text'=>"*• اسم الزر : $namelinks*

- مسار الزر : home

نوع الزر : *رابط (*$prilinks*)*
",
      'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"markdown",
      'disable_web_page_preview'=> true ,
     'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"مسح الزر",'callback_data'=>'delete|'.$zhend[1]]],
[['text'=>"رجوع",'callback_data'=>"zrar"]],
]
])]);exit;}
if(strpos($offer, "زر ") === 0){
bot('editMessageText',[
      'chat_id'=>$chat_id,
      'message_id'=>$message_id,
      'text'=>"*• اسم الزر : $namezera*

- مسار الزر : home

نوع الزر : *(زر مختصر)*
",
      'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"markdown",
      'disable_web_page_preview'=> true ,
     'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"مسح الزر",'callback_data'=>'delete|'.$zhend[1]]],
[['text'=>"رجوع",'callback_data'=>"zrar"]],
]
])]);exit;}
bot('editMessageText',[
      'chat_id'=>$chat_id,
      'message_id'=>$message_id,
      'text'=>"*• اسم الزر : $name*

- مسار الزر : home

- نوع الزر : *$fro* *($pri)*
",
      'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"markdown",
      'disable_web_page_preview'=> true ,
     'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"مسح الزر",'callback_data'=>'delete|'.$zhend[1]]],
[['text'=>"طريقة عرض النص : ".$offer,'callback_data'=>'offer|'.$zhend[1]]],
[['text'=>"رجوع",'callback_data'=>"zrar"]],
]
])]);}
$EditMes=file_get_contents("edid/EditMes.txt");
if($EditMes==null){
$EditMes='EditMes';
}
$zoffer = explode("|",$data);
if($data == "offer|$zoffer[1]"){
$pri = $button['buttons'][$zoffer[1]]['mo'];
$prilinks = $button['links'][$zoffer[1]]['mo'];
$name = $button['buttons'][$zoffer[1]]['name'];
$namelinks = $button['links'][$zoffer[1]]['name'];
$Type = $button['buttons'][$zoffer[1]]['Type'];
$fro = "محتوى نصي";
if($EditMes == "EditMes"){
bot('answerCallbackQuery',[
   'callback_query_id'=>$update->callback_query->id,
        'text'=>'تعديل الرساله',
      ]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*• اسم الزر : $name*

- مسار الزر : home

- نوع الزر : *$fro*


$pri
",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"مسح الزر",'callback_data'=>'delete|'.$zhend[1]]],
[['text'=>"طريقة عرض النص : تعديل الرساله",'callback_data'=>'offer|'.$zhend[1]]],
[['text'=>"رجوع",'callback_data'=>"zrar"]],
]])
]);    
$iu = "EditMessageText";
$button['buttons'][$zoffer[1]]['Type'] = $iu;
file_put_contents('button.json', json_encode($button,128|34|256));
file_put_contents("edid/EditMes.txt","sendMessage");
}else{
if($EditMes == "sendMessage"){
bot('answerCallbackQuery',[
   'callback_query_id'=>$update->callback_query->id,
        'text'=>'رساله جديده',
      ]);
$iu ="sendMessage";
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*• اسم الزر : $name*

- مسار الزر : home

- نوع الزر : *$fro*


$pri
",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"مسح الزر",'callback_data'=>'delete|'.$zhend[1]]],
[['text'=>"طريقة عرض النص : رساله جديده",'callback_data'=>'offer|'.$zhend[1]]],
[['text'=>"رجوع",'callback_data'=>"zrar"]],
]])
]);    
$button['buttons'][$zoffer[1]]['Type'] = $iu;
file_put_contents('button.json', json_encode($button,128|34|256));
file_put_contents("edid/EditMes.txt","answercallbackquery");
}else{
if($EditMes == "answercallbackquery"){
bot('answerCallbackQuery',[
   'callback_query_id'=>$update->callback_query->id,
        'text'=>'همسة',
      ]);
file_put_contents("edid/EditMes.txt","EditMes");
$iu ="answercallbackquery";
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*• اسم الزر : $name*

- مسار الزر : home

- نوع الزر : *$fro*


$pri
",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"مسح الزر",'callback_data'=>'delete|'.$zhend[1]]],
[['text'=>"طريقة عرض النص : همسة",'callback_data'=>'offer|'.$zhend[1]]],
[['text'=>"رجوع",'callback_data'=>"zrar"]],
]])
]);    
$button['buttons'][$zoffer[1]]['Type'] = $iu;
file_put_contents('button.json', json_encode($button,128|34|256));
}}}}
///////حذف الزر////
$zdelete = explode("|",$data);
if($data == "delete|$zdelete[1]"){    
$name = $button['buttons'][$zdelete[1]]['name'];
$namelinks = $button['links'][$zdelete[1]]['name'];
$namezera = $button['codzer'][$zdelete[1]]['name'];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>
"
*• اسم الزر : $name"."$namelinks "."$namezera

- تم مسح الزر
*",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"zrar"]],
]
])
]);
unset($button['links'][$zdelete[1]]);
unset($button['buttons'][$zdelete[1]]);
unset($button['codzer'][$zdelete[1]]);
file_put_contents("button.json",json_encode($button,128|34|256));
}
if($data == "addbtn"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>
"*• ارسل اسم الزر الراد اضافته*",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"zrar"]],
]
])
]);
$button['mode'] = 'add';
save($button,128|34|256);
exit;
}
if($text != '/start' and $text != null and $button['mode'] == 'add'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"*• ارسل الان المحتوى المراد اضافته الى الزر *

- يمكنك ارسل كليشة نصية (يمكنك استخدام الماركداون) 
- يمكنك ارسل رابط مباشر يبدء (https://....) لكي يصبح الزر يحتوي على رابط
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message->message_id,
]);
$zhgo ='co:9u7ehcde55tak3m06
co:644ffh76sin4hf6ntk9b
co:gd53hd48dh6dmds4o
co:b89ckihdrsfdgfngu469
co:bfr68agaddhybvotrk7in
co:nd567bsa5onf90h4h6d
co:che57r7bsa5onmf906d
co:bxry8kcs56bbkahr6ydloj
co:opfrfbsfbbty66jgdfsrd
co:yatuno756dfbddk6srd';
file_put_contents("aeradd.txt","$zhgo");
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"•  يمكنك وضع بعض الاضافات الى كليشه من خلال استخدام الاهاشتاكات التاليه :

1. `#name` : لوضع اسم شخص  
2. `#username` : لوضع اسم مستخدم الشخص مع اضافه @ 
3. `#id` : لوضع ايدي الشخص ",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message->message_id,
]);
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"*• يمكنك استخدام الازرار الاساسية في عمل الزر الجديد *

- لكي تحصل على كود الازرار ارسل '`مشاهدة الازرار`' بالرد على اي كليشة تحتوي على ازرار",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message->message_id,
]);
$button['n'] = $text;
$button['mode'] = 'addm';
save($button,128|34|256);
exit;}
if (strpos($text, "http://") === 0 || strpos($text, "https://") === 0 and $button['mode'] == 'addm'){
$code = $button['n'];
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"*• تم حفظ الزر (رابط)*

- مسار الزر : home
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"zrar"]],
]
])
]);
$button['links'][$code]['name'] = $button['n'];
$button['links'][$code]['mo'] = $text;
$button['links'][$code]['Type'] = "رابط";
$button['n'] = null;
$button['mode'] = null;
save($button,128|34|256);
file_put_contents("sends.txt","");
exit;}
if($text != '/start' and $button['mode'] == 'addm' && $text != 'مشاهدة الازرار'){
$file = 'aeradd.txt';
$fileContent = file_get_contents($file);
if (strpos($fileContent, $text) !== false) {
$code = $button['n'];
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"*• تم حفظ الزر (زر مختصر)*

- مسار الزر : home
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"zrar"]],
]
])
]);
if (strpos($text, 'co:9u7ehcde55tak3m06') !== false) {$datacodamr = "takemember";}
if (strpos($text, 'co:644ffh76sin4hf6ntk9b') !== false) {$datacodamr = "takecoinn";}
if (strpos($text, 'co:gd53hd48dh6dmds4o') !== false) {$datacodamr = "accont";}
if (strpos($text, 'co:b89ckihdrsfdgfngu469') !== false) {$datacodamr = "amr6";}
if (strpos($text, 'co:bfr68agaddhybvotrk7in') !== false) {$datacodamr = "sendcoin";}
if (strpos($text, 'co:nd567bsa5onf90h4h6d') !== false) {$datacodamr = "amr4";}
if (strpos($text, 'co:che57r7bsa5onmf906d') !== false) {$datacodamr = "amr5";}
if (strpos($text, 'co:bxry8kcs56bbkahr6ydloj') !== false) {$datacodamr = "amr2";}
if (strpos($text, 'co:opfrfbsfbbty66jgdfsrd') !== false) {$datacodamr = "amr1";}
if (strpos($text, 'co:yatuno756dfbddk6srd') !== false) {$datacodamr = "null";}
$button['codzer'][$code]['name'] = $button['n'];
$button['codzer'][$code]['mo'] = $datacodamr;
$button['codzer'][$code]['Type'] = "EditMessageText";
$button['codzer'][$code]['tymyzr'] = "زر مختصر";
$button['n'] = null;
$button['mode'] = null;
save($button,128|34|256);
file_put_contents("sends.txt","");
exit;}}
if($text != '/start' and $button['mode'] == 'addm' &&$text !=  'مشاهدة الازرار'){
$code = $button['n'];
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*• تم حفظ الزر (محتوى نصي)*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"zrar"]],
]
])
]);
$button['buttons'][$code]['name'] = $button['n'];
$button['buttons'][$code]['mo'] = $text;
$button['buttons'][$code]['Type'] = "EditMessageText";
$button['n'] = null;
$button['mode'] = null;
save($button,128|34|256);
file_put_contents("sends.txt","");
}
$st = "$start";
$price = $button['buttons'][$data]['mo'];
$price = str_replace('#name', "[$name](tg://user?id=$from_id)",$price);
$price = str_replace('#username', "[$use]",$price);
$price = str_replace('#id', "$from_id",$price);
$name = $button['buttons'][$data]['name'];
$Type = $button['buttons'][$data]['Type'];
if($Type == "EditMessageText"){
$reply_p[] = [['text'=>"رجوع",'callback_data'=>"panel"]];
$reply_p = json_encode(['inline_keyboard'=>$reply_p,]);
}
if($price != null){
if($Type == "answercallbackquery"){
bot('answercallbackquery',[
        'callback_query_id'=>$update->callback_query->id,
            'text' => "$price",
            'show_alert' =>true
        ]);exit;}}
if($price != null){
bot($Type,[
      'chat_id'=>$chat_id,
      'message_id'=>$message_id,
      'text'=>$price,
      'reply_to_message_id'=>$message->message_id,

 'reply_markup'=>$reply_p,
      ]);
  }
date_default_timezone_set("Asia/Riyadh");
$getbot = bot_curl('getMe')->result;
$me = $getbot->username;
$namebot = "@".$getbot->username;
// $update مُعرَّف في بداية الملف
if($update->message){
$message = $update->message;
$message_id = $update->message->message_id;
$username = $message->from->username;
$chat_id = $message->chat->id;
$title = $message->chat->title;
$text = $message->text;
$user = $message->from->username;
$name = $message->from->first_name;
$from_id = $message->from->id;
}
if($update->callback_query){
$data = $update->callback_query->data;
$chat_id = $update->callback_query->message->chat->id;
$title = $update->callback_query->message->chat->title;
$message_id = $update->callback_query->message->message_id;
$name = $update->callback_query->from->first_name ?? '';
$user = strtolower($update->callback_query->from->username ?? '');
$from_id = $update->callback_query->from->id ?? 0;
}
// متغيرات مُعرَّفة في بداية الملف 
// callback_query مُعرَّف في بداية الملف
 $replies  = json_decode(file_get_contents('replies.json'),true);
 $sttings = json_decode(file_get_contents("CEPO/hiim.json"),1);
function save_replies($array){
file_put_contents('replies.json', json_encode($array,128|34|256));
}
if($user != null){
$use = "@$user";
}else
if($user == null){
$use = "لا يوجد معرف";
}
if($data == "redd"){
      foreach($replies['replies'] as $f5f7 => $repliess){
$reply_markup[] = [['text'=>$repliess['name'],'callback_data'=>"null"],['text'=>"🗑",'callback_data'=>'add_red|'.$f5f7]];
}
foreach($replies['links'] as $f5f7 => $repliess){
$reply_markup[] = [['text'=>$repliess['name'],'url'=>$f5f7]];
}
$reply_markup[] = [['text'=>"+",'callback_data'=>"add_red"]];
$reply_markup[] =[['text'=>"• رجوع •",'callback_data'=>"home"]];
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
*• مرحبا بك في قسم الردود 👮‍♀️*
- يمكنك اضافه ردود وحذفها ",
'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"markdown",
 'reply_markup'=>$reply_markup,
]);
file_put_contents("set.txt",".");
$replies['n'] = null;
$replies['mode'] = null;
save_replies($replies);
  }
$zdelete = explode("|",$data);
if($data == "add_red|$zdelete[1]"){    
$name = $replies['replies'][$zdelete[1]]['name'];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>
"*
تم مسح الرد بنجاح
*",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"redd"]],
]
])
]);
unset($replies['replies'][$zdelete[1]]);
file_put_contents("replies.json",json_encode($replies,128|34|256));
}
if($data == "add_red"){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
• ارسل الكلمة الان .
",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"redd"]],
]
])
]);
$replies['mode'] = 'add';
save_replies($replies,128|34|256);
exit;
}
if($text != '/start' and $text != null and $replies['mode'] == 'add'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
• ارسل الرد الان , النص
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message->message_id,
]);
$replies['n'] = $text;
$replies['mode'] = 'addm';
save_replies($replies,128|34|256);
exit;
}
if($text != '/start' and $replies['mode'] == 'addm'){
$code = $replies['n'];
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*- تم حفط الرد بنجاح*
",
'parse_mode'=>"MarkDown",
'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>"• اضافة رد جديد •",'callback_data'=>"add_red"]],
[['text'=>"• رجوع •",'callback_data'=>"redd"]],
]
])
]);
$replies['replies'][$code]['name'] = $replies['n'];
$replies['replies'][$code]['mo'] = $text;
$replies['n'] = null;
$replies['mode'] = null;
save_replies($replies,128|34|256);
file_put_contents("sends.txt","");
}
if($replies['replies'][$text]!=null){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>$replies['replies'][$text]['mo'],
'reply_to_message_id'=>$message->message_id,
'parse_mode'=>"MarkDown",
]);
}
if($data=="dcnhrc"){
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
'text'=>"
• تم مسح رساله start والرجوع الى الرساله الاصلية !
 ",
'disable_web_page_preview'=>true,
"message_id"=>$message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[

[['text'=>" • رجوع •  ",'callback_data'=>"home"]],
]
])
]);
$infosudo["info"]["amr"]="null";
unlink("start.txt");
file_put_contents("sudo.json", json_encode($infosudo));
}
if($data == "VFTGKKCSS"){
$infosudo["info"]["amr"]="start";
file_put_contents("sudo.json", json_encode($infosudo));
bot('EditMessageText',[
'chat_id'=>$chat_id, 
'message_id'=>$message_id,
'text'=>"• ارسال الان الكليشه .

- يمكنك وضع بعض الاضافات الى كليشه الstart من خلال استخدام الاهاشتاكات التاليه :
  
1. `#nambot` : لوضع اسم  البوت
2. `#name` : لوضع اسم الشخص
3. `#id` : لوضع ايدي الشخص 
4. `#points` : لوضع عدد $cdiamlaadf الشخص

*يمكنك تعين نص ماركداون في البوت , عند كتابه معرف قناتك او معرفك قم بوضع [] بين المعرف .*", 
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]);
}
if($text  and $text !="/start" and $infosudo["info"]["amr"]=="start" and in_array($from_id,$sudo)){
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"• مثال على الرساله : 
 
$text ",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
]);
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"• تم الحفظ بنجاح",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]);
$infosudo["info"]["amr"]="null";
file_put_contents("start.txt","$text");
file_put_contents("sudo.json", json_encode($infosudo));
}
if($message and $fwrmember=="✅" and $from_id != $admin){
bot('ForwardMessage',[
 'chat_id'=>$admin,
 'from_chat_id'=>$chat_id,
 'message_id'=>$message->message_id,
]);
}
if($data == "FAFAF" and $chat_id != $admin){
bot('EditMessageText',[
'chat_id'=>$chat_id, 
   'message_id'=>$message_id,
'text'=>"*• لا يمكنك الدخول الى هذه القسم !* ً",
'parse_mode'=>'markdown',
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]); return false;}
if($data ==  'FAFAF'){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
'text'=>"
*• مرحبا بك في قسم  جميع الرسائل التي يتم ارسالها الى البوت 🚸
*
",
'parse_mode'=>"MARKDOWN",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"نوع التوجية",'callback_data'=>'ppshshsj'],['text'=>"$fwrmember",'callback_data'=>'fwrmember']],
				   [['text'=>"• رجوع • ",'callback_data'=>'home']],
                     ]
               ])
			   ]);
			}
function FAFAF($chat_id,$message_id){	
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$fwrmember=$infosudo["info"]["fwrmember"];
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*• مرحبا بك في قسم  جميع الرسائل التي يتم ارسالها الى البوت 🚸*",
'parse_mode'=>"MARKDOWN",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"نوع التوجية",'callback_data'=>'ppshshsj'],['text'=>"$fwrmember",'callback_data'=>'fwrmember']],
				   [['text'=>"• رجوع • ",'callback_data'=>'home']],
                     ]
               ])
			   ]);
			}
// $info_VISCODEV غير مستخدم - محذوف
$update     = json_decode(file_get_contents('php://input'));
$message = $update->message;
$text           = $message->text;
$data = $up->data ?? '';
$chat_id     = $message->chat->id;
$user          = $update->message->from->username;
$sudo         = $admin;
$bot_id = bot('getme')->result->id ?? 0;
$from_id     = $message->from->id;
$first_name = $message->from->first_name;
$type       = $update->message->chat->type;
@mkdir("ViSCo");
// callback_query مُعرَّف في بداية الملف 
$gp_get = file_exists("ViSCo/groups.txt") ? file_get_contents("ViSCo/groups.txt") : '';
$groups = array_filter(explode("\n", $gp_get));
$ViSCo6 = file_exists("ViSCo/ViSCots.txt") ? file_get_contents("ViSCo/ViSCots.txt") : '';
$pirvate = file_exists("ViSCo/pirvate.txt") ? array_filter(explode("\n",file_get_contents("ViSCo/pirvate.txt"))) : [];
$gtobn = $groups;
$forward = $update->message->forward_from;
$AMRQQPl = count($pirvate)-1;
$AMRQQP = count($groups)-1;
$AMLMP = $AMRQQP + $AMRQQPl;
if($data == "AMAMALT1"){
    file_put_contents("ViSCo/ViSCots.txt","ViSCots");
  bot('editmessagetext',[
  'chat_id'=>$chat_id,
  'message_id'=>$message_id,
    'text'=>"
• ارسال الان الرساله !
",
'reply_to_message_id'=>$message->message_id,
    'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,

'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"bbcybhu"]],
]])
]);
}
if($message and $ViSCo6 == "ViSCots" and $from_id == $sudo && $text !='/start' ){
	        bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"• جاري الاذاعه .....",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
          'reply_to_message_id'=>$message->message_id
]);
  for($i=0;$i<count($groups);$i++){
bot('ForwardMessage',[
 'chat_id'=>$groups[$i],
 'from_chat_id'=>$chat_id,
 'message_id'=>$message_id,
 ]);
} 
for($i=0;$i<count($pirvate);$i++){
bot('forwardMessage',[
 'chat_id'=>$pirvate[$i],
 'from_chat_id'=>$chat_id,
 'message_id'=>$message->message_id,
 ]);
 unlink("ViSCo/ViSCots.txt");
} 
bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"
• تم الاذاعة بنجاح 🎉

• الاعضاء الذين شاهدو الاذاعه {*$AMRQQPl*} عضو 
• الكروبات الذي تم الاذاعه لهم {*$AMRQQP*}

• عدد العضاء الكلي : {*$AMLMP*}
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]); return false;
}
if($text and $type == "private" and !in_array($from_id, $pirvate)){
file_put_contents("ViSCo/pirvate.txt", "$from_id\n",FILE_APPEND);
}
if($text and $type == "supergroup" and !in_array($chat_id, $groups)) {
file_put_contents("ViSCo/groups.txt", "$chat_id\n",FILE_APPEND);
}
$bot_id = bot('getme')->result->id ?? 0;
$new = $message->new_chat_member;
if($new and $new->id == $bot_id) {
file_put_contents("ViSCo/groups.txt", "$chat_id\n",FILE_APPEND);
}
if($data == "AMAMALT2"){
    file_put_contents("ViSCo/ViSCots.txt","ViSCo3ff");
  bot('editmessagetext',[
  'chat_id'=>$chat_id,
  'message_id'=>$message_id,
    'text'=>"
• ارسال الان الرساله !
",
'reply_to_message_id'=>$message->message_id,
    'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"bbcybhu"]],
]])
]);
}
if($message and $ViSCo6 == "ViSCo3ff" and $from_id == $sudo && $text !='/start' ){
	        bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"• جاري الاذاعه .....",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
          'reply_to_message_id'=>$message->message_id
]);
for($i=0;$i<count($pirvate);$i++){
bot('forwardMessage',[
 'chat_id'=>$pirvate[$i],
 'from_chat_id'=>$chat_id,
 'message_id'=>$message->message_id,
 ]);
 unlink("ViSCo/ViSCots.txt");
} 
$AMRQQPl = count($pirvate)-1;
bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"
• تم الاذاعة بنجاح 🎉

• الاعضاء الذين شاهدو الاذاعه {*$AMRQQPl*} عضو 
• الكروبات الذي تم الاذاعه لهم {*$AMRQQP*}

• عدد العضاء الكلي : {*$AMLMP*}
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]); return false;
}
if($data == "AMAMALT3"){
    file_put_contents("ViSCo/ViSCots.txt","ViSCo3");
  bot('editmessagetext',[
  'chat_id'=>$chat_id,
  'message_id'=>$message_id,
    'text'=>"
• ارسال الان الرساله !
",
'reply_to_message_id'=>$message->message_id,
    'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,

'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"bbcybhu"]],
]])
]);
}
if($message and $ViSCo6 == "ViSCo3" and $from_id == $sudo && $text !='/start' ){
	        bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"• جاري الاذاعه .....",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
          'reply_to_message_id'=>$message->message_id
]);
for($i=0;$i<count($gtobn);$i++){
bot('forwardMessage',[
 'chat_id'=>$gtobn[$i],
 'from_chat_id'=>$chat_id,
 'message_id'=>$message->message_id,
 ]);
 unlink("ViSCo/ViSCots.txt");
} 
$AMRQMMK = count($gtobn)-1;
bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"
• تم الاذاعة بنجاح 🎉

• الاعضاء الذين شاهدو الاذاعه {*$AMRQMMK*} عضو 
• الكروبات الذي تم الاذاعه لهم {*$AMRQQP*}

• عدد العضاء الكلي : {*$AMLMP*}
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]); return false;
}
if($data == "AMAlMAL"){
	file_put_contents("ViSCo/ViSCots.txt","V5YY5");
  bot('editmessagetext',[
  'chat_id'=>$chat_id,
  'message_id'=>$message_id,
    'text'=>"*
• ارسال الان الرساله ( نص او جميع الميديا ) 
• يمكنك استخدام كود جاهز في الاذاعه او يمكنك استخدام الماركداون*",
'reply_to_message_id'=>$message->message_id,
    'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,

'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"bbcybhu"]],
]])
]);
  }
if($message and $ViSCo6 == "V5YY5" and $from_id == $sudo && $text !='/start' ){
	        bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"• جاري الاذاعه .....",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
          'reply_to_message_id'=>$message->message_id
]);
    for ($i=0; $i<count($groups); $i++) { 
        bot('sendMessage',[
          'chat_id'=>$groups[$i],
          'text'=>"$text",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,

]);
    for ($i=0; $i<count($pirvate); $i++) { 
        bot('sendMessage',[
          'chat_id'=>$pirvate[$i],
          'text'=>"$text",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
]);
 file_put_contents("ViSCo/ViSCots.txt","juMAMRQQPl");
} 
$AMRQQPl = count($pirvate)-1;
bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"
• تم الاذاعة بنجاح 🎉

• الاعضاء الذين شاهدو الاذاعه {*$AMRQQPl*} عضو 
• الكروبات الذي تم الاذاعه لهم {*$AMRQQP*}

• عدد العضاء الكلي : {*$AMRQQPl*}
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]); return false;
}
}
if($data == "AMAMALp"){
	file_put_contents("ViSCo/ViSCots.txt","V5YY5");
  bot('editmessagetext',[
  'chat_id'=>$chat_id,
  'message_id'=>$message_id,
    'text'=>"*
• ارسال الان الرساله ( نص او جميع الميديا ) 
• يمكنك استخدام كود جاهز في الاذاعه او يمكنك استخدام الماركداون*",
'reply_to_message_id'=>$message->message_id,
    'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,

'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"bbcybhu"]],
]])
]);
  }
if($message and $ViSCo6 == "V5YY5" and $from_id == $sudo && $text !='/start' ){
	        bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"• جاري الاذاعه .....",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
          'reply_to_message_id'=>$message->message_id
]);
    for ($i=0; $i<count($pirvate); $i++) { 
        bot('sendMessage',[
          'chat_id'=>$pirvate[$i],
          'text'=>"$text",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
]);
 file_put_contents("ViSCo/ViSCots.txt","BBI4FD");
} 
$AMRQQPl = count($pirvate)-1;
bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"
• تم الاذاعة بنجاح 🎉

• الاعضاء الذين شاهدو الاذاعه {*$AMRQQPl*} عضو 

• عدد العضاء الكلي : {*$AMRQQPl*}
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]); return false;
}
if($data == "AMRAZLpm"){
	file_put_contents("ViSCo/ViSCots.txt","ECOZTS");
  bot('editmessagetext',[
  'chat_id'=>$chat_id,
  'message_id'=>$message_id,
    'text'=>"*
• ارسال الان الرساله ( نص او جميع الميديا ) 
• يمكنك استخدام كود جاهز في الاذاعه او يمكنك استخدام الماركداون*",
'reply_to_message_id'=>$message->message_id,
    'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"bbcybhu"]],
]])
]);
  }
if($message and $ViSCo6 == "ECOZTS" and $from_id == $sudo && $text !='/start' ){
	        bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"• جاري الاذاعه .....",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
          'reply_to_message_id'=>$message->message_id
]);
    for ($i=0; $i<count($gtobn); $i++) { 
        bot('sendMessage',[
          'chat_id'=>$gtobn[$i],
          'text'=>"$text",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
]);
 file_put_contents("ViSCo/ViSCots.txt","BBI4FD");
} 
$gtobnAmr = count($gtobn)-1;
bot('sendMessage',[
          'chat_id'=>$chat_id,
          'text'=>"
• تم الاذاعة بنجاح 🎉

• الكروبات الذين شاهدو الاذاعه {*$gtobnAmr*} كروب

• عدد الكروبات الكلي : {*$gtobnAmr*}
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]); return false;
}
$gdbyj =file_get_contents("gdbyj $alnhjm.txt");
if($data =='bbcybhu' && $gdbyj == null){
	  	$vvcvhg = $AMRQQPl + $AMRQQP;
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
        *• مرحبا بك في قسم الاذاعه 🔥
*
- عدد المسخدمين الكلي :* $vvcvhg*
- عدد الخاص : *$AMRQQPl*
- عدد الكروبات والقنوات :* $AMRQQP*

- عدد المحظورين : *0*
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"تثبيت الاذاعه : ❌",'callback_data'=>"MLpAPK"]],
[['text'=>"اذاعه للكل",'callback_data'=>"AMAlMAL"],['text'=>"اذاعه توجيه للكل",'callback_data'=>"AMAMALT1"]],
[['text'=>"اذاعه للخاص",'callback_data'=>"AMAMALp"],['text'=>"اذاعه توجيه للخاص",'callback_data'=>"AMAMALT2"]],
[['text'=>"اذاعه كروبات",'callback_data'=>"AMRAZLpm"],['text'=>"اذاعه توجيه للكروبات",'callback_data'=>"AMAMALT3"]],
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]);
}
if($data == "bbcybhu" && $gdbyj == "on") {
$vvcvhg = $AMRQQPl + $AMRQQP;
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
        *• مرحبا بك في قسم الاذاعه 🔥
*
- عدد المسخدمين الكلي :* $vvcvhg*
- عدد الخاص : *$AMRQQPl*
- عدد الكروبات والقنوات :* $AMRQQP*

- عدد المحظورين : *0*
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"تثبيت الاذاعه : ✅",'callback_data'=>"MLAPK"]],
[['text'=>"اذاعه للكل",'callback_data'=>"AMAlMAL"],['text'=>"اذاعه توجيه للكل",'callback_data'=>"AMAMALT1"]],
[['text'=>"اذاعه للخاص",'callback_data'=>"AMAMALp"],['text'=>"اذاعه توجيه للخاص",'callback_data'=>"AMAMALT2"]],
[['text'=>"اذاعه كروبات",'callback_data'=>"AMRAZLpm"],['text'=>"اذاعه توجيه للكروبات",'callback_data'=>"AMAMALT3"]],
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]);
}
if($data == "bbcybhu" && $gdbyj == "off") {
  	$vvcvhg = $AMRQQPl + $AMRQQP;
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
        *• مرحبا بك في قسم الاذاعه 🔥
*
- عدد المسخدمين الكلي :* $vvcvhg*
- عدد الخاص : *$AMRQQPl*
- عدد الكروبات والقنوات :* $AMRQQP*

- عدد المحظورين : *0*
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"تثبيت الاذاعه : ❌",'callback_data'=>"MLpAPK"]],
[['text'=>"اذاعه للكل",'callback_data'=>"AMAlMAL"],['text'=>"اذاعه توجيه للكل",'callback_data'=>"AMAMALT1"]],
[['text'=>"اذاعه للخاص",'callback_data'=>"AMAMALp"],['text'=>"اذاعه توجيه للخاص",'callback_data'=>"AMAMALT2"]],
[['text'=>"اذاعه كروبات",'callback_data'=>"AMRAZLpm"],['text'=>"اذاعه توجيه للكروبات",'callback_data'=>"AMAMALT3"]],
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]);
}
if($data == "MLpAPK") {
$vvcvhg = $AMRQQPl + $AMRQQP;
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
        *• مرحبا بك في قسم الاذاعه 🔥
*
- عدد المسخدمين الكلي :* $vvcvhg*
- عدد الخاص : *$AMRQQPl*
- عدد الكروبات والقنوات :* $AMRQQP*

- عدد المحظورين : *0*
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"تثبيت الاذاعه : ✅",'callback_data'=>"MLAPK"]],
[['text'=>"اذاعه للكل",'callback_data'=>"AMAlMAL"],['text'=>"اذاعه توجيه للكل",'callback_data'=>"AMAMALT1"]],
[['text'=>"اذاعه للخاص",'callback_data'=>"AMAMALp"],['text'=>"اذاعه توجيه للخاص",'callback_data'=>"AMAMALT2"]],
[['text'=>"اذاعه كروبات",'callback_data'=>"AMRAZLpm"],['text'=>"اذاعه توجيه للكروبات",'callback_data'=>"AMAMALT3"]],
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]);
file_put_contents("gdbyj $alnhjm.txt","on");
}
if($data == "MLAPK") {
$vvcvhg = $AMRQQPl + $AMRQQP;
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"
        *• مرحبا بك في قسم الاذاعه 🔥
*
- عدد المسخدمين الكلي :* $vvcvhg*
- عدد الخاص : *$AMRQQPl*
- عدد الكروبات والقنوات :* $AMRQQP*

- عدد المحظورين : *0*
",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"تثبيت الاذاعه : ❌",'callback_data'=>"MLpAPK"]],
[['text'=>"اذاعه للكل",'callback_data'=>"AMAlMAL"],['text'=>"اذاعه توجيه للكل",'callback_data'=>"AMAMALT1"]],
[['text'=>"اذاعه للخاص",'callback_data'=>"AMAMALp"],['text'=>"اذاعه توجيه للخاص",'callback_data'=>"AMAMALT2"]],
[['text'=>"اذاعه كروبات",'callback_data'=>"AMRAZLpm"],['text'=>"اذاعه توجيه للكروبات",'callback_data'=>"AMAMALT3"]],
[['text'=>"• رجوع •",'callback_data'=>"home"]],
]])
]);
file_put_contents("gdbyj $alnhjm.txt","off");}
$d = date('D');
$day = explode("\n",file_get_contents($d.".txt"));
$todayuser = count($day);
if($d == "Sat"){
unlink("Fri.txt");
}
if($d == "Sun"){
unlink("Sat.txt");
}
if($d == "Mon"){
unlink("Sun.txt");
}
if($d == "Tue"){
unlink("Mon.txt");
}
if($d == "Wed"){
unlink("The.txt");
}
if($d == "Thu"){
unlink("Wed.txt");
}
if($d == "Fri"){
unlink("Thu.txt");
}
if($message and !in_array($from_id, $day)){ 
file_put_contents($d.".txt",$from_id. "\n",FILE_APPEND);
}
 $dd = explode("\n",file_get_contents("sudo/member.txt"));
$k =1;
foreach (array_slice($dd, -6, 5,true)  as $sendmgddyessage) {
$sendmgddyessag = "[$sendmgddyessage](tg://user?id=$sendmgddyessage)";
$a = $a.$k.". ".$sendmgddyessag."\n";
$k++;
}
if($data== "sendmgddyessage"){
  $all = count($user["channellist"]);
  $order = count($user["channellist"]);
  $amryui = $cunte + $order;
bot('EditMessageText',[
'chat_id'=>$chat_id,
     'message_id'=>$message_id,
'text'=>"
*مرحبا بك في قسم الاحصائيات 📊*

• عدد المسخدمين الكلي : *$amryui*
- عدد المستخدمين في الخاص : *$cunte*
- عدد الكروبات والقنوات : *$order*

• عدد المحظورين : *$countban*

• عدد المتفاعلين اليوم : *$todayuser*
- قائمة اخر الاعضاء الذين شتركوا :
$a",
'parse_mode'=>"MARKDOWN",
'disable_web_page_preview' =>true,
 'reply_to_message_id'=>$message->message_id, 
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
[['text'=>"مسح قائمة الحظر",'callback_data'=>"unbanall"]],
[['text'=>"حظر شخص",'callback_data'=>"ban"],['text'=>" الغاء حظر شخص",'callback_data'=>"unban"]],
[['text'=>" قسم الاذاعة",'callback_data'=>"bbcybhu"]],
[['text'=>"• رجوع •",'callback_data'=>'home']],
]])
]);
}
  if($data ==  'agbary' ){
  bot( 'editMessageText' ,[
     'chat_id' =>$chat_id,
     'message_id'=>$message_id,
        'text'=>"*• مرحبا بك في قسم الاشتراك الاجباري 💫*

اليك التحكم عزيزي 🔥",
                'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"إضافة قناة",'callback_data'=>"addchannel"],['text'=>"مسح قناة",'callback_data'=>"delchannel"]],
[['text'=>"تعيين رسالة الاشتراك الاجباري ",'callback_data'=>"klish_sil"]],
[['text'=>"ماركدون : $silk ",'callback_data'=>"silk"]],
[['text'=>"عرض قنوات الاشتراك الاجباري ",'callback_data'=>"viwechannel"]],
[['text'=>" العودة  ",'callback_data'=>"home"]],
]])
]);
}
$bot_id = bot('getme')->result->id ?? 0;
$chat_id = $message->chat->id;
$new = $message->new_chat_member;
if($new and $new->id == $bot_id) {
 file_put_contents("t.txt",$chat_id);
}
// متغيرات مُعرَّفة في بداية الملف 
// callback_query مُعرَّف في بداية الملف
// inline_query مُعرَّف في بداية الملف
// $sudo = $admin مكررة - محذوفة
$infosudo = json_decode(file_get_contents("sudo.json"),true);
if($data == "VFTGKKCSS"){
$infosudo["info"]["amr"]="start";
file_put_contents("sudo.json", json_encode($infosudo));
bot('EditMessageText',[
'chat_id'=>$chat_id, 
     'message_id'=>$message_id,
'text'=>"• ارسال الان الكليشه .

- يمكنك وضع بعض الاضافات الى كليشه الstart من خلال استخدام الاهاشتاكات التاليه :
  
1. `#nambot` : لوضع اسم  البوت
2. `#name` : لوضع اسم الشخص
3. `#id` : لوضع ايدي الشخص 
4. `#points` : لوضع عدد $cdiamlaadf الشخص

*يمكنك تعين نص ماركداون في البوت , عند كتابه معرف قناتك او معرفك قم بوضع [] بين المعرف .*", 
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]);
}
if($text  and $text !="/start" and $infosudo["info"]["amr"]=="start" and in_array($from_id,$sudo)){
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"• مثال على الرساله : 
 
$text ",
'parse_mode'=>'markdown',
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
]);
bot('sendmessage',[
'chat_id'=>$chat_id, 
'text'=>"• تم الحفظ بنجاح",
'disable_web_page_preview'=>true,
 'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>"• رجوع •  ",'callback_data'=>"home"]],
]
])
]);
$infosudo["info"]["amr"]="null";
$infosudo["info"]["start"]="$text";
file_put_contents("sudo.json", json_encode($infosudo));
}

// متغيرات مُعرَّفة في بداية الملف 
// callback_query مُعرَّف في بداية الملف
// inline_query مُعرَّف في بداية الملف
if($data == "home" and in_array($from_id,$sudo)){
$infosudo["info"]["amr"]="null";
file_put_contents("sudo.json", json_encode($infosudo));
sendwataw($chat_id,$message_id);
}
if($data == "fwrmember"){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$fwrmember=$infosudo["info"]["fwrmember"];
if($fwrmember=="✅"){
$infosudo["info"]["fwrmember"]="❎";
}
if($fwrmember=="❎"){
$infosudo["info"]["fwrmember"]="✅";
}
file_put_contents("sudo.json", json_encode($infosudo));
FAFAF($chat_id,$message_id);
}
if($data == "tnbih"){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$tnbih=$infosudo["info"]["tnbih"];
if($tnbih=="✅"){
$infosudo["info"]["tnbih"]="❎";
}
if($tnbih=="❎"){
$infosudo["info"]["tnbih"]="✅";
}
file_put_contents("sudo.json", json_encode($infosudo));
sendwataw($chat_id,$message_id);
}
if($data == "amr987"){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$amr987=$infosudo["info"]["amr987"];
if($tnbih=="✅"){
$infosudo["info"]["tnbih"]="❎";
}
if($amr987=="❎"){
$infosudo["info"]["amr987"]="✅";
}
file_put_contents("sudo.json", json_encode($infosudo));
sendwataw($chat_id,$message_id);
}
if($data == "silk"){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$skil=$infosudo["info"]["silk"];
if($skil=="✅"){
$infosudo["info"]["silk"]="❎";
}
if($skil=="❎"){
$infosudo["info"]["silk"]="✅";
}
file_put_contents("sudo.json", json_encode($infosudo));
sendwataw($chat_id,$message_id);
}
if($data == "allch"){
$infosudo = json_decode(file_get_contents("sudo.json"),true);
$allch=$infosudo["info"]["allch"];
if($allch=="✅"){
$infosudo["info"]["allch"]="❎";
}
if($allch=="❎"){
$infosudo["info"]["allch"]="✅";
}
file_put_contents("sudo.json", json_encode($infosudo));
sendwataw($chat_id,$message_id);
}   
$up = $update->callback_query;
$data = $up->data ?? '';
$forward_from = $update->message->forward_from ?? null;
$forward_from_id = $forward_from->id ?? 0;
$forward_from_username = $forward_from->username ?? '';
$forward_from_first_name = $forward_from->first_name ?? '';
$reply = $update->message->reply_to_message->forward_from->id ?? 0;
$reply_username = $update->message->reply_to_message->forward_from->username ?? '';
$reply_first = $update->message->reply_to_message->forward_from->first_name ?? '';
$membercall = $update->callback_query->id ?? 0;
$tc = $update->message->chat->type ?? '';
$infobot = file_exists("info.txt") ? explode("\n",file_get_contents("info.txt")) : [];
$usernamebot = $infobot[1] ?? $usernamebot ?? '';
$channel = str_replace('@','',file_get_contents('data/channelyes.txt') ?: '');
$channelcode = str_replace('@','',file_get_contents('data/channelcode.txt') ?: '');
$coins_start = file_get_contents('edid/coinsstart.txt') ?: 15;
if($channel){
$forchannel = bot_curl('getChatMember', ['chat_id' => '@'.$channel, 'user_id' => $from_id]);
$tch  = $forchannel->result->status ?? '';
$tchq = $tch;
} else { $tch = ''; $tchq = ''; }
function getChatMembersCount($chat_id,$VISCODEV) {
  $url = 'https://api.telegram.org/bot'.$VISCODEV.'/getChatMembersCount?chat_id=@'.$chat_id;
  $result = file_get_contents($url);
  $result = json_decode ($result);
  $result = $result->result;
  return $result;
}
$username_admin=file_get_contents("data/username_admin.txt");
$Dev = [$admin];
@mkdir("data");
@$user = json_decode(file_get_contents("data/user.json"),true);
@$juser = json_decode(file_get_contents("data/$from_id.json"),true);
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
if(!in_array($from_id, $user["userlist"]) == true) {
$user["userlist"][]= $from_id;
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);}
$e=explode("|", $data) ;
$e1=str_replace("/start",null,$text); 
$akl = json_decode(file_get_contents("akl/akl.json"),true);
if($text == "/start$e1" and is_numeric($e1)) {
	if($chat_id == $sudo or $chat_id == $admin ) {
  $akl['HACKER'][$from_id] = "I";
  $akl['HACK'][$from_id] = str_replace(" ", null, $e1);
  SETJSON($akl);
}}
$akl = json_decode(file_get_contents("akl/akl.json"),true);
// $Api_Tok محذوف - يُستخدم VISCODEV بدلاً منه
$usrbot = $usernamebot;
function SETJSON($INPUT){if($INPUT != NULL || $INPUT != "") {$F = "akl/akl.json";$N = json_encode($INPUT, JSON_PRETTY_PRINT);file_put_contents($F, $N);}
}
$info = (array) bot_curl('getChatMember', ['chat_id' => $chat_id, 'user_id' => $from_id]);
$group = $info['result']['status'];
if($message and $group == "creator"){
exit;
}
if(in_array($from_id, $user["blocklist"])) {
bot('sendmessage',[
	'chat_id'=>$chat_id,
	'text'=>"انت محظور من البوت",
'reply_markup'=>json_encode(['KeyboardRemove'=>[
],'remove_keyboard'=>true])]);}
$pro=$projson["info"]["pro"];
$amrorders = count(file("akl/orders.txt"));
$zr = json_decode(file_get_contents("zr.json"),true);
// callback_query مُعرَّف في بداية الملف
if(isset($update->inline_query)){
$chat_id = $update->inline_query->chat->id;
$from_id = $update->inline_query->from->id;
$name = $update->inline_query->from->first_name.' '.$update->inline_query->from->last_name;
$text_inline = $update->inline_query->query;
$mes_id = $update->inline_query->inline_message_id; 
$user = strtolower($update->inline_query->from->username); 
$usr = strtolower($update->inline_query->from->username); 
}
$cdiamlaadf = file_get_contents("edid/cdiamlaadf.txt") ?: 'نقاط';
$cdiamlao   = $cdiamlaadf . 'ك';
$cdiamlanoo = $cdiamlaadf;
// ملاحظة: cdiamlaadf/cdiamlao/cdiamlanoo مُعرَّفة في بداية الملف
$baageel = file_exists("baageel.txt") ? file_get_contents("baageel.txt") : "✅";
if($data and $baageel =="❎" and $chat_id != $admin ){
 bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' => "البوت تحت الصيانة",
            'show_alert' =>false
         ]);return false;}
$sec = time();
$madey = json_decode(file_get_contents("time.json"),true);
$offdata =file_get_contents("amr/offdata $from_id.txt");
if($data && $offdata == null){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->ad,
'text'=>"off",
'show_alert'=>true,
]);
file_put_contents("amr/offdata $chat_id.txt","amr");
}
if($text && $offdata == 'amr'){unlink("amr/offdata $chat_id.txt");}
if($data and $offdata == 'amr' && $madey[$from_id]["time"] >= $sec and $chat_id !=$admin){
bot('answercallbackquery',[
'callback_query_id'=>$update->callback_query->id,
'text'=>"
لا يمكنكك الارسال الا بعد 3 ثواني
",
'show_alert'=>true,
]);
unlink("amr/offdata $chat_id.txt");
die;
} else {
$madey[$from_id]["time"] = time()+3;
file_put_contents("time.json", json_encode($madey));
file_put_contents("amr/offdata $chat_id.txt","amr");
}
$tc = $update->message->chat->type ?? '';
if($tc == 'group' or $tc == 'supergroup'){bot('LeaveChat',['chat_id'=>$chat_id,'message_id'=>$message_id,]);return false;}
// callback_query مُعرَّف في بداية الملف
// inline_query مُعرَّف في بداية الملف
$nambot = file_exists("edid/nambot.txt") ? file_get_contents("edid/nambot.txt") : "DomKom";
$start = file_exists("start.txt") ? file_get_contents("start.txt") : null;
if($start==null){
$start="✰︙ مرحبا بك في بوت $nambot ✨ اقوى بوت رشق على تيليجرام 
✰] $cdiamlao : #points 
✰] ايديك : #id";
}
$start1 = file_exists("start.txt") ? file_get_contents("start.txt") : null;
if($start1==null){
$start1="✰︙ مرحبا بك في بوت $nambot ✨ اقوى بوت رشق على تيليجرام 
✰] $cdiamlao : #points 
✰] ايديك : #id";
}
$coins_start = file_exists("edid/coinsstart.txt") ? (int)file_get_contents("edid/coinsstart.txt") : 15;
$adna_coins = file_exists("data/adna_coins.txt") ? (int)file_get_contents("data/adna_coins.txt") : 40;
$day_coins = file_exists("data/day_coins.txt") ? (int)file_get_contents("data/day_coins.txt") : 20;
$work_add_day = file_exists("edid/work_add_day.txt") ? (int)file_get_contents("edid/work_add_day.txt") : 10;
$add_ado = file_exists("edid/addado.txt") ? (int)file_get_contents("edid/addado.txt") : 12;
$add_aoc = file_exists("edid/add_aoc.txt") ? (int)file_get_contents("edid/add_aoc.txt") : 5;
$chadmin = file_exists("edid/chadmin.txt") ? file_get_contents("edid/chadmin.txt") : "لم يتم تعين قناة";
$chadin = str_replace("@","",$chadmin);
$acont_admin = file_exists("edid/acont_admin.txt") ? file_get_contents("edid/acont_admin.txt") : "لم يتم تعين حساب";
$msgasro=file_get_contents("edid/msgasro.txt");
if($msgasro==null){
$msgasro="شروط استخدام بوت $nambot 

- بوت $nambot اول بوت عربي في التلجرام مخصص لجميع خدمات برامج التواصل الاجتماعي انستقرام - تيك توك - يوتيوب - تيوتر - فيسبوك وللخ... هناك العديد من الشروط حول استخدام بوت $nambot.

- الامان والثقه الموضوع الاول لدينا وحماية خصوصية جميع المستخدمين من الاولويات لدينا لذالك جميع المعلومات من ال$cdiamlaadf والطلبات هي محصنة تماماً لا يسمح لـ اي شخص الاطلاع عليها الا في حالة طلب المستخدم ذالك من الدعم الفني

- على جميع المستخدمين التركيز في حالة طلب اي شيء من البوت في حالة كان حسابك او قناتك او ماشبه ذالك خاص سيلغي طلبك نهائياً لذالك لايوجد استرداد او اي تعويض لذالك وجب التنبيه

- جميع الخدمات تتحدث يومياً لا يوجد لدينا خدمات ثابته يتم اضافة يومياً العديد من الخدمات التي تكون مناسبة لجميع المستخدمين في البوت لنكون الاول والافضل دائماً

- لا يوجد اي استرداد او الغاء في حالة تم الرشق او الدعم لحساب او لقناة او لمنشور في الغلط 

- جميع الخدمات المتوفره هي موثوقه تماماً ويتم التجربه عليها قبل اضافاتها للبوت لذالك يتوفر انواع الخدمات بأسعار مختلفة من خدمة لخدمة اخرى

- قنوات بوت $nambot في التلجرام 
قناة بوت $nambot $chadmin القناة الرسميه التي يتم نشر بها جميع الخدمات والمعلومات حول بوت $nambot

قناة وكيل بوت $nambot $chadmin القناة الرسميه لوكيل بوت $nambot لذالك لا يتوفر لدينا سوا هذه القنوات المطروحه اعلاه واذا توفر لدينا اي قناة سنقوم بنشرها في قنواتنا الرسميه ليكون لدى جميع المستخدمين العلم بذالك

فريق بوت $nambot ✍";
}
$msgasar=file_get_contents("edid/msgasar.txt");
if($msgasar==null){
$msgasar="💰] $cdiamlaadf بوت $nambot

3200 $cdiamlanoo بـ 1$
16 الف $cdiamlanoo بـ 5$
32 الف $cdiamlanoo بـ 10$
48 الف $cdiamlanoo بـ 15$
96 الف $cdiamlanoo بـ 30$
480 الف $cdiamlanoo. بـ 150$
1 مليون بـ 290$

  الوكيل من هنا $acont_admin";
}
$msgaspat=file_get_contents("edid/msgaspat.txt");
if($msgaspat==null){
$msgaspat="
*تم تنفيذ طلب جديد  ✅

معلومات الطلب
            -----------------------    *
ايدي العضو : #id
اسم الخدمه : #nameService
سعر الطلب : #coinService
رقم الطلب : #numberall";
}
$coin = (int)($cuser["userfild"][$from_id]["coin"] ?? 0);
$nzambot = file_exists("edid/nzambot.txt") ? file_get_contents("edid/nzambot.txt") : "❌";
$aklamrnm1 = file_exists("edid/aklamrnm1.txt") ? file_get_contents("edid/aklamrnm1.txt") : "الخدمات 🗂";
$aklamrnm2 = file_exists("edid/aklamrnm2.txt") ? file_get_contents("edid/aklamrnm2.txt") : "تجميع ✳️";
$aklamrnm3 = file_exists("edid/aklamrnm3.txt") ? file_get_contents("edid/aklamrnm3.txt") : "الحساب 🗃️";
$aklamrnm4 = file_exists("edid/aklamrnm4.txt") ? file_get_contents("edid/aklamrnm4.txt") : "استخدام كود 💳";
$aklamrnm5 = file_exists("edid/aklamrnm5.txt") ? file_get_contents("edid/aklamrnm5.txt") : "تحويل $cdiamlaadf ♻️";
$aklamrnm6 = file_exists("edid/aklamrnm6.txt") ? file_get_contents("edid/aklamrnm6.txt") : "معلومات الطلب 📥";
$aklamrnm7 = file_exists("edid/aklamrnm7.txt") ? file_get_contents("edid/aklamrnm7.txt") : "طلباتي 📮";
$aklamrnm8 = file_exists("edid/aklamrnm8.txt") ? file_get_contents("edid/aklamrnm8.txt") : "قناة البوت 🤍";
$aklamrnm9 = file_exists("edid/aklamrnm9.txt") ? file_get_contents("edid/aklamrnm9.txt") : "شحن $cdiamlaadf 💰";
$aklamrnm10 = file_exists("edid/aklamrnm10.txt") ? file_get_contents("edid/aklamrnm10.txt") : "الشروط 📜";
$aklamrnm11=file_get_contents("edid/aklamrnm11.txt");
if($aklamrnm11==null){
$aklamrnm11="عدد الطلبات : $amrorders ✅";
}

if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr1 = $aklamrnm1;}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr2 = "$aklamrnm2";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr3 = "$aklamrnm3";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr4 = "$aklamrnm4";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr5 = "$aklamrnm5";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr6 = "$aklamrnm6";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr7 = "$aklamrnm7";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr8 = "$aklamrnm8";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr9 = "$aklamrnm9";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr10 = "$aklamrnm10";}
if(file_get_contents("edid/zerasaseon.txt") == '✅') {$aklamr11 = "$aklamrnm11";}
$start = str_replace('#id',$chat_id,$start);
$start = str_replace('#points',$coin,$start);
$start = str_replace('#name',$name,$start);
$start = str_replace('#nambot',$nambot,$start);
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
if($text=="/start" && $tc == "private" and !preg_match("/^\/(start) (code)_(.*)/s",$text)){
if(in_array($from_id, $user["userlist"]) == true) {
$coin = $cuser["userfild"]["$from_id"]["coin"];
$reply_markup = ne_home_keyboard();
foreach($button['codzer'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>"$cedname"]];
}
foreach($button['buttons'] as $ced => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>$ced]];
}
foreach($button['links'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'url'=>"$cedname"]];
}
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
$start = ne_home_text($chat_id, $from_id, $cuser, $message, $cdiamlaadf, $cdiamlanoo);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"$start",
'parse_mode'=>'HTML',
 'reply_markup'=>$reply_markup,              
    	]);
$arr = $user['finance'];
$channel = array_rand($arr);
$channelincoin = $arr[$channel][1];
$channelssssss = $arr[$channel][0];
$join = json_encode(bot_curl('getChatMember', ['chat_id' => $channelssssss, 'user_id' => $from_id]));
if((strpos($join,'"status":"left"') or strpos($join,'"Bad Request: USER_ID_INVALID"') or strpos($join,'"status":"kicked"')) !== false){
if(!in_array($channelssssss, $juser["userfild"]["$from_id"]["channeljoin"])) {
if($channelincoin > 0){
$text_add = "انضم في القناة ".$arr[$channel][0]." ✅
 واحصل على 10 $cdiamlaadf 💰";
           bot('sendmessage',[
          	'chat_id'=>$chat_id,
          	'text'=>$text_add,
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
               ['text'=>"تحقق من الانظمام ♻️",'callback_data'=>"finance_".$channel]
  				   ],
                       ]
                 ])
   ]);
}else {
@$usernew = json_decode(file_get_contents("data/user.json"),true);
unset($usernew['finance'][$channel]);
$usernew = json_encode($usernew,true);
file_put_contents("data/user.json",$usernew);
}
}
}
$juser["userfild"]["$from_id"]["file"]="none";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
else
{
$startado = file_get_contents("edid/amr/$chat_id.txt");
$result = preg_match("/[0-9]/", $startado);
$messagle = "";
if ($result) {
    $messagle = "$startado";
} else {
    $messagle = "";
}
$juser = json_decode(file_get_contents("data/$from_id.json"),true);
$inuser = json_decode(file_get_contents("data/$messagle.json"),true);
$member = $inuser["userfild"]["$messagle"]["invite"];
$coin = $inuser["userfild"]["$messagle"]["coin"];
$memberplus = $member + 1;
$coinplus = $coin  + $coins_start;
$inuser["userfild"]["$messagle"]["invite"]="$memberplus";
$inuser["userfild"]["$messagle"]["coin"]="$coinplus";
$inuser = json_encode($inuser,true);
file_put_contents("data/$messagle.json",$inuser);
// --- Wallet ledger: مكافأة دعوة (إحالة) ---
wallet_log($messagle, 'gift', $coins_start, "مكافأة دعوة مستخدم جديد", ['balance_after' => $coinplus]);
$juser["userfild"]["$from_id"]["inviter"]="$messagle";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
unlink("edid/amr/$chat_id.txt");
bot('sendmessage',[
	'chat_id'=>$messagle,
	'text'=>"لقد حصلت على $coins_start 👤 $cdiamlanoo من $name ",
 ]);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$reply_markup = ne_home_keyboard();
foreach($button['codzer'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>"$cedname"]];
}
foreach($button['buttons'] as $ced => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>$ced]];
}
foreach($button['links'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'url'=>"$cedname"]];
}
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
$start = ne_home_text($chat_id, $from_id, $cuser, $message, $cdiamlaadf, $cdiamlanoo);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"$start",
'parse_mode'=>'HTML',
 'reply_markup'=>$reply_markup,              
    	]);
$arr = $user['finance'];
$channel = array_rand($arr);
$channelincoin = $arr[$channel][1];
$channelssssss = $arr[$channel][0];
$join = json_encode(bot_curl('getChatMember', ['chat_id' => $channelssssss, 'user_id' => $from_id]));
if((strpos($join,'"status":"left"') or strpos($join,'"Bad Request: USER_ID_INVALID"') or strpos($join,'"status":"kicked"')) !== false){
if(!in_array($channelssssss, $juser["userfild"]["$from_id"]["channeljoin"])) {
if($channelincoin > 0){
$text_add = "انضم في القناة ".$arr[$channel][0]." ✅
 واحصل على 10 $cdiamlaadf 💰";
           bot('sendmessage',[
          	'chat_id'=>$chat_id,
          	'text'=>$text_add,
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
               ['text'=>"تحقق من الانظمام ♻️",'callback_data'=>"finance_".$channel]
  				   ],
                       ]
                 ])
   ]);
}else {
@$usernew = json_decode(file_get_contents("data/user.json"),true);
unset($usernew['finance'][$channel]);
$usernew = json_encode($usernew,true);
file_put_contents("data/user.json",$usernew);
}
}
}
$juser = json_decode(file_get_contents("data/$from_id.json"),true);
$juser["userfild"]["$from_id"]["invite"]="0";
$juser["userfild"]["$from_id"]["coin"]="0";
$juser["userfild"]["$from_id"]["setchannel"]="لا يوجد !";
$juser["userfild"]["$from_id"]["setmember"]="لا يوجد !";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
}
elseif(strpos($text , '/start ') !== false   and !preg_match("/^\/(start) (code)_(.*)/s",$text)) {
$start2 = str_replace("/start ","",$text);
if(in_array($from_id, $user["userlist"])) {
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$reply_markup = ne_home_keyboard();
foreach($button['codzer'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>"$cedname"]];
}
foreach($button['buttons'] as $ced => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>$ced]];
}
foreach($button['links'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'url'=>"$cedname"]];
}
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
$start = ne_home_text($chat_id, $from_id, $cuser, $message, $cdiamlaadf, $cdiamlanoo);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"$start",
'parse_mode'=>'HTML',
 'reply_markup'=>$reply_markup,              
    	]);
$arr = $user['finance'];
$channel = array_rand($arr);
$channelincoin = $arr[$channel][1];
$channelssssss = $arr[$channel][0];
$join = json_encode(bot_curl('getChatMember', ['chat_id' => $channelssssss, 'user_id' => $from_id]));
if((strpos($join,'"status":"left"') or strpos($join,'"Bad Request: USER_ID_INVALID"') or strpos($join,'"status":"kicked"')) !== false){
if(!in_array($channelssssss, $juser["userfild"]["$from_id"]["channeljoin"])) {
if($channelincoin > 0){
$text_add = "انضم في القناة ".$arr[$channel][0]." ✅
 واحصل على 10 $cdiamlaadf 💰";
           bot('sendmessage',[
          	'chat_id'=>$chat_id,
          	'text'=>$text_add,
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
               ['text'=>"تحقق من الانظمام ♻️",'callback_data'=>"finance_".$channel]
  				   ],
                       ]
                 ])
   ]);
}else {
@$usernew = json_decode(file_get_contents("data/user.json"),true);
unset($usernew['finance'][$channel]);
$usernew = json_encode($usernew,true);
file_put_contents("data/user.json",$usernew);
}
}
}
}
else
{
$start = str_replace("/start ","",$text);
$juser = json_decode(file_get_contents("data/$from_id.json"),true);
$inuser = json_decode(file_get_contents("data/$start.json"),true);
$member = $inuser["userfild"]["$start"]["invite"];
$coin = $inuser["userfild"]["$start"]["coin"];
$memberplus = $member + 1;
$coinplus = $coin  + $coins_start;
bot('sendmessage',[
	'chat_id'=>$start,
	'text'=>"لقد حصلت على $coins_start 👤 $cdiamlanoo من $name ",
 ]);
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$reply_markup[] =   [['text'=>"$aklamr1",'callback_data'=>'takemember']];
$reply_markup[] =   [['text'=>"$aklamr2",'callback_data'=>'takecoinn'],['text'=>"$aklamr3",'callback_data'=>'accont']];
$reply_markup[] =   [['text'=>"$aklamr4",'callback_data'=>'amr6'],['text'=>"$aklamr5",'callback_data'=>'sendcoin']];
$reply_markup[] =   [['text'=>"$aklamr6",'callback_data'=>'amr4'],['text'=>"$aklamr7",'callback_data'=>'amr5']];
$reply_markup[] =   [['text'=>"$aklamr8",'url'=>"https://t.me/$chadin"]];
$reply_markup[] =   [['text'=>"$aklamr9",'callback_data'=>'amr2'],['text'=>"$aklamr10",'callback_data'=>'amr1']];
$reply_markup[] =   [['text'=>"$aklamr11",'callback_data'=>'sec_stats']];
foreach($button['codzer'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>"$cedname"]];
}
foreach($button['buttons'] as $ced => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>$ced]];
}
foreach($button['links'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'url'=>"$cedname"]];
}
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"$start1 
",
 'reply_markup'=>$reply_markup,              
    	]);
$arr = $user['finance'];
$channel = array_rand($arr);
$channelincoin = $arr[$channel][1];
$channelssssss = $arr[$channel][0];
$join = json_encode(bot_curl('getChatMember', ['chat_id' => $channelssssss, 'user_id' => $from_id]));
if((strpos($join,'"status":"left"') or strpos($join,'"Bad Request: USER_ID_INVALID"') or strpos($join,'"status":"kicked"')) !== false){
if(!in_array($channelssssss, $juser["userfild"]["$from_id"]["channeljoin"])) {
if($channelincoin > 0){
$text_add = "انضم في القناة ".$arr[$channel][0]." ✅
 واحصل على 10 $cdiamlaadf 💰";
           bot('sendmessage',[
          	'chat_id'=>$chat_id,
          	'text'=>$text_add,
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
               ['text'=>"تحقق من الانظمام ♻️",'callback_data'=>"finance_".$channel]
  				   ],
                       ]
                 ])
   ]);
}else {
@$usernew = json_decode(file_get_contents("data/user.json"),true);
unset($usernew['finance'][$channel]);
$usernew = json_encode($usernew,true);
file_put_contents("data/user.json",$usernew);
}
}
}
$inuser["userfild"]["$start"]["invite"]="$memberplus";
$inuser["userfild"]["$start"]["coin"]="$coinplus";
$inuser = json_encode($inuser,true);
file_put_contents("data/$start.json",$inuser);
// --- Wallet ledger: مكافأة دعوة (إحالة) ---
wallet_log($start, 'gift', $coins_start, "مكافأة دعوة مستخدم جديد", ['balance_after' => $coinplus]);
$juser["userfild"]["$from_id"]["invite"]="0";
$juser["userfild"]["$from_id"]["coin"]="0";
$juser["userfild"]["$from_id"]["setchannel"]="لا يوجد !";
$juser["userfild"]["$from_id"]["setmember"]="لا يوجد !";
$juser["userfild"]["$from_id"]["inviter"]="$start";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
}
elseif($cuser["userfild"]["$from_id"]["channeljoin"] == true){
$allchannel = $cuser["userfild"]["$from_id"]["channeljoin"];
for($z = 0;$z <= count($allchannel) -1;$z++){
$getchannel = bot_curl('getChatMember', ['chat_id' => '@'.$allchannel[$z], 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
break;
}
}
if($allchannel[$z] == true){
if(in_array($allchannel[$z], $user["channellist"])) {
     bot('answercallbackquery', [
              'callback_query_id' =>$membercall,
            'text' => "تم خصم 2 من $cdiamlao بسبب مغادرة @$allchannel[$z] القناة ⚠️",
            'show_alert' =>false
         ]);
unset($cuser["userfild"]["$from_id"]["channeljoin"][$z]);
$cuser["userfild"]["$from_id"]["channeljoin"]=array_values($cuser["userfild"]["$from_id"]["channeljoin"]);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$pluscoin = $coin - 2;
$cuser["userfild"]["$from_id"]["coin"]="$pluscoin";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
// --- Wallet ledger: خصم بسبب مغادرة قناة ---
wallet_log($from_id, 'deduct', 2, "خصم لمغادرة قناة @$allchannel[$z]", ['balance_after' => $pluscoin]);
}
}
if($allchannel[$z] == true){
if(in_array($allchannel[$z], $user["channellist"])) {
     bot('SendMessage', [
              'chat_id'=>$chat_id,
            'text' => "⚠️ عذرا عزيزي ❗️

لا يمكنك طلب اعضاء الي بعد الرجوه الي القنوات التي غادرت منها
▪️ملاحضة :- عند مغادرتك اي من القنوات يتم الخصم 2 $cdiamlanoo لكل قناة

▪️اشترك واستعيد قنواتك 🌐
@$allchannel[$z]

▪️بعدها اضغط على تحديث 🔄",
            'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"تحديث 🔄",'callback_data'=>'takecoin']]
                     ]
               ])
         ]);
unset($cuser["userfild"]["$from_id"]["channeljoin"][$z]);
$cuser["userfild"]["$from_id"]["channeljoin"]=array_values($cuser["userfild"]["$from_id"]["channeljoin"]);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$pluscoin = $coin - 2;
$cuser["userfild"]["$from_id"]["coin"]="$pluscoin";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
// --- Wallet ledger: خصم بسبب مغادرة قناة ---
wallet_log($from_id, 'deduct', 2, "خصم لمغادرة قناة @$allchannel[$z]", ['balance_after' => $pluscoin]);
}
}
}
if($data=="panel"){
$akl['3dd'][$from_id][$from_id]  = null;
$akl['mode'][$from_id]  = null;
$akl["tlbia"][$from_id] = null;
$akl["cointlb"][$from_id] += null;
$akl["s3rltlb"][$from_id] = null;
$akl['tp'][$from_id] = null;
$akl['coinn'] = null;
SETJSON($akl);
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$reply_markup = ne_home_keyboard();
foreach($button['codzer'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>"$cedname"]];
}
foreach($button['buttons'] as $ced => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>$ced]];
}
foreach($button['links'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'url'=>"$cedname"]];
}
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
$start = ne_home_text($chat_id, $from_id, $cuser, $message, $cdiamlaadf, $cdiamlanoo);
  bot('editmessagetext',[
          'chat_id'=>$chat_id,
       'message_id'=>$message_id,
'text'=>"$start",
'parse_mode'=>'HTML',
 'reply_markup'=>$reply_markup,              
    	]);
$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$cuser["userfild"]["$from_id"]["file"]="none";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
die;}
if($data == "sec_stats"){
$amrorders = file_exists('akl/orders.txt') ? count(array_filter(file('akl/orders.txt'))) : 0;
$statsArr  = ['pending'=>0,'processing'=>0,'completed'=>0,'canceled'=>0];
if(file_exists('akl/orders.txt')){
foreach(file('akl/orders.txt') as $ol){
$od = json_decode(trim($ol), true);
if(is_array($od) && isset($od['status']) && array_key_exists($od['status'], $statsArr)){
$statsArr[$od['status']]++;
}
}
}
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'parse_mode'=>'HTML',
'text'=>
"📊 <b>إحصائية الطلبات</b>
━━━━━━━━━━━━━━━━━
📦 إجمالي الطلبات: <b>{$amrorders}</b>
⏳ قيد الانتظار: <b>{$statsArr['pending']}</b>
⚙️ قيد التنفيذ: <b>{$statsArr['processing']}</b>
✅ مكتملة: <b>{$statsArr['completed']}</b>
❌ ملغية: <b>{$statsArr['canceled']}</b>
━━━━━━━━━━━━━━━━━
👥 عدد الأعضاء: <b>{$cunte}</b>",
'reply_markup'=>json_encode(['inline_keyboard'=>[
[['text'=>'رجوع ➡️','callback_data'=>'panel']],
]]),
]);
die;}
if($data == "xdmat") {
    if($chat_id == $sudo or $chat_id == $admin ) {
      bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "
*مرحبا بك في قسم تقديم خدمات البوت ☄*

*في هذا القسم يمكنك* اضافه خدمات و مسح خدمات و اضافه تطبيقات و مسح تطبيقات خدمات البوت كامله هنا *اختار ما تريد ايفل*

*قسم الخدمات -->* لخدمات البوت
*الاقسام الاساسية -->* لاقسام التطبيقات
        ",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
          'inline_keyboard' => [
            [["text" => "قسم الخدمات","callback_data"=>"qsmsa"]],
            [["text" => "الاقسام الاساسية","callback_data"=>"mr1"]],
            [["text" => "قسم نسخ الخدمات","callback_data"=>"codyser"]],
            [['text' => 'رجوع', 'callback_data' => "sitingbots"]],
          ]
        ])
      ]);
      $akl['mode'][$from_id] = null;
      $akl = json_encode($akl, 32 | 128 | 265);
      file_put_contents("akl/akl.json", $akl);
    }
  }
if($data == "codyser") {
      bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "*• مرحبا بك في قسم نسخ خدمات البوت ✨*

- يمكنك نسخ خدمات البوت الخاص بك الى ملف حيث يمكن لشخص اخر استخدامها .
        ",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
          'inline_keyboard' => [
            [["text" => "رفع نسخة","callback_data"=>"codoserue"],["text" => "عمل نسخة","callback_data"=>"codyseradd"]],
            [['text' => 'رجوع', 'callback_data' => "sitingbots"]],
          ]
        ])
      ]);
    }
  if($data == "codyseradd") {
      bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "*انتظر جاري الصنع*",
        'parse_mode' => "markdown",
      ]);
       bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "*- تم عمل نسخة الخدمات*",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
          'inline_keyboard' => [
            [['text' => 'رجوع', 'callback_data' => "sitingbots"]],
          ]
        ])
     ]);
$cidamra = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), -40);
$userbot = bot("getme")->result->username; 
$idbot = bot('getme',['bot'])->result->id;
$filezo = file_get_contents("../../filetos.txt");
$codosefier ="$filezo
$cidamra
$idbot";
file_put_contents("$userbot.tupac","$codosefier");
bot ("senddocument", [
"chat_id" => $chat_id, 
"document" => new CURLFile("$userbot.tupac"),
'caption'=>"- ملف خدمات البوت  : [@$userbot]

*~ يمكنك رفع الملف في بوت اخر و سيتم نسخ الخدمات*",
'parse_mode' => "markdown",
'reply_to_message_id'=>$message->message_id, 
]);
unlink("$userbot.tupac");
file_put_contents("fielbotco.txt","$cidamra");
}
  if($data == "codoserue") {
      bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "*- ارسل ملف نسخة الخدمات الان!*",
        'parse_mode' => "markdown",
 'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>'رجوع' ,'callback_data'=>"codyser"]],
      ]
    ])
      ]);
file_put_contents("edid/idfilebot $chat_id.txt","on");
}
if ($update->message->document && file_get_contents("edid/idfilebot $chat_id.txt") == "on") {
    $file_extension = pathinfo($update->message->document->file_name, PATHINFO_EXTENSION);
    $document = $message->document ?? null;
    $file_id = $document->file_id;
    $file_path = bot("getFile", [
        "file_id" => $file_id,
    ])->result->file_path;
    $download_link = "https://api.telegram.org/file/bot" . $VISCODEV . "/" . $file_path;
    $file_content = file_get_contents($download_link);
    $lines = explode("\n", $file_content);
    $line20 = $lines[19];
    $line21 = $lines[20];
    $filecodbo = file_get_contents("../$line21/fielbotco.txt");
    $file_coodls = file_get_contents($download_link);   
if (trim($filecodbo) != trim($line20)) {
    bot('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "*يوجد خطاء ما في النسخه يمكنك اعاده نسخ خدماتك و ارسال الملف الجديد *",
        'reply_to_message_id' => $message_id,
         'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>'رجوع' ,'callback_data'=>"codyser"]],
    ]
    ])
    ]);
    unlink("edid/idfilebot $chat_id.txt");
    return;
}
    if ($file_extension == "tupac") {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "*• تم رفع نسخة الخدمات !*",
            'reply_to_message_id' => $message_id,
            'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>'رجوع' ,'callback_data'=>"codyser"]],
      ]
    ])
        ]);
        $filecoarbo = file_get_contents("../$line21/akl/akl.json");
        unlink("akl/akl.json");
        file_put_contents("akl/akl.json","$filecoarbo");
        unlink("edid/idfilebot $chat_id.txt");
    } else {
        bot('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "*• يجب ان يكون الملف .tupac !*",
            'reply_to_message_id' => $message_id,
                     'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>'رجوع' ,'callback_data'=>"codyser"]],
      ]
    ])
        ]);
        unlink("edid/idfilebot $chat_id.txt");
    }
}
if($data == "qsmsa"){
  $key = ['inline_keyboard' => []];
  foreach ($akl['qsm'] as $i) {
    $nameq = explode("-",$i)[0];
    $i = explode("-",$i)[1];
    if($akl['IFWORK>'][$i] != "NOT"){
    $key['inline_keyboard'][] = [['text' => "$nameq", 'callback_data' => "edits|$i"], ['text' => "🗑", 'callback_data' => "delets|$i"]];
  }
}
  $key['inline_keyboard'][] = [['text' => "+ اضافه قسم جديد", 'callback_data' => "addqsm"]];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "xdmat"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "*اقسام التطبيقات المضافه في البوت ☄*

يمكنك اضافه اقسام او مسح اقسام من هنا

*اضغط علي الخدمه للتحكم به 🖲*",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = null;
  SETJSON($akl);
}
if(explode("|",$data)[0] == "delets"){
  $akl['IFWORK>'][explode("|",$data)[1]] = "NOT";
  $akl['mode'][$from_id] = null;
  SETJSON($akl);
  $key = ['inline_keyboard' => []];
  foreach ($akl['qsm'] as $i) {
    $nameq = explode("-",$i)[0];
    $i = explode("-",$i)[1];
    if($akl['IFWORK>'][$i] != "NOT"){
    $key['inline_keyboard'][] = [['text' => "$nameq", 'callback_data' => "edits|$i"], ['text' => "🗑", 'callback_data' => "delets|$i"]];
  }
}
  $key['inline_keyboard'][] = [['text' => "+ اضافه قسم جديد", 'callback_data' => "addqsm"]];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "xdmat"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "*اقسام التطبيقات المضافه في البوت ☄*

يمكنك اضافه اقسام او مسح اقسام من هنا

*اضغط علي الخدمه للتحكم به 🖲*",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
}

if(explode("|",$data)[0]=="edits"){
  $key = ['inline_keyboard' => []];
  $vv = rand(100,900);
  foreach ( $akl['xdmaxs'][explode("|",$data)[1]] as $hjjj => $i) {
    $key['inline_keyboard'][] = [['text' => "$i", 'callback_data' => "editss|".explode("|",$data)[1]."|$hjjj"], ['text' => "🗑", 'callback_data' => "delets|".explode("|",$data)[1]."|$hjjj"]];
  }

  $bbot = explode("|",$data)[1];
  $key['inline_keyboard'][] = [['text' => "+ اضافه خدمات الي هذا القسم", 'callback_data' => "add|$bbot"]];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "xdmat"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
    خدمات قسم *".$akl['NAMES'][explode("|",$data)[1]]."*
    
    يمكنك الضغط علي اي خدمه للتعديل
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = null;
  $akl['idTIMER'][$vv] = $akl['NAMES'][explode("|",$data)[1]];
  SETJSON($akl);
}

if(explode("|",$data)[0]=="editss"){
  $key = ['inline_keyboard' => []];
  $key['inline_keyboard'][] = [['text' => "تعيين السعر", 'callback_data' => "setprice|".explode("|",$data)[1]."|".explode("|",$data)[2]],['text' => "تعيين الايدي", 'callback_data' =>  "setid|".explode("|",$data)[1]."|".explode("|",$data)[2]]];
  $key['inline_keyboard'][] = [['text' => "تعيين ادني حد ", 'callback_data' =>  "setmin|".explode("|",$data)[1]."|".explode("|",$data)[2]]];
  $key['inline_keyboard'][] = [['text' => "تعيين اقصي حد ", 'callback_data' =>  "setmix|".explode("|",$data)[1]."|".explode("|",$data)[2]]];
  $key['inline_keyboard'][] = [['text' => "تعيين الوصف", 'callback_data' =>  "setdes|".explode("|",$data)[1]."|".explode("|",$data)[2]]];
  $key['inline_keyboard'][] = [['text' => "حذف الخدمه", 'callback_data' =>  "delt|".explode("|",$data)[1]."|".explode("|",$data)[2]]];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "xdmat"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
     خدمه ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]."

 في قسم ".$akl['NAMES'][explode("|",$data)[1]]."

يمكنك التعديل علي الخدمه من هنا
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = null;
  SETJSON($akl);
}

if(explode("|",$data)[0]=="delt"){
  $key = ['inline_keyboard' => []];
  $vv = rand(100,900);

  foreach ( $akl['xdmaxs'][explode("|",$data)[1]] as $hjjj => $i) {

    $key['inline_keyboard'][] = [['text' => "$i", 'callback_data' => "editss|".explode("|",$data)[1]."|$hjjj"], ['text' => "🗑", 'callback_data' => "delets|".explode("|",$data)[1]."|$hjjj"]];
  }

  $bbot = explode("|",$data)[1];
  $key['inline_keyboard'][] = [['text' => "+ اضافه خدمات الي هذا القسم", 'callback_data' => "add|$bbot"]];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "sitingbots"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
    خدمات قسم *".$akl['NAMES'][explode("|",$data)[1]]."*
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]] = null;
  $akl['mode'][$from_id] = null;
  $akl['idTIMER'][$vv] = $akl['NAMES'][explode("|",$data)[1]];
  SETJSON($akl);
}

$akl = json_decode(file_get_contents("akl/akl.json"),true);
if(explode("|",$data)[0]=="setprice"){
  $key = ['inline_keyboard' => []];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "sitingbots"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
     خدمه ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]." 

في قسم ".$akl['NAMES'][explode("|",$data)[1]]."
    ارسل سعر الخدمه الان ؟
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = "setprice";
  $akl['MGS'][$from_id] = "MGS|".explode("|",$data)[1]."|".explode("|",$data)[2];
  SETJSON($akl);
}
if(explode("|",$data)[0]=="setmin"){
  $key = ['inline_keyboard' => []];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "sitingbots"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
     خدمه ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]." 

في قسم ".$akl['NAMES'][explode("|",$data)[1]]."
    ارسل ادني عدد للخدمه الان؟ 
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = "setmin";
  $akl['MGS'][$from_id] = "MGS|".explode("|",$data)[1]."|".explode("|",$data)[2];
  SETJSON($akl);
}

if(is_numeric($text) and $akl['mode'][$from_id] == "setmin"){
  if($chat_id == $sudo or $chat_id == $admin ) {
    $bA = $text / 1000;
    bot("sendmessage",[
      "chat_id" => $chat_id,
      "text" => "
      تم تعيين ادني حد *". $akl['xdmaxs'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]]."* في قسم *".$akl['NAMES'][explode("|",$akl['MGS'][$from_id])[1]]."*
      ",
      "parse_mode"=>"markdown",
    ]);
    $akl['mode'][$from_id] = null;
    $akl['min'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]] = $text ;
    $akl['MGS'][$from_id] = null;
    SETJSON($akl);
  }
}

if(explode("|",$data)[0]=="setkey"){
  $key = ['inline_keyboard' => []];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "sitingbots"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
     خدمه ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]." 

في قسم ".$akl['NAMES'][explode("|",$data)[1]]."
    ارسل API KEY الموقع الان؟ 
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = "setkey";
  $akl['MGS'][$from_id] = "MGS|".explode("|",$data)[1]."|".explode("|",$data)[2];
  SETJSON($akl);
}
$akl = json_decode(file_get_contents("akl/akl.json"),true);
if($text and $akl['mode'][$from_id] == "setkey"){
  if($chat_id == $sudo or $chat_id == $admin ) {
    $bA = $text / 1000;
    bot("sendmessage",[
      "chat_id" => $chat_id,
      "text" => "
      تم تعيين API KEY *". $akl['xdmaxs'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]]."* في قسم *".$akl['NAMES'][explode("|",$akl['MGS'][$from_id])[1]]."*
      ",
      "parse_mode"=>"markdown",
    ]);
    $akl['mode'][$from_id] = null;
    $akl['key'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]] = $text ;
    $akl['MGS'][$from_id] = null;
    SETJSON($akl);
  }
}
if(explode("|",$data)[0]=="setmix"){
  $key = ['inline_keyboard' => []];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "sitingbots"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
     خدمه ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]." 

في قسم ".$akl['NAMES'][explode("|",$data)[1]]."
    ارسل اقصي حد للخدمه الان؟ 
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = "setmix";
  $akl['MGS'][$from_id] = "MGS|".explode("|",$data)[1]."|".explode("|",$data)[2];
  SETJSON($akl);
}

if(is_numeric($text) and $akl['mode'][$from_id] == "setmix"){
  if($chat_id == $sudo or $chat_id == $admin ) {
    bot("sendmessage",[
      "chat_id" => $chat_id,
      "text" => "
      تم تعيين اقصي حد *". $akl['xdmaxs'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]]."* في قسم *".$akl['NAMES'][explode("|",$akl['MGS'][$from_id])[1]]."*
      ",
      "parse_mode"=>"markdown",
    ]);
    $akl['mode'][$from_id] = null;
    $akl['mix'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]] = $text ;
    $akl['MGS'][$from_id] = null;
    SETJSON($akl);
  }
}
if(is_numeric($text) and $akl['mode'][$from_id] == "setprice"){
  if($chat_id == $sudo or $chat_id == $admin ) {
    $bA = $text / 1000;
    bot("sendmessage",[
      "chat_id" => $chat_id,
      "text" => "
      تم تعيين سعر *". $akl['xdmaxs'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]]."* في قسم *".$akl['NAMES'][explode("|",$akl['MGS'][$from_id])[1]]."*
      ",
      "parse_mode"=>"markdown",
    ]);
    $akl['mode'][$from_id] = null;
    $akl['S3RS'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]] = $bA;
    $akl['MGS'][$from_id] = null;
    SETJSON($akl);
  }
}

if(explode("|",$data)[0]=="setWeb"){
  $key = ['inline_keyboard' => []];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "sitingbots"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
     خدمه ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]." 

في قسم ".$akl['NAMES'][explode("|",$data)[1]]."

    ارسل رابط الموقع؟ 
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = "setWeb";
  $akl['MGS'][$from_id] = "MGS|".explode("|",$data)[1]."|".explode("|",$data)[2];
  SETJSON($akl);
}

if($text and $akl['mode'][$from_id] == "setWeb"){
  if($chat_id == $sudo or $chat_id == $admin ) {
$IMbot = parse_url($text);
$INbot = $IMbot['host'];
    bot("sendmessage",[
      "chat_id" => $chat_id,
      "text" => "
      تم تعيين ربط موقع *". $akl['xdmaxs'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]]."* في قسم *".$akl['NAMES'][explode("|",$akl['MGS'][$from_id])[1]]."*
      ",
      "parse_mode"=>"markdown",
    ]);
    $akl['mode'][$from_id] = null;
    $akl['Web'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]] = $INbot;
    $akl['MGS'][$from_id] = null;
    SETJSON($akl);
  }
}
if(explode("|",$data)[0]=="setdes"){
  $key = ['inline_keyboard' => []];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "sitingbots"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
     خدمه ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]." 

في قسم ".$akl['NAMES'][explode("|",$data)[1]]."

    ارسل وصف الخدمه الان؟
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = "setdes";
  $akl['MGS'][$from_id] = "MGS|".explode("|",$data)[1]."|".explode("|",$data)[2];
  SETJSON($akl);
}

if($text and $akl['mode'][$from_id] == "setdes"){
  if($chat_id == $sudo or $chat_id == $admin ) {
    
    bot("sendmessage",[
      "chat_id" => $chat_id,
      "text" => "
      تم تعيين وصف *". $akl['xdmaxs'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]]."* في قسم *".$akl['NAMES'][explode("|",$akl['MGS'][$from_id])[1]]."*
      ",
      "parse_mode"=>"markdown",
    ]);
    $akl['mode'][$from_id] = null;
    $akl['WSF'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]] = $text;
    $akl['MGS'][$from_id] = null;
    SETJSON($akl);
  }
}
if(explode("|",$data)[0]=="setid"){
  $key = ['inline_keyboard' => []];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "sitingbots"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "
    *
     خدمه ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]." 

في قسم ".$akl['NAMES'][explode("|",$data)[1]]."

    ارسل ايدي الخدمه الان ؟
    *
    ",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = explode("|",$data)[0];
  $akl['MGS'][$from_id] = "MGS|".explode("|",$data)[1]."|".explode("|",$data)[2];
  SETJSON($akl);
}
if(is_numeric($text) and $akl['mode'][$from_id] == "setid"){
  if($chat_id == $sudo or $chat_id == $admin ) {
    bot("sendmessage",[
      "chat_id" => $chat_id,
      "text" => "
      تم تعيين ايدي خدمه *". $akl['xdmaxs'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]]."* في قسم *".$akl['NAMES'][explode("|",$akl['MGS'][$from_id])[1]]."*
      ",
      "parse_mode"=>"markdown",
    ]);
    $akl['mode'][$from_id] = null;
    $akl['IDSSS'][explode("|",$akl['MGS'][$from_id])[1]][explode("|",$akl['MGS'][$from_id])[2]] = $text;
    $akl['MGS'][$from_id] = null;
    SETJSON($akl);
  }
}
  if($data == "addqsm") {
    if($chat_id == $sudo or $chat_id == $admin ) {
      bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "
        *
        ارسل اسم القسم الان مثلا خدمات تيليجرام
        *
        ",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
          'inline_keyboard' => [
            [['text' => 'رجوع', 'callback_data' => "xdmat"]],
          ]
        ])
      ]);
      $akl['mode'][$from_id] = $data;
      $akl = json_encode($akl, 32 | 128 | 265);
      file_put_contents("akl/akl.json", $akl);
    }
  }
  if($text and $akl["mode"][$from_id] == "addqsm") {
    if($chat_id == $sudo or $chat_id == $admin ) {
      $bbot = "bot" . rand(0, 999999999999999);
      bot("sendmessage", [
        "chat_id" => $chat_id,
        "text" => "*تم اضافه القسم بنجاح
        
        - اسم القسم *: [$text]
",
        "parse_mode" => "markdown",
        'reply_markup' => json_encode([
          'inline_keyboard' => [
            [['text' => 'للدخول لهذا القسم', 'callback_data' => "mamrmr|$bbot"]],
          ]
        ])
      ]);
      $akl['qsm'][] = $text . '-' . $bbot;
      $akl['NAMES'][$bbot] = $text;
      $akl['mode'][$from_id] = null;
      $akl = json_encode($akl, 32 | 128 | 265);
      file_put_contents("akl/akl.json", $akl);
    }
  }
if($data=="mr1"){
$insta = file_get_contents("edid/mr_insta.txt");
$tektok = file_get_contents("edid/mr_tektok.txt");
$telegram =file_get_contents("edid/mr_telegram.txt");
$yoteop =file_get_contents("edid/mr_yoteop.txt");
$faesbook =file_get_contents("edid/mr_faesbook.txt");
$twetr =file_get_contents("edid/mr_twetr.txt");
$free =file_get_contents("edid/mr_free.txt");
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*مرحبا بك في قسم الخدمات الاساسية 

يمكنك من هنا فتح و قفل الاقسام المراد اظهاره من تضمن الخدمات
*",
"parse_mode" => "markdown",
        'reply_markup' => json_encode([
          'inline_keyboard' => [
    [['text'=>"انستغرام 💜 : $insta",'callback_data'=>"null"],['text'=>"فتح",'callback_data'=>'open_insta'],['text'=>"قفل",'callback_data'=>'off_insta']],       
    [['text'=>"تيكتوك 🖤 : $tektok",'callback_data'=>"null"],['text'=>"فتح",'callback_data'=>'open_tektok'],['text'=>"قفل",'callback_data'=>'off_tektok']],
    [['text'=>"تيليغرام 💙 : $telegram",'callback_data'=>"null"],['text'=>"فتح",'callback_data'=>'open_telegram'],['text'=>"قفل",'callback_data'=>'off_telegram']],
    [['text'=>"يوتيوب ❤ : $yoteop",'callback_data'=>"null"],['text'=>"فتح",'callback_data'=>'open_yoteop'],['text'=>"قفل",'callback_data'=>'off_yoteop']],
    [['text'=>"فيسبوك 🤍 : $faesbook",'callback_data'=>"null"],['text'=>"فتح",'callback_data'=>'open_faesbook'],['text'=>"قفل",'callback_data'=>'off_faesbook']],
    [['text'=>"تويتر 💙 : $twetr",'callback_data'=>"null"],['text'=>"فتح",'callback_data'=>'open_twetr'],['text'=>"قفل",'callback_data'=>'off_twetr']],
    [['text'=>"المجانية 🎁 : $free",'callback_data'=>"null"],['text'=>"فتح",'callback_data'=>'open_free'],['text'=>"قفل",'callback_data'=>'off_free']],
    [['text'=>" • رجوع •  ",'callback_data'=>"home"]],
]
])]);
}
$bbot = "bot" . rand(0, 999999999999999);
if($data == 'open_insta'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح القسم ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text' => 'للدخول لهذا القسم', 'callback_data' => "mamrmr|$bbot"]],[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_insta.txt","✅");
file_put_contents("edid/cood_insta.txt","$bbot");
$akl['qsm'][] = "انستغرام 💜" . '-' . $bbot;
$akl['NAMES'][$bbot] = "انستغرام 💜";
$akl['mode'][$from_id] = null;
$akl = json_encode($akl, 32 | 128 | 265);
file_put_contents("akl/akl.json", $akl);
}
if($data == 'off_insta'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل القسم ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_insta.txt","❌");
unlink("edid/cood_insta.txt");
}
if($data == 'open_tektok'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح القسم ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text' => 'للدخول لهذا القسم', 'callback_data' => "mamrmr|$bbot"]],[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_tektok.txt","✅");
file_put_contents("edid/cood_tektok.txt","$bbot");
$akl['qsm'][] = 'تيكتوك 🖤' . '-' . $bbot;
$akl['NAMES'][$bbot] = 'تيكتوك 🖤';
$akl['mode'][$from_id] = null;
$akl = json_encode($akl, 32 | 128 | 265);
file_put_contents("akl/akl.json", $akl);
}
if($data == 'off_tektok'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل القسم ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_tektok.txt","❌");
unlink("edid/cood_tektok.txt");
}
if($data == 'open_telegram'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح القسم ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text' => 'للدخول لهذا القسم', 'callback_data' => "mamrmr|$bbot"]],[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_telegram.txt","✅");
file_put_contents("edid/cood_telegram.txt","$bbot");
$akl['qsm'][] = 'تيليغرام 💙' . '-' . $bbot;
$akl['NAMES'][$bbot] = 'تيليغرام 💙';
$akl['mode'][$from_id] = null;
$akl = json_encode($akl, 32 | 128 | 265);
file_put_contents("akl/akl.json", $akl);
}
if($data == 'off_telegram'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل القسم ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_telegram.txt","❌");
unlink("edid/cood_telegram.txt");
}
if($data == 'open_yoteop'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح القسم ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text' => 'للدخول لهذا القسم', 'callback_data' => "mamrmr|$bbot"]],[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_yoteop.txt","✅");
file_put_contents("edid/cood_yoteop.txt","$bbot");
$akl['qsm'][] = 'يوتيوب ❤' . '-' . $bbot;
$akl['NAMES'][$bbot] = 'يوتيوب ❤';
$akl['mode'][$from_id] = null;
$akl = json_encode($akl, 32 | 128 | 265);
file_put_contents("akl/akl.json", $akl);
}
if($data == 'off_yoteop'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل القسم ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_yoteop.txt","❌");
unlink("edid/cood_yoteop.txt");
}
if($data == 'open_faesbook'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح القسم ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text' => 'للدخول لهذا القسم', 'callback_data' => "mamrmr|$bbot"]],[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_faesbook.txt","✅");
file_put_contents("edid/cood_faesbook.txt","$bbot");
$akl['qsm'][] = 'فيسبوك 🤍' . '-' . $bbot;
$akl['NAMES'][$bbot] = 'فيسبوك 🤍';
$akl['mode'][$from_id] = null;
$akl = json_encode($akl, 32 | 128 | 265);
file_put_contents("akl/akl.json", $akl);
}
if($data == 'off_faesbook'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل القسم ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_faesbook.txt","❌");
unlink("edid/cood_faesbook.txt");
}
if($data == 'open_twetr'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح القسم ✅*

",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text' => 'للدخول لهذا القسم', 'callback_data' => "mamrmr|$bbot"]],[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_twetr.txt","✅");
file_put_contents("edid/cood_twetr.txt","$bbot");
$akl['qsm'][] = 'تويتر 💙' . '-' . $bbot;
$akl['NAMES'][$bbot] = 'تويتر 💙';
$akl['mode'][$from_id] = null;
$akl = json_encode($akl, 32 | 128 | 265);
file_put_contents("akl/akl.json", $akl);
}
if($data == 'off_twetr'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل القسم ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_twetr.txt","❌");
unlink("edid/cood_twetr.txt");
}
if($data == 'open_free'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح القسم ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text' => 'للدخول لهذا القسم', 'callback_data' => "mamrmr|$bbot"]],[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/mr_free.txt","✅");
file_put_contents("edid/cood_free.txt","$bbot");
$akl['qsm'][] = 'الخدمات المجانية 🎁' . '-' . $bbot;
$akl['NAMES'][$bbot] = 'الخدمات المجانية 🎁';
$akl['mode'][$from_id] = null;
$akl = json_encode($akl, 32 | 128 | 265);
file_put_contents("akl/akl.json", $akl);
}
if($data == 'off_free'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل القسم ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'mr1']],
 ] 
])]);
file_put_contents("edid/cood_free.txt","❌");
unlink("edid/cood_free.txt");
}
  $UUS = explode("|", $data);
  if($UUS[0] == "mamrmr") {
    if($chat_id == $sudo or $chat_id == $admin ) {
      $bbot = $UUS[1];
      if($akl['NAMES'][$bbot] != null) {
        $key = ['inline_keyboard' => []];
        foreach ($akl['xdmaxs'][$bbot] as $i) {
          $name = $akl['nam'][$i];
          $ids = $akl['ids'][$i];
          $key['inline_keyboard'][] = [['text' => "$name", 'callback_data' => "edits:$i"], ['text' => "🗑", 'callback_data' => "edits:$i"]];
        }
        $key['inline_keyboard'][] = [['text' => "+ اضافه خدمه", 'callback_data' => "add|$bbot"]];
        $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "xdmat"]];
       
        bot('EditMessageText', [
          'chat_id' => $chat_id,
          'message_id' => $message_id,
          'text' => "
          *
          مرحبا بك في هذا القسم " . $akl['NAMES'][$bbot] . "
          *
          ",
          'parse_mode' => "markdown",
          'reply_markup' => json_encode($key),
        ]);
      }
    }
  }

  if($UUS[0]=="add"){
    if($chat_id == $sudo or $chat_id == $admin ) {
      bot('EditMessageText', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "
        *        ارسل اسم الخدمه لاضافاتها *
        ",
        'parse_mode' => "markdown",
        'reply_markup' => json_encode([
          'inline_keyboard' => [
            [['text' => 'رجوع', 'callback_data' => "xdmat"]],
          ]
        ])
      ]);
      $akl['mode'][$from_id] = "adders";
      $akl['idxs'][$from_id] = $UUS[1];
      $akl = json_encode($akl, 32 | 128 | 265);
      file_put_contents("akl/akl.json", $akl);
    }
  }

if($text and  $akl['mode'][$from_id] == "adders" &&  $text !='/start'){
  if($chat_id == $sudo or $chat_id == $admin ) {
    $bbot = $akl['idxs'][$from_id];
    $bsf = rand(33,33333);
    $j=1;
    foreach ( $akl['xdmaxs'][$bbot] as $hjjj => $i) {
$j+=1;
    }
    bot("sendmessaGE",[
      "chat_id" => $chat_id,
      "text" => "
      تم اضافه هذا الخدمه الي قسم *".$akl['NAMES'][$bbot]."*
      ",
      "parse_mode" => "markdown",
      'reply_markup' => json_encode([
        'inline_keyboard' => [
          [['text' => 'دخول الي الخدمه', 'callback_data' => "editss|".$bbot."|$hjjj"]],
          [['text' => 'رجوع', 'callback_data' => "xdmat"]],
        ]
      ])
    ]);
    $akl['mode'][$from_id] = null;
    $akl['idxs'][$from_id] = null;
    $akl['xdmaxs'][$bbot][] = $text;
    $akl= json_encode($akl,32|128|265);
    file_put_contents("akl/akl.json",$akl);
  }
}
if($data == "VISCODEV" ) {
if($chat_id == $sudo or $chat_id == $admin ){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال التوكن الان ☄*

[طريقه جلب التوكن ✨](https://t.me/ViSCo/7811)",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>"رجوع",'callback_data'=>"sitingbots" ]],
      ]
    ])
]);
$akl['mode'][$from_id]  = "sVISCODEV";
SETJSON($akl);
} 
}
$rnd=rand(999,99999);
if($text and $akl['mode'][$from_id] == "sVISCODEV" &&  $text !='/start' ) {
	if($chat_id == $sudo or $chat_id == $admin ){
	
	bot('sendMessage',[
   'chat_id'=>$chat_id,
   'text'=>"*تم الحفظ ✅*", 
  'parse_mode'=>"markdown",
  'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>"رجوع",'callback_data'=>"sitingbots" ]],
      ]
    ])
]);
$akl['mode'][$from_id]  = null;
$akl["sVISCODEV"]  = $text;
SETJSON($akl);
}return false;}
if($data == "SiteDomen"  ) {
if($chat_id == $sudo or $chat_id == $admin ){
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*قوم بارسال موقع الرشق الان ☄*

مثل : kd1s.com",
'parse_mode'=>"markdown",
'disable_web_page_preview'=>true,
'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>"رجوع",'callback_data'=>"sitingbots" ]],
      ]
    ])
]);
$akl['mode'][$from_id]  = "SiteDomen";
SETJSON($akl);
} 
}
$rnd=rand(999,99999);
if($text and $akl['mode'][$from_id] == "SiteDomen" &&  $text !='/start' ) {
	if($chat_id == $sudo or $chat_id == $admin ){
	$adomenbot = str_replace("http:// ","",$text);
	$adomenbott = str_replace("https:// ","",$adomenbot);
	$IMbot = parse_url($adomenbott);
    $INbot = $IMbot['host'];
	bot('sendMessage',[
   'chat_id'=>$chat_id,
   'text'=>"*تم الحفظ ✅*", 
  'parse_mode'=>"markdown",
  'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>"رجوع",'callback_data'=>"sitingbots" ]],
      ]
    ])
]);
$akl['mode'][$from_id]  = null;
$akl["sSite"]  = $adomenbott;
SETJSON($akl);
}return false;}
$akl = json_decode(file_get_contents("akl/akl.json"),true);
 if($data == "takemember" and $nzambot == '❌') {
 	    foreach ($akl['qsm'] as $i) {
      $nameq = explode("-",$i)[0];
      $i = explode("-",$i)[1];
      $insta = file_get_contents("edid/cood_insta.txt");
      $tektok = file_get_contents("edid/cood_tektok.txt");
      $telegram = file_get_contents("edid/cood_telegram.txt");
      $yoteop = file_get_contents("edid/cood_yoteop.txt");
      $faesbook = file_get_contents("edid/cood_faesbook.txt");
      $twetr = file_get_contents("edid/cood_twetr.txt");
      $free = file_get_contents("edid/cood_free.txt");
    $key = ['inline_keyboard' => []];
    if(file_exists("edid/cood_insta.txt")) {
    $mr_insta = "انستغرام 💜";
}
    if(file_exists("edid/cood_tektok.txt")) {
    $mr_tektok = "تيكتوك 🖤";
}
if(file_exists("edid/cood_telegram.txt")) {
    $mr_telegram = "تيليغرام 💙";
}
if(file_exists("edid/cood_yoteop.txt")) {
    $mr_yoteop = "يوتيوب ❤";
}
if(file_exists("edid/cood_faesbook.txt")) {
    $mr_faesbook = "فيسبوك 🤍";
}
if(file_exists("edid/cood_twetr.txt")) {
    $mr_twetr = "تويتر 💙";
}
if(file_exists("edid/cood_free.txt")) {
    $mr_free = "الخدمات المجانية 🎁";
}
    $key['inline_keyboard'][] = [['text'=>"$mr_insta",'callback_data'=>"botENT|$insta"],['text'=>"$mr_tektok",'callback_data'=>"botENT|$tektok"]];
    $key['inline_keyboard'][] = [['text'=>"$mr_telegram",'callback_data'=>"botENT|$telegram"]];
    $key['inline_keyboard'][] = [['text'=>"$mr_yoteop",'callback_data'=>"botENT|$yoteop"],['text'=>"$mr_faesbook",'callback_data'=>"botENT|$faesbook"]];
    $key['inline_keyboard'][] = [['text'=>"$mr_twetr",'callback_data'=>"botENT|$twetr"]];
    $key['inline_keyboard'][] = [['text'=>"$mr_free",'callback_data'=>"botENT|$free"]];
    foreach ($akl['qsm'] as $i) {
      $nameq = explode("-",$i)[0];
      $i = explode("-",$i)[1];
      $wordsToRemove = array("انستغرام 💜", "تيليغرام 💙", "تيكتوك 🖤", "يوتيوب ❤", "فيسبوك 🤍", "تويتر 💙", "الخدمات المجانية 🎁");
      $nameq = str_ireplace($wordsToRemove, "", $nameq);
      $nameq = $nameq;
      if($akl['IFWORK>'][$i] != "NOT"){
      $key['inline_keyboard'][] = [['text' => "$nameq", 'callback_data' => "botENT|$i"]];
    }
  }}
    $key['inline_keyboard'][] =  [['text'=>"تمويل تليجرام حقيقي عراقيين 100%",'callback_data'=>'takememberto']];
    $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "panel"]];
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
👥] $cdiamlao : $coin
🔢] ايديك : $from_id 
",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode($key),
]);
} 
$tmwel = file_exists("edid/tmwel.txt") ? file_get_contents("edid/tmwel.txt") : "✅";
 if($data == "takemember" and $nzambot == '✅') {
 if($tmwel == '✅'){
if($coin >= $adna_coins){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"⚠️] ارفع البوت ادمن في قناتك

و ارسل معرف القناة مثل : $chadmin",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
$cuser["userfild"]["$from_id"]["file"]="setchannel";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
return false;}
else
{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"
• عليك تجميع $cdiamlaadf اكثر من $adna_coins $cdiamlanoo ⚠️
",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
            [['text'=>"تجميع {$cdiamlaadf}💰",'callback_data'=>'takecoinn']],
            [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
                                 ]
               ])
			   ]);
}}else{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"*نعتذر تم قفل استقبال القنوات في الوقت الحالي عد بعد قليل ❌*",
               'parse_mode' => "markdown",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
            [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
                                 ]
               ])
			   ]);}}

if(explode("|",$data)[0]=="botENT"){
  $key = ['inline_keyboard' => []];
  $vv = rand(100,900);
  foreach ( $akl['xdmaxs'][explode("|",$data)[1]] as $hjjj => $i) {
    $key['inline_keyboard'][] = [['text' => "$i", 'callback_data' => "type|".explode("|",$data)[1]."|$hjjj"]];
  }
  $bbot = explode("|",$data)[1];
  $key['inline_keyboard'][] = [['text' => "رجوع", 'callback_data' => "panel"]];
  bot('EditMessageText', [
    'chat_id' => $chat_id,
    'message_id' => $message_id,
    'text' => "    ✳️] اختر الخدمات التي تريدها :",
    'parse_mode' => "markdown",
    'reply_markup' => json_encode($key),
  ]);
  $akl['mode'][$from_id] = null;
  SETJSON($akl);
}
$akl = json_decode(file_get_contents("akl/akl.json"),true);
if($e[0] == "type"){
	$akl = json_decode(file_get_contents("akl/akl.json"),true);
	if($e[1] == "thbt" or $e[1] == "mthbt" or $e[1] == "hq" ) {
		$typee = "متابعين" ;
		} elseif($e[1] == "view"){
			$typee = "مشاهدات";
			}elseif($e[1] == "like"){
				$typee = "لايكات";
				}
		
		if($e[1] == "thbt") {
			$s3r = 1;
			
			}
			if($e[1] == "mthbt") {
			$s3r = 2;
			}
			if($e[1] == "hq") {
			$s3r = 0.2;
			}
			if($e[1] == "view") {
			$s3r = 25;
			}
			
			if($e[1] == "like") {
			$s3r = 18;
			}
			
			if($akl["s3rr"][$e[1]] !=null) {
			$s3r = $akl["s3rr"][$e[1]] ;
			}
        $akl = json_decode(file_get_contents("akl/akl.json"),true);
        $s3r = $akl['S3RS'][explode("|",$data)[1]][explode("|",$data)[2]];
        $web = ($akl['Web'][explode("|",$data)[1]][explode("|",$data)[2]]??$akl["sSite"]) ;
        $s3r = ($s3r ?? "1");
        $key = ($akl['key'][explode("|",$data)[1]][explode("|",$data)[2]] ?? $akl["sVISCODEV"]);
        $mix = ($akl['mix'][explode("|",$data)[1]][explode("|",$data)[2]] ?? "1000");
        $min = ($akl['min'][explode("|",$data)[1]][explode("|",$data)[2]] ?? "100");
        $g= $s3r * 1000;
        $akl = json_decode(file_get_contents("akl/akl.json"),true);
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"✳️] اسم الخدمة : ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]."

💰] السعر : ". $g ." $cdiamlanoo لكل 1000

🔅] اقل طلب  : $min

🔆] اكبر طلب : $mix

*️⃣] ارسل الكمية التي تريد طلبها :",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
[['text'=>'رجوع' ,'callback_data'=>"panel"]],
]])
]);
$akl['IDX'][$from_id]  =  $akl['IDSSS'][explode("|",$data)[1]][explode("|",$data)[2]];
$akl['WSFV'][$from_id]  =  $akl['WSF'][explode("|",$data)[1]][explode("|",$data)[2]];
$akl['S3RS'][$from_id]  =  $s3r;
$akl['web'][$from_id]  =  $web;
$akl['key'][$from_id]  =  $key;
$akl['min_mix'][$from_id]  = "$min|$mix" ;
$akl['SB1'][$from_id]  =  explode("|",$data)[1];
$akl['mode'][$from_id]  =  "SETd";
$akl['SB2'][$from_id]  =  explode("|",$data)[2];
$akl["="][$from_id] = $akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]];
SETJSON($akl);
} 
if($e[0] == "kmiat"){
$s3r = $akl['S3RS'][$from_id];
$s3r = ($s3r ?? "1");
$g= $s3r * 1000;
bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
👮🏽] اسم الخدمة : ".$akl['xdmaxs'][explode("|",$data)[1]][explode("|",$data)[2]]."

💰] السعر : ". $g ." $cdiamlanoo لكل 1000

🦾] اختر الكمية التي تريد طلبها :
", 
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([ 
'inline_keyboard'=>[
  [['text'=>'السعر' ,'callback_data'=>"type|$thbt"], ['text'=>'العدد' ,'callback_data'=>"type|$mthbt"]],
  [['text'=>"$ ".$nm.$s3r*1000,'callback_data'=>"to|1000|$e[1]"], ['text'=>'1000 $' ,'callback_data'=>"to|1000|$e[1]"]],
  [['text'=>"$ ".$nm.$s3r*2000,'callback_data'=>"to|2000|$e[1]"], ['text'=>'2000 $' ,'callback_data'=>"to|2000|$e[1]"]],
  [['text'=>"$ ".$nm.$s3r*4000,'callback_data'=>"to|4000|$e[1]"], ['text'=>'4000 $' ,'callback_data'=>"to|4000|$e[1]"]],
  [['text'=>"$ ".$nm.$s3r*8000,'callback_data'=>"to|8000|$e[1]"], ['text'=>'8000 $' ,'callback_data'=>"to|8000|$e[1]"]],
  [['text'=>"$ ".$nm.$s3r*10000,'callback_data'=>"to|10000|$e[1]"], ['text'=>'10000 $' ,'callback_data'=>"to|10000|$e[1]"]],
  [['text'=>"$ ".$nm.$s3r*20000,'callback_data'=>"to|20000|$e[1]"], ['text'=>'20000 $' ,'callback_data'=>"to|400|$e[1]"]],  
  [['text'=>'رجوع' ,'callback_data'=>"type|". $akl['SB1'][$from_id]."|".$akl['SB2'][$from_id]]],
]])
]);
} 
$akl = json_decode(file_get_contents("akl/akl.json"),true);
if(is_numeric($text) and $akl['mode'][$from_id]  ==  "SETd") {
$s3r = $akl['S3RS'][$from_id];
$e[1] = $text;
$s3r = $s3r * $text;
$min = explode("|", $akl['min_mix'][$from_id])[0];
$mix = explode("|", $akl['min_mix'][$from_id])[1];
if($text >= $min){
if($text <= $mix){
bot('sendmessage',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"
".$akl['WSFV'][$from_id]."
ارسل الرابط الان :
",
'reply_markup'=>json_encode([ 
  'inline_keyboard'=>[
  [['text'=>'رجوع' ,'callback_data'=>"tobon"]],
  ]])
]);
    $akl['3dd'][$from_id][$from_id]  = $e[1];
    $akl['mode'][$from_id]  = "MJK";
    $akl["tlbia"][$from_id] = $tlbia;
    $akl["s3rltlb"][$from_id] = $s3r;
    $akl['tp'][$from_id] = $e[2];
    $akl['coinn'] = $s3r;
SETJSON($akl); 
return false;} else {
	bot('sendmessage',[
      'chat_id'=>$chat_id,
      'message_id'=>$message_id,
      'text'=>"ارسل عدد بين $min و $mix",
      ]);
	} 
  } else {
    bot('sendmessage',[
      'chat_id'=>$chat_id,
      'message_id'=>$message_id,
      'text'=>"ارسل عدد بين $min و $mix",
      ]);
  }
} 
if($text and $akl['mode'][$from_id]  == "MJK" and $text !="/start") {
    	$s3r = $akl['S3RS'][$from_id];
        $s3r = ($s3r ?? "1");
        $g= $s3r * $akl['3dd'][$from_id][$from_id]  ;
    bot('sendmessage',[
        'chat_id'=>$chat_id,
        'message_id'=>$message_id,
        'text'=>"✅] هل أنت متأكد 
        
🔢] ايدي الخدمة : ".rand(999,999999)."
💠] الى : $text
🔆] الكمية : ".$akl['3dd'][$from_id][$from_id]."
        ",
        'reply_markup'=>json_encode([
             'inline_keyboard'=>[
             [['text'=>"موافق ✅",'callback_data'=>"YESS|$from_id" ],['text'=>"الغاء ❌",'callback_data'=>"panel" ]],
              ]
            ])
        ]);
        $akl['LINKS_$from_id'] = $text;
        $akl['mode'][$from_id] = "PROG";
        $akl= json_encode($akl,32|128|265);
        file_put_contents("akl/akl.json",$akl);
}
$akl["sSite"] = ($akl['web'][$from_id]?? $akl["sSite"]) ;
$Api_Tok = ($akl['key'][$from_id]?? $akl["sVISCODEV"]) ;
$aklaft =$akl['bot_tlb']+1;
if(explode("|",$data)[0] == "YESS" and $akl['mode'][$from_id]  == "PROG") {
	$akl = json_decode(file_get_contents("akl/akl.json"),true);
	$coin3aa = $akl['3dd'][$from_id][$from_id];
	$s3r = $akl['S3RS'][$from_id];
	$s3r = $s3r * $coin3aa;
	if($coin >= $s3r){
      $akl['S3RS'][$from_id] =  $akl["s3rltlb"][$from_id];
      $inid = $akl['IDX'][$from_id];
      $text = $akl['LINKS_$from_id'];
      $cuser["userfild"]["$from_id"]["coin"]-=$akl["s3rltlb"][$from_id];
      // --- Wallet ledger: تسجيل حركة خصم (طلب خدمة) ---
      wallet_log($from_id, 'deduct', $akl["s3rltlb"][$from_id], "خصم تنفيذ طلب خدمة #$inid", ['balance_after' => $cuser["userfild"]["$from_id"]["coin"]]);
      $jsonString = file_get_contents("https://".$akl["sSite"]."/api/v2?key=$Api_Tok&action=add&service=$inid&link=$text&quantity=". $akl['3dd'][$from_id][$from_id]);
      $requst = $jsonString;
      $rnd = json_decode($jsonString)->order;
      $idreq = json_decode($jsonString)->order;
      // --- Order tracking: تسجيل الطلب بحالة "قيد الانتظار" ---
      if($rnd){
          order_create($rnd, $from_id, $inid, $text, $akl['3dd'][$from_id][$from_id], $akl["s3rltlb"][$from_id]);
      }
      $cuser = json_encode($cuser,true);
      file_put_contents("data/$from_id.json",$cuser);
	bot('editmessagetext',[
   'chat_id'=>$chat_id,
   "message_id" => $message_id,
   'text'=>"
   ✅] تم انشاء طلب بنجاح : 
        
   🔢] ايدي الطلب : $rnd
   🌐] تم الطلب الى : $text",   
'disable_web_page_preview'=>true,
]);
$reply_markup = ne_home_keyboard();
foreach($button['codzer'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>"$cedname"]];
}
foreach($button['buttons'] as $ced => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>$ced]];
}
foreach($button['links'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'url'=>"$cedname"]];
}
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
$start = ne_home_text($chat_id, $from_id, $cuser, $message, $cdiamlaadf, $cdiamlanoo);
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"$start",
'parse_mode'=>'HTML',
 'reply_markup'=>$reply_markup,              
    	]);
 $userbot = bot("getme")->result->username;
 $chidaspat = file_get_contents("edid/aspatchid1.txt");
 $name_user = "الاسم : [$name](tg://user?id=$chat_id)";
 $nameService = $akl["="][$from_id];
 $coinService = $akl["s3rltlb"][$from_id];
 $numberLink = $akl['3dd'][$from_id][$from_id];
 $msgaspat = str_replace('#nameService',$nameService,$msgaspat);
 $msgaspat = str_replace('#coinService',$coinService,$msgaspat);
 $msgaspat = str_replace('#numberall',$aklaft,$msgaspat);
 $msgaspat = str_replace('#Link',$text,$msgaspat);
 $msgaspat = str_replace('#name_user',$name_user,$msgaspat);
 $msgaspat = str_replace('#numberLink',$numberLink,$msgaspat);
 $msgaspat = str_replace('#id',$chat_id,$msgaspat);
   bot('sendMessage',[
   'chat_id'=>$chidaspat,
   'text'=>"$msgaspat", 
 'parse_mode'=>"markdown",
 'disable_web_page_preview'=>true,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"دخول البوت",'url'=>"https://t.me/$userbot?start=$admin"]],
                     ]
               ])
]);
bot('sendMessage',[
   'chat_id'=>$admin,
   'text'=>"
*طلب جديد ✅*

*معلومات العضو *
            *-----------------------*    
الاسم : [$name](tg://user?id=$chat_id)
ايدي : `$from_id`
معرف : [@$user]
رصيد العضو : $coin
            *-----------------------*    
*معلومات الطلب*
            *-----------------------*    
            
اسم الخدمه : ".$akl["="][$from_id]."
سعر الطلب :". $akl["s3rltlb"][$from_id]. "
ايدي الطلب : $rnd
الرقم التسلسلي للطلب : *". $aklaft." * 
العدد المطلوب : " . $akl['3dd'][$from_id][$from_id] . " $tp
الرابط :  : [$text]  ", 
 'parse_mode'=>"markdown",
 'disable_web_page_preview'=>true,
]);
mkdir("amr/$chat_id");
$addrl = $akl['s3rltlb'][$from_id];
$addlro = file_get_contents("amr/$chat_id/coirlt.txt");
$addrlad = $addrl + $addlro;
file_put_contents("amr/$chat_id/coirlt.txt",$addrlad);
file_put_contents("akl/orders.txt","$idreq\n",FILE_APPEND);
file_put_contents("akl/akltlep.txt","$idreq\n",FILE_APPEND);
$rnn = "
- - - - - - - - - -
ا] 🎁 ".$akl["="][$from_id]." 🎁
ا] $rnd
- - - - - - - - - -";
$akl["coin"][$from_id] -=  $akl["s3rltlb"][$from_id];
$akl['S3RS'][$from_id] = 0;
$akl["orders"][$from_id][]= "$rnn";
$akl["order"][$rnd]= $idreq;
$akl["ordn"][$idreq]= $akl["="][$from_id];
$akl["sites"][$idreq]= $web;
$akl["keys"][$idreq]= $Api_Tok;
$akl["tlby"][$from_id] += 1;
$akl["cointlb"][$from_id] +=  $akl["s3rltlb"][$from_id];
$akl['3dd'][$from_id][$from_id]  = null;
$akl['mode'][$from_id]  = null;
$akl['bot_tlb']+= 1;
   SETJSON($akl);
return false;} else{
        bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' => "لا يوجد لديك $cdiamlaadf كافيه ❌",
            'show_alert' =>true
        ]);
$reply_markup[] =   [['text'=>"$aklamr1",'callback_data'=>'takemember']];
$reply_markup[] =   [['text'=>"$aklamr2",'callback_data'=>'takecoinn'],['text'=>"$aklamr3",'callback_data'=>'accont']];
$reply_markup[] =   [['text'=>"$aklamr4",'callback_data'=>'amr6'],['text'=>"$aklamr5",'callback_data'=>'sendcoin']];
$reply_markup[] =   [['text'=>"$aklamr6",'callback_data'=>'amr4'],['text'=>"$aklamr7",'callback_data'=>'amr5']];
$reply_markup[] =   [['text'=>"$aklamr8",'url'=>"https://t.me/$chadin"]];
$reply_markup[] =   [['text'=>"$aklamr9",'callback_data'=>'amr2'],['text'=>"$aklamr10",'callback_data'=>'amr1']];
$reply_markup[] =   [['text'=>"$aklamr11",'callback_data'=>'sec_stats']];
foreach($button['codzer'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>"$cedname"]];
}
foreach($button['buttons'] as $ced => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>$ced]];
}
foreach($button['links'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'url'=>"$cedname"]];
}
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"$start",
 'reply_markup'=>$reply_markup,              
    	]);
}return false;}
elseif($data=="takecoinn" ){
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$coin = $cuser["userfild"]["$from_id"]["coin"];
  bot('editmessagetext',[
       'chat_id'=>$chat_id,
       'message_id'=>$message_id,
                 'text'=>"✳️ تجميع $cdiamlaadf",
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [['text'=>"الانضمام لقنوات 📣",'callback_data'=>'takecoin']],
                     [['text'=>"رابط الدعوة 🌀",'callback_data'=>'link_add']],
                     [['text'=>"الهدية 🎁",'callback_data'=>'kk']],
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
  [
  				   ],
                       ]
                 ])
  	]);
}
if($text == '/id'){
bot('sendmessage',[
'chat_id'=>$chat_id, 
    'text'=>$chat_id,
 'reply_to_message_id'=>$message->message_id,
 ]);
 }
if($data == 'link_add'){
$invite = $cuser["userfild"]["$from_id"]["invite"];
$userbot = bot("getme")->result->username;
  bot('editMessageText',[
    'chat_id'=>$chat_id,
     'message_id'=>$message_id,
    'text'=>"
✳️ تجميع $cdiamlaadf

لقد دعوت : $invite 👤

عندما تقوم بدعوة شخص من خلال الرابط :
https://t.me/$userbot?start=$chat_id
ستحصل على $coins_start $cdiamlanoo 👤
",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
 ] 
   ])
  ]);
 }
if($data == 'amr1'){
  bot('editMessageText',[
    'chat_id'=>$chat_id,
     'message_id'=>$message_id,
    'text'=>$msgasro,
           'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
 ] 
   ])
  ]);
 }
if($text == 'مشاهدة الازرار'){
$reply = $message->reply_to_message; 
$message_reply = $reply->message->text;
$dhf =  $update->message->reply_to_message->text;
if (strpos($dhf, "$coin") !== false) {
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*• الازرار :*

- *$aklamr1* ~> `co:9u7ehcde55tak3m06`
- *$aklamr2* ~> `co:644ffh76sin4hf6ntk9b`
- *$aklamr3* ~> `co:gd53hd48dh6dmds4o`
- *$aklamr4* ~> `co:b89ckihdrsfdgfngu469`
- *$aklamr5* ~> `co:bfr68agaddhybvotrk7in`
- *$aklamr6* ~> `co:nd567bsa5onf90h4h6d`
- *$aklamr7* ~> `co:che57r7bsa5onmf906d`
- *$aklamr9* ~> `co:bxry8kcs56bbkahr6ydloj`
- *$aklamr10* ~> `co:opfrfbsfbbty66jgdfsrd`
- *$aklamr11* ~> `co:yatuno756dfbddk6srd`

*• اضغط على الكود للنسخ ..*

",'parse_mode'=>"Markdown",'reply_to_message_id'=>$message->message_id,]);}}
if($data == 'amr2'){
  bot('editMessageText',[
    'chat_id'=>$chat_id,
     'message_id'=>$message_id,
    'text'=>$msgasar,
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
 ] 
   ])
  ]);
 }
$akl = json_decode(file_get_contents("akl/akl.json"),true);
if($data == "amr4"){
  bot('editMessageText',[
    'chat_id'=>$chat_id,
     'message_id'=>$message_id,
'text'=>"🔢] ارسل ايدي الطلب :", 
'parse_mode'=>"Markdown",
'reply_markup'=>json_encode([
'inline_keyboard'=>[
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
]])
]);
$akl['mode'][$from_id]  = 'infotlb';
SETJSON($akl);
}
$akl = json_decode(file_get_contents("akl/akl.json"),true);
$akl["sSite"] = ($akl["sites"][$text]??$akl["sSite"]) ;
$Api_Tok = ($akl["keys"][$text]?? $akl["sVISCODEV"]) ;
if(is_numeric($text) and $akl['mode'][$from_id] == "infotlb"){
if($text != null){
$amr = json_decode(file_get_contents("https://".$akl["sSite"]."/api/v2?key=$Api_Tok&action=status&order=".$text));
$startcc = $amr->start_count; 
$status = $amr->remains; 
if($status == "0"){
$sers= "مكتمل ✅";
}else{
$sers="قيد المراجعة ⏳";
}
if($amr) {
$open = file_get_contents('akl/akltlep.txt');
$arr = explode("\n", $open);
if(in_array($text, $arr)){ 
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
   ️⃣] معلومات عن الطلب :

- 🔡] اسم الخدمة : ".$akl["ordn"][$text]."
- 🔢] ايدي الطلب : `$text`
- ♻️] حالة الطلب : $sers
- ⏳] المتبقي : $status
  ", 
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>'رجوع' ,'callback_data'=>"panel"]],
      ]
    ])
]);
$akl['mode'][$from_id]  = null;
SETJSON($akl);
} else {
	bot('sendMessage',[
   'chat_id'=>$chat_id,
   'text'=>"
️هذا الطلب ليس موجود في طلباتك ❌
  ", 
 'parse_mode'=>"markdown",
]);
} 
}
}}
$akl = json_decode(file_get_contents("akl/akl.json"),true);
if($data == "amr5"){
foreach($akl["orders"][$from_id] as $m){
  $b=$b."\n$m";
}
mkdir("amr/$chat_id");
$fIleadd = "amr/$chat_id/orders.txt";
file_put_contents($fIleadd,$b);
$file = fopen($fIleadd, "r"); 
if($file) {
$mrorders = "";
for ($i = 1; $i <= 20; $i++) {
if(($line = fgets($file)) !== false) {
$mrorders .= $line;
}else {break;}}fclose($file); 
}
if(empty($mrorders)) {
$result = "لا توجد أي طلبات حتى الآن ❌";
} else {
$result = $mrorders;
}
bot('editmessagetext',[
  'chat_id'=>$chat_id,
  'message_id' => $message_id,
  'text'=>"
📮] اخر 5 طلبات

$result
 ", 
 'parse_mode'=>"markdown",
 'reply_markup'=>json_encode([
     'inline_keyboard'=>[
     [['text'=>"ارسال جميع الطلبات بصيغة ملف 🗂",'callback_data'=>"sendMeTxt" ]],
     [['text'=>"رجوع",'callback_data'=>"panel" ]],
      ]
    ])
]); 
}
if($data =="sendMeTxt"){
$akl = json_decode(file_get_contents("akl/akl.json"),true);
foreach($akl["orders"][$chat_id] as $m){
  $b=$b."\n$m";
}
if(empty($b)) {
bot('answercallbackquery',[
        'callback_query_id'=>$update->callback_query->id,
'text' =>"لا يوجد لديك طلبات ❌",
        'show_alert'=>true,
]);
} else {
mkdir("amr/$chat_id");
file_put_contents("amr/$chat_id/orders.txt",$b);
bot ("senddocument", [
"chat_id" => $chat_id, 
"document" => new CURLFile("amr/$chat_id/orders.txt") 
]) ;
unlink("amr/$chat_id/orders.txt");
rmdir("amr/$chat_id");
  }}
// متغيرات مُعرَّفة في بداية الملف 
// callback_query مُعرَّف في بداية الملف
// inline_query مُعرَّف في بداية الملف
@$user = json_decode(file_get_contents("data/user.json"),true);
@$juser = json_decode(file_get_contents("data/$from_id.json"),true);
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
if(!in_array($from_id, $user["userlist"]) == true) {
$user["userlist"][]= $from_id;
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
}
if($data == 'amr6'){
  bot('editMessageText',[
    'chat_id'=>$chat_id,
     'message_id'=>$message_id,
    'text'=>"
💳 ارسل الكود : ",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
                     [['text'=>"الغاء ❌",'callback_data'=>'panel']],
] 
])]);
file_put_contents("amr/amr $chat_id.txt","amr");
}
if($text and file_get_contents("amr/amr $chat_id.txt") == "amr"){
    $codematch = $text;
    $code = $user["codecoin"];
    $howcodead = file_get_contents("edid/howcodeadd $code.txt");
    $howcodea = count(file("edid/howcdead $code.txt"));
    $howrcode = file_get_contents("edid/howcdead $code.txt");
    file_put_contents("amr/amr $chat_id.txt","amr1");
    if($codematch != $code) {
        unlink("amr/amr $chat_id.txt");
        bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"الكود خطأ او تم استخدامه ❌",
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text'=>"رجوع ➡️",'callback_data'=>'panel']
                    ]
                ]
            ])
        ]);
        return false;
    }
    if($howcodead <= $howcodea) {
        unlink("amr/amr $chat_id.txt");
        bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"الكود خطأ او تم استخدامه ❌",
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text'=>"رجوع ➡️",'callback_data'=>'panel']
                    ]
                ]
            ])
        ]);
        unlink("edid/howcdead $code.txt");
        unlink("edid/howcode $code.txt");
        unlink("edid/howcodeadd $code.txt");
        unset($user["codecoin"]);
        unset($user["howcoincode"]);
        return false;
    }
    $arr = explode("\n", $howrcode);
    if(in_array($chat_id, $arr)){
        unlink("amr/amr $chat_id.txt");
        bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"انت استخدم الكود من قبل ❌",
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text'=>"رجوع ➡️",'callback_data'=>'panel']
                    ]
                ]
            ])
        ]);
        return false;
    }
    $coincode = $user["howcoincode"];
    if($text and file_get_contents("amr/amr $chat_id.txt") == "amr1"){
       bot('sendmessage',[
            'chat_id'=>$admin,
            'text'=>"
*قد قام احد باستخدام كود هدية *✅

*معلومات العضو *

 الاسم : [$name]
 ايدي : $chat_id
 
*معلومات الكود *

عدد ال$cdiamlaadf : $coincode
الكود المستخدم : $code
عدد المحاولات : $howcodea
",
'parse_mode'=>"markdown",
        ]);
        bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"تم اضافة $coincode $cdiamlanoo الى حسابك ✅",
            'reply_markup'=>json_encode([
                'inline_keyboard'=>[
                    [
                        ['text'=>"رجوع ➡️",'callback_data'=>'panel']
                    ]
                ]
            ])
        ]);
        unlink("amr/amr $chat_id.txt");
        $user = json_encode($user,true);
        file_put_contents("data/user.json",$user);
        $juser = json_decode(file_get_contents("data/$from_id.json"), true);
        $coin = $juser["userfild"]["$from_id"]["coin"];
        $coinplus = $coin + $coincode;
        $juser["userfild"]["$from_id"]["coin"] = $coinplus;
        $juser = json_encode($juser,true);
        file_put_contents("data/$from_id.json",$juser);
        file_put_contents("edid/howcdead $code.txt","$from_id\n",FILE_APPEND);
    }
}
#توباك
if($data=="takecoin" ){
$rules = $cuser["userfild"]["$from_id"]["acceptrules"];
$llaml = file_get_contents("edid/asttacbot.txt");
if($rules == false and $llaml == "✅"){	
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"*مرحبا بك عزيزي 👋

في قسم الاشتراك في القنوات مقابل ال$cdiamlaadf القنوات التي في هذا القسم هيه القنوات التي يتم تمويله اعضاء حقيقيه من خلال قسم طلب التمويل الحقيقي اذا كنت تريد اضافته قناتك هنا قوم بزياره القسم اذا كنت ترغب بلاستمرار للاشتراك في القنوات اضغط اسفل *",
               'parse_mode'=>"markdown",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"تاكيد ☑️",'callback_data'=>"takecoin"]],
  [
  				   ],
                       ]
                 ])
  	]);
$cuser["userfild"]["$from_id"]["acceptrules"]="true";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
		   }
else
{
if($tchq != 'member' && $tchq != 'creator' && $tchq != 'administrator'){
$join = $cuser["userfild"]["$from_id"]["canceljoin"];
if($join == false){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"▪️عزيزي اشترك بلقناة الرئيسية ( @$channel )
⚠️ عند اشتراكك ستحصل على نقطتين مجانا .

بعد الاشتراك اضغط على كلمة [تحقق من الانضمام 🔄]",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                        [["text"=>"join","url"=>"t.me/".$channel]],
                     [['text'=>"تحقق من الانضمام 🔄",'callback_data'=>'mainchannel'],['text'=>"مشترك مسبقا ❗️",'callback_data'=>'takecoin']],
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
                     ]
               ])
			   ]);
$cuser["userfild"]["$from_id"]["canceljoin"]="true";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}
else
{
$allchannel = $user["channellist"];
for($z = 0;$z <= count($allchannel);$z++){
$getchannel = bot_curl('getChatMember', ['chat_id' => $allchannel[$z], 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
break;
}
}

if($allchannel[$z] == true){
$url = json_encode(bot_curl('getChat', ['chat_id' => $allchannel[$z]]));
$getchat = json_decode($url, true);
$name = $getchat["result"]["title"];
$username = $getchat["result"]["username"];
$id = $getchat["result"]["id"];
if($username != "" and $username != null){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"اشترك فالقناة @$username  
وستحصل على $add_aoc $cdiamlaadf ??✌🏻",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"اشتركت ✅",'callback_data'=>'truechannel'],['text'=>"تخطي ⚠️",'callback_data'=>'nextchannel']],
                   [['text'=>"ابلاغ 📛",'callback_data'=>'badchannel']],
                   [['text'=>"رجوع ➡️",'callback_data'=>'panel']]
                     ]
               ])
			   ]);
$cuser["userfild"]["$from_id"]["getjoin"]="$username";
$cuser["userfild"]["$from_id"]["arraychannel"]="$z";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}else
{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
}
}
else
{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
}
}
}
else
{
$allchannel = $user["channellist"];
for($z = 0;$z <= count($allchannel);$z++){
$getchannel = bot_curl('getChatMember', ['chat_id' => $allchannel[$z], 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
break;
}
}
if($allchannel[$z] == true){
$url = json_encode(bot_curl('getChat', ['chat_id' => $allchannel[$z]]));
$getchat = json_decode($url, true);
$name = $getchat["result"]["title"];
$username = $getchat["result"]["username"];
$id = $getchat["result"]["id"];
if($username != "" and $username != null){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"اشترك فالقناة @$username  
وستحصل على $add_aoc $cdiamlaadf 🌝✌🏻",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"اشتركت ✅",'callback_data'=>'truechannel'],['text'=>"تخطي ⚠️",'callback_data'=>'nextchannel']],
                   [['text'=>"ابلاغ 📛",'callback_data'=>'badchannel']],
                   [['text'=>"رجوع ➡️",'callback_data'=>'panel']]
                     ]
               ])
			   ]);
$cuser["userfild"]["$from_id"]["getjoin"]="$username";
$cuser["userfild"]["$from_id"]["arraychannel"]="$z";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}else
{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
}
}
else
{
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
                 'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
  				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
  				   ],
                       ]
                 ])
  			   ]);
}
}
}
}
elseif($data=="truechannel" ){
$getjoinchannel = $cuser["userfild"]["$from_id"]["getjoin"];
$getchannel = bot_curl('getChatMember', ['chat_id' => '@'.$getjoinchannel, 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
        bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' =>  "عليك الاشتراك بالقناة اولآ ❌", 
            'show_alert' =>true
        ]);
}
else
{
$getjoinchannel = $cuser["userfild"]["$from_id"]["getjoin"];
$j = file_get_contents("userch/@$getjoinchannel/from_id.txt");
$arr = explode("\n", $j);
 	if(in_array($chat_id, $arr)){
 	        bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' =>  "تم التحقق من اعاده الاشتراك في القناة ✅", 
            'show_alert' =>true
          ]);
$reply_markup[] =   [['text'=>"$aklamr1",'callback_data'=>'takemember']];
$reply_markup[] =   [['text'=>"$aklamr2",'callback_data'=>'takecoinn'],['text'=>"$aklamr3",'callback_data'=>'accont']];
$reply_markup[] =   [['text'=>"$aklamr4",'callback_data'=>'amr6'],['text'=>"$aklamr5",'callback_data'=>'sendcoin']];
$reply_markup[] =   [['text'=>"$aklamr6",'callback_data'=>'amr4'],['text'=>"$aklamr7",'callback_data'=>'amr5']];
$reply_markup[] =   [['text'=>"$aklamr8",'url'=>"https://t.me/$chadin"]];
$reply_markup[] =   [['text'=>"$aklamr9",'callback_data'=>'amr2'],['text'=>"$aklamr10",'callback_data'=>'amr1']];
$reply_markup[] =   [['text'=>"$aklamr11",'callback_data'=>'sec_stats']];
foreach($button['codzer'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>"$cedname"]];
}
foreach($button['buttons'] as $ced => $buttonss){
$reply_markup[] = [['text'=>$buttonss['name'],'callback_data'=>$ced]];
}
foreach($button['links'] as $ced => $buttonss){
$cedname = $buttonss['mo'];
$reply_markup[] = [['text'=>$buttonss['name'],'url'=>"$cedname"]];
}
$reply_markup = json_encode(['inline_keyboard'=>$reply_markup,]);
  bot('editmessagetext',[
          'chat_id'=>$chat_id,
       'message_id'=>$message_id,
'text'=>"$start",
 'reply_markup'=>$reply_markup,              
    	]);
          }
else
{
$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$arraychannel = $cuser["userfild"]["$from_id"]["arraychannel"];
$coinchannel = $user["setmemberlist"];
$channelincoin = $coinchannel[$arraychannel];
$downchannel = $channelincoin - 1;
$pluscoin = $coin + $add_aoc;
$getjoinchannel = $cuser["userfild"]["$from_id"]["getjoin"];
        bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' =>  " تم اضافه $add_aoc $cdiamlaadf بنجاح ✅", 
            'show_alert' =>true
          ]);
$cuser["userfild"]["$from_id"]["channeljoin"][]="$getjoinchannel";
$cuser["userfild"]["$from_id"]["coin"]="$pluscoin";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
file_put_contents("userch/@$getjoinchannel/from_id.txt","$from_id\n",FILE_APPEND);
if($downchannel > 0){
@$user = json_decode(file_get_contents("data/user.json"),true);
$user["setmemberlist"]["$arraychannel"]="$downchannel";
$user["setmemberlist"]=array_values($user["setmemberlist"]);
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
@$user = json_decode(file_get_contents("data/user.json"),true);
$allchannel = $user["channellist"];
for($z = 0;$z <= count($allchannel);$z++){
$getchannel = bot_curl('getChatMember', ['chat_id' => $allchannel[$z], 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
break;
}
}
if($allchannel[$z] == true){
$url = json_encode(bot_curl('getChat', ['chat_id' => $allchannel[$z]]));
$getchat = json_decode($url, true);
$name = $getchat["result"]["title"];
$username = $getchat["result"]["username"];
$id = $getchat["result"]["id"];
if($username != "" and $username != null){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"اشترك فالقناة @$username  
وستحصل على $add_aoc $cdiamlaadf 🌝✌🏻",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"اشتركت ✅",'callback_data'=>'truechannel'],['text'=>"تخطي ⚠️",'callback_data'=>'nextchannel']],
                   [['text'=>"ابلاغ 📛",'callback_data'=>'badchannel']],
                   [['text'=>"رجوع ➡️",'callback_data'=>'panel']]
                     ]
               ])
			   ]);
$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$cuser["userfild"]["$from_id"]["getjoin"]="$username";
$cuser["userfild"]["$from_id"]["arraychannel"]="$z";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}else
{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
}
}
else
{
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
                 'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
  				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
  				   ],
                       ]
                 ])
  			   ]);
}
}
else
{
    $chamc = $user["channellist"]["$arraychannel"];
    $chatm = $user["channellist"]["$arraychannel"];
    if($chamc != "" and $chamc != null){
	bot('sendmessage',[
	'chat_id'=>"$admin",
	'text'=>"✅ تم اكتمال تمويل القناة : $chatm",
  	]);
	bot('sendmessage',[
	'chat_id'=>$user["admin"]["$arraychannel"],
	'text'=>"✅ تم اكتمال تمويل القناة : ".$user["channellist"]["$arraychannel"],
  	]);
$dirPath = "userch/$chatm";
function deleteDirectory($dirPath) {
    if (!is_dir($dirPath)) {return;}
    $files = glob(rtrim($dirPath, '/') . '/*');
    foreach ($files as $file) {
        is_dir($file) ? deleteDirectory($file) : unlink($file);}
    rmdir($dirPath);}
deleteDirectory($dirPath);
    }
unset($user["setmemberlist"]["$arraychannel"]);
unset($user["channellist"]["$arraychannel"]);
unset($user["admin"]["$arraychannel"]);
$user["channellist"]=array_values($user["channellist"]);
$user["setmemberlist"]=array_values($user["setmemberlist"]);
$user["admin"]=array_values($user["admin"]);
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
@$user = json_decode(file_get_contents("data/user.json"),true);
$allchannel = $user["channellist"];
for($z = 0;$z <= count($allchannel);$z++){
$getchannel = bot_curl('getChatMember', ['chat_id' => $allchannel[$z], 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
break;
}
}
if($allchannel[$z] == true){
$url = json_encode(bot_curl('getChat', ['chat_id' => $allchannel[$z]]));
$getchat = json_decode($url, true);
$name = $getchat["result"]["title"];
$username = $getchat["result"]["username"];
$id = $getchat["result"]["id"];
if($username != "" and $username != null){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"اشترك فالقناة @$username  
وستحصل على $add_aoc $cdiamlaadf 🌝✌🏻",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"اشتركت ✅",'callback_data'=>'truechannel'],['text'=>"تخطي ⚠️",'callback_data'=>'nextchannel']],
                   [['text'=>"ابلاغ 📛",'callback_data'=>'badchannel']],
                   [['text'=>"رجوع ➡️",'callback_data'=>'panel']]
                     ]
               ])
			   ]);
$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$cuser["userfild"]["$from_id"]["getjoin"]="$username";
$cuser["userfild"]["$from_id"]["arraychannel"]="$z";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}else
{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
}
}
else
{
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
                 'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
  				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
  				   ],
                       ]
                 ])
  			   ]);
}
}
}
}
}
elseif($data=="nextchannel" ){
 bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' => " انتضر قليلا ⏳،",
            'show_alert' =>false
        ]);
$arraychannel = $cuser["userfild"]["$from_id"]["arraychannel"];
$plusarraychannel = $arraychannel + 1 ;
$allchannel = $user["channellist"];
for($z = $plusarraychannel;$z <= count($allchannel);$z++){
$getchannel = bot_curl('getChatMember', ['chat_id' => $allchannel[$z], 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
break;
}
}
if($allchannel[$z] == true){
$url = json_encode(bot_curl('getChat', ['chat_id' => $allchannel[$z]]));
$getchat = json_decode($url, true);
$name = $getchat["result"]["title"];
$username = $getchat["result"]["username"];
$id = $getchat["result"]["id"];
if($username != "" and $username != null){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"اشترك فالقناة @$username  
وستحصل على $add_aoc $cdiamlaadf 🌝✌🏻",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"اشتركت ✅",'callback_data'=>'truechannel'],['text'=>"تخطي ⚠️",'callback_data'=>'nextchannel']],
                   [['text'=>"ابلاغ 📛",'callback_data'=>'badchannel']],
                   [['text'=>"رجوع ➡️",'callback_data'=>'panel']]
                     ]
               ])
			   ]);
$cuser["userfild"]["$from_id"]["getjoin"]="$username";
$cuser["userfild"]["$from_id"]["arraychannel"]="$z";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}else
{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
}
}
else
{
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
                 'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
  				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
  				   ],
                       ]
                 ])
  			   ]);
}
}
elseif($data=="mainchannel" ){
$getchannel = bot_curl('getChatMember', ['chat_id' => '@'.$channel, 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
  bot('answercallbackquery', [
      'callback_query_id' =>$membercall,
 'text' =>"عليك الاشتراك بالقناة اولآ ❌", 
      'show_alert' =>true
  ]);
}
else
{

$getjoinchannel = $cuser["userfild"]["$from_id"]["getjoin"];
$j = file_get_contents("userch/@$getjoinchannel/from_id.txt");
$arr = explode("\n", $j);
 	if(in_array($chat_id, $arr)){
 	        bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' =>  "تم التحقق من اعاده الاشتراك في القناة ✅", 
            'show_alert' =>true
          ]);
          
          }
else
{
$coin = $cuser["userfild"]["$from_id"]["coin"];
$pluscoin = $coin + $add_aoc;
        bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' =>  " تم اضافه $add_aoc $cdiamlaadf بنجاح ✅", 
            'show_alert' =>true
          ]);
 
$cuser["userfild"]["$from_id"]["coin"]="$pluscoin";
$cuser["userfild"]["$from_id"]["channeljoin"][]="$channel";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
file_put_contents("userch/@$getjoinchannel/from_id.txt","$from_id\n",FILE_APPEND);
@$user = json_decode(file_get_contents("data/user.json"),true);
$allchannel = $user["channellist"];
for($z = 0;$z <= count($allchannel);$z++){
$getchannel = bot_curl('getChatMember', ['chat_id' => $allchannel[$z], 'user_id' => $from_id]);
$okchannel = $getchannel->result->status;
if($okchannel != 'member' && $okchannel != 'creator' && $okchannel != 'administrator'){
$omm = $allchannel[$z];
break;
}
}
if($allchannel[$z] == true){
$url = json_encode(bot_curl('getChat', ['chat_id' => $allchannel[$z]]));
$getchat = json_decode($url, true);
$name = $getchat["result"]["title"];
$username = $getchat["result"]["username"];
$id = $getchat["result"]["id"];
if($username != "" and $username != null){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"اشترك فالقناة @$username  
وستحصل على $add_aoc $cdiamlaadf 🌝✌🏻",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"اشتركت ✅",'callback_data'=>'truechannel'],['text'=>"تخطي ⚠️",'callback_data'=>'nextchannel']],
                   [['text'=>"ابلاغ 📛",'callback_data'=>'badchannel']],
                   [['text'=>"رجوع ➡️",'callback_data'=>'panel']]
                     ]
               ])
			   ]);
$cuser = json_decode(file_get_contents("data/$from_id.json"),true);
$cuser["userfild"]["$from_id"]["getjoin"]="$username";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
}
else
{
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
                 'text'=>"لا يوجد قنوات في الوقت الحالي عد لاحقا",
                 'reply_markup'=>json_encode([
                     'inline_keyboard'=>[
  				   [
  				   ['text'=>"تحديث 🔄",'callback_data'=>'takecoin'],['text'=>"رجوع ➡️",'callback_data'=>'panel']
  				   ],
                       ]
                 ])
  			   ]);
}
}
}}
elseif($data=="badchannel"){
$getjoinchannel = $cuser["userfild"]["$from_id"]["getjoin"];
	 bot('answercallbackquery', [
	            'callback_query_id' =>$membercall,
            'text' => "تم ارسال الابلاغ الى مبرمج البوت, وسوف يقوم بمراجعة القناة ومعرفة محتوى القناة ،
نشكرك للتعاون معنا  ♥️ !",
            'show_alert' =>true
        ]);
	bot('sendmessage',[
	'chat_id'=>"$admin",
	'text'=>"ابلاغ جديد!

القناة : @$getjoinchannel
معرف المبلغ : @$usernames
",
  	]);
}
$amrto = file_exists("amr/$chat_id/coirlt.txt") ? (int)file_get_contents("amr/$chat_id/coirlt.txt") : 0;

$d = date('D');
$day = explode("\n",file_get_contents("data/".$d.".txt"));
if($d == "Sat"){
unlink("data/Fri.txt");
}
if($d == "Sun"){
unlink("data/Sat.txt");
}
if($d == "Mon"){
unlink("data/Sun.txt");
}
if($d == "Tue"){
unlink("data/Mon.txt");
}
if($d == "Wed"){
unlink("data/The.txt");
}
if($d == "Thu"){
unlink("data/Wed.txt");
}
if($d == "Fri"){
unlink("data/Thu.txt");
}
if($data=="accont"){
if(!in_array($from_id, $day)){
$invite = $cuser["userfild"]["$from_id"]["invite"];
$coin = $cuser["userfild"]["$from_id"]["coin"];
$setchannel = $cuser["userfild"]["$from_id"]["setchannel"];
$setmember = $cuser["userfild"]["$from_id"]["setmember"];
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"🗃️ الحساب
$cdiamlao : $coin $cdiamlanoo
ال$cdiamlaadf المستخدمة : $amrto $cdiamlanoo
لقد دعوت : $invite ✳️
المتبقي للهدية : تستطيع المطالبة بها 🎁",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                     [['text'=>"تجميع $cdiamlaadf ✳️",'callback_data'=>'takecoinn'],['text'=>"الهدية 🎁",'callback_data'=>'add_day']],
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
                     ]
               ])
			   ]);
}else{
date_default_timezone_set('Africa/Cairo');
$current_time = time();
$end_of_day = strtotime('tomorrow') - 1;
$remaining_seconds = $end_of_day - $current_time;
$hours = floor($remaining_seconds / 3600);
$minutes = floor(($remaining_seconds % 3600) / 60);
$seconds = $remaining_seconds % 60;
$aaastf = " $hours";
$invite = $cuser["userfild"]["$from_id"]["invite"];
$coin = $cuser["userfild"]["$from_id"]["coin"];
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"🗃️ الحساب
$cdiamlao : $coin $cdiamlanoo
ال$cdiamlaadf المستخدمة : $amrto $cdiamlanoo
لقد دعوت : $invite ✳️
المتبقي للهدية : $hours ساعة
",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                     [['text'=>"تجميع $cdiamlaadf ✳️",'callback_data'=>'takecoinn'],['text'=>"الهدية 🎁",'callback_data'=>'kk']],
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
                     ]
               ])
			   ]);
}
}
$coadd = file_exists("edid/coadd.txt") ? file_get_contents("edid/coadd.txt") : "✅";
if($data == 'sendcoin' and $coadd =='❌'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*عذرا القسم تحت الصيانه*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']], ]   ])]);exit;}
if($data == 'sendcoin' and $coadd =='✅'){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
	'text'=>"🔢 ارسل ايدي الشخص :",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
				   ]
            ])
  	]);
$cuser["userfild"]["$from_id"]["file"]="sendcoin";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}
elseif($juser["userfild"]["$from_id"]["file"] == 'sendcoin') {
$coin = $juser["userfild"]["$from_id"]["coin"];
if($forward_from == true){
if($forward_from_id != $from_id){
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"💳 ارسل الكمية : 
⚠️) يجب ان يكون عدد التحويل $work_add_day فأكثر",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
$juser["userfild"]["$from_id"]["file"]="setsendcoin";
$juser["userfild"]["$from_id"]["sendcoinid"]="$forward_from_id";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
else
{
	bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"عذراً لا يمكنك الارسال لنفسك ❌",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
}
}
else
{
if($text != $from_id){
if(is_numeric($text)){
$stat = json_encode(bot_curl('getChatMember', ['chat_id' => $text, 'user_id' => $text]));
$statjson = json_decode($stat, true);
$status = $statjson['ok'];
if($status == 1){
$name = $statjson['result']['user']['first_name'];
$username = $statjson['result']['user']['username'];
$id = $statjson['result']['user']['id'];
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"💳 ارسل الكمية : 
⚠️) يجب ان يكون عدد التحويل $work_add_day فأكثر",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
$juser["userfild"]["$from_id"]["file"]="setsendcoin";
$juser["userfild"]["$from_id"]["sendcoinid"]="$text";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
else
{
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"عذراً الشخص غير موجود فالبوت ❌",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
}
}
else
{
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"عذراً الشخص غير موجود فالبوت ❌",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
}
}
else
{
	bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"عذراً لا يمكنك الارسال لنفسك ❌",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
}
}
}
elseif($juser["userfild"]["$from_id"]["file"] == "setsendcoin"and $text != '/start'){ 
if($text < $work_add_day and $text != '/start'){
bot('sendmessage',[
	'chat_id'=>$chat_id,
	'text'=>"يجب ان يكون عدد التحويل $work_add_day فأكثر ❌",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
				   ]
            ])
  	]);exit;}
$coin = $juser["userfild"]["$from_id"]["coin"];
$userid = $juser["userfild"]["$from_id"]["sendcoinid"];
$inuser = json_decode(file_get_contents("data/$userid.json"),true);
$coinuser = $inuser["userfild"]["$userid"]["coin"];
if($text <= $coin && $coin > 0 ){
$coinplus = $coin - $text;
$sendcoinplus = $coinuser + $text;
$amrco = $coinplus + $text;
	bot('sendmessage',[
	'chat_id'=>$chat_id,
	'text'=>"👤 تم ارسال $text من $cdiamlaadf 

- الى الشخص : $userid
- $cdiamlao القديمة : $amrco
- $cdiamlao الان : $coinplus",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
				   ]
            ])
  	]);
		bot('sendmessage',[
	'chat_id'=>$userid,
	'text'=>"👤 تم استلام $text من $cdiamlaadf

- من الشخص : $chat_id",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
				   ]
            ])
  	]);
$juser["userfild"]["$from_id"]["file"]="none";
$juser["userfild"]["$from_id"]["coin"]="$coinplus";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
$inuser["userfild"]["$userid"]["coin"]="$sendcoinplus";
$inuser = json_encode($inuser,true);
file_put_contents("data/$userid.json",$inuser);
// --- Wallet ledger: تسجيل حركتي تحويل (صادر/وارد) ---
wallet_log($from_id, 'transfer_out', $text, "تحويل إلى $userid", ['to' => $userid, 'balance_after' => $coinplus]);
wallet_log($userid, 'transfer_in', $text, "تحويل من $from_id", ['from' => $from_id, 'balance_after' => $sendcoinplus]);
}
else
{
	bot('sendmessage',[
	'chat_id'=>$chat_id,
	'text'=>"انت لاتمتلك هذه ال$cdiamlaadf ❗️",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
				   ]
            ])
  	]);
}
}
$tmwel = file_get_contents("edid/tmwel.txt");
if($data == 'takememberto' and $tmwel =='❌'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*عذرا القسم تحت الصيانه*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']], ]   ])]);exit;}
if($data == 'takememberto' and $tmwel =='✅'){
$coin = $cuser["userfild"]["$from_id"]["coin"];
if($coin >= $adna_coins){
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"⚠️] ارفع البوت ادمن في قناتك

و ارسل معرف القناة مثل : $chadmin",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
			   ]);
$cuser["userfild"]["$from_id"]["file"]="setchannel";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}
else
{
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"
• عليك تجميع $cdiamlaadf اكثر من $adna_coins $cdiamlanoo ⚠️
",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
            [['text'=>"تجميع {$cdiamlaadf}💰",'callback_data'=>'takecoinn']],
            [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
                                 ]
               ])
			   ]);
}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'setchannel') {
if(preg_match('/^(@)(.*)/s',$text)){
$coin = $juser["userfild"]["$from_id"]["coin"];
$max = $coin / $add_ado;
$maxmember = floor($max);
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"قم بأرسال عدد الاعضاء الان ليتم لتمويل 
        
📡] قناتك : $text 

💰] $cdiamlao : $coin 

🖲] كل عضو يساوي $add_ado $cdiamlaadf

🛎 يمكنك طلب : $maxmember عضو",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
$juser["userfild"]["$from_id"]["file"]="setmember";
$juser["userfild"]["$from_id"]["setchannel"]="$text";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
else
{
	bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"تاكد من معرف القناة ❗️
ارسل المعرف الصحيح مثل : $chadmin .",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'setmember') {
if(preg_match('/([0-9])/i',$text)){
$coin = $juser["userfild"]["$from_id"]["coin"];
$setchannel = $juser["userfild"]["$from_id"]["setchannel"];
$max = $coin / $add_ado;
$maxmember = floor($max);
if($maxmember >= $text){
$howmember = getChatMembersCount($setchanel,$VISCODEV);
$endmember = $howmember + $text;
$setchannel = $cuser["userfild"]["$from_id"]["setchannel"];
$setmember = $cuser["userfild"]["$from_id"]["setmember"];
$pluscoin = $setmember * $add_ado;
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"معلومات طلبك [📝

📡] القناة المراد تمويلها : $setchannel 
👥] العدد المطلوب : $text 
🏷] السعر : $pluscoin

❗️] ارفع البوت ادمن حتى يتم تمويل القناة 
",
'parse_mode'=>"MarkDown",
'disable_web_page_preview'=>true,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   				   [
['text'=>"تاكيد ☑️",'callback_data'=>'trueorder'],['text'=>"رجوع ➡️",'callback_data'=>'panel'],
				   ],
                     ]
               ])
 ]);
$juser["userfild"]["$from_id"]["file"]="none";
$juser["userfild"]["$from_id"]["setmember"]="$text";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
else
{
	bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"$cdiamlao : $coin 💰

اقصى عدد يمكن ان تطلبه : $maxmember 

يرجى ارسال $maxmember او اقل منه 💡",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
}
}
}
elseif($data=="trueorder"){
$setchannel = $cuser["userfild"]["$from_id"]["setchannel"];
if(!in_array($setchannel, $user["channellist"])) {
$admin = getChatstats(@$setchannel,$VISCODEV);
if($admin != true){
	       bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' => "ارفع البوت ادمن اولا ❗️",
            'show_alert' =>true
        ]);
}
else
{
$setchannel = $cuser["userfild"]["$from_id"]["setchannel"];
$setmember = $cuser["userfild"]["$from_id"]["setmember"];
$pluscoin = $setmember * $add_ado;
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"تم انشاء تمويل جديد ✅

💠] الكمية : $setmember
🏷] السعر : $pluscoin
📣] القناة : $setchannel

⚠️] تأكد من عدم ازالة البوت من الادمنية",
			   ]);
$setchannel = $cuser["userfild"]["$from_id"]["setchannel"];
$setmember = $cuser["userfild"]["$from_id"]["setmember"];
$pluscoin = $setmember * $add_ado;
@mkdir("userch");
@mkdir("userch/$setchannel");
bot('sendmessage',[
	'chat_id'=>"$admin",
	'text'=>"تم انشاء تمويل جديد ✅

💠] الكمية : $setmember
🏷] السعر : $pluscoin
📣] القناة : $setchannel

معلومات العضو

اسم العضو : $name
معرف العضو : $user
ايدي العضو : $chat_id
",
  	]);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$setchannel = $cuser["userfild"]["$from_id"]["setchannel"];
$setmember = $cuser["userfild"]["$from_id"]["setmember"];
$pluscoin = $setmember * $add_ado;
$coinplus = $coin - $pluscoin;
$cuser["userfild"]["$from_id"]["coin"]="$coinplus";
$cuser["userfild"]["$from_id"]["listorder"][]="$setchannel -> $setmember";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
$user["channellist"][]="$setchannel";
$user["setmemberlist"][]="$setmember";
$user["admin"][]="$from_id";
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
@mkdir("userch");
@mkdir("userch/$setchannel");
$addlro = file_get_contents("amr/$chat_id/coirlt.txt");
$addrlad = $pluscoin + $addlro;
file_put_contents("amr/$chat_id/order.txt",$addrlad);
}
exit;}else { 
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
	'text'=>"عذراً القناة في حالة تمويل  ⚠️",
  	]);
$juser["userfild"]["$from_id"]["file"]="none";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
exit;}}
if(file_get_contents("data/$from_id.txt") == "true"){
$pluscoin = file_get_contents("data/".$from_id."coin.txt");
$inviter = $cuser["userfild"]["$from_id"]["inviter"];
$invitercoin = $pluscoin / 100 * 20;
	       bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' => "اضافة النقود الان 💰",
            'show_alert' =>false
        ]);
		         bot('sendmessage',[
        	'chat_id'=>$inviter,
        	'text'=>"تم اضافة ( $invitercoin 💰 ) بنجاح ☑️",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
$coin = $cuser["userfild"]["$from_id"]["coin"];
$coinplus = $coin + $pluscoin;
$cuser["userfild"]["$from_id"]["coin"]="$coinplus";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
$inuser = json_decode(file_get_contents("data/$inviter.json"),true);
$coininviter = $inuser["userfild"]["$inviter"]["coin"];
$coinplusinviter = $coininviter + $invitercoin ;
$inuser["userfild"]["$inviter"]["coin"]="$coinplusinviter";;
$inuser = json_encode($inuser,true);
file_put_contents("data/$inviter.json",$inuser);
unlink("data/".$from_id."coin.txt");
unlink("data/$from_id.txt");
}
@$user = json_decode(file_get_contents("data/user.json"),true);
@$juser = json_decode(file_get_contents("data/$from_id.json"),true);
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);

if(!in_array($from_id, $user["userlist"]) == true) {
$user["userlist"][]= $from_id;
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
    }
$akl = json_decode(file_get_contents("akl/akl.json"),true);
if($akl["sVISCODEV"] == null){
$sTok="لم يتم التعين ❌";
}else{
$sTok=$akl["sVISCODEV"];
}
if($akl["sVISCODEV"] == null){
$Sdom="لم يتم التعين ❌";
}else{
$Sdom=$akl["sSite"];
}
if($data=="sitingbots"){
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*
	- مرحباً عزيزي المطور $name

- حساب التواصل : $acont_admin
 - عدد ال$cdiamlaadf الدخول : $coins_start
 - عدد $cdiamlaadf الاشتراك  : $add_aoc
 - سعر التمويل  : $add_ado
 - عموله التحويل : $work_add_day
  - ادنى حد لل$cdiamlaadf : $adna_coins
 - عدد $cdiamlaadf الهديه اليوميه : $day_coins
- موقع الرشق : $Sdom
*
- يمكن للعضو ارسال `/id` لارسال الايدي الخاص به",
     'parse_mode'=>"MARKDOWN",
'disable_web_page_preview'=>true,
 'reply_markup'=>json_encode([
             'inline_keyboard'=>[
       [['text'=>" تعيين $cdiamlaadf الدخول",'callback_data'=>'coins_start'],['text'=>"تعيين معرف التواصل",'callback_data'=>'username_admin_twasl']],        
        [['text'=>"اضافه تمويل",'callback_data'=>'admin_addfinance'],['text'=>"مسح قناة",'callback_data'=>'admin_deletech']],
        [['text'=>"جميع القنوات",'callback_data'=>'admin_listfinance']],
        [['text'=>"زيادة $cdiamlaadf",'callback_data'=>'admin_sendcon'],['text'=>"خصم $cdiamlaadf",'callback_data'=>'admin_deletecon']],
        [['text'=>"صنع كود",'callback_data'=>'admin_code'],['text'=>"ارسال $cdiamlaadf للكل",'callback_data'=>'admin_bccon']],
        [['text'=>"ادنى حد عدد لل$cdiamlaadf ",'callback_data'=>'adna_coins'],['text'=>"تعيين الهدية اليومية ",'callback_data'=>'day_coins']],
        [['text'=>"فتح الرشق",'callback_data'=>'opan_ras'],['text'=>"قفل الرشق",'callback_data'=>'off_ras']],
        [['text'=>"فتح التمويل",'callback_data'=>'opan_tmwel'],['text'=>"قفل التمويل",'callback_data'=>'off_tmwel']],
        [['text'=>"فتح تحويل ال$cdiamlaadf",'callback_data'=>'opan_coadd'],['text'=>"قفل تحويل ال$cdiamlaadf",'callback_data'=>'off_coadd']],
        [['text'=>"فتح الهديه اليوميه",'callback_data'=>'opan_add_day'],['text'=>"قفل الهديه اليوميه",'callback_data'=>'off_add_day']],
        [['text'=>"فتح تعريف الاشتراك",'callback_data'=>'opan_asttacbot'],['text'=>"قفل تعريف الاشتراك",'callback_data'=>'off_asttacbot']],
        [['text'=>"تعين ادني حد للتحويل",'callback_data'=>'work_add_day'],['text'=>"تعين عدد $cdiamlaadf الاشتراك",'callback_data'=>'add_cono_tmwel']],
        [['text'=>"تعين رساله الاسعار",'callback_data'=>'msg_asar'],['text'=>"تعين سعر التمويل",'callback_data'=>'add_co_tmwel']],
        [['text'=>"تعين قناة البوت",'callback_data'=>'add_ch_admin'],['text'=>"تعين رساله الشروط",'callback_data'=>'add_msg_sro']],
        [['text'=>"تعين توكن لموقع",'callback_data'=>"VISCODEV" ],['text'=>"تعين موقع الرشق",'callback_data'=>"SiteDomen" ]],
        [['text'=>"قسم الخدمات",'callback_data'=>"xdmat" ],['text'=>"تعين اسم البوت",'callback_data'=>'add_nam_bot']],
        [['text'=>"تعين قناة الاشعارات",'callback_data'=>'aspatchid'],['text'=>"تعين رساله الاشعار",'callback_data'=>'msgaspat']],
        [['text'=>"تعين عملة البوت",'callback_data'=>'cdiamlaadf']],
        [['text'=>"نظام تمويل القنوات فقط : $nzambot",'callback_data'=>'nzambot']],
        [['text'=>" • رجوع •  ",'callback_data'=>"home"]],
               ]
         ])
 ]);
}


elseif($data =="paneladmin"){
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*
	- مرحباً عزيزي المطور $name

- حساب التواصل : $acont_admin
 - عدد ال$cdiamlaadf الدخول : $coins_start
 - عدد $cdiamlaadf الاشتراك  : $add_aoc
 - سعر التمويل  : $add_ado
 - عموله التحويل : $work_add_day
  - ادنى حد لل$cdiamlaadf : $adna_coins
 - عدد $cdiamlaadf الهديه اليوميه : $day_coins
- موقع الرشق : $Sdom
*
- يمكن للعضو ارسال `/id` لارسال الايدي الخاص به",
     'parse_mode'=>"MARKDOWN",
'disable_web_page_preview'=>true,
 'reply_markup'=>json_encode([
             'inline_keyboard'=>[
       [['text'=>" تعيين $cdiamlaadf الدخول",'callback_data'=>'coins_start'],['text'=>"تعيين معرف التواصل",'callback_data'=>'username_admin_twasl']],        
        [['text'=>"اضافه تمويل",'callback_data'=>'admin_addfinance'],['text'=>"مسح قناة",'callback_data'=>'admin_deletech']],
        [['text'=>"جميع القنوات",'callback_data'=>'admin_listfinance']],
        [['text'=>"زيادة $cdiamlaadf",'callback_data'=>'admin_sendcon'],['text'=>"خصم $cdiamlaadf",'callback_data'=>'admin_deletecon']],
        [['text'=>"صنع كود",'callback_data'=>'admin_code'],['text'=>"ارسال $cdiamlaadf للكل",'callback_data'=>'admin_bccon']],
        [['text'=>"ادنى حد عدد لل$cdiamlaadf ",'callback_data'=>'adna_coins'],['text'=>"تعيين الهدية اليومية ",'callback_data'=>'day_coins']],
        [['text'=>"فتح الرشق",'callback_data'=>'opan_ras'],['text'=>"قفل الرشق",'callback_data'=>'off_ras']],
        [['text'=>"فتح التمويل",'callback_data'=>'opan_tmwel'],['text'=>"قفل التمويل",'callback_data'=>'off_tmwel']],
        [['text'=>"فتح تحويل ال$cdiamlaadf",'callback_data'=>'opan_coadd'],['text'=>"قفل تحويل ال$cdiamlaadf",'callback_data'=>'off_coadd']],
        [['text'=>"فتح الهديه اليوميه",'callback_data'=>'opan_add_day'],['text'=>"قفل الهديه اليوميه",'callback_data'=>'off_add_day']],
        [['text'=>"فتح تعريف الاشتراك",'callback_data'=>'opan_asttacbot'],['text'=>"قفل تعريف الاشتراك",'callback_data'=>'off_asttacbot']],
        [['text'=>"تعين ادني حد للتحويل",'callback_data'=>'work_add_day'],['text'=>"تعين عدد $cdiamlaadf الاشتراك",'callback_data'=>'add_cono_tmwel']],
        [['text'=>"تعين رساله الاسعار",'callback_data'=>'msg_asar'],['text'=>"تعين سعر التمويل",'callback_data'=>'add_co_tmwel']],
        [['text'=>"تعين قناة البوت",'callback_data'=>'add_ch_admin'],['text'=>"تعين رساله الشروط",'callback_data'=>'add_msg_sro']],
        [['text'=>"تعين توكن لموقع",'callback_data'=>"VISCODEV" ],['text'=>"تعين موقع الرشق",'callback_data'=>"SiteDomen" ]],
        [['text'=>"قسم الخدمات",'callback_data'=>"xdmat" ],['text'=>"تعين اسم البوت",'callback_data'=>'add_nam_bot']],
        [['text'=>"تعين قناة الاشعارات",'callback_data'=>'aspatchid'],['text'=>"تعين رساله الاشعار",'callback_data'=>'msgaspat']],
        [['text'=>"تعين عملة البوت",'callback_data'=>'cdiamlaadf']],
        [['text'=>"نظام تمويل القنوات فقط : $nzambot",'callback_data'=>'nzambot']],
        [['text'=>" • رجوع •  ",'callback_data'=>"home"]],  ]
           ])
   ]);
$cuser["userfild"]["$from_id"]["file"]="none";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}
if($data == 'nzambot' and $nzambot =='✅'){
file_put_contents("edid/nzambot.txt","❌");
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*
	- مرحباً عزيزي المطور $name

- حساب التواصل : $acont_admin
 - عدد ال$cdiamlaadf الدخول : $coins_start
 - عدد $cdiamlaadf الاشتراك  : $add_aoc
 - سعر التمويل  : $add_ado
 - عموله التحويل : $work_add_day
  - ادنى حد لل$cdiamlaadf : $adna_coins
 - عدد $cdiamlaadf الهديه اليوميه : $day_coins
- موقع الرشق : $Sdom
*
- يمكن للعضو ارسال `/id` لارسال الايدي الخاص به",
     'parse_mode'=>"MARKDOWN",
'disable_web_page_preview'=>true,
 'reply_markup'=>json_encode([
             'inline_keyboard'=>[
       [['text'=>" تعيين $cdiamlaadf الدخول",'callback_data'=>'coins_start'],['text'=>"تعيين معرف التواصل",'callback_data'=>'username_admin_twasl']],        
        [['text'=>"اضافه تمويل",'callback_data'=>'admin_addfinance'],['text'=>"مسح قناة",'callback_data'=>'admin_deletech']],
        [['text'=>"جميع القنوات",'callback_data'=>'admin_listfinance']],
        [['text'=>"زيادة $cdiamlaadf",'callback_data'=>'admin_sendcon'],['text'=>"خصم $cdiamlaadf",'callback_data'=>'admin_deletecon']],
        [['text'=>"صنع كود",'callback_data'=>'admin_code'],['text'=>"ارسال $cdiamlaadf للكل",'callback_data'=>'admin_bccon']],
        [['text'=>"ادنى حد عدد لل$cdiamlaadf ",'callback_data'=>'adna_coins'],['text'=>"تعيين الهدية اليومية ",'callback_data'=>'day_coins']],
        [['text'=>"فتح الرشق",'callback_data'=>'opan_ras'],['text'=>"قفل الرشق",'callback_data'=>'off_ras']],
        [['text'=>"فتح التمويل",'callback_data'=>'opan_tmwel'],['text'=>"قفل التمويل",'callback_data'=>'off_tmwel']],
        [['text'=>"فتح تحويل ال$cdiamlaadf",'callback_data'=>'opan_coadd'],['text'=>"قفل تحويل ال$cdiamlaadf",'callback_data'=>'off_coadd']],
        [['text'=>"فتح الهديه اليوميه",'callback_data'=>'opan_add_day'],['text'=>"قفل الهديه اليوميه",'callback_data'=>'off_add_day']],
        [['text'=>"فتح تعريف الاشتراك",'callback_data'=>'opan_asttacbot'],['text'=>"قفل تعريف الاشتراك",'callback_data'=>'off_asttacbot']],
        [['text'=>"تعين ادني حد للتحويل",'callback_data'=>'work_add_day'],['text'=>"تعين عدد $cdiamlaadf الاشتراك",'callback_data'=>'add_cono_tmwel']],
        [['text'=>"تعين رساله الاسعار",'callback_data'=>'msg_asar'],['text'=>"تعين سعر التمويل",'callback_data'=>'add_co_tmwel']],
        [['text'=>"تعين قناة البوت",'callback_data'=>'add_ch_admin'],['text'=>"تعين رساله الشروط",'callback_data'=>'add_msg_sro']],
        [['text'=>"تعين توكن لموقع",'callback_data'=>"VISCODEV" ],['text'=>"تعين موقع الرشق",'callback_data'=>"SiteDomen" ]],
        [['text'=>"قسم الخدمات",'callback_data'=>"xdmat" ],['text'=>"تعين اسم البوت",'callback_data'=>'add_nam_bot']],
        [['text'=>"تعين قناة الاشعارات",'callback_data'=>'aspatchid'],['text'=>"تعين رساله الاشعار",'callback_data'=>'msgaspat']],
        [['text'=>"تعين عملة البوت",'callback_data'=>'cdiamlaadf']],
        [['text'=>"نظام تمويل القنوات فقط : ❌",'callback_data'=>'nzambot']],
        [['text'=>" • رجوع •  ",'callback_data'=>"home"]], 
 ] 
])]);
file_put_contents("edid/aklamrnm1.txt",'الخدمات 🗂');
}
if($data == 'nzambot' and $nzambot =='❌'){
file_put_contents("edid/nzambot.txt","✅");
file_put_contents("edid/aklamrnm1.txt",'تمويل قناتك 🗂');
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*
	- مرحباً عزيزي المطور $name

- حساب التواصل : $acont_admin
 - عدد ال$cdiamlaadf الدخول : $coins_start
 - عدد $cdiamlaadf الاشتراك  : $add_aoc
 - سعر التمويل  : $add_ado
 - عموله التحويل : $work_add_day
  - ادنى حد لل$cdiamlaadf : $adna_coins
 - عدد $cdiamlaadf الهديه اليوميه : $day_coins
- موقع الرشق : $Sdom
*
- يمكن للعضو ارسال `/id` لارسال الايدي الخاص به",
     'parse_mode'=>"MARKDOWN",
'disable_web_page_preview'=>true,
 'reply_markup'=>json_encode([
             'inline_keyboard'=>[
       [['text'=>" تعيين $cdiamlaadf الدخول",'callback_data'=>'coins_start'],['text'=>"تعيين معرف التواصل",'callback_data'=>'username_admin_twasl']],        
        [['text'=>"اضافه تمويل",'callback_data'=>'admin_addfinance'],['text'=>"مسح قناة",'callback_data'=>'admin_deletech']],
        [['text'=>"جميع القنوات",'callback_data'=>'admin_listfinance']],
        [['text'=>"زيادة $cdiamlaadf",'callback_data'=>'admin_sendcon'],['text'=>"خصم $cdiamlaadf",'callback_data'=>'admin_deletecon']],
        [['text'=>"صنع كود",'callback_data'=>'admin_code'],['text'=>"ارسال $cdiamlaadf للكل",'callback_data'=>'admin_bccon']],
        [['text'=>"ادنى حد عدد لل$cdiamlaadf ",'callback_data'=>'adna_coins'],['text'=>"تعيين الهدية اليومية ",'callback_data'=>'day_coins']],
        [['text'=>"فتح الرشق",'callback_data'=>'opan_ras'],['text'=>"قفل الرشق",'callback_data'=>'off_ras']],
        [['text'=>"فتح التمويل",'callback_data'=>'opan_tmwel'],['text'=>"قفل التمويل",'callback_data'=>'off_tmwel']],
        [['text'=>"فتح تحويل ال$cdiamlaadf",'callback_data'=>'opan_coadd'],['text'=>"قفل تحويل ال$cdiamlaadf",'callback_data'=>'off_coadd']],
        [['text'=>"فتح الهديه اليوميه",'callback_data'=>'opan_add_day'],['text'=>"قفل الهديه اليوميه",'callback_data'=>'off_add_day']],
        [['text'=>"فتح تعريف الاشتراك",'callback_data'=>'opan_asttacbot'],['text'=>"قفل تعريف الاشتراك",'callback_data'=>'off_asttacbot']],
        [['text'=>"تعين ادني حد للتحويل",'callback_data'=>'work_add_day'],['text'=>"تعين عدد $cdiamlaadf الاشتراك",'callback_data'=>'add_cono_tmwel']],
        [['text'=>"تعين رساله الاسعار",'callback_data'=>'msg_asar'],['text'=>"تعين سعر التمويل",'callback_data'=>'add_co_tmwel']],
        [['text'=>"تعين قناة البوت",'callback_data'=>'add_ch_admin'],['text'=>"تعين رساله الشروط",'callback_data'=>'add_msg_sro']],
        [['text'=>"تعين توكن لموقع",'callback_data'=>"VISCODEV" ],['text'=>"تعين موقع الرشق",'callback_data'=>"SiteDomen" ]],
        [['text'=>"قسم الخدمات",'callback_data'=>"xdmat" ],['text'=>"تعين اسم البوت",'callback_data'=>'add_nam_bot']],
        [['text'=>"تعين قناة الاشعارات",'callback_data'=>'aspatchid'],['text'=>"تعين رساله الاشعار",'callback_data'=>'msgaspat']],
        [['text'=>"تعين عملة البوت",'callback_data'=>'cdiamlaadf']],
        [['text'=>"نظام تمويل القنوات فقط : ✅",'callback_data'=>'nzambot']],
        [['text'=>" • رجوع •  ",'callback_data'=>"home"]], 
 ] 
])]);
}
if($data == 'opan_ras'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح قسم الرشق ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/opan.txt","✅");
file_put_contents("edid/nzambot.txt","❌");
}
if($data == 'off_ras'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل قسم الرشق ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/opan.txt","❌");
}
if($data == 'opan_tmwel'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح قسم التمويل ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/tmwel.txt","✅");
}
if($data == 'off_tmwel'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل قسم التمويل ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/tmwel.txt","❌");
}
if($data == 'opan_coadd'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح قسم التحويل ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/coadd.txt","✅");
}
if($data == 'off_coadd'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل قسم التحويل ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/coadd.txt","❌");
}
if($data == 'opan_add_day'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح قسم الهدية ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/add_day.txt","✅");
}
if($data == 'off_add_day'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل قسم الهدية ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/add_day.txt","❌");
}
if($data == 'opan_asttacbot'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح فتح قسم التعريف ✅*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/asttacbot.txt","✅");
}
if($data == 'off_asttacbot'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*تم بنجاح قفل قسم التعريف ❌*",
 'parse_mode'=>"MARKDOWN",
'reply_markup'=>json_encode([
'inline_keyboard'=>[[['text'=>"رجوع",'callback_data'=>'home']],
 ] 
])]);
file_put_contents("edid/asttacbot.txt","❌");
}
if($data == 'msg_asar'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*مرحبا عزيزي

قوم بارسال رساله الاسعار الان*",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/as1.txt","on");
}
if($text and file_get_contents("edid/as1.txt") == "on" and $text != '/start'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/as1.txt");
file_put_contents("edid/msgasar.txt",$text);
}
if($data == 'cdiamlaadf'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*مرحبا عزيزي

قوم بارسال الكلمه الي تريدها الان*",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/acdiamlaadf.txt","on");
}
if($text and file_get_contents("edid/acdiamlaadf.txt") == "on" and $text != '/start'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/acdiamlaadf.txt");
file_put_contents("edid/cdiamlaadf.txt",$text);
}
if($data == 'msgaspat'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"• ارسال الان الكليشه .

- يمكنك وضع بعض الاضافات الى كليشه الاشعارات من خلال استخدام الاهاشتاكات التاليه :
  
1. `#nameService` : لوضع اسم الخدمه
2. `#coinService` : لوضع سعر الخدمه
3. `#numberall` : لوضع عدد الطلبات
4. `#Link` : لوضع رابط الطلب
5. `#name_user` : لوضع اسم الشخص برابط
6. `#numberLink` : لوضع عدد الطلب
7. `#id` : لوضع ايدي العضو

*يمكنك تعين نص ماركداون في البوت , عند كتابه معرف قناتك او معرفك قم بوضع [] بين المعرف .*

",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/msgaspat2.txt","on");
}
if($text and file_get_contents("edid/msgaspat2.txt") == "on" and $text != '/start'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/msgaspat2.txt");
file_put_contents("edid/msgaspat.txt",$text);
}

if($data == 'add_msg_sro'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*مرحبا عزيزي

قوم بارسال رساله الشروط الان*",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/as2.txt","on");
}
if($text and file_get_contents("edid/as2.txt") == "on" and $text != '/start'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/as2.txt");
file_put_contents("edid/msgasro.txt",$text);
}
if($data == 'add_nam_bot'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*مرحبا عزيزي

قوم بارسال اسم البوت الان*",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/as34.txt","on");
}
if($text and file_get_contents("edid/as34.txt") == "on" and $text != '/start'){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/as34.txt");
file_put_contents("edid/nambot.txt",$text);
}
if($data == 'add_co_tmwel'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"• ارسل عدد ال$cdiamlaadf مقابل العضو الواحد 📬",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/add_ao.txt","on");
}
if($text and file_get_contents("edid/add_ao.txt") == "on" and $text != '/start'){
if(preg_match('/([0-9])/i',$text)){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/add_ao.txt");
file_put_contents("edid/addado.txt",$text);
}}
if($data == 'add_cono_tmwel'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"• ارسل عدد ال$cdiamlaadf التي يحصل عليها الشخص عند الاشتراك في قناة 📬",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/add_aoc.txt","on");
}
if($text and file_get_contents("edid/add_aoc.txt") == "on" and $text != '/start'){
if(preg_match('/([0-9])/i',$text)){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/add_aoc.txt");
file_put_contents("edid/add_aoc.txt",$text);
}}
if($data == 'add_ch_admin'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"• ارسل معرف القناة الان 📬",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/chadm.txt","on");
}
if($text and file_get_contents("edid/chadm.txt") == "on" and $text != '/start'){
if(preg_match('/^(@)(.*)/s',$text)){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/chadm.txt");
file_put_contents("edid/chadmin.txt",$text);
}}
if($data == 'aspatchid'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*- ارسل توجيه من القناة لرساله نصيه فقط !*",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                   [['text'=>"مسح القناة وتعطيل النشر ",'callback_data'=>'deletaspat']],
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/aspatchid.txt","on");
}
if($text and file_get_contents("edid/aspatchid.txt") == "on" and $text != '/start'){
if($message->forward_from_chat ){
$id_channel= $message->forward_from_chat->id;
$get = file_get_contents("http://api.tlgr.org/bot".API_KEY."/getChat?chat_id=$id_channel"); 
$msg=$a->result->message_id;
$js = json_decode($get); 
$title=$js->result->title;
$channel=$js->result->username;
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

اسم القناة : [$title]
معرف القناة : [@$channel]
ايدي القناة : $id_channel",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/aspatchid.txt");
file_put_contents("edid/aspatchid1.txt",$id_channel);
}}
if($data == 'deletaspat'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*- تم مسح القناة وتعطيل نشر الاشعارات !*",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
unlink("edid/aspatchid1.txt");
}
elseif($data == "work_add_day"){
if(in_array($from_id,$Dev)){
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
		'text'=>"• ارسل عدد ادني حد التحويل الان .",
    'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع",'callback_data'=>'paneladmin']
    ],
          ]
    ])
		]);
$cuser["userfild"]["$from_id"]["file"]="work_add_day";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
		}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'work_add_day') {
if(preg_match('/([0-9])/i',$text)){
  bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"✅ تم حفظ ادني حد التحويل $text $cdiamlanoo ",
      'reply_to_message_id'=>$message_id,
      'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع",'callback_data'=>'paneladmin']
    ],
          ]
    ])
   ]);
file_put_contents("edid/work_add_day.txt",$text);
$cuser["userfild"]["$from_id"]["file"]="work_add_ay";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}}

#تعيين $cdiamlaadf الدخول 
if($data == 'coins_start'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*• ارسل عدد ال$cdiamlaadf التي يحصل عليها المستخدم من مشاركة الرابط الخاص به .*",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/coinstart.txt","on");
}
if($text and file_get_contents("edid/coinstart.txt") == "on" and $text != '/start'){
if(preg_match('/([0-9])/i',$text)){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"*✅ تم حفظ $cdiamlaadf الدخول $text $cdiamlanoo *",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/coinstart.txt");
file_put_contents("edid/coinsstart.txt",$text);
}}

#تعيين حساب التواصل 
if($data == 'username_admin_twasl'){
  bot('EditMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"• ارسال معرف حسابك او بوت تواصل الخاص بك 📬",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'paneladmin']],
                     ]
               ])
 ]);
file_put_contents("edid/oen_admin.txt","on");
}
if($text and file_get_contents("edid/oen_admin.txt") == "on" and $text != '/start'){
if(preg_match('/^(@)(.*)/s',$text)){
bot('sendMessage',[
'chat_id'=>$chat_id,
'text'=>"
*تم الحفظ بنجاح ✅*

: [$text]
",
'parse_mode'=>"MARKDOWN",
'reply_to_message_id'=>$message->message_id,
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [['text'=>"رجوع",'callback_data'=>'home']],
                     ]
               ])
]);
unlink("edid/oen_admin.txt");
file_put_contents("edid/acont_admin.txt",$text);
}
}
#ادنى حد
elseif($data == "adna_coins"){
if(in_array($from_id,$Dev)){
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
		'text'=>" قم بإرسال عدد $cdiamlaadf ادنئ حد لطلب التمويل",
    'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
    ],
          ]
    ])
		]);
$cuser["userfild"]["$from_id"]["file"]="adna_coins";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
		}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'adna_coins') {
 if(preg_match('/([0-9])/i',$text)){
  bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"✅ تم حفظ الحد الادنئ $text $cdiamlanoo ",
      'reply_to_message_id'=>$message_id,
      'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
    ],
          ]
    ])
   ]);
file_put_contents("data/adna_coins.txt",$text);
}}
elseif($data == "day_coins"){
if(in_array($from_id,$Dev)){
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
		'text'=>"قم بإرسال عدد $cdiamlaadf  الهدية اليومية",
    'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
    ],
          ]
    ])
		]);
$cuser["userfild"]["$from_id"]["file"]="day_coins";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
		}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'day_coins') {
 if(preg_match('/([0-9])/i',$text)){
  bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"✅ تم حفظ $cdiamlaadf الهدية اليومية $text $cdiamlanoo ",
      'reply_to_message_id'=>$message_id,
      'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
    ],
          ]
    ])
   ]);
file_put_contents("data/day_coins.txt",$text);
}}
elseif($data == "admin_addfinance"){
if(in_array($from_id,$Dev)){
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
		'text'=>"• قم برفع البوت ادمن في القناة من ثم ارسل معرف القناة",
    'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع",'callback_data'=>'paneladmin']
    ],
          ]
    ])
		]);
$cuser["userfild"]["$from_id"]["file"]="addfinance";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
		}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'addfinance') {
  $checkadmin = getChatstats($text,$VISCODEV);
  if($checkadmin == true){
  bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"• ارسل عدد الاعضاء المراد اضافته",
      'reply_to_message_id'=>$message_id,
      'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
    ],
          ]
    ])
   ]);
$juser["idforsend"]="$text";
$juser["userfild"]["$from_id"]["file"]="addfinance_2";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}else {
  bot('sendmessage',[
            'chat_id'=>$chat_id,
   'text' => "ارفع البوت ادمن اولا ❗️",
'reply_markup'=>json_encode([
  'inline_keyboard'=>[
[
['text'=>"رجوع",'callback_data'=>'paneladmin']
],
    ]
])
]);
}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'addfinance_2') {
if(preg_match('/([0-9])/i',$text)){
$id = $juser["idforsend"];
$user["finance"][]=[$id,$text];
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"• تم الحفظ",
	  'reply_to_message_id'=>$message_id,
    'reply_markup'=>json_encode([
  'inline_keyboard'=>[
[
['text'=>"رجوع",'callback_data'=>'paneladmin']
],
    ]
])
 ]);
$juser["userfild"]["$from_id"]["file"]="none";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}}
if(preg_match("/^(coin)_(.*)_(.*)/s",$data)){
   preg_match("/^(coin)_(.*)_(.*)/s",$data,$matchaa);
  $channel = $matchaa[2];
  $coinpluss = $matchaa[3];
  $join = json_encode(bot_curl('getChatMember', ['chat_id' => $channel, 'user_id' => $from_id]));
  if((strpos($join,'"status":"left"') or strpos($join,'"Bad Request: USER_ID_INVALID"') or strpos($join,'"status":"kicked"'))=== false){
    bot('deleteMessage',[
   'chat_id'=>$chat_id,
   'message_id'=>$message_id
       ]);
 bot('answercallbackquery', [
            'callback_query_id' =>$membercall,
            'text' => "تم اعطائك ($coinpluss)",
            'show_alert' =>false
        ]);
    $inuser = json_decode(file_get_contents("data/$from_id.json"),true);
    $coin = $inuser["userfild"]["$from_id"]["coin"];
    $coinplus = $coin + $coinpluss;
    $inuser["userfild"]["$from_id"]["coin"]="$coinplus";
    $inuser = json_encode($inuser,true);
    file_put_contents("data/$from_id.json",$inuser);
    // --- Wallet ledger: تسجيل حركة شحن ---
    wallet_log($from_id, 'charge', $coinpluss, "شحن عبر قناة $channel", ['balance_after' => $coinplus]);
}else {
  bot('answercallbackquery', [
    'callback_query_id' =>$membercall,
    'text' => "عذرا ❗️اشترك بلقناة اولا ",
    'show_alert' =>true
]);
}
}
elseif($data=="admin_channels"){
if(in_array($from_id,$Dev)){
$order = $user["channellist"];
$ordercount = count($user["channellist"]);
for($z = 0;$z <= count($order)-1;$z++){
$result = $result.$order[$z]."\n";
}
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
		'text'=>"📣 القنوات الجاري تمويلها($ordercount):
$result",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
[
['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
],
      ]
])
		]);
		}
}
elseif($data=="admin_listfinance"){
if(in_array($from_id,$Dev)){
$arr = $user['finance'];
$out = "" ;
for($z = 0;$z <= count($arr);$z++){
if($arr[$z][0] != null and $arr[$z][0] != ""){
$out = $out.$arr[$z][0]." - ".$arr[$z][1]."\n";
}
}
bot('editmessagetext',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
		'text'=>"هذه جميع القنوات الجاري تمويله 
		
$out",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
[
['text'=>"رجوع",'callback_data'=>'paneladmin']
],
 ]
])
]);}}
elseif($data == "admin_deletech"){
if(in_array($from_id,$Dev)){
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
		'text'=>"ارسل معرف القناة الان ليتم حذفه من التمويل",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
[
['text'=>"رجوع",'callback_data'=>'paneladmin']
],
      ]
])
		]);
$cuser["userfild"]["$from_id"]["file"]="remorder";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
		}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'remorder') {
$how = array_search($text,$user["channellist"]);
unset($user["setmemberlist"][$how]);
unset($user["channellist"][$how]);
unset($user["admin"][$how]);
$user["channellist"]=array_values($user["channellist"]);
$user["setmemberlist"]=array_values($user["setmemberlist"]);
$user["admin"]=array_values($user["admin"]);
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
$juser["userfild"]["$from_id"]["file"]="none";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
bot('sendmessage',[
 'chat_id'=>$chat_id,
 'text'=>"• تم الحفظ",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
[
['text'=>"رجوع",'callback_data'=>'paneladmin']
],
      ]
])
]);
}
elseif($data == "admin_sendcon"){
if(in_array($from_id,$Dev)){
  bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
		'text'=>"ارسل ايدي العضو او رساله موجه منه",
    'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
    ],
          ]
    ])
		]);
$cuser["userfild"]["$from_id"]["file"]="adminsendcoin";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
		}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'adminsendcoin') {
if($text == $admin){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*لا يمكن ارسال $cdiamlaadf لمالك البوت حساب المالك يجب تفقد البوت منه ليعرف المشاكل *",
 'parse_mode'=>"markdown",
  'reply_to_message_id'=>$message_id,
    'reply_markup'=>json_encode([
  'inline_keyboard'=>[
[
['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
],]
])
 ]);
$juser["userfild"]["$from_id"]["file"]="null";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
return false;}
if($forward_from == true) {
  bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"جيد, الأن ارسل عدد ال$cdiamlaadf ",
      'reply_to_message_id'=>$message_id,
      'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
    ],
          ]
    ])
   ]);
$juser["idforsend"]="$forward_from_id";
$juser["userfild"]["$from_id"]["file"]="sethowsendcoin";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
else
{
	         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"جيد, الأن ارسل عدد ال$cdiamlaadf",
	  'reply_to_message_id'=>$message_id,
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
  [
  ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
  ],
        ]
  ])
 ]);
$juser["idforsend"]="$text";
$juser["userfild"]["$from_id"]["file"]="sethowsendcoin";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'sethowsendcoin') {
if(preg_match('/([0-9])/i',$text)){
$id = $juser["idforsend"];
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"تم بنجاح ارسال $text الي $id",
	  'reply_to_message_id'=>$message_id,
    'reply_markup'=>json_encode([
  'inline_keyboard'=>[
[
['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
],
    ]
])
 ]);
          bot('sendmessage',[
        	'chat_id'=>$id,
        	'text'=>"تم ارسال لك $text $cdiamlanoo هدية من البوت ",
			               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
$inuser = json_decode(file_get_contents("data/$id.json"),true);
$coin = $inuser["userfild"]["$id"]["coin"];
$coinplus = $coin + $text;
$inuser["userfild"]["$id"]["coin"]="$coinplus";
$inuser = json_encode($inuser,true);
file_put_contents("data/$id.json",$inuser);
// --- Wallet ledger: شحن مباشر من الأدمن ---
wallet_log($id, 'charge', $text, "شحن مباشر من الأدمن", ['balance_after' => $coinplus]);
$juser["userfild"]["$from_id"]["file"]="none";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}}
elseif($data == "admin_deletecon"){
if(in_array($from_id,$Dev)){
  bot('editmessagetext',[
                    'chat_id'=>$chat_id,
         'message_id'=>$message_id,
      'text'=>"ارسل رسالة موجهة أو ايدي العضو  ",
      'reply_markup'=>json_encode([
          'inline_keyboard'=>[
      [
      ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
      ],
            ]
      ])
      ]);
$cuser["userfild"]["$from_id"]["file"]="adminsendcoin2";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
		}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'adminsendcoin2') {
if($forward_from == true) {
  bot('sendmessage',[
            'chat_id'=>$chat_id,
            'text'=>"جيد, الأن ارسل عدد ال$cdiamlaadf التي تريد خصمها",
      'reply_to_message_id'=>$message_id,
      'reply_markup'=>json_encode([
        'inline_keyboard'=>[
    [
    ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
    ],
          ]
    ])
   ]);
$juser["idforsend"]="$forward_from_id";
$juser["userfild"]["$from_id"]["file"]="sethowsendcoin2";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
else
{
 bot('sendmessage',[
          'chat_id'=>$chat_id,
          'text'=>"جيد, الأن ارسل عدد ال$cdiamlaadf التي تريد خصمها",
    'reply_to_message_id'=>$message_id,
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
  [
  ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
  ],
        ]
  ])
 ]);
$juser["idforsend"]="$text";
$juser["userfild"]["$from_id"]["file"]="sethowsendcoin2";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'sethowsendcoin2') {
if(preg_match('/([0-9])/i',$text)){
$id = $juser["idforsend"];
bot('sendmessage',[
 'chat_id'=>$chat_id,
 'text'=>"تم خصم [$text] من $id",
'reply_to_message_id'=>$message_id,
'reply_markup'=>json_encode([
'inline_keyboard'=>[
[
['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
],
]
])
]);
          bot('sendmessage',[
        	'chat_id'=>$id,
        	'text'=>"تم خصم [$text] من $cdiamlao من قبل البوت",
			               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
 ]);
$inuser = json_decode(file_get_contents("data/$id.json"),true);
$coin = $inuser["userfild"]["$id"]["coin"];
$coinplus = $coin - $text;
$inuser["userfild"]["$id"]["coin"]="$coinplus";
$inuser = json_encode($inuser,true);
file_put_contents("data/$id.json",$inuser);
// --- Wallet ledger: خصم مباشر من الأدمن ---
wallet_log($id, 'deduct', $text, "خصم مباشر من الأدمن", ['balance_after' => $coinplus]);
$juser["userfild"]["$from_id"]["file"]="none";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}}
@$user = json_decode(file_get_contents("data/user.json"),true);
@$juser = json_decode(file_get_contents("data/$from_id.json"),true);
@$cuser = json_decode(file_get_contents("data/$from_id.json"),true);

if(!in_array($from_id, $user["userlist"]) == true) {
$user["userlist"][]= $from_id;
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
    }
elseif($data == "admin_code"){
$Rand = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), -8);
$codet = $user["codecoin"];
file_put_contents("edid/hownmcode.txt","$codet");
bot('editmessagetext',[
                  'chat_id'=>$chat_id,
       'message_id'=>$message_id,
 'text'=>"• ارسال عدد ال$cdiamlaadf المراد وضعها في الهدية .",
'reply_markup'=>json_encode([
  'inline_keyboard'=>[
[
['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
],
    ]
])
]);
$user["codecoin"]="$Rand";
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
$cuser["userfild"]["$from_id"]["file"]="howcodecoin";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}
elseif($juser["userfild"]["$from_id"]["file"] == 'howcodecoin') {
if(preg_match('/([0-9])/i',$text)){
$codr = file_get_contents("edid/hownmcode.txt");
unlink("edid/howcdead $codr.txt");
unlink("edid/howcode $codr.txt");
unlink("edid/howcodeadd $codr.txt");
$code = $user["codecoin"];
file_put_contents("edid/howcode $code.txt","$text");
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"        • ارسل عدد الاعضاء الذين يمكنهم الحصول على الهدية .",
  'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([
   'inline_keyboard'=>[
[
['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
],
     ]
])
 ]);
$juser["userfild"]["$from_id"]["file"]="howcfiadd";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}}
elseif($juser["userfild"]["$from_id"]["file"] == 'howcfiadd') {
if(preg_match('/([0-9])/i',$text)){
if($text=="1"){
bot('sendmessage',[
'chat_id'=>$chat_id,
'text'=>"*عذرا اقل عدد يمكنك تعينه من الاشخاص هوا 2 عضو ❌*",
'parse_mode'=>"markdown",
  'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([
   'inline_keyboard'=>[
[
['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
],
     ]
])
 ]);return false;}
$code = $user["codecoin"];
file_put_contents("edid/howcodeadd $code.txt","$text");
$wocfiadd = file_get_contents("edid/howcode $code.txt");
unlink("edid/howcode $code.txt");
         bot('sendmessage',[
        	'chat_id'=>$chat_id,
        	'text'=>"
• تم صنع كود هدية بقيمة *$wocfiadd* $cdiamlanoo

• الكود : $code

• الرابط صالح لمده *30* ساعة

• يمكن الي *$text* عضو الاستمتاع بالهدية
",
'parse_mode'=>"markdown",
  'reply_to_message_id'=>$message->message_id,
'reply_markup'=>json_encode([
   'inline_keyboard'=>[
[
['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
],
     ]
])
 ]);
$user["howcoincode"]="$wocfiadd";
$user = json_encode($user,true);
file_put_contents("data/user.json",$user);
$juser["userfild"]["$from_id"]["file"]="none";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
}}
elseif($data == "admin_bccon") {
if(in_array($from_id,$Dev)){
  bot('editmessagetext',[
                    'chat_id'=>$chat_id,
         'message_id'=>$message_id,
      'text'=>"• ارسال عدد ال$cdiamlaadf المراد ارسالها الى الجميع .",
      'reply_markup'=>json_encode([
          'inline_keyboard'=>[
      [
      ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
      ],
            ]
      ])
      ]);
$cuser["userfild"]["$from_id"]["file"]="sendcointoall";
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
}
}
elseif($juser["userfild"]["$from_id"]["file"] == 'sendcointoall') {
if(preg_match('/([0-9])/i',$text)){
$juser["userfild"]["$from_id"]["file"]="none";
$juser = json_encode($juser,true);
file_put_contents("data/$from_id.json",$juser);
$numbers = $user["userlist"];
$all = count($user["userlist"]);

bot('sendmessage',[
          'chat_id'=>$chat_id,
          'text'=>"• تم ارسال $text $cdiamlanoo الى جميع مستخدمين البوت الخاص بك .

- قمت بارسال رساله اليهم لكي تخبرهم انك قمت باضافه $text $cdiamlanoo الى حساباتهم .",
    'reply_to_message_id'=>$message_id,
    'reply_markup'=>json_encode([
      'inline_keyboard'=>[
  [
  ['text'=>"رجوع ➡️",'callback_data'=>'paneladmin']
  ],
        ]
  ])
 ]);
for($z = 0;$z <= count($numbers)-1;$z++){
     $user = file_get_contents('Member.txt');
    $members = explode("\n",$user);
    if(!in_array($numbers[$z],$members)){
      $add_user = file_get_contents('Member.txt');
      $add_user .= $numbers[$z]."\n";
     file_put_contents('Member.txt',$add_user);
   bot('sendmessage',[
          'chat_id'=>$numbers[$z],
		  'text'=>"تم اعطائك [$text] $cdiamlaadf هدية من البوت ",
          'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
				   [
['text'=>"رجوع ➡️",'callback_data'=>'panel']
				   ],
                     ]
               ])
        ]);
$juser = json_decode(file_get_contents("data/$numbers[$z].json"),true);
$coin = $juser["userfild"]["$numbers[$z]"]["coin"];
$coinplus = $coin + $text;
$juser["userfild"]["$numbers[$z]"]["coin"]="$coinplus";
$juser = json_encode($juser,true);
file_put_contents("data/$numbers[$z].json",$juser);
// --- Wallet ledger: هدية جماعية من الأدمن لجميع المستخدمين ---
wallet_log($numbers[$z], 'gift', $text, "هدية جماعية من الأدمن", ['balance_after' => $coinplus]);
}
}
}
}
$d = date('D');
$day = explode("\n",file_get_contents("data/".$d.".txt"));
if($d == "Sat"){
unlink("data/Fri.txt");
}
if($d == "Sun"){
unlink("data/Sat.txt");
}
if($d == "Mon"){
unlink("data/Sun.txt");
}
if($d == "Tue"){
unlink("data/Mon.txt");
}
if($d == "Wed"){
unlink("data/The.txt");
}
if($d == "Thu"){
unlink("data/Wed.txt");
}
if($d == "Fri"){
unlink("data/Thu.txt");
}
$add_day = file_exists("edid/add_day.txt") ? file_get_contents("edid/add_day.txt") : "✅";
if($data == 'kk' and $add_day =='❌'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*عذرا القسم تحت الصيانه*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']], ]   ])]);exit;}
if($data == 'kk' and $add_day =='✅'){
  if(!in_array($from_id, $day)){
    bot('answercallbackquery',[
        'callback_query_id'=>$update->callback_query->id,
'text'=>"لقد حصلت على $day_coins $cdiamlanoo ✅",
 'show_alert'=>true,
]);
 file_put_contents("data/".$d.'.txt',$from_id."\n",FILE_APPEND);
$cuser["userfild"]["$from_id"]["coin"]+=$day_coins;
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
// --- Wallet ledger: تسجيل حركة الهدية اليومية ---
wallet_log($from_id, 'daily', $day_coins, "هدية يومية (kk)");
}else{
date_default_timezone_set('Africa/Cairo');
$current_time = time();
$end_of_day = strtotime('tomorrow') - 1;
$remaining_seconds = $end_of_day - $current_time;
$hours = floor($remaining_seconds / 3600);
$minutes = floor(($remaining_seconds % 3600) / 60);
$seconds = $remaining_seconds % 60;
$aaastf = " $hours";
bot('answercallbackquery',[
        'callback_query_id'=>$update->callback_query->id,
'text' =>"طالب بالهدية بعد $hours ساعة",
        'show_alert'=>true,
]);
}
}
$d = date('D');
$day = explode("\n",file_get_contents("data/".$d.".txt"));
if($d == "Sat"){
unlink("data/Fri.txt");
}
if($d == "Sun"){
unlink("data/Sat.txt");
}
if($d == "Mon"){
unlink("data/Sun.txt");
}
if($d == "Tue"){
unlink("data/Mon.txt");
}
if($d == "Wed"){
unlink("data/The.txt");
}
if($d == "Thu"){
unlink("data/Wed.txt");
}
if($d == "Fri"){
unlink("data/Thu.txt");
}
$add_day = file_exists("edid/add_day.txt") ? file_get_contents("edid/add_day.txt") : "✅";
if($data == 'add_day' and $add_day =='❌'){
bot('editMessageText',[
'chat_id'=>$chat_id,
'message_id'=>$message_id,
'text'=>"*عذرا القسم تحت الصيانه*",
'parse_mode'=>"markdown",
'reply_markup'=>json_encode([
    'inline_keyboard'=>[
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']], ]   ])]);exit;}
if($data == 'add_day' and $add_day =='✅'){
  if(!in_array($from_id, $day)){
    bot('answercallbackquery',[
        'callback_query_id'=>$update->callback_query->id,
'text'=>"لقد حصلت على $day_coins $cdiamlanoo ✅",
 'show_alert'=>true,
]);
$invite = $cuser["userfild"]["$from_id"]["invite"];
$coin = $cuser["userfild"]["$from_id"]["coin"];
 file_put_contents("data/".$d.'.txt',$from_id."\n",FILE_APPEND);
$cuser["userfild"]["$from_id"]["coin"]+=$day_coins;
$cuser = json_encode($cuser,true);
file_put_contents("data/$from_id.json",$cuser);
// --- Wallet ledger: تسجيل حركة الهدية اليومية ---
wallet_log($from_id, 'daily', $day_coins, "هدية يومية (add_day)");
date_default_timezone_set('Africa/Cairo');
$current_time = time();
$end_of_day = strtotime('tomorrow') - 1;
$remaining_seconds = $end_of_day - $current_time;
$hours = floor($remaining_seconds / 3600);
$minutes = floor(($remaining_seconds % 3600) / 60);
$seconds = $remaining_seconds % 60;
$aaastf = " $hours";
bot('editmessagetext',[
                'chat_id'=>$chat_id,
     'message_id'=>$message_id,
               'text'=>"🗃️ الحساب
$cdiamlao : $coin $cdiamlanoo
ال$cdiamlaadf المستخدمة : $amrto $cdiamlanoo
لقد دعوت : $invite ✳️
المتبقي للهدية : $hours ساعة
",
               'reply_markup'=>json_encode([
                   'inline_keyboard'=>[
                     [['text'=>"تجميع $cdiamlaadf ✳️",'callback_data'=>'takecoinn'],['text'=>"الهدية 🎁",'callback_data'=>'kk']],
                     [['text'=>"رجوع ➡️",'callback_data'=>'panel']],
                     ]
               ])
			   ]);
}else{
date_default_timezone_set('Africa/Cairo');
$current_time = time();
$end_of_day = strtotime('tomorrow') - 1;
$remaining_seconds = $end_of_day - $current_time;
$hours = floor($remaining_seconds / 3600);
$minutes = floor(($remaining_seconds % 3600) / 60);
$seconds = $remaining_seconds % 60;
$aaastf = " $hours";
bot('answercallbackquery',[
        'callback_query_id'=>$update->callback_query->id,
'text' =>"طالب بالهدية بعد $hours ساعة",
        'show_alert'=>true,
]);
}
}

// ============================================================
// [NEW] معالجات الواجهة الجديدة والوحدات الإضافية (ne_*)
// كل الأسماء أدناه فريدة ولا تتقاطع مع أي بيانات قديمة في الملف.
// ============================================================

$ne_is_admin = ($from_id == $admin) || (is_array($sudo) && in_array($from_id, $sudo));

// ------------------------------------------------------------
// ⚙️ المزيد والإعدادات
// ------------------------------------------------------------
if ($data == "ne_more") {
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "قائمة المزيد والإعدادات\n\nاختر الخيار المناسب:",
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '🔮 القناة الرسمية 🛑', 'callback_data' => 'ne_official_channel'], ['text' => '🔮 قناة الطلبات ✅', 'callback_data' => 'ne_orders_channel']],
            [['text' => '📊 الإحصائيات 📊', 'callback_data' => 'sec_stats'], ['text' => '🙋‍♂️ طلباتي 🗣', 'callback_data' => 'amr5']],
            [['text' => '🔄 طلب تعويض لجميع طلباتي', 'callback_data' => 'ne_compensation_all']],
            [['text' => '🔍 كشف طلب 🔍', 'callback_data' => 'amr4'], ['text' => '💬 الشروط والتعليمات 💬', 'callback_data' => 'amr1']],
            ne_back_row('panel'),
        ]]),
    ]);
    die;
}

if ($data == "ne_official_channel" || $data == "ne_orders_channel") {
    $chn = file_exists("edid/nambot.txt") ? file_get_contents("edid/nambot.txt") : '';
    bot('answercallbackquery', [
        'callback_query_id' => $up->id ?? '',
        'text' => "سيتم تفعيل رابط هذه القناة قريباً من لوحة تحكم الأدمن 🔧",
        'show_alert' => true,
    ]);
    die;
}

if ($data == "ne_compensation_all") {
    bot('answercallbackquery', [
        'callback_query_id' => $up->id ?? '',
        'text' => "تم إرسال طلبك لمراجعة فريق الدعم، سيتم التواصل معك قريباً 🛠",
        'show_alert' => true,
    ]);
    bot('sendmessage', [
        'chat_id' => $admin,
        'parse_mode' => 'HTML',
        'text' => "🔄 <b>طلب تعويض شامل جديد</b>\n🆔 من المستخدم: <code>{$from_id}</code>",
    ]);
    die;
}

// ------------------------------------------------------------
// 💰 تغيير العملة
// ------------------------------------------------------------
if ($data == "ne_currency") {
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "💰 اختر عملتك:",
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '🇸🇦 ريال سعودي 🇸🇦 (ر.س)', 'callback_data' => 'ne_cur_sar']],
            [['text' => '🇺🇸 دولار 💵 ($)', 'callback_data' => 'ne_cur_usd']],
            [['text' => '🇾🇪 ريال يمني قديم 🇾🇪 (ر.ي.ش)', 'callback_data' => 'ne_cur_yer_n']],
            [['text' => '🇾🇪 ريال يمني/جنوب 🇾🇪 (ر.ي.ج)', 'callback_data' => 'ne_cur_yer_s']],
            [['text' => '🇪🇬 جنية مصري 🇪🇬 (ج.م)', 'callback_data' => 'ne_cur_egp']],
            [['text' => '🇮🇶 دينار عراقي 🇮🇶 (د.ع)', 'callback_data' => 'ne_cur_iqd']],
            [['text' => 'P (P)', 'callback_data' => 'ne_cur_p']],
            ne_back_row('panel'),
        ]]),
    ]);
    die;
}

$ne_currency_map = [
    'ne_cur_sar'   => 'ريال سعودي (ر.س)',
    'ne_cur_usd'   => 'دولار ($)',
    'ne_cur_yer_n' => 'ريال يمني قديم (ر.ي.ش)',
    'ne_cur_yer_s' => 'ريال يمني/جنوب (ر.ي.ج)',
    'ne_cur_egp'   => 'جنية مصري (ج.م)',
    'ne_cur_iqd'   => 'دينار عراقي (د.ع)',
    'ne_cur_p'     => 'P',
];
if (isset($ne_currency_map[$data])) {
    $cuser = json_decode(file_get_contents("data/$from_id.json"), true);
    $cuser["userfild"]["$from_id"]["currency"] = $ne_currency_map[$data];
    file_put_contents("data/$from_id.json", json_encode($cuser, JSON_UNESCAPED_UNICODE));
    bot('answercallbackquery', [
        'callback_query_id' => $up->id ?? '',
        'text' => "✅ تم تعيين عملتك: {$ne_currency_map[$data]}",
        'show_alert' => true,
    ]);
    die;
}

// ------------------------------------------------------------
// ➕ رصيد مجاني / نظام الإحالة
// ------------------------------------------------------------
if ($data == "ne_referral") {
    $me = bot_curl('getMe');
    $botUsername = $me->result->username ?? '';
    $cuser = json_decode(file_get_contents("data/$from_id.json"), true);
    $referralCount = (int)($cuser["userfild"]["$from_id"]["invite"] ?? 0);
    $earnings = ne_referral_earnings($from_id);
    $rewardValue = $coins_start ?? 0;
    $link = "https://t.me/{$botUsername}?start={$from_id}";
    $txt  = "👋 <b>يمكنك الآن الحصول على رصيد مجاني من خلال مشاركة رابط الدعوة الخاص بك 😎</b>\n";
    $txt .= "📢 <b>الرابط الخاص بك:</b> <code>{$link}</code>\n";
    $txt .= "🤝 <b>شارك رابط الدعوة الخاص بك مع أصدقائك أو قنواتك أو أي مكان، واحصل على {$rewardValue} مجاناً لكل شخص يقوم بالدخول عبر رابطك 🛑</b>\n";
    $txt .= "⭐ <b>يمكنك الضغط على زر تجهيز إعلان 🌐 للحصول على إعلان جاهز حول البوت</b>\n";
    $txt .= "🛑 <b>لقد قمت بدعوة {$referralCount} شخص إلى الآن 💎</b>\n";
    $txt .= "😎 <b>أرباحك إلى الآن {$earnings} 💰</b>";
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'HTML',
        'text' => $txt,
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '📋 : نسخ رابط الإحالة 📲 .', 'callback_data' => 'ne_referral_copy']],
            [['text' => '♻️ : تجهيز إعلان 🚀 .', 'callback_data' => 'ne_referral_ad']],
            ne_back_row('panel'),
        ]]),
    ]);
    die;
}

if ($data == "ne_referral_copy") {
    $me = bot_curl('getMe');
    $botUsername = $me->result->username ?? '';
    $link = "https://t.me/{$botUsername}?start={$from_id}";
    bot('answercallbackquery', [
        'callback_query_id' => $up->id ?? '',
        'text' => $link,
        'show_alert' => true,
    ]);
    die;
}

if ($data == "ne_referral_ad") {
    $me = bot_curl('getMe');
    $botUsername = $me->result->username ?? '';
    $link = "https://t.me/{$botUsername}?start={$from_id}";
    $ad  = "🚀 <b>بوت لتلبية جميع خدمات السوشيال ميديا بأرخص الأسعار!</b>\n";
    $ad .= "✅ رشق متابعين، لايكات، مشاهدات وأكثر\n";
    $ad .= "💳 شحن فوري وأسعار تنافسية\n";
    $ad .= "🎁 رصيد مجاني عند التسجيل\n\n";
    $ad .= "👇 ابدأ الآن:\n{$link}";
    bot('sendmessage', [
        'chat_id' => $chat_id,
        'parse_mode' => 'HTML',
        'text' => $ad,
    ]);
    die;
}

// ------------------------------------------------------------
// 📞 الدعم الفني
// ------------------------------------------------------------
if ($data == "ne_support") {
    $txt  = "💁‍♂️ <b>مرحباً بك في قسم الدعم الفني</b>\n";
    $txt .= "📌 <b>اختر طريقة المساعدة التي تريدها:</b>\n";
    $txt .= "1️⃣ <b>التواصل المباشر - للتواصل مع فريق الدعم مباشرة</b>\n";
    $txt .= "2️⃣ <b>الدعم الفني مدعوم بالذكاء الاصطناعي - للإجابة على استفساراتك حول البوت بشكل تلقائي</b>";
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'HTML',
        'text' => $txt,
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '💬 : التواصل المباشر', 'callback_data' => 'ne_support_direct']],
            [['text' => '🤖 : الدعم بالذكاء الاصطناعي', 'callback_data' => 'ne_support_ai']],
            ne_back_row('panel'),
        ]]),
    ]);
    die;
}

if ($data == "ne_support_direct") {
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'HTML',
        'text' => "💬 <b>التواصل المباشر</b>\n\nيمكنك مراسلة فريق الدعم مباشرة عبر: " . BOT_SUPPORT . "\nسيتم الرد عليك في أقرب وقت ممكن 🙏",
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '📩 مراسلة الدعم', 'url' => 'https://t.me/' . ltrim(BOT_SUPPORT, '@')]],
            ne_back_row('ne_support'),
        ]]),
    ]);
    die;
}

if ($data == "ne_support_ai") {
    $cuser = json_decode(file_get_contents("data/$from_id.json"), true);
    $cuser["userfild"]["$from_id"]["file"] = "ne_wait_ai_question";
    file_put_contents("data/$from_id.json", json_encode($cuser, JSON_UNESCAPED_UNICODE));
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'HTML',
        'text' => "🤖 <b>الدعم الفني الذكي</b>\n\nأرسل الآن سؤالك عن البوت (الشحن، الطلبات، الأسعار...) وسأحاول مساعدتك تلقائياً.\nإن لم أستطع فهم طلبك، سيتم تحويلك إلى الدعم المباشر.",
        'reply_markup' => json_encode(['inline_keyboard' => [ne_back_row('ne_support')]]),
    ]);
    die;
}

if ($text !== '' && !isset($update->callback_query)) {
    $cuser_ai = json_decode(file_get_contents("data/$from_id.json"), true);
    if (($cuser_ai["userfild"]["$from_id"]["file"] ?? '') === 'ne_wait_ai_question') {
        $cuser_ai["userfild"]["$from_id"]["file"] = "none";
        file_put_contents("data/$from_id.json", json_encode($cuser_ai, JSON_UNESCAPED_UNICODE));
        $q = mb_strtolower(trim($text));
        $faq = [
            'شحن'  => "لشحن رصيدك: من القائمة الرئيسية اضغط 💰 إشحن رصيدك، أو استخدم كوبون عبر 💳 شحن كرت.",
            'طلب'  => "لمتابعة طلباتك: من ⚙️ المزيد والإعدادات اضغط 🙋‍♂️ طلباتي، أو 🔍 كشف طلب لمتابعة طلب معيّن برقمه.",
            'سعر'  => "أسعار الخدمات تظهر داخل كل قسم عند اختيار الخدمة مباشرة قبل تأكيد الطلب.",
            'رصيد' => "رصيدك الحالي يظهر أعلى القائمة الرئيسية، ويمكنك تحويله لصديقك عبر 🔄 تحويل رصيد.",
        ];
        $answer = null;
        foreach ($faq as $k => $v) {
            if (mb_strpos($q, $k) !== false) { $answer = $v; break; }
        }
        if ($answer) {
            bot('sendmessage', ['chat_id' => $chat_id, 'text' => "🤖 " . $answer]);
        } else {
            bot('sendmessage', [
                'chat_id' => $chat_id,
                'parse_mode' => 'HTML',
                'text' => "🤖 لم أتمكن من فهم سؤالك بدقة، تم تحويلك للدعم المباشر: " . BOT_SUPPORT,
            ]);
            bot('sendmessage', [
                'chat_id' => $admin,
                'parse_mode' => 'HTML',
                'text' => "🤖 <b>سؤال لم يفهمه الدعم الذكي</b>\n🆔 من: <code>{$from_id}</code>\n💬 السؤال: " . htmlspecialchars($text),
            ]);
        }
        die;
    }
    if (($cuser_ai["userfild"]["$from_id"]["file"] ?? '') === 'ne_wait_compensation') {
        $cuser_ai["userfild"]["$from_id"]["file"] = "none";
        file_put_contents("data/$from_id.json", json_encode($cuser_ai, JSON_UNESCAPED_UNICODE));
        bot('sendmessage', ['chat_id' => $chat_id, 'text' => "✅ تم استلام طلب التعويض الخاص بك، سيتم مراجعته من الإدارة قريباً."]);
        bot('sendmessage', [
            'chat_id' => $admin,
            'parse_mode' => 'HTML',
            'text' => "📋 <b>طلب تعويض جديد</b>\n🆔 من: <code>{$from_id}</code>\n📝 التفاصيل: " . htmlspecialchars($text),
        ]);
        die;
    }
}

// ------------------------------------------------------------
// 📋 طلب تعويض (فردي) / 🔑 مفتاح API
// ------------------------------------------------------------
if ($data == "ne_compensation") {
    $cuser = json_decode(file_get_contents("data/$from_id.json"), true);
    $cuser["userfild"]["$from_id"]["file"] = "ne_wait_compensation";
    file_put_contents("data/$from_id.json", json_encode($cuser, JSON_UNESCAPED_UNICODE));
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "📋 يرجى إرسال رقم الطلب وسبب التعويض في رسالة واحدة، وسيتم تحويلها لفريق الدعم فوراً.",
        'reply_markup' => json_encode(['inline_keyboard' => [ne_back_row('panel')]]),
    ]);
    die;
}

if ($data == "ne_api_key") {
    $cuser = json_decode(file_get_contents("data/$from_id.json"), true);
    [$key, $cuser, $changed] = ne_get_or_create_api_key($from_id, is_array($cuser) ? $cuser : []);
    if ($changed) {
        file_put_contents("data/$from_id.json", json_encode($cuser, JSON_UNESCAPED_UNICODE));
    }
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'HTML',
        'text' => "🔑 <b>مفتاح API الخاص بك:</b>\n<code>{$key}</code>\n\n⚠️ لا تشارك هذا المفتاح مع أي شخص.",
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '♻️ توليد مفتاح جديد', 'callback_data' => 'ne_api_key_regen']],
            ne_back_row('panel'),
        ]]),
    ]);
    die;
}

if ($data == "ne_api_key_regen") {
    $cuser = json_decode(file_get_contents("data/$from_id.json"), true);
    $cuser["userfild"]["$from_id"]["api_key"] = 'RQ-' . strtoupper(bin2hex(random_bytes(16)));
    file_put_contents("data/$from_id.json", json_encode($cuser, JSON_UNESCAPED_UNICODE));
    bot('answercallbackquery', ['callback_query_id' => $up->id ?? '', 'text' => '✅ تم توليد مفتاح جديد', 'show_alert' => true]);
    die;
}

// ------------------------------------------------------------
// أزرار قيد التفعيل (لا يوجد لها مزوّد بيانات حالياً)
// ------------------------------------------------------------
$ne_placeholders = [
    'ne_fake_numbers'  => '📇 أرقام وهمية',
    'ne_game_topup'    => '🎁 شحن الألعاب',
    'ne_favorites'     => '⭐ خدماتي المفضلة',
    'ne_free_services' => '📚 خدمات مجانية',
];
if (isset($ne_placeholders[$data])) {
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $ne_placeholders[$data] . "\n\n🚧 هذا القسم قيد التفعيل حالياً، تابعنا قريباً!",
        'reply_markup' => json_encode(['inline_keyboard' => [ne_back_row('panel')]]),
    ]);
    die;
}

// ============================================================
// 👑 لوحة الإمبراطور — لوحة التحكم المركزية الجديدة للأدمن
// ============================================================
if ($data == "emperor_panel" && $ne_is_admin) {
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'HTML',
        'text' => "⚙️ <b>أهلاً بك في لوحة الإمبراطور للتحكم المركزي 🤖</b>\n--------------------------------------------\n• يمكنك التحكم في متجرك وإعدادات الخدمات والأسعار من هنا.",
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '🛍️ إدارة الأقسام والخدمات', 'callback_data' => 'xdmat'], ['text' => '💰 إدارة النقاط والأرصدة', 'callback_data' => 'amruu']],
            [['text' => '📊 الإحصائيات المتقدمة', 'callback_data' => 'emperor_stats'], ['text' => '🎫 إدارة الكوبونات والكروت', 'callback_data' => 'emperor_coupons']],
            [['text' => '📢 قسم الإذاعة والتوجيه', 'callback_data' => 'sendmgddyessage'], ['text' => '📝 تعديل أسطر ونصوص البوت', 'callback_data' => 'emperor_texts']],
            [['text' => '⚙️ إعدادات الربط والـ API', 'callback_data' => 'VISCODEV'], ['text' => '🛡️ الحماية وقفل الأقسام', 'callback_data' => 'emperor_security']],
            [['text' => '❌ إغلاق اللوحة وبدء البوت', 'callback_data' => 'panel']],
        ]]),
    ]);
    die;
}

// ---- 📊 الإحصائيات المتقدمة ----
if ($data == "emperor_stats" && $ne_is_admin) {
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'HTML',
        'text' => ne_admin_stats_text(),
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '🚫 حظر عضو', 'callback_data' => 'ban'], ['text' => '✅ إلغاء حظر عضو', 'callback_data' => 'unban']],
            [['text' => '🧹 تصفير قائمة المحظورين بالكامل', 'callback_data' => 'unbanall']],
            ne_back_row('emperor_panel'),
        ]]),
    ]);
    die;
}

// ---- 🎫 إدارة الكوبونات والكروت ----
if ($data == "emperor_coupons" && $ne_is_admin) {
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🎫 <b>إدارة الكوبونات والكروت</b>\n\nاختر العملية المطلوبة:",
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '➕ إنشاء كوبون شحن رصيد', 'callback_data' => 'emperor_cp_new_charge']],
            [['text' => '➕ إنشاء كوبون خصم %', 'callback_data' => 'emperor_cp_new_discount']],
            [['text' => '🔎 بحث عن كوبون', 'callback_data' => 'emperor_cp_search']],
            [['text' => '📃 عرض كل الكوبونات', 'callback_data' => 'emperor_cp_list']],
            ne_back_row('emperor_panel'),
        ]]),
    ]);
    die;
}

if (($data == "emperor_cp_new_charge" || $data == "emperor_cp_new_discount") && $ne_is_admin) {
    $type = $data == "emperor_cp_new_charge" ? 'charge' : 'discount';
    $cuser = json_decode(file_get_contents("data/$from_id.json"), true);
    $cuser["userfild"]["$from_id"]["file"] = "emperor_wait_cp_" . $type;
    file_put_contents("data/$from_id.json", json_encode($cuser, JSON_UNESCAPED_UNICODE));
    $hint = $type == 'charge'
        ? "أرسل بيانات الكوبون بالصيغة التالية في رسالة واحدة:\nCODE القيمة الحد_الأقصى_للاستخدام\nمثال: WELCOME10 10 0\n(الحد الأقصى = 0 يعني بلا حد)"
        : "أرسل بيانات كوبون الخصم بالصيغة التالية:\nCODE النسبة الحد_الأقصى_للاستخدام\nمثال: SALE20 20 100";
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $hint,
        'reply_markup' => json_encode(['inline_keyboard' => [ne_back_row('emperor_coupons')]]),
    ]);
    die;
}

if ($data == "emperor_cp_search" && $ne_is_admin) {
    $cuser = json_decode(file_get_contents("data/$from_id.json"), true);
    $cuser["userfild"]["$from_id"]["file"] = "emperor_wait_cp_search";
    file_put_contents("data/$from_id.json", json_encode($cuser, JSON_UNESCAPED_UNICODE));
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🔎 أرسل كود الكوبون الذي تريد البحث عنه:",
        'reply_markup' => json_encode(['inline_keyboard' => [ne_back_row('emperor_coupons')]]),
    ]);
    die;
}

if ($data == "emperor_cp_list" && $ne_is_admin) {
    $coupons = coupons_read();
    if (!$coupons) {
        $txt = "📃 لا توجد كوبونات مضافة حتى الآن.";
    } else {
        $txt = "📃 <b>قائمة الكوبونات (" . count($coupons) . ")</b>\n━━━━━━━━━━━━━━━━━\n";
        $i = 0;
        foreach ($coupons as $c) {
            $i++;
            if ($i > 25) { $txt .= "... والمزيد"; break; }
            $st = !empty($c['active']) ? '✅' : '❌';
            $txt .= "{$st} <code>{$c['code']}</code> | {$c['type']} | {$c['value']} | استُخدم " . count($c['used_by'] ?? []) . "/" . ($c['max_uses'] ?: '∞') . "\n";
        }
    }
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'parse_mode' => 'HTML',
        'text' => $txt,
        'reply_markup' => json_encode(['inline_keyboard' => [ne_back_row('emperor_coupons')]]),
    ]);
    die;
}

if ($text !== '' && !isset($update->callback_query) && $ne_is_admin) {
    $cuser_e = json_decode(file_get_contents("data/$from_id.json"), true);
    $step = $cuser_e["userfild"]["$from_id"]["file"] ?? '';

    if ($step === 'emperor_wait_cp_charge' || $step === 'emperor_wait_cp_discount') {
        $cuser_e["userfild"]["$from_id"]["file"] = "none";
        file_put_contents("data/$from_id.json", json_encode($cuser_e, JSON_UNESCAPED_UNICODE));
        $parts = preg_split('/\s+/', trim($text));
        $type = $step === 'emperor_wait_cp_charge' ? 'charge' : 'discount';
        if (count($parts) >= 2 && is_numeric($parts[1])) {
            $code    = $parts[0];
            $value   = (float)$parts[1];
            $maxUses = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : 0;
            $created = coupon_create($code, $type, $value, $maxUses);
            if ($created) {
                bot('sendmessage', [
                    'chat_id' => $chat_id,
                    'parse_mode' => 'HTML',
                    'text' => "✅ تم إنشاء الكوبون بنجاح:\n<code>{$created['code']}</code> | {$created['type']} | {$created['value']}",
                ]);
            } else {
                bot('sendmessage', ['chat_id' => $chat_id, 'text' => "❌ فشل إنشاء الكوبون، تأكد من صحة الصيغة والقيمة."]);
            }
        } else {
            bot('sendmessage', ['chat_id' => $chat_id, 'text' => "❌ صيغة غير صحيحة. مثال: CODE 10 0"]);
        }
        die;
    }

    if ($step === 'emperor_wait_cp_search') {
        $cuser_e["userfild"]["$from_id"]["file"] = "none";
        file_put_contents("data/$from_id.json", json_encode($cuser_e, JSON_UNESCAPED_UNICODE));
        $c = coupon_get(trim($text));
        if ($c) {
            $txt = "🎫 <code>{$c['code']}</code>\nالنوع: {$c['type']}\nالقيمة: {$c['value']}\nالحالة: " . (!empty($c['active']) ? 'مفعّل ✅' : 'معطّل ❌') . "\nمرات الاستخدام: " . count($c['used_by'] ?? []) . " / " . ($c['max_uses'] ?: '∞');
            bot('sendmessage', [
                'chat_id' => $chat_id, 'parse_mode' => 'HTML', 'text' => $txt,
                'reply_markup' => json_encode(['inline_keyboard' => [[['text' => '🔴 تعطيل الكوبون', 'callback_data' => 'emperor_cp_disable_' . $c['code']]]]]),
            ]);
        } else {
            bot('sendmessage', ['chat_id' => $chat_id, 'text' => "❌ لم يتم العثور على هذا الكود."]);
        }
        die;
    }
}

if (strpos((string)$data, 'emperor_cp_disable_') === 0 && $ne_is_admin) {
    $code = substr($data, strlen('emperor_cp_disable_'));
    $ok = coupon_disable($code);
    bot('answercallbackquery', [
        'callback_query_id' => $up->id ?? '',
        'text' => $ok ? "✅ تم تعطيل الكوبون {$code}" : "❌ لم يتم العثور على الكوبون",
        'show_alert' => true,
    ]);
    die;
}

// ---- 📝 تعديل أسطر ونصوص البوت (مركز روابط للأقسام الموجودة فعلياً) ----
if ($data == "emperor_texts" && $ne_is_admin) {
    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "📝 <b>تعديل أسطر ونصوص البوت</b>\n\nاختر النص الذي تريد تعديله:",
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => '📝 كليشة الترحيب /start', 'callback_data' => 'start']],
            [['text' => '📜 رسالة الشروط والأحكام', 'callback_data' => 'amr1']],
            [['text' => '💰 رسالة أسعار النقاط والوكلاء', 'callback_data' => 'msg_asar']],
            [['text' => '🔔 رسالة إشعار إكمال الطلب', 'callback_data' => 'msgaspat']],
            ne_back_row('emperor_panel'),
        ]]),
    ]);
    die;
}

// ---- 🛡️ الحماية وقفل الأقسام (لوحة موحّدة لأعلام موجودة فعلياً في البوت) ----
if ($data == "emperor_security" && $ne_is_admin) {
    $baageel   = file_exists("baageel.txt") ? file_get_contents("baageel.txt") : "✅";
    $nzambot   = file_exists("edid/nzambot.txt") ? file_get_contents("edid/nzambot.txt") : "❌";
    $ras       = file_exists("edid/opan.txt") ? file_get_contents("edid/opan.txt") : "✅";
    $tmwel     = file_exists("edid/tmwel.txt") ? file_get_contents("edid/tmwel.txt") : "✅";
    $coadd     = file_exists("edid/coadd.txt") ? file_get_contents("edid/coadd.txt") : "✅";
    $addDay    = file_exists("edid/add_day.txt") ? file_get_contents("edid/add_day.txt") : "✅";
    $asttacbot = file_exists("edid/asttacbot.txt") ? file_get_contents("edid/asttacbot.txt") : "❌";

    bot('editmessagetext', [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => "🛡️ <b>الحماية وقفل الأقسام</b>\n\nاضغط أي زر لتبديل حالته فوراً:",
        'parse_mode' => 'HTML',
        'reply_markup' => json_encode(['inline_keyboard' => [
            [['text' => "الرشق والخدمات: {$ras}", 'callback_data' => $ras == '✅' ? 'off_ras' : 'opan_ras'],
             ['text' => "التمويل الحقيقي: {$tmwel}", 'callback_data' => $tmwel == '✅' ? 'off_tmwel' : 'opan_tmwel']],
            [['text' => "تحويل الرصيد: {$coadd}", 'callback_data' => $coadd == '✅' ? 'off_coadd' : 'opan_coadd'],
             ['text' => "الهدية اليومية: {$addDay}", 'callback_data' => $addDay == '✅' ? 'off_add_day' : 'opan_add_day']],
            [['text' => "الاشتراك الإجباري: {$nzambot}", 'callback_data' => 'nzambot'],
             ['text' => "إشعارات دخول الأدمن: {$asttacbot}", 'callback_data' => $asttacbot == '✅' ? 'off_asttacbot' : 'opan_asttacbot']],
            ne_back_row('emperor_panel'),
        ]]),
    ]);
    die;
}

// لا نحذف error_log تلقائياً

?>
