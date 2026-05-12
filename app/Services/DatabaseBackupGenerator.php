<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PDO;

class DatabaseBackupGenerator
{
    /**
     * Runtime tables should be recreated on restore, but their temporary rows
     * should not travel with an application backup.
     *
     * @var list<string>
     */
    private const DATA_EXCLUDED_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'sessions',
    ];

    public function __construct(
        private readonly ApplicationSettings $settings,
    ) {}

    public function filename(): string
    {
        return sprintf(
            'lineup-database-backup-%s.sql',
            Carbon::now($this->settings->timezone())->format('Ymd-His'),
        );
    }

    public function generate(): string
    {
        return $this->header()
            ."\nSET FOREIGN_KEY_CHECKS=0;\n\n"
            .$this->tablesDump()
            ."SET FOREIGN_KEY_CHECKS=1;\n";
    }

    private function header(): string
    {
        $timezone = $this->settings->timezone();

        return implode("\n", [
            '-- LineUp database backup',
            '-- Application: '.$this->sanitizeCommentValue($this->settings->displayName()),
            '-- App version: '.$this->sanitizeCommentValue((string) config('app.version', 'dev')),
            '-- Repository: '.$this->sanitizeCommentValue((string) config('app.repository_url', '')),
            '-- Generated at: '.Carbon::now($timezone)->format('Y-m-d H:i:s').' '.$timezone,
            '-- Database: '.$this->sanitizeCommentValue($this->databaseName()),
        ])."\n";
    }

    private function tablesDump(): string
    {
        $dump = '';

        foreach ($this->tables() as $table) {
            $dump .= $this->tableDump($table)."\n";
        }

        return $dump;
    }

    private function tableDump(string $table): string
    {
        $quotedTable = $this->quoteIdentifier($table);
        $createTable = $this->createTableStatement($table);
        $dump = "--\n-- Table structure for table {$quotedTable}\n--\n\n";
        $dump .= "DROP TABLE IF EXISTS {$quotedTable};\n";
        $dump .= $createTable.";\n\n";

        if ($this->shouldExcludeData($table)) {
            return $dump."-- Data for table {$quotedTable} was excluded from this backup.\n";
        }

        $rows = DB::table($table)->orderBy($this->firstColumn($table))->get();

        if ($rows->isEmpty()) {
            return $dump;
        }

        $dump .= "--\n-- Data for table {$quotedTable}\n--\n\n";

        foreach ($rows as $row) {
            $values = array_map(
                fn (mixed $value): string => $this->quoteValue($value),
                array_values((array) $row),
            );

            $dump .= sprintf(
                "INSERT INTO %s (%s) VALUES (%s);\n",
                $quotedTable,
                implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column), array_keys((array) $row))),
                implode(', ', $values),
            );
        }

        return $dump."\n";
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return collect(DB::select(
            'select table_name from information_schema.tables where table_schema = database() and table_type = ? order by table_name',
            ['BASE TABLE'],
        ))
            ->map(function (object $row): string {
                $values = (array) $row;

                return (string) array_values($values)[0];
            })
            ->values()
            ->all();
    }

    private function createTableStatement(string $table): string
    {
        $statement = DB::selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($table));
        $values = (array) $statement;

        return (string) ($values['Create Table'] ?? array_values($values)[1]);
    }

    private function firstColumn(string $table): string
    {
        $column = DB::selectOne('SHOW COLUMNS FROM '.$this->quoteIdentifier($table));

        return (string) $column->Field;
    }

    private function databaseName(): string
    {
        return (string) DB::selectOne('select database() as database_name')->database_name;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return DB::connection()->getPdo()->quote((string) $value, PDO::PARAM_STR);
    }

    private function sanitizeCommentValue(string $value): string
    {
        return str_replace(["\r", "\n"], ' ', $value);
    }

    private function shouldExcludeData(string $table): bool
    {
        return in_array($table, self::DATA_EXCLUDED_TABLES, true);
    }
}
