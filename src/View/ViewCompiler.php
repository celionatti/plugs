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

    /**
     * Inline components registry
     */
    public static array $inlineComponents = [];

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
            'escaped_echo' => '/(?<!@)\{\{\s*(.*?)\s*\}\}/s',
            'inline_echo'  => '/(?<![@\{])\{\s*([a-zA-Z0-9_\.\>\<\=\!\?\:\+\-\*\/\(\)\[\]\'\"\$ ]+)\s*\}(?!\})/s',
            'raw_echo' => '/(?<!@)\{!!\s*(.*?)\s*!!\}/s',
            'legacy_raw_echo' => '/\{\{\{\s*(.*?)\s*\}\}\}/s',

            // Comment patterns
            'comment' => '/\{\{--.*?--\}\}/s',

            // Verbatim patterns
            'verbatim' => '/@verbatim(.*?)@endverbatim/s',

            // PHP patterns
            'php_block' => '/@php\s*\n?(.*?)\n?\s*@endphp/s',
            'php_inline' => '/@php\s*\((.+?)\)/s',

            // Component patterns
            'component_self_close' => '/<(x-[a-z0-9_\-\.]+|[A-Z][a-zA-Z0-9]*(?:::[A-Z][a-zA-Z0-9]*)*)((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\'))*?)\/>/',
            'component_with_content' => '/<(x-[a-z0-9_\-\.]+|[A-Z][a-zA-Z0-9]*(?:::[A-Z][a-zA-Z0-9]*)*)((?:\s+(?:[^>"\'\/]+|"[^"]*"|\'[^\']*\'))*?)>(.*?)<\/\1\s*>/s',

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
            'class' => '/@class\s*\((.+?)\)/s',
            'style' => '/@style\s*\((.+?)\)/s',
            'use' => '/@use\s*\((.+?)\)/s',

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
            'json' => '/@json\s*\((.+?)\)/s',
            'js' => '/@js\s*\((.+?)\)/s',
            't' => '/@t\s*\((.+?)\)/s',
            'debug' => '/@debug/s',
            'selected' => '/@selected\s*\((.+?)\)/s',
            'checked' => '/@checked\s*\((.+?)\)/s',
            'disabled' => '/@disabled\s*\((.+?)\)/s',
            'readonly' => '/@readonly\s*\((.+?)\)/s',
            'required' => '/@required\s*\((.+?)\)/s',
            'let' => '/@let\s+([a-zA-Z0-9_]+)\s*=\s*(.*?)(?=\r?\n|$)/s',
            'calc' => '/@calc\s+([a-zA-Z0-9_]+)\s*=\s*(.*?)(?=\r?\n|$)/s',
            'needs' => '/@needs\s+(.+?)(?=\r?\n|$)/s',
            'defaults' => '/@defaults\s*\((.*?)\)/s',
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

        // Phase 0.5: Scoped Styles (Before any tag replacements to ensure pure HTML targeting)
        $content = $this->compileScopedStyles($content);

        // Phase 0.6: Shorthands (@click -> p-click)
        $content = $this->compileShorthands($content);

        // Phase 0.75: Inline Components (Extract them before they are processed as standard directives or echoes)
        $content = $this->extractInlineComponents($content);

        // Phase 1: Tag Pre-processing (Convert ALL tags to @directives first)
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

    private function extractInlineComponents(string $content): string
    {
        // Matches: @component name(args) ... @endcomponent
        // Or just: @component name ... @endcomponent
        $pattern = '/@component\s+([a-zA-Z0-9_\-\.]+)(?:\s*\((.*?)\))?\s*(.*?)@endcomponent/is';

        $content = preg_replace_callback($pattern, function ($matches) {
            $name = trim($matches[1]);
            // $arguments = isset($matches[2]) ? trim($matches[2]) : ''; // (syntactic sugar)
            $body = $matches[3];

            // Register in the registry
            self::$inlineComponents[$name] = $body;

            // Remove it from the compiled HTML output
            return '';
        }, $content);

        return $content;
    }

    private function compileScopedStyles(string $content): string
    {
        // Check if <style scoped> exists
        if (!preg_match('/<style\s+scoped[^>]*>(.*?)<\/style>/is', $content, $matches)) {
            return $content; // No scoped styles found
        }

        // Generate a unique scope ID for this view based on its starting content
        $scopeId = 'data-v-' . substr(self::fastHash($content), 0, 8);

        // 1. Rewrite the CSS block
        $content = preg_replace_callback('/<style\s+scoped[^>]*>(.*?)<\/style>/is', function ($styleMatches) use ($scopeId) {
            $css = $styleMatches[1];

            // A regex to locate CSS blocks: 'selector { rules }'
            // Captures the selector string in group 1, and the rules in group 2
            $css = preg_replace_callback('/([^{]+)\{([^}]*)\}/s', function ($ruleMatches) use ($scopeId) {
                $selectorsRaw = trim($ruleMatches[1]);
                $rules = $ruleMatches[2];

                // Ignore media queries and keyframes (we don't scope the @media rule itself)
                if (str_starts_with($selectorsRaw, '@')) {
                    if (str_starts_with($selectorsRaw, '@font-face') || str_starts_with($selectorsRaw, '@keyframes')) {
                        return $ruleMatches[0];
                    }
                    // For @media, we'd ideally parse interior rules, but a simple regex might break.
                    // For a robust implementation we'll assume basic @media nesting isn't fully scoped via simple regex,
                    // or we skip scoping on standard @media wrappers for now, but usually in standard Vue, media queries wrap scoped css.
                    // To be safe, if a developer writes @media, we just leave the @media line alone,
                    // but since a regex `([^{]+)\{` matches the `@media (...) {`, the inside rules are treated as the 'rules' block.
                    // Wait, basic regex cannot parse nested braces { { } }. 
                    // Let's implement a safe standard scoped selector approach that works for typical classes.
                }

                // Split selectors by comma: .card, a:hover
                $selectors = explode(',', $selectorsRaw);
                $scopedSelectors = [];

                foreach ($selectors as $selector) {
                    $selector = trim($selector);
                    if (empty($selector)) continue;

                    // Don't scope root or keyframe steps
                    if (in_array($selector, [':root', 'from', 'to']) || preg_match('/^[0-9]+%$/', $selector)) {
                        $scopedSelectors[] = $selector;
                        continue;
                    }

                    // Append the scope ID before any pseudo-classes (e.g., .card:hover -> .card[data-v-123]:hover)
                    if (strpos($selector, ':') !== false) {
                        $parts = explode(':', $selector, 2);
                        $scopedSelectors[] = $parts[0] . '[' . $scopeId . ']:' . $parts[1];
                    } else {
                        // Just append
                        $scopedSelectors[] = $selector . '[' . $scopeId . ']';
                    }
                }

                // Reconstruct rule
                return implode(', ', $scopedSelectors) . " {\n" . $rules . "\n}";
            }, $css);

            return "<style>\n" . $css . "\n</style>";
        }, $content);

        // 2. Inject $scopeId into all opening HTML tags
        // E.g., <div class="card"> -> <div class="card" data-v-xxx>
        // We match <tagName attributes> but ignore PHP tags and self-closing component tags (<x- )
        $content = preg_replace_callback('/<([a-zA-Z0-9\-]+)([^>]*?)\s*\/?>/s', function ($tagMatches) use ($scopeId) {
            $tagName = $tagMatches[1];
            $attributes = $tagMatches[2];
            $fullTag = $tagMatches[0];

            // Ignore PHP tags, components (<x-), slots, and other special framework tags
            if (
                in_array(strtolower($tagName), ['php', 'slot', 'auth', 'guest', 'fragment', 'teleport', 'style', 'script']) ||
                str_starts_with(strtolower($tagName), 'x-') ||
                str_starts_with($tagName, '@') || 
                str_starts_with($tagName, '?')
            ) {
                return $fullTag;
            }

            // Append scope ID. Check if self closing '/>' or '>'
            if (str_ends_with($fullTag, '/>')) {
                return '<' . $tagName . $attributes . ' ' . $scopeId . ' />';
            }

            return '<' . $tagName . $attributes . ' ' . $scopeId . '>';
        }, $content);

        return $content;
    }

    private function extractComponentsWithSlots(string $content): string
    {
        // Regex to match attributes while ignoring '>' inside quotes
        $attrRegex = '((?:[^>"\']+|"[^"]*"|\'[^\']*\')*)';

        // 1. Self-closing components: <ComponentName attr="value" /> or <x-component /> or <Module::Component />
        $content = preg_replace_callback(
            '/<(x-[a-z0-9_\-\.:]+|[A-Z][a-zA-Z0-9]*(?:::[A-Z][a-zA-Z0-9]*)*)' . $attrRegex . '\/>/s',
            function ($matches) {
                $componentName = $matches[1];
                if (str_starts_with($componentName, 'x-')) {
                    $componentName = substr($componentName, 2);
                }
                // Don't replace :: with . here to preserve view namespaces (e.g. auth::input)
                $attributes = $matches[2];

                return $this->createComponentPlaceholder($componentName, trim($attributes), '');
            },
            $content
        ) ?? $content;

        // 2. Components with content: <ComponentName>...</ComponentName> or <x-component>...</x-component>
        $content = preg_replace_callback(
            '/<(x-[a-z0-9_\-\.:]+|[A-Z][a-zA-Z0-9]*(?:::[A-Z][a-zA-Z0-9]*)*)' . $attrRegex . '>(.*?)<\/\1\s*>/s',
            function ($matches) {
                $componentName = $matches[1];
                if (str_starts_with($componentName, 'x-')) {
                    $componentName = substr($componentName, 2);
                }
                // Don't replace :: with . here to preserve view namespaces
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
        $content = $this->compileLet($content);
        $content = $this->compileNeeds($content);
        $content = $this->compileDefaults($content);

        // Phase 4: Authorization & Environment Compilation
        $content = $this->compileAuthDirectives($content);
        $content = $this->compileEnvironmentDirectives($content);

        // Phase 5: Forms & UI Utils Compilation
        $content = $this->compileCsrf($content);
        $content = $this->compileMethod($content);
        $content = $this->compileFormHelpers($content);
        $content = $this->compileOnce($content);
        $content = $this->compileWait($content);
        $content = $this->compileStream($content);
        $content = $this->compileError($content);
        $content = $this->compileEndError($content);

        // Phase 6: Components & UI Elements Compilation
        $content = $this->compileFragment($content);
        $content = $this->compileTeleport($content);
        $content = $this->compileCacheBlocks($content);
        $content = $this->compileLive($content);
        $content = $this->compileLazy($content);
        $content = $this->compileAware($content);
        $content = $this->compileId($content);
        $content = $this->compileSanitize($content);
        $content = $this->compileEntangle($content);
        $content = $this->compileActive($content);
        $content = $this->compileSvg($content);
        $content = $this->compileSkeleton($content);
        $content = $this->compileConfirm($content);
        $content = $this->compileTooltip($content);
        $content = $this->compileDump($content);
        $content = $this->compileMarkdown($content);

        // Phase 7: Assets, Security & Formatting
        $content = $this->compileAssets($content);
        $content = $this->compileNonce($content);
        $content = $this->compileCsp($content);
        $content = $this->compileHelperDirectives($content);
        $content = $this->compileReadTime($content);
        $content = $this->compileWordCount($content);
        $content = $this->compileCustomDirectives($content);
        $content = $this->compileShortAttributes($content);
        $content = $this->compileClass($content);
        $content = $this->compileStyle($content);
        $content = $this->compileUse($content);
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
        return preg_replace('/@wait\s*\((.+?)\)/', 'hx-trigger="wait:$1"', $content) ?? $content;
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
        $content = $this->compilePhpTag($content);          // <php>
        $content = $this->compileMarkdownTag($content);    // <markdown>
        $content = $this->compileAttributeDirectives($content); // :class, :style
        $content = $this->compileAutoCsp($content);        // Auto-inject CSP nonces
        return $content;
    }

    /**
     * Compile :class and :style attributes on any HTML tag.
     */
    protected function compileAttributeDirectives(string $content): string
    {
        // 1. Support :class="..."
        $content = preg_replace_callback('/<([a-zA-Z0-9-]+)(\s+[^>]*?):class=(["\'])(.*?)\3([^>]*?)>/is', function ($matches) {
            $tagName = $matches[1];
            $before = $matches[2];
            $expression = $matches[4];
            $after = $matches[5];

            $compiledClass = "class=\"<?php echo \Plugs\View\ComponentAttributes::escapeClass(\Plugs\View\ComponentAttributes::resolveClass($expression)); ?>\"";

            return "<{$tagName}{$before} {$compiledClass}{$after}>";
        }, $content) ?? $content;

        // 2. Support :style="..."
        $content = preg_replace_callback('/<([a-zA-Z0-9-]+)(\s+[^>]*?):style=(["\'])(.*?)\3([^>]*?)>/is', function ($matches) {
            $tagName = $matches[1];
            $before = $matches[2];
            $expression = $matches[4];
            $after = $matches[5];

            $compiledStyle = "style=\"<?php echo \Plugs\View\ComponentAttributes::escapeStyle(\Plugs\View\ComponentAttributes::resolveStyle($expression)); ?>\"";

            return "<{$tagName}{$before} {$compiledStyle}{$after}>";
        }, $content) ?? $content;

        return $content;
    }

    /**
     * Automatically inject CSP nonce into <script> and <style> tags if they lack one
     */
    protected function compileAutoCsp(string $content): string
    {
        // Add nonce to <script> tags safely avoiding those that already have a nonce
        $content = preg_replace_callback('/<script(?![^>]*\bnonce=)([^>]*)>/i', function ($matches) {
            $attributes = $matches[1];
            // Only inject if there's no src attribute or it's an inline script
            if (stripos($attributes, ' src=') === false || stripos($attributes, 'inline') !== false) {
                return sprintf('<script nonce="<?php echo $view->getCspNonce(); ?>"%s>', $attributes);
            }
            return $matches[0];
        }, $content) ?? $content;

        // Add nonce to <style> tags safely avoiding those that already have a nonce
        $content = preg_replace_callback('/<style(?![^>]*\bnonce=)([^>]*)>/i', function ($matches) {
            $attributes = $matches[1];
            return sprintf('<style nonce="<?php echo $view->getCspNonce(); ?>"%s>', $attributes);
        }, $content);

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

        $callback = function ($matches) use (&$expressionMap, &$expressionCounter) {
            $placeholder = sprintf('___EXPR_%d___', $expressionCounter);
            $expressionMap[$placeholder] = $this->convertDotSyntax(trim($matches[1]));
            $expressionCounter++;

            return $placeholder;
        };

        // Extract {{ ... }} expressions
        $attributes = preg_replace_callback(self::$patterns['escaped_echo'], $callback, $attributes);
        
        // Extract { ... } expressions (inline echo)
        $attributes = preg_replace_callback(self::$patterns['inline_echo'], $callback, $attributes);

        // Quoted attributes
        preg_match_all(
            '/([\w:@.-]+)\s*=\s*(["\'])(.*?)\2/is',
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
                $value = $this->convertDotSyntax($value);
                $hasExpression = true;
            }

            $result[$key] = [
                'value' => $value,
                'is_variable' => $hasExpression,
            ];
        }

        // Boolean attributes (flags)
        $withoutQuoted = preg_replace('/[\w:@.-]+\s*=\s*(?:(["\']).*?\1|\$[\w\[\]\'\"\-\>]+)/s', '', $attributes);
        preg_match_all('/([\w:@.-]+)/', $withoutQuoted, $matches);

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
