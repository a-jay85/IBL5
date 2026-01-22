<?php

declare(strict_types=1);

namespace View;

/**
 * Simple Blade-like Template Renderer
 *
 * Provides basic Blade template rendering for the hybrid PHP/Blade theme system.
 * Supports a subset of Blade syntax for gradual migration.
 *
 * Supported directives:
 * - {{ $var }} - Escaped output
 * - {!! $var !!} - Raw output
 * - @if / @else / @elseif / @endif
 * - @isset / @endisset
 * - @foreach / @endforeach
 * - @php / @endphp
 * - {{-- Comment --}}
 */
class BladeRenderer
{
    private string $viewPath;

    /** @var array<string, mixed> */
    private array $sharedData = [];

    public function __construct(string $viewPath = '')
    {
        $this->viewPath = $viewPath ?: dirname(__DIR__, 2) . '/themes';
    }

    /**
     * Render a view template
     *
     * @param string $view View path (e.g., 'IBL.partials.header')
     * @param array<string, mixed> $data Data to pass to the view
     * @return string Rendered HTML
     */
    public function render(string $view, array $data = []): string
    {
        $filePath = $this->resolveViewPath($view);

        if (!file_exists($filePath)) {
            throw new \RuntimeException("View not found: {$view} ({$filePath})");
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new \RuntimeException("Failed to read view: {$view}");
        }

        $compiled = $this->compile($content);

        return $this->evaluate($compiled, array_merge($this->sharedData, $data));
    }

    /**
     * Share data with all views
     *
     * @param string $key
     * @param mixed $value
     */
    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }

    /**
     * Convert view name to file path
     */
    private function resolveViewPath(string $view): string
    {
        // Convert dot notation to path
        $path = str_replace('.', DIRECTORY_SEPARATOR, $view);
        return $this->viewPath . DIRECTORY_SEPARATOR . $path . '.blade.php';
    }

    /**
     * Compile Blade syntax to PHP
     */
    private function compile(string $content): string
    {
        // Remove Blade comments
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);

        // Compile directives (order matters)
        $content = $this->compilePhp($content);
        $content = $this->compileConditionals($content);
        $content = $this->compileLoops($content);
        $content = $this->compileEchoes($content);

        return $content;
    }

    /**
     * Compile @php / @endphp blocks
     */
    private function compilePhp(string $content): string
    {
        $content = preg_replace('/@php/', '<?php', $content);
        $content = preg_replace('/@endphp/', '?>', $content);
        return $content;
    }

    /**
     * Compile conditional directives
     */
    private function compileConditionals(string $content): string
    {
        // @if
        $content = preg_replace('/@if\s*\((.+?)\)/', '<?php if($1): ?>', $content);

        // @elseif
        $content = preg_replace('/@elseif\s*\((.+?)\)/', '<?php elseif($1): ?>', $content);

        // @else
        $content = preg_replace('/@else/', '<?php else: ?>', $content);

        // @endif
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);

        // @isset
        $content = preg_replace('/@isset\s*\((.+?)\)/', '<?php if(isset($1)): ?>', $content);

        // @endisset
        $content = preg_replace('/@endisset/', '<?php endif; ?>', $content);

        // @empty
        $content = preg_replace('/@empty\s*\((.+?)\)/', '<?php if(empty($1)): ?>', $content);

        // @endempty
        $content = preg_replace('/@endempty/', '<?php endif; ?>', $content);

        return $content;
    }

    /**
     * Compile loop directives
     */
    private function compileLoops(string $content): string
    {
        // @foreach
        $content = preg_replace('/@foreach\s*\((.+?)\s+as\s+(.+?)\)/', '<?php foreach($1 as $2): ?>', $content);

        // @endforeach
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

        // @for
        $content = preg_replace('/@for\s*\((.+?)\)/', '<?php for($1): ?>', $content);

        // @endfor
        $content = preg_replace('/@endfor/', '<?php endfor; ?>', $content);

        // @while
        $content = preg_replace('/@while\s*\((.+?)\)/', '<?php while($1): ?>', $content);

        // @endwhile
        $content = preg_replace('/@endwhile/', '<?php endwhile; ?>', $content);

        return $content;
    }

    /**
     * Compile echo statements
     */
    private function compileEchoes(string $content): string
    {
        // {!! $var !!} - Raw/unescaped output
        $content = preg_replace('/\{!!\s*(.+?)\s*!!\}/', '<?php echo $1; ?>', $content);

        // {{ $var }} - Escaped output
        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>', $content);

        return $content;
    }

    /**
     * Evaluate compiled PHP code with data
     */
    private function evaluate(string $compiled, array $data): string
    {
        // Extract data to local scope
        extract($data);

        // Buffer output
        ob_start();

        try {
            // Evaluate the compiled template
            eval('?>' . $compiled);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException("Error evaluating template: " . $e->getMessage());
        }

        return ob_get_clean() ?: '';
    }

    /**
     * Factory method for quick rendering
     *
     * @param string $view View path
     * @param array<string, mixed> $data Data to pass to the view
     * @return string Rendered HTML
     */
    public static function make(string $view, array $data = []): string
    {
        $renderer = new self();
        return $renderer->render($view, $data);
    }
}
