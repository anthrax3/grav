<?php
/**
 * @package    Grav.Common.Twig
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig;

use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Media;
use Grav\Common\Twig\TokenParser\TwigTokenParserScript;
use Grav\Common\Twig\TokenParser\TwigTokenParserStyle;
use Grav\Common\Twig\TokenParser\TwigTokenParserSwitch;
use Grav\Common\Twig\TokenParser\TwigTokenParserTryCatch;
use Grav\Common\Twig\TokenParser\TwigTokenParserMarkdown;
use Grav\Common\Utils;
use Grav\Common\Markdown\Parsedown;
use Grav\Common\Markdown\ParsedownExtra;
use Grav\Common\Helpers\Base32;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;
use Symfony\Component\Yaml\Yaml;

class TwigExtension extends \Twig_Extension implements \Twig_Extension_GlobalsInterface
{
    protected $grav;
    protected $debugger;
    protected $config;

    /**
     * TwigExtension constructor.
     */
    public function __construct()
    {
        $this->grav     = Grav::instance();
        $this->debugger = isset($this->grav['debugger']) ? $this->grav['debugger'] : null;
        $this->config   = $this->grav['config'];
    }

    /**
     * Register some standard globals
     *
     * @return array
     */
    public function getGlobals()
    {
        return [
            'grav' => $this->grav,
        ];
    }

    /**
     * Return a list of all filters.
     *
     * @return array
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('*ize', [$this, 'inflectorFilter']),
            new \Twig_SimpleFilter('absolute_url', [$this, 'absoluteUrlFilter']),
            new \Twig_SimpleFilter('contains', [$this, 'containsFilter']),
            new \Twig_SimpleFilter('chunk_split', [$this, 'chunkSplitFilter']),
            new \Twig_SimpleFilter('nicenumber', [$this, 'niceNumberFunc']),
            new \Twig_SimpleFilter('nicefilesize', [$this, 'niceFilesizeFunc']),
            new \Twig_SimpleFilter('nicetime', [$this, 'nicetimeFunc']),
            new \Twig_SimpleFilter('defined', [$this, 'definedDefaultFilter']),
            new \Twig_SimpleFilter('ends_with', [$this, 'endsWithFilter']),
            new \Twig_SimpleFilter('fieldName', [$this, 'fieldNameFilter']),
            new \Twig_SimpleFilter('ksort', [$this, 'ksortFilter']),
            new \Twig_SimpleFilter('ltrim', [$this, 'ltrimFilter']),
            new \Twig_SimpleFilter('markdown', [$this, 'markdownFunction']),
            new \Twig_SimpleFilter('md5', [$this, 'md5Filter']),
            new \Twig_SimpleFilter('base32_encode', [$this, 'base32EncodeFilter']),
            new \Twig_SimpleFilter('base32_decode', [$this, 'base32DecodeFilter']),
            new \Twig_SimpleFilter('base64_encode', [$this, 'base64EncodeFilter']),
            new \Twig_SimpleFilter('base64_decode', [$this, 'base64DecodeFilter']),
            new \Twig_SimpleFilter('randomize', [$this, 'randomizeFilter']),
            new \Twig_SimpleFilter('modulus', [$this, 'modulusFilter']),
            new \Twig_SimpleFilter('rtrim', [$this, 'rtrimFilter']),
            new \Twig_SimpleFilter('pad', [$this, 'padFilter']),
            new \Twig_SimpleFilter('regex_replace', [$this, 'regexReplace']),
            new \Twig_SimpleFilter('safe_email', [$this, 'safeEmailFilter']),
            new \Twig_SimpleFilter('safe_truncate', ['\Grav\Common\Utils', 'safeTruncate']),
            new \Twig_SimpleFilter('safe_truncate_html', ['\Grav\Common\Utils', 'safeTruncateHTML']),
            new \Twig_SimpleFilter('sort_by_key', [$this, 'sortByKeyFilter']),
            new \Twig_SimpleFilter('starts_with', [$this, 'startsWithFilter']),
            new \Twig_SimpleFilter('t', [$this, 'translate']),
            new \Twig_SimpleFilter('tl', [$this, 'translateLanguage']),
            new \Twig_SimpleFilter('ta', [$this, 'translateArray']),
            new \Twig_SimpleFilter('truncate', ['\Grav\Common\Utils', 'truncate']),
            new \Twig_SimpleFilter('truncate_html', ['\Grav\Common\Utils', 'truncateHTML']),
            new \Twig_SimpleFilter('json_decode', [$this, 'jsonDecodeFilter']),
            new \Twig_SimpleFilter('array_unique', 'array_unique'),
            new \Twig_SimpleFilter('basename', 'basename'),
            new \Twig_SimpleFilter('dirname', 'dirname'),
            new \Twig_SimpleFilter('print_r', 'print_r'),
            new \Twig_SimpleFilter('yaml_encode', [$this, 'yamlEncodeFilter']),
            new \Twig_SimpleFilter('yaml_decode', [$this, 'yamlDecodeFilter']),
        ];
    }

    /**
     * Return a list of all functions.
     *
     * @return array
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('array', [$this, 'arrayFunc']),
            new \Twig_SimpleFunction('array_key_value', [$this, 'arrayKeyValueFunc']),
            new \Twig_SimpleFunction('array_key_exists', 'array_key_exists'),
            new \Twig_SimpleFunction('array_unique', 'array_unique'),
            new \Twig_SimpleFunction('array_intersect', [$this, 'arrayIntersectFunc']),
            new \Twig_simpleFunction('authorize', [$this, 'authorize']),
            new \Twig_SimpleFunction('debug', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new \Twig_SimpleFunction('dump', [$this, 'dump'], ['needs_context' => true, 'needs_environment' => true]),
            new \Twig_SimpleFunction('vardump', [$this, 'vardumpFunc']),
            new \Twig_SimpleFunction('print_r', 'print_r'),
            new \Twig_SimpleFunction('http_response_code', 'http_response_code'),
            new \Twig_SimpleFunction('evaluate', [$this, 'evaluateStringFunc'], ['needs_context' => true]),
            new \Twig_SimpleFunction('evaluate_twig', [$this, 'evaluateTwigFunc'], ['needs_context' => true]),
            new \Twig_SimpleFunction('gist', [$this, 'gistFunc']),
            new \Twig_SimpleFunction('nonce_field', [$this, 'nonceFieldFunc']),
            new \Twig_SimpleFunction('pathinfo', 'pathinfo'),
            new \Twig_simpleFunction('random_string', [$this, 'randomStringFunc']),
            new \Twig_SimpleFunction('repeat', [$this, 'repeatFunc']),
            new \Twig_SimpleFunction('regex_replace', [$this, 'regexReplace']),
            new \Twig_SimpleFunction('regex_filter', [$this, 'regexFilter']),
            new \Twig_SimpleFunction('string', [$this, 'stringFunc']),
            new \Twig_simpleFunction('t', [$this, 'translate']),
            new \Twig_simpleFunction('tl', [$this, 'translateLanguage']),
            new \Twig_simpleFunction('ta', [$this, 'translateArray']),
            new \Twig_SimpleFunction('url', [$this, 'urlFunc']),
            new \Twig_SimpleFunction('json_decode', [$this, 'jsonDecodeFilter']),
            new \Twig_SimpleFunction('get_cookie', [$this, 'getCookie']),
            new \Twig_SimpleFunction('redirect_me', [$this, 'redirectFunc']),
            new \Twig_SimpleFunction('range', [$this, 'rangeFunc']),
            new \Twig_SimpleFunction('isajaxrequest', [$this, 'isAjaxFunc']),
            new \Twig_SimpleFunction('exif', [$this, 'exifFunc']),
            new \Twig_SimpleFunction('media_directory', [$this, 'mediaDirFunc']),
            new \Twig_SimpleFunction('body_class', [$this, 'bodyClassFunc']),
            new \Twig_SimpleFunction('theme_var', [$this, 'themeVarFunc']),
            new \Twig_SimpleFunction('header_var', [$this, 'pageHeaderVarFunc']),
            new \Twig_SimpleFunction('read_file', [$this, 'readFileFunc']),
            new \Twig_SimpleFunction('nicenumber', [$this, 'niceNumberFunc']),
            new \Twig_SimpleFunction('nicefilesize', [$this, 'niceFilesizeFunc']),
            new \Twig_SimpleFunction('nicetime', [$this, 'nicetimeFilter']),

        ];
    }

    /**
     * @return array
     */
    public function getTokenParsers()
    {
        return [
            new TwigTokenParserTryCatch(),
            new TwigTokenParserScript(),
            new TwigTokenParserStyle(),
            new TwigTokenParserMarkdown(),
            new TwigTokenParserSwitch(),
        ];
    }

    /**
     * Filters field name by changing dot notation into array notation.
     *
     * @param  string $str
     *
     * @return string
     */
    public function fieldNameFilter($str)
    {
        $path = explode('.', rtrim($str, '.'));

        return array_shift($path) . ($path ? '[' . implode('][', $path) . ']' : '');
    }

    /**
     * Protects email address.
     *
     * @param  string $str
     *
     * @return string
     */
    public function safeEmailFilter($str)
    {
        $email   = '';
        for ( $i = 0, $len = strlen( $str ); $i < $len; $i++ ) {
            $j = mt_rand( 0, 1);
            if ( $j === 0 ) {
                $email .= '&#' . ord( $str[$i] ) . ';';
            } elseif ( $j === 1 ) {
                $email .= $str[$i];
            }
        }

        return str_replace( '@', '&#64;', $email );
    }

    /**
     * Returns array in a random order.
     *
     * @param  array $original
     * @param  int   $offset Can be used to return only slice of the array.
     *
     * @return array
     */
    public function randomizeFilter($original, $offset = 0)
    {
        if (!is_array($original)) {
            return $original;
        }

        if ($original instanceof \Traversable) {
            $original = iterator_to_array($original, false);
        }

        $sorted = [];
        $random = array_slice($original, $offset);
        shuffle($random);

        $sizeOf = count($original);
        for ($x = 0; $x < $sizeOf; $x++) {
            if ($x < $offset) {
                $sorted[] = $original[$x];
            } else {
                $sorted[] = array_shift($random);
            }
        }

        return $sorted;
    }

    /**
     * Returns the modulus of an integer
     *
     * @param  string|int   $number
     * @param  int          $divider
     * @param  array        $items array of items to select from to return
     *
     * @return int
     */
    public function modulusFilter($number, $divider, $items = null)
    {
        if (is_string($number)) {
            $number = strlen($number);
        }

        $remainder = $number % $divider;

        if (is_array($items)) {
            if (isset($items[$remainder])) {
                return $items[$remainder];
            }

                return $items[0];
            }

        return $remainder;
    }

    /**
     * Inflector supports following notations:
     *
     * `{{ 'person'|pluralize }} => people`
     * `{{ 'shoes'|singularize }} => shoe`
     * `{{ 'welcome page'|titleize }} => "Welcome Page"`
     * `{{ 'send_email'|camelize }} => SendEmail`
     * `{{ 'CamelCased'|underscorize }} => camel_cased`
     * `{{ 'Something Text'|hyphenize }} => something-text`
     * `{{ 'something_text_to_read'|humanize }} => "Something text to read"`
     * `{{ '181'|monthize }} => 5`
     * `{{ '10'|ordinalize }} => 10th`
     *
     * @param string $action
     * @param string $data
     * @param int    $count
     *
     * @return mixed
     */
    public function inflectorFilter($action, $data, $count = null)
    {
        $action = $action . 'ize';

        $inflector = $this->grav['inflector'];

        if (\in_array(
            $action,
            ['titleize', 'camelize', 'underscorize', 'hyphenize', 'humanize', 'ordinalize', 'monthize'],
            true
        )) {
            return $inflector->$action($data);
        }

        if (\in_array($action, ['pluralize', 'singularize'], true)) {
            if ($count) {
                return $inflector->$action($data, $count);
            }

                return $inflector->$action($data);
            }

            return $data;
    }

    /**
     * Return MD5 hash from the input.
     *
     * @param  string $str
     *
     * @return string
     */
    public function md5Filter($str)
    {
        return md5($str);
    }

    /**
     * Return Base32 encoded string
     *
     * @param $str
     * @return string
     */
    public function base32EncodeFilter($str)
    {
        return Base32::encode($str);
    }

    /**
     * Return Base32 decoded string
     *
     * @param $str
     * @return bool|string
     */
    public function base32DecodeFilter($str)
    {
        return Base32::decode($str);
    }

    /**
     * Return Base64 encoded string
     *
     * @param $str
     * @return string
     */
    public function base64EncodeFilter($str)
    {
        return base64_encode($str);
    }

    /**
     * Return Base64 decoded string
     *
     * @param $str
     * @return bool|string
     */
    public function base64DecodeFilter($str)
    {
        return base64_decode($str);
    }


    /**
     * Sorts a collection by key
     *
     * @param  array    $input
     * @param  string   $filter
     * @param array|int $direction
     *
     * @return array
     */
    public function sortByKeyFilter($input, $filter, $direction = SORT_ASC)
    {
        $output = [];

        if (!is_array($input) || !$input) {
            return $output;
        }

        foreach ($input as $key => $row) {
            $output[$key] = $row[$filter];
        }

        array_multisort($output, $direction, $input);

        return $input;
    }

    /**
     * Return ksorted collection.
     *
     * @param  array $array
     *
     * @return array
     */
    public function ksortFilter($array)
    {
        if (null === $array) {
            $array = [];
        }
        ksort($array);

        return $array;
    }

    /**
     * Wrapper for chunk_split() function
     *
     * @param $value
     * @param $chars
     * @param string $split
     * @return string
     */
    public function chunkSplitFilter($value, $chars, $split = '-')
    {
        return chunk_split($value, $chars, $split);
    }

    /**
     * determine if a string contains another
     *
     * @param String $haystack
     * @param String $needle
     *
     * @return boolean
     */
    public function containsFilter($haystack, $needle)
    {
        return (strpos($haystack, $needle) !== false);
    }

    /**
     * displays a facebook style 'time ago' formatted date/time
     *
     * @param $date
     * @param $long_strings
     *
     * @return boolean
     */
    public function nicetimeFunc($date, $long_strings = true)
    {
        if (empty($date)) {
            return $this->grav['language']->translate('NICETIME.NO_DATE_PROVIDED', null, true);
        }

        if ($long_strings) {
            $periods = [
                "NICETIME.SECOND",
                "NICETIME.MINUTE",
                "NICETIME.HOUR",
                "NICETIME.DAY",
                "NICETIME.WEEK",
                "NICETIME.MONTH",
                "NICETIME.YEAR",
                "NICETIME.DECADE"
            ];
        } else {
            $periods = [
                "NICETIME.SEC",
                "NICETIME.MIN",
                "NICETIME.HR",
                "NICETIME.DAY",
                "NICETIME.WK",
                "NICETIME.MO",
                "NICETIME.YR",
                "NICETIME.DEC"
            ];
        }

        $lengths = ["60", "60", "24", "7", "4.35", "12", "10"];

        $now = time();

        // check if unix timestamp
        if ((string)(int)$date == $date) {
            $unix_date = $date;
        } else {
            $unix_date = strtotime($date);
        }

        // check validity of date
        if (empty($unix_date)) {
            return $this->grav['language']->translate('NICETIME.BAD_DATE', null, true);
        }

        // is it future date or past date
        if ($now > $unix_date) {
            $difference = $now - $unix_date;
            $tense      = $this->grav['language']->translate('NICETIME.AGO', null, true);

        } else if ($now == $unix_date) {
            $difference = $now - $unix_date;
            $tense      = $this->grav['language']->translate('NICETIME.JUST_NOW', null, false);

        } else {
            $difference = $unix_date - $now;
            $tense      = $this->grav['language']->translate('NICETIME.FROM_NOW', null, true);
        }

        for ($j = 0; $difference >= $lengths[$j] && $j < count($lengths) - 1; $j++) {
            $difference /= $lengths[$j];
        }

        $difference = round($difference);

        if ($difference != 1) {
            $periods[$j] .= '_PLURAL';
        }

        if ($this->grav['language']->getTranslation($this->grav['language']->getLanguage(),
            $periods[$j] . '_MORE_THAN_TWO')
        ) {
            if ($difference > 2) {
                $periods[$j] .= '_MORE_THAN_TWO';
            }
        }

        $periods[$j] = $this->grav['language']->translate($periods[$j], null, true);

        if ($now == $unix_date) {
            return "{$tense}";
        }

            return "$difference $periods[$j] {$tense}";
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    public function absoluteUrlFilter($string)
    {
        $url    = $this->grav['uri']->base();
        $string = preg_replace('/((?:href|src) *= *[\'"](?!(http|ftp)))/i', "$1$url", $string);

        return $string;

    }

    /**
     * @param $string
     *
     * @param bool $block  Block or Line processing
     * @return mixed|string
     */
    public function markdownFunction($string, $block = true)
    {
        $page     = $this->grav['page'];
        $defaults = $this->config->get('system.pages.markdown');

        // Initialize the preferred variant of Parsedown
        if ($defaults['extra']) {
            $parsedown = new ParsedownExtra($page, $defaults);
        } else {
            $parsedown = new Parsedown($page, $defaults);
        }

        if ($block) {
            $string = $parsedown->text($string);
        } else {
            $string = $parsedown->line($string);
        }


        return $string;
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public function startsWithFilter($haystack, $needle)
    {
        return Utils::startsWith($haystack, $needle);
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public function endsWithFilter($haystack, $needle)
    {
        return Utils::endsWith($haystack, $needle);
    }

    /**
     * @param      $value
     * @param null $default
     *
     * @return null
     */
    public function definedDefaultFilter($value, $default = null)
    {
        return null !== $value ? $value : $default;
        }

    /**
     * @param      $value
     * @param null $chars
     *
     * @return string
     */
    public function rtrimFilter($value, $chars = null)
    {
        return rtrim($value, $chars);
    }

    /**
     * @param      $value
     * @param null $chars
     *
     * @return string
     */
    public function ltrimFilter($value, $chars = null)
    {
        return ltrim($value, $chars);
    }

    /**
     * @return mixed
     */
    public function translate()
    {
        return $this->grav['language']->translate(func_get_args());
    }

    /**
     * Translate Strings
     *
     * @param $args
     * @param array|null $languages
     * @param bool $array_support
     * @param bool $html_out
     * @return mixed
     */
    public function translateLanguage($args, array $languages = null, $array_support = false, $html_out = false)
    {
        return $this->grav['language']->translate($args, $languages, $array_support, $html_out);
    }

    /**
     * @param      $key
     * @param      $index
     * @param null $lang
     *
     * @return mixed
     */
    public function translateArray($key, $index, $lang = null)
    {
        return $this->grav['language']->translateArray($key, $index, $lang);
    }

    /**
     * Repeat given string x times.
     *
     * @param  string $input
     * @param  int    $multiplier
     *
     * @return string
     */
    public function repeatFunc($input, $multiplier)
    {
        return str_repeat($input, $multiplier);
    }

    /**
     * Return URL to the resource.
     *
     * @example {{ url('theme://images/logo.png')|default('http://www.placehold.it/150x100/f4f4f4') }}
     *
     * @param  string $input  Resource to be located.
     * @param  bool   $domain True to include domain name.
     *
     * @return string|null      Returns url to the resource or null if resource was not found.
     */
    public function urlFunc($input, $domain = false)
    {
        return Utils::url($input, $domain);
        }

    /**
     * This function will evaluate Twig $twig through the $environment, and return its results.
     *
     * @param array $context
     * @param string $twig
     * @return mixed
     */
    public function evaluateTwigFunc($context, $twig ) {

        $loader = new \Twig_Loader_Filesystem('.');
        $env = new \Twig_Environment($loader);

        $template = $env->createTemplate($twig);
        return $template->render($context);
;
    }

    /**
     * This function will evaluate a $string through the $environment, and return its results.
     *
     * @param $context
     * @param $string
     * @return mixed
     */
    public function evaluateStringFunc($context, $string )
    {
        return $this->evaluateTwigFunc($context, "{{ $string }}");
    }


    /**
     * Based on Twig_Extension_Debug / twig_var_dump
     * (c) 2011 Fabien Potencier
     *
     * @param \Twig_Environment $env
     * @param                   $context
     */
    public function dump(\Twig_Environment $env, $context)
    {
        if (!$env->isDebug() || !$this->debugger) {
            return;
        }

        $count = func_num_args();
        if (2 === $count) {
            $data = [];
            foreach ($context as $key => $value) {
                if (is_object($value)) {
                    if (method_exists($value, 'toArray')) {
                        $data[$key] = $value->toArray();
                    } else {
                        $data[$key] = "Object (" . get_class($value) . ")";
                    }
                } else {
                    $data[$key] = $value;
                }
            }
            $this->debugger->addMessage($data, 'debug');
        } else {
            for ($i = 2; $i < $count; $i++) {
                $this->debugger->addMessage(func_get_arg($i), 'debug');
            }
        }
    }

    /**
     * Output a Gist
     *
     * @param  string $id
     * @param  string $file
     *
     * @return string
     */
    public function gistFunc($id, $file = false)
    {
        $url = 'https://gist.github.com/' . $id . '.js';
        if ($file) {
            $url .= '?file=' . $file;
        }
        return '<script src="' . $url . '"></script>';
    }

    /**
     * Generate a random string
     *
     * @param int $count
     *
     * @return string
     */
    public function randomStringFunc($count = 5)
    {
        return Utils::generateRandomString($count);
    }

    /**
     * Pad a string to a certain length with another string
     *
     * @param        $input
     * @param        $pad_length
     * @param string $pad_string
     * @param int    $pad_type
     *
     * @return string
     */
    public static function padFilter($input, $pad_length, $pad_string = " ", $pad_type = STR_PAD_RIGHT)
    {
        return str_pad($input, (int)$pad_length, $pad_string, $pad_type);
    }


    /**
     * Cast a value to array
     *
     * @param $value
     *
     * @return array
     */
    public function arrayFunc($value)
    {
        return (array)$value;
    }

    /**
     * Workaround for twig associative array initialization
     * Returns a key => val array
     *
     * @param string $key           key of item
     * @param string $val           value of item
     * @param array  $current_array optional array to add to
     *
     * @return array
     */
    public function arrayKeyValueFunc($key, $val, $current_array = null)
    {
        if (empty($current_array)) {
            return array($key => $val);
        }

            $current_array[$key] = $val;
            return $current_array;
        }

    /**
     * Wrapper for array_intersect() method
     *
     * @param $array1
     * @param $array2
     * @return array
     */
    public function arrayIntersectFunc($array1, $array2)
    {
        if ($array1 instanceof Collection && $array2 instanceof Collection) {
            return $array1->intersect($array2);
        }

        return array_intersect($array1, $array2);
    }

    /**
     * Returns a string from a value. If the value is array, return it json encoded
     *
     * @param $value
     *
     * @return string
     */
    public function stringFunc($value)
    {
        if (is_array($value)) { //format the array as a string
            return json_encode($value);
        }

            return $value;
        }

    /**
     * Translate a string
     *
     * @return string
     */
    public function translateFunc()
    {
        return $this->grav['language']->translate(func_get_args());
    }

    /**
     * Authorize an action. Returns true if the user is logged in and
     * has the right to execute $action.
     *
     * @param  string|array $action An action or a list of actions. Each
     *                              entry can be a string like 'group.action'
     *                              or without dot notation an associative
     *                              array.
     * @return bool                 Returns TRUE if the user is authorized to
     *                              perform the action, FALSE otherwise.
     */
    public function authorize($action)
    {
        if (!$this->grav['user']->authenticated) {
            return false;
        }

        $action = (array) $action;
        foreach ($action as $key => $perms) {
            $prefix = is_int($key) ? '' : $key . '.';
            $perms = $prefix ? (array) $perms : [$perms => true];
            foreach ($perms as $action2 => $authenticated) {
                if ($this->grav['user']->authorize($prefix . $action2)) {
                    return $authenticated;
                }
            }
        }

        return false;
    }

    /**
     * Used to add a nonce to a form. Call {{ nonce_field('action') }} specifying a string representing the action.
     *
     * For maximum protection, ensure that the string representing the action is as specific as possible
     *
     * @param string $action         the action
     * @param string $nonceParamName a custom nonce param name
     *
     * @return string the nonce input field
     */
    public function nonceFieldFunc($action, $nonceParamName = 'nonce')
    {
        $string = '<input type="hidden" name="' . $nonceParamName . '" value="' . Utils::getNonce($action) . '" />';

        return $string;
    }

    /**
     * Decodes string from JSON.
     *
     * @param  string  $str
     * @param  bool  $assoc
     * @param int $depth
     * @param int $options
     * @return array
     */
    public function jsonDecodeFilter($str, $assoc = false, $depth = 512, $options = 0)
    {
        return json_decode(html_entity_decode($str), $assoc, $depth, $options);
    }

    /**
     * Used to retrieve a cookie value
     *
     * @param string $key     The cookie name to retrieve
     *
     * @return mixed
     */
    public function getCookie($key)
    {
        return filter_input(INPUT_COOKIE, $key, FILTER_SANITIZE_STRING);
    }

    /**
     * Twig wrapper for PHP's preg_replace method
     *
     * @param mixed $subject the content to perform the replacement on
     * @param mixed $pattern the regex pattern to use for matches
     * @param mixed $replace the replacement value either as a string or an array of replacements
     * @param int   $limit   the maximum possible replacements for each pattern in each subject
     *
     * @return mixed the resulting content
     */
    public function regexReplace($subject, $pattern, $replace, $limit = -1)
    {
        return preg_replace($pattern, $replace, $subject, $limit);
    }

    /**
     * Twig wrapper for PHP's preg_grep method
     *
     * @param $array
     * @param $regex
     * @param int $flags
     * @return array
     */
    public function regexFilter($array, $regex, $flags = 0) {
        return preg_grep($regex, $array, $flags);
    }

    /**
     * redirect browser from twig
     *
     * @param string $url          the url to redirect to
     * @param int $statusCode      statusCode, default 303
     */
    public function redirectFunc($url, $statusCode = 303)
    {
        header('Location: ' . $url, true, $statusCode);
        die();
    }

    /**
     * Generates an array containing a range of elements, optionally stepped
     *
     * @param int $start      Minimum number, default 0
     * @param int $end        Maximum number, default `getrandmax()`
     * @param int $step       Increment between elements in the sequence, default 1
     *
     * @return array
     */
    public function rangeFunc($start = 0, $end = 100, $step = 1)
    {
        return range($start, $end, $step);
    }

    /**
     * Check if HTTP_X_REQUESTED_WITH has been set to xmlhttprequest,
     * in which case we may unsafely assume ajax. Non critical use only.
     *
     * @return true if HTTP_X_REQUESTED_WITH exists and has been set to xmlhttprequest
     */
    public function isAjaxFunc()
    {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    /**
     * Get's the Exif data for a file
     *
     * @param $image
     * @param bool $raw
     * @return mixed
     */
    public function exifFunc($image, $raw = false)
    {
        if (isset($this->grav['exif'])) {

            /** @var UniformResourceLocator $locator */
            $locator = $this->grav['locator'];

            if ($locator->isStream($image)) {
                $image = $locator->findResource($image);
            }

            $exif_reader = $this->grav['exif']->getReader();

            if (file_exists($image) && $this->config->get('system.media.auto_metadata_exif') && $exif_reader) {

                $exif_data = $exif_reader->read($image);

                if ($exif_data) {
                    if ($raw) {
                        return $exif_data->getRawData();
                    }

                        return $exif_data->getData();
                    }
                }
            }

        return null;
    }

    /**
     * Simple function to read a file based on a filepath and output it
     *
     * @param $filepath
     * @return bool|string
     */
    public function readFileFunc($filepath)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        if ($locator->isStream($filepath)) {
            $filepath = $locator->findResource($filepath);
        }

        if (file_exists($filepath)) {
            return file_get_contents($filepath);
    }

        return false;
    }

    /**
     * Process a folder as Media and return a media object
     *
     * @param $media_dir
     * @return Media|null
     */
    public function mediaDirFunc($media_dir)
    {
        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        if ($locator->isStream($media_dir)) {
            $media_dir = $locator->findResource($media_dir);
        }

        if (file_exists($media_dir)) {
            return new Media($media_dir);
        }

        return null;
    }

    /**
     * Dump a variable to the browser
     *
     * @param $var
     */
    public function vardumpFunc($var)
    {
        var_dump($var);
    }

    /**
     * Returns a nicer more readable filesize based on bytes
     *
     * @param $bytes
     * @return string
     */
    public function niceFilesizeFunc($bytes)
    {
        if ($bytes >= 1073741824)
        {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        }
        elseif ($bytes >= 1048576)
        {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        }
        elseif ($bytes >= 1024)
        {
            $bytes = number_format($bytes / 1024, 1) . ' KB';
        }
        elseif ($bytes > 1)
        {
            $bytes = $bytes . ' bytes';
        }
        elseif ($bytes == 1)
        {
            $bytes = $bytes . ' byte';
        }
        else
        {
            $bytes = '0 bytes';
        }

        return $bytes;
    }


    /**
     * Returns a nicer more readable number
     *
     * @param int|float $n
     * @return bool|string
     */
    public function niceNumberFunc($n)
    {
        // first strip any formatting;
        $n = 0 + str_replace(',', '', $n);

        // is this a number?
        if (!is_numeric($n)) {
            return false;
        }

        // now filter it;
        if ($n > 1000000000000) {
            return round(($n/1000000000000), 2).' t';
        }
        if ($n > 1000000000) {
            return round(($n/1000000000), 2).' b';
        }
        if ($n > 1000000) {
            return round(($n/1000000), 2).' m';
        }
        if ($n > 1000) {
            return round(($n/1000), 2).' k';
        }

        return number_format($n);
    }

    /**
     * Get a theme variable
     *
     * @param $var
     * @param bool $default
     * @return string
     */
    public function themeVarFunc($var, $default = null)
    {
        $header = $this->grav['page']->header();
        $header_classes = isset($header->$var) ? $header->$var : null;
        return $header_classes ?: $this->config->get('theme.' . $var, $default);
    }

    /**
     * takes an array of classes, and if they are not set on body_classes
     * look to see if they are set in theme config
     *
     * @param $classes
     * @return string
     */
    public function bodyClassFunc($classes)
    {

        $header = $this->grav['page']->header();
        $body_classes = isset($header->body_classes) ? $header->body_classes : '';

        foreach ((array)$classes as $class) {
            if (!empty($body_classes) && Utils::contains($body_classes, $class)) {
                continue;
            }

            $val = $this->config->get('theme.' . $class, false) ? $class : false;
            $body_classes .= $val ? ' ' . $val : '';
        }

        return $body_classes;
    }

    /**
     * Look for a page header variable in an array of pages working its way through until a value is found
     *
     * @param $var
     * @param null $pages
     * @return mixed
     */
    public function pageHeaderVarFunc($var, $pages = null)
    {
        if ($pages === null) {
            $pages = $this->grav['page'];
        }

        // Make sure pages are an array
        if (!is_array($pages)) {
            $pages = array($pages);
        }

        // Loop over pages and look for header vars
        foreach ($pages as $page) {
            if (is_string($page)) {
                $page = $this->grav['pages']->find($page);
            }

            if ($page) {
                $header = $page->header();
                if (isset($header->$var)) {
                    return $header->$var;
                }
            }
        }

        return null;
    }

    /**
     * Dump/Encode data into YAML format
     *
     * @param $data
     * @return mixed
     */
    public function yamlEncodeFilter($data)
    {
        return Yaml::dump($data, 10);
    }

    /**
     * Decode/Parse data from YAML format
     *
     * @param $data
     * @return mixed
     */
    public function yamlDecodeFilter($data)
    {
        return Yaml::parse($data);
    }
}
