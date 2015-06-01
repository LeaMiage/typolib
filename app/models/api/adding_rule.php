<?php
namespace Typolib;

$edit_mode = true;

$type = $_GET['type'];
$comment = $_GET['comment'];
$content_array = array_filter(json_decode($_GET['array']));

$array_OK = true;
if (! empty($content_array)) {
    foreach ($content_array as $key => $value) {
        if ($value == '') {
            $array_OK = false;
        }
    }
    try {
        if ($array_OK) {
            $new_rule = new Rule($code, $locale, $content_array, $type, $comment);

            include MODELS . 'prepare_set_of_rules.php';
            include VIEWS . 'view_treeview.php';
        } else {
            echo '0';
        }
    } catch (Exception $e) {
    }
} else {
    echo '0';
}
