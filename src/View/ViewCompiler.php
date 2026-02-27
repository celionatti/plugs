<?php

declare(strict_types=1);

namespace Plugs\View;

/*
|--------------------------------------------------------------------------
| ViewCompiler Class
|--------------------------------------------------------------------------
|
| This class is responsible for compiling view templates into executable PHP code.
| It processes various directives such as conditionals, loops, includes, sections,
| and more, transforming them into valid PHP syntax.
| It also manages component extraction and slot handling for reusable UI elements.
|
| Represents a view compiler that transforms template syntax into PHP code.
|
| Compiles template syntax into executable PHP code.
| Handles directives, components, loops, conditionals, and more.
| @package Plugs\View
*/

class ViewCompiler
{
    use \Plugs\View\Compilers\CompilesComponents,
        \Plugs\View\Compilers\CompilesControlStructures,
        \Plugs\View\Compilers\CompilesEchos,
        \Plugs\View\Compilers\CompilesFormDirectives,
        \Plugs\View\Compilers\CompilesFormatDirectives,
        \Plugs\View\Compilers\CompilesLayouts;

    private array $componentData = [];
    private array $compilationCache = [];
    private array $customDirectives = [];
    private array $verbatimBlocks = [];
    private const MAX_CACHE_SIZE = 1000;

    /**
     * Whether xxh128 hashing is available (PHP 8.1+)
     */
    private static ?bool $xxhashAvailable = null;

    /**
     * Pre-compiled regex patterns for performance
     */
    private static array $patterns = [];

    /**
     * Source map tracking for debugging
     */
    private bool $sourceMapEnabled = false;
    private array $sourceMap = [];
    private int $currentLine = 0;

    /**
     * Fragment renderer for HTMX/Turbo support
     */
    private ?FragmentRenderer $fragmentRenderer = null;

    /**
     * View cache for block caching
     */
    private ?ViewCache $viewCache = null;

    /**
     * Check if source maps are enabled
     */
    public function isSourceMapEnabled(): bool
    {
        return $this->sourceMapEnabled;
    }

    /**
     * Get the current line number
     */
    public function getCurrentLine(): int
    {
        return $this->currentLine;
    }

    /**
     * Get the source map
     */
    public function getSourceMap(): array
    {
        return $this->sourceMap;
    }

    /**
     * Get the fragment renderer
     */
    public function getFragmentRenderer(): ?FragmentRenderer
    {
        return $this->fragmentRenderer;
    }

    /**
     * Get the view cache
     */
    public function getViewCache(): ?ViewCache
    {
        return $this->viewCache;
    }

    /**
     * Parent component data stack for @aware directive
     */
    private static array $parentDataStack = [];

    public function __construct()
    {
        self::initPatterns();
    }

