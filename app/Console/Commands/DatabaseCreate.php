<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabaseCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new MySQL database based on the configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $databaseName = config('database.connections.mysql.database');
        
        if (empty($databaseName)) {
            $this->error('Database name is not set in the configuration.');
            return Command::FAILURE;
        }
        
        try {
            // Connect to MySQL without specifying a database
            $pdo = new \PDO(
                'mysql:host=' . config('database.connections.mysql.host') . ';port=' . config('database.connections.mysql.port'),
                config('database.connections.mysql.username'),
                config('database.connections.mysql.password')
            );
            
            // Create the database if it doesn't exist
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s;',
                $databaseName,
                config('database.connections.mysql.charset', 'utf8mb4'),
                config('database.connections.mysql.collation', 'utf8mb4_unicode_ci')
            ));
            
            $this->info(sprintf('Successfully created database: `%s`', $databaseName));
            
            // Also create the testing database if we're in a development environment
            if (app()->environment('local', 'development', 'testing')) {
                $testingDatabaseName = $databaseName . '_testing';
                
                $pdo->exec(sprintf(
                    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s;',
                    $testingDatabaseName,
                    config('database.connections.mysql.charset', 'utf8mb4'),
                    config('database.connections.mysql.collation', 'utf8mb4_unicode_ci')
                ));
                
                $this->info(sprintf('Successfully created testing database: `%s`', $testingDatabaseName));
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error(sprintf('Failed to create database: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
