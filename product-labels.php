<?php
declare(strict_types=1);

const DECLARED_ALLERGENS = ['Milk', 'Eggs', 'Fish', 'Shellfish', 'Peanuts', 'Tree nuts', 'Wheat', 'Soy', 'Sesame'];
const LABEL_PHOTO_FIELDS = [
    'ingredients_photo' => 'Ingredients label',
    'allergen_photo' => 'Allergen statement',
    'nutrition_photo' => 'Nutrition label',
];

function labelText(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function declaredAllergens(array $submission): array {
    $items = json_decode((string) ($submission['allergens_json'] ?? '[]'), true);
    return is_array($items) ? array_values(array_intersect(DECLARED_ALLERGENS, $items)) : [];
}

function allergenSummary(array $submission): string {
    return match ($submission['allergen_status'] ?? 'unavailable') {
        'declared' => implode(', ', declaredAllergens($submission)) ?: 'Information unavailable',
        'none_declared' => 'None declared (owner claim; review the label)',
        default => 'Information unavailable',
    };
}

function claimSummary(mixed $value): string {
    return match ($value) {
        'yes' => 'Yes (owner claim)',
        'no' => 'No',
        default => 'Information unavailable',
    };
}

function labelPhotoLinks(array $submission): string {
    $html = '<ul class="label-photos">';
    foreach (LABEL_PHOTO_FIELDS as $field => $title) {
        $html .= '<li>' . labelText($title) . ': ';
        if (!empty($submission[$field])) {
            $html .= '<a target="_blank" rel="noopener" href="label-photo.php?submission_id=' . (int) $submission['id'] . '&amp;type=' . labelText($field) . '">View photo</a>';
        } else {
            $html .= 'No photo supplied';
        }
        $html .= '</li>';
    }
    return $html . '</ul>';
}

function parseLabelDetails(array $post): array {
    $status = (string) ($post['allergen_status'] ?? '');
    $claimVegetarian = (string) ($post['vegetarian_claim'] ?? '');
    $claimVegan = (string) ($post['vegan_claim'] ?? '');
    $selected = $post['allergens'] ?? [];
    if (!in_array($status, ['declared', 'none_declared', 'unavailable'], true)
        || !in_array($claimVegetarian, ['yes', 'no', 'unknown'], true)
        || !in_array($claimVegan, ['yes', 'no', 'unknown'], true)
        || ($claimVegan === 'yes' && $claimVegetarian === 'no')
        || !is_array($selected) || count($selected) > count(DECLARED_ALLERGENS)) {
        throw new InvalidArgumentException('Choose valid allergen and dietary declarations.');
    }
    $selected = array_values(array_unique($selected));
    if (count(array_diff($selected, DECLARED_ALLERGENS))
        || ($status === 'declared' && !$selected)
        || ($status !== 'declared' && $selected)) {
        throw new InvalidArgumentException('Select allergens only when using Declared allergens, and choose at least one.');
    }
    return [$status, json_encode($selected, JSON_THROW_ON_ERROR), $claimVegetarian, $claimVegan];
}

function validateLabelUploads(array $files): array {
    $accepted = [];
    $mimeToExtension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    foreach (LABEL_PHOTO_FIELDS as $field => $title) {
        $file = $files[$field] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        if (!is_array($file) || ($file['error'] ?? -1) !== UPLOAD_ERR_OK
            || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))
            || ($file['size'] ?? 0) < 1 || $file['size'] > 5 * 1024 * 1024) {
            throw new InvalidArgumentException($title . ': upload a photo under 5 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $dimensions = getimagesize($file['tmp_name']);
        if (!isset($mimeToExtension[$mime]) || !$dimensions || $dimensions[0] < 500 || $dimensions[1] < 500
            || $dimensions[0] > 8000 || $dimensions[1] > 8000 || $dimensions['mime'] !== $mime) {
            throw new InvalidArgumentException($title . ': use a clear JPEG, PNG, or WebP image at least 500 × 500 pixels.');
        }
        $accepted[$field] = [$file['tmp_name'], $mimeToExtension[$mime]];
    }
    return $accepted;
}

function saveLabelUploads(array $accepted, array $previous = []): array {
    $directory = __DIR__ . '/uploads/product-labels';
    if (!is_dir($directory) || !is_file($directory . '/.htaccess')) {
        throw new RuntimeException('Copy the protected uploads/product-labels directory before saving.');
    }
    $paths = [];
    $created = [];
    try {
        foreach (LABEL_PHOTO_FIELDS as $field => $title) {
            $paths[$field] = $previous[$field] ?? null;
            if (!isset($accepted[$field])) continue;
            [$temporary, $extension] = $accepted[$field];
            $filename = bin2hex(random_bytes(20)) . '.' . $extension;
            $path = $directory . '/' . $filename;
            if (!move_uploaded_file($temporary, $path)) throw new RuntimeException('Could not save ' . $title);
            chmod($path, 0600);
            $created[] = $path;
            $paths[$field] = $filename;
        }
    } catch (Throwable $error) {
        foreach ($created as $path) @unlink($path);
        throw $error;
    }
    return [$paths, $created];
}

function labelForm(array $current = []): void {
    $status = $current['allergen_status'] ?? 'unavailable';
    $chosen = declaredAllergens($current);
    $vegetarian = $current['vegetarian_claim'] ?? 'unknown';
    $vegan = $current['vegan_claim'] ?? 'unknown';
    echo '<fieldset><legend>Declared allergens</legend><p>These are Product Owner declarations. “None declared” is different from missing information.</p>';
    foreach (['unavailable' => 'Information unavailable', 'none_declared' => 'None declared', 'declared' => 'Declared allergens (select below)'] as $value => $label) {
        echo '<label class="choice"><input type="radio" name="allergen_status" value="' . $value . '" ' . ($value === $status ? 'checked' : '') . '> ' . labelText($label) . '</label>';
    }
    echo '<div class="choices" id="declared-allergens">';
    foreach (DECLARED_ALLERGENS as $allergen) {
        echo '<label class="choice"><input type="checkbox" name="allergens[]" value="' . labelText($allergen) . '" ' . (in_array($allergen, $chosen, true) ? 'checked' : '') . '> ' . labelText($allergen) . '</label>';
    }
    echo '</div><script>document.querySelectorAll(\'input[name="allergen_status"]\').forEach(r => r.addEventListener(\'change\', () => { if (r.checked && r.value !== \'declared\') document.querySelectorAll(\'input[name="allergens[]"]\').forEach(c => c.checked = false); }));</script></fieldset>';
    foreach (['vegetarian_claim' => 'Vegetarian claim', 'vegan_claim' => 'Vegan claim'] as $field => $title) {
        $currentValue = $field === 'vegan_claim' ? $vegan : $vegetarian;
        echo '<p><label>' . $title . '<select name="' . $field . '" required>';
        foreach (['unknown' => 'Information unavailable', 'yes' => 'Yes (owner claim)', 'no' => 'No'] as $value => $label) {
            echo '<option value="' . $value . '" ' . ($currentValue === $value ? 'selected' : '') . '>' . $label . '</option>';
        }
        echo '</select></label></p>';
    }
    echo '<fieldset><legend>Label photos (optional, for administrator review)</legend><p>Use readable JPEG, PNG or WebP photos, at least 500 × 500 pixels, maximum 5 MB each. Photos are evidence, not numeric nutrition data.</p>';
    foreach (LABEL_PHOTO_FIELDS as $field => $title) {
        echo '<p><label>' . labelText($title) . '<input type="file" name="' . $field . '" accept="image/jpeg,image/png,image/webp"></label>';
        if (!empty($current[$field]) && !empty($current['id'])) {
            echo '<small>Current photo retained if no replacement is chosen: <a target="_blank" rel="noopener" href="label-photo.php?submission_id=' . (int) $current['id'] . '&amp;type=' . $field . '">View photo</a></small>';
        }
        echo '</p>';
    }
    echo '</fieldset>';
}