    /**
     * Initialize pre-compiled regex patterns (called once)
     */
    private static function initPatterns(): void
    {
        if (!empty(self::$patterns)) {
            return;
        }

        self::$patterns = [
            // Echo patterns
            'escaped_echo' => '/\{\{\s*(.*?)\s*\}\}/s',
            'raw_echo' => '/\{!!\s*(.*?)\s*!!\}/s',
            'legacy_raw_echo' => '/\{\{\{\s*(.*?)\s*\}\}\}/s',

            // Comment patterns
            'comment' => '/\{\{--.*?--\}\}/s',

            // Verbatim patterns
            'verbatim' => '/@verbatim(.*?)@endverbatim/s',

            // PHP patterns
            'php_block' => '/@php\s*\n?(.*?)\n?\s*@endphp/s',
            'php_inline' => '/@php\s*\((.+?)\)/s',

            // Component patterns
            'component_self_close' => '/<([A-Z][a-zA-Z0-9]*(?:::[A-Z][a-zA-Z0-9]*)*)((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\'))*?)\/>/',
            'component_with_content' => '/<([A-Z][a-zA-Z0-9]*(?:::[A-Z][a-zA-Z0-9]*)*)((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\'))*?)>(.*?)<\/\1\s*>/s',

            // Slot patterns
            'named_slot' => '/<slot(?:\s+name=["\']([^"\']+)["\']|:([\w-]+))(.*?)>(.*?)<\/slot>/s',

            // Control structure patterns
            'if' => '/@if\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'elseif' => '/@elseif\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'unless' => '/@unless\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'isset' => '/@isset\s*\(((?:[^()]|\([^()]*\))*)\)/s',
            'empty' => '/@empty\s*\(((?:[^()]|\([^()]*\))*)\)/s',

            // Loop patterns
            'foreach' => '/@foreach\s*\((.+?)\s+as\s+(.+?)\)/s',
            'forelse' => '/@forelse\s*\((.+?)\s+as\s+(.+?)\)(.*?)@empty(.*?)@endforelse/s',
            'for' => '/@for\s*\((.+?)\)/s',
            'while' => '/@while\s*\((.+?)\)/s',

            // Section patterns
            'extends' => '/@extends\s*\([\'"](.+?)[\'"]\)/',
            'section_inline' => '/@section\s*\([\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\)/',
            'section_block' => '/@section\s*\([\'"](.+?)[\'"]\)/',
            'yield' => '/@yield\s*\([\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\)/',

            // Include patterns
            'include' => '/@include\s*\([\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]|\$\w+))?\s*\)/s',

            // New directive patterns
            'props' => '/@props\s*\((\[.+?\])\)/s',
            'fragment' => '/@fragment\s*\([\'"](.+?)[\'"]\)/s',
            'endfragment' => '/@endfragment\s*/',
            'teleport' => '/@teleport\s*\([\'"](.+?)[\'"]\)/s',
            'endteleport' => '/@endteleport\s*/',
            'cache' => '/@cache\s*\([\'"](.+?)[\'"](?:\s*,\s*(\d+))?\)/s',
            'endcache' => '/@endcache\s*/',
            'lazy' => '/@lazy\s*\([\'"](.+?)[\'"](?:\s*,\s*(\[.+?\]))?\)/s',
            'aware' => '/@aware\s*\((\[.+?\])\)/s',
            'sanitize' => '/@sanitize\s*\((.+?)\)/s',
            'entangle' => '/@entangle\s*\([\'"](.+?)[\'"]\)/s',
            'raw' => '/@raw\s*\((.+?)\)/s',

            // RBAC patterns
            'can' => '/@can\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'cannot' => '/@cannot\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'elsecan' => '/@elsecan\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'role' => '/@role\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'hasrole' => '/@hasrole\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'hasanyrole' => '/@hasanyrole\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'hasallroles' => '/@hasallroles\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s',
            'csp' => '/@csp\s*/',
            'id' => '/@id\s*\((.+?)\)/s',
            'stream' => '/@stream\s*\((.+?)\)/s',
            'error' => '/@error\s*\((.+?)\)/s',
        ];
    }

    /**
     * Enable source map generation for debugging
     */
    public function enableSourceMaps(bool $enabled = true): self
    {
        $this->sourceMapEnabled = $enabled;

        return $this;
    }

    /**
     * Set view cache instance
     */
    public function setViewCache(ViewCache $cache): self
    {
        $this->viewCache = $cache;

        return $this;
    }

    /**
     * Set fragment renderer instance
     */
    public function setFragmentRenderer(FragmentRenderer $renderer): self
    {
        $this->fragmentRenderer = $renderer;

        return $this;
    }

    /**
     * Push parent data for @aware directive
     */
    public static function pushParentData(array $data): void
    {
        self::$parentDataStack[] = $data;
    }

    /**
     * Pop parent data stack
     */
    public static function popParentData(): ?array
    {
        return array_pop(self::$parentDataStack);
    }

    /**
     * Get all parent data for @aware directive
     */
    public static function getParentData(array $keys): array
    {
        $result = [];
        foreach (array_reverse(self::$parentDataStack) as $data) {
            foreach ($keys as $key) {
                if (!isset($result[$key]) && isset($data[$key])) {
                    $result[$key] = $data[$key];
                }
            }
        }

        return $result;
    }

    public function registerCustomDirective(string $name, callable $handler): void
    {
        $this->customDirectives[$name] = $handler;
    }

    public function getCustomDirectives(): array
    {
        return array_keys($this->customDirectives);
    }

    /**
     * Fast hash using xxh128 when available, falling back to md5
     */
    public static function fastHash(string $content): string
    {
        if (self::$xxhashAvailable === null) {
            self::$xxhashAvailable = function_exists('hash') && in_array('xxh128', hash_algos(), true);
        }

        return self::$xxhashAvailable
            ? hash('xxh128', $content)
            : md5($content);
    }

