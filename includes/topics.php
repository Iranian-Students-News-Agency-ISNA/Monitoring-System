<?php
// ===================== ماژول «ارزیابی موضوعی» (مستقل از فایل‌های اصلی سامانه) =====================
// داده‌ها در دو فایل جدا نگهداری می‌شوند: topics.json (لیست موضوعات) و
// topic_evaluations.json (موضوع انتخاب‌شده برای هر خبر، بر اساس تاریخ+کد خبر).
// هیچ فایل موجود سامانه توسط این ماژول تغییر نمی‌کند.

// لیست پایه موضوعات (برگرفته از فایل تعاریف موضوعات)؛ فقط در اولین اجرا در topics.json ذخیره می‌شود.
function topicsSeedDefaults(): array
{
    return [
    ['name' => '*اربعین', 'definition' => 'تمامی اخبار مرتبط با اربعین در تمامی حوزه‌ها'],
    ['name' => '*جام جهانی', 'definition' => 'همه اخبار مربوط به جام جهانی آمریکا'],
    ['name' => '*سفر ترامپ به چین', 'definition' => 'اخبار سفر ترامپ به چین'],
    ['name' => '*اکبر عبدی', 'definition' => 'همه اخبار مربوط به درگذشت اکبر عبدی'],
    ['name' => '*مراسم تشییع رهبر', 'definition' => 'این کد روی اخبار مربوط به مراسم تشییع رهبری زده شد'],
    ['name' => 'اصناف و مشاغل', 'definition' => 'اخبار مربوط به رخدادها یا افراد شاغل در صنف های مختلف'],
    ['name' => 'افشاگری، شایعات، حاشیه‌ها', 'definition' => 'این دسته از اخبار به موضوعی پنهانی یا ادعاها درباره یک موضوع و پشت پرده آنها، شایعات و حواشی'],
    ['name' => 'آموزشی و دانشگاهی', 'definition' => 'همه اخبار مربوط به مدارس و دانشگاه ها مثلا برگزاری آزمون‌ها در مقاطع مختلف'],
    ['name' => 'انتصاب', 'definition' => 'اخبار انتصاب، پیامهای تبریک مربوط به انتصاب'],
    ['name' => 'انتقادات سیاسی', 'definition' => '*اخباری که در آن یا اشخاص سیاسی انتقادی مطرح کردند یا درباره موضوعی سیاسی انتقاد داشتند.\n*فقط درمورد سیاست داخلی'],
    ['name' => 'انتقادات ورزشی', 'definition' => 'اخبار مربوط به انتقادها و گله های درون جامعه ورزش ایران'],
    ['name' => 'اینترنت', 'definition' => 'اخباری درباره ابعاد مختلف اینترنت از جمله مشکلات قطع اینترنت بین‌الملل، زمان احتمالی اتصال، امکانات آنلاین و مجازی شدن‌ها و اینترنت پرو'],
    ['name' => 'بانک', 'definition' => 'اخبار بانک ها'],
    ['name' => 'تازه‌های پزشکی', 'definition' => 'اخبار پیشرفت‌های پزشکی'],
    ['name' => 'تجارت', 'definition' => 'اخبار صادرات، واردات، تجارت داخلی و خارجی، گمرک، خودرو، بورس'],
    ['name' => 'تفاهم ایران و آمریکا', 'definition' => 'اخبار مربوط به مذاکرات پایان جنگ یا تفاهم ایران و آمریکا'],
    ['name' => 'توسعه', 'definition' => 'اخبار مربوط به تجهیز، احداث، توسعه، ساخت و ساز و تمامی فعالیت‌ها برای توسعه یک منطقه'],
    ['name' => 'تیتر مبهم', 'definition' => 'اخباری که از روی تیتر به هیچ عنوان نمی توان چیزی از محتوای آن فهمید'],
    ['name' => 'جالب', 'definition' => 'موضوع این اخبار گوناگون است اما احتمالا مخاطبان با خواندن تیتر کنجکاو به دانستن آن می‌شوند\n*خبر در سایر دسته‌ها نمی‌گنجد'],
    ['name' => 'جنگ ایران، آمریکا و اسرائیل', 'definition' => 'همه اخبار مربوط به حملات دشمن به ایران و واکنشها به آن، تجمعات مردمی، اظهارنظرهای مختلف درباره جنگ'],
    ['name' => 'حقوق و دستمزد', 'definition' => 'اخبار مربوط حقوق و دستمزد کارمندان، کارگران، بازنشستگان'],
    ['name' => 'حوادث', 'definition' => 'اخبار مربوط به حوادث طبیعی مانند طوفان و زلزله و حوادث بین فردی مانند منازعه و تصادفات'],
    ['name' => 'راه و ترابری', 'definition' => 'اخبار مربوط به احداث و پیشبرد و یا مشکلاتی که در مسیرهای رفت و آمد درون شهری و برون شهری پیش می‌آید. کلیه وسایل حمل و نقل مانند اتوبوس، قطار و هواپیما و مسیرهای دریایی نیز در این دسته هستند.'],
    ['name' => 'رسانه', 'definition' => 'اخبار مربوط به تلویزیون، ماهواره، رادیو، کتاب، مطبوعات و رسانه های چاپی'],
    ['name' => 'رویدادها و مناسبت‌ها', 'definition' => 'مناسبت‌های تقویمی یا مناسبت‌های برآمده از رخدادهای روز'],
    ['name' => 'رهبری', 'definition' => 'اخبار مربوط به رهبر'],
    ['name' => 'سایر موضوعات اجتماعی', 'definition' => 'اخبار گوناگون مربوط به ازدواج، جمعیت، وام‌ها، هزینه‌ها'],
    ['name' => 'سایر موضوعات اقتصادی', 'definition' => 'هر خبر اقتصادی که در سایر دسته‌ها نمی گنجد'],
    ['name' => 'سایر موضوعات ورزشی', 'definition' => 'هر خبر  ورزشی که در سایر دسته‌های ورزشی نمی‌گنجد'],
    ['name' => 'سلامت و بیماری', 'definition' => 'اخبار مربوط به توصیف و هشدار نسبت به بیماری‌ها، علایم، درمان، داروها'],
    ['name' => 'فناوری جدید', 'definition' => 'پیشرفت‌ها، کشفیات، ابتکارات و موضوعات جالب دنیای علم و فناوری'],
    ['name' => 'گردشگری', 'definition' => 'اخبار مرتبط با جاذبه های طبیعی یا غیرطبیعی گردشگری ایران'],
    ['name' => 'کشتی', 'definition' => 'اخبار مربوط به مسابقات داخلی یا خارجی کشتی'],
    ['name' => 'لیگ داخلی فوتبال', 'definition' => 'اخبار مربوط به تیم‌های داخلی کشور'],
    ['name' => 'مجلس', 'definition' => 'اخبار حوزه مجلس\n*اگر خبری در مجلس است که می‌تواند در دسته‌های دیگر بگنجد باید کد آن دسته را بگیرد'],
    ['name' => 'رییس‌جمهور', 'definition' => 'اخبار و اظهارات مربوط به رییس‌جمهور'],
    ['name' => 'محیط زیست', 'definition' => 'همه اخبار مرتبط با محیط زیست'],
    ['name' => 'مسابقات بین‌المللی', 'definition' => 'همه اخبار مسابقات جهانی کشور ایران یا سایر کشورها به جز  اخبار "جام جهانی فوتبال"'],
    ['name' => 'مسائل بین‌المللی', 'definition' => 'رخدادی که برای جهان حائز اهمیت باشد. مراودات بین کشورها بدون ارتباط مستقیم به ایران'],
    ['name' => 'مسائل منطقه‌ای', 'definition' => 'رخدادی که برای منطقه خاورمیانه و همسایگان ایران حائز اهمیت است\n*هر ارتباطی که بین سایر کشورها و ایران باشد'],
    ['name' => 'مسکن', 'definition' => 'اخبار مربوط به وضعیت و طرح های مسکن، قیمت‌ها'],
    ['name' => 'مشاهیر', 'definition' => 'اخبار مربوط به افراد مشهور در هنر، ورزش و سیاست که ارزش خبر مربوط به شهرت آنهاست و نه لزوما اقدامی که انجام شده است'],
    ['name' => 'انتظامی و قضایی', 'definition' => 'اخبار مرتبط با قوه قضائیه ایران + اظهارات مقام‌های انتظامی و قضایی + هشدارهای موادمخدر + دستگیری‌ها\n*پرونده‌های خارجی در این دسته نمی‌گنجد'],
    ['name' => 'نوسانات قیمت طلا و ارز', 'definition' => 'نوسات قیمت ارز، سکه، طلا، دلار و ...'],
    ['name' => 'والیبال', 'definition' => 'اخبار مربوط به مسابقات داخلی یا خارجی والیبال'],
    ['name' => 'انتخابات', 'definition' => 'همه انتخابات های رسمی و قانونی کشور'],
    ['name' => 'خانواده و ازدواج', 'definition' => 'تمامی اخبار و تحلیل هایی که به نحوی مربوط به جنبه اجتماعی خانواده باشد'],
    ['name' => 'کشاورزی و دامپروری', 'definition' => 'تمامی اخبار مرتبط با کشاورزی، باغات، دامپروری، ماکیان، آبزیان'],
    ['name' => 'درگذشت', 'definition' => 'اخبار مربوط به درگذشت چهره های سرشناس در این کد قرار می‌گیرد.\nبعضی از این اخبار ممکن است به دلیل شهرت بسیار زیاد، کد جداگانه یا کد مشاهیر بگیرند'],
    ['name' => 'آب و هوا', 'definition' => 'وضعیت آب و هوایی، اخبار هواشناسی'],
    ['name' => 'بازی‌های آسیایی ناگویا', 'definition' => 'اخبار مربوط به مسابقات آسیایی ناگویا که از ۲۸ شهریور تا ۱۲ مهر در ژاپن برگزار می‌شود'],
    ['name' => 'وحدت ملی', 'definition' => 'اخبار یا اظهارنظرهایی که می‌تواند به کاهش اختلافات سیاسی و شکل‌گیری وحدت میان آحاد جامعه یا احزاب و گروه‌ها کمک کند. (بیشتر وحدت سیاسی مدنظر است)'],
    ['name' => 'سایر موضوعات سیاسی', 'definition' => 'هر خبر سیاسی که در سایر دسته‌های سیاسی نمی‌گنجد'],
    ['name' => 'سایر موضوعات فرهنگی و هنری', 'definition' => 'هر خبر فرهنگی و هنری که در سایر دسته‌های هنری نمی‌گنجد'],
    ['name' => 'انرژی', 'definition' => 'همه اخبار مربوط به انرژی‌های آب، سوخت، برق، گاز و ...'],
    ['name' => 'کالابرگ و یارانه', 'definition' => 'همه اخبار مرتبط به مبلغ، زمان بندی و تغییرات کالابرگ و یارانه'],
    ];
}

