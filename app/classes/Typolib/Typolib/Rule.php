<?php
namespace Typolib;

use Exception;
use IntlBreakIterator;
use Transvision\Strings;

/**
 * Rule class
 *
 * This class provides methods to manage a rule: create, delete or update,
 * check if a rule exists and get all the rules for a specific code.
 *
 * @package Typolib
 */
class Rule
{
    private $id;
    private $content;
    private $type;
    private $comment;
    // FIXME: string?
    public static $rules_type = [ 'replace_with'         => 'REPLACE %s WITH %s',
                                  'plural_separator'     => 'PLURAL SEPARATOR %s',
                                  'ignore_variable'      => 'IGNORE VARIABLE %s',
                                  'quotation_mark'       => 'QUOTATION MARK %s %s',
                                  'check_before'         => 'CHECK %s BEFORE %s',
                                  'check_after'          => 'CHECK %s AFTER %s',
                                ];
    private static $ifThenRuleArray = [];
    private static $variable_to_ignore_array = [];
    private static $start_variable_tag = '😺';
    private static $end_variable_tag = '💩';
    private static $plural_separator_array = [];
    private static $all_ids = [];
    private static $quotation_marks = [
                                            ['«','»'],
                                            ['“','”'],
                                            ['"','"'],
                                            ['‘','’'],
                                            ['»','«'],
                                            ['„','“'],
                                            ['„','”'],
                                            ['”','”'],
                                      ];

    /**
     * Constructor that initializes all the arguments then call the method to
     * create the rule if the code exists.
     *
     * @param  String    $name_code   The code name from which the rule depends.
     * @param  String    $locale_code The locale code from which the rule depends.
     * @param  array     $content     The content of the new rule.
     * @param  String    $type        The type of the new rule.
     * @param  String    $comment     The comment of the new rule.
     * @throws Exception if rule creation failed.
     */
    public function __construct($name_code, $locale_code, $content, $type, $comment = '')
    {
        $success = false;

        if (Code::existCode($name_code, $locale_code, RULES_STAGING) && self::isSupportedType($type)) {
            if (array_filter($content)) {
                $this->content = $content;
                $this->type = $type;
                $this->comment = $comment;
                $this->createRule($name_code, $locale_code);
                $success = true;
            }
        }

        if (! $success) {
            throw new Exception('Rule creation failed.');
        }
    }

    /**
     * Creates a rule into the rules.php file located inside the code directory.
     *
     * @param String $name_code   The code name from which the rule depends.
     * @param String $locale_code The locale code from which the rule depends.
     */
    private function createRule($name_code, $locale_code)
    {
        $file = DATA_ROOT . RULES_STAGING . "/$locale_code/$name_code/rules.php";
        $code = Rule::getArrayRules($name_code, $locale_code, RULES_STAGING);
        $code['rules'][] = [
                                'content' => $this->content,
                                'type'    => $this->type,
                            ];

        //Get the last inserted id
        end($code['rules']);
        $this->id = key($code['rules']);

        if ($this->comment != '') {
            $code['rules'][$this->id]['comment'] = $this->comment;
        }

        $repo_mgr = new RepoManager();

        file_put_contents($file, serialize($code));

        $repo_mgr->commitAndPush("Adding new rule in /$locale_code/$name_code");
    }

