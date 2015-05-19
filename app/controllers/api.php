<?php
namespace Typolib;

use Transvision\Utils;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-type: text/html; charset=utf-8');

$locale = $_GET['locale'];

switch ($_GET['action']) {
    case 'codes':
        $codes = Code::getCodeList($locale);
        reset($codes);
        echo Utils::getHtmlSelectOptions(Code::getCodeList($locale), key($codes), true);
        break;
    case 'rules':
        $code = $_GET['code'];
        $rules = Rule::getArrayRules($code, $locale);
        $ruletypes = Rule::getRulesTypeList();
        $rule_exceptions = RuleException::getArrayExceptions($code, $locale);
        include VIEWS . 'rules_treeview.php';
    break;
    case 'adding_rule':
        $code = $_GET['code'];
        $type = $_GET['type'];
        $content = $_GET['content'];
        $comment = $_GET['comment'];

        if ($content != '') {
            try {
                $new_rule = new Rule($code, $locale, $content, $type, $comment);
            } catch (Exception $e) {
            }
        }

        $rules = Rule::getArrayRules($code, $locale);
        $ruletypes = Rule::getRulesTypeList();
        $rule_exceptions = RuleException::getArrayExceptions($code, $locale);
        include VIEWS . 'rules_treeview.php';
    break;
    case 'adding_exception':
        $code = $_GET['code'];
        $exception = $_GET['content'];
        $id_rule = $_GET['id_rule'];

        if ($exception != '') {
            try {
                $new_exception = new RuleException($code, $locale, $id_rule, $exception);
            } catch (Exception $e) {
            }
        }

        $rules = Rule::getArrayRules($code, $locale);
        $ruletypes = Rule::getRulesTypeList();
        $rule_exceptions = RuleException::getArrayExceptions($code, $locale);
        include VIEWS . 'rules_treeview.php';
    break;
}
