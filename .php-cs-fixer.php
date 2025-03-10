<?php declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->exclude([
        '.build/',
        '.github/',
    ])
    ->in(__DIR__);

$config = new Config();

$config->setFinder($finder);
$config->setRules([
    // Basic PER Coding Style 2.0 ruleset plus our overrides for it, see https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/ruleSets/PER-CS2.0.rst
    '@PER-CS2.0' => true, // https://www.php-fig.org/per/coding-style/}
    'new_with_parentheses' => ['anonymous_class' => true], // It will be changed in PHP-CS-Fixer v4.0 (but we want to enforce it), see https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/pull/8148
    // overrides for PER-CS2.0
    'concat_space' => ['spacing' => 'none'], // make strings shorter "'hello' . $name . '!'" => "'hello'.$name.'!'"
    'blank_line_after_opening_tag' => false, // it makes "<?php declare(strict_types=1);" multiline (and more verbose)

    // Additional rules on the top of PER-CS2
    // Please keep these rules alphabetically
    'align_multiline_comment' => ['comment_type' => 'phpdocs_only'],
    'array_indentation' => true,
    'assign_null_coalescing_to_coalesce_equal' => true,
    'binary_operator_spaces' => ['default' => 'single_space'],
    'cast_spaces' => ['space' => 'single'],
    'class_attributes_separation' => ['elements' => ['method' => 'one']],
    'declare_strict_types' => true,
    'explicit_string_variable' => true,
    'final_public_method_for_abstract_class' => true,
    'general_phpdoc_annotation_remove' => [
        'annotations' => [
            'access',
            'author',
            'category',
            'copyright',
            'created',
            'license',
            'link',
            'package',
            'since',
            'subpackage',
        ],
    ],
    'modernize_types_casting' => true,
    'mb_str_functions' => true,
    'no_alias_functions' => true,
    'no_binary_string' => true,
    'no_empty_comment' => true,
    'no_empty_phpdoc' => true,
    'no_empty_statement' => true,
    'no_extra_blank_lines' => ['tokens' => ['extra', 'curly_brace_block']],
    'no_homoglyph_names' => true,
    'no_leading_namespace_whitespace' => true,
    'no_mixed_echo_print' => true,
    'no_short_bool_cast' => true,
    'no_singleline_whitespace_before_semicolons' => true,
    'no_spaces_around_offset' => true,
    'no_trailing_comma_in_singleline' => false, // it's a good marker that there are more elements in an array
    'no_unneeded_braces' => true,
    'no_unneeded_control_parentheses' => true,
    'no_unneeded_final_method' => true,
    'no_unreachable_default_argument_value' => true,
    'no_unused_imports' => true,
    'no_useless_concat_operator' => true,
    'no_useless_return' => true,
    'no_whitespace_before_comma_in_array' => true,
    'normalize_index_brace' => true,
    'nullable_type_declaration' => ['syntax' => 'question_mark'],
    'object_operator_without_whitespace' => true,
    'ordered_class_elements' => [
        'order' => [
            'use_trait',
            'constant',
            'case', // for enums only
            'property',
            'method',
        ]
    ],
    'php_unit_attributes' => true,
    'php_unit_construct' => true,
    'php_unit_dedicate_assert' => ['target' => 'newest'],
    'php_unit_expectation' => true,
    'php_unit_fqcn_annotation' => true,
    'php_unit_method_casing' => ['case' => 'snake_case'],
    'php_unit_no_expectation_annotation' => true,
    'php_unit_set_up_tear_down_visibility' => true,
    'php_unit_strict' => true,
    'php_unit_test_annotation' => ['style' => 'annotation'],
    'phpdoc_align' => ['align' => 'left'],
    'phpdoc_array_type' => true,
    'phpdoc_indent' => true,
    'phpdoc_line_span' => ['const' => 'single', 'property' => 'single', 'method' => 'single'],
    'phpdoc_param_order' => true,
    'phpdoc_scalar' => true,
    'phpdoc_single_line_var_spacing' => true,
    'phpdoc_tag_casing' => true,
    'phpdoc_types' => true,
    'protected_to_private' => true,
    'psr_autoloading' => true,
    'self_accessor' => true,
    'self_static_accessor' => true,
    'single_line_comment_spacing' => true,
    'single_line_comment_style' => ['comment_types' => ['asterisk', 'hash']],
    'space_after_semicolon' => true,
    'standardize_not_equals' => true,
    'strict_param' => true,
    'ternary_to_null_coalescing' => true,
    'trim_array_spaces' => true,
    'trailing_comma_in_multiline' => true,
    'type_declaration_spaces' => true,
    'types_spaces' => ['space' => 'single'],
    'whitespace_after_comma_in_array' => true,
    'yoda_style' => ['equal' => false, 'identical' => false, 'less_and_greater' => false],
]);

return $config;
