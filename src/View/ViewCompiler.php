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
*/

class ViewCompiler
{
    private $sectionStack = [];

    public function compile(string $content): string
    {
        $content = $this->compileComments($content);
        $content = $this->compileEchos($content);
        $content = $this->compileEscapedEchos($content);
        $content = $this->compileConditionals($content);
        $content = $this->compileLoops($content);
        $content = $this->compileIncludes($content);
        $content = $this->compileSections($content);
        $content = $this->compileCsrf($content);
        $content = $this->compileMethod($content);
        $content = $this->compilePhp($content);

        return $content;
    }

    protected function compileComments(string $content): string
    {
        return preg_replace('/\{\{--(.+?)--\}\}/s', '', $content);
    }

    protected function compileEchos(string $content): string
    {
        return preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $content);
    }

    protected function compileEscapedEchos(string $content): string
    {
        return preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>', $content);
    }

    protected function compileConditionals(string $content): string
    {
        // @if
        $content = preg_replace('/\@if\s*\((.+?)\)/s', '<?php if ($1): ?>', $content);

        // @elseif
        $content = preg_replace('/\@elseif\s*\((.+?)\)/s', '<?php elseif ($1): ?>', $content);

        // @else
        $content = preg_replace('/\@else\b/', '<?php else: ?>', $content);

        // @endif
        $content = preg_replace('/\@endif\b/', '<?php endif; ?>', $content);

        // @unless
        $content = preg_replace('/\@unless\s*\((.+?)\)/s', '<?php if (!($1)): ?>', $content);

        // @endunless
        $content = preg_replace('/\@endunless\b/', '<?php endif; ?>', $content);

        // @isset
        $content = preg_replace('/\@isset\s*\((.+?)\)/s', '<?php if (isset($1)): ?>', $content);

        // @endisset
        $content = preg_replace('/\@endisset\b/', '<?php endif; ?>', $content);

        // @empty
        $content = preg_replace('/\@empty\s*\((.+?)\)/s', '<?php if (empty($1)): ?>', $content);

        // @endempty
        $content = preg_replace('/\@endempty\b/', '<?php endif; ?>', $content);

        return $content;
    }

    protected function compileLoops(string $content): string
    {
        // @foreach
        $content = preg_replace('/\@foreach\s*\((.+?)\)/s', '<?php foreach ($1): ?>', $content);

        // @endforeach
        $content = preg_replace('/\@endforeach\b/', '<?php endforeach; ?>', $content);

        // @for
        $content = preg_replace('/\@for\s*\((.+?)\)/s', '<?php for ($1): ?>', $content);

        // @endfor
        $content = preg_replace('/\@endfor\b/', '<?php endfor; ?>', $content);

        // @while
        $content = preg_replace('/\@while\s*\((.+?)\)/s', '<?php while ($1): ?>', $content);

        // @endwhile
        $content = preg_replace('/\@endwhile\b/', '<?php endwhile; ?>', $content);

        return $content;
    }

    protected function compileIncludes(string $content): string
    {
        return preg_replace_callback(
            '/\@include\s*\([\'"](.+?)[\'"]\s*(?:,\s*(\[.+?\]))?\)/s',
            function ($matches) {
                $view = addslashes($matches[1]);
                $data = $matches[2] ?? '[]';

                // Use the view engine instance passed in the data
                return "<?php if (isset(\$view)) { " .
                    "echo \$view->render('{$view}', array_merge(get_defined_vars(), {$data})); " .
                    "} ?>";
            },
            $content
        );
    }

    protected function compileSections(string $content): string
    {
        // @extends - needs to be processed first
        $content = preg_replace_callback(
            '/\@extends\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                return "<?php \$__extends = '" . addslashes($matches[1]) . "'; ?>";
            },
            $content
        );

        // @section with inline content - @section('name', 'content')
        $content = preg_replace_callback(
            '/\@section\s*\([\'"](.+?)[\'"]\s*,\s*[\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                $content = addslashes($matches[2]);
                return "<?php \$__sections['{$name}'] = '{$content}'; ?>";
            },
            $content
        );

        // @section with block content
        $content = preg_replace_callback(
            '/\@section\s*\([\'"](.+?)[\'"]\)/',
            function ($matches) {
                $name = addslashes($matches[1]);
                return "<?php \$__currentSection = '{$name}'; ob_start(); ?>";
            },
            $content
        );

        // @endsection
        $content = preg_replace_callback(
            '/\@endsection\b/',
            function ($matches) {
                return "<?php if (isset(\$__currentSection)) { \$__sections[\$__currentSection] = ob_get_clean(); unset(\$__currentSection); } ?>";
            },
            $content
        );

        // @show - ends section and immediately displays it
        $content = preg_replace_callback(
            '/\@show\b/',
            function ($matches) {
                return "<?php if (isset(\$__currentSection)) { \$__sections[\$__currentSection] = ob_get_clean(); echo \$__sections[\$__currentSection]; unset(\$__currentSection); } ?>";
            },
            $content
        );

        // @yield with default content
        $content = preg_replace_callback(
            '/\@yield\s*\([\'"](.+?)[\'"]\s*(?:,\s*[\'"]?(.*?)[\'"]?)?\)/',
            function ($matches) {
                $section = addslashes($matches[1]);
                $default = isset($matches[2]) ? addslashes($matches[2]) : '';
                return "<?php echo \$__sections['{$section}'] ?? '{$default}'; ?>";
            },
            $content
        );

        // @parent - includes parent section content
        $content = preg_replace('/\@parent\b/', '<?php echo $__parentContent ?? \'\'; ?>', $content);

        return $content;
    }

    protected function compileCsrf(string $content): string
    {
        // Fixed: Use proper function check and fallback
        return preg_replace(
            '/\@csrf\b/',
            '<?php echo function_exists(\'csrf_field\') ? csrf_field() : \'<input type="hidden" name="_token" value="\' . ($_SESSION[\'csrf_token\'] ?? \'\') . \'">\'; ?>',
            $content
        );
    }

    protected function compileMethod(string $content): string
    {
        // Fixed: Properly escape quotes in the replacement
        return preg_replace(
            '/\@method\s*\([\'"](.+?)[\'"]\)/',
            '<?php echo \'<input type="hidden" name="_method" value="$1">\'; ?>',
            $content
        );
    }

    protected function compilePhp(string $content): string
    {
        // Fixed: Handle both inline and block PHP
        // Inline @php(...) syntax
        $content = preg_replace('/\@php\s*\((.+?)\)/s', '<?php $1 ?>', $content);

        // Block @php ... @endphp syntax
        $content = preg_replace('/\@php\b/', '<?php ', $content);
        $content = preg_replace('/\@endphp\b/', ' ?>', $content);

        return $content;
    }
}