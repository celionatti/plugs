<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static \Plugs\Pdf\Pdf loadView(string $view, array $data = [])
 * @method static \Plugs\Pdf\Pdf loadHtml(string $html)
 * @method static \Plugs\Pdf\Pdf setPaper(string $paper, string $orientation = 'portrait')
 * @method static \Plugs\Pdf\Pdf template(string $type, array $data = [])
 * @method static void render()
 * @method static string output()
 * @method static void stream(string $filename = 'document.pdf', array $options = ['Attachment' => 0])
 * @method static void download(string $filename = 'document.pdf')
 * @method static bool save(string $path)
 * 
 * @see \Plugs\Pdf\Pdf
 */
class Pdf extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'pdf';
    }
}
