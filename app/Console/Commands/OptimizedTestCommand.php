<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class OptimizedTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:optimized 
                            {filter? : The filter to apply to the tests}
                            {--memory=1G : Memory limit for tests}
                            {--exclude= : Tests to exclude}
                            {--debug : Enable debug mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run tests with optimized memory settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filter = $this->argument('filter');
        $memory = $this->option('memory');
        $exclude = $this->option('exclude');
        $debug = $this->option('debug');
        
        // Build the command
        $command = ['php', "-d", "memory_limit={$memory}"];
        
        if ($debug) {
            $command[] = "-d";
            $command[] = "xdebug.mode=debug";
        }
        
        $command[] = "artisan";
        $command[] = "test";
        
        if ($filter) {
            $command[] = "--filter={$filter}";
        }
        
        if ($exclude) {
            $command[] = "--exclude={$exclude}";
        }
        
        // Run the tests in a separate process
        $process = new Process($command);
        $process->setTimeout(300); // 5 minutes timeout
        
        $this->info("Running tests with memory limit: {$memory}");
        
        // Stream output
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });
        
        return $process->getExitCode();
    }
}
