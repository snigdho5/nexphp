<?php

declare(strict_types=1);

namespace Nex\Console;

class Output
{
    public function writeln(string $message = ''): void
    {
        echo $message . PHP_EOL;
    }

    public function info(string $message): void
    {
        echo "\033[34m[INFO]\033[0m " . $message . PHP_EOL;
    }

    public function success(string $message): void
    {
        echo "\033[32m[SUCCESS]\033[0m " . $message . PHP_EOL;
    }

    public function error(string $message): void
    {
        echo "\033[31m[ERROR]\033[0m " . $message . PHP_EOL;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $colWidths = [];

        foreach ($headers as $i => $header) {
            $colWidths[$i] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, strlen((string) $cell));
            }
        }

        // Print header
        $headerLine = '';
        $separator = '';
        foreach ($headers as $i => $header) {
            $width = $colWidths[$i];
            $headerLine .= '| ' . str_pad($header, $width) . ' ';
            $separator .= '+-' . str_repeat('-', $width) . '-';
        }
        $headerLine .= '|';
        $separator .= '+';

        $this->writeln($separator);
        $this->writeln($headerLine);
        $this->writeln($separator);

        foreach ($rows as $row) {
            $rowLine = '';
            foreach ($row as $i => $cell) {
                $width = $colWidths[$i];
                $rowLine .= '| ' . str_pad((string) $cell, $width) . ' ';
            }
            $rowLine .= '|';
            $this->writeln($rowLine);
        }

        $this->writeln($separator);
    }
}