    /**
     * Allows deleting a rule, or updating the content or the type of a rule.
     *
     * @param  String  $name_code   The code name from which the rule depends.
     * @param  String  $locale_code The locale code from which the rule depends.
     * @param  integer $id          The identity of the rule.
     * @param  String  $action      The action to perform: 'delete', 'update_content',
     *                              'update_type' or 'update_comment'.
     * @param  String  $value       The new content or type of the rule. If action
     *                              is 'delete' the value must be empty.
     * @return boolean True if the function succeeds.
     */
    public static function manageRule($name_code, $locale_code, $id, $action, $value = '', $comment = '')
    {
        $file = DATA_ROOT . RULES_STAGING . "/$locale_code/$name_code/rules.php";

        $code = Rule::getArrayRules($name_code, $locale_code, RULES_STAGING);
        if ($code != null && Rule::existRule($code, $id)) {
            switch ($action) {
                case 'delete':
                    unset($code['rules'][$id]);

                    //delete all the exceptions for the rule.
                    $rule_exceptions = self::getArrayRuleExceptions($name_code,
                                                                    $locale_code,
                                                                    $id,
                                                                    RULES_STAGING
                                                                );
                    if ($rule_exceptions != false) {
                        foreach ($rule_exceptions as $id_exception => $content) {
                            RuleException::manageException(
                                                            $name_code,
                                                            $locale_code,
                                                            $id_exception,
                                                            'delete'
                                                        );
                        }
                    }
                    break;

                case 'update_content':
                    $code['rules'][$id]['content'] = $value;
                    $code['rules'][$id]['comment'] = $comment;
                    break;

                case 'update_type':
                    if (self::isSupportedType($value)) {
                        $code['rules'][$id]['type'] = $value;
                    } else {
                        return false;
                    }
                    break;
            }

            $repo_mgr = new RepoManager();

            file_put_contents($file, serialize($code));

            $repo_mgr->commitAndPush("Editing rule in /$locale_code/$name_code");

            return true;
        }

        return false;
    }

    /**
     * Check if the rule exists in a rules array.
     *
     * @param  array   $code The array in which the rule must be searched.
     * @param  integer $id   The identity of the rule we search.
     * @return boolean True if the rule exists
     */
    public static function existRule($code, $id)
    {
        return array_key_exists($id, $code['rules']);
    }

    /**
     * Get an array of all the rules for a specific code.
     *
     * @param String $name_code   The code name from which the rules depend.
     * @param String $locale_code The locale code from which the rules depend.
     * @param String $repo        Repository we want to check (staging or production)
     */
    public static function getArrayRules($name_code, $locale_code, $repo)
    {
        if (Code::existCode($name_code, $locale_code, $repo)) {
            $file = DATA_ROOT . $repo . "/$locale_code/$name_code/rules.php";

            return unserialize(file_get_contents($file));
        }

        return false;
    }

    /**
     * Get an array of all the exceptions for a specific rule.
     *
     * @param String $name_code   The code name from which the exceptions depend.
     * @param String $locale_code The locale code from which the exceptions depend.
     * @param String $id          The rule id from which the exceptions depend.
     * @param String $repo        Repository we want to check (staging or production)
     */
    public static function getArrayRuleExceptions($name_code, $locale_code, $id, $repo)
    {
        $code = Rule::getArrayRules($name_code, $locale_code, $repo);
        if ($code != null && Rule::existRule($code, $id)) {
            $rule_exceptions = RuleException::getArrayExceptions(
                                                                $name_code,
                                                                $locale_code,
                                                                $repo
                                                            );

            if ($rule_exceptions != false) {
                foreach ($rule_exceptions['exceptions'] as $id_exception => $exception) {
                    if ($exception['rule_id'] == $id) {
                        $array[$id_exception] = $exception['content'];
                    }
                }

                return $array;
            }
        }

        return false;
    }

    /**
     * Check in a string if there is quotation marks.
     *
     * @param  String $string The string to check.
     * @return array  $position The list of all quotation marks present in the
     *                       string (with their position in the string).
     */
    private static function findQuotationMarks($string)
    {
        $position = null;
        $i = 0;
        $code_point_iterator = IntlBreakIterator::createCodePointInstance();
        $code_point_iterator->setText($string);
        $parts_iterator = $code_point_iterator->getPartsIterator();

        foreach ($parts_iterator as $part) {
            foreach (array_values(self::$quotation_marks) as $key => $value) {
                if (in_array($part, $value)) {
                    $position[$i] = $part;
                }
            }
            $i++;
        }

        return $position != null ? $position : false;
    }

