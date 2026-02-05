<?php
session_start();

function env_load($path)
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");
        $_ENV[$key] = $value;
    }
}

env_load(__DIR__ . '/../.env');

$adminUser = $_ENV['ADMIN_USER'] ?? '';
$adminPass = $_ENV['ADMIN_PASS'] ?? '';

function is_logged_in()
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_login()
{
    if (!is_logged_in()) {
        http_response_code(401);
        exit;
    }
}

function safe_read_json($path)
{
    if (!file_exists($path)) {
        return ['items' => []];
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        return ['items' => []];
    }
    if (!isset($data['items']) || !is_array($data['items'])) {
        $data['items'] = [];
    }
    return $data;
}

function safe_write_json($path, $data)
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return file_put_contents($path, $json) !== false;
}

function normalize_gallery_categories($items)
{
    $out = [];
    foreach ($items as $item) {
        if (is_string($item)) {
            $value = trim($item);
            if ($value === '') {
                continue;
            }
            $out[] = ['value' => $value, 'label' => $value];
            continue;
        }
        if (is_array($item)) {
            $value = trim($item['value'] ?? '');
            $label = trim($item['label'] ?? '');
            if ($value === '') {
                continue;
            }
            if ($label === '') {
                $label = $value;
            }
            $out[] = ['value' => $value, 'label' => $label];
        }
    }
    return $out;
}

function upload_file($inputName, $allowedExt, $targetDir)
{
    if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $uploadsDir = $targetDir;
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }
    $tmpName = $_FILES[$inputName]['tmp_name'];
    $original = basename($_FILES[$inputName]['name']);
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return '';
    }
    $name = uniqid('img_', true) . '.' . $ext;
    $dest = $uploadsDir . '/' . $name;
    if (!move_uploaded_file($tmpName, $dest)) {
        return '';
    }
    return $name;
}

$error = '';
$success = '';

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($user === $adminUser && $adminPass !== '' && $pass === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: /admin/');
        exit;
    }
    $error = 'Неверный логин или пароль';
}

if (isset($_POST['action']) && $_POST['action'] === 'logout') {
    session_destroy();
    header('Location: /admin/');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'save') {
    require_login();

    $faqItems = [];
    if (!empty($_POST['faq_question'])) {
        foreach ($_POST['faq_question'] as $i => $q) {
            $q = trim($q);
            $a = trim($_POST['faq_answer'][$i] ?? '');
            if ($q === '' && $a === '') {
                continue;
            }
            $faqItems[] = ['question' => $q, 'answer' => $a];
        }
    }

    $galleryItems = [];
    if (!empty($_POST['gallery_category'])) {
        foreach ($_POST['gallery_category'] as $i => $cat) {
            $img = trim($_POST['gallery_image'][$i] ?? '');
            $alt = trim($_POST['gallery_alt'][$i] ?? '');
            $uploadKey = 'gallery_upload_' . $i;
            $uploaded = upload_file($uploadKey, ['jpg', 'jpeg', 'png', 'webp'], __DIR__ . '/../image/uploads');
            if ($uploaded) {
                $img = '/image/uploads/' . $uploaded;
            }
            if ($img === '') {
                continue;
            }
            $galleryItems[] = [
                'category' => $cat ?: 'all',
                'image' => $img,
                'alt' => $alt
            ];
        }
    }

    $docItems = [];
    if (!empty($_POST['doc_title'])) {
        foreach ($_POST['doc_title'] as $i => $title) {
            $title = trim($title);
            $url = trim($_POST['doc_url'][$i] ?? '');
            $uploadKey = 'doc_upload_' . $i;
            $uploaded = upload_file($uploadKey, ['pdf'], __DIR__ . '/../files/uploads');
            if ($uploaded) {
                $url = '/files/uploads/' . $uploaded;
            }
            if ($title === '' && $url === '') {
                continue;
            }
            $docItems[] = ['title' => $title, 'url' => $url];
        }
    }

    $galleryCategories = [];
    if (!empty($_POST['gallery_cat_value'])) {
        foreach ($_POST['gallery_cat_value'] as $i => $value) {
            $value = trim($value);
            $label = trim($_POST['gallery_cat_label'][$i] ?? '');
            if ($value === '') {
                continue;
            }
            if ($label === '') {
                $label = $value;
            }
            $galleryCategories[$value] = ['value' => $value, 'label' => $label];
        }
    }
    $galleryCategories = array_values($galleryCategories);

    $faqSaved = safe_write_json(__DIR__ . '/../content/faq.json', ['items' => $faqItems]);
    $gallerySaved = safe_write_json(__DIR__ . '/../content/gallery.json', ['items' => $galleryItems]);
    $docsSaved = safe_write_json(__DIR__ . '/../content/documents.json', ['items' => $docItems]);
    $catsSaved = safe_write_json(__DIR__ . '/../content/gallery_categories.json', ['items' => $galleryCategories]);

    if ($faqSaved && $gallerySaved && $docsSaved && $catsSaved) {
        $success = 'Изменения сохранены';
    } else {
        $error = 'Не удалось сохранить изменения';
    }
}