// تغییر نام موضوعات قدیمی به نام جدید (فایل «آخرین تعاریف»)؛ مقدار null یعنی حذف
function topicsLegacyRenames(): array
{
    return [
        'موضوعات قضایی' => 'انتظامی و قضایی',
        'افشاگری' => 'افشاگری، شایعات، حاشیه‌ها',
        '*مراسم تشییع' => '*مراسم تشییع رهبر',
        'مسائل بین المللی' => 'مسائل بین‌المللی',
        'مسائل منطقه ای' => 'مسائل منطقه‌ای',
        'سایر' => null,
    ];
}

// اطمینان از وجود topics.json با محتوای اولیه؛ و یک‌بار همگام‌سازی لیست قدیمی با فایل «آخرین تعاریف».
// موضوعاتی که کاربران خودشان ساخته‌اند حفظ می‌شوند.
function topicsEnsureSeeded(): void
{
    $rows = jsonRead('topics');
    if (empty($rows)) {
        jsonUpdate('topics', function ($rows) {
            if (!empty($rows)) return $rows;
            $out = [];
            $id = 1;
            foreach (topicsSeedDefaults() as $t) {
                $out[] = ['id' => $id++, 'name' => $t['name'], 'definition' => $t['definition']];
            }
            return $out;
        });
        jsonUpdate('topics_meta', function ($m) { $m['sync_v'] = 2; return $m; });
        return;
    }
    $meta = jsonRead('topics_meta');
    if ((int)($meta['sync_v'] ?? 0) >= 2) return;

    $renames = topicsLegacyRenames();
    jsonUpdate('topics', function ($rows) use ($renames) {
        $out = [];
        $have = [];
        foreach ($rows as $r) {
            $n = trim((string)($r['name'] ?? ''));
            if (array_key_exists($n, $renames)) {
                if ($renames[$n] === null) continue;
                $r['name'] = $n = $renames[$n];
            }
            if (isset($have[$n])) continue;
            $have[$n] = true;
            $out[] = $r;
        }
        $maxId = 0;
        foreach ($out as $r) $maxId = max($maxId, (int)($r['id'] ?? 0));
        foreach (topicsSeedDefaults() as $t) {
            if (!isset($have[$t['name']])) {
                $out[] = ['id' => ++$maxId, 'name' => $t['name'], 'definition' => $t['definition']];
            }
        }
        return $out;
    });
    jsonUpdate('topic_evaluations', function ($rows) use ($renames) {
        foreach ($rows as &$r) {
            $new = [];
            foreach (($r['topics'] ?? []) as $t) {
                if (array_key_exists($t, $renames)) {
                    if ($renames[$t] === null) continue;
                    $t = $renames[$t];
                }
                $new[] = $t;
            }
            $r['topics'] = array_values(array_unique($new));
        }
        return $rows;
    });
    jsonUpdate('topics_meta', function ($m) { $m['sync_v'] = 2; return $m; });
}