    /**
     * Check a "quotation mark" rule.
     *
     * @param  string $user_string The string entered by the user.
     * @param  string $before      The opening quotation mark wanted by the user.
     * @param  string $after       The ending quotation mark wanted by the user.
     * @return array  $res         The text corrected and the position of the
     *                            quotation .
     */
    public static function checkQuotationMarkRule($user_string, $rule)
    {
        $res = []; // var to be returned
        $array_quotation_marks = self::findQuotationMarks($user_string);
        $before = $rule[0];
        $after = $rule[1];

        $characters = \Typolib\Strings::getArrayFromString($user_string);

        $variable_to_ignore = self::getTagsPosition($characters);

        $positions = [];
        if ($array_quotation_marks != false) {
            $count = 0;
            foreach ($array_quotation_marks as $position => $quote) {
                if (! self::ignoreCharacter($position, $variable_to_ignore)) {
                    if ($count % 2 == 0 && $quote != $before) {
                        $user_string = \Typolib\Strings::replaceString(
                                                                $user_string,
                                                                $before,
                                                                $position
                                                            );
                        $positions[] = $position;
                    }
                    if ($count % 2 == 1 && $quote != $after) {
                        $user_string = \Typolib\Strings::replaceString(
                                                                $user_string,
                                                                $after,
                                                                $position
                                                            );
                        $positions[] = $position;
                    }
                    $count++;
                }
            }
        }

        array_push($res, $user_string);
        array_push($res, $positions);

        return $res;
    }

    /**
     * Check a "if x then y" rule (just for ellipsis character)
     * TODO : generic method for any character of the ifThen rule array
     *
     * @param string $user_string the string entered by the user
     */
    public static function checkIfThenRule($user_string, $rule)
    {
        $res = []; // var to be returned
        $search = $rule[0];
        $replace = $rule[1];

        $replacements = [];

        $characters = \Typolib\Strings::getArrayFromString($user_string);

        $variable_to_ignore = self::getTagsPosition($characters);

        $positions = []; // array containing the positions of the errors detected in the source string

        $last_position = 0;

        // save all the positions of the errors
        while (($last_position = strpos($user_string, $search, $last_position)) !== false) {
            $next_position = $last_position + strlen($search);
            if (! self::ignoreCharacter($last_position, $variable_to_ignore)) {
                $positions[] = [$last_position, $next_position];
                $replacements[] = $last_position;
            }
            $last_position = $next_position;
        }

        if (! empty(($replacements))) {
            $replacements = array_reverse($replacements, true);
            foreach ($replacements as $key => $value) {
                $user_string = \Typolib\Strings::replaceString(
                                                                $user_string,
                                                                $replace,
                                                                $value,
                                                                strlen($search)
                                                            );
            }
        }

        array_push($res, $user_string);
        array_push($res, $positions);

        return $res;
    }

    /**
     * Check a "separator" rule.
     *
     * @param  string $user_string The string entered by the user.
     * @param  array  $rule        The rule with the separator.
     * @return string $user_string The string with the tags arround the
     *                            separator.
     */
    private static function checkSeparatorRule($user_string, $rule)
    {
        $separator = $rule[0];
        $pos = strpos($user_string, $separator);

        if ($pos !== false) {
            $split_strings = explode($separator, $user_string);
            $levenshtein_results = [];
            $acceptance_level = 90;

            $arr_length = count($split_strings);
            for ($i = 0;$i < $arr_length;$i++) {
                if ($i + 1 < $arr_length) {
                    $levenshtein_results[] = Strings::levenshteinQuality(
                                                        $split_strings[$i],
                                                        $split_strings[$i + 1]
                                                    );
                }
            }

            $levenshtein_results_average = 0;

            foreach ($levenshtein_results as $key => $value) {
                $levenshtein_results_average += $value;
            }

            $levenshtein_results_average =
                    $levenshtein_results_average / count($levenshtein_results);

            if ($levenshtein_results_average > $acceptance_level) {
                $user_string = str_replace(
                            $separator,
                            self::$start_variable_tag . $separator . self::$end_variable_tag,
                            $user_string
                        );
            }
        }

        return $user_string;
    }

