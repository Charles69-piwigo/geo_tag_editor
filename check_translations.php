<?php
/**
 * Script de vérification des traductions
 * Usage: php check_translations.php
 * Placez ce fichier à la racine du plugin geo_tag_editor/
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== VÉRIFICATION DES TRADUCTIONS - geo_tag_editor ===\n\n";

$fr_file = __DIR__ . '/language/fr_FR/plugin.lang.php';
$en_file = __DIR__ . '/language/en_UK/plugin.lang.php';

// Vérifier que les fichiers existent
if (!file_exists($fr_file)) {
    die("❌ Fichier FR introuvable: $fr_file\n");
}
if (!file_exists($en_file)) {
    die("❌ Fichier EN introuvable: $en_file\n");
}

// Charger les fichiers
$lang = array();
include($fr_file);
$lang_fr = $lang;

$lang = array();
include($en_file);
$lang_en = $lang;

echo "📁 Fichiers chargés:\n";
echo "  FR: $fr_file (" . count($lang_fr) . " clés)\n";
echo "  EN: $en_file (" . count($lang_en) . " clés)\n\n";

// 1. Clés en FR mais pas en EN
$missing_en = array_diff_key($lang_fr, $lang_en);
if (!empty($missing_en)) {
    echo "❌ Clés manquantes dans EN (" . count($missing_en) . "):\n";
    foreach ($missing_en as $key => $value) {
        echo "  - '$key' = '$value'\n";
    }
    echo "\n";
} else {
    echo "✅ Toutes les clés FR sont présentes en EN\n\n";
}

// 2. Clés en EN mais pas en FR
$missing_fr = array_diff_key($lang_en, $lang_fr);
if (!empty($missing_fr)) {
    echo "❌ Clés manquantes dans FR (" . count($missing_fr) . "):\n";
    foreach ($missing_fr as $key => $value) {
        echo "  - '$key' = '$value'\n";
    }
    echo "\n";
} else {
    echo "✅ Toutes les clés EN sont présentes en FR\n\n";
}

// 3. Traductions identiques (probablement oubli de traduction)
$same = array();
foreach ($lang_fr as $key => $value) {
    if (isset($lang_en[$key]) && $lang_fr[$key] === $lang_en[$key]) {
        $same[$key] = $value;
    }
}

if (!empty($same)) {
    echo "⚠️  Traductions identiques FR/EN (" . count($same) . ") - possibles oublis:\n";
    foreach ($same as $key => $value) {
        echo "  - '$key' = '$value'\n";
    }
    echo "\n";
} else {
    echo "✅ Aucune traduction identique FR/EN\n\n";
}

// 4. Rechercher les appels à l10n() et _() dans le code
echo "🔍 Recherche des clés utilisées dans le code...\n";

$code_keys = array();
$files_to_check = array();

// Fichiers PHP
foreach (glob(__DIR__ . '/*.php') as $file) {
    $files_to_check[] = $file;
}
foreach (glob(__DIR__ . '/include/*.php') as $file) {
    $files_to_check[] = $file;
}
foreach (glob(__DIR__ . '/lib/*.php') as $file) {
    $files_to_check[] = $file;
}

// Fichiers JS
foreach (glob(__DIR__ . '/template/*.js') as $file) {
    $files_to_check[] = $file;
}
foreach (glob(__DIR__ . '/js/*.js') as $file) {
    $files_to_check[] = $file;
}

// Patterns de recherche
$patterns = array(
    "/l10n\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/",  // l10n('key')
    "/_\s*\(\s*['\"]([^'\"]+)['\"]\s*\)/"       // _('key')
);

foreach ($files_to_check as $file) {
    $content = file_get_contents($file);
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $key) {
                $code_keys[$key] = true;
            }
        }
    }
}

echo "  Trouvé " . count($code_keys) . " clés utilisées dans le code\n\n";

// 5. Clés utilisées dans le code mais non définies
$undefined = array();
foreach (array_keys($code_keys) as $key) {
    if (!isset($lang_fr[$key]) && !isset($lang_en[$key])) {
        $undefined[$key] = true;
    }
}

if (!empty($undefined)) {
    echo "❌ Clés utilisées dans le code mais NON DÉFINIES (" . count($undefined) . "):\n";
    foreach (array_keys($undefined) as $key) {
        echo "  - '$key'\n";
    }
    echo "\n";
} else {
    echo "✅ Toutes les clés utilisées sont définies\n\n";
}

// 6. Clés définies mais jamais utilisées
$unused_fr = array();
foreach (array_keys($lang_fr) as $key) {
    if (!isset($code_keys[$key])) {
        $unused_fr[$key] = $lang_fr[$key];
    }
}

if (!empty($unused_fr)) {
    echo "⚠️  Clés définies en FR mais jamais utilisées (" . count($unused_fr) . "):\n";
    foreach ($unused_fr as $key => $value) {
        echo "  - '$key' = '$value'\n";
    }
    echo "\n";
} else {
    echo "✅ Toutes les clés FR sont utilisées\n\n";
}

// === RÉSUMÉ ===
echo "═══════════════════════════════════════════════════════════════\n";
echo "                         RÉSUMÉ\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "Clés FR:              " . str_pad(count($lang_fr), 5, ' ', STR_PAD_LEFT) . "\n";
echo "Clés EN:              " . str_pad(count($lang_en), 5, ' ', STR_PAD_LEFT) . "\n";
echo "Clés dans le code:    " . str_pad(count($code_keys), 5, ' ', STR_PAD_LEFT) . "\n";
echo "-----------------------------------------------------------\n";
echo "Manquantes EN:        " . str_pad(count($missing_en), 5, ' ', STR_PAD_LEFT) . " " . (empty($missing_en) ? "✅" : "❌") . "\n";
echo "Manquantes FR:        " . str_pad(count($missing_fr), 5, ' ', STR_PAD_LEFT) . " " . (empty($missing_fr) ? "✅" : "❌") . "\n";
echo "Identiques FR/EN:     " . str_pad(count($same), 5, ' ', STR_PAD_LEFT) . " " . (empty($same) ? "✅" : "⚠️ ") . "\n";
echo "Non définies:         " . str_pad(count($undefined), 5, ' ', STR_PAD_LEFT) . " " . (empty($undefined) ? "✅" : "❌") . "\n";
echo "Non utilisées:        " . str_pad(count($unused_fr), 5, ' ', STR_PAD_LEFT) . " " . (empty($unused_fr) ? "✅" : "⚠️ ") . "\n";
echo "═══════════════════════════════════════════════════════════════\n";

// Score global
$errors = count($missing_en) + count($missing_fr) + count($undefined);
$warnings = count($same) + count($unused_fr);

if ($errors === 0 && $warnings === 0) {
    echo "✅ PARFAIT ! Aucun problème détecté.\n";
} elseif ($errors === 0) {
    echo "⚠️  BIEN mais " . $warnings . " avertissement(s) à vérifier.\n";
} else {
    echo "❌ PROBLÈMES DÉTECTÉS : " . $errors . " erreur(s) et " . $warnings . " avertissement(s).\n";
}

echo "\n";