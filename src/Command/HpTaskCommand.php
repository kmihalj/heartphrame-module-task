<?php

declare(strict_types=1);

namespace AaiEduHr\HeartPhrameModuleTask\Command;

use HeartPhrame\Config\ConfigInterface;
use RuntimeException;

use function array_slice;
use function array_values;
use function date;
use function file_get_contents;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_string;
use function mkdir;
use function rtrim;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * HR: Kopira jedinu početnu Task migraciju u host aplikaciju.
 * EN: Copies the single initial Task migration into a host application.
 */
final readonly class HpTaskCommand
{
    private const TEMPLATE = 'resources/migrations/initial_task_schema.php';

    /**
     * HR: Prima host konfiguraciju za razrješavanje app roota.
     * EN: Receives host configuration for resolving the application root.
     */
    public function __construct(private ConfigInterface $config)
    {
    }

    /**
     * HR: Usmjerava podnaredbe install i help.
     * EN: Routes the install and help subcommands.
     *
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    public function run(array $arguments = [], array $options = []): int
    {
        $subcommand = strtolower(trim((string)($arguments[0] ?? 'help')));

        return match ($subcommand) {
            'install', 'install-migration', 'migration:install' =>
                $this->installMigration(array_values(array_slice($arguments, 1)), $options),
            'help', '--help', '-h' => $this->help(),
            default => $this->unknown($subcommand),
        };
    }

    /**
     * HR: Kopira predložak bez naknadnih parcijalnih migracija.
     * EN: Copies the template without creating follow-up partial migrations.
     *
     * @param array<int, string> $arguments
     * @param array<string, mixed> $options
     */
    public function installMigration(array $arguments = [], array $options = []): int
    {
        $template = dirname(__DIR__, 2) . '/' . self::TEMPLATE;
        if (!is_file($template)) {
            throw new RuntimeException(__('Početna Task migracija nije pronađena.'));
        }

        $directory = $this->targetDirectory($options);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(__('Nije moguće kreirati direktorij migracija.'));
        }

        $target = rtrim($directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . date('YmdHis')
            . '_install_task_module_schema.php';
        $content = file_get_contents($template);
        if (!is_string($content) || file_put_contents($target, $content) === false) {
            throw new RuntimeException(__('Nije moguće kopirati početnu Task migraciju.'));
        }

        echo __('Kreirana je početna Task migracija: ') . $target . PHP_EOL;
        echo __('Sljedeći korak: pokreni `vendor/bin/hph orm-migrate:up`.') . PHP_EOL;

        return 0;
    }

    /**
     * HR: Ispisuje kratku dvojezičnu pomoć naredbe.
     * EN: Prints concise bilingual command help.
     */
    public function help(): int
    {
        echo 'hph task <install|help>' . PHP_EOL;

        return 0;
    }

    /**
     * HR: Vraća grešku za nepoznatu podnaredbu.
     * EN: Returns an error for an unknown subcommand.
     */
    private function unknown(string $subcommand): int
    {
        echo __('Nepoznata Task podnaredba: ') . $subcommand . PHP_EOL;

        return 1;
    }

    /**
     * HR: Razrješava ciljni migracijski direktorij iz opcije ili app roota.
     * EN: Resolves the target migration directory from an option or app root.
     *
     * @param array<string, mixed> $options
     */
    private function targetDirectory(array $options): string
    {
        $path = is_scalar($options['path'] ?? null) ? trim((string)$options['path']) : '';
        if ($path === '') {
            return rtrim($this->config->getAppRootDir(), DIRECTORY_SEPARATOR)
                . '/database/migrations';
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR)
            ? rtrim($path, DIRECTORY_SEPARATOR)
            : rtrim($this->config->getAppRootDir(), DIRECTORY_SEPARATOR) . '/' . trim($path, '/');
    }
}
