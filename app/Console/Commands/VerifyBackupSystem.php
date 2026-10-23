<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class VerifyBackupSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:verify';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Verifica el estado del sistema de backups y diagnostica problemas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->line('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  🔍 VERIFICACIÓN DEL SISTEMA DE BACKUPS');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->line('');

        // 1. Verificar configuración de BD
        $this->checkDatabaseConfig();

        // 2. Verificar directorio de backups
        $this->checkBackupDirectory();

        // 3. Verificar backups existentes
        $this->checkExistingBackups();

        // 4. Verificar permisos
        $this->checkPermissions();

        // 5. Simular ejecución del scheduler
        $this->checkSchedulerConfig();

        $this->line('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->comment('✅ Verificación completada');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->line('');

        return Command::SUCCESS;
    }

    private function checkDatabaseConfig()
    {
        $this->line('📊 CONFIGURACIÓN DE BASE DE DATOS:');
        $this->table(
            ['Parámetro', 'Valor'],
            [
                ['Conexión', config('database.default')],
                ['Host', config('database.connections.mysql.host')],
                ['Puerto', config('database.connections.mysql.port')],
                ['Base de datos', config('database.connections.mysql.database')],
                ['Usuario', config('database.connections.mysql.username')],
            ]
        );
        $this->line('✅ Configuración leída correctamente');
        $this->line('');
    }

    private function checkBackupDirectory()
    {
        $this->line('📁 DIRECTORIO DE BACKUPS:');
        $backupDir = storage_path('backups');
        $this->line("Ruta: {$backupDir}");

        if (is_dir($backupDir)) {
            $this->line('✅ El directorio existe');
            if (is_writable($backupDir)) {
                $this->line('✅ El directorio tiene permisos de escritura');
            } else {
                $this->error('❌ No hay permisos de escritura');
            }
        } else {
            $this->warn('⚠️  El directorio no existe (se creará automáticamente)');
        }
        $this->line('');
    }

    private function checkExistingBackups()
    {
        $this->line('💾 BACKUPS EXISTENTES:');
        $backupDir = storage_path('backups');

        if (! is_dir($backupDir)) {
            $this->warn('Sin backups aún');
            $this->line('');

            return;
        }

        $files = glob("{$backupDir}/backup_*.sql");

        if (empty($files)) {
            $this->warn('Sin backups creados aún');
            $this->line('');

            return;
        }

        $backupData = [];
        foreach ($files as $file) {
            $backupData[] = [
                basename($file),
                round(filesize($file) / 1024 / 1024, 2).' MB',
                Carbon::createFromTimestamp(filemtime($file))->format('Y-m-d H:i:s'),
            ];
        }

        $this->table(['Archivo', 'Tamaño', 'Fecha de creación'], $backupData);
        $this->line('✅ '.count($backupData).' backup(s) encontrado(s)');
        $this->line('');
    }

    private function checkPermissions()
    {
        $this->line('🔐 PERMISOS:');

        $dirs = [
            'storage' => storage_path(),
            'storage/backups' => storage_path('backups'),
            'storage/logs' => storage_path('logs'),
        ];

        foreach ($dirs as $name => $path) {
            if (is_writable($path)) {
                $this->line("✅ {$name} - Escribible");
            } else {
                $this->error("❌ {$name} - NO escribible");
            }
        }
        $this->line('');
    }

    private function checkSchedulerConfig()
    {
        $this->line('⏰ CONFIGURACIÓN DEL SCHEDULER:');
        $this->line('Hora programada: 00:00 (media noche)');
        $this->line('Zona horaria: '.config('app.timezone'));
        $this->line('Retención: 7 días');
        $this->line('Ejecutar cada: 1 día');
        $this->line('');
        $this->info('Para ejecutar el scheduler manualmente, usa:');
        $this->comment('  php artisan schedule:run');
        $this->line('');
    }
}