// همه موضوعات (id, name, definition)
function topicsAll(): array
{
    topicsEnsureSeeded();
    $rows = jsonRead('topics');
    usort($rows, fn($a, $b) => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? '')));
    return $rows;
}

// افزودن موضوع جدید (در صورت تکراری‌نبودن نام)؛ نام موضوع را برمی‌گرداند
function topicAdd(string $name, string $definition = ''): string
{
    $name = trim($name);
    if ($name === '') return '';
    jsonUpdate('topics', function ($rows) use ($name, $definition) {
        foreach ($rows as $r) {
            if (trim((string)($r['name'] ?? '')) === $name) return $rows;
        }
        $maxId = 0;
        foreach ($rows as $r) $maxId = max($maxId, (int)($r['id'] ?? 0));
        $rows[] = ['id' => $maxId + 1, 'name' => $name, 'definition' => $definition];
        return $rows;
    });
    return $name;
}

// حذف موضوع از لیست انتخاب (ارزیابی‌های قبلاً ثبت‌شده دست‌نخورده می‌مانند)
function topicDelete(string $name): bool
{
    $name = trim($name);
    $ok = false;
    jsonUpdate('topics', function ($rows) use ($name, &$ok) {
        $out = [];
        foreach ($rows as $r) {
            if (trim((string)($r['name'] ?? '')) === $name) { $ok = true; continue; }
            $out[] = $r;
        }
        return $out;
    });
    return $ok;
}