    public function compile(string $content): string
    {
        $cacheKey = self::fastHash($content);

        if (isset($this->compilationCache[$cacheKey])) {
            return $this->compilationCache[$cacheKey];
        }

        // Phase 0: Line tracking preparation (only if enabled)
        if ($this->sourceMapEnabled) {
            $originalLines = explode("\n", $content);
            $contentWithLines = '';
            foreach ($originalLines as $i => $line) {
                $contentWithLines .= "<?php /** line: " . ($i + 1) . " **/ ?>" . $line . "\n";
            }
            $content = $contentWithLines;
        }

        // Phase 0.5: Tag Pre-processing (Convert ALL tags to @directives first)
        $content = $this->compileTagsToDirectives($content);

        // Phase 1: Extract components first (they have highest priority)
        $content = $this->extractComponentsWithSlots($content);

        // Phase 2: Compile all other directives in correct order
        $content = $this->compileNonComponentContent($content);

        // Phase 3: Embed compiled fingerprint for cache validation
        $fingerprint = self::fastHash($content);
        $content = "<?php /* @compiled:" . $fingerprint . " */ ?>\n" . $content;

        $this->cacheCompilation($cacheKey, $content);

        return $content;
    }

    public function getCompiledSlot(string $slotId): string
    {
        if (isset($this->componentData[$slotId])) {
            $slotContent = $this->componentData[$slotId];

            return $this->compileNonComponentContent($slotContent);
        }

        return '';
    }

    public function clearCache(): void
    {
        $this->compilationCache = [];
        $this->componentData = [];
    }