    /**
     * Check a "check_before" or "check_after" rule.
     *
     * @param  string $user_string The string entered by the user.
     * @param  array  $rule        The rule to check.
     * @param  string $mode        The type of the rule: "check_before" or
     *                             "check_after".
     * @return array  $res         The text corrected and the position of the
     *                            mistakes.
     */
    private static function checkBeforeAfter($user_string, $rule, $mode)
    {
        $res = [];
        $replacements = [];
        $searched_character = $rule[1];
        $check = $rule[0];
        $positions = [];
        $ignore = false;

        $characters = \Typolib\Strings::getArrayFromString($user_string);
        $check_array = \Typolib\Strings::getArrayFromString($check);

        $variable_to_ignore = self::getTagsPosition($characters);

        $searched_characters_positions = [];
        if (in_array($searched_character, $characters)) {
            $searched_characters_positions = array_keys(
                                                    $characters,
                                                    $searched_character
                                                );

            foreach ($searched_characters_positions as $key => $position) {
                if (! self::ignoreCharacter($position, $variable_to_ignore)) {
                    $found = false;
                    $i = $mode == 'check_after'
                                            ? $position + 1
                                            : $position - strlen($check);

                    if (! empty($check_array)) {
                        foreach ($check_array as $key => $char) {
                            if (! isset($characters[$i]) || $char != $characters[$i]) {
                                if ($mode == 'check_before') {
                                    $replacements[] = [$position, $char, 0];
                                } elseif (! $found) {
                                    $slice = implode(array_slice($check_array, $key));
                                    $replacements[] = [$i, $slice, 0];
                                }
                                $found = true;
                            }
                            $i++;
                        }
                    } else {
                        if ($mode == 'check_before') {
                            $i--;
                        }
                        if ($characters[$i] == NBSP || $characters[$i] == WHITE_SP || $characters[$i] == NARROW_NBSP) {
                            $replacements[] = [$i, '', 1];
                            $found = true;
                        }
                    }

                    if ($found) {
                        $positions[] = $position;
                    }
                }
            }
        }

        $replacements = array_reverse($replacements, true);
        foreach ($replacements as $key => $value) {
            $user_string = \Typolib\Strings::replaceString(
                                                            $user_string,
                                                            $value[1],
                                                            $value[0],
                                                            $value[2]
                                                        );
        }

        array_push($res, $user_string);
        array_push($res, $positions);

        return $res;
    }

    /**
     * Check a "ignore_variable" rule.
     *
     * @param  string $user_string The string entered by the user.
     * @param  array  $rule        The rule to check.
     * @return array  $string_with_tag The text corrected and the position of the
     *                            mistakes.
     */
    private static function checkIgnoreVariables($user_string, $rule)
    {
        $res = [];
        $positions = [];
        $variable_to_ignore = $rule[0];
        $offset = 0;
        $found = true;
        $string_with_tag = $user_string;

        if (strpos($user_string, $variable_to_ignore, $offset) !== false) {
            $string_with_tag = str_replace(
                            $variable_to_ignore,
                            self::$start_variable_tag . $variable_to_ignore . self::$end_variable_tag,
                            $user_string
                        );
        }

        return $string_with_tag;
    }

    /**
     * Check if the character has to be ignore or not.
     *
     * @param  int     $position           The position of the character we want to check.
     * @param  array   $variable_positions The positions of the variable to ignore.
     * @return boolean $ignore True if the character has to be ignore.
     */
    private static function ignoreCharacter($position, $variable_positions)
    {
        $ignore = false;
        if (! empty($variable_positions)) {
            foreach ($variable_positions as $key => $value) {
                if ($position > $value[0] && $position < $value[1]) {
                    $ignore = true;
                }
            }
        }

        return $ignore;
    }
    /**
     * Ignore all the variables of the variable_to_ignore_array in the user string
     *
     * @param string $user_string the string entered by the user
     */
    public static function ignoreVariables($user_string)
    {
        strtr($user_string, self::$variable_to_ignore_array);
    }

    /**
     * Unused for now
     */
    public static function generateRuleId()
    {
        $array = Rule::scanDirectory(DATA_ROOT . 'code');
        $id = empty($array) ? 0 : max($array);

        return ++$id;
    }

    /**
     * Scan the directory and put all the rules id in an array
     *
     * @param  String $dir The directory to be scanned.
     * @return array  $all_ids The array which contains all the rules id.
     */
    public static function scanDirectory($dir)
    {
        if (is_dir($dir)) {
            $me = opendir($dir);
            while ($child = readdir($me)) {
                if ($child != '.' && $child != '..') {
                    $folder = $dir . DIRECTORY_SEPARATOR . $child;
                    if ($child == 'rules.php') {
                        $code = unserialize(file_get_contents($folder));
                        foreach (array_keys($code['rules']) as $key => $value) {
                            self::$all_ids[] = $value;
                        }
                    }
                    Rule::scanDirectory($folder);
                }
            }
            unset($code);
        }

        return self::$all_ids;
    }

