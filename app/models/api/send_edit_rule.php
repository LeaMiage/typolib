<?php
namespace Typolib;

$edit_mode = true;

$id_rule = $_GET['id_rule'];
$comment = $_GET['comment'];
$id_type = $_GET['id_type'];

$content_array_with_tags = json_decode($_GET['array']);

$array_OK = true;
if (! empty($content_array_with_tags)) {
    foreach ($content_array_with_tags as $key => $value) {
        if (empty($value)) {
            $array_OK = false;
        }
    }
    if ($array_OK) {
        $content_array = [];
        foreach ($content_array_with_tags as $key => $field) {
            $content_array[$key] = Strings::replaceTagsBySpaces($field);
        }
        echo Rule::buildRuleString($id_type, $content_array);

        Utils::closeConnection();

        Rule::manageRule($code, $locale, $id_rule, 'update_content', $content_array, $comment);
    } else {
        echo '0';
    }
} else {
    echo '0';
}