    private function extractComponentsWithSlots(string $content): string
    {
        // Regex to match attributes while ignoring '>' inside quotes
        $attrRegex = '((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\')*)*?)';

        // 1. Self-closing components: <ComponentName attr="value" /> or <Module::Component />
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*(?:::[A-Z][a-zA-Z0-9]*)*)' . $attrRegex . '\/>/s',
            function ($matches) {
                $componentName = str_replace('::', '.', $matches[1]);
                $attributes = $matches[2];

                return $this->createComponentPlaceholder($componentName, trim($attributes), '');
            },
            $content
        ) ?? $content;

        // 2. Components with content: <ComponentName>...</ComponentName>
        $content = preg_replace_callback(
            '/<([A-Z][a-zA-Z0-9]*(?:::[A-Z][a-zA-Z0-9]*)*)' . $attrRegex . '>(.*?)<\/\1\s*>/s',
            function ($matches) {
                $componentName = str_replace('::', '.', $matches[1]);
                $attributes = $matches[2];
                $slotContent = $matches[3];

                return $this->createComponentPlaceholder($componentName, trim($attributes), $slotContent);
            },
            $content
        ) ?? $content;

        return $content;
    }

    private function anyToPascalCase(string $input): string
    {
        return str_replace(['_', '-', '.', ' ', '\\'], '', ucwords($input, '_-. \\'));
    }

    /**
     * Convert kebab-case or snake_case to PascalCase
     */
    private function kebabToPascalCase(string $input): string
    {
        return $this->anyToPascalCase($input);
    }

    private function createComponentPlaceholder(string $componentName, string $attributes, string $slotContent): string
    {
        $attributesArray = $this->parseAttributes($attributes);

        // Check for 'lazy' attribute
        $isLazy = false;
        if (isset($attributesArray['lazy'])) {
            $isLazy = true;
            unset($attributesArray['lazy']);
        }

        $dataPhp = $this->buildDataArray($attributesArray);

        $slots = [];
        $defaultSlot = $slotContent;

        // Parse named slots: <slot name="header">...</slot> OR <slot:header>...</slot>
        if (str_contains($slotContent, '<slot')) {
            // 1. Shorthand syntax: <slot:header ...>...</slot[:header]>
            $slotContent = preg_replace_callback(
                '/<slot:([\w-]+)(.*?)>(.*?)<\/slot(?::\1)?>/s',
                function ($matches) use (&$slots) {
                    $name = $matches[1];
                    $slotAttrStr = trim($matches[2]);
                    $content = $matches[3];

                    $slotAttrArray = $this->parseAttributes($slotAttrStr);
                    $slotDataPhp = $this->buildDataArray($slotAttrArray);

                    $compiled = $this->compileNonComponentContent($content);
                    $slots[$name] = ['content' => $compiled, 'attributes' => $slotDataPhp];
                    return '';
                },
                $slotContent
            ) ?? $slotContent;

            // 2. Standard syntax: <slot name="header" ...>...</slot>
            $slotContent = preg_replace_callback(
                '/<slot\s+name=["\']([^"\']+)["\'](.*?)>(.*?)<\/slot>/s',
                function ($matches) use (&$slots) {
                    $name = $matches[1];
                    $slotAttrStr = trim($matches[2]);
                    $content = $matches[3];

                    $slotAttrArray = $this->parseAttributes($slotAttrStr);
                    $slotDataPhp = $this->buildDataArray($slotAttrArray);

                    $compiled = $this->compileNonComponentContent($content);
                    $slots[$name] = ['content' => $compiled, 'attributes' => $slotDataPhp];
                    return '';
                },
                $slotContent
            ) ?? $slotContent;

            $defaultSlot = $slotContent;
        }

        $dataArray = $dataPhp;

        // Add Default Slot
        if (!empty(trim($defaultSlot))) {
            $compiledSlot = $this->compileNonComponentContent($defaultSlot);
            $dataArray .= (empty($dataArray) ? '' : ', ') . sprintf("'slot' => '%s'", addslashes($compiledSlot));
        }

        // Add Named Slots
        foreach ($slots as $name => $info) {
            $dataArray .= (empty($dataArray) ? '' : ', ') . sprintf("'%s' => '%s'", addslashes($name), addslashes($info['content']));
            if (!empty($info['attributes'])) {
                $dataArray .= sprintf(", '%s_attributes' => [%s]", addslashes($name), $info['attributes']);
            }
        }

        // Inject ComponentAttributes bag
        $attributesConstruction = sprintf("new \Plugs\View\ComponentAttributes([%s])", $dataPhp);
        $dataArray .= (empty($dataArray) ? '' : ', ') . "'attributes' => " . $attributesConstruction;

        // Initial Render (Standard)
        $renderCall = sprintf(
            '<?php echo $view->renderComponent(\'%s\', [%s]); ?>',
            addslashes($componentName),
            $dataArray
        );

        // Lazy Loading Logic
        if ($isLazy) {
            // We need to serialize the attributes for the payload
            // Note: We can only securely serialize scalar values or arrays of scalars here basically.
            // Complex objects (like Models) passed as variables ($post) need special handling
            // or should be passed as IDs. For now, we assume standard data.

            // We'll generate a runtime PHP snippet to encrypt the payload
            // This ensures variables ($var) are evaluated before encryption.
            return sprintf(
                '<?php
                $lazyPayload = [
                    "component" => "%s",
                    "attributes" => [%s]
                ];
                $encryptedPayload = \Plugs\Facades\Crypt::encrypt($lazyPayload);
                ?>
                <div class="plugs-lazy-component" data-plugs-lazy-payload="<?php echo $encryptedPayload; ?>">
                    %s
                </div>',
                addslashes($componentName),
                $dataPhp, // We use $dataPhp (key => value) not $dataArray which includes slots
                isset($slots['loading']) ? $slots['loading']['content'] : '<div class="text-center p-3"><span class="spinner-border spinner-border-sm"></span> Loading...</div>'
            );
        }

        return $renderCall;
    }

    protected function compileNonComponentContent(string $content): string
    {
        // Phase 0: Tag Pre-processing (Ensures all tags in slots/fragments are converted)
        $content = $this->compileTagsToDirectives($content);

        // Phase 1: Preparation
        $content = $this->compileComments($content);
        $content = $this->compileVerbatim($content);
        $content = $this->compilePhp($content);

        // Phase 2: Structural & Layout Compilation
        $content = $this->compileSections($content);
        $content = $this->compileIncludes($content);
        $content = $this->compileStacks($content);
        $content = $this->compilePushOnce($content);

        // Phase 3: Control Flow Compilation
        $content = $this->compileConditionals($content);
        $content = $this->compileLoops($content);

        // Phase 4: Authorization & Environment Compilation
        $content = $this->compileAuthDirectives($content);
        $content = $this->compileEnvironmentDirectives($content);

        // Phase 5: Forms & UI Utils Compilation
        $content = $this->compileCsrf($content);
        $content = $this->compileMethod($content);
        $content = $this->compileFormHelpers($content);
        $content = $this->compileOnce($content);
        $content = $this->compileWait($content);

        // Phase 6: Components & UI Elements Compilation
        $content = $this->compileProps($content);
        $content = $this->compileFragment($content);
        $content = $this->compileTeleport($content);
        $content = $this->compileCacheBlocks($content);
        $content = $this->compileLazy($content);
        $content = $this->compileAware($content);
        $content = $this->compileSanitize($content);
        $content = $this->compileEntangle($content);
        $content = $this->compileActive($content);
        $content = $this->compileSvg($content);
        $content = $this->compileSkeleton($content);
        $content = $this->compileConfirm($content);
        $content = $this->compileTooltip($content);
        $content = $this->compileDump($content);
        $content = $this->compileMarkdown($content);

        // Phase 7: Formatting, Helper Directives & Echos
        $content = $this->compileHelperDirectives($content);
        $content = $this->compileReadTime($content);
        $content = $this->compileWordCount($content);
        $content = $this->compileCustomDirectives($content);
        $content = $this->compileShortAttributes($content);
        $content = $this->compileRawEchos($content);
        $content = $this->compileEscapedEchos($content);

        // Phase 8: Verbatim Restoration (Must be last)
        if (!empty($this->verbatimBlocks)) {
            foreach ($this->verbatimBlocks as $placeholder => $original) {
                $content = str_replace($placeholder, $original, $content);
            }
            $this->verbatimBlocks = [];
        }

        return $content;
    }

    /**
     * Compile @wait directive for HTMX processing
     */
    protected function compileWait(string $content): string
    {
        return preg_replace('/@wait\s*\((.+?)\)/', 'hx-trigger="wait:$1"', $content);
    }

    /**
     * Consolidate all tag-to-directive conversions.
     */
    protected function compileTagsToDirectives(string $content): string
    {
        $content = $this->compileLayoutTag($content);      // <layout>
        $content = $this->compileTagDirectives($content);   // <push>, <include>, etc.
        $content = $this->compileTagConditionals($content); // <if>, <unless>
        $content = $this->compileTagLoops($content);        // <loop>, <forelse>, <while>, <for>
        $content = $this->compileFormTags($content);        // <csrf>, <method>, <error>, etc.
        $content = $this->compileComponentTags($content);   // <fragment>, <teleport>, <auth>, etc.
        $content = $this->compileMarkdownTag($content);    // <markdown>
        return $content;
    }

    public function parseAttributes(string $attributes): array
    {
        $result = [];
        $attributes = trim($attributes);

        if (empty($attributes)) {
            return $result;
        }

        // Store expressions temporarily
        $expressionMap = [];
        $expressionCounter = 0;

        $attributes = preg_replace_callback(
            '/\{\{\s*(.+?)\s*\}\}/s',
            function ($matches) use (&$expressionMap, &$expressionCounter) {
                $placeholder = sprintf('___EXPR_%d___', $expressionCounter);
                $expressionMap[$placeholder] = trim($matches[1]);
                $expressionCounter++;

                return $placeholder;
            },
            $attributes
        );

        // Quoted attributes
        preg_match_all(
            '/([\w:.-]+)\s*=\s*(["\'])(.*?)\2/is',
            $attributes,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $key = $match[1];
            $isDynamic = false;

            // Support :attr="..." syntax
            if (str_starts_with($key, ':')) {
                $key = substr($key, 1);
                $isDynamic = true;
            }

            $value = str_replace('\\' . $match[2], $match[2], $match[3]);
            $hasExpression = $isDynamic;

            if (strpos($value, '___EXPR_') !== false) {
                $parts = preg_split('/(___EXPR_\d+___)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                $exprParts = [];

                foreach ($parts as $part) {
                    if (isset($expressionMap[$part])) {
                        $exprParts[] = "(" . $expressionMap[$part] . ")";
                    } else {
                        $exprParts[] = "'" . addslashes($part) . "'";
                    }
                }

                $value = implode(" . ", $exprParts);
                $hasExpression = true;
            } elseif ($isDynamic) {
                $hasExpression = true;
            }

            $result[$key] = [
                'value' => $value,
                'is_variable' => $hasExpression,
            ];
        }

        // Boolean attributes (flags)
        $withoutQuoted = preg_replace('/[\w:.-]+\s*=\s*(?:(["\']).*?\1|\$[\w\[\]\'\"\-\>]+)/s', '', $attributes);
        preg_match_all('/([\w:.-]+)/', $withoutQuoted, $matches);

        foreach ($matches[1] as $attr) {
            if (!isset($result[$attr]) && !empty(trim($attr)) && !preg_match('/^___EXPR_\d+___$/', $attr)) {
                $result[$attr] = [
                    'value' => 'true',
                    'is_variable' => false,
                ];
            }
        }

        return $result;
    }

    private function buildDataArray(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $info) {
            $value = $info['value'];
            $isVariable = $info['is_variable'];

            if ($isVariable) {
                $parts[] = sprintf("'%s' => %s", addslashes((string) $key), $value);
            } else {
                $val = ($value === 'true' || $value === 'false') ? $value : "'" . addslashes((string) $value) . "'";
                $parts[] = sprintf("'%s' => %s", addslashes((string) $key), $val);
            }
        }

        return implode(', ', $parts);
    }

    private function cacheCompilation(string $key, string $content): void
    {
        if (count($this->compilationCache) >= self::MAX_CACHE_SIZE) {
            array_shift($this->compilationCache);
        }
        $this->compilationCache[$key] = $content;
    }
}