    /**
     * Check if the type of the rule is supported or not
     *
     * @param  String  $type The type of the rule we want to check.
     * @return boolean True if the type is supported.
     */
    public static function isSupportedType($type)
    {
        return array_key_exists($type, self::$rules_type);
    }

    /**
     * Get the list of all the types of rules
     *
     * @return array rules_type which contains all the supported types.
     */
    public static function getRulesTypeList()
    {
        return self::$rules_type;
    }

    public static function getPrettyRulesTypeList()
    {
        foreach (self::getRulesTypeList() as $key => $value) {
            $ruletypes[$key] = sprintf(str_replace('%s', '%1$s', $value), '[…]');
        }

        return $ruletypes;
    }

    public static function buildRuleString($type, $rule)
    {
        if (self::isSupportedType($type)) {
            return vsprintf(self::$rules_type[$type], $rule);
        }
    }

    /**
     * Return the positions of characters between the tags in a string.
     *
     * @param  array $characters The string we want to get the variable to ignore.
     * @return array $variable_to_ignore The positions of all the tags.
     */
    private static function getTagsPosition($characters)
    {
        $variable_to_ignore = [];

        if (in_array(self::$start_variable_tag, $characters)) {
            $start_tags = array_keys($characters, self::$start_variable_tag);
        }

        if (in_array(self::$end_variable_tag, $characters)) {
            $end_tags = array_keys($characters, self::$end_variable_tag);
        }

        if (! empty($start_tags)) {
            $count = 0;
            foreach ($start_tags as $key => $position) {
                $variable_to_ignore[] = [$position, $end_tags[$count]];
                $count++;
            }
        }

        return $variable_to_ignore;
    }

    /**
     * Remove all the tags from a string.
     *
     * @param  string $string The string we want to remove tags.
     * @return string $string The string without the tags.
     */
    private static function removeTagsFromString($string)
    {
        $string = str_replace(self::$start_variable_tag, '', $string);
        $string = str_replace(self::$end_variable_tag, '', $string);

        return $string;
    }

    public static function process($string, $rules)
    {
        $processed_string = [];
        $positions = [];
        $result = [];
        $rule_comment;
        foreach ($rules['rules'] as $id => $rule) {
            if ($rule['type'] == 'plural_separator') {
                $string = self::checkSeparatorRule($string, $rule['content']);
            }
        }
        foreach ($rules['rules'] as $id => $rule) {
            if ($rule['type'] == 'ignore_variable') {
                $string = self::checkIgnoreVariables($string, $rule['content']);
            }
        }
        foreach ($rules['rules'] as $id => $rule) {
            if ($rule['type'] == 'replace_with') {
                $result = self::checkIfThenRule($string, $rule['content']);
                $comment = ! empty($rule['comment']) ? $rule['comment'] : '';
                $positions[] = [$result[1], $comment];
                $string = $result[0];
            }
        }
        foreach ($rules['rules'] as $id => $rule) {
            if ($rule['type'] == 'quotation_mark') {
                $result = self::checkQuotationMarkRule($string, $rule['content']);
                $comment = ! empty($rule['comment']) ? $rule['comment'] : '';
                $positions[] = [$result[1], $comment];
                $string = $result[0];
            }
        }
        foreach ($rules['rules'] as $id => $rule) {
            if (($rule['type'] == 'check_before') || ($rule['type'] == 'check_after')) {
                $result = self::checkBeforeAfter($string, $rule['content'], $rule['type']);
                $comment = ! empty($rule['comment']) ? $rule['comment'] : '';
                $positions[] = [$result[1], $comment];
                $string = $result[0];
            }
        }

        $string = self::removeTagsFromString($string);

        array_push($processed_string, $string);
        array_push($processed_string, $positions);

        return $processed_string;
    }
}