// تعداد اخبار ارزیابی‌شده برای هر موضوع
function topicUsageCounts(): array
{
    $c = [];
    foreach (jsonRead('topic_evaluations') as $r) {
        foreach (($r['topics'] ?? []) as $t) $c[$t] = ($c[$t] ?? 0) + 1;
    }
    return $c;
}

// کلید یکتای هر خبر برای ذخیره ارزیابی موضوعی
function topicEvalKey(string $date, string $code): string
{
    return normalizeJalaliDate($date) . '|' . normalizeDigits(trim($code));
}

// موضوعات ثبت‌شده برای یک خبر (آرایه‌ای از نام موضوعات)
function topicEvalGet(string $date, string $code): array
{
    $key = topicEvalKey($date, $code);
    $rows = jsonRead('topic_evaluations');
    foreach ($rows as $r) {
        if (($r['key'] ?? '') === $key) return $r['topics'] ?? [];
    }
    return [];
}

// اطلاعات آخرین ویرایش (نام کاربری و زمان) برای یک خبر
function topicEvalMeta(string $date, string $code): ?array
{
    $key = topicEvalKey($date, $code);
    $rows = jsonRead('topic_evaluations');
    foreach ($rows as $r) {
        if (($r['key'] ?? '') === $key) {
            return ['updated_by' => $r['updated_by'] ?? '', 'updated_at' => $r['updated_at'] ?? ''];
        }
    }
    return null;
}

