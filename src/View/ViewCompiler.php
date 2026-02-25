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

        // Phase 0.5: Compile layout tags before component extraction
        $content = $this->compileLayoutTag($content);

        // Phase 0.6: Compile tag-based directives into @ equivalents
        $content = $this->compileTagDirectives($content);

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

    private function compileNonComponentContent(string $content): string
    {
        // Helper to safely apply preg-based compilation
        $safeCompile = fn(callable $method, string $c) => $method($c) ?? $c;

        // 1. Comments first (remove them entirely)
        $content = $safeCompile([$this, 'compileComments'], $content);

        // 2. Verbatim blocks (protect from compilation)
        $content = $safeCompile([$this, 'compileVerbatim'], $content);

        // 3. PHP blocks (before template syntax to avoid conflicts)
        $content = $safeCompile([$this, 'compilePhp'], $content);

        // 3b. HTML Tag equivalents for directives
        $content = $safeCompile([$this, 'compileTagDirectives'], $content);

        // 4. Control structures (conditionals and loops)
        $content = $safeCompile([$this, 'compileConditionals'], $content);
        $content = $safeCompile([$this, 'compileLoops'], $content);

        // 4b. Auth & environment conditionals
        $content = $safeCompile([$this, 'compileAuthDirectives'], $content);
        $content = $safeCompile([$this, 'compileEnvironmentDirectives'], $content);

        // 5. Custom directives (user-defined)
        $content = $safeCompile([$this, 'compileCustomDirectives'], $content);

        // 6. Template inheritance and sections
        $content = $safeCompile([$this, 'compileSections'], $content);
        $content = $safeCompile([$this, 'compileIncludes'], $content);

        // 7. Stacks and assets
        $content = $safeCompile([$this, 'compileStacks'], $content);
        $content = $safeCompile([$this, 'compilePushOnce'], $content);
        $content = $safeCompile([$this, 'compileAssets'], $content);
        $content = $safeCompile([$this, 'compileOnce'], $content);

        // 8. Form helpers
        $content = $safeCompile([$this, 'compileCsrf'], $content);
        $content = $safeCompile([$this, 'compileMethod'], $content);
        $content = $safeCompile([$this, 'compileFormHelpers'], $content);

        // 8b. Security directives
        $content = $safeCompile([$this, 'compileNonce'], $content);
        $content = $safeCompile([$this, 'compileHoneypot'], $content);
        $content = $safeCompile([$this, 'compileCsp'], $content);
        $content = $safeCompile([$this, 'compileId'], $content);

        // 8c. RBAC directives
        $content = $safeCompile([$this, 'compileCan'], $content);
        $content = $safeCompile([$this, 'compileRole'], $content);

        // 9. NEW DIRECTIVES - Component & HTMX support
        $content = $safeCompile([$this, 'compileProps'], $content);
        $content = $safeCompile([$this, 'compileFragment'], $content);
        $content = $safeCompile([$this, 'compileTeleport'], $content);
        $content = $safeCompile([$this, 'compileCacheBlocks'], $content);
        $content = $safeCompile([$this, 'compileLazy'], $content);
        $content = $safeCompile([$this, 'compileAware'], $content);
        $content = $safeCompile([$this, 'compileSanitize'], $content);
        $content = $safeCompile([$this, 'compileEntangle'], $content);
        $content = $safeCompile([$this, 'compileRawDirective'], $content);
        $content = $safeCompile([$this, 'compileStream'], $content);

        // 10. Utilities
        $content = $safeCompile([$this, 'compileInject'], $content);
        $content = $safeCompile([$this, 'compileJson'], $content);
        $content = $safeCompile([$this, 'compileHelperDirectives'], $content);
        $content = $safeCompile([$this, 'compileOld'], $content);
        $content = $safeCompile([$this, 'compileFlashMessages'], $content);
        $content = $safeCompile([$this, 'compileErrorDirectives'], $content);
        $content = $safeCompile([$this, 'compileError'], $content);
        $content = $safeCompile([$this, 'compileEndError'], $content);
        $content = $safeCompile([$this, 'compileReadTime'], $content);
        $content = $safeCompile([$this, 'compileWordCount'], $content);
        $content = $safeCompile([$this, 'compileAutofocus'], $content);

        // 10b. UI utilities & content directives
        $content = $safeCompile([$this, 'compileActive'], $content);
        $content = $safeCompile([$this, 'compileSvg'], $content);
        $content = $safeCompile([$this, 'compileSkeleton'], $content);
        $content = $safeCompile([$this, 'compileConfirm'], $content);
        $content = $safeCompile([$this, 'compileTooltip'], $content);
        $content = $safeCompile([$this, 'compileDump'], $content);
        $content = $safeCompile([$this, 'compileMarkdown'], $content);
        $content = $safeCompile([$this, 'compileMarkdownTag'], $content);

        // 10c. Short-attribute binding on regular tags (after components)
        $content = $safeCompile([$this, 'compileShortAttributes'], $content);

        // 11. Echo statements LAST (after all directives)
        $content = $safeCompile([$this, 'compileRawEchos'], $content);
        $content = $safeCompile([$this, 'compileTagConditionals'], $content);
        $content = $safeCompile([$this, 'compileTagLoops'], $content);
        $content = $safeCompile([$this, 'compileEscapedEchos'], $content);

        // Restore verbatim blocks LAST
        if (!empty($this->verbatimBlocks)) {
            foreach ($this->verbatimBlocks as $placeholder => $original) {
                $content = str_replace($placeholder, $original, $content);
            }
            $this->verbatimBlocks = [];
        }

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
            '/\{\{\{?\s*(.+?)\s*\}\}\}?/s',
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
            '/([\w:.-]+)\s*=\s*(["\'])(.*?)\2/s',
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
                'quoted' => true,
                'is_variable' => $hasExpression,
            ];
        }

        // Unquoted variables
        preg_match_all('/([\w:.-]+)\s*=\s*(\$[\w\[\]\'\"\-\>]+)(?=\s|$|\/)/s', $attributes, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!isset($result[$match[1]])) {
                $result[$match[1]] = [
                    'value' => $match[2],
                    'quoted' => false,
                    'is_variable' => true,
                ];
            }
        }

        // Boolean attributes (flags)
        $withoutQuoted = preg_replace('/[\w:.-]+\s*=\s*(?:(["\']).*?\1|\$[\w\[\]\'\"\-\>]+)/s', '', $attributes);
        preg_match_all('/([\w:.-]+)/', $withoutQuoted, $matches);

        foreach ($matches[1] as $attr) {
            if (!isset($result[$attr]) && !empty(trim($attr)) && !preg_match('/^___EXPR_\d+___$/', $attr)) {
                $result[$attr] = [
                    'value' => 'true',
                    'quoted' => false,
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

    private function compileComments(string $content): string
    {
        // Simple comments: {{-- comment --}}
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);

        // HTML-style hidden comments: <!--# comment #-->
        return preg_replace('/<!--#.*?#-->/s', '', $content);
    }

    /**
     * Compile short-attribute binding: :class="$active ? 'a' : 'b'"
     */
    private function compileShortAttributes(string $content): string
    {
        // Matches :attr="expression" in any tag, ensuring it's not preceded by a space or start of line
        // We use a negative lookbehind to avoid matching components if possible, 
        // but actually components should handle their own attributes.
        // This is for regular HTML tags.
        return preg_replace_callback(
            '/\s:([\w-]+)=((["\'])(.*?)\3)/s',
            function ($matches) {
                $attr = $matches[1];
                $expression = $matches[4];

                return sprintf(' %s="<?php echo e(%s); ?>"', $attr, $expression);
            },
            $content
        );
    }

    private function compileVerbatim(string $content): string
    {
        // Store verbatim blocks
        $verbatimBlocks = [];
        $content = preg_replace_callback(
            '/@verbatim(.*?)@endverbatim/s',
            function ($matches) use (&$verbatimBlocks) {
                $placeholder = '___VERBATIM_' . md5($matches[1]) . '___';
                $verbatimBlocks[$placeholder] = $matches[1];

                return $placeholder;
            },
            $content
        );

        // Store for later restoration
        $this->verbatimBlocks = $verbatimBlocks;

        return $content;
    }

    private function compilePhp(string $content): string
    {
        // Multi-line PHP blocks: @php ... @endphp
        $content = preg_replace_callback(
            '/@php\s*\n?(.*?)\n?\s*@endphp/s',
            function ($matches) {
                $code = trim($matches[1]);

                return "<?php {$code} ?>";
            },
            $content
        );

        // Inline PHP: @php($expression)
        $content = preg_replace('/@php\s*\((.+?)\)/s', '<?php $1; ?>', $content);

        return $content;
    }

    private function compileRawEchos(string $content): string
    {
        // 1. Raw output: {!! $var !!}
        $content = preg_replace_callback(self::$patterns['raw_echo'], function ($matches) {
            $expr = trim($matches[1]);
            if ($expr === '')
                return '';
            return "<?php echo {$expr}; ?>";
        }, $content);

        // 2. Legacy Raw output: {{{ $var }}}
        return preg_replace_callback(self::$patterns['legacy_raw_echo'], function ($matches) {
            $expr = trim($matches[1]);
            if ($expr === '')
                return '';
            return "<?php echo {$expr}; ?>";
        }, $content);
    }

    private function compileEscapedEchos(string $content): string
    {
        /**
         * Context-Aware Auto-Detection Logic
         * 1. Script Context: Inside <script> tags use js()
         * 2. Attribute Context: Inside HTML attributes use attr()
         * 3. Text Context: Default behavior use e()
         */

        // 1. Handle <script> blocks (highest priority)
        $content = preg_replace_callback('/(<\s*script\b[^>]*>)(.*?)(<\/\s*script\s*>)/is', function ($matches) {
            $tagOpen = $matches[1];
            $scriptBody = $matches[2];
            $tagClose = $matches[3];

            $scriptBody = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', function ($eMatches) {
                $expr = trim($eMatches[1]);
                if ($expr === '')
                    return '';
                if (preg_match('/^(js|json|attr|e|u|query|json_encode)\b\s*\(/', $expr)) {
                    return "<?php echo {$expr}; ?>";
                }
                return "<?php echo js({$expr}); ?>";
            }, $scriptBody);

            return $tagOpen . $scriptBody . $tagClose;
        }, $content);

        // 2. Handle HTML attributes
        $content = preg_replace_callback('/(<[\w:-]+\s+)([^>]+)(>)/is', function ($tagMatches) {
            $tagStart = $tagMatches[1];
            $attributes = $tagMatches[2];
            $tagEnd = $tagMatches[3];

            if (!str_contains($attributes, '{{')) {
                return $tagMatches[0];
            }

            // Replace all attributes that contain {{ }}
            $newAttributes = preg_replace_callback('/([\w:-]+\s*=\s*(["\']))(.*?)\2/is', function ($attrMatches) {
                $attrFull = $attrMatches[0];
                $attrStart = $attrMatches[1];
                $quote = $attrMatches[2];
                $attrValue = $attrMatches[3];

                if (str_contains($attrValue, '{{')) {
                    $attrName = strtolower(trim(explode('=', $attrStart)[0]));
                    $isUrlAttr = in_array($attrName, ['href', 'src', 'formaction', 'poster', 'action', 'data', 'background']);

                    $newAttrValue = preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', function ($eMatches) use ($isUrlAttr) {
                        $expr = trim($eMatches[1]);
                        if ($expr === '')
                            return '';

                        // Respect explicit helpers: u, query, route, etc.
                        if (preg_match('/^(attr|u|query|route|e|js|json|json_encode|safeUrl)\b\s*\(/', $expr)) {
                            return "<?php echo {$expr}; ?>";
                        }

                        // Auto-detect: safeUrl for links/assets, attr for others
                        return $isUrlAttr ? "<?php echo safeUrl({$expr}); ?>" : "<?php echo attr({$expr}); ?>";
                    }, $attrValue);

                    return $attrStart . $newAttrValue . $quote;
                }

                return $attrFull;
            }, $attributes);

            return $tagStart . $newAttributes . $tagEnd;
        }, $content);

        // 3. Default: HTML text context
        return preg_replace_callback('/\{\{\s*(.*?)\s*\}\}/s', function ($matches) {
            $expr = trim($matches[1]);
            if ($expr === '')
                return '';
            if (preg_match('/^(e|attr|u|query|route|js|json|json_encode)\b\s*\(/', $expr)) {
                return "<?php echo {$expr}; ?>";
            }
            return "<?php echo e({$expr}); ?>";
        }, $content);
    }

    /**
     * Compile tag-based conditionals: <if :condition="...">
     */
    private function compileTagConditionals(string $content): string
    {
        // <if :condition="$user">
        $content = preg_replace_callback('/<if\s+:condition=["\'](.+?)["\']\s*>/s', function ($matches) {
            return "<?php if ({$matches[1]}): ?>";
        }, $content);

        // <elseif :condition="...">
        $content = preg_replace_callback('/<elseif\s+:condition=["\'](.+?)["\']\s*\/?>/s', function ($matches) {
            return "<?php elseif ({$matches[1]}): ?>";
        }, $content);

        // <else />
        $content = preg_replace('/<else\s*\/?>/s', '<?php else: ?>', $content);

        // </if>
        $content = preg_replace('/<\/if\s*>/s', '<?php endif; ?>', $content);

        // <unless :condition="...">
        $content = preg_replace_callback('/<unless\s+:condition=["\'](.+?)["\']\s*>/s', function ($matches) {
            return "<?php if (!({$matches[1]})): ?>";
        }, $content);

        // </unless>
        $content = preg_replace('/<\/unless\s*>/s', '<?php endif; ?>', $content);

        return $content;
    }

    /**
     * Compile tag-based loops: <loop :items="..." as="...">
     */
    private function compileTagLoops(string $content): string
    {
        // <loop :items="$users" as="$user">
        $content = preg_replace_callback('/<loop\s+:items=["\'](.+?)["\']\s+as=["\'](.+?)["\']\s*>/s', function ($matches) {
            $array = trim($matches[1]);
            $iteration = trim($matches[2]);
            $checkIsset = preg_match('/^\$[\w]+$/', $array) ? "isset($array) && " : '';

            $initLoop = '$__loop_parent = $loop ?? null; $loop = new \Plugs\View\Loop(' . $array . ', $__loop_parent, ($__loop_parent->depth ?? 0) + 1);';

            return sprintf(
                '<?php if(%sis_iterable(%s)): %s foreach (%s as %s): ?>',
                $checkIsset,
                $array,
                $initLoop,
                $array,
                $iteration
            );
        }, $content);

        // </loop>
        $content = preg_replace('/<\/loop\s*>/s', '<?php $loop->tick(); endforeach; $loop = $__loop_parent; endif; ?>', $content);

        // <while :condition="...">
        $content = preg_replace_callback('/<while\s+:condition=["\'](.+?)["\']\s*>/s', function ($matches) {
            return "<?php while ({$matches[1]}): ?>";
        }, $content);

        // </while>
        $content = preg_replace('/<\/while\s*>/s', '<?php endwhile; ?>', $content);

        // <for :init="..." :condition="..." :step="...">
        $content = preg_replace_callback('/<for\s+:init=["\'](.+?)["\']\s+:condition=["\'](.+?)["\']\s+:step=["\'](.+?)["\']\s*>/s', function ($matches) {
            return "<?php for ({$matches[1]}; {$matches[2]}; {$matches[3]}): ?>";
        }, $content);

        // </for>
        $content = preg_replace('/<\/for\s*>/s', '<?php endfor; ?>', $content);

        return $content;
    }

    /**
     * Compile tag-based layout inheritance: <layout name="..."> content </layout>
     */
    private function compileLayoutTag(string $content): string
    {
        return preg_replace_callback('/<layout\s+name=["\'](.+?)["\']\s*>(.*?)<\/layout\s*>/is', function ($matches) {
            $layoutName = $matches[1];
            $body = $matches[2];

            $sections = [];
            $defaultContent = [];

            // 1. Parse <slot name="..."> content </slot>
            $body = preg_replace_callback('/<slot\s+name=["\'](.+?)["\']\s*>(.*?)<\/slot>/s', function ($sMatches) use (&$sections) {
                $sections[$sMatches[1]] = trim($sMatches[2]);
                return '';
            }, $body);

            // 2. Parse <slot:name> content </slot:name> (V5 shorthand syntax)
            $body = preg_replace_callback('/<slot:([\w:-]+)\s*>(.*?)<\/slot(?::\1)?>/s', function ($sMatches) use (&$sections) {
                $sections[$sMatches[1]] = trim($sMatches[2]);
                return '';
            }, $body);

            // 3. Anything left goes to @section('content')
            $defaultContent = trim($body);

            $result = "@extends('{$layoutName}')\n\n";

            foreach ($sections as $name => $content) {
                $result .= "@section('{$name}')\n{$content}\n@endsection\n\n";
            }

            if (!empty($defaultContent)) {
                if (isset($sections['content'])) {
                    // If content slot is already defined, unrelated content (push/stack/etc) 
                    // should be appended raw, not wrapped in a duplicate content section.
                    $result .= $defaultContent;
                } else {
                    $result .= "@section('content')\n{$defaultContent}\n@endsection";
                }
            }

            return $result;
        }, $content);
    }

    /**
     * Compile tag-based directives into their @ equivalents.
     * This runs early so the main compiler can process the resulting @ directives.
     *
     * Supported tags:
     *   <push:name>...</push:name>       → @push('name')...@endpush
     *   <stack:name />                   → @stack('name')
     *   <yield:name />                   → @yield('name')
     *   <yield:name default=".." />      → @yield('name', '...')
     *   <csrf />                         → @csrf
     *   <include view=".." />            → @include('...')
     *   <fragment name="..">...</fragment>   → @fragment('..')...@endfragment
     *   <teleport to="..">...</teleport>    → @teleport('..')...@endteleport
     *   <forelse :items=".." as="..">    → @forelse(.. as ..)
     *   <empty />                        → @empty
     *   </forelse>                       → @endforelse
     *   <auth>...</auth>                 → @auth...@endauth
     *   <guest>...</guest>               → @guest...@endguest
     *   <skeletonStyles />               → @skeletonStyles
     */
    private function compileTagDirectives(string $content): string
    {
        // 1. <push:name> ... </push:name>
        $content = preg_replace_callback('/<push:([\w-]+)\s*>/s', function ($m) {
            return "@push('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<\/push:[\w-]+\s*>/s', '@endpush', $content);

        // 1b. <pushOnce:stack key="..."> ... </pushOnce:stack>
        $content = preg_replace_callback('/<pushOnce:([\w-]+)\s+key=["\'](.+?)["\']\s*>(.*?)<\/pushOnce:[\w-]+\s*>/si', function ($m) {
            $stack = $m[1];
            $key = $m[2];
            $inner = $m[3];
            return "@pushOnce('{$key}', '{$stack}'){$inner}@endPushOnce";
        }, $content);


        // 2. <prepend:name> ... </prepend:name>
        $content = preg_replace_callback('/<prepend:([\w-]+)\s*>/s', function ($m) {
            return "@prepend('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<\/prepend:[\w-]+\s*>/s', '@endprepend', $content);

        // 3. <stack:name /> — self-closing, renders the stack
        $content = preg_replace_callback('/<stack:([\w-]+)\s*\/>/s', function ($m) {
            return "@stack('{$m[1]}')";
        }, $content);

        // 4. <yield:name default="..." /> or <yield:name />
        $content = preg_replace_callback('/<yield:([\w-]+)\s+default=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@yield('{$m[1]}', '{$m[2]}')";
        }, $content);
        $content = preg_replace_callback('/<yield:([\w-]+)\s*\/>/s', function ($m) {
            return "@yield('{$m[1]}')";
        }, $content);

        // 5. <csrf /> — self-closing
        $content = preg_replace('/<csrf\s*\/>/s', '@csrf', $content);

        // 6. <include view="..." :data="[...]" /> or <include view="..." />
        $content = preg_replace_callback('/<include\s+view=["\'](.+?)["\']\s+:data=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@include('{$m[1]}', {$m[2]})";
        }, $content);
        $content = preg_replace_callback('/<include\s+view=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@include('{$m[1]}')";
        }, $content);

        // 7. <fragment name="..."> ... </fragment>
        $content = preg_replace_callback('/<fragment\s+name=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@fragment('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<\/fragment\s*>/s', '@endfragment', $content);

        // 8. <teleport to="..."> ... </teleport>
        $content = preg_replace_callback('/<teleport\s+to=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@teleport('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<\/teleport\s*>/s', '@endteleport', $content);

        // 9. <forelse :items="..." as="..."> ... <empty /> ... </forelse>
        $content = preg_replace_callback('/<forelse\s+:items=["\'](.+?)["\']\s+as=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@forelse({$m[1]} as {$m[2]})";
        }, $content);
        $content = preg_replace('/<empty\s*\/>/s', '@empty', $content);
        $content = preg_replace('/<\/forelse\s*>/s', '@endforelse', $content);

        // 10. <auth> ... </auth>  and  <auth guard="..."> ... </auth>
        $content = preg_replace_callback('/<auth\s+guard=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@auth('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<auth\s*>/s', '@auth', $content);
        $content = preg_replace('/<\/auth\s*>/s', '@endauth', $content);

        // 11. <guest> ... </guest>  and  <guest guard="..."> ... </guest>
        $content = preg_replace_callback('/<guest\s+guard=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@guest('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<guest\s*>/s', '@guest', $content);
        $content = preg_replace('/<\/guest\s*>/s', '@endguest', $content);

        // 12. <skeletonStyles /> — self-closing
        $content = preg_replace('/<skeletonStyles\s*\/>/s', '@skeletonStyles', $content);

        // 13. <method type="PUT" /> — self-closing
        $content = preg_replace_callback('/<method\s+type=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@method('{$m[1]}')";
        }, $content);

        // 14. <skeleton :code="..." /> — self-closing (for fluent builder)
        $content = preg_replace_callback('/<skeleton\s+:code=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@skeleton({$m[1]})";
        }, $content);

        // 15. <cache key="..." ttl="..."> ... </cache>
        $content = preg_replace_callback('/<cache\s+key=["\'](.+?)["\']\s+(?:ttl=["\'](.+?)["\']\s*)?>/s', function ($m) {
            $key = $m[1];
            $ttl = $m[2] ?? 'null';
            if ($ttl !== 'null') {
                return "@cache('{$key}', {$ttl})";
            }
            return "@cache('{$key}')";
        }, $content);
        $content = preg_replace('/<\/cache\s*>/s', '@endcache', $content);
        // 16. <if :condition="..."> ... <elseif :condition="..."> ... <else /> ... </if>
        $content = preg_replace_callback('/<if\s+:condition=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@if({$m[1]})";
        }, $content);
        $content = preg_replace_callback('/<elseif\s+:condition=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@elseif({$m[1]})";
        }, $content);
        $content = preg_replace('/<else\s*\/>/s', '@else', $content);
        $content = preg_replace('/<\/if\s*>/s', '@endif', $content);

        // 17. <loop :items="..." :as="..."> ... </loop>
        $content = preg_replace_callback('/<loop\s+:items=["\'](.+?)["\']\s+(?::)?as=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@foreach({$m[1]} as {$m[2]})";
        }, $content);
        $content = preg_replace('/<\/loop\s*>/s', '@endforeach', $content);

        $content = preg_replace('/<\/loop\s*>/s', '@endforeach', $content);

        // 18. <error field="..."> ... </error>
        $content = preg_replace_callback('/<error\s+field=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@error('{$m[1]}')";
        }, $content);
        $content = preg_replace('/<\/error\s*>/s', '@enderror', $content);

        // 19. <class :map="..." />
        // Compiles to @class(...)
        $content = preg_replace_callback('/<class\s+:map=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@class({$m[1]})";
        }, $content);

        // 20. RBAC Tags
        // <can :ability="..."> ... </can>
        $content = preg_replace_callback('/<can\s+:ability=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@can({$m[1]})";
        }, $content);
        $content = preg_replace('/<\/can\s*>/s', '@endcan', $content);

        // <cannot :ability="..."> ... </cannot>
        $content = preg_replace_callback('/<cannot\s+:ability=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@cannot({$m[1]})";
        }, $content);
        $content = preg_replace('/<\/cannot\s*>/s', '@endcannot', $content);

        // <elsecan :ability="...">
        $content = preg_replace_callback('/<elsecan\s+:ability=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@elsecan({$m[1]})";
        }, $content);

        // <role :name="..."> ... </role>
        $content = preg_replace_callback('/<role\s+:name=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@role({$m[1]})";
        }, $content);
        $content = preg_replace('/<\/role\s*>/s', '@endrole', $content);

        // <hasrole :name="..."> ... </hasrole>
        $content = preg_replace_callback('/<hasrole\s+:name=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@hasrole({$m[1]})";
        }, $content);
        $content = preg_replace('/<\/hasrole\s*>/s', '@endhasrole', $content);

        // <hasanyrole :roles="..."> ... </hasanyrole>
        $content = preg_replace_callback('/<hasanyrole\s+:roles=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@hasanyrole({$m[1]})";
        }, $content);
        $content = preg_replace('/<\/hasanyrole\s*>/s', '@endhasanyrole', $content);

        // <hasallroles :roles="..."> ... </hasallroles>
        $content = preg_replace_callback('/<hasallroles\s+:roles=["\'](.+?)["\']\s*>/s', function ($m) {
            return "@hasallroles({$m[1]})";
        }, $content);
        $content = preg_replace('/<\/hasallroles\s*>/s', '@endhasallroles', $content);

        // 21. <vite entry="..." /> or <vite :entries="..." />
        $content = preg_replace_callback('/<vite\s+:entries=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@vite({$m[1]})";
        }, $content);
        $content = preg_replace_callback('/<vite\s+entry=["\'](.+?)["\']\s*\/>/s', function ($m) {
            return "@vite('{$m[1]}')";
        }, $content);

        // 22. <once [key="..."]> ... </once>
        $content = preg_replace_callback('/<once(?:\s+key=["\'](.+?)["\'])?\s*>(.*?)<\/once>/s', function ($m) {
            $key = !empty($m[1]) ? $m[1] : md5($m[2]);
            $id = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
            return "<?php if (!isset(\$__once_{$id})): \$__once_{$id} = true; ?>{$m[2]}<?php endif; ?>";
        }, $content);

        // 23. <csp />
        $content = preg_replace('/<csp\s*\/?>/i', '<?php echo \'<meta http-equiv="Content-Security-Policy" content="default-src \\\'self\\\'; script-src \\\'self\\\' \\\'nonce-\' . ($__cspNonce ?? "") . \'\\\'; style-src \\\'self\\\' \\\'unsafe-inline\\\'; img-src \\\'self\\\' data:;">\'; ?>', $content);

        // 24. <id value="..." /> or <id :value="..." />
        $content = preg_replace_callback('/<id\s+(?::?value)=["\'](.+?)["\']\s*\/?>/i', function ($m) {
            return "<?php echo \Plugs\View\Escaper::id({$m[1]}); ?>";
        }, $content);

        // 25. <stream view="..." [:data="..."] />
        $content = preg_replace_callback('/<stream\s+view=["\'](.+?)["\'](?:\s+(?::?data)=["\'](.+?)["\'])?\s*\/?>/i', function ($m) {
            $view = $m[1];
            $data = $m[2] ?? '$__data ?? []';
            return "<?php \$view->renderToStream('{$view}', {$data}); ?>";
        }, $content);

        // 26. <css href="..." /> — self-closing, with optional attributes
        $content = preg_replace_callback('/<css\s+href=["\'](.+?)["\']([^>]*?)\s*\/>/si', function ($m) {
            $href = $m[1];
            $extraAttrs = trim($m[2]);
            if ($extraAttrs) {
                // Build associative array from HTML attributes
                $attrs = [];
                preg_match_all('/([\w-]+)(?:=["\'](.+?)["\'])?/', $extraAttrs, $attrMatches, PREG_SET_ORDER);
                foreach ($attrMatches as $attr) {
                    $key = $attr[1];
                    $val = $attr[2] ?? true;
                    $attrs[] = "'" . addslashes($key) . "' => " . (is_bool($val) ? 'true' : "'" . addslashes($val) . "'");
                }
                return "@css('{$href}', [" . implode(', ', $attrs) . "])";
            }
            return "@css('{$href}')";
        }, $content);

        // 27. <js src="..." /> — self-closing, with optional attributes (defer, async, type)
        $content = preg_replace_callback('/<js\s+src=["\'](.+?)["\']([^>]*?)\s*\/>/si', function ($m) {
            $src = $m[1];
            $extraAttrs = trim($m[2]);
            if ($extraAttrs) {
                $attrs = [];
                preg_match_all('/([\w-]+)(?:=["\'](.+?)["\'])?/', $extraAttrs, $attrMatches, PREG_SET_ORDER);
                foreach ($attrMatches as $attr) {
                    $key = $attr[1];
                    $val = $attr[2] ?? true;
                    $attrs[] = "'" . addslashes($key) . "' => " . (is_bool($val) ? 'true' : "'" . addslashes($val) . "'");
                }
                return "@js('{$src}', [" . implode(', ', $attrs) . "])";
            }
            return "@js('{$src}')";
        }, $content);

        // 28. <checked :when="$condition" />
        $content = preg_replace_callback('/<checked\s+(?::when)=["\'](.+?)["\']\s*\/?>/i', function ($m) {
            return "@checked({$m[1]})";
        }, $content);

        // 29. <selected :when="$condition" />
        $content = preg_replace_callback('/<selected\s+(?::when)=["\'](.+?)["\']\s*\/?>/i', function ($m) {
            return "@selected({$m[1]})";
        }, $content);

        // 30. <disabled :when="$condition" />
        $content = preg_replace_callback('/<disabled\s+(?::when)=["\'](.+?)["\']\s*\/?>/i', function ($m) {
            return "@disabled({$m[1]})";
        }, $content);

        // 31. <readonly :when="$condition" />
        $content = preg_replace_callback('/<readonly\s+(?::when)=["\'](.+?)["\']\s*\/?>/i', function ($m) {
            return "@readonly({$m[1]})";
        }, $content);

        // 32. <env :is="'production'">...</env>
        $content = preg_replace_callback('/<env\s+(?::is)=["\'](.+?)["\']>(.*?)<\/env>/si', function ($m) {
            return "@env({$m[1]}){$m[2]}@endenv";
        }, $content);

        // 33. <production>...</production>
        $content = preg_replace_callback('/<production>(.*?)<\/production>/si', function ($m) {
            return "@production{$m[1]}@endproduction";
        }, $content);

        // 34. <class :map="..." />
        $content = preg_replace_callback('/<class\s+(?::map)=["\'](.+?)[\"\\\']\s*\/?>/i', function ($m) {
            return "@class({$m[1]})";
        }, $content);

        // 35. <style :map="..." />
        $content = preg_replace_callback('/<style\s+(?::map)=["\'](.+?)[\"\\\']\s*\/?>/i', function ($m) {
            return "@style({$m[1]})";
        }, $content);

        return $content;
    }

    private function compileConditionals(string $content): string
    {
        // Improved regex to handle nested parentheses (like @if(isset($var)))
        $balanced = '\((?:[^()]|(?R))*\)';

        // @if
        $content = preg_replace_callback('/@if\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php if ({$matches[1]}): ?>";
        }, $content);

        // @elseif
        $content = preg_replace_callback('/@elseif\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php elseif ({$matches[1]}): ?>";
        }, $content);

        // @else
        $content = preg_replace('/@else\s*(?:\r?\n)?/', '<?php else: ?>', $content);

        // @endif
        $content = preg_replace('/@endif\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @unless (inverted if)
        $content = preg_replace_callback('/@unless\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php if (!({$matches[1]})): ?>";
        }, $content);
        $content = preg_replace('/@endunless\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @isset
        $content = preg_replace_callback('/@isset\s*\(((?:[^()]|\([^()]*\))*)\)/s', function ($matches) {
            return "<?php if (isset({$matches[1]})): ?>";
        }, $content);
        $content = preg_replace('/@endisset\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @empty
        $content = preg_replace_callback('/@empty\s*\(((?:[^()]|\([^()]*\))*)\)/s', function ($matches) {
            return "<?php if (empty({$matches[1]})): ?>";
        }, $content);
        $content = preg_replace('/@endempty\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @switch
        $content = preg_replace_callback('/@switch\s*\(((?:[^()]|\([^()]*\))*)\)/s', function ($matches) {
            return "<?php switch ({$matches[1]}): ?>";
        }, $content);

        $content = preg_replace_callback('/@case\s*\(((?:[^()]|\([^()]*\))*)\)/s', function ($matches) {
            return "<?php case {$matches[1]}: ?>";
        }, $content);

        $content = preg_replace('/@default\s*/', '<?php default: ?>', $content);
        $content = preg_replace('/@endswitch\s*(?:\r?\n)?/', '<?php endswitch; ?>', $content);

        return $content;
    }

    private function compileLoops(string $content): string
    {
        // @forelse (Optimized for memory: avoids count() and array casting)
        $content = preg_replace_callback(
            '/@forelse\s*\((.+?)\s+as\s+(.+?)\)(.*?)@empty(.*?)@endforelse/s',
            function ($matches) {
                $array = trim($matches[1]);
                $iteration = trim($matches[2]);
                $loopContent = $matches[3];
                $emptyContent = $matches[4];
                $emptyVar = '__empty_' . md5($array . uniqid());

                $checkIsset = preg_match('/^\$[\w]+$/', $array) ? "isset($array) && " : '';

                $initLoop = '$__loop_parent = $loop ?? null; $loop = new \Plugs\View\Loop(' . $array . ', $__loop_parent, ($__loop_parent->depth ?? 0) + 1);';
                $endLoop = '$loop = $__loop_parent;';
                $tick = '$loop->tick(); if (isset($this) && method_exists($this, "isAutoFlushEnabled") && $this->isAutoFlushEnabled() && $loop->shouldFlush($this->getAutoFlushFrequency())) flush();';

                return sprintf(
                    '<?php $%s = true; if(%sis_iterable(%s)): %s foreach (%s as %s): $%s = false; ?>%s<?php %s endforeach; %s endif; if($%s): ?>%s<?php endif; ?>',
                    $emptyVar,
                    $checkIsset,
                    $array,
                    $initLoop,
                    $array,
                    $iteration,
                    $emptyVar,
                    $loopContent,
                    $tick,
                    $endLoop,
                    $emptyVar,
                    $emptyContent
                );
            },
            $content
        );

        // @foreach
        $content = preg_replace_callback(
            '/@foreach\s*\((.+?)\s+as\s+(.+?)\)/s',
            function ($matches) {
                $array = trim($matches[1]);
                $iteration = trim($matches[2]);
                $checkIsset = preg_match('/^\$[\w]+$/', $array) ? "isset($array) && " : '';

                $initLoop = '$__loop_parent = $loop ?? null; $loop = new \Plugs\View\Loop(' . $array . ', $__loop_parent, ($__loop_parent->depth ?? 0) + 1);';

                return sprintf(
                    '<?php if(%sis_iterable(%s)): %s foreach (%s as %s): ?>',
                    $checkIsset,
                    $array,
                    $initLoop,
                    $array,
                    $iteration
                );
            },
            $content
        );

        $content = preg_replace('/@endforeach\s*(?:\r?\n)?/', '<?php $loop->tick(); if (isset($this) && method_exists($this, "isAutoFlushEnabled") && $this->isAutoFlushEnabled() && $loop->shouldFlush($this->getAutoFlushFrequency())) flush(); endforeach; $loop = $__loop_parent; endif; ?>', $content);

        // @for
        $content = preg_replace('/@for\s*\((.+?)\)/s', '<?php for ($1): ?>', $content);
        $content = preg_replace('/@endfor\s*(?:\r?\n)?/', '<?php endfor; ?>', $content);

        // @while
        $content = preg_replace('/@while\s*\((.+?)\)/s', '<?php while ($1): ?>', $content);
        $content = preg_replace('/@endwhile\s*(?:\r?\n)?/', '<?php endwhile; ?>', $content);

        // @continue
        $content = preg_replace_callback(
            '/@continue(?:\s*\((.+?)\))?/s',
            function ($matches) {
                if (isset($matches[1]) && !empty(trim($matches[1]))) {
                    return sprintf('<?php if (%s) continue; ?>', $matches[1]);
                }

                return '<?php continue; ?>';
            },
            $content
        );

        // @break
        $content = preg_replace_callback(
            '/@break(?:\s*\((.+?)\))?/s',
            function ($matches) {
                if (isset($matches[1]) && !empty(trim($matches[1]))) {
                    return sprintf('<?php if (%s) break; ?>', $matches[1]);
                }

                return '<?php break; ?>';
            },
            $content
        );

        return $content;
    }

    private function compileIncludes(string $content): string
    {
        return preg_replace_callback(
            '/@include\s*\([\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]|\$\w+))?\s*\)/s',
            function ($matches) {
                $view = $matches[1];

                // Normalize and validate path
                $view = str_replace(['\\', '/'], '.', $view);
                if (preg_match('/[^a-zA-Z0-9._-]/', $view) || strpos($view, '..') !== false) {
                    return sprintf('<?php /* Invalid include path: %s */ ?>', htmlspecialchars($view));
                }

                $view = addslashes($view);
                $data = $matches[2] ?? '[]';

                return sprintf(
                    '<?php if (isset($view)) { echo $view->render(\'%s\', array_merge(get_defined_vars(), (array)%s)); } ?>',
                    $view,
                    $data
                );
            },
            $content
        );
    }

    private function compileSections(string $content): string
    {
        // @extends
        $content = preg_replace_callback(
            '/@extends\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                return sprintf('<?php $__extends = \'%s\'; ?>', addslashes($matches[1]));
            },
            $content
        );

        // @section with inline content
        $content = preg_replace_callback(
            '/@section\s*\([\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                $sectionContent = addslashes($matches[2]);

                return sprintf('<?php $__sections[\'%s\'] = \'%s\'; ?>', $name, $sectionContent);
            },
            $content
        );

        // @section block
        $content = preg_replace_callback(
            '/@section\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);

                return sprintf('<?php $__currentSection = \'%s\'; ob_start(); ?>', $name);
            },
            $content
        );

        $content = preg_replace(
            '/@endsection\s*(?:\r?\n)?/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); unset($__currentSection); } ?>',
            $content
        );

        // @show
        $content = preg_replace(
            '/@show\s*(?:\r?\n)?/',
            '<?php if (isset($__currentSection)) { $__sections[$__currentSection] = ob_get_clean(); echo $__sections[$__currentSection]; unset($__currentSection); } ?>',
            $content
        );

        // @yield
        $content = preg_replace_callback(
            '/@yield\s*\([\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\)/',
            function ($matches) {
                $section = addslashes($matches[1]);
                $default = isset($matches[2]) ? addslashes($matches[2]) : '';

                return sprintf('<?php echo $__sections[\'%s\'] ?? \'%s\'; ?>', $section, $default);
            },
            $content
        );

        // @parent
        $content = preg_replace('/@parent\s*(?:\r?\n)?/', '<?php echo $__parentContent ?? \'\'; ?>', $content);

        return $content;
    }

    private function compileStacks(string $content): string
    {
        // @push
        $content = preg_replace_callback(
            '/@push\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);

                return sprintf('<?php $__currentStack = \'%s\'; ob_start(); ?>', $stackName);
            },
            $content
        );

        $content = preg_replace(
            '/@endpush\s*(?:\r?\n)?/',
            '<?php if (isset($__currentStack)) { ' .
            'if (!isset($__stacks[$__currentStack])) { $__stacks[$__currentStack] = []; } ' .
            '$__stacks[$__currentStack][] = ob_get_clean(); unset($__currentStack); } ?>',
            $content
        );

        // @prepend
        $content = preg_replace_callback(
            '/@prepend\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);

                return sprintf('<?php $__currentStack = \'%s\'; $__isPrepend = true; ob_start(); ?>', $stackName);
            },
            $content
        );

        $content = preg_replace(
            '/@endprepend\s*(?:\r?\n)?/',
            '<?php if (isset($__currentStack)) { ' .
            'if (!isset($__stacks[$__currentStack])) { $__stacks[$__currentStack] = []; } ' .
            'array_unshift($__stacks[$__currentStack], ob_get_clean()); unset($__currentStack, $__isPrepend); } ?>',
            $content
        );

        // @stack
        $content = preg_replace_callback(
            '/@stack\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $stackName = addslashes($matches[1]);

                return sprintf('<?php echo implode(\'\', $__stacks[\'%s\'] ?? []); ?>', $stackName);
            },
            $content
        );

        return $content;
    }

    /**
     * Compile @pushOnce directive to push content to a stack only once.
     * Usage: @pushOnce('uniqueKey', 'stackName') content @endPushOnce
     */
    private function compilePushOnce(string $content): string
    {
        return preg_replace_callback(
            '/@pushOnce\s*\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*\)\s*\n?(.*?)\n?\s*@endPushOnce/s',
            function ($matches) {
                $key = $matches[1];
                $stackName = addslashes($matches[2]);
                $innerContent = $matches[3];
                $id = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);

                return sprintf(
                    '<?php if (!isset($__pushOnce_%s)): $__pushOnce_%s = true; $__currentStack = \'%s\'; ob_start(); ?>%s<?php if (isset($__currentStack)) { if (!isset($__stacks[$__currentStack])) { $__stacks[$__currentStack] = []; } $__stacks[$__currentStack][] = ob_get_clean(); unset($__currentStack); } endif; ?>',
                    $id,
                    $id,
                    $stackName,
                    $innerContent
                );
            },
            $content
        );
    }


    /**
     * Compile @css and @js asset directives into HTML link/script tags.
     *
     * Usage:
     *   @css('path/to/file.css')                              → <link rel="stylesheet" href="...">
     *   @css('file.css', ['media' => 'print'])                → <link rel="stylesheet" href="..." media="print">
     *   @js('path/to/file.js')                                → <script src="..."></script>
     *   @js('file.js', ['defer' => true])                     → <script src="..." defer></script>
     *   @js('file.js', ['async' => true, 'type' => 'module']) → <script src="..." async type="module"></script>
     */
    private function compileAssets(string $content): string
    {
        // @css('path') or @css('path', ['attr' => 'val'])
        $content = preg_replace_callback(
            '/@css\s*\(\s*[\'\"](.+?)[\'\"](?:\s*,\s*(\[.+?\]))?\s*\)/',
            function ($matches) {
                $href = addslashes($matches[1]);
                if (isset($matches[2])) {
                    $attrsExpr = $matches[2];
                    return '<?php $__cssAttrs = ' . $attrsExpr . '; '
                        . '$__cssExtra = \'\'; '
                        . 'foreach ($__cssAttrs as $__k => $__v) { '
                        . '    if ($__v === true) { $__cssExtra .= \' \' . $__k; } '
                        . '    elseif ($__v !== false && $__v !== null) { $__cssExtra .= \' \' . $__k . \'="\' . htmlspecialchars((string)$__v, ENT_QUOTES, \'UTF-8\') . \'"\'; } '
                        . '} '
                        . 'echo \'<link rel="stylesheet" href="' . $href . '"\' . $__cssExtra . \'>\'; ?>';
                }
                return '<link rel="stylesheet" href="' . $href . '">';
            },
            $content
        );

        // @js('path') or @js('path', ['attr' => val])
        $content = preg_replace_callback(
            '/@js\s*\(\s*[\'\"](.+?)[\'\"](?:\s*,\s*(\[.+?\]))?\s*\)/',
            function ($matches) {
                $src = addslashes($matches[1]);
                if (isset($matches[2])) {
                    $attrsExpr = $matches[2];
                    return '<?php $__jsAttrs = ' . $attrsExpr . '; '
                        . '$__jsExtra = \'\'; '
                        . 'foreach ($__jsAttrs as $__k => $__v) { '
                        . '    if ($__v === true) { $__jsExtra .= \' \' . $__k; } '
                        . '    elseif ($__v !== false && $__v !== null) { $__jsExtra .= \' \' . $__k . \'="\' . htmlspecialchars((string)$__v, ENT_QUOTES, \'UTF-8\') . \'"\'; } '
                        . '} '
                        . 'echo \'<script src="' . $src . '"\' . $__jsExtra . \'></script>\'; ?>';
                }
                return '<script src="' . $src . '"></script>';
            },
            $content
        );

        return $content;
    }

    private function compileCsrf(string $content): string
    {
        return preg_replace(
            '/@csrf\s*(?:\r?\n)?/',
            '<?php echo function_exists(\'csrf_field\') ? csrf_field() : ' .
            '\'<input type="hidden" name="_token" value="\' . ($_SESSION[\'_csrf_token\'] ?? \'\') . \'">\'; ?>',
            $content
        );
    }

    private function compileMethod(string $content): string
    {
        return preg_replace(
            '/@method\s*\([\'"](.+?)[\'"]\)/',
            '<?php echo \'<input type="hidden" name="_method" value="$1">\'; ?>',
            $content
        );
    }

    private function compileCan(string $content): string
    {
        // @can
        $content = preg_replace_callback('/@can\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php if (function_exists('gate') && gate()->check({$matches[1]})): ?>";
        }, $content);

        // @cannot
        $content = preg_replace_callback('/@cannot\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php if (function_exists('gate') && gate()->denies({$matches[1]})): ?>";
        }, $content);

        // @elsecan
        $content = preg_replace_callback('/@elsecan\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php elseif (function_exists('gate') && gate()->check({$matches[1]})): ?>";
        }, $content);

        // @elsecannot
        $content = preg_replace_callback('/@elsecannot\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php elseif (function_exists('gate') && gate()->denies({$matches[1]})): ?>";
        }, $content);

        // @endcan and @endcannot
        $content = preg_replace('/@endcan\s*(?:\r?\n)?/', '<?php endif; ?>', $content);
        $content = preg_replace('/@endcannot\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        return $content;
    }

    private function compileRole(string $content): string
    {
        // @role
        $content = preg_replace_callback('/@role\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php if (auth()->check() && auth()->user()->hasRole({$matches[1]})): ?>";
        }, $content);

        // @endrole
        $content = preg_replace('/@endrole\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @hasrole
        $content = preg_replace_callback('/@hasrole\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php if (auth()->check() && auth()->user()->hasRole({$matches[1]})): ?>";
        }, $content);

        // @endhasrole
        $content = preg_replace('/@endhasrole\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @hasanyrole
        $content = preg_replace_callback('/@hasanyrole\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php if (auth()->check() && auth()->user()->hasAnyRole({$matches[1]})): ?>";
        }, $content);

        // @endhasanyrole
        $content = preg_replace('/@endhasanyrole\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        // @hasallroles
        $content = preg_replace_callback('/@hasallroles\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\)/s', function ($matches) {
            return "<?php if (auth()->check() && auth()->user()->hasAllRoles({$matches[1]})): ?>";
        }, $content);

        // @endhasallroles
        $content = preg_replace('/@endhasallroles\s*(?:\r?\n)?/', '<?php endif; ?>', $content);

        return $content;
    }

    private function compileOnce(string $content): string
    {
        return preg_replace_callback(
            '/@once(?:\s*\(\s*[\'"](.+?)[\'"]\s*\))?\s*\n?(.*?)\n?\s*@endonce/s',
            function ($matches) {
                // If a key is provided in @once('key'), use it. Otherwise hash the content.
                $key = !empty($matches[1]) ? $matches[1] : md5($matches[2]);
                $id = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);

                return sprintf(
                    '<?php if (!isset($__once_%s)): $__once_%s = true; ?>%s<?php endif; ?>',
                    $id,
                    $id,
                    $matches[2]
                );
            },
            $content
        );
    }

    /**
     * Compile @stream directive for chunked/streamed rendering
     */
    private function compileStream(string $content): string
    {
        return preg_replace_callback(
            self::$patterns['stream'] ?? '/@stream\s*\((.+?)\)/s',
            function ($matches) {
                return sprintf('<?php $view->renderToStream(%s, $__data ?? []); ?>', $matches[1]);
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @error directive for validation error messaging
     */
    private function compileError(string $content): string
    {
        return preg_replace_callback(
            self::$patterns['error'] ?? '/@error\s*\((.+?)\)/s',
            function ($matches) {
                $field = $matches[1];
                return sprintf(
                    '<?php if ($errors->has(%s)): ?>
                    <?php $message = $errors->first(%s); ?>',
                    $field,
                    $field
                );
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @enderror directive
     */
    private function compileEndError(string $content): string
    {
        return str_replace('@enderror', '<?php unset($message); endif; ?>', $content);
    }

    private function compileErrorDirectives(string $content): string
    {
        // @error directive with message variable
        $content = preg_replace_callback(
            '/@error\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $field = $matches[1];

                return sprintf(
                    '<?php if (isset($errors) && $errors->has(\'%s\')): ' .
                    '$message = $errors->first(\'%s\'); ?>',
                    addslashes($field),
                    addslashes($field)
                );
            },
            $content
        );

        $content = preg_replace('/@enderror\s*(?:\r?\n)?/', '<?php unset($message); endif; ?>', $content);

        return $content;
    }

    private function compileJson(string $content): string
    {
        return preg_replace(
            '/@json\s*\((.+?)\)/',
            '<?php echo json_encode($1, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>',
            $content
        );
    }

    /*
     * @jsonScript($data, 'variableName')
     * Output JSON in a script tag with CSP nonce support
     */
    private function compileJsonScript(string $content): string
    {
        return preg_replace_callback(
            '/@jsonScript\s*\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $data = $matches[1];
                $varName = $matches[2];

                return sprintf(
                    '<?php $__nonce = isset($view) ? $view->getCspNonce() : null; ' .
                    'echo "<script" . ($__nonce ? " nonce=\"{$__nonce}\"" : "") . ">" . ' .
                    '"var %s = " . json_encode(%s, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) . ";" . ' .
                    '"</script>"; ?>',
                    addslashes($varName),
                    $data
                );
            },
            $content
        );
    }

    /*
     * @class(['btn', 'btn-primary' => $isPrimary, 'disabled' => !$isActive])
     * Compile conditional class attributes
     */
    /*
     * @class(['btn', 'btn-primary' => $isPrimary, 'disabled' => !$isActive])
     * Compile conditional class attributes
     */
    private function compileClass(string $content): string
    {
        return preg_replace_callback(
            '/@class\s*\((\[.+?\])\)/s',
            function ($matches) {
                return sprintf(
                    '<?php echo \'class="\' . \Plugs\Utils\Arr::toCssClasses(%s) . \'"\'; ?>',
                    $matches[1]
                );
            },
            $content
        );
    }

    private function compileCustomDirectives(string $content): string
    {
        // Sort directives by length descending to prevent prefix conflicts
        $directives = $this->customDirectives;
        uksort($directives, fn($a, $b) => strlen($b) <=> strlen($a));

        foreach ($directives as $name => $handler) {
            $content = preg_replace_callback(
                '/@' . preg_quote($name, '/') . '(?!\w)(?:\s*\(([^()]*+(?:\((?1)\)[^()]*+)*+)\))?/s',
                function ($matches) use ($handler) {
                    $expression = $matches[1] ?? null;

                    return call_user_func($handler, $expression);
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Compile @old directive (alternative syntax)
     * Usage: @old('name', 'default value')
     */
    private function compileOld(string $content): string
    {
        return preg_replace_callback(
            '/@old\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\s*\)/s',
            function ($matches) {
                $key = addslashes($matches[1]);
                $default = isset($matches[2]) ? addslashes($matches[2]) : '';

                return sprintf(
                    '<?php echo htmlspecialchars((string)(isset($old) && is_callable($old) ? $old(\'%s\', \'%s\') : (isset($_SESSION[\'_old_input\'][\'%s\']) ? $_SESSION[\'_old_input\'][\'%s\'] : \'%s\')), ENT_QUOTES, \'UTF-8\'); ?>',
                    $key,
                    $default,
                    $key,
                    $key,
                    $default
                );
            },
            $content
        );
    }

    /**
     * Compile @success and @error directives for flash messages
     */
    private function compileFlashMessages(string $content): string
    {
        // @success directive
        $content = preg_replace_callback(
            '/@success\s*(?:\r?\n)?/',
            function ($matches) {
                return '<?php if (isset($_SESSION[\'_success\'])): $message = $_SESSION[\'_success\']; unset($_SESSION[\'_success\']); ?>';
            },
            $content
        );

        $content = preg_replace('/@endsuccess\s*(?:\r?\n)?/', '<?php unset($message); endif; ?>', $content);

        return $content;
    }

    private function compileHelperDirectives(string $content): string
    {
        // Date & Time
        $content = $this->compileDate($content);
        $content = $this->compileTime($content);
        $content = $this->compileDatetime($content);
        $content = $this->compileHumanDate($content);
        $content = $this->compileDiffForHumans($content);

        // Numbers & Currency
        $content = $this->compileNumber($content);
        $content = $this->compileCurrency($content);
        $content = $this->compilePercent($content);

        // String Manipulation
        $content = $this->compileUpper($content);
        $content = $this->compileLower($content);
        $content = $this->compileTitle($content);
        $content = $this->compileTitleTruncate($content);
        $content = $this->compileSlug($content);
        $content = $this->compileTruncate($content);
        $content = $this->compileExcerpt($content);
        $content = $this->compileUcfirst($content);

        // Arrays & Collections
        $content = $this->compileCount($content);
        $content = $this->compileJoin($content);
        $content = $this->compileImplode($content);

        // Utility
        $content = $this->compileDefault($content);
        $content = $this->compileRoute($content);
        $content = $this->compileAsset($content);
        $content = $this->compileUrl($content);
        $content = $this->compileConfig($content);
        $content = $this->compileEnv($content);
        $content = $this->compileJsonScript($content);
        $content = $this->compileClass($content);
        $content = $this->compileStyle($content);
        $content = $this->compileVite($content);
        $content = $this->compileFlush($content);

        return $content;
    }

    /**
     * Compile @flush directive to flush the output buffer.
     */
    private function compileFlush(string $content): string
    {
        return preg_replace('/@flush\s*/', '<?php flush(); ?>', $content);
    }

    // ============================================
    // DATE & TIME DIRECTIVES
    // ============================================

    /*
     * @date($timestamp, 'Y-m-d')
     * Format date - handles DateTime objects, timestamps, and strings
     */
    private function compileDate(string $content): string
    {
        return preg_replace_callback(
            '/@date\s*\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*\)/s',
            function ($matches) {
                $timestamp = trim($matches[1]);
                $format = $matches[2];

                return sprintf(
                    '<?php echo (function($ts, $fmt) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        return $ts->format($fmt);
                    } elseif (is_numeric($ts)) {
                        return date($fmt, (int)$ts);
                    } else {
                        return date($fmt, strtotime($ts));
                    }
                })(%s, \'%s\'); ?>',
                    $timestamp,
                    addslashes($format)
                );
            },
            $content
        );
    }

    /*
     * @time($timestamp, 'H:i:s')
     * Format time - handles DateTime objects, timestamps, and strings
     */
    private function compileTime(string $content): string
    {
        return preg_replace_callback(
            '/@time\s*\(\s*(.+?)\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            function ($matches) {
                $timestamp = trim($matches[1]);
                $format = $matches[2] ?? 'H:i:s';

                return sprintf(
                    '<?php echo (function($ts, $fmt) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        return $ts->format($fmt);
                    } elseif (is_numeric($ts)) {
                        return date($fmt, (int)$ts);
                    } else {
                        return date($fmt, strtotime($ts));
                    }
                })(%s, \'%s\'); ?>',
                    $timestamp,
                    addslashes($format)
                );
            },
            $content
        );
    }

    /*
     * @datetime($timestamp, 'Y-m-d H:i:s')
     * Format datetime - handles DateTime objects, timestamps, and strings
     */
    private function compileDatetime(string $content): string
    {
        return preg_replace_callback(
            '/@datetime\s*\(\s*(.+?)\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            function ($matches) {
                $timestamp = trim($matches[1]);
                $format = $matches[2] ?? 'Y-m-d H:i:s';

                return sprintf(
                    '<?php echo (function($ts, $fmt) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        return $ts->format($fmt);
                    } elseif (is_numeric($ts)) {
                        return date($fmt, (int)$ts);
                    } else {
                        return date($fmt, strtotime($ts));
                    }
                })(%s, \'%s\'); ?>',
                    $timestamp,
                    addslashes($format)
                );
            },
            $content
        );
    }

    /*
     * @humanDate($timestamp)
     * Output: January 15, 2024
     * Handles DateTime objects, timestamps, and strings
     */
    private function compileHumanDate(string $content): string
    {
        return preg_replace_callback(
            '/@humanDate\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $timestamp = trim($matches[1]);

                return sprintf(
                    '<?php echo (function($ts) {
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        return $ts->format(\'F j, Y\');
                    } elseif (is_numeric($ts)) {
                        return date(\'F j, Y\', (int)$ts);
                    } else {
                        return date(\'F j, Y\', strtotime($ts));
                    }
                })(%s); ?>',
                    $timestamp
                );
            },
            $content
        );
    }

    /*
     * @diffForHumans($timestamp)
     * Output: 2 hours ago, 3 days ago, etc.
     * Handles: DateTime objects, timestamps, and date strings
     */
    private function compileDiffForHumans(string $content): string
    {
        return preg_replace_callback(
            '/@diffForHumans\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $timestamp = trim($matches[1]);

                return sprintf(
                    '<?php echo (function($ts) {
                    // Handle different input types
                    if ($ts instanceof DateTime || $ts instanceof DateTimeInterface) {
                        $time = $ts->getTimestamp();
                    } elseif (is_numeric($ts)) {
                        $time = (int)$ts;
                    } elseif (is_string($ts)) {
                        $time = strtotime($ts);
                    } else {
                        return "Invalid date";
                    }
                    
                    $diff = time() - $time;
                    
                    // Future dates
                    if ($diff < 0) {
                        $diff = abs($diff);
                        if ($diff < 60) return "in " . $diff . " second" . ($diff != 1 ? "s" : "");
                        if ($diff < 3600) return "in " . floor($diff / 60) . " minute" . (floor($diff / 60) != 1 ? "s" : "");
                        if ($diff < 86400) return "in " . floor($diff / 3600) . " hour" . (floor($diff / 3600) != 1 ? "s" : "");
                        if ($diff < 604800) return "in " . floor($diff / 86400) . " day" . (floor($diff / 86400) != 1 ? "s" : "");
                        if ($diff < 2592000) return "in " . floor($diff / 604800) . " week" . (floor($diff / 604800) != 1 ? "s" : "");
                        if ($diff < 31536000) return "in " . floor($diff / 2592000) . " month" . (floor($diff / 2592000) != 1 ? "s" : "");
                        return "in " . floor($diff / 31536000) . " year" . (floor($diff / 31536000) != 1 ? "s" : "");
                    }
                    
                    // Past dates
                    if ($diff < 60) return $diff . " second" . ($diff != 1 ? "s" : "") . " ago";
                    if ($diff < 3600) return floor($diff / 60) . " minute" . (floor($diff / 60) != 1 ? "s" : "") . " ago";
                    if ($diff < 86400) return floor($diff / 3600) . " hour" . (floor($diff / 3600) != 1 ? "s" : "") . " ago";
                    if ($diff < 604800) return floor($diff / 86400) . " day" . (floor($diff / 86400) != 1 ? "s" : "") . " ago";
                    if ($diff < 2592000) return floor($diff / 604800) . " week" . (floor($diff / 604800) != 1 ? "s" : "") . " ago";
                    if ($diff < 31536000) return floor($diff / 2592000) . " month" . (floor($diff / 2592000) != 1 ? "s" : "") . " ago";
                    return floor($diff / 31536000) . " year" . (floor($diff / 31536000) != 1 ? "s" : "") . " ago";
                })(%s); ?>',
                    $timestamp
                );
            },
            $content
        );
    }

    // ============================================
    // NUMBER & CURRENCY DIRECTIVES
    // ============================================

    /*
     * @number($value, 2)
     * Format number with decimals
     */
    private function compileNumber(string $content): string
    {
        return preg_replace_callback(
            '/@number\s*\(\s*(.+?)\s*(?:,\s*(\d+))?\s*\)/s',
            function ($matches) {
                $value = trim($matches[1]);
                $decimals = $matches[2] ?? 0;

                return sprintf(
                    '<?php echo number_format(%s, %d); ?>',
                    $value,
                    $decimals
                );
            },
            $content
        );
    }

    /*
     * @currency($amount, 'USD')
     * Format currency (default: USD)
     */
    private function compileCurrency(string $content): string
    {
        return preg_replace_callback(
            '/@currency\s*\(\s*(.+?)\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            function ($matches) {
                $amount = trim($matches[1]);
                $currency = $matches[2] ?? 'USD';

                return sprintf(
                    '<?php echo (function($amt, $curr) {
                    $symbols = [
                        \'USD\' => \'$\', \'EUR\' => \'€\', \'GBP\' => \'£\',
                        \'JPY\' => \'¥\', \'NGN\' => \'₦\', \'INR\' => \'₹\'
                    ];
                    $symbol = $symbols[$curr] ?? $curr . \' \';
                    return $symbol . number_format($amt, 2);
                })(%s, \'%s\'); ?>',
                    $amount,
                    addslashes($currency)
                );
            },
            $content
        );
    }

    /*
     * @percent($value, 2)
     * Format as percentage
     */
    private function compilePercent(string $content): string
    {
        return preg_replace_callback(
            '/@percent\s*\(\s*(.+?)\s*(?:,\s*(\d+))?\s*\)/s',
            function ($matches) {
                $value = trim($matches[1]);
                $decimals = $matches[2] ?? 2;

                return sprintf(
                    '<?php echo number_format(%s, %d) . \'%%\'; ?>',
                    $value,
                    $decimals
                );
            },
            $content
        );
    }

    // ============================================
    // STRING MANIPULATION DIRECTIVES
    // ============================================

    /*
     * @upper($string)
     * Convert to uppercase
     */
    private function compileUpper(string $content): string
    {
        return preg_replace_callback(
            '/@upper\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $string = trim($matches[1]);

                return sprintf('<?php echo strtoupper(%s); ?>', $string);
            },
            $content
        );
    }

    /*
     * @lower($string)
     * Convert to lowercase
     */
    private function compileLower(string $content): string
    {
        return preg_replace_callback(
            '/@lower\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $string = trim($matches[1]);

                return sprintf('<?php echo strtolower(%s); ?>', $string);
            },
            $content
        );
    }

    /*
     * @title($string)
     * Convert to title case
     */
    private function compileTitle(string $content): string
    {
        return preg_replace_callback(
            '/@title\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $string = trim($matches[1]);

                return sprintf('<?php echo ucwords(strtolower(%s)); ?>', $string);
            },
            $content
        );
    }

    /*
     * Add this new method to your ViewCompiler class
     * @titleTruncate($string, 50)
     * Combines title case + truncation in one directive
     */
    private function compileTitleTruncate(string $content): string
    {
        return preg_replace_callback(
            '/@titleTruncate\s*\(\s*(.+?)\s*(?:,\s*(\d+)\s*(?:,\s*[\'"](.+?)[\'"])?)?\s*\)/s',
            function ($matches) {
                $string = trim($matches[1]);
                $length = $matches[2] ?? 100;
                $end = $matches[3] ?? '...';

                return sprintf(
                    '<?php echo (function($str, $len, $ending) {
                    $str = ucwords(strtolower($str));
                    return mb_strlen($str) > $len ? mb_substr($str, 0, $len) . $ending : $str;
                })(%s, %d, \'%s\'); ?>',
                    $string,
                    $length,
                    addslashes($end)
                );
            },
            $content
        );
    }

    /*
     * @ucfirst($string)
     * Capitalize first letter
     */
    private function compileUcfirst(string $content): string
    {
        return preg_replace_callback(
            '/@ucfirst\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $string = trim($matches[1]);

                return sprintf('<?php echo ucfirst(%s); ?>', $string);
            },
            $content
        );
    }

    /*
     * @slug($string)
     * Convert to URL-friendly slug
     */
    private function compileSlug(string $content): string
    {
        return preg_replace_callback(
            '/@slug\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $string = trim($matches[1]);

                return sprintf(
                    '<?php echo strtolower(trim(preg_replace(\'/[^A-Za-z0-9-]+/\', \'-\', %s), \'-\')); ?>',
                    $string
                );
            },
            $content
        );
    }

    /*
     * @truncate($string, 100, '...')
     * @truncate($string, 100)
     * @truncate($string) - defaults to 100 characters
     * Truncate string to length
     */
    private function compileTruncate(string $content): string
    {
        return preg_replace_callback(
            '/@truncate\s*\(\s*(.+?)\s*(?:,\s*(\d+)\s*(?:,\s*[\'"](.+?)[\'"])?)?\s*\)/s',
            function ($matches) {
                $string = trim($matches[1]);
                $length = $matches[2] ?? 100; // Default to 100 if not provided
                $end = $matches[3] ?? '...';

                return sprintf(
                    '<?php echo mb_strlen(%s) > %d ? mb_substr(%s, 0, %d) . \'%s\' : %s; ?>',
                    $string,
                    $length,
                    $string,
                    $length,
                    addslashes($end),
                    $string
                );
            },
            $content
        );
    }

    /*
     * @excerpt($string, 150)
     * Create excerpt (word-aware truncation)
     */
    private function compileExcerpt(string $content): string
    {
        return preg_replace_callback(
            '/@excerpt\s*\(\s*(.+?)\s*(?:,\s*(\d+))?\s*\)/s',
            function ($matches) {
                $string = trim($matches[1]);
                $length = $matches[2] ?? 150;

                return sprintf(
                    '<?php echo (function($str, $len) {
                    $str = strip_tags($str);
                    if (mb_strlen($str) <= $len) return $str;
                    $str = mb_substr($str, 0, $len);
                    return mb_substr($str, 0, mb_strrpos($str, \' \')) . \'...\';
                })(%s, %d); ?>',
                    $string,
                    $length
                );
            },
            $content
        );
    }

    // ============================================
    // ARRAY & COLLECTION DIRECTIVES
    // ============================================

    /*
     * @count($array)
     * Count array elements
     */
    private function compileCount(string $content): string
    {
        return preg_replace_callback(
            '/@count\s*\(\s*(.+?)\s*\)/s',
            function ($matches) {
                $array = trim($matches[1]);

                return sprintf('<?php echo count(%s); ?>', $array);
            },
            $content
        );
    }

    /*
     * @join($array, ', ')
     * Join array elements with separator
     */
    private function compileJoin(string $content): string
    {
        return preg_replace_callback(
            '/@join\s*\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*\)/s',
            function ($matches) {
                $array = trim($matches[1]);
                $separator = $matches[2];

                return sprintf(
                    '<?php echo implode(\'%s\', %s); ?>',
                    addslashes($separator),
                    $array
                );
            },
            $content
        );
    }

    /*
     * @implode($array, ', ')
     * Alias for join
     */
    private function compileImplode(string $content): string
    {
        return $this->compileJoin($content);
    }

    // ============================================
    // UTILITY DIRECTIVES
    // ============================================

    /*
     * @default($value, 'fallback')
     * Provide default value if empty
     */
    private function compileDefault(string $content): string
    {
        return preg_replace_callback(
            '/@default\s*\(\s*(.+?)\s*,\s*[\'"](.+?)[\'"]\s*\)/s',
            function ($matches) {
                $value = trim($matches[1]);
                $default = $matches[2];

                return sprintf(
                    '<?php echo !empty(%s) ? %s : \'%s\'; ?>',
                    $value,
                    $value,
                    addslashes($default)
                );
            },
            $content
        );
    }

    /*
     * @route('route.name', ['id' => 1])
     * Generate route URL
     */
    private function compileRoute(string $content): string
    {
        return preg_replace_callback(
            '/@route\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]))?\s*\)/s',
            function ($matches) {
                $routeName = $matches[1];
                $params = $matches[2] ?? '[]';

                return sprintf(
                    '<?php echo function_exists(\'route\') ? route(\'%s\', %s) : \'#\'; ?>',
                    addslashes($routeName),
                    $params
                );
            },
            $content
        );
    }

    /*
     * @asset('css/style.css')
     * Generate asset URL with cache busting
     */
    private function compileAsset(string $content): string
    {
        return preg_replace_callback(
            '/@asset\s*\(\s*[\'"](.+?)[\'"]\s*\)/s',
            function ($matches) {
                $path = $matches[1];

                return sprintf(
                    '<?php echo (function($p) {
                    $docRoot = $_SERVER[\'DOCUMENT_ROOT\'] ?? \'\';
                    $filePath = rtrim($docRoot, \'/\') . \'/\' . ltrim($p, \'/\');
                    $version = file_exists($filePath) ? \'?v=\' . filemtime($filePath) : \'\';
                    $base = rtrim($_SERVER[\'REQUEST_SCHEME\'] . \'://\' . $_SERVER[\'HTTP_HOST\'], \'/\');
                    return $base . \'/\' . ltrim($p, \'/\') . $version;
                })(\'%s\'); ?>',
                    addslashes($path)
                );
            },
            $content
        );
    }

    /*
     * @vite(['resources/css/app.css', 'resources/js/app.js'])
     * @vite('resources/js/app.js')
     * Generate Vite scripts/styles with Hot Module Replacement support
     */
    private function compileVite(string $content): string
    {
        return preg_replace_callback(
            '/@vite\s*\((.+?)\)/s',
            function ($matches) {
                $entry = $matches[1];

                return sprintf(
                    '<?php echo (function($entry) {
                    $hotFile = public_path("hot");
                    
                    // Dev Mode
                    if (file_exists($hotFile)) {
                        $url = trim(file_get_contents($hotFile));
                        $scripts = "";
                        
                        // Client script
                        $scripts .= "<script type=\"module\" src=\"{$url}/@vite/client\"></script>";
                        
                        if (is_array($entry)) {
                            foreach ($entry as $e) {
                                $scripts .= "<script type=\"module\" src=\"{$url}/{$e}\"></script>";
                            }
                        } else {
                            $scripts .= "<script type=\"module\" src=\"{$url}/{$entry}\"></script>";
                        }
                        return $scripts;
                    }
                    
                    // Production Mode
                    $manifestFile = public_path("build/manifest.json");
                    if (!file_exists($manifestFile)) {
                        return "<!-- Vite manifest not found -->";
                    }
                    
                    $manifest = json_decode(file_get_contents($manifestFile), true);
                    $output = "";
                    $entries = is_array($entry) ? $entry : [$entry];
                    
                    foreach ($entries as $e) {
                         if (isset($manifest[$e])) {
                             $file = $manifest[$e]["file"];
                             $css = $manifest[$e]["css"] ?? [];
                             
                             // Main script
                             $output .= "<script type=\"module\" src=\"/build/{$file}\"></script>";
                             
                             // Associated CSS
                             foreach ($css as $c) {
                                 $output .= "<link rel=\"stylesheet\" href=\"/build/{$c}\">";
                             }
                         }
                    }
                    
                    return $output;
                })(%s); ?>',
                    $entry
                );
            },
            $content
        );
    }

    /*
     * @url('path/to/page')
     * Generate full URL
     */
    private function compileUrl(string $content): string
    {
        return preg_replace_callback(
            '/@url\s*\(\s*[\'"](.+?)[\'"]\s*\)/s',
            function ($matches) {
                $path = $matches[1];

                return sprintf(
                    '<?php echo rtrim($_SERVER[\'REQUEST_SCHEME\'] . \'://\' . $_SERVER[\'HTTP_HOST\'], \'/\') . \'/%s\'; ?>',
                    ltrim(addslashes($path), '/')
                );
            },
            $content
        );
    }

    /*
     * @config('app.name')
     * Get config value
     */
    private function compileConfig(string $content): string
    {
        return preg_replace_callback(
            '/@config\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            function ($matches) {
                $key = $matches[1];
                $default = $matches[2] ?? null;

                if ($default !== null) {
                    return sprintf(
                        '<?php echo function_exists(\'config\') ? config(\'%s\', \'%s\') : \'%s\'; ?>',
                        addslashes($key),
                        addslashes($default),
                        addslashes($default)
                    );
                }

                return sprintf(
                    '<?php echo function_exists(\'config\') ? config(\'%s\') : \'\'; ?>',
                    addslashes($key)
                );
            },
            $content
        );
    }

    /*
     * @env('APP_NAME', 'Default')
     * Get environment variable
     */
    private function compileEnv(string $content): string
    {
        return preg_replace_callback(
            '/@env\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            function ($matches) {
                $key = $matches[1];
                $default = $matches[2] ?? '';

                return sprintf(
                    '<?php echo $_ENV[\'%s\'] ?? getenv(\'%s\') ?: \'%s\'; ?>',
                    addslashes($key),
                    addslashes($key),
                    addslashes($default)
                );
            },
            $content
        );
    }

    private function cacheCompilation(string $key, string $content): void
    {
        if (count($this->compilationCache) >= self::MAX_CACHE_SIZE) {
            $this->compilationCache = array_slice($this->compilationCache, -500, null, true);
        }

        $this->compilationCache[$key] = $content;
    }

    private function compileFormHelpers(string $content): string
    {
        // @checked(condition)
        $content = preg_replace(
            '/@checked\s*\((.+?)\)/',
            '<?php if($1) echo "checked"; ?>',
            $content
        );

        // @selected(condition)
        $content = preg_replace(
            '/@selected\s*\((.+?)\)/',
            '<?php if($1) echo "selected"; ?>',
            $content
        );

        // @disabled(condition)
        $content = preg_replace(
            '/@disabled\s*\((.+?)\)/',
            '<?php if($1) echo "disabled"; ?>',
            $content
        );

        // @readonly(condition)
        $content = preg_replace(
            '/@readonly\s*\((.+?)\)/',
            '<?php if($1) echo "readonly"; ?>',
            $content
        );

        // @required(condition)
        $content = preg_replace(
            '/@required\s*\((.+?)\)/',
            '<?php if($1) echo "required"; ?>',
            $content
        );

        return $content;
    }

    private function compileInject(string $content): string
    {
        return preg_replace_callback(
            '/@inject\s*\(\s*[\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $variable = $matches[1];
                $service = $matches[2];

                return sprintf(
                    '<?php $%s = \Plugs\Container\Container::getInstance()->make(\'%s\'); ?>',
                    $variable,
                    $service
                );
            },
            $content
        );
    }

    private function compileStyle(string $content): string
    {
        return preg_replace_callback(
            '/@style\s*\((\[.+?\])\)/s',
            function ($matches) {
                return sprintf(
                    '<?php echo \'style="\' . implode(\'; \', array_filter(array_map(function($k, $v) {
                    return is_int($k) ? $v : ($v ? $k : null);
                }, array_keys(%s), array_values(%s)))) . \'"\'; ?>',
                    $matches[1],
                    $matches[1]
                );
            },
            $content
        );
    }

    // ============================================
    // NEW ADVANCED DIRECTIVES
    // ============================================

    /**
     * Compile @props directive for component default props
     * Usage: @props(['type' => 'primary', 'size' => 'md'])
     */
    private function compileProps(string $content): string
    {
        return preg_replace_callback(
            self::$patterns['props'] ?? '/@props\s*\((\[.+?\])\)/s',
            function ($matches) {
                $defaults = $matches[1];

                return sprintf(
                    '<?php $__props = %s; foreach ($__props as $__key => $__default) { if (!isset($$__key)) { $$__key = $__default; } } unset($__props, $__key, $__default); ?>',
                    $defaults
                );
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @fragment directive for HTMX/Turbo partial rendering
     * Usage: @fragment('sidebar') ... @endfragment
     */
    private function compileFragment(string $content): string
    {
        // Start fragment
        $content = preg_replace_callback(
            self::$patterns['fragment'] ?? '/@fragment\s*\([\'"](.+?)[\'"]\)/s',
            function ($matches) {
                $name = addslashes($matches[1]);

                return sprintf(
                    '<?php $__fragmentRenderer = $__fragmentRenderer ?? new \Plugs\View\FragmentRenderer(); $__fragmentRenderer->startFragment(\'%s\'); ?>',
                    $name
                );
            },
            $content
        ) ?? $content;

        // End fragment
        $content = preg_replace(
            self::$patterns['endfragment'] ?? '/@endfragment\s*/',
            '<?php echo $__fragmentRenderer->endFragment(); ?>',
            $content
        ) ?? $content;

        return $content;
    }

    /**
     * Compile @teleport directive for content relocation
     * Usage: @teleport('#modals') ... @endteleport
     */
    private function compileTeleport(string $content): string
    {
        // Start teleport
        $content = preg_replace_callback(
            self::$patterns['teleport'] ?? '/@teleport\s*\([\'"](.+?)[\'"]\)/s',
            function ($matches) {
                $target = addslashes($matches[1]);

                return sprintf(
                    '<?php $__fragmentRenderer = $__fragmentRenderer ?? new \Plugs\View\FragmentRenderer(); $__fragmentRenderer->startTeleport(\'%s\'); ?>',
                    $target
                );
            },
            $content
        ) ?? $content;

        // End teleport
        $content = preg_replace_callback(
            self::$patterns['endteleport'] ?? '/@endteleport\s*/',
            function ($matches) {
                return '<?php $__fragmentRenderer->endTeleport(); ?>';
            },
            $content
        ) ?? $content;

        return $content;
    }

    /**
     * Compile @cache directive for caching view blocks
     * Usage: @cache('sidebar', 3600) ... @endcache
     * Usage: @cache('sidebar') ... @endcache (uses default TTL)
     */
    private function compileCacheBlocks(string $content): string
    {
        // Start cache block
        $content = preg_replace_callback(
            self::$patterns['cache'] ?? '/@cache\s*\([\'"](.+?)[\'"](?:\s*,\s*(\d+))?\)/s',
            function ($matches) {
                $key = addslashes($matches[1]);
                $ttl = $matches[2] ?? 'null';

                return sprintf(
                    '<?php $__cacheKey = \'%s\'; $__cacheTtl = %s; if (isset($__viewCache) && $__viewCache->has($__cacheKey)) { echo $__viewCache->get($__cacheKey); } else { ob_start(); ?>',
                    $key,
                    $ttl
                );
            },
            $content
        ) ?? $content;

        // End cache block
        $content = preg_replace_callback(
            self::$patterns['endcache'] ?? '/@endcache\s*/',
            function ($matches) {
                return '<?php $__cacheContent = ob_get_clean(); if (isset($__viewCache)) { $__viewCache->put($__cacheKey, $__cacheContent, $__cacheTtl ?? null); } echo $__cacheContent; } ?>';
            },
            $content
        ) ?? $content;

        return $content;
    }

    /**
     * Compile @lazy directive for lazy loading components
     * Usage: @lazy('heavy-component', ['data' => $data])
     * Usage: @lazy('heavy-component')
     */
    private function compileLazy(string $content): string
    {
        return preg_replace_callback(
            self::$patterns['lazy'] ?? '/@lazy\s*\([\'"](.+?)[\'"](?:\s*,\s*(\[.+?\]))?\)/s',
            function ($matches) {
                $component = addslashes($matches[1]);
                $data = $matches[2] ?? '[]';
                $uniqueId = 'lazy_' . substr(md5($component . uniqid()), 0, 8);

                return sprintf(
                    '<?php 
                    $__lazyId = \'%s\';
                    $__lazyComponent = \'%s\';
                    $__lazyData = %s;
                    echo \'<div id="\' . $__lazyId . \'" data-lazy-component="\' . $__lazyComponent . \'" data-lazy-data="\' . htmlspecialchars(json_encode($__lazyData), ENT_QUOTES) . \'">\';
                    echo \'<div class="lazy-placeholder" style="min-height: 50px; background: #f0f0f0; border-radius: 4px; display: flex; align-items: center; justify-content: center;">\';
                    echo \'<span style="color: #666;">Loading...</span>\';
                    echo \'</div></div>\';
                    unset($__lazyId, $__lazyComponent, $__lazyData);
                    ?>',
                    $uniqueId,
                    $component,
                    $data
                );
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @aware directive to access parent component data
     * Usage: @aware(['theme', 'user'])
     */
    private function compileAware(string $content): string
    {
        return preg_replace_callback(
            self::$patterns['aware'] ?? '/@aware\s*\((\[.+?\])\)/s',
            function ($matches) {
                $keys = $matches[1];

                return sprintf(
                    '<?php $__awareData = \Plugs\View\ViewCompiler::getParentData(%s); extract($__awareData, EXTR_SKIP); unset($__awareData); ?>',
                    $keys
                );
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @sanitize directive for HTML sanitization
     * Usage: @sanitize($userContent)
     * Usage: @sanitize($userContent, 'strict')
     */
    private function compileSanitize(string $content): string
    {
        return preg_replace_callback(
            '/@sanitize\s*\((.+?)(?:\s*,\s*[\'"](.+?)[\'"])?\)/s',
            function ($matches) {
                $input = trim($matches[1]);
                $mode = $matches[2] ?? 'default';

                // Define allowed tags based on mode
                $allowedTags = match ($mode) {
                    'strict' => '',
                    'basic' => '<p><br><strong><em><b><i>',
                    'rich' => '<p><br><strong><em><b><i><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><code><pre>',
                    default => '<p><br><strong><em><b><i><ul><ol><li><a>',
                };

                return sprintf(
                    '<?php 
                    $__sanitized = strip_tags(%s, \'%s\'); 
                    // Remove potentially dangerous event handlers and javascript: links
                    $__sanitized = preg_replace(\'/\s+on\w+\s*=\s*(["\\\'])(?:(?!\1).)*\1/i\', \'\', $__sanitized);
                    $__sanitized = preg_replace(\'/(href|src|style)\s*=\s*(["\\\'])\s*(javascript|data|vbscript):(?:(?!\2).)*\2/i\', \'\', $__sanitized);
                    echo $__sanitized;
                    unset($__sanitized);
                    ?>',
                    $input,
                    $allowedTags
                );
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @csp directive for automatic Content-Security-Policy meta tag
     */
    private function compileCsp(string $content): string
    {
        return preg_replace_callback(
            self::$patterns['csp'] ?? '/@csp\s*/',
            function ($matches) {
                return '<?php echo \'<meta http-equiv="Content-Security-Policy" content="default-src \\\'self\\\'; script-src \\\'self\\\' \\\'nonce-\' . ($__cspNonce ?? "") . \'\\\'; style-src \\\'self\\\' \\\'unsafe-inline\\\'; img-src \\\'self\\\' data:;">\'; ?>';
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @id directive for safe element IDs
     */
    private function compileId(string $content): string
    {
        return preg_replace_callback(
            self::$patterns['id'] ?? '/@id\s*\((.+?)\)/s',
            function ($matches) {
                return sprintf('<?php echo \Plugs\View\Escaper::id(%s); ?>', $matches[1]);
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @entangle directive for Livewire-style two-way binding
     * Usage: wire:model="@entangle('property')"
     */
    private function compileEntangle(string $content): string
    {
        return preg_replace_callback(
            self::$patterns['entangle'] ?? '/@entangle\s*\([\'"](.+?)[\'"]\)/s',
            function ($matches) {
                $property = addslashes($matches[1]);

                return sprintf(
                    '<?php echo "data-entangle=\"%s\" data-entangle-value=\"" . htmlspecialchars(json_encode($%s ?? null), ENT_QUOTES) . "\""; ?>',
                    $property,
                    $property
                );
            },
            $content
        ) ?? $content;
    }

    // ============================================
    // ADDITIONAL FORM DIRECTIVES
    // ============================================

    /**
     * Compile @autofocus directive
     * Usage: @autofocus($condition) or @autofocus
     */
    private function compileAutofocus(string $content): string
    {
        return preg_replace(
            '/@autofocus(?:\s*\((.+?)\))?/',
            '<?php if(${1:-true}) echo "autofocus"; ?>',
            $content
        ) ?? $content;
    }

    /**
     * Compile @readtime directive
     * Calculates estimated reading time for content
     *
     * Usage: @readtime($content)
     * Usage: @readtime($content, 200) - custom words per minute
     * Usage: @readtime($content, 200, 'short') - format: 'short', 'long', 'minutes'
     *
     * Output examples:
     * - short: "5 min read"
     * - long: "5 minutes read"
     * - minutes: "5"
     */
    private function compileReadTime(string $content): string
    {
        return preg_replace_callback(
            '/@readtime\s*\((.+?)(?:\s*,\s*(\d+))?(?:\s*,\s*[\'"](.+?)[\'"])?\)/s',
            function ($matches) {
                $input = trim($matches[1]);
                $wpm = $matches[2] ?? '200';
                $format = $matches[3] ?? 'short';

                return sprintf(
                    '<?php
                    echo (function($text, $wpm, $format) {
                        // Strip HTML and count words
                        $plainText = strip_tags($text);
                        $wordCount = str_word_count($plainText);
                        $minutes = max(1, (int) ceil($wordCount / $wpm));
                        
                        return match($format) {
                            "minutes" => (string) $minutes,
                            "long" => $minutes . " " . ($minutes === 1 ? "minute" : "minutes") . " read",
                            default => $minutes . " min read",
                        };
                    })(%s, %s, \'%s\');
                    ?>',
                    $input,
                    $wpm,
                    $format
                );
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @wordcount directive
     * Returns the word count of content
     *
     * Usage: @wordcount($content)
     */
    private function compileWordCount(string $content): string
    {
        return preg_replace_callback(
            '/@wordcount\s*\((.+?)\)/s',
            function ($matches) {
                $input = trim($matches[1]);

                return sprintf(
                    '<?php echo str_word_count(strip_tags(%s)); ?>',
                    $input
                );
            },
            $content
        ) ?? $content;
    }

    // ============================================
    // AUTHENTICATION & AUTHORIZATION DIRECTIVES
    // ============================================

    /**
     * Compile @auth / @endauth directives
     * Usage: @auth ... @endauth
     * Usage: @auth('admin') ... @endauth
     */
    private function compileAuthDirectives(string $content): string
    {
        // @auth('guard') or @auth
        $content = preg_replace_callback(
            '/@auth\s*(?:\(\s*[\'"](.+?)[\'"]\s*\))?/',
            function ($matches) {
                $guard = $matches[1] ?? 'null';
                if ($guard !== 'null') {
                    $guard = "'{$guard}'";
                }
                return "<?php if(function_exists('auth') && auth({$guard})->check()): ?>";
            },
            $content
        ) ?? $content;

        // @guest('guard') or @guest
        $content = preg_replace_callback(
            '/@guest\s*(?:\(\s*[\'"](.+?)[\'"]\s*\))?/',
            function ($matches) {
                $guard = $matches[1] ?? 'null';
                if ($guard !== 'null') {
                    $guard = "'{$guard}'";
                }
                return "<?php if(!function_exists('auth') || auth({$guard})->guest()): ?>";
            },
            $content
        ) ?? $content;

        // @role('admin')
        $content = preg_replace_callback(
            '/@role\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $role = addslashes($matches[1]);
                return "<?php if(function_exists('auth') && auth()->check() && auth()->user()->hasRole('{$role}')): ?>";
            },
            $content
        ) ?? $content;

        // @can('ability')
        $content = preg_replace_callback(
            '/@can\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $ability = addslashes($matches[1]);
                return "<?php if(function_exists('auth') && auth()->check() && auth()->user()->can('{$ability}')): ?>";
            },
            $content
        ) ?? $content;

        // @cannot('ability')
        $content = preg_replace_callback(
            '/@cannot\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $ability = addslashes($matches[1]);
                return "<?php if(!function_exists('auth') || !auth()->check() || !auth()->user()->can('{$ability}')): ?>";
            },
            $content
        ) ?? $content;

        // Batch all end-tag replacements in a single pass (order: longest match first)
        $content = strtr($content, [
            '@endcannot' => '<?php endif; ?>',
            '@endguest' => '<?php endif; ?>',
            '@endrole' => '<?php endif; ?>',
            '@endauth' => '<?php endif; ?>',
            '@endcan' => '<?php endif; ?>',
        ]);

        return $content;
    }

    // ============================================
    // ENVIRONMENT CONDITIONAL DIRECTIVES
    // ============================================

    /**
     * Compile @production / @endproduction, @local / @endlocal,
     * @envIs('staging') / @endenvIs directives
     */
    private function compileEnvironmentDirectives(string $content): string
    {
        // @production ... @endproduction
        $content = str_replace(
            '@production',
            "<?php if((getenv('APP_ENV') ?: (\$_ENV['APP_ENV'] ?? 'production')) === 'production'): ?>",
            $content
        );


        // @local ... @endlocal
        $content = str_replace(
            '@local',
            "<?php if(in_array(getenv('APP_ENV') ?: (\$_ENV['APP_ENV'] ?? 'production'), ['local', 'development'])): ?>",
            $content
        );

        // @envIs('staging') ... @endenvIs
        $content = preg_replace_callback(
            '/@envIs\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $env = addslashes($matches[1]);
                return "<?php if((getenv('APP_ENV') ?: (\$_ENV['APP_ENV'] ?? 'production')) === '{$env}'): ?>";
            },
            $content
        ) ?? $content;

        // @debug ... @enddebug (shows only when APP_DEBUG is true)
        $content = str_replace(
            '@debug',
            "<?php if(function_exists('config') ? config('app.debug', false) : (filter_var(getenv('APP_DEBUG') ?: (\$_ENV['APP_DEBUG'] ?? false), FILTER_VALIDATE_BOOLEAN))): ?>",
            $content
        );

        // Batch all end-tag replacements in a single pass
        $content = strtr($content, [
            '@endproduction' => '<?php endif; ?>',
            '@endlocal' => '<?php endif; ?>',
            '@endenvIs' => '<?php endif; ?>',
            '@enddebug' => '<?php endif; ?>',
        ]);

        return $content;
    }

    // ============================================
    // SECURITY VIEW DIRECTIVES
    // ============================================

    /**
     * Compile @nonce — outputs the CSP nonce for inline scripts/styles
     * Usage: <script nonce="@nonce">...</script>
     */
    private function compileNonce(string $content): string
    {
        return str_replace(
            '@nonce',
            '<?php echo isset($__cspNonce) ? $__cspNonce : ""; ?>',
            $content
        );
    }

    /**
     * Compile @honeypot — outputs a hidden anti-spam field
     * Usage: @honeypot or @honeypot('custom_field_name')
     */
    private function compileHoneypot(string $content): string
    {
        return preg_replace_callback(
            '/@honeypot\s*(?:\(\s*[\'"](.+?)[\'"]\s*\))?/',
            function ($matches) {
                $fieldName = $matches[1] ?? '_hp_' . substr(md5(uniqid()), 0, 8);
                return '<div style="position:absolute;left:-9999px;top:-9999px;opacity:0;height:0;width:0;overflow:hidden;" aria-hidden="true">'
                    . '<input type="text" name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '" value="" tabindex="-1" autocomplete="off">'
                    . '</div>';
            },
            $content
        ) ?? $content;
    }

    // ============================================
    // UI UTILITY DIRECTIVES
    // ============================================

    /**
     * Compile @active directive — outputs 'active' class if route matches
     * Usage: <a class="@active('home')">Home</a>
     * Usage: <a class="@active('/dashboard', 'nav-active')">Dashboard</a>
     */
    private function compileActive(string $content): string
    {
        return preg_replace_callback(
            '/@active\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"]\s*)?\)/',
            function ($matches) {
                $route = addslashes($matches[1]);
                $class = $matches[2] ?? 'active';

                return "<?php echo (function_exists('request_path') ? request_path() : (\$_SERVER['REQUEST_URI'] ?? '')) === '/{$route}' || (function_exists('request_path') ? request_path() : (\$_SERVER['REQUEST_URI'] ?? '')) === '{$route}' ? '{$class}' : ''; ?>";
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @svg directive — inline SVG from resources/svg/
     * Usage: @svg('icon-name')
     * Usage: @svg('icon-name', 'w-6 h-6 text-blue-500')
     */
    private function compileSvg(string $content): string
    {
        return preg_replace_callback(
            '/@svg\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"]\s*)?\)/',
            function ($matches) {
                $icon = addslashes($matches[1]);
                $class = $matches[2] ?? '';

                return sprintf(
                    '<?php echo (function($name, $class) {
                    $paths = [
                        rtrim($_SERVER["DOCUMENT_ROOT"] ?? "", "/") . "/../resources/svg/" . $name . ".svg",
                        rtrim($_SERVER["DOCUMENT_ROOT"] ?? "", "/") . "/assets/svg/" . $name . ".svg",
                    ];
                    foreach ($paths as $path) {
                        if (file_exists($path)) {
                            $svg = file_get_contents($path);
                            if ($class) {
                                $svg = preg_replace("/<svg/", "<svg class=\"" . htmlspecialchars($class, ENT_QUOTES) . "\"", $svg, 1);
                            }
                            return $svg;
                        }
                    }
                    return "<!-- SVG \'$name\' not found -->";
                })(\'%s\', \'%s\'); ?>',
                    $icon,
                    addslashes($class)
                );
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @skeleton directive — outputs a CSS skeleton loader placeholder
     * Usage: @skeleton('text')
     * Usage: @skeleton('avatar', '48px')
     * Usage: @skeleton('image', '100%', '200px')
     * Usage: @skeleton('text-dark')
     */
    private function compileSkeleton(string $content): string
    {
        return preg_replace_callback(
            '/@skeleton\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"]\s*)?(?:,\s*[\'"](.+?)[\'"]\s*)?\)/s',
            function ($matches) {
                return $this->getSkeletonHtml($matches[1], $matches[2] ?? '100%', $matches[3] ?? '20px');
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @confirm directive — adds onclick confirmation
     * Usage: <button @confirm('Are you sure?')>Delete</button>
     */
    private function compileConfirm(string $content): string
    {
        return preg_replace_callback(
            '/@confirm\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $message = htmlspecialchars($matches[1], ENT_QUOTES);
                return 'onclick="return confirm(\'' . addslashes($message) . '\')"';
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile @tooltip directive — adds data-tooltip attribute
     * Usage: <span @tooltip('Helpful tip')>Info</span>
     */
    private function compileTooltip(string $content): string
    {
        return preg_replace_callback(
            '/@tooltip\s*\(\s*[\'"](.+?)[\'"]\s*\)/',
            function ($matches) {
                $text = htmlspecialchars($matches[1], ENT_QUOTES);
                return 'title="' . $text . '" data-tooltip="' . $text . '"';
            },
            $content
        ) ?? $content;
    }

    // ============================================
    // DEVELOPMENT/DEBUG DIRECTIVES
    // ============================================

    /**
     * Compile @dump directive — inline dump without dying (debug mode only)
     * Usage: @dump($variable)
     */
    private function compileDump(string $content): string
    {
        return preg_replace_callback(
            '/@dump\s*\((.+?)\)/',
            function ($matches) {
                $var = trim($matches[1]);
                return sprintf(
                    '<?php if(function_exists("config") ? config("app.debug", false) : true) { echo "<pre style=\"background:#1e1e2e;color:#cdd6f4;padding:12px;border-radius:8px;font-size:13px;overflow-x:auto;margin:8px 0;\">"; var_export(%s); echo "</pre>"; } ?>',
                    $var
                );
            },
            $content
        ) ?? $content;
    }

    // ============================================
    // CONTENT DIRECTIVES
    // ============================================

    /**
     * Compile @markdown / @endmarkdown — parse markdown to HTML
     * Usage: @markdown ... @endmarkdown
     * Supports: headings, bold, italic, links, code blocks, lists, blockquotes, hr
     */
    private function compileMarkdown(string $content): string
    {
        return preg_replace_callback(
            '/@markdown\s*\n?(.*?)@endmarkdown/s',
            function ($matches) {
                return $this->renderMarkdown($matches[1]);
            },
            $content
        ) ?? $content;
    }

    /**
     * Compile <markdown> tag
     */
    private function compileMarkdownTag(string $content): string
    {
        return preg_replace_callback(
            '/<markdown\s*>(.*?)<\/markdown>/s',
            function ($matches) {
                return $this->renderMarkdown($matches[1]);
            },
            $content
        ) ?? $content;
    }

    private function compileRawDirective(string $content): string
    {
        return preg_replace_callback(self::$patterns['raw'], function ($matches) {
            return "<?php echo {$matches[1]}; ?>";
        }, $content);
    }

    private function renderMarkdown(string $markdown): string
    {
        $md = addslashes(trim($markdown));

        return sprintf(
            '<?php echo (function($md) {
                // Code blocks (fenced)
                $md = preg_replace_callback("/```(\\\\w+)?\\\\n(.*?)```/s", function($m) {
                    $lang = $m[1] ?? "";
                    return "<pre><code class=\"language-" . htmlspecialchars($lang) . "\">" . htmlspecialchars($m[2]) . "</code></pre>";
                }, $md);
                // Inline code
                $md = preg_replace("/`([^`]+)`/", "<code>$1</code>", $md);
                // Headings
                $md = preg_replace("/^######\\\\s+(.+)$/m", "<h6>$1</h6>", $md);
                $md = preg_replace("/^#####\\\\s+(.+)$/m", "<h5>$1</h5>", $md);
                $md = preg_replace("/^####\\\\s+(.+)$/m", "<h4>$1</h4>", $md);
                $md = preg_replace("/^###\\\\s+(.+)$/m", "<h3>$1</h3>", $md);
                $md = preg_replace("/^##\\\\s+(.+)$/m", "<h2>$1</h2>", $md);
                $md = preg_replace("/^#\\\\s+(.+)$/m", "<h1>$1</h1>", $md);
                // Bold and italic
                $md = preg_replace("/\\\\*\\\\*\\\\*(.+?)\\\\*\\\\*\\\\*/s", "<strong><em>$1</em></strong>", $md);
                $md = preg_replace("/\\\\*\\\\*(.+?)\\\\*\\\\*/s", "<strong>$1</strong>", $md);
                $md = preg_replace("/\\\\*(.+?)\\\\*/s", "<em>$1</em>", $md);
                // Links
                $md = preg_replace("/\\\\[([^\\\\]]+)\\\\]\\\\(([^)]+)\\\\)/", "<a href=\\\"$2\\\">$1</a>", $md);
                // Images
                $md = preg_replace("/!\\\\[([^\\\\]]*?)\\\\]\\\\(([^)]+)\\\\)/", "<img src=\\\"$2\\\" alt=\\\"$1\\\">", $md);
                // Blockquotes
                $md = preg_replace("/^>\\\\s+(.+)$/m", "<blockquote>$1</blockquote>", $md);
                // Horizontal rule
                $md = preg_replace("/^---$/m", "<hr>", $md);
                // Unordered lists
                $md = preg_replace("/^[\\\\-\\\\*]\\\\s+(.+)$/m", "<li>$1</li>", $md);
                $md = preg_replace("/((?:<li>.*?<\\\\/li>\\\\n?)+)/s", "<ul>$1</ul>", $md);
                // Paragraphs
                $md = preg_replace("/\\\\n\\\\n+/", "</p><p>", trim($md));
                $md = "<p>" . $md . "</p>";
                $md = str_replace("<p></p>", "", $md);
                return $md;
            })(\'%s\'); ?>',
            $md
        );
    }

    /**
     * Internal helper for generating skeleton HTML
     */
    private function getSkeletonHtml(string $type, string $width = '100%', string $height = '20px'): string
    {
        $style = 'background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: skeleton-pulse 1.5s ease-in-out infinite;';
        $border = 'border-radius: 4px;';

        switch ($type) {
            case 'avatar':
            case 'avatar-dark':
                $width = $height = ($width !== '100%' && $width !== '') ? $width : '48px';
                $border = 'border-radius: 50%;';
                break;
            case 'text':
            case 'text-dark':
                $height = ($height !== '20px' && $height !== '') ? $height : '16px';
                break;
            case 'image':
            case 'image-dark':
                $height = ($height !== '20px' && $height !== '') ? $height : '200px';
                break;
            case 'button':
            case 'button-dark':
                $height = ($height !== '20px' && $height !== '') ? $height : '40px';
                $width = ($width !== '100%' && $width !== '') ? $width : '120px';
                $border = 'border-radius: 8px;';
                break;
        }

        // Support for dark mode
        if (str_contains($type, 'dark')) {
            $style = 'background: linear-gradient(90deg, #333 25%, #444 50%, #333 75%); background-size: 200% 100%; animation: skeleton-pulse 1.5s ease-in-out infinite;';
        }

        return sprintf(
            '<style>@keyframes skeleton-pulse{0%%{background-position:200%% 0}100%%{background-position:-200%% 0}}</style>' .
            '<div class="skeleton-loader %s" style="display:inline-block;width:%s;height:%s;%s%s"></div>',
            htmlspecialchars($type, ENT_QUOTES),
            htmlspecialchars($width, ENT_QUOTES),
            htmlspecialchars($height, ENT_QUOTES),
            $border,
            $style
        );
    }
}
