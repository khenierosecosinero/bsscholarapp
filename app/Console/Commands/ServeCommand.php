<?php

namespace App\Console\Commands;

use App\Support\LanAddress;
use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;
use Illuminate\Support\Stringable;

use function Termwind\terminal;

class ServeCommand extends BaseServeCommand
{
    /**
     * Flush the output buffer.
     */
    protected function flushOutputBuffer()
    {
        $lines = (new Stringable($this->outputBuffer))->explode("\n");

        $this->outputBuffer = (string) $lines->pop();

        $lines
            ->map(fn ($line) => trim($line))
            ->filter()
            ->each(function ($line) {
                if ((new Stringable($line))->contains('Development Server (http')) {
                    if ($this->serverRunningHasBeenDisplayed === false) {
                        $this->serverRunningHasBeenDisplayed = true;

                        $port = $this->port();
                        $bound = $this->host();

                        if (LanAddress::isWildcard($bound)) {
                            $network = LanAddress::ipv4();
                            $this->components->info("Server running on [http://{$network}:{$port}].");
                            $this->line("  <fg=gray>Local:</>   http://127.0.0.1:{$port}");
                            $this->line("  <fg=gray>Network:</> http://{$network}:{$port}");
                        } else {
                            $this->components->info("Server running on [http://{$bound}:{$port}].");
                        }

                        $this->comment('  <fg=yellow;options=bold>Press Ctrl+C to stop the server</>');
                        $this->newLine();
                    }

                    return;
                }

                if ((new Stringable($line))->contains(' Accepted')) {
                    $requestPort = static::getRequestPortFromLine($line);

                    $this->requestsPool[$requestPort] = [
                        $this->getDateFromLine($line),
                        $this->requestsPool[$requestPort][1] ?? false,
                        microtime(true),
                    ];
                } elseif ((new Stringable($line))->contains([' [200]: GET '])) {
                    $requestPort = static::getRequestPortFromLine($line);

                    $this->requestsPool[$requestPort][1] = trim(explode('[200]: GET', $line)[1]);
                } elseif ((new Stringable($line))->contains('URI:')) {
                    $requestPort = static::getRequestPortFromLine($line);

                    $this->requestsPool[$requestPort][1] = trim(explode('URI: ', $line)[1]);
                } elseif ((new Stringable($line))->contains(' Closing')) {
                    $requestPort = static::getRequestPortFromLine($line);

                    if (empty($this->requestsPool[$requestPort]) || count($this->requestsPool[$requestPort] ?? []) !== 3) {
                        $this->requestsPool[$requestPort] = [
                            $this->getDateFromLine($line),
                            false,
                            microtime(true),
                        ];
                    }

                    [$startDate, $file, $startMicrotime] = $this->requestsPool[$requestPort];

                    $formattedStartedAt = $startDate->format('Y-m-d H:i:s');

                    unset($this->requestsPool[$requestPort]);

                    [$date, $time] = explode(' ', $formattedStartedAt);

                    $this->output->write("  <fg=gray>$date</> $time");

                    $runTime = $this->runTimeForHumans($startMicrotime);

                    if ($file) {
                        $this->output->write($file = " $file");
                    }

                    $dots = max(terminal()->width() - mb_strlen($formattedStartedAt) - mb_strlen($file) - mb_strlen($runTime) - 9, 0);

                    $this->output->write(' '.str_repeat('<fg=gray>.</>', $dots));
                    $this->output->writeln(" <fg=gray>~ {$runTime}</>");
                } elseif ((new Stringable($line))->contains(['Closed without sending a request', 'Failed to poll event'])) {
                    //
                } elseif (! empty($line)) {
                    if ((new Stringable($line))->startsWith('[')) {
                        $line = (new Stringable($line))->after('] ');
                    }

                    $this->output->writeln("  <fg=gray>$line</>");
                }
            });
    }
}
