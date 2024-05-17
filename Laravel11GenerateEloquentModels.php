This is to generate the Eloquent Models within Laravel 11
Command: generate:models


Retrieves the list of databases.
Iterates through each database, updating the database connection dynamically.
For each database, retrieves the list of tables.
For each table, generates an Eloquent model by creating a PHP class file in the appropriate namespace and directory.


<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenerateModels extends Command
{
    protected $signature = 'generate:models';
    protected $description = 'Generate Eloquent models for all tables in all databases';

    /**
     * Executes the generation of Eloquent models for all tables in all databases.
     *
     * @throws \Exception An error occurred during model generation.
     */
    public function handle() {
        $databases = $this->getDatabases();

        foreach ($databases as $databaseName) {
            $this->info("Generating models for database: $databaseName");
            Config::set('database.connections.mysql.database', $databaseName);
            DB::purge('mysql');
            DB::reconnect('mysql');

            try {
                //Pass the string query directly
                $tables = DB::select('SHOW TABLES');
                foreach ($tables as $key => $table) {
                    $tables[$key] = reset($table);
                }

                foreach ($tables as $tableName) {
                    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $tableName)));

                    // Generate the Eloquent model file for the table
                    $this->generateEloquentModel($tableName, $className, $databaseName);
                }

                $this->info('Eloquent models generated successfully for '.$databaseName);
            } catch (\Exception $e) {
                $this->error("An error occurred in $databaseName: ".$e->getMessage());
            }
        }
    }

    /**
     * Retrieves the list of databases available in the MySQL server.
     *
     * @return array The array of database names.
     */
    function getDatabases() {
        $databases = DB::select('SHOW DATABASES');

        return array_map(function ($db) {
            return $db->Database;
        }, $databases);
    }

    /**
     * Fetches column names for the table, generates an Eloquent model file, and saves it.
     *
     * @param  mixed  $tableName  The name of the table.
     * @param  mixed  $className  The name of the class.
     * @param  mixed  $databaseName  The name of the database.
     *
     * @return void
     */
    function generateEloquentModel($tableName, $className, $databaseName) {
        // Fetch column names for the table
        $columns = Schema::getColumnListing($tableName);
        $primaryKey = $this->getPrimaryKey($tableName);

        $fillableArrayString = "protected \$fillable = ['".implode("', '", $columns)."'];\n";
        $primaryKeyString = "protected \$primaryKey = '$primaryKey';\n";

        $modelStr
            = "<?php\n\n// Author: Austen Green\n\nnamespace App\Models\\$databaseName;\n\nuse Illuminate\Database\Eloquent\Model;\n\n";
        $modelStr .= "class $className extends Model {\n";
        $modelStr .= "    protected \$table = '$tableName';\n";
        $modelStr .= $primaryKeyString;
        $modelStr .= "    $fillableArrayString";
        $modelStr .= "}\n";

        $directoryPath = app_path("Models/$databaseName");
        if ( ! file_exists($directoryPath)) {
            mkdir($directoryPath, 0755, true);
        }

        $filePath = "$directoryPath/$className.php";
        file_put_contents($filePath, $modelStr);
        $this->info("Model generated for $tableName: $filePath");
    }

    /**
     * Fetches the primary key column name for a given table.
     *
     * @param  mixed  $tableName  The name of the table.
     *
     * @return string The primary key column name or 'id' if no primary key is found.
     */
    function getPrimaryKey($tableName) {
        $keys = DB::select('SHOW KEYS FROM `'.$tableName.'` WHERE Key_name = "PRIMARY"');

        return $keys[0]->Column_name ?? 'id'; // Assuming 'id' as default if no PK is found
    }

}