// ===================== یادداشت‌های آزاد کاربران روی هر خبر =====================
// هر یادداشت: {id, username, display_name, text, created_at, updated_at}

function topicNotesGet(string $date, string $code): array
{
    $key = topicEvalKey($date, $code);
    $rows = jsonRead('topic_evaluations');
    foreach ($rows as $r) {
        if (($r['key'] ?? '') === $key) return $r['notes'] ?? [];
    }
    return [];
}

function topicNoteAdd(string $date, string $code, string $username, string $displayName, string $text): void
{
    $text = trim($text);
    if ($text === '') return;
    $key = topicEvalKey($date, $code);
    jsonUpdate('topic_evaluations', function ($rows) use ($key, $username, $displayName, $text) {
        $now = date('Y-m-d H:i:s');
        foreach ($rows as &$r) {
            if (($r['key'] ?? '') === $key) {
                $notes = $r['notes'] ?? [];
                $maxId = 0;
                foreach ($notes as $n) $maxId = max($maxId, (int)($n['id'] ?? 0));
                $notes[] = ['id' => $maxId + 1, 'username' => $username, 'display_name' => $displayName,
                            'text' => $text, 'created_at' => $now, 'updated_at' => $now];
                $r['notes'] = $notes;
                return $rows;
            }
        }
        $rows[] = ['key' => $key, 'topics' => [], 'notes' => [
            ['id' => 1, 'username' => $username, 'display_name' => $displayName,
             'text' => $text, 'created_at' => $now, 'updated_at' => $now]
        ]];
        return $rows;
    });
}

// فقط نویسنده‌ی یادداشت اجازه‌ی ویرایش دارد
function topicNoteEdit(string $date, string $code, int $noteId, string $username, string $text): bool
{
    $text = trim($text);
    if ($text === '') return false;
    $key = topicEvalKey($date, $code);
    $ok = false;
    jsonUpdate('topic_evaluations', function ($rows) use ($key, $noteId, $username, $text, &$ok) {
        $now = date('Y-m-d H:i:s');
        foreach ($rows as &$r) {
            if (($r['key'] ?? '') !== $key) continue;
            foreach (($r['notes'] ?? []) as &$n) {
                if ((int)($n['id'] ?? 0) === $noteId && strcasecmp((string)($n['username'] ?? ''), $username) === 0) {
                    $n['text'] = $text;
                    $n['updated_at'] = $now;
                    $ok = true;
                }
            }
        }
        return $rows;
    });
    return $ok;
}

// فقط نویسنده‌ی یادداشت اجازه‌ی حذف دارد
function topicNoteDelete(string $date, string $code, int $noteId, string $username): bool
{
    $key = topicEvalKey($date, $code);
    $ok = false;
    jsonUpdate('topic_evaluations', function ($rows) use ($key, $noteId, $username, &$ok) {
        foreach ($rows as &$r) {
            if (($r['key'] ?? '') !== $key) continue;
            $before = count($r['notes'] ?? []);
            $r['notes'] = array_values(array_filter($r['notes'] ?? [], function ($n) use ($noteId, $username, &$ok) {
                if ((int)($n['id'] ?? 0) === $noteId && strcasecmp((string)($n['username'] ?? ''), $username) === 0) {
                    $ok = true;
                    return false;
                }
                return true;
            }));
        }
        return $rows;
    });
    return $ok;
}

// ثبت/به‌روزرسانی موضوعات یک خبر
function topicEvalSet(string $date, string $code, array $topics, string $username = ''): void
{
    $key = topicEvalKey($date, $code);
    $topics = array_values(array_unique(array_filter(array_map('trim', $topics), fn($t) => $t !== '')));
    jsonUpdate('topic_evaluations', function ($rows) use ($key, $topics, $username) {
        $now = date('Y-m-d H:i:s');
        foreach ($rows as &$r) {
            if (($r['key'] ?? '') === $key) {
                $r['topics'] = $topics;
                $r['updated_at'] = $now;
                $r['updated_by'] = $username;
                return $rows;
            }
        }
        $rows[] = ['key' => $key, 'topics' => $topics, 'updated_at' => $now, 'updated_by' => $username];
        return $rows;
    });
}