if (!is_logged_in()) {
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка — вход</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f8fc; margin: 0; display: grid; place-items: center; height: 100vh; }
        .card { background: #fff; padding: 32px; border-radius: 16px; width: 320px; box-shadow: 0 10px 30px rgba(8,20,35,0.12); }
        h1 { font-size: 20px; margin: 0 0 16px; }
        input { width: 100%; padding: 12px 14px; margin-bottom: 12px; border-radius: 8px; border: 1px solid #d7e2ec; font-size: 14px; }
        button { width: 100%; padding: 12px 14px; border: none; border-radius: 8px; background: #0f4c81; color: #fff; font-weight: 700; }
        .error { color: #b00020; margin-bottom: 12px; }
    </style>
</head>
<body>
    <form class="card" method="post">
        <h1>Вход в админку</h1>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <input type="hidden" name="action" value="login">
        <input type="text" name="username" placeholder="Логин" required>
        <input type="password" name="password" placeholder="Пароль" required>
        <button type="submit">Войти</button>
    </form>
</body>
</html>
<?php
    exit;
}

$faqData = safe_read_json(__DIR__ . '/../content/faq.json');
$galleryData = safe_read_json(__DIR__ . '/../content/gallery.json');
$galleryCatData = safe_read_json(__DIR__ . '/../content/gallery_categories.json');
$docsData = safe_read_json(__DIR__ . '/../content/documents.json');
$defaultGalleryCats = [
    ['value' => 'fleet', 'label' => 'Автопарк'],
    ['value' => 'pit', 'label' => 'Карьер'],
    ['value' => 'port', 'label' => 'Склад в порту'],
    ['value' => 'barges', 'label' => 'Баржи'],
    ['value' => 'rail', 'label' => 'ЖД'],
    ['value' => 'road', 'label' => 'Автобан'],
    ['value' => 'build', 'label' => 'Стройки']
];
$galleryCategories = normalize_gallery_categories($galleryCatData['items']);
if (empty($galleryCategories)) {
    $galleryCategories = $defaultGalleryCats;
}
$known = [];
foreach ($galleryCategories as $cat) {
    $known[$cat['value']] = true;
}
foreach ($galleryData['items'] as $item) {
    $value = trim($item['category'] ?? '');
    if ($value !== '' && !isset($known[$value])) {
        $galleryCategories[] = ['value' => $value, 'label' => $value];
        $known[$value] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админка — Карьер‑ТЛТ+</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f8fc; margin: 0; }
        header { background: #fff; padding: 16px 24px; box-shadow: 0 4px 20px rgba(8,20,35,0.08); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; }
        h1 { font-size: 18px; margin: 0; }
        main { max-width: 1100px; margin: 24px auto; padding: 0 16px 40px; }
        .section { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 10px 30px rgba(8,20,35,0.08); margin-bottom: 20px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px; }
        .row-3 { display: grid; grid-template-columns: 1fr 1fr auto; gap: 12px; margin-bottom: 12px; align-items: end; }
        label { font-size: 13px; color: #607182; display: block; margin-bottom: 6px; }
        input, textarea, select { width: 100%; padding: 10px 12px; border-radius: 8px; border: 1px solid #d7e2ec; font-size: 14px; }
        textarea { min-height: 70px; resize: vertical; }
        .actions { display: flex; gap: 10px; margin-top: 12px; }
        .btn { padding: 10px 14px; border-radius: 8px; border: none; font-weight: 700; cursor: pointer; }
        .btn-primary { background: #0f4c81; color: #fff; }
        .btn-muted { background: #e6eef7; }
        .notice { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; }
        .success { background: #e7f5ee; color: #0f6b3f; }
        .error { background: #fde8e8; color: #b00020; }
        .item { border: 1px dashed #d7e2ec; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
        .item h3 { margin: 0 0 8px; font-size: 14px; color: #213243; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        @media (max-width: 800px) {
            .row, .row-3, .grid-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header>
        <h1>Админка — Карьер‑ТЛТ+</h1>
        <form method="post">
            <input type="hidden" name="action" value="logout">
            <button class="btn btn-muted" type="submit">Выйти</button>
        </form>
    </header>
    <main>
        <?php if ($success): ?><div class="notice success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save">

            <div class="section">
                <h2>Вопросы и ответы</h2>
                <div id="faq-list">
                    <?php foreach ($faqData['items'] as $i => $item): ?>
                        <div class="item">
                            <h3>Вопрос #<?php echo $i + 1; ?></h3>
                            <label>Вопрос</label>
                            <input name="faq_question[]" value="<?php echo htmlspecialchars($item['question']); ?>">
                            <label>Ответ</label>
                            <textarea name="faq_answer[]"><?php echo htmlspecialchars($item['answer']); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-muted" onclick="addFaq()">Добавить вопрос</button>
            </div>

            <div class="section">
                <h2>Категории галереи</h2>
                <div id="gallery-categories">
                    <?php foreach ($galleryCategories as $i => $cat): ?>
                        <div class="item">
                            <div class="row-3">
                                <div>
                                    <label>Код категории</label>
                                    <input name="gallery_cat_value[]" value="<?php echo htmlspecialchars($cat['value']); ?>">
                                </div>
                                <div>
                                    <label>Название</label>
                                    <input name="gallery_cat_label[]" value="<?php echo htmlspecialchars($cat['label']); ?>">
                                </div>
                                <div>
                                    <button type="button" class="btn btn-muted" onclick="removeItem(this)">Удалить</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-muted" onclick="addGalleryCategory()">Добавить категорию</button>
            </div>

            <div class="section">
                <h2>Фотогалерея</h2>
                <div id="gallery-list">
                    <?php foreach ($galleryData['items'] as $i => $item): ?>
                        <div class="item">
                            <h3>Фото #<?php echo $i + 1; ?></h3>
                            <div class="grid-3">
                                <div>
                                    <label>Категория</label>
                                    <select name="gallery_category[]">
                                        <?php
                                        foreach ($galleryCategories as $cat):
                                        ?>
                                            <option value="<?php echo htmlspecialchars($cat['value']); ?>" <?php echo ($item['category'] ?? '') === $cat['value'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label>URL изображения</label>
                                    <input name="gallery_image[]" value="<?php echo htmlspecialchars($item['image']); ?>">
                                </div>
                                <div>
                                    <label>Alt-текст</label>
                                    <input name="gallery_alt[]" value="<?php echo htmlspecialchars($item['alt']); ?>">
                                </div>
                            </div>
                            <label>Загрузить файл (опционально)</label>
                            <input type="file" name="gallery_upload_<?php echo $i; ?>" accept="image/*">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-muted" onclick="addGallery()">Добавить фото</button>
            </div>

            <div class="section">
                <h2>Документы</h2>
                <div id="docs-list">
                    <?php foreach ($docsData['items'] as $i => $item): ?>
                        <div class="item">
                            <h3>Документ #<?php echo $i + 1; ?></h3>
                            <div class="row">
                                <div>
                                    <label>Название</label>
                                    <input name="doc_title[]" value="<?php echo htmlspecialchars($item['title']); ?>">
                                </div>
                                <div>
                                    <label>Ссылка (PDF)</label>
                                    <input name="doc_url[]" value="<?php echo htmlspecialchars($item['url']); ?>">
                                </div>
                            </div>
                            <label>Загрузить PDF (опционально)</label>
                            <input type="file" name="doc_upload_<?php echo $i; ?>" accept="application/pdf">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn btn-muted" onclick="addDoc()">Добавить документ</button>
            </div>

            <div class="actions">
                <button class="btn btn-primary" type="submit">Сохранить изменения</button>
            </div>
        </form>
    </main>
    <script>
        const faqList = document.getElementById('faq-list');
        const galleryList = document.getElementById('gallery-list');
        const galleryCategoriesList = document.getElementById('gallery-categories');
        const docsList = document.getElementById('docs-list');
        function removeItem(button) {
            const item = button.closest('.item');
            if (item) item.remove();
        }

        function addFaq() {
            const item = document.createElement('div');
            item.className = 'item';
            item.innerHTML = `
                <h3>Новый вопрос</h3>
                <label>Вопрос</label>
                <input name="faq_question[]" value="">
                <label>Ответ</label>
                <textarea name="faq_answer[]"></textarea>
            `;
            faqList.appendChild(item);
        }

        function categoryOptionsHtml() {
            const values = Array.from(document.querySelectorAll('input[name="gallery_cat_value[]"]'));
            const labels = Array.from(document.querySelectorAll('input[name="gallery_cat_label[]"]'));
            return values.map((input, index) => {
                const value = input.value.trim();
                const label = (labels[index]?.value || value).trim();
                if (!value) return '';
                return `<option value="${value}">${label}</option>`;
            }).join('');
        }

        function addGalleryCategory() {
            const item = document.createElement('div');
            item.className = 'item';
            item.innerHTML = `
                <div class="row-3">
                    <div>
                        <label>Код категории</label>
                        <input name="gallery_cat_value[]" value="">
                    </div>
                    <div>
                        <label>Название</label>
                        <input name="gallery_cat_label[]" value="">
                    </div>
                    <div>
                        <button type="button" class="btn btn-muted" onclick="removeItem(this)">Удалить</button>
                    </div>
                </div>
            `;
            galleryCategoriesList.appendChild(item);
        }

        function addGallery() {
            const index = galleryList.children.length;
            const item = document.createElement('div');
            item.className = 'item';
            item.innerHTML = `
                <h3>Новое фото</h3>
                <div class="grid-3">
                    <div>
                        <label>Категория</label>
                        <select name="gallery_category[]">
                            ${categoryOptionsHtml()}
                        </select>
                    </div>
                    <div>
                        <label>URL изображения</label>
                        <input name="gallery_image[]" value="">
                    </div>
                    <div>
                        <label>Alt-текст</label>
                        <input name="gallery_alt[]" value="">
                    </div>
                </div>
                <label>Загрузить файл (опционально)</label>
                <input type="file" name="gallery_upload_${index}" accept="image/*">
            `;
            galleryList.appendChild(item);
        }

        function addDoc() {
            const index = docsList.children.length;
            const item = document.createElement('div');
            item.className = 'item';
            item.innerHTML = `
                <h3>Новый документ</h3>
                <div class="row">
                    <div>
                        <label>Название</label>
                        <input name="doc_title[]" value="">
                    </div>
                    <div>
                        <label>Ссылка (PDF)</label>
                        <input name="doc_url[]" value="">
                    </div>
                </div>
                <label>Загрузить PDF (опционально)</label>
                <input type="file" name="doc_upload_${index}" accept="application/pdf">
            `;
            docsList.appendChild(item);
        }
    </script>
</body>
</html>
