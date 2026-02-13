<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Utils\Minifier;

class MakeEditorAssetCommand extends Command
{
  protected string $description = 'Create the plugs-editor.js and plugs-editor.css files in public plugs directory';


  public function handle(): int
  {
    $this->title('Editor Asset Generator');

    $jsDirectory = getcwd() . '/public/assets/js';
    $cssDirectory = getcwd() . '/public/assets/css';
    $jsFilename = 'plugs-editor.js';
    $cssFilename = 'plugs-editor.css';

    $shouldMinify = $this->hasOption('min');
    $force = $this->isForce();

    // Ensure directories exist
    if (!$this->ensureDirectory($jsDirectory)) {
      $this->error("Failed to create directory: {$jsDirectory}");
      return 1;
    }
    if (!$this->ensureDirectory($cssDirectory)) {
      $this->error("Failed to create directory: {$cssDirectory}");
      return 1;
    }

    $this->section('Publishing Assets');

    // JS
    $jsSource = dirname(__DIR__, 2) . '/Resources/assets/js/plugs-editor.js';
    $jsPath = $jsDirectory . '/' . $jsFilename;

    if (!file_exists($jsSource)) {
      $this->error("Source file not found: {$jsSource}");
      return 1;
    }

    $jsContent = file_get_contents($jsSource);
    $this->writeAsset($jsPath, $jsFilename, $jsContent, $force, $shouldMinify, 'js');

    // CSS
    $cssSource = dirname(__DIR__, 2) . '/Resources/assets/css/plugs-editor.css';
    $cssPath = $cssDirectory . '/' . $cssFilename;

    if (!file_exists($cssSource)) {
      $this->error("Source file not found: {$cssSource}");
      return 1;
    }

    $cssContent = file_get_contents($cssSource);
    $this->writeAsset($cssPath, $cssFilename, $cssContent, $force, $shouldMinify, 'css');

    $this->newLine();
    $this->info("Editor assets published successfully.");
    $this->info("The RichTextField component uses these assets from /assets/js/ and /assets/css/");
    $this->newLine();

    return 0;
  }

  protected function writeAsset(string $path, string $filename, string $content, bool $force, bool $shouldMinify, string $type): void
  {
    if (file_exists($path) && !$force) {
      if (!$this->confirm("File {$filename} already exists. Overwrite?", false)) {
        $this->warning("Skipped: {$filename}");
      } else {
        file_put_contents($path, $content);
        $this->success("Updated: {$path}");
      }
    } else {
      file_put_contents($path, $content);
      $this->success("Created: {$path}");
    }

    if ($shouldMinify) {
      $ext = pathinfo($filename, PATHINFO_EXTENSION);
      $minFilename = str_replace(".{$ext}", ".min.{$ext}", $filename);
      $minPath = dirname($path) . '/' . $minFilename;

      $minified = $type === 'js' ? Minifier::js($content) : Minifier::css($content);
      file_put_contents($minPath, $minified);
      $this->success("Created: {$minPath}");
    }
  }

  protected function defineOptions(): array
  {
    return [
      '--min' => 'Create minified versions',
      '--force' => 'Overwrite existing files',
    ];
  }
}