// ===================== محدوده‌های بازدید =====================
// هر بازه [کلید, برچسب, حداقل (شامل), حداکثر (شامل، null یعنی بی‌نهایت)]
function viewsBuckets(): array
{
    return [
        ['key' => 'r1000',   'label' => 'هزار',                 'min' => 1000,   'max' => 1999],
        ['key' => 'r2000',   'label' => 'دو هزار',               'min' => 2000,   'max' => 2999],
        ['key' => 'r3000',   'label' => 'سه هزار',               'min' => 3000,   'max' => 3999],
        ['key' => 'r4000',   'label' => 'چهار هزار',             'min' => 4000,   'max' => 4999],
        ['key' => 'r5_10k',  'label' => 'پنج تا ده هزار',        'min' => 5000,   'max' => 9999],
        ['key' => 'r10_50k', 'label' => 'ده تا پنجاه هزار',      'min' => 10000,  'max' => 49999],
        ['key' => 'r50_100k','label' => 'پنجاه تا صد هزار',      'min' => 50000,  'max' => 99999],
        ['key' => 'r100k_up','label' => 'صد هزار به بالا',       'min' => 100000, 'max' => null],
    ];
}

// بازه‌ی متناظر با یک عدد بازدید را برمی‌گرداند (یا null اگر زیر ۱۰۰۰ باشد)
function viewsBucketFor(int $views): ?array
{
    foreach (viewsBuckets() as $b) {
        if ($views >= $b['min'] && ($b['max'] === null || $views <= $b['max'])) return $b;
    }
    return null;
}

// ردیف‌های اکسل در بازه‌ی زمانی/سرویس/محدوده‌های بازدید انتخابی، به‌همراه موضوع(های) ثبت‌شده
// (استفاده مشترک توسط صفحه‌ی نمایش و خروجی اکسل)
function topicEvaluationRows(string $from, string $to, string $service, array $ranges): array
{
    $rangeSet = array_flip($ranges);
    $out = [];
    foreach (excelRowsInRange($from, $to, $service) as $r) {
        if (trim((string)($r['site'] ?? '')) !== 'ایسنا') continue; // فقط اخبار سایت ایسنا
        $views = (int)($r['views'] ?? 0);
        $bucket = viewsBucketFor($views);
        if ($bucket === null) continue; // زیر ۱۰۰۰ بازدید: در نظر گرفته نمی‌شود
        if (!empty($ranges) && !isset($rangeSet[$bucket['key']])) continue;
        $r['__views'] = $views;
        $r['__bucket'] = $bucket['label'];
        $r['__topics'] = topicEvalGet((string)$r['date'], (string)$r['code']);
        $r['__meta'] = topicEvalMeta((string)$r['date'], (string)$r['code']);
        $out[] = $r;
    }
    usort($out, fn($a, $b) => $b['__views'] <=> $a['__views']);
    return $out;
}

// سرویس‌های موجود در بازه‌ی تاریخی، فقط از میان اخبار سایت ایسنا (برای پرکردن فیلتر سرویس در صفحه ارزیابی موضوعی)
function topicEvaluationDistinctServices(string $from, string $to): array
{
    $set = [];
    foreach (excelRowsInRange($from, $to) as $r) {
        if (trim((string)($r['site'] ?? '')) !== 'ایسنا') continue;
        $s = trim((string)($r['service_main'] ?? ''));
        if ($s !== '') $set[$s] = true;
    }
    $list = array_keys($set);
    sort($list, SORT_FLAG_CASE | SORT_STRING);
    return $list;
}

// ===================== پیشنهاد خودکار موضوع بر اساس تیتر =====================
// هر موضوعی که نامش (بدون ستاره‌ی ابتدایی در صورت وجود) به‌صورت زیررشته در تیتر باشد، پیشنهاد می‌شود.
function topicsSuggestForTitle(string $title, array $topics): array
{
    $title = normalizePersianChars($title);
    $out = [];
    foreach ($topics as $t) {
        $name = trim((string)($t['name'] ?? ''));
        $needle = ltrim($name, '*');
        if ($needle === '') continue;
        if (mb_stripos($title, normalizePersianChars($needle)) !== false) {
            $out[] = $name;
        }
    }
    return $out;
}

