<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

/*
|--------------------------------------------------------------------------
| Make: Inspire Command
|--------------------------------------------------------------------------
*/

use Plugs\Console\Command;

class InspireCommand extends Command
{
    protected string $description = 'Display an inspiring quote';

    private array $quotes = [
        ["The only way to do great work is to love what you do.", "Steve Jobs"],
        ["Code is like humor. When you have to explain it, it's bad.", "Cory House"],
        ["First, solve the problem. Then, write the code.", "John Johnson"],
        ["Experience is the name everyone gives to their mistakes.", "Oscar Wilde"],
        ["In order to be irreplaceable, one must always be different.", "Coco Chanel"],
        ["Knowledge is power.", "Francis Bacon"],
        ["Simplicity is the soul of efficiency.", "Austin Freeman"],
        ["Make it work, make it right, make it fast.", "Kent Beck"],
        ["Clean code always looks like it was written by someone who cares.", "Robert C. Martin"],
        ["Any fool can write code that a computer can understand. Good programmers write code that humans can understand.", "Martin Fowler"],
    ];

    public function handle(): int
    {
        $quote = $this->quotes[array_rand($this->quotes)];

        $this->newLine();
        $this->quote($quote[0], $quote[1]);
        $this->newLine();

        return 0;
    }
}
