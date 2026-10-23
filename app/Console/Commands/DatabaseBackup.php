<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DatabaseBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup {--force : Fuerza la copia incluso si ya existe}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Realiza una copia de seguridad de la base de datos MySQL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            // Obtener credenciales de la base de datos
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');

            // Crear directorio de backups si no existe
            $backupDir = storage_path('backups');
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Generar nombre del archivo con timestamp
            $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
            $backupFile = "{$backupDir}/backup_{$database}_{$timestamp}.sql";

            // Construir comando mysqldump
            $command = [
                'mysqldump',
                "--host={$host}",
                "--port={$port}",
                "--user={$username}",
                "--password={$password}",
                $database,
            ];

            // Ejecutar el comando usando Symfony Process
            $this->info('⏳ Iniciando copia de seguridad...');

            try {
                $process = new Process($command);
                $process->setTimeout(300); // 5 minutos timeout
                $process->run();

                // Guardar output en archivo
                if ($process->isSuccessful()) {
                    file_put_contents($backupFile, $process->getOutput());
                    $returnCode = 0;
                } else {
                    $this->error('Error en mysqldump: '.$process->getErrorOutput());
                    $returnCode = 1;
                }
            } catch (Exception $e) {
                $this->error('Error ejecutando mysqldump: '.$e->getMessage());
                $returnCode = 1;
            }

            // Verificar si fue exitoso
            if ($returnCode === 0 && file_exists($backupFile)) {
                $fileSize = round(filesize($backupFile) / 1024 / 1024, 2); // Tamaño en MB
                $this->info('✅ Copia de seguridad completada exitosamente!');
                $this->line("📁 Archivo: {$backupFile}");
                $this->line("💾 Tamaño: {$fileSize} MB");

                // Limpiar backups antiguos (mantener solo los últimos 7 días)
                $this->cleanOldBackups($backupDir);

                return Command::SUCCESS;
            } else {
                $this->error('❌ Error al crear la copia de seguridad');
                $this->error('Código de error: '.$returnCode);

                return Command::FAILURE;
            }
        } catch (Exception $e) {
            $this->error('❌ Excepción: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Eliminar backups más antiguos de 7 días
     */
    private function cleanOldBackups($backupDir)
    {
        $files = glob("{$backupDir}/backup_*.sql");
        $sevenDaysAgo = Carbon::now()->subDays(7)->timestamp;

        foreach ($files as $file) {
            if (filemtime($file) < $sevenDaysAgo) {
                unlink($file);
                $this->line('🗑️  Backup antiguo eliminado: '.basename($file));
            }
        }
    }
}