// ===================== پیشنهاد موضوع بر اساس سرویس / زیرسرویس =====================
// عیناً مطابق فایل «سرویس > زیرسرویس | موضوعات پیشنهادی». هر ردیف: [سرویس, زیرسرویس‌ها (با « / » جدا)، موضوعات]
function topicsSubServiceRows(): array
{
    return [
        ['علمی‌ودانشگاهی', 'علم', ['فناوری جدید', 'آموزشی و دانشگاهی']],
        ['علمی‌ودانشگاهی', 'پژوهش', ['فناوری جدید', 'آموزشی و دانشگاهی']],
        ['علمی‌ودانشگاهی', 'فناوری', ['فناوری جدید', 'تازه‌های پزشکی']],
        ['علمی‌ودانشگاهی', 'جهاد دانشگاهی', ['آموزشی و دانشگاهی', 'فناوری جدید']],
        ['علمی‌ودانشگاهی', 'هوش مصنوعی', ['فناوری جدید']],
        ['علمی‌ودانشگاهی', 'آموزش', ['آموزشی و دانشگاهی']],
        ['علمی‌ودانشگاهی', 'صنفی،فرهنگی‌ودانشجویی', ['آموزشی و دانشگاهی']],
        ['علمی‌ودانشگاهی', 'دانش‌بنیان‌ها', ['فناوری جدید']],

        ['سیاسی', 'اندیشه امام و رهبری', ['رهبری', 'سایر موضوعات سیاسی']],
        ['سیاسی', 'سیاست داخلی', ['وحدت ملی', 'سایر موضوعات سیاسی', 'انتقادات سیاسی']],
        ['سیاسی', 'دولت', ['رییس‌جمهور', 'انتصاب', 'سایر موضوعات سیاسی', 'تفاهم ایران و آمریکا']],
        ['سیاسی', 'مجلس', ['مجلس', 'سایر موضوعات سیاسی', 'انتقادات سیاسی', 'جنگ ایران، آمریکا و اسرائیل', 'تفاهم ایران و آمریکا']],
        ['سیاسی', 'دفاعی-امنیتی', ['سایر موضوعات سیاسی', 'انتقادات سیاسی', 'جنگ ایران، آمریکا و اسرائیل', 'تفاهم ایران و آمریکا']],
        ['سیاسی', 'حقوقی و قضایی', ['انتظامی و قضایی']],

        ['فرهنگی‌وهنری', 'ادبیات و کتاب', ['مشاهیر', 'رسانه', 'رویدادها و مناسبت‌ها', 'سایر موضوعات فرهنگی و هنری']],
        ['فرهنگی‌وهنری', 'سینما و تئاتر', ['مشاهیر', 'رسانه', 'رویدادها و مناسبت‌ها', 'سایر موضوعات فرهنگی و هنری']],
        ['فرهنگی‌وهنری', 'تجسمی و موسیقی', ['مشاهیر', 'رسانه', 'رویدادها و مناسبت‌ها', 'سایر موضوعات فرهنگی و هنری']],
        ['فرهنگی‌وهنری', 'گردشگری و میراث', ['گردشگری', 'سایر موضوعات فرهنگی و هنری']],
        ['فرهنگی‌وهنری', 'رسانه', ['رسانه', 'سایر موضوعات فرهنگی و هنری']],
        ['فرهنگی‌وهنری', 'فرهنگ حماسه', ['رویدادها و مناسبت‌ها']],
        ['فرهنگی‌وهنری', 'دین و اندیشه / فرهنگ عمومی', ['سایر موضوعات فرهنگی و هنری', 'جالب']],

        ['اقتصادی', 'اقتصاد کلان', ['بانک', 'کالابرگ و یارانه', 'توسعه', 'سایر موضوعات اقتصادی', 'نوسانات قیمت طلا و ارز', 'اصناف و مشاغل', 'مسکن']],
        ['اقتصادی', 'تولید و تجارت', ['سایر موضوعات اقتصادی', 'نوسانات قیمت طلا و ارز', 'تجارت', 'اصناف و مشاغل', 'کالابرگ و یارانه', 'کشاورزی و دامپروری']],
        ['اقتصادی', 'انرژی', ['انرژی', 'سایر موضوعات اقتصادی']],
        ['اقتصادی', 'عمران و اشتغال', ['توسعه', 'حقوق و دستمزد', 'اصناف و مشاغل', 'راه و ترابری', 'مسکن']],
        ['اقتصادی', 'ارتباطات و فناوری اطلاعات', ['اینترنت', 'فناوری جدید', 'توسعه']],

        ['اجتماعی', 'جامعه، شهری', ['سایر موضوعات اجتماعی', 'راه و ترابری']],
        ['اجتماعی', 'سلامت', ['سلامت و بیماری', 'تازه‌های پزشکی']],
        ['اجتماعی', 'خانواده', ['خانواده و ازدواج', 'سایر موضوعات اجتماعی']],
        ['اجتماعی', 'آموزش و پرورش', ['آموزشی و دانشگاهی', 'اصناف و مشاغل', 'حقوق و دستمزد']],
        ['اجتماعی', 'محیط‌زیست', ['محیط زیست', 'آب و هوا', 'انرژی']],
        ['اجتماعی', 'حوادث، انتظامی', ['حوادث', 'انتظامی و قضایی']],
        ['اجتماعی', 'سبک زندگی', ['سلامت و بیماری', 'سایر موضوعات اجتماعی', 'خانواده و ازدواج']],

        ['جهان', 'ایران در جهان / آسیا و اقیانوسیه', ['مسائل بین‌المللی', 'مسائل منطقه‌ای', 'جنگ ایران، آمریکا و اسرائیل', 'تفاهم ایران و آمریکا']],
        ['جهان', 'سیاست خارجی / آمریکا و اروپا', ['مسائل بین‌المللی', 'جنگ ایران، آمریکا و اسرائیل', 'تفاهم ایران و آمریکا']],
        ['جهان', 'غرب آسیا و آفریقا / محور مقاومت', ['مسائل منطقه‌ای', 'جنگ ایران، آمریکا و اسرائیل', 'تفاهم ایران و آمریکا']],
        ['جهان', 'انرژی هسته‌ای', ['مسائل منطقه‌ای', 'جنگ ایران، آمریکا و اسرائیل', 'تفاهم ایران و آمریکا']],

        ['ورزشی', 'فوتبال، فوتسال', ['لیگ داخلی فوتبال', 'مسابقات بین‌المللی']],
        ['ورزشی', 'کشتی، رزمی', ['کشتی', 'مسابقات بین‌المللی']],
        ['ورزشی', 'جهان ورزش', ['مشاهیر', 'مسابقات بین‌المللی']],
        ['ورزشی', 'توپ و تور', ['والیبال']],
        ['ورزشی', 'ورزش بانوان / علم ورزش / سایر ورزش‌ها', ['سایر موضوعات ورزشی', 'مسابقات بین‌المللی']],
        ['ورزشی', 'ویدیوی ورزشی / نتایج زنده', ['لیگ داخلی فوتبال', 'سایر موضوعات ورزشی']],
    ];
}

// کلید مقایسه: بدون فاصله، نیم‌فاصله، ویرگول و خط‌تیره
function topicsMatchKey(string $s): string
{
    $s = normalizePersianChars($s);
    return preg_replace('/[\s\x{200C}\x{200F}\x{200E}،,\-–—]+/u', '', $s);
}

// پیشنهاد موضوع بر اساس سرویس و زیرسرویسِ خبر (فقط طبق فایل؛ برای زیرسرویس‌های خارج از فایل، پیشنهادی نیست)
function topicsSuggestForServiceSub(string $service, string $sub): array
{
    $svc = topicsMatchKey($service);
    $sb = topicsMatchKey($sub);
    if ($svc === '' || $sb === '') return [];
    foreach (topicsSubServiceRows() as [$rowSvc, $rowSubs, $names]) {
        if (topicsMatchKey($rowSvc) !== $svc) continue;
        foreach (explode('/', $rowSubs) as $one) {
            if (topicsMatchKey($one) === $sb) return $names;
        }
    }
    return [];
}
