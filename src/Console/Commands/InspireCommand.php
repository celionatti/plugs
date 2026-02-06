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
        ["Talk is cheap. Show me the code.", "Linus Torvalds"],
        ["Software is a great combination between artistry and engineering.", "Bill Gates"],
        ["The best error message is the one that never shows up.", "Thomas Fuchs"],
        ["Always code as if the guy who ends up maintaining your code will be a violent psychopath who knows where you live.", "John Woods"],
        ["Don't comment bad code—rewrite it.", "Brian Kernighan"],
        ["One man's constant is another man's variable.", "Alan Perlis"],
        ["Perfection is achieved, not when there is nothing more to add, but when there is nothing left to take away.", "Antoine de Saint-Exupéry"],
        ["Design is not just what it looks like and feels like. Design is how it works.", "Steve Jobs"],
        ["The best way to predict the future is to invent it.", "Alan Kay"],
        ["Computers are good at following instructions, but not at reading your mind.", "Donald Knuth"],
        ["Simplicity is the ultimate sophistication.", "Leonardo da Vinci"],
        ["Testing leads to failure, and failure leads to understanding.", "Burt Rutan"],
        ["Programs must be written for people to read, and only incidentally for machines to execute.", "Harold Abelson"],
        ["The most disastrous thing that you can ever learn is your first programming language.", "Alan Kay"],
        ["The most important property of a program is whether it accomplishes the intention of its user.", "C.A.R. Hoare"],
    ];

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->title('Framework Inspiration');

        $quote = $this->quotes[array_rand($this->quotes)];

        $this->newLine();
        $this->quote($quote[0], $quote[1]);
        $this->newLine();

        $this->checkpoint('finished');

        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return 0;
    }
}
